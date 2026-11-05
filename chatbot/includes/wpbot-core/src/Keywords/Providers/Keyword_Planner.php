<?php
/**
 * Google Keyword Planner as a keyword data source (round 2, R5), through the
 * read-only Google Ads connection (Connections\Google_Ads::planner()). It
 * supplies volumes (12-month average), a 12-month trend, competition (level
 * and 0–100 index) and the top-of-page bid range; ideas from a seed keyword;
 * and ideas Google associates with a site (not the site's rankings). It has
 * no backlinks and no difficulty. Nothing is charged per keyword; requests
 * count against the Google Ads daily quota.
 *
 * Places and languages are Google's own constants, read once with the
 * reporting API and cached.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords\Providers;

use QuantumCloud\WPBotCore\Connections\Google_Ads;
use QuantumCloud\WPBotCore\Keywords\Provider;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword Planner provider.
 */
final class Keyword_Planner implements Provider {

	const ID        = 'google_ads_planner';
	const CONSTANTS = 'qcld_wpbot_core_ads_constants';

	/**
	 * Stable id.
	 *
	 * @return string
	 */
	public function id() {
		return self::ID;
	}

	/**
	 * Name shown to the owner.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Google Keyword Planner', 'chatbot' );
	}

	/**
	 * Unit (nothing is charged).
	 *
	 * @return string
	 */
	public function unit() {
		return 'usd';
	}

	/**
	 * Keywords per volume request (Google allows up to 10,000).
	 *
	 * @return int
	 */
	public function batch_size() {
		return 1000;
	}

	/**
	 * Nothing is charged per keyword.
	 *
	 * @param string $op    Operation.
	 * @param int    $count Count.
	 * @return float
	 */
	public function estimate( $op, $count ) {
		unset( $op, $count );
		return 0.0;
	}

	/**
	 * Volumes for keywords (historical metrics).
	 *
	 * @param string[] $keywords Keywords.
	 * @param string   $country  Country.
	 * @param string   $language Language.
	 * @return array|WP_Error
	 */
	public function volumes( array $keywords, $country, $language ) {
		$body = $this->place( $country, $language );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$body['keywords']                 = array_values( array_slice( array_map( 'strval', $keywords ), 0, 10000 ) );
		$body['historicalMetricsOptions'] = array( 'includeAverageCpc' => true );
		$data                             = Google_Ads::planner( 'generateKeywordHistoricalMetrics', $body );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$rows = array();
		foreach ( isset( $data['results'] ) ? (array) $data['results'] : array() as $result ) {
			$metrics = isset( $result['keywordMetrics'] ) ? (array) $result['keywordMetrics'] : array();
			$rows[]  = $this->row( isset( $result['text'] ) ? (string) $result['text'] : '', $metrics );
			// Google merges close variants into one result: each variant gets the same numbers.
			foreach ( isset( $result['closeVariants'] ) ? (array) $result['closeVariants'] : array() as $variant ) {
				$rows[] = $this->row( (string) $variant, $metrics );
			}
		}
		return $rows;
	}

	/**
	 * Ideas for a seed keyword.
	 *
	 * @param string $seed     Seed.
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @param int    $limit    Rows.
	 * @return array|WP_Error
	 */
	public function ideas( $seed, $country, $language, $limit ) {
		return $this->generate( array( 'keywordSeed' => array( 'keywords' => array( (string) $seed ) ) ), $country, $language, $limit );
	}

	/**
	 * Ideas Google associates with a site (not its rankings: rows have no position).
	 *
	 * @param string $domain   Domain.
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @param int    $limit    Rows.
	 * @return array|WP_Error
	 */
	public function domain_keywords( $domain, $country, $language, $limit ) {
		$rows = $this->generate( array( 'siteSeed' => array( 'site' => (string) $domain ) ), $country, $language, $limit );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['position']   = null;
			$rows[ $index ]['site_ideas'] = true;
		}
		return $rows;
	}

	/**
	 * Keyword Planner has no backlink data.
	 *
	 * @param string $domain Domain.
	 * @param int    $limit  Rows.
	 * @return WP_Error
	 */
	public function backlinks( $domain, $limit ) {
		unset( $domain, $limit );
		return new WP_Error( 'qcld_wpbot_core_keyword_unsupported', __( 'Google Keyword Planner has no backlink data. Connect a keyword data provider for backlinks.', 'chatbot' ), array( 'status' => 400 ) );
	}

	/**
	 * No balance: nothing is charged.
	 *
	 * @return float
	 */
	public function balance() {
		return 0.0;
	}

	/**
	 * Run generateKeywordIdeas with a seed.
	 *
	 * @param array  $seed     Seed part of the body.
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @param int    $limit    Rows.
	 * @return array|WP_Error
	 */
	private function generate( array $seed, $country, $language, $limit ) {
		$body = $this->place( $country, $language );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$body = array_merge(
			$body,
			$seed,
			array(
				'includeAdultKeywords'     => false,
				'pageSize'                 => max( 10, min( 1000, (int) $limit ) ),
				'historicalMetricsOptions' => array( 'includeAverageCpc' => true ),
			)
		);
		$data = Google_Ads::planner( 'generateKeywordIdeas', $body );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$rows = array();
		foreach ( isset( $data['results'] ) ? (array) $data['results'] : array() as $result ) {
			$rows[] = $this->row( isset( $result['text'] ) ? (string) $result['text'] : '', isset( $result['keywordIdeaMetrics'] ) ? (array) $result['keywordIdeaMetrics'] : array() );
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) $b['volume'] <=> (int) $a['volume'];
			}
		);
		return array_slice( $rows, 0, max( 10, (int) $limit ) );
	}

	/**
	 * One row from Google's metrics (micros to money; index to 0–1; months oldest first).
	 *
	 * @param string $keyword Keyword.
	 * @param array  $m       KeywordPlanHistoricalMetrics.
	 * @return array keyword, volume, cpc, competition (0–1), trend, competition_level, bid_low, bid_high.
	 */
	private function row( $keyword, array $m ) {
		$months = isset( $m['monthlySearchVolumes'] ) ? (array) $m['monthlySearchVolumes'] : array();
		$order  = array( 'JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER' );
		usort(
			$months,
			static function ( $a, $b ) use ( $order ) {
				$ma = isset( $a['month'] ) ? (int) array_search( (string) $a['month'], $order, true ) : 0;
				$mb = isset( $b['month'] ) ? (int) array_search( (string) $b['month'], $order, true ) : 0;
				return array( isset( $a['year'] ) ? (int) $a['year'] : 0, $ma ) <=> array( isset( $b['year'] ) ? (int) $b['year'] : 0, $mb );
			}
		);
		$trend = array();
		foreach ( array_slice( $months, -12 ) as $month ) {
			$trend[] = isset( $month['monthlySearches'] ) ? (int) $month['monthlySearches'] : 0;
		}
		$low  = isset( $m['lowTopOfPageBidMicros'] ) ? round( (float) $m['lowTopOfPageBidMicros'] / 1000000, 2 ) : null;
		$high = isset( $m['highTopOfPageBidMicros'] ) ? round( (float) $m['highTopOfPageBidMicros'] / 1000000, 2 ) : null;
		$cpc  = isset( $m['averageCpcMicros'] ) ? round( (float) $m['averageCpcMicros'] / 1000000, 2 ) : ( null !== $low && null !== $high ? round( ( $low + $high ) / 2, 2 ) : null );
		$lvl  = isset( $m['competition'] ) ? strtolower( (string) $m['competition'] ) : '';
		return array(
			'keyword'           => (string) $keyword,
			'volume'            => isset( $m['avgMonthlySearches'] ) ? (int) $m['avgMonthlySearches'] : null,
			'cpc'               => $cpc,
			'competition'       => isset( $m['competitionIndex'] ) ? max( 0.0, min( 1.0, (float) $m['competitionIndex'] / 100 ) ) : null,
			'trend'             => $trend,
			'competition_level' => in_array( $lvl, array( 'low', 'medium', 'high' ), true ) ? $lvl : '',
			'bid_low'           => $low,
			'bid_high'          => $high,
		);
	}

	/**
	 * Google's language and place for a request (looked up once, cached).
	 *
	 * @param string $country  ISO country code.
	 * @param string $language Language code.
	 * @return array|WP_Error language, geoTargetConstants, keywordPlanNetwork.
	 */
	private function place( $country, $language ) {
		$cache   = get_option( self::CONSTANTS, array() );
		$cache   = is_array( $cache ) ? $cache : array();
		$country = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $country ) );
		$lang    = strtolower( preg_replace( '/[^A-Za-z-]/', '', (string) $language ) );
		if ( '' !== $country && ! isset( $cache['geo'][ $country ] ) ) {
			$rows = Google_Ads::search( "SELECT geo_target_constant.resource_name FROM geo_target_constant WHERE geo_target_constant.country_code = '" . $country . "' AND geo_target_constant.target_type = 'Country'" );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			$cache['geo'][ $country ] = isset( $rows[0]['geoTargetConstant']['resourceName'] ) ? (string) $rows[0]['geoTargetConstant']['resourceName'] : '';
		}
		if ( '' !== $lang && ! isset( $cache['lang'][ $lang ] ) ) {
			$rows = Google_Ads::search( "SELECT language_constant.resource_name FROM language_constant WHERE language_constant.code = '" . $lang . "'" );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			$cache['lang'][ $lang ] = isset( $rows[0]['languageConstant']['resourceName'] ) ? (string) $rows[0]['languageConstant']['resourceName'] : '';
		}
		update_option( self::CONSTANTS, $cache, false );
		$body = array( 'keywordPlanNetwork' => 'GOOGLE_SEARCH' );
		if ( '' !== $country && '' !== $cache['geo'][ $country ] ) {
			$body['geoTargetConstants'] = array( $cache['geo'][ $country ] );
		}
		if ( '' !== $lang && '' !== $cache['lang'][ $lang ] ) {
			$body['language'] = $cache['lang'][ $lang ];
		}
		return $body;
	}
}
