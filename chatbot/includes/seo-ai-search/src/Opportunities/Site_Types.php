<?php
/**
 * Opportunity types that need no Search Console (S14).
 *
 * - vault_no_page: a Vault keyword (researched or targeted) the site has no
 *   page for: "Write a page".
 * - vault_competing: two of the site's pages target the same Vault keyword
 *   ("Competing pages").
 * - onpage_issues and technical_issues: on-page and technical fixes (kept
 *   with Search Console since round 2, ranked with its types).
 *
 * The list is rebuilt daily, after Search Console and Analytics imports,
 * and shortly after Vault changes.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Opportunities;

use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\SeoAiSearch\Research\Research_View;
use QuantumCloud\SeoAiSearch\Technical\Scan;
use QuantumCloud\WPBotCore\Map\Keyword_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Vault, on-page and technical opportunity types.
 */
final class Site_Types {

	const REBUILD = 'qcld_wpbot_seo_opps_rebuild';

	/**
	 * Hook the types and the rebuild triggers.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_seo/opportunity_types', array( __CLASS__, 'types' ) );
		add_filter( 'qcld_wpbot_seo/opportunity_reason', array( __CLASS__, 'reason' ), 10, 3 );
		add_action( 'qcld_wpbot_seo/daily', array( Opportunities::class, 'rebuild' ) );
		add_action( 'qcld_wpbot_core/vault_changed', array( __CLASS__, 'soon' ) );
		add_action( 'qcld_wpbot_seo/scan_completed', array( __CLASS__, 'soon' ) );
		add_action( self::REBUILD, array( __CLASS__, 'refresh' ) );
	}

	/**
	 * Rebuild in a minute (several changes in a row rebuild once).
	 *
	 * @return void
	 */
	public static function soon() {
		if ( ! wp_next_scheduled( self::REBUILD ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::REBUILD );
		}
	}

	/**
	 * Rebuild opportunities and topic coverage (Vault keywords count as planned).
	 *
	 * @return void
	 */
	public static function refresh() {
		Opportunities::rebuild();
		if ( class_exists( '\\QuantumCloud\\WPBotCore\\Map\\Topic_Map' ) ) {
			\QuantumCloud\WPBotCore\Map\Topic_Map::refresh_coverage();
		}
	}

	/**
	 * Add the types.
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public static function types( $types ) {
		$types['vault_no_page']   = array(
			'label'    => __( 'Vault keyword without a page', 'chatbot' ),
			'callback' => array( __CLASS__, 'vault_no_page' ),
			'action'   => 'write',
		);
		$types['vault_competing'] = array(
			'label'    => __( 'Competing pages (Vault)', 'chatbot' ),
			'callback' => array( __CLASS__, 'vault_competing' ),
			'action'   => 'improve',
		);
		// R3: kept with Search Console too, ranked together with its types.
		$types['onpage_issues']    = array(
			'label'    => __( 'On-page fixes', 'chatbot' ),
			'callback' => array( __CLASS__, 'onpage' ),
			'action'   => 'fix_page',
		);
		$types['technical_issues'] = array(
			'label'    => __( 'Technical fixes', 'chatbot' ),
			'callback' => array( __CLASS__, 'technical' ),
			'action'   => 'technical',
		);
		return $types;
	}

	/**
	 * Vault keywords the site has no page for.
	 *
	 * @return array[]
	 */
	public static function vault_no_page() {
		if ( ! function_exists( 'qcld_wpbot_core_vault_query' ) ) {
			return array();
		}
		$out = array();
		foreach ( qcld_wpbot_core_vault_query(
			array(
				'status'   => array( 'researched', 'targeted' ),
				'has_page' => false,
				'limit'    => 200,
			)
		) as $row ) {
			$key = Keyword_Map::normalise( $row['keyword'] );
			if ( Research_View::page_for( $key ) ) {
				continue;
			}
			$weight = array(
				'high'   => 30,
				'medium' => 15,
				'low'    => 6,
			);
			$volume = null !== $row['volume'] ? (int) $row['volume'] : null;
			$out[]  = array(
				'type'      => 'vault_no_page',
				'ref'       => 'vault_no_page:' . $row['id'],
				'query'     => $row['keyword'],
				'potential' => null !== $volume ? round( $volume * 0.05, 1 ) : ( isset( $weight[ $row['demand'] ] ) ? $weight[ $row['demand'] ] : 10 ),
				'effort'    => 3,
				'action'    => 'write',
				'numbers'   => array(
					'vault_id' => (int) $row['id'],
					'status'   => $row['status'],
					'intent'   => $row['intent'],
					'demand'   => $row['demand'],
					'volume'   => $volume,
					'source'   => '' !== $row['volume_source'] ? Research_View::provider_label( $row['volume_source'] ) : '',
				),
			);
		}
		return $out;
	}

	/**
	 * Two or more pages target the same Vault keyword.
	 *
	 * @return array[]
	 */
	public static function vault_competing() {
		if ( ! function_exists( 'qcld_wpbot_core_vault_query' ) ) {
			return array();
		}
		$out = array();
		foreach ( qcld_wpbot_core_vault_query(
			array(
				'status' => array( 'researched', 'targeted', 'ranking' ),
				'limit'  => 500,
			)
		) as $row ) {
			$posts = array();
			foreach ( Keyword_Map::posts_for_query( Keyword_Map::normalise( $row['keyword'] ) ) as $entry ) {
				if ( $entry['post_id'] && 'publish' === get_post_status( $entry['post_id'] ) ) {
					$posts[] = (int) $entry['post_id'];
				}
			}
			$posts = array_values( array_unique( $posts ) );
			if ( count( $posts ) < 2 ) {
				continue;
			}
			// The page the Vault assigned is the one to keep; the other is the one to change.
			$keep  = in_array( (int) $row['post_id'], $posts, true ) ? (int) $row['post_id'] : $posts[0];
			$other = array_values( array_diff( $posts, array( $keep ) ) );
			$out[] = array(
				'type'      => 'vault_competing',
				'ref'       => 'vault_competing:' . $row['id'],
				'post_id'   => $other[0],
				'url'       => (string) get_permalink( $other[0] ),
				'query'     => $row['keyword'],
				'potential' => 12,
				'effort'    => 2,
				'action'    => 'improve',
				'numbers'   => array(
					'pages'    => count( $posts ),
					'stronger' => (string) get_permalink( $keep ),
					'keep'     => get_the_title( $keep ),
				),
			);
		}
		return $out;
	}

	/**
	 * Pages with a low on-page score (no Search Console).
	 *
	 * @return array[]
	 */
	public static function onpage() {
		$ids = get_posts(
			array(
				'post_type'      => Audit::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Ids only, audited pages.
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only audited pages; bounded.
				'meta_key'       => Audit::META,
			)
		);
		$out = array();
		foreach ( $ids as $post_id ) {
			$stored = Audit::stored( $post_id );
			if ( ! $stored || (int) $stored['score'] >= 60 ) {
				continue;
			}
			$high = 0;
			foreach ( (array) $stored['issues'] as $issue ) {
				if ( isset( $issue['severity'] ) && 'high' === $issue['severity'] ) {
					++$high;
				}
			}
			$out[] = array(
				'type'      => 'onpage_issues',
				'ref'       => 'onpage_issues:' . $post_id,
				'post_id'   => (int) $post_id,
				'url'       => (string) get_permalink( $post_id ),
				'potential' => 5 + $high * 3,
				'effort'    => 1,
				'action'    => 'fix_page',
				'numbers'   => array(
					'score'  => (int) $stored['score'],
					'issues' => count( (array) $stored['issues'] ),
					'high'   => $high,
				),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $a['numbers']['score'] <=> $b['numbers']['score'];
			}
		);
		return array_slice( $out, 0, 30 );
	}

	/**
	 * Critical technical issues from the latest scan (no Search Console).
	 *
	 * @return array[]
	 */
	public static function technical() {
		$scan = Scan::latest( 'done' );
		if ( ! $scan ) {
			return array();
		}
		$critical = \QuantumCloud\SeoAiSearch\Api\Dashboard_Data::critical_issues();
		if ( $critical < 1 ) {
			return array();
		}
		return array(
			array(
				'type'      => 'technical_issues',
				'ref'       => 'technical_issues:' . $scan['id'],
				'potential' => 10 + $critical * 2,
				'effort'    => 2,
				'action'    => 'technical',
				'numbers'   => array(
					'critical' => $critical,
					'score'    => (int) $scan['score'],
				),
			),
		);
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
		switch ( $type ) {
			case 'vault_no_page':
				if ( isset( $numbers['volume'] ) && null !== $numbers['volume'] ) {
					/* translators: 1: keyword, 2: monthly searches, 3: data source. */
					return sprintf( __( '"%1$s" is in your Keyword Vault and you have no page for it. About %2$s searches a month (%3$s).', 'chatbot' ), $numbers['query'], number_format_i18n( (int) $numbers['volume'] ), $numbers['source'] );
				}
				/* translators: 1: keyword, 2: demand level (AI estimate). */
				return sprintf( __( '"%1$s" is in your Keyword Vault and you have no page for it. Demand: %2$s (AI estimate).', 'chatbot' ), $numbers['query'], '' !== (string) $numbers['demand'] ? $numbers['demand'] : __( 'not estimated', 'chatbot' ) );
			case 'vault_competing':
				/* translators: 1: number of pages, 2: keyword, 3: page to keep. */
				return sprintf( __( '%1$d of your pages target "%2$s". Keep "%3$s" for it and point this page at another search, or merge them.', 'chatbot' ), (int) $numbers['pages'], $numbers['query'], $numbers['keep'] );
			case 'onpage_issues':
				/* translators: 1: on-page score, 2: issues, 3: high-impact issues. */
				return sprintf( __( 'On-page score %1$d/100: %2$d issues, %3$d with high impact.', 'chatbot' ), (int) $numbers['score'], (int) $numbers['issues'], (int) $numbers['high'] );
			case 'technical_issues':
				/* translators: %d: critical issues. */
				return sprintf( _n( 'The latest site check found %d critical technical issue.', 'The latest site check found %d critical technical issues.', (int) $numbers['critical'], 'chatbot' ), (int) $numbers['critical'] );
		}
		return $reason;
	}
}
