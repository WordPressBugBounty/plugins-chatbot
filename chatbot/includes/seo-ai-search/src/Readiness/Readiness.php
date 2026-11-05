<?php
/**
 * Readiness for real pages: gathers page data for Readiness_Checks and
 * hands fixes to AI Writer.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Readiness;

use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Local\Locations;
use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;
use QuantumCloud\SeoAiSearch\Technical\Site_Checks;
use QuantumCloud\SeoAiSearch\Writer\Writer_Bridge;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Readiness service.
 */
final class Readiness {

	/**
	 * Checklist for one post.
	 *
	 * @param int $post_id Post id.
	 * @return array|null score, items.
	 */
	public static function for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$main = Locations::main();
		return Readiness_Checks::run(
			array(
				'content'          => (string) $post->post_content,
				'title'            => (string) $post->post_title,
				'main_query'       => Audit::main_query( $post_id ),
				'is_post'          => 'post' === $post->post_type,
				'has_author'       => '' !== (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
				'has_date'         => true,
				'schema'           => Seo_Plugins::handles_head() || (bool) apply_filters( 'qcld_wpbot_seo/schema_output', true ),
				'business'         => $main && '' !== $main['name'] ? $main['name'] : (string) get_bloginfo( 'name' ),
				'place'            => $main ? (string) $main['city'] : '',
				'crawlers_blocked' => self::blocked_bots(),
			)
		);
	}

	/**
	 * Pages to show: most clicks first (Search Console), else recent.
	 *
	 * @param int $limit Pages.
	 * @return array List of post_id, title, url, score, items.
	 */
	public static function pages( $limit = 30 ) {
		$period = Period::last( 28 );
		$ids    = array();
		foreach ( Data_Store::top( 'page', $period['start'], $period['end'], $limit ) as $row ) {
			$post_id = Data_Store::post_for_url( $row['id'] );
			if ( $post_id ) {
				$ids[] = $post_id;
			}
		}
		if ( count( $ids ) < $limit ) {
			$ids = array_values(
				array_unique(
					array_merge(
						$ids,
						get_posts(
							array(
								'post_type'      => Audit::post_types(),
								'post_status'    => 'publish',
								'fields'         => 'ids',
								'posts_per_page' => $limit,
							)
						)
					)
				)
			);
		}
		$out = array();
		foreach ( array_slice( $ids, 0, $limit ) as $post_id ) {
			$check = self::for_post( $post_id );
			if ( $check ) {
				$out[] = array_merge(
					array(
						'post_id'  => (int) $post_id,
						'title'    => get_the_title( $post_id ),
						'url'      => (string) get_permalink( $post_id ),
						'can_edit' => current_user_can( 'edit_post', $post_id ),
					),
					$check
				);
			}
		}
		return $out;
	}

	/**
	 * Open AI Writer's Improve flow for a content item.
	 *
	 * @param int    $post_id Post id.
	 * @param string $item    Item id.
	 * @return string|WP_Error Brief URL.
	 */
	public static function fix_url( $post_id, $item ) {
		$check = self::for_post( $post_id );
		$found = null;
		foreach ( $check ? $check['items'] : array() as $entry ) {
			if ( $entry['id'] === $item && ! $entry['pass'] && 'writer' === $entry['fix'] ) {
				$found = $entry;
			}
		}
		if ( ! $found ) {
			return new WP_Error( 'qcld_wpbot_seo_no_issue', __( 'This item has no AI Writer fix, or it already passes.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return Writer_Bridge::brief_url(
			array(
				'post_id' => (int) $post_id,
				'query'   => Audit::main_query( $post_id ),
				/* translators: %s: advice. */
				'reason'  => sprintf( __( 'Make this page easy for AI assistants to quote: %s', 'chatbot' ), $found['message'] ),
				'numbers' => array(),
				'title'   => get_the_title( $post_id ),
				'url'     => (string) get_permalink( $post_id ),
			),
			'improve'
		);
	}

	/**
	 * AI bots blocked in the site's robots.txt (cached an hour).
	 *
	 * @return string[]
	 */
	public static function blocked_bots() {
		$cached = get_transient( 'qcld_wpbot_seo_blocked_bots' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$robots  = Site_Checks::robots();
		$blocked = Ai_Crawlers::blocked_in( $robots['text'] );
		set_transient( 'qcld_wpbot_seo_blocked_bots', $blocked, HOUR_IN_SECONDS );
		return $blocked;
	}
}
