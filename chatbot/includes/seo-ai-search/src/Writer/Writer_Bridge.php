<?php
/**
 * Hand-offs to AI Writer: briefs (Improve, Write, Refresh), the calendar
 * (saved ideas) and Topic Finder (opportunities as an idea source).
 *
 * AI Writer writes and rewrites content; this module never does. When AI
 * Writer is missing, actions say so and nothing breaks.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Writer;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Opportunities\Opportunities;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Bridge to AI Writer's public API.
 */
final class Writer_Bridge {

	/**
	 * Hook the Topic Finder source.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_writer/idea_sources', array( __CLASS__, 'idea_source' ) );
	}

	/**
	 * Whether AI Writer's brief API is available.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'qcld_wpbot_writer_open_brief_url' );
	}

	/**
	 * Brief URL for an opportunity action.
	 *
	 * @param array  $opp    Opportunity (shape()).
	 * @param string $action improve | write | refresh | rewrite_meta.
	 * @return string|WP_Error
	 */
	public static function brief_url( array $opp, $action ) {
		if ( ! self::available() ) {
			return new WP_Error( 'qcld_wpbot_seo_no_writer', __( 'AI Writer is not available on this site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$numbers = self::numbers_text( $opp );
		$query   = (string) $opp['query'];
		switch ( $action ) {
			case 'write':
				$brief = array(
					'mode'       => 'new',
					'topic'      => $query,
					'main_query' => $query,
					'notes'      => $opp['reason'] . "\n" . $numbers,
				);
				break;
			case 'refresh':
				$brief = array(
					'mode'       => 'improve',
					'post_id'    => (int) $opp['post_id'],
					'main_query' => $query,
					/* translators: %s: numbers behind the opportunity. */
					'fix'        => sprintf( __( 'Refresh this page: update facts, dates and examples, and answer what searchers want now. %s', 'chatbot' ), $numbers ),
				);
				break;
			case 'rewrite_meta':
				$brief = array(
					'mode'    => 'improve',
					'post_id' => (int) $opp['post_id'],
					/* translators: %s: numbers behind the opportunity. */
					'fix'     => sprintf( __( 'Rewrite the search title and description so more searchers click. %s', 'chatbot' ), $numbers ),
				);
				break;
			default:
				$brief = array(
					'mode'       => 'improve',
					'post_id'    => (int) $opp['post_id'],
					'main_query' => $query,
					'fix'        => $opp['reason'] . "\n" . $numbers,
				);
		}
		if ( 'improve' === $brief['mode'] && ( empty( $brief['post_id'] ) || ! current_user_can( 'edit_post', $brief['post_id'] ) ) ) {
			return new WP_Error( 'qcld_wpbot_seo_no_post', __( 'This page is not a post you can edit (it may be an archive or the home page). Improve it in its editor.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$brief['source_module'] = Module::ID;
		$brief['type']          = 'new' === $brief['mode'] ? 'post' : '';
		return (string) qcld_wpbot_writer_open_brief_url( $brief );
	}

	/**
	 * Brief URL for a new piece of content outside opportunities (guest
	 * articles, partner content, linkable pages).
	 *
	 * @param string $topic Topic or working title.
	 * @param string $notes Notes for the writer (rules, guidelines).
	 * @param string $type  post | page.
	 * @return string|WP_Error
	 */
	public static function new_content_url( $topic, $notes, $type = 'post' ) {
		if ( ! self::available() ) {
			return new WP_Error( 'qcld_wpbot_seo_no_writer', __( 'AI Writer is not available on this site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return (string) qcld_wpbot_writer_open_brief_url(
			array(
				'mode'          => 'new',
				'topic'         => sanitize_text_field( (string) $topic ),
				'notes'         => sanitize_textarea_field( (string) $notes ),
				'type'          => 'page' === $type ? 'page' : 'post',
				'source_module' => Module::ID,
			)
		);
	}

	/**
	 * Save an opportunity as an AI Writer idea, so it can be placed on the calendar.
	 *
	 * @param array $opp Opportunity.
	 * @return array|WP_Error idea_id, url (AI Writer calendar).
	 */
	public static function add_to_calendar( array $opp ) {
		if ( ! class_exists( '\QuantumCloud\AiWriter\Topics\Ideas' ) ) {
			return new WP_Error( 'qcld_wpbot_seo_no_writer', __( 'AI Writer is not available on this site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$title = '' !== $opp['query'] ? $opp['query'] : ( '' !== $opp['title'] ? $opp['title'] : $opp['url'] );
		$id    = \QuantumCloud\AiWriter\Topics\Ideas::add(
			$title,
			'seo',
			array(
				'source_ref' => 'opp:' . $opp['id'],
				'reason'     => $opp['reason'],
				'relevance'  => min( 100, (int) round( $opp['score'] ) ),
				'main_query' => $opp['query'],
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array(
			'idea_id' => (int) $id,
			'url'     => admin_url( 'admin.php?page=wpbot-ai-writer&tab=calendar' ),
		);
	}

	/**
	 * Topic Finder source: missing pages and near-page-one searches.
	 *
	 * @param array $sources Sources.
	 * @return array
	 */
	public static function idea_source( $sources ) {
		$sources['seo_opportunities'] = array(
			'label'           => __( 'SEO & AI Search opportunities', 'chatbot' ),
			'description'     => __( 'Searches with no matching page and pages close to page one, from your Search Console data.', 'chatbot' ),
			'callback'        => array( __CLASS__, 'ideas' ),
			'status_callback' => array( __CLASS__, 'source_status' ),
			'free'            => true,
		);
		return $sources;
	}

	/**
	 * Ideas for Topic Finder.
	 *
	 * @param array $context Topic Finder context.
	 * @return array
	 */
	public static function ideas( $context = array() ) {
		unset( $context );
		$ideas = array();
		foreach ( array( 'missing_page', 'near_page_one' ) as $type ) {
			$found = Opportunities::query(
				array(
					'type'  => $type,
					'limit' => 15,
				)
			);
			foreach ( $found['items'] as $opp ) {
				if ( '' === $opp['query'] ) {
					continue;
				}
				$ideas[] = array(
					'title'      => $opp['query'],
					'ref'        => 'opp:' . $opp['id'],
					'reason'     => $opp['reason'],
					'relevance'  => min( 100, (int) round( $opp['score'] ) ),
					'trend'      => 'missing_page' === $type ? 'gap' : 'rising',
					'main_query' => $opp['query'],
					'metrics'    => array(
						'impressions' => isset( $opp['numbers']['impressions'] ) ? (int) $opp['numbers']['impressions'] : 0,
						'position'    => isset( $opp['numbers']['position'] ) ? (float) $opp['numbers']['position'] : 0,
					),
				);
			}
		}
		return $ideas;
	}

	/**
	 * Source status for Topic Finder's Sources strip.
	 *
	 * @return array status, message, fix.
	 */
	public static function source_status() {
		$connected = \QuantumCloud\WPBotCore\Connections\Google::connected();
		return array(
			'status'  => $connected ? 'connected' : 'not_connected',
			'message' => $connected ? '' : __( 'Connect Search Console in SEO & AI Search.', 'chatbot' ),
			'fix'     => $connected ? null : array(
				'label' => __( 'Connect', 'chatbot' ),
				'url'   => admin_url( 'admin.php?page=wpbot-seo-ai-search&tab=settings' ),
			),
		);
	}

	/**
	 * Numbers behind an opportunity as one line for a brief.
	 *
	 * @param array $opp Opportunity.
	 * @return string
	 */
	private static function numbers_text( array $opp ) {
		$n     = $opp['numbers'];
		$parts = array();
		if ( isset( $n['impressions'] ) ) {
			/* translators: %s: impressions. */
			$parts[] = sprintf( __( '%s impressions', 'chatbot' ), number_format_i18n( (float) $n['impressions'] ) );
		}
		if ( isset( $n['clicks'] ) ) {
			/* translators: %s: clicks. */
			$parts[] = sprintf( __( '%s clicks', 'chatbot' ), number_format_i18n( (float) $n['clicks'] ) );
		}
		if ( ! empty( $n['position'] ) ) {
			/* translators: %s: average position. */
			$parts[] = sprintf( __( 'average position %s', 'chatbot' ), number_format_i18n( (float) $n['position'], 1 ) );
		}
		/* translators: %s: list of numbers. */
		$text   = array() === $parts ? '' : sprintf( __( 'Search Console, last 28 days: %s.', 'chatbot' ), implode( ', ', $parts ) );
		$volume = isset( $opp['query'] ) ? \QuantumCloud\SeoAiSearch\Keywords\Volumes::brief_text( (string) $opp['query'] ) : '';
		return trim( $text . ' ' . $volume );
	}
}
