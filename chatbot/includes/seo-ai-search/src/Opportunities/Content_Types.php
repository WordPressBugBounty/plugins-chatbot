<?php
/**
 * Opportunities that need only the site's own content (round 2, R3), so the
 * list fills within minutes of installing, with or without Search Console:
 * orphan pages, internal links to add, pages with no focus keyword, no search
 * description or a duplicate title, thin and outdated pages, pages competing
 * for the same focus keyword, missing business details, WooCommerce products
 * without a description and categories without an introduction, key pages
 * that don't answer questions, and AI topic gaps (on a click; labelled AI
 * suggestions, with no numbers).
 *
 * Most signals come from the stored page audits (the first scan runs them);
 * each opportunity is ranked by the page's importance, plus Search Console
 * impressions when they are there.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Opportunities;

use QuantumCloud\SeoAiSearch\Local\Locations;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\SeoAiSearch\Research\Research;
use QuantumCloud\SeoAiSearch\Research\Research_View;
use QuantumCloud\SeoAiSearch\Research\Schema_Guard;
use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Content-only opportunity types.
 */
final class Content_Types {

	const GAPS = 'qcld_wpbot_seo_topic_gaps';
	const CAP  = 30;

	/**
	 * Stored audits of published pages (cached per rebuild).
	 *
	 * @var array|null
	 */
	private static $audits = null;

	/**
	 * Search Console impressions per URL (cached per rebuild).
	 *
	 * @var array|null
	 */
	private static $impressions = null;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_seo/opportunity_types', array( __CLASS__, 'types' ) );
		add_filter( 'qcld_wpbot_seo/opportunity_reason', array( __CLASS__, 'reason' ), 10, 3 );
		// A finished page audit (first scan, publish) refreshes the list shortly after.
		add_action( 'qcld_wpbot_seo/audit_recorded', array( Site_Types::class, 'soon' ) );
		add_action( 'qcld_wpbot_seo/opportunities_rebuild_start', array( __CLASS__, 'reset' ) );
	}

	/**
	 * Add the types.
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public static function types( $types ) {
		$defs = array(
			'orphan_page'         => array( __( 'Orphan page', 'chatbot' ), 'orphans', 'edit_page' ),
			'internal_link'       => array( __( 'Internal link to add', 'chatbot' ), 'internal_links', 'edit_page' ),
			'no_focus_keyword'    => array( __( 'No focus keyword', 'chatbot' ), 'no_focus', 'fix_page' ),
			'missing_description' => array( __( 'No search description', 'chatbot' ), 'no_description', 'fix_page' ),
			'duplicate_title'     => array( __( 'Duplicate title', 'chatbot' ), 'duplicate_titles', 'fix_page' ),
			'thin_page'           => array( __( 'Thin page', 'chatbot' ), 'thin', 'improve' ),
			'outdated'            => array( __( 'Outdated content', 'chatbot' ), 'outdated', 'refresh' ),
			'competing_focus'     => array( __( 'Pages competing for one keyword', 'chatbot' ), 'competing', 'improve' ),
			'missing_schema'      => array( __( 'Missing business details', 'chatbot' ), 'schema', 'local_seo' ),
			'woo_no_description'  => array( __( 'Product without a description', 'chatbot' ), 'woo_products', 'improve' ),
			'woo_category_intro'  => array( __( 'Category without an introduction', 'chatbot' ), 'woo_categories', 'edit_term' ),
			'ai_answers'          => array( __( 'AI search readiness', 'chatbot' ), 'ai_answers', 'improve' ),
			'ai_topic_gap'        => array( __( 'Topic gap (AI suggestion)', 'chatbot' ), 'topic_gaps', 'write' ),
			'llms_txt'            => array( __( 'llms.txt for AI search', 'chatbot' ), 'llms', 'technical' ),
		);
		foreach ( $defs as $id => $def ) {
			$types[ $id ] = array(
				'label'    => $def[0],
				'callback' => array( __CLASS__, $def[1] ),
				'action'   => $def[2],
			);
		}
		return $types;
	}

	/**
	 * Stored audits of published pages: post_id => audit.
	 *
	 * @return array
	 */
	public static function audits() {
		if ( null !== self::$audits ) {
			return self::$audits;
		}
		self::$audits = array();
		$ids          = get_posts(
			array(
				'post_type'      => Audit::post_types(),
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Ids only, audited pages; bounded.
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only audited pages; bounded.
				'meta_key'       => Audit::META,
				'orderby'        => 'modified',
			)
		);
		// One query for all their meta (audits, SEO titles, descriptions, focus keywords).
		update_meta_cache( 'post', $ids );
		_prime_post_caches( $ids, false, false );
		foreach ( $ids as $post_id ) {
			$stored = Audit::stored( $post_id );
			if ( $stored ) {
				self::$audits[ (int) $post_id ] = $stored;
			}
		}
		return self::$audits;
	}

	/**
	 * Forget cached audits (each rebuild reads them again).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$audits      = null;
		self::$impressions = null;
		Page_Importance::reset();
	}

	/**
	 * Status of one check in a stored audit ('' when the audit has no such check).
	 *
	 * @param array  $audit Audit.
	 * @param string $id    Check id.
	 * @return string
	 */
	private static function check( array $audit, $id ) {
		foreach ( isset( $audit['checks'] ) ? (array) $audit['checks'] : array() as $check ) {
			if ( isset( $check['id'] ) && $id === $check['id'] ) {
				return isset( $check['status'] ) ? (string) $check['status'] : '';
			}
		}
		return '';
	}

	/**
	 * One page candidate.
	 *
	 * @param string $type    Type.
	 * @param int    $post_id Post.
	 * @param float  $base    Base value.
	 * @param int    $effort  Effort 1–3.
	 * @param string $action  Action.
	 * @param array  $context Opportunity context.
	 * @param array  $numbers Extra numbers.
	 * @param string $ref     Reference (default type:post).
	 * @return array
	 */
	private static function page( $type, $post_id, $base, $effort, $action, array $context, array $numbers = array(), $ref = '' ) {
		if ( null === self::$impressions ) {
			self::$impressions = Page_Importance::impressions( $context );
		}
		$importance = Page_Importance::of( $post_id );
		return array(
			'type'      => $type,
			'ref'       => '' !== $ref ? $ref : $type . ':' . $post_id,
			'post_id'   => (int) $post_id,
			'url'       => (string) get_permalink( $post_id ),
			'potential' => Page_Importance::potential( $base, $post_id, self::$impressions ),
			'effort'    => $effort,
			'action'    => $action,
			'numbers'   => array_merge(
				array(
					'importance' => $importance['score'],
					'why'        => implode( ', ', $importance['why'] ),
					'edit'       => (string) get_edit_post_link( $post_id, 'raw' ),
				),
				$numbers
			),
		);
	}

	/**
	 * Best first, capped.
	 *
	 * @param array $items Candidates.
	 * @return array
	 */
	private static function top( array $items ) {
		usort(
			$items,
			static function ( $a, $b ) {
				// Equal value: newer pages first.
				return array( $b['potential'], isset( $b['post_id'] ) ? $b['post_id'] : 0 ) <=> array( $a['potential'], isset( $a['post_id'] ) ? $a['post_id'] : 0 );
			}
		);
		/**
		 * Most opportunities kept per content type.
		 *
		 * @param int $cap Default 30.
		 */
		return array_slice( $items, 0, max( 1, (int) apply_filters( 'qcld_wpbot_seo/content_opportunity_cap', self::CAP ) ) );
	}

	/**
	 * Pages no other page links to (not the home page, not menu pages).
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function orphans( $context ) {
		$out   = array();
		$front = (int) get_option( 'page_on_front' );
		$menu  = Page_Importance::menu_posts();
		$links = Page_Importance::inbound_counts();
		foreach ( self::audits() as $post_id => $audit ) {
			// Both the stored audit and today's link count must agree.
			if ( $post_id === $front || in_array( $post_id, $menu, true ) || 'fail' !== self::check( $audit, 'links_in' ) || ! empty( $links[ $post_id ] ) ) {
				continue;
			}
			$out[] = self::page( 'orphan_page', $post_id, 8, 1, 'edit_page', (array) $context );
		}
		return self::top( $out );
	}

	/**
	 * Links a topic cluster is missing (pillar to page and back).
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function internal_links( $context ) {
		if ( ! class_exists( '\QuantumCloud\WPBotCore\Map\Topic_Map' ) ) {
			return array();
		}
		$out = array();
		foreach ( \QuantumCloud\WPBotCore\Map\Topic_Map::topics() as $topic ) {
			foreach ( \QuantumCloud\WPBotCore\Map\Topic_Map::missing_links( (int) $topic['id'] ) as $link ) {
				$to    = Keyword_Map::main_query( $link['to'] );
				$out[] = self::page(
					'internal_link',
					$link['from'],
					5,
					1,
					'edit_page',
					(array) $context,
					array(
						'to_title' => get_the_title( $link['to'] ),
						'to_url'   => (string) get_permalink( $link['to'] ),
						'anchor'   => $to ? (string) $to['query'] : get_the_title( $link['to'] ),
						'topic'    => (string) $topic['name'],
					),
					'internal_link:' . $link['from'] . ':' . $link['to']
				);
			}
		}
		return self::top( $out );
	}

	/**
	 * Pages with no focus keyword (no SEO plugin keyword, no owner choice, no Search Console search).
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function no_focus( $context ) {
		$out = array();
		foreach ( self::audits() as $post_id => $audit ) {
			if ( '' !== (string) ( isset( $audit['main_query'] ) ? $audit['main_query'] : '' ) || '' !== Seo_Bridge::read( $post_id )['focus_keyword'] ) {
				continue;
			}
			if ( Page_Importance::of( $post_id )['score'] < 20 ) {
				continue;
			}
			$out[] = self::page( 'no_focus_keyword', $post_id, 5, 1, 'fix_page', (array) $context );
		}
		return self::top( $out );
	}

	/**
	 * Pages with no search description.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function no_description( $context ) {
		$out = array();
		foreach ( array_keys( self::audits() ) as $post_id ) {
			if ( '' === trim( (string) Seo_Bridge::read( $post_id )['description'] ) ) {
				$out[] = self::page( 'missing_description', $post_id, 6, 1, 'fix_page', (array) $context );
			}
		}
		return self::top( $out );
	}

	/**
	 * Pages whose title another page also uses.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function duplicate_titles( $context ) {
		$out = array();
		foreach ( self::audits() as $post_id => $audit ) {
			if ( 'fail' === self::check( $audit, 'duplicate_title' ) ) {
				$out[] = self::page( 'duplicate_title', $post_id, 6, 1, 'fix_page', (array) $context );
			}
		}
		return self::top( $out );
	}

	/**
	 * Important pages with very little text.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function thin( $context ) {
		$out = array();
		foreach ( self::audits() as $post_id => $audit ) {
			if ( 'fail' !== self::check( $audit, 'thin_content' ) || Page_Importance::of( $post_id )['score'] < 30 ) {
				continue;
			}
			$out[] = self::page( 'thin_page', $post_id, 10, 2, 'improve', (array) $context, array( 'words' => isset( $audit['words'] ) ? (int) $audit['words'] : 0 ) );
		}
		return self::top( $out );
	}

	/**
	 * Posts not updated for a year, and pages with a past year in the title.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function outdated( $context ) {
		$out   = array();
		$year  = (int) current_time( 'Y' );
		$cut   = gmdate( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
		$posts = get_posts(
			array(
				'post_type'      => Audit::post_types(),
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded.
				'orderby'        => 'modified',
				'order'          => 'ASC',
			)
		);
		foreach ( $posts as $post ) {
			$old_year = preg_match( '/\b(20\d{2})\b/', $post->post_title, $m ) && (int) $m[1] < $year ? (int) $m[1] : 0;
			$stale    = 'post' === $post->post_type && $post->post_modified_gmt < $cut && Page_Importance::of( $post->ID )['score'] >= 20;
			if ( ! $old_year && ! $stale ) {
				continue;
			}
			$out[] = self::page(
				'outdated',
				$post->ID,
				8,
				2,
				'refresh',
				(array) $context,
				array(
					'modified' => substr( $post->post_modified, 0, 10 ),
					'old_year' => $old_year,
				)
			);
		}
		return self::top( $out );
	}

	/**
	 * Two or more pages with the same focus keyword (SEO plugin or the owner's choice). Vault keywords have their own type.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function competing( $context ) {
		$by = array();
		foreach ( array_keys( self::audits() ) as $post_id ) {
			$keyword = (string) Seo_Bridge::read( $post_id )['focus_keyword'];
			if ( '' === $keyword ) {
				$main    = Keyword_Map::main_query( $post_id );
				$keyword = $main && ( 'manual' === $main['source'] || ! empty( $main['locked'] ) ) ? (string) $main['query'] : '';
			}
			$keyword = Keyword_Map::normalise( $keyword );
			if ( '' !== $keyword ) {
				$by[ $keyword ][] = $post_id;
			}
		}
		$out = array();
		foreach ( $by as $keyword => $posts ) {
			if ( count( $posts ) < 2 || ( function_exists( 'qcld_wpbot_core_vault_query' ) && \QuantumCloud\WPBotCore\Vault\Vault::find( $keyword ) ) ) {
				continue;
			}
			usort(
				$posts,
				static function ( $a, $b ) {
					return Page_Importance::of( $b )['score'] <=> Page_Importance::of( $a )['score'];
				}
			);
			$keep = array_shift( $posts );
			foreach ( $posts as $post_id ) {
				$item          = self::page(
					'competing_focus',
					$post_id,
					10,
					2,
					'improve',
					(array) $context,
					array(
						'keep'     => get_the_title( $keep ),
						'stronger' => (string) get_permalink( $keep ),
						'pages'    => count( $posts ) + 1,
					)
				);
				$item['query'] = $keyword;
				$out[]         = $item;
			}
		}
		return self::top( $out );
	}

	/**
	 * Business details missing while the site has a contact or about page (no LocalBusiness data for search engines).
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function schema( $context ) {
		unset( $context );
		$main = Locations::main();
		if ( is_array( $main ) && '' !== (string) $main['name'] && '' !== (string) $main['city'] ) {
			return array();
		}
		$page = null;
		foreach ( array( 'contact', 'contact-us', 'about', 'about-us' ) as $slug ) {
			$found = get_page_by_path( $slug );
			if ( $found instanceof \WP_Post && 'publish' === $found->post_status ) {
				$page = $found;
				break;
			}
		}
		if ( ! $page ) {
			return array();
		}
		return array(
			array(
				'type'      => 'missing_schema',
				'ref'       => 'missing_schema:local',
				'post_id'   => (int) $page->ID,
				'url'       => (string) get_permalink( $page ),
				'potential' => 12,
				'effort'    => 1,
				'action'    => 'local_seo',
				'numbers'   => array( 'page' => get_the_title( $page ) ),
			),
		);
	}

	/**
	 * WooCommerce products with no description.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function woo_products( $context ) {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One bounded read of published products without text.
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND post_password = '' AND TRIM(post_content) = '' AND TRIM(post_excerpt) = '' ORDER BY ID DESC LIMIT 300" );
		$out = array();
		foreach ( $ids as $post_id ) {
			$out[] = self::page( 'woo_no_description', (int) $post_id, 10, 2, 'improve', (array) $context );
		}
		return self::top( $out );
	}

	/**
	 * Product categories with three or more products and no introduction.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function woo_categories( $context ) {
		unset( $context );
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 200,
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( (int) $term->count < 3 || '' !== trim( wp_strip_all_tags( (string) $term->description ) ) || 'uncategorized' === $term->slug ) {
				continue;
			}
			$out[] = array(
				'type'      => 'woo_category_intro',
				'ref'       => 'woo_category_intro:' . $term->term_id,
				'url'       => (string) get_term_link( $term ),
				'potential' => 6 + min( 10, (int) $term->count / 3 ),
				'effort'    => 1,
				'action'    => 'edit_term',
				'numbers'   => array(
					'term'     => $term->name,
					'products' => (int) $term->count,
					'edit'     => (string) get_edit_term_link( $term->term_id, 'product_cat', 'product' ),
				),
			);
		}
		return self::top( $out );
	}

	/**
	 * Key pages that don't answer questions clearly (no summary or FAQ).
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function ai_answers( $context ) {
		$out = array();
		foreach ( self::audits() as $post_id => $audit ) {
			if ( ! in_array( self::check( $audit, 'faq_summary' ), array( 'fail', 'warning' ), true ) || Page_Importance::of( $post_id )['score'] < 35 ) {
				continue;
			}
			$out[] = self::page( 'ai_answers', $post_id, 7, 2, 'improve', (array) $context );
		}
		return self::top( $out );
	}

	/**
	 * The llms.txt file: not set up, or the live file is not WPBot's.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function llms( $context ) {
		unset( $context );
		if ( ! class_exists( '\QuantumCloud\SeoAiSearch\Llms\Llms_Txt' ) ) {
			return array();
		}
		$state = \QuantumCloud\SeoAiSearch\Llms\Llms_Txt::enabled() ? \QuantumCloud\SeoAiSearch\Llms\Llms_Check::state()['status'] : 'off';
		if ( ! in_array( $state, array( 'off', 'stale_cache', 'other_source', 'missing' ), true ) ) {
			return array();
		}
		return array(
			array(
				'type'      => 'llms_txt',
				'ref'       => 'llms_txt:' . $state,
				'potential' => 'off' === $state ? 6 : 10,
				'effort'    => 1,
				'action'    => 'technical',
				'numbers'   => array( 'state' => $state ),
			),
		);
	}

	/**
	 * AI topic gaps the site has no page for yet.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function topic_gaps( $context ) {
		unset( $context );
		$stored = get_option( self::GAPS, array() );
		$out    = array();
		foreach ( isset( $stored['gaps'] ) ? (array) $stored['gaps'] : array() as $gap ) {
			$keyword = Keyword_Map::normalise( isset( $gap['keyword'] ) ? $gap['keyword'] : '' );
			if ( '' === $keyword || Research_View::page_for( $keyword ) ) {
				continue;
			}
			/**
			 * Numbers for an AI topic gap (Pro adds Keyword Planner volumes). Return an array; only real numbers with their source.
			 *
			 * @param array $numbers Numbers (ai => true).
			 * @param array $gap     topic, keyword, reason.
			 */
			$numbers = (array) apply_filters(
				'qcld_wpbot_seo/topic_gap_numbers',
				array(
					'ai'     => true,
					'topic'  => (string) $gap['topic'],
					'reason' => (string) $gap['reason'],
				),
				$gap
			);
			$volume  = isset( $numbers['volume'] ) && null !== $numbers['volume'] ? (int) $numbers['volume'] : null;
			$out[]   = array(
				'type'      => 'ai_topic_gap',
				'ref'       => 'ai_topic_gap:' . md5( $keyword ),
				'query'     => $keyword,
				'potential' => null !== $volume ? round( $volume * 0.05, 1 ) : 12,
				'effort'    => 3,
				'action'    => 'write',
				'numbers'   => $numbers,
			);
		}
		return self::top( $out );
	}

	/**
	 * Ask the AI for topics the site is missing (on a click; uses AI credits).
	 *
	 * @return array|WP_Error gaps, generated_at, cost.
	 */
	public static function find_gaps() {
		$ready = Research::ai_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$topics = array();
		if ( class_exists( '\QuantumCloud\WPBotCore\Map\Topic_Map' ) ) {
			foreach ( array_slice( \QuantumCloud\WPBotCore\Map\Topic_Map::topics(), 0, 40 ) as $topic ) {
				$topics[] = $topic['name'];
			}
		}
		$facts    = class_exists( '\QuantumCloud\WPBotCore\Competitors\Suggest' ) ? \QuantumCloud\WPBotCore\Competitors\Suggest::context() : 'Website: ' . home_url( '/' );
		$response = AI_Service::generate(
			Module::ID,
			'topic_gaps',
			Request::make(
				'You help a website owner find topics their site is missing: subjects their customers or readers search for that no page covers yet. You never invent numbers: search volumes, prices and percentages are not part of your answer. Answer with JSON only, exactly in the requested shape.',
				"Task: topic_gaps\n" . $facts . "\nTopics the site already covers: " . ( array() !== $topics ? implode( ' | ', $topics ) : 'none recorded' ) . "\n\nSuggest up to 10 missing topics, each with one search keyword a page about it should target, and one short sentence on why it fits this site.\n\nReturn JSON exactly in this shape:\n" . '{"gaps":[{"topic":"Winter bike storage","keyword":"how to store a bike in winter","reason":"Your customers commute all year."}]}',
				array(
					'max_tokens' => 1200,
					'json'       => true,
					'mode'       => 'strict',
				)
			),
			array( 'cache' => false )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$json = $response->json();
		$gaps = array();
		foreach ( is_array( $json ) && isset( $json['gaps'] ) ? array_slice( (array) $json['gaps'], 0, 10 ) : array() as $row ) {
			$keyword = is_array( $row ) && isset( $row['keyword'] ) ? Schema_Guard::keyword( (string) $row['keyword'] ) : '';
			if ( '' === $keyword ) {
				continue;
			}
			$gaps[] = array(
				'topic'   => substr( sanitize_text_field( isset( $row['topic'] ) ? (string) $row['topic'] : $keyword ), 0, 120 ),
				'keyword' => $keyword,
				// No numbers from the AI: digits are taken out of the reason.
				'reason'  => substr( trim( (string) preg_replace( '/\s+/', ' ', (string) preg_replace( '/\d[\d,.%]*/', '', sanitize_text_field( isset( $row['reason'] ) ? (string) $row['reason'] : '' ) ) ) ), 0, 200 ),
			);
		}
		$stored = array(
			'gaps'         => $gaps,
			'generated_at' => gmdate( 'c' ),
		);
		update_option( self::GAPS, $stored, false );
		/**
		 * New AI topic gaps (Pro looks up their search volumes before the list is rebuilt).
		 *
		 * @param array $gaps topic, keyword, reason.
		 */
		do_action( 'qcld_wpbot_seo/topic_gaps_found', $gaps );
		Opportunities::rebuild();
		return $stored + array( 'cost' => (float) $response->cost );
	}

	/**
	 * Reasons in plain words.
	 *
	 * @param string $reason  Reason.
	 * @param string $type    Type.
	 * @param array  $numbers Numbers.
	 * @return string
	 */
	public static function reason( $reason, $type, $numbers ) {
		$why = ! empty( $numbers['why'] ) ? ' ' . sprintf(
			/* translators: %s: why the page matters (in your menu, 5 pages link here). */
			__( 'It matters: %s.', 'chatbot' ),
			$numbers['why']
		) : '';
		switch ( $type ) {
			case 'orphan_page':
				return __( 'No other page on your site links here, so visitors and search engines struggle to find it. Link to it from a related page.', 'chatbot' ) . $why;
			case 'internal_link':
				/* translators: 1: page to link to, 2: suggested link text, 3: topic. */
				return sprintf( __( 'Link from this page to "%1$s" (for example with the words "%2$s"): both are in the topic "%3$s", and linked topics rank better together.', 'chatbot' ), $numbers['to_title'], $numbers['anchor'], $numbers['topic'] );
			case 'no_focus_keyword':
				return __( 'This page has no focus keyword, so nothing checks that it targets a search people make. Run Page Audit and pick one.', 'chatbot' ) . $why;
			case 'missing_description':
				return __( 'This page has no search description, so Google picks any text from the page for your result.', 'chatbot' ) . $why;
			case 'duplicate_title':
				return __( 'Another page uses the same title, so search engines can\'t tell which one to show.', 'chatbot' ) . $why;
			case 'thin_page':
				/* translators: %d: words on the page. */
				return sprintf( __( 'An important page with only %d words: it may not answer the search fully.', 'chatbot' ), (int) $numbers['words'] ) . $why;
			case 'outdated':
				if ( ! empty( $numbers['old_year'] ) ) {
					/* translators: %d: year in the title. */
					return sprintf( __( 'The title still says %d. Update the page and its title for this year.', 'chatbot' ), (int) $numbers['old_year'] ) . $why;
				}
				/* translators: %s: date last updated. */
				return sprintf( __( 'Not updated since %s. Refresh the facts, dates and examples.', 'chatbot' ), $numbers['modified'] ) . $why;
			case 'competing_focus':
				/* translators: 1: number of pages, 2: keyword, 3: page to keep. */
				return sprintf( __( '%1$d of your pages target "%2$s". Keep "%3$s" for it and point this page at another search, or merge them.', 'chatbot' ), (int) $numbers['pages'], $numbers['query'], $numbers['keep'] );
			case 'missing_schema':
				/* translators: %s: contact or about page. */
				return sprintf( __( 'You have a "%s" page but no business details in Local SEO, so search engines get no name, address or opening hours for your business.', 'chatbot' ), $numbers['page'] );
			case 'woo_no_description':
				return __( 'This product has no description, so it has nothing to rank with and nothing to convince buyers.', 'chatbot' ) . $why;
			case 'woo_category_intro':
				/* translators: 1: category, 2: products. */
				return sprintf( __( 'The category "%1$s" lists %2$d products but has no introduction. A short one helps it rank for the category search.', 'chatbot' ), $numbers['term'], (int) $numbers['products'] );
			case 'ai_answers':
				return __( 'A key page with no clear summary or answers to common questions, which AI search tools quote first. Add a short summary and an FAQ.', 'chatbot' ) . $why;
			case 'llms_txt':
				return 'off' === $numbers['state']
					? __( 'Set up llms.txt in Technical SEO: a short, always-current index of your public pages for AI tools. It is a proposed standard; keep it clean rather than expect ranking gains.', 'chatbot' )
					: __( 'Visitors are not getting your current llms.txt (an old cached copy, another source, or "not found"). Open Technical SEO > llms.txt and check the live file.', 'chatbot' );
			case 'ai_topic_gap':
				$text = sprintf(
					/* translators: 1: topic, 2: keyword, 3: why it fits. */
					__( 'AI suggestion: your site has no page about "%1$s" (search: "%2$s"). %3$s', 'chatbot' ),
					$numbers['topic'],
					$numbers['query'],
					$numbers['reason']
				);
				if ( isset( $numbers['volume'] ) && null !== $numbers['volume'] && ! empty( $numbers['source'] ) ) {
					/* translators: 1: monthly searches, 2: data source. */
					$text .= ' ' . sprintf( __( 'About %1$s searches a month (%2$s).', 'chatbot' ), number_format_i18n( (int) $numbers['volume'] ), $numbers['source'] );
				}
				return trim( $text );
		}
		return $reason;
	}
}
