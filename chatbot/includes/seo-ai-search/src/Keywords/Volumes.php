<?php
/**
 * Search volumes from the owner's keyword data provider (through WPBot
 * Core): shown on opportunities with cost per click and trend, and turned
 * into potential clicks. Reading cached data costs nothing; lookups show an
 * estimate first and run in the job queue. Everything works without a
 * provider.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Keywords;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Opportunities\Ctr_Curve;
use QuantumCloud\SeoAiSearch\Opportunities\Opportunities;
use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\WPBotCore\Keywords\Keyword_Data;
use QuantumCloud\WPBotCore\Keywords\Keyword_Keys;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Volumes.
 */
final class Volumes {

	/**
	 * Is the core keyword data layer present (WPBot Core with S12)?
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( Keyword_Data::class ) && function_exists( 'qcld_wpbot_core_keyword_data' );
	}

	/**
	 * Is a provider connected?
	 *
	 * @return bool
	 */
	public static function connected() {
		return self::available() && null !== Keyword_Keys::provider();
	}

	/**
	 * Bold notice for a failing keyword key or empty balance (same shape as AI key notices).
	 *
	 * @return array[]
	 */
	public static function notices() {
		return self::available() ? Keyword_Keys::notices() : array();
	}

	/**
	 * Country and language for lookups (settings, else the site locale).
	 *
	 * @return array country, language.
	 */
	public static function place() {
		return array(
			'country'  => (string) Settings::get( 'country' ),
			'language' => (string) Settings::get( 'language' ),
		);
	}

	/**
	 * Cached data for searches (no cost).
	 *
	 * @param string[] $queries Searches.
	 * @return array normalised => row.
	 */
	public static function cached( array $queries ) {
		return self::available() ? qcld_wpbot_core_keyword_data( $queries, self::place() ) : array();
	}

	/**
	 * Clicks a search could bring at position 3 (volume × expected CTR).
	 *
	 * @param int|null $volume Monthly searches.
	 * @param float    $at     Target position.
	 * @return int|null
	 */
	public static function potential_clicks( $volume, $at = 3.0 ) {
		return null === $volume ? null : (int) round( (int) $volume * Ctr_Curve::expected( $at ) );
	}

	/**
	 * Add volume, CPC, trend and potential clicks to opportunities with a search.
	 *
	 * @param array $items Opportunities (shape()).
	 * @return array
	 */
	public static function attach( array $items ) {
		$queries = array_filter( array_column( $items, 'query' ) );
		$data    = $queries ? self::cached( $queries ) : array();
		foreach ( $items as $index => $item ) {
			$row                      = '' !== $item['query'] ? self::row_for( $data, $item['query'] ) : null;
			$items[ $index ]['kdata'] = $row ? array(
				'volume'    => $row['volume'],
				'cpc'       => $row['cpc'],
				'trend'     => $row['trend'],
				'potential' => self::potential_clicks( $row['volume'] ),
			) : null;
		}
		return $items;
	}

	/**
	 * Row for a query from a cached map.
	 *
	 * @param array  $data  Map.
	 * @param string $query Query.
	 * @return array|null
	 */
	public static function row_for( array $data, $query ) {
		$norm = \QuantumCloud\WPBotCore\Map\Keyword_Map::normalise( $query );
		return isset( $data[ $norm ] ) ? $data[ $norm ] : null;
	}

	/**
	 * Searches for a lookup scope.
	 *
	 * @param string $scope opportunities (open ones with a search) | top (top searches from Search Console) | areas (service + area searches of the Local SEO planner).
	 * @param int    $limit Most searches.
	 * @return string[]
	 */
	public static function scope_queries( $scope, $limit = 200 ) {
		if ( 'areas' === $scope ) {
			return array_slice( \QuantumCloud\SeoAiSearch\Local\Area_Planner::searches(), 0, (int) $limit );
		}
		if ( 'top' === $scope ) {
			$period = Period::last( 90 );
			return array_column( Data_Store::top( 'query', $period['start'], $period['end'], max( 1, (int) $limit ), 'impressions' ), 'label' );
		}
		$items = Opportunities::query(
			array(
				'status' => 'open',
				'limit'  => 500,
			)
		)['items'];
		return array_slice( array_values( array_unique( array_filter( array_column( $items, 'query' ) ) ) ), 0, (int) $limit );
	}

	/**
	 * Estimate for a scope.
	 *
	 * @param string $scope Scope.
	 * @param int    $limit Most searches.
	 * @return array|WP_Error
	 */
	public static function estimate( $scope, $limit = 200 ) {
		if ( ! self::available() ) {
			return self::missing_core();
		}
		$place = self::place();
		return Keyword_Data::estimate( self::scope_queries( $scope, $limit ), $place['country'], $place['language'] );
	}

	/**
	 * Start a lookup for a scope (job queue).
	 *
	 * @param string $scope Scope.
	 * @param int    $limit Most searches.
	 * @return array|WP_Error job_id (0 = all cached), estimate.
	 */
	public static function start( $scope, $limit = 200 ) {
		if ( ! self::available() ) {
			return self::missing_core();
		}
		$place    = self::place();
		$queries  = self::scope_queries( $scope, $limit );
		$estimate = Keyword_Data::estimate( $queries, $place['country'], $place['language'] );
		if ( is_wp_error( $estimate ) ) {
			return $estimate;
		}
		$job = Keyword_Data::start_lookup( Module::ID, $queries, $place['country'], $place['language'] );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		return array(
			'job_id'   => (int) $job,
			'estimate' => $estimate,
		);
	}

	/**
	 * Volume text for AI Writer briefs, when cached.
	 *
	 * @param string $query Main search.
	 * @return string
	 */
	public static function brief_text( $query ) {
		if ( '' === (string) $query ) {
			return '';
		}
		$row = self::row_for( self::cached( array( $query ) ), $query );
		if ( ! $row || null === $row['volume'] ) {
			return '';
		}
		/* translators: 1: search, 2: monthly searches. */
		return sprintf( __( 'About %2$s searches a month for "%1$s".', 'chatbot' ), $query, number_format_i18n( $row['volume'] ) );
	}

	/**
	 * Error when WPBot Core has no keyword data layer.
	 *
	 * @return WP_Error
	 */
	private static function missing_core() {
		return new WP_Error( 'qcld_wpbot_seo_no_keyword_data', sprintf( /* translators: %s: product name, for example WPBot. */ __( 'Keyword data needs a newer version of %s. Update the plugin.', 'chatbot' ), \QuantumCloud\SeoAiSearch\Module::product() ), array( 'status' => 400 ) );
	}
}
