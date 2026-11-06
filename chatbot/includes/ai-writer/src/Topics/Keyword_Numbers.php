<?php
/**
 * Search numbers for keywords (CR-6 W4): monthly searches, a 12-month trend,
 * competition and the bid range, each with the name of its source.
 *
 * Read from WPBot Core's keyword data cache (filled by SEO & AI Search,
 * from Google Keyword Planner or the owner's paid keyword provider). The
 * cache is free to read; AI Writer never starts paid lookups. Without
 * WPBot Core's keyword data, every method returns nothing and screens show
 * no numbers. Numbers are never invented: a keyword without data has none.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics;

use QuantumCloud\WPBotCore\Map\Keyword_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword numbers.
 */
final class Keyword_Numbers {

	/**
	 * Whether WPBot Core's keyword data is available.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'qcld_wpbot_core_keyword_data' );
	}

	/**
	 * Numbers for searches (cached data only).
	 *
	 * @param string[] $queries Searches.
	 * @return array Normalised search => numbers (see shape()); searches without a volume are left out.
	 */
	public static function lookup( array $queries ) {
		if ( ! self::available() ) {
			return array();
		}
		$normal  = array();
		$queries = array_slice( array_values( array_unique( array_filter( array_map( 'strval', $queries ) ) ) ), 0, 500 );
		foreach ( $queries as $query ) {
			$key = self::normalise( $query );
			if ( '' !== $key ) {
				$normal[ $key ] = $query;
			}
		}
		if ( array() === $normal ) {
			return array();
		}
		$rows = qcld_wpbot_core_keyword_data( array_keys( $normal ) );
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $query => $row ) {
			$shaped = self::shape( (array) $row );
			if ( null !== $shaped['volume'] ) {
				$out[ self::normalise( (string) $query ) ] = $shaped;
			}
		}
		return $out;
	}

	/**
	 * One row in the shape screens and briefs use.
	 *
	 * @param array $row Row from WPBot Core (volume, trend, competition_level, bid_low, bid_high, provider …).
	 * @return array volume (int|null), volume_range ([min, max]|null), trend (int[]), trend_direction, competition (low|medium|high|''), bid_low, bid_high, currency, source (label), source_id.
	 */
	public static function shape( array $row ) {
		$trend  = isset( $row['trend'] ) && is_array( $row['trend'] ) ? array_values( array_map( 'intval', $row['trend'] ) ) : array();
		$source = isset( $row['provider'] ) ? (string) $row['provider'] : ( isset( $row['volume_source'] ) ? (string) $row['volume_source'] : '' );
		$range  = isset( $row['volume_range'] ) && is_array( $row['volume_range'] ) && 2 === count( $row['volume_range'] ) ? array_map( 'intval', array_values( $row['volume_range'] ) ) : null;
		$level  = isset( $row['competition_level'] ) ? (string) $row['competition_level'] : ( isset( $row['competition'] ) && is_string( $row['competition'] ) ? (string) $row['competition'] : '' );
		return array(
			'volume'          => isset( $row['volume'] ) && null !== $row['volume'] && '' !== $row['volume'] ? max( 0, (int) $row['volume'] ) : null,
			'volume_range'    => $range,
			'trend'           => array_slice( $trend, -12 ),
			'trend_direction' => self::direction( $trend, isset( $row['trend_direction'] ) ? (string) $row['trend_direction'] : '' ),
			'competition'     => in_array( $level, array( 'low', 'medium', 'high' ), true ) ? $level : '',
			'bid_low'         => isset( $row['bid_low'] ) && null !== $row['bid_low'] ? round( (float) $row['bid_low'], 2 ) : null,
			'bid_high'        => isset( $row['bid_high'] ) && null !== $row['bid_high'] ? round( (float) $row['bid_high'], 2 ) : null,
			'currency'        => isset( $row['currency'] ) ? substr( sanitize_text_field( (string) $row['currency'] ), 0, 8 ) : '',
			'source'          => self::source_label( $source ),
			'source_id'       => sanitize_key( $source ),
		);
	}

	/**
	 * Rising, steady or falling from a monthly series ('' with too little data).
	 *
	 * @param int[]  $trend     Monthly searches, oldest first.
	 * @param string $direction A direction already worked out ('' to compute).
	 * @return string
	 */
	public static function direction( array $trend, $direction = '' ) {
		if ( in_array( $direction, array( 'rising', 'steady', 'falling' ), true ) ) {
			return $direction;
		}
		$api = '\\QuantumCloud\\WPBotCore\\Keywords\\Keyword_Data';
		if ( class_exists( $api ) && method_exists( $api, 'trend_direction' ) ) {
			return (string) call_user_func( array( $api, 'trend_direction' ), $trend );
		}
		return '';
	}

	/**
	 * The name shown on a number's source badge.
	 *
	 * @param string $id Provider or source id, or a label already.
	 * @return string
	 */
	public static function source_label( $id ) {
		$labels = array(
			'google_ads_planner' => __( 'Google Keyword Planner', 'chatbot' ),
			'dataforseo'         => 'DataForSEO',
			'keywordseverywhere' => 'Keywords Everywhere',
			'semrush'            => 'Semrush',
			'search_console'     => __( 'Search Console', 'chatbot' ),
			'ai'                 => __( 'AI estimate', 'chatbot' ),
		);
		$key    = sanitize_key( (string) $id );
		if ( isset( $labels[ $key ] ) ) {
			return $labels[ $key ];
		}
		if ( function_exists( 'qcld_wpbot_core_keyword_provider' ) ) {
			$provider = qcld_wpbot_core_keyword_provider();
			if ( is_array( $provider ) && isset( $provider['id'], $provider['label'] ) && $key === $provider['id'] ) {
				return (string) $provider['label'];
			}
		}
		// Already a readable label (SEO & AI Search may send one).
		return substr( sanitize_text_field( (string) $id ), 0, 60 );
	}

	/**
	 * Normalise a search the way the shared map does.
	 *
	 * @param string $query Search.
	 * @return string
	 */
	public static function normalise( $query ) {
		if ( class_exists( Keyword_Map::class ) ) {
			return Keyword_Map::normalise( (string) $query );
		}
		return trim( preg_replace( '/\s+/', ' ', strtolower( wp_strip_all_tags( (string) $query ) ) ) );
	}
}
