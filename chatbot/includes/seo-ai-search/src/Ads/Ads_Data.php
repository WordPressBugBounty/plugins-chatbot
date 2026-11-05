<?php
/**
 * Paid searches from Google Ads (S13): the exact searches people typed
 * before clicking an ad, with clicks, cost and conversions. Two sources:
 * "ga4" (Google Ads data through the linked Google Analytics property,
 * Free) and "ads_api" (the direct, read-only Google Ads API, Pro). Each
 * sync replaces that source's 30-day snapshot. Every number shown carries
 * its source.
 *
 * Rows with url_id 0 are per search (clicks, impressions, cost); rows with
 * a url_id are per search and landing page (sessions, conversions).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Ads;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Storage\Schema;
use QuantumCloud\WPBotCore\Map\Keyword_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Paid search storage.
 */
final class Ads_Data {

	const SOURCES = array( 'ga4', 'ads_api' );

	/**
	 * Replace a source's snapshot.
	 *
	 * @param string $source   ga4 or ads_api.
	 * @param array  $rows     query, landing (URL or path, '' for the search row), clicks, impressions, cost (null when unknown), conversions, sessions.
	 * @param array  $period   start, end (Y-m-d).
	 * @param string $currency Currency code.
	 * @return int Rows stored.
	 */
	public static function replace( $source, array $rows, array $period, $currency = '' ) {
		global $wpdb;
		$source = in_array( $source, self::SOURCES, true ) ? $source : 'ga4';
		$table  = Schema::table( Schema::ADS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		$wpdb->delete( $table, array( 'source' => $source ) );
		$now    = current_time( 'mysql', true );
		$stored = 0;
		foreach ( $rows as $row ) {
			$raw = trim( isset( $row['query'] ) ? (string) $row['query'] : '' );
			// Google hides low-volume terms as "(not set)" or "(other)": nothing to learn from them.
			if ( '' === $raw || '(' === $raw[0] ) {
				continue;
			}
			$query = Keyword_Map::normalise( $raw );
			if ( '' === $query ) {
				continue;
			}
			$landing = isset( $row['landing'] ) ? (string) $row['landing'] : '';
			$url_id  = '' !== $landing ? Data_Store::url_id( \QuantumCloud\SeoAiSearch\Google\Ga_Pull::landing_url( $landing ) ) : 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
			$wpdb->replace(
				$table,
				array(
					'source'       => $source,
					'query'        => substr( $query, 0, 191 ),
					'query_hash'   => md5( $query ),
					'url_id'       => (int) $url_id,
					'clicks'       => max( 0, (int) ( isset( $row['clicks'] ) ? $row['clicks'] : 0 ) ),
					'impressions'  => max( 0, (int) ( isset( $row['impressions'] ) ? $row['impressions'] : 0 ) ),
					'cost'         => isset( $row['cost'] ) && null !== $row['cost'] ? round( (float) $row['cost'], 2 ) : null,
					'conversions'  => round( max( 0, (float) ( isset( $row['conversions'] ) ? $row['conversions'] : 0 ) ), 2 ),
					'sessions'     => max( 0, (int) ( isset( $row['sessions'] ) ? $row['sessions'] : 0 ) ),
					'currency'     => substr( strtoupper( sanitize_key( (string) $currency ) ), 0, 3 ),
					'period_start' => isset( $period['start'] ) ? (string) $period['start'] : null,
					'period_end'   => isset( $period['end'] ) ? (string) $period['end'] : null,
					'updated_at'   => $now,
				)
			);
			++$stored;
		}
		/**
		 * Paid searches were imported (Vault, map and opportunity updates hook in).
		 *
		 * @param string $source ga4 or ads_api.
		 */
		do_action( 'qcld_wpbot_seo/ads_pulled', $source );
		return $stored;
	}

	/**
	 * Is there paid search data (either source)?
	 *
	 * @return bool
	 */
	public static function has_data() {
		global $wpdb;
		$table = Schema::table( Schema::ADS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		return (bool) $wpdb->get_var( "SELECT 1 FROM {$table} LIMIT 1" );
	}

	/**
	 * Best source available: the direct API (Pro) when it has data, else GA4.
	 *
	 * @return string
	 */
	public static function source() {
		global $wpdb;
		$table = Schema::table( Schema::ADS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		return $wpdb->get_var( "SELECT 1 FROM {$table} WHERE source = 'ads_api' LIMIT 1" ) ? 'ads_api' : 'ga4';
	}

	/**
	 * Paid searches with their totals and landing pages (best source).
	 *
	 * @return array[] query, clicks, impressions, cost, conversions, cost_per_conversion, currency, source, landing (url_id => post_id), period.
	 */
	public static function searches() {
		global $wpdb;
		$table  = Schema::table( Schema::ADS );
		$source = self::source();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE source = %s ORDER BY url_id ASC", $source ), ARRAY_A );
		$out  = array();
		foreach ( $rows as $row ) {
			$key = $row['query_hash'];
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array(
					'query'       => (string) $row['query'],
					'clicks'      => 0,
					'impressions' => 0,
					'cost'        => null,
					'conversions' => 0.0,
					'currency'    => (string) $row['currency'],
					'source'      => $source,
					'landing'     => array(),
					'period'      => array(
						'start' => (string) $row['period_start'],
						'end'   => (string) $row['period_end'],
					),
				);
			}
			if ( 0 === (int) $row['url_id'] ) {
				$out[ $key ]['clicks']      = (int) $row['clicks'];
				$out[ $key ]['impressions'] = (int) $row['impressions'];
				$out[ $key ]['cost']        = null === $row['cost'] ? null : (float) $row['cost'];
				// The direct API reports conversions per search; GA4 per landing page (summed below).
				$out[ $key ]['conversions'] = max( $out[ $key ]['conversions'], (float) $row['conversions'] );
			} else {
				$post_id = Data_Store::post_for_url( (int) $row['url_id'] );
				if ( $post_id ) {
					$out[ $key ]['landing'][] = $post_id;
				}
				if ( 'ga4' === $source ) {
					$out[ $key ]['conversions'] += (float) $row['conversions'];
				}
			}
		}
		foreach ( $out as $key => $row ) {
			$out[ $key ]['landing']             = array_values( array_unique( $row['landing'] ) );
			$out[ $key ]['conversions']         = round( $row['conversions'], 2 );
			$out[ $key ]['cost_per_conversion'] = null !== $row['cost'] && $row['conversions'] > 0 ? round( $row['cost'] / $row['conversions'], 2 ) : null;
		}
		$out = array_values( $out );
		usort(
			$out,
			static function ( $a, $b ) {
				return array( $b['conversions'], (float) $b['cost'] ) <=> array( $a['conversions'], (float) $a['cost'] );
			}
		);
		return $out;
	}

	/**
	 * Source name for badges.
	 *
	 * @param string $source ga4 or ads_api.
	 * @return string
	 */
	public static function label( $source ) {
		return 'ads_api' === $source ? __( 'Google Ads', 'chatbot' ) : __( 'Google Ads via Analytics', 'chatbot' );
	}
}
