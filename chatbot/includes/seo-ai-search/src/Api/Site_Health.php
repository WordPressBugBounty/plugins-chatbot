<?php
/**
 * Site health without Search Console (S14): on-page, technical and AI
 * search readiness, Vault progress, latest research and the setup steps.
 * Used by the Overview, which never needs a connection to show content.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Api;

use QuantumCloud\SeoAiSearch\Local\Locations;
use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\SeoAiSearch\Readiness\Readiness;
use QuantumCloud\SeoAiSearch\Research\Research_Store;
use QuantumCloud\SeoAiSearch\Research\Vault_Service;
use QuantumCloud\SeoAiSearch\Technical\Scan;
use QuantumCloud\WPBotCore\Connections\Google;

defined( 'ABSPATH' ) || exit;

/**
 * Overview facts that need no connection.
 */
final class Site_Health {

	/**
	 * Everything the Overview shows without Search Console (cached 10 minutes).
	 *
	 * @return array onpage, technical, readiness, vault, research, setup.
	 */
	public static function get() {
		$cached = get_transient( 'qcld_wpbot_seo_site_health' );
		if ( is_array( $cached ) ) {
			$cached['research'] = self::research();
			$cached['vault']    = self::vault();
			return $cached;
		}
		$data = array(
			'onpage'    => self::onpage(),
			'technical' => self::technical(),
			'readiness' => self::readiness(),
		);
		set_transient( 'qcld_wpbot_seo_site_health', $data, 10 * MINUTE_IN_SECONDS );
		$data['research'] = self::research();
		$data['vault']    = self::vault();
		return $data;
	}

	/**
	 * Setup steps: business details, first scan, optional connections.
	 *
	 * @return array business, scan, connections (gsc, keyword_data, ads).
	 */
	public static function setup() {
		$main = Locations::main();
		return array(
			'business'    => $main && '' !== trim( (string) $main['name'] ),
			'scan'        => null !== Scan::latest( 'done' ),
			'connections' => array(
				'gsc'          => Google::connected(),
				'keyword_data' => \QuantumCloud\SeoAiSearch\Keywords\Volumes::connected(),
				/**
				 * Whether Google Ads data is connected (S13 sets this).
				 *
				 * @param bool $connected Connected.
				 */
				'ads'          => (bool) apply_filters( 'qcld_wpbot_seo/ads_connected', false ),
			),
		);
	}

	/**
	 * Average on-page score of audited pages.
	 *
	 * @return array average (or null), pages, weak (score under 50).
	 */
	private static function onpage() {
		$ids   = get_posts(
			array(
				'post_type'      => Audit::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Ids only; cached 10 minutes.
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only audited pages.
				'meta_key'       => Audit::META,
			)
		);
		$total = 0;
		$count = 0;
		$weak  = 0;
		foreach ( $ids as $post_id ) {
			$stored = Audit::stored( $post_id );
			if ( $stored && isset( $stored['score'] ) ) {
				$total += (int) $stored['score'];
				++$count;
				if ( (int) $stored['score'] < 50 ) {
					++$weak;
				}
			}
		}
		return array(
			'average' => $count ? (int) round( $total / $count ) : null,
			'pages'   => $count,
			'weak'    => $weak,
		);
	}

	/**
	 * Latest technical scan.
	 *
	 * @return array score (or null), critical, date.
	 */
	private static function technical() {
		$scan = Scan::latest( 'done' );
		return array(
			'score'    => $scan ? (int) $scan['score'] : null,
			'critical' => $scan ? Dashboard_Data::critical_issues() : 0,
			'date'     => $scan && ! empty( $scan['finished_at'] ) ? (string) $scan['finished_at'] : '',
		);
	}

	/**
	 * Average AI search readiness of the most recent pages.
	 *
	 * @return array average (or null), pages.
	 */
	private static function readiness() {
		$pages = Readiness::pages( 20 );
		if ( array() === $pages ) {
			return array(
				'average' => null,
				'pages'   => 0,
			);
		}
		return array(
			'average' => (int) round( array_sum( wp_list_pluck( $pages, 'score' ) ) / count( $pages ) ),
			'pages'   => count( $pages ),
		);
	}

	/**
	 * Vault progress.
	 *
	 * @return array|null Totals per status, or null without a Vault.
	 */
	private static function vault() {
		return Vault_Service::available() ? \QuantumCloud\WPBotCore\Vault\Vault::totals() : null;
	}

	/**
	 * Latest research runs.
	 *
	 * @return array[]
	 */
	private static function research() {
		return Research_Store::history( 3 );
	}
}
