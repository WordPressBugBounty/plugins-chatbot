<?php
/**
 * Search Console side of the shared keyword map (WPBot Core): each page's
 * main search and up to four supporting searches from its own Search
 * Console data. Runs after each Search Console import, in the job queue.
 * Manual or locked entries are never changed by a refresh. Pages without
 * data can get an AI suggestion on request.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Keywords;

use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Onpage\Checks;
use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use QuantumCloud\WPBotCore\Privacy\Redactor;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Map sync.
 */
final class Map_Sync {

	/**
	 * Pages per job step.
	 */
	const PER_STEP = 50;

	/**
	 * Least impressions for a search to count.
	 */
	const MIN_IMPRESSIONS = 10;

	/**
	 * Is the shared map available?
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( Keyword_Map::class );
	}

	/**
	 * Hook in: refresh after each Search Console import.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_seo/gsc_pulled', array( __CLASS__, 'after_pull' ) );
	}

	/**
	 * Job step handlers.
	 *
	 * @return array
	 */
	public static function handlers() {
		return array( 'seo_map_sync' => array( __CLASS__, 'step' ) );
	}

	/**
	 * After an import: queue a refresh.
	 *
	 * @return void
	 */
	public static function after_pull() {
		if ( self::available() ) {
			self::start();
		}
	}

	/**
	 * Main and supporting searches from a page's Search Console rows (pure).
	 *
	 * @param array $rows Rows for one page: query, clicks, impressions, position.
	 * @return array|null main, supporting[].
	 */
	public static function suggest( array $rows ) {
		$rows = array_values(
			array_filter(
				$rows,
				static function ( $row ) {
					return (int) $row['impressions'] >= self::MIN_IMPRESSIONS && '' !== trim( (string) $row['query'] );
				}
			)
		);
		if ( array() === $rows ) {
			return null;
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return array( (int) $b['clicks'], (int) $b['impressions'] ) <=> array( (int) $a['clicks'], (int) $a['impressions'] );
			}
		);
		$main       = $rows[0]['query'];
		$supporting = array();
		$rest       = array_slice( $rows, 1 );
		usort(
			$rest,
			static function ( $a, $b ) {
				return (int) $b['impressions'] <=> (int) $a['impressions'];
			}
		);
		foreach ( $rest as $row ) {
			if ( (float) $row['position'] <= 30 && count( $supporting ) < 4 ) {
				$supporting[] = $row['query'];
			}
		}
		return array(
			'main'       => $main,
			'supporting' => $supporting,
		);
	}

	/**
	 * Search Console rows per page (last 90 days), keyed by post id.
	 *
	 * @return array post_id => rows.
	 */
	public static function rows_by_post() {
		$period = Period::last( 90 );
		$out    = array();
		foreach ( Data_Store::period_rows( 'page_query', $period['start'], $period['end'], self::MIN_IMPRESSIONS, 20000 ) as $row ) {
			$post_id = Data_Store::post_for_url( (int) $row['url_id'] );
			if ( $post_id ) {
				$out[ $post_id ][] = $row;
			}
		}
		return $out;
	}

	/**
	 * Queue a refresh of every page with Search Console data.
	 *
	 * @return int|WP_Error Job id (0 when there is nothing to map).
	 */
	public static function start() {
		$ids = array_keys( self::rows_by_post() );
		if ( array() === $ids ) {
			return 0;
		}
		$steps = array();
		foreach ( array_chunk( $ids, self::PER_STEP ) as $index => $chunk ) {
			$steps[] = array(
				'kind'  => 'seo_map_sync',
				/* translators: %d: batch number. */
				'label' => sprintf( __( 'Map searches to pages, batch %d', 'chatbot' ), $index + 1 ),
				'input' => array( 'ids' => $chunk ),
			);
		}
		return (int) Job_Store::create( Module::ID, 'map_sync', array( 'title' => __( 'Update the keyword map from Search Console', 'chatbot' ) ), $steps );
	}

	/**
	 * Job step: map a batch of pages.
	 *
	 * @param array $step Step.
	 * @return array
	 */
	public static function step( array $step ) {
		$rows    = self::rows_by_post();
		$changed = 0;
		foreach ( (array) $step['input']['ids'] as $post_id ) {
			$changed += self::map_post( (int) $post_id, isset( $rows[ (int) $post_id ] ) ? $rows[ (int) $post_id ] : array() ) ? 1 : 0;
		}
		return array( 'changed' => $changed );
	}

	/**
	 * Map one page from its rows (manual and locked entries are kept by the map).
	 *
	 * @param int   $post_id Post.
	 * @param array $rows    Rows.
	 * @return bool Changed.
	 */
	public static function map_post( $post_id, array $rows ) {
		$suggestion = self::suggest( $rows );
		if ( null === $suggestion ) {
			return false;
		}
		$current = Keyword_Map::main_query( $post_id );
		if ( $current && Keyword_Map::normalise( $suggestion['main'] ) === $current['query'] && 'search_console' === $current['source'] ) {
			return false;
		}
		return Keyword_Map::set_post(
			$post_id,
			$suggestion['main'],
			array(
				'source'     => 'search_console',
				'supporting' => $suggestion['supporting'],
				'module'     => Module::ID,
				'topic_id'   => $current ? (int) $current['topic_id'] : 0,
			)
		);
	}

	/**
	 * The owner sets a page's searches (manual; optionally locked).
	 *
	 * @param int      $post_id    Post.
	 * @param string   $main       Main search.
	 * @param string[] $supporting Supporting searches.
	 * @param bool     $locked     Lock.
	 * @return bool|WP_Error
	 */
	public static function set_manual( $post_id, $main, array $supporting, $locked ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'You cannot edit this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		if ( '' === Keyword_Map::normalise( $main ) ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'Enter the main search for this page.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$current = Keyword_Map::main_query( $post_id );
		return Keyword_Map::set_post(
			$post_id,
			$main,
			array(
				'source'     => 'manual',
				'supporting' => $supporting,
				'locked'     => $locked,
				'module'     => Module::ID,
				'topic_id'   => $current ? (int) $current['topic_id'] : 0,
			)
		);
	}

	/**
	 * AI suggestion for a page with no Search Console data yet (not saved until accepted).
	 *
	 * @param int $post_id Post.
	 * @return array|WP_Error main, supporting.
	 */
	public static function ai_suggest( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new WP_Error( 'qcld_wpbot_seo_no_post', __( 'This page no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$text     = Redactor::redact( self::cut( Checks::text( $post->post_content ), 1500 ) );
		$response = AI_Service::generate(
			Module::ID,
			'map_suggest',
			Request::make(
				'You choose the search a web page should target: the words a real person would type into Google to find exactly this page. Same language as the page. No brand names unless the page is about the brand.',
				"Task: map_suggest\nTitle: " . $post->post_title . "\nText (start):\n" . $text . "\n\nReturn JSON {\"main\": \"...\", \"supporting\": [\"...\", \"...\"]} with 2 to 4 supporting searches.",
				array(
					'max_tokens' => 200,
					'json'       => true,
					'mode'       => 'strict',
				)
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$json = $response->json();
		if ( ! is_array( $json ) || empty( $json['main'] ) ) {
			return new WP_Error( 'qcld_wpbot_seo_ai_empty', __( 'The AI did not suggest a search. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$suggestion = array(
			'main'       => sanitize_text_field( (string) $json['main'] ),
			'supporting' => array_slice( array_map( 'sanitize_text_field', array_map( 'strval', isset( $json['supporting'] ) ? (array) $json['supporting'] : array() ) ), 0, 4 ),
		);
		/**
		 * A main search and supporting searches suggested for a page (Pro swaps
		 * in supporting searches from the same Keyword Planner group, with volumes).
		 *
		 * @param array $suggestion main, supporting.
		 * @param int   $post_id    Post.
		 */
		return (array) apply_filters( 'qcld_wpbot_seo/map_suggestion', $suggestion, (int) $post_id );
	}

	/**
	 * Cut text.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function cut( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, $max ) : substr( (string) $text, 0, $max );
	}
}
