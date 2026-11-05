<?php
/**
 * Free keyword data from Bing Webmaster Tools (round 3, B): weekly Bing
 * impressions for a search (GetKeywordStats) and related searches
 * (GetRelatedKeywords), with the owner's own free Bing API key.
 *
 * Used only when no paid provider and no Keyword Planner is set, for
 * volumes and ideas. Numbers are searches **on Bing**, labelled as such
 * everywhere ("Bing (free)"): useful to compare searches and see trends,
 * not Google volumes.
 *
 * WPBot Core keeps no Bing key: the module that has one (SEO & AI Search)
 * supplies the API call through the filter qcld_wpbot_core/bing_webmaster_call
 * (callable( string $method, array $args ): array|WP_Error, the "d" payload).
 * Microsoft marks these methods as prerelease; responses are read defensively.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords\Providers;

use QuantumCloud\WPBotCore\Keywords\Abstract_Provider;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Bing (free).
 */
final class Bing_Free extends Abstract_Provider {

	const ID = 'bing_free';

	/**
	 * No key of its own.
	 *
	 * @param string $key Unused.
	 */
	public function __construct( $key = '' ) {
		parent::__construct( (string) $key );
	}

	/**
	 * Is a Bing call available (a module with the owner's Bing key)?
	 *
	 * @return bool
	 */
	public static function available() {
		return is_callable( self::caller() );
	}

	/**
	 * The call supplied by a module.
	 *
	 * @return callable|null
	 */
	private static function caller() {
		/**
		 * Call Bing Webmaster Tools with the owner's key: callable( $method, $args ) returning the "d" payload or WP_Error.
		 *
		 * @param callable|null $call Default null.
		 */
		$call = apply_filters( 'qcld_wpbot_core/bing_webmaster_call', null );
		return is_callable( $call ) ? $call : null;
	}

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return self::ID;
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Bing (free)', 'chatbot' );
	}

	/**
	 * Unit.
	 *
	 * @return string
	 */
	public function unit() {
		return 'free';
	}

	/**
	 * One keyword per call.
	 *
	 * @return int
	 */
	public function batch_size() {
		return 10;
	}

	/**
	 * Free.
	 *
	 * @param string $op    Operation.
	 * @param int    $count Count.
	 * @return float
	 */
	public function estimate( $op, $count ) {
		return 0.0;
	}

	/**
	 * Bing searches a month (last 4 weeks) and a monthly trend, per keyword.
	 *
	 * @param string[] $keywords Keywords.
	 * @param string   $country  Country.
	 * @param string   $language Language.
	 * @return array|WP_Error
	 */
	public function volumes( array $keywords, $country, $language ) {
		$out = array();
		foreach ( array_slice( $keywords, 0, $this->batch_size() ) as $keyword ) {
			$weeks = $this->call(
				'GetKeywordStats',
				array(
					'q'        => (string) $keyword,
					'country'  => self::country( $country ),
					'language' => self::language( $language, $country ),
				)
			);
			if ( is_wp_error( $weeks ) ) {
				return $weeks;
			}
			list( $volume, $trend ) = self::monthly( $weeks );
			$out[]                  = $this->row( $keyword, $volume, null, null, $trend, array( 'volume_source' => self::ID ) );
		}
		return $out;
	}

	/**
	 * Related searches with their Bing searches (last 30 days).
	 *
	 * @param string $seed     Seed.
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @param int    $limit    Rows.
	 * @return array|WP_Error
	 */
	public function ideas( $seed, $country, $language, $limit ) {
		$rows = $this->call(
			'GetRelatedKeywords',
			array(
				'q'         => (string) $seed,
				'country'   => self::country( $country ),
				'language'  => self::language( $language, $country ),
				'startDate' => gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ),
				'endDate'   => gmdate( 'Y-m-d' ),
			)
		);
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$out = array();
		foreach ( (array) $rows as $row ) {
			$keyword = self::field( $row, array( 'Query', 'Keyword', 'query' ) );
			if ( '' === (string) $keyword ) {
				continue;
			}
			$volume = self::field( $row, array( 'Impressions', 'impressions', 'BroadImpressions' ) );
			$out[]  = $this->row( (string) $keyword, null === $volume ? null : (int) $volume, null, null, array(), array( 'volume_source' => self::ID ) );
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Not offered for free.
	 *
	 * @param string $domain   Domain.
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @param int    $limit    Rows.
	 * @return WP_Error
	 */
	public function domain_keywords( $domain, $country, $language, $limit ) {
		return new WP_Error( 'qcld_wpbot_core_not_offered', __( 'Bing (free) does not list the searches another site ranks for. Add a keyword data provider for this.', 'chatbot' ), array( 'status' => 400 ) );
	}

	/**
	 * Not offered for free.
	 *
	 * @param string $domain Domain.
	 * @param int    $limit  Rows.
	 * @return WP_Error
	 */
	public function backlinks( $domain, $limit ) {
		return new WP_Error( 'qcld_wpbot_core_not_offered', __( 'Bing (free) does not list backlinks here. Links > Link Monitor reads Bing link counts.', 'chatbot' ), array( 'status' => 400 ) );
	}

	/**
	 * Nothing to pay.
	 *
	 * @return float
	 */
	public function balance() {
		return 0.0;
	}

	/**
	 * Weekly rows to a monthly figure (last 4 weeks, scaled to 30 days) and a
	 * trend of up to 12 calendar months, oldest first.
	 *
	 * @param array $weeks Rows with Date and Impressions.
	 * @return array{0: int|null, 1: int[]}
	 */
	public static function monthly( array $weeks ) {
		$points = array();
		foreach ( $weeks as $week ) {
			$time   = self::time( self::field( $week, array( 'Date', 'date' ) ) );
			$impr   = self::field( $week, array( 'Impressions', 'impressions' ) );
			$broad  = self::field( $week, array( 'BroadImpressions', 'broadImpressions' ) );
			$amount = null !== $impr ? (int) $impr : ( null !== $broad ? (int) $broad : null );
			if ( $time && null !== $amount ) {
				$points[ $time ] = $amount;
			}
		}
		if ( array() === $points ) {
			return array( null, array() );
		}
		ksort( $points );
		$last   = array_slice( $points, -4, 4, true );
		$volume = (int) round( array_sum( $last ) * 30 / ( 7 * count( $last ) ) );
		$months = array();
		foreach ( $points as $time => $amount ) {
			$key            = gmdate( 'Y-m', $time );
			$months[ $key ] = ( isset( $months[ $key ] ) ? $months[ $key ] : 0 ) + $amount;
		}
		return array( $volume, array_values( array_slice( $months, -12, 12 ) ) );
	}

	/**
	 * One call through the module.
	 *
	 * @param string $method Method.
	 * @param array  $args   Arguments.
	 * @return array|WP_Error
	 */
	private function call( $method, array $args ) {
		$call = self::caller();
		if ( ! $call ) {
			return new WP_Error( 'qcld_wpbot_core_no_bing', __( 'Add your free Bing Webmaster Tools API key in SEO & AI Search > Settings & AI Provider > Connections.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$result = call_user_func( $call, $method, $args );
		return is_wp_error( $result ) ? $result : (array) $result;
	}

	/**
	 * First present field.
	 *
	 * @param mixed    $row   Row.
	 * @param string[] $names Names.
	 * @return mixed|null
	 */
	private static function field( $row, array $names ) {
		foreach ( $names as $name ) {
			if ( is_array( $row ) && isset( $row[ $name ] ) && '' !== $row[ $name ] ) {
				return $row[ $name ];
			}
		}
		return null;
	}

	/**
	 * Bing dates ("/Date(1699999999000)/" or ISO) to a timestamp.
	 *
	 * @param mixed $value Date.
	 * @return int
	 */
	public static function time( $value ) {
		if ( is_string( $value ) && preg_match( '#/Date\((-?\d+)#', $value, $m ) ) {
			return (int) floor( (int) $m[1] / 1000 );
		}
		$time = is_string( $value ) ? strtotime( $value ) : false;
		return false === $time ? 0 : (int) $time;
	}

	/**
	 * Country code Bing expects (lowercase two letters; '' = all).
	 *
	 * @param string $country Country.
	 * @return string
	 */
	private static function country( $country ) {
		$country = strtolower( preg_replace( '/[^a-z]/i', '', (string) $country ) );
		return 2 === strlen( $country ) ? $country : '';
	}

	/**
	 * Language as Bing expects it (for example en-US).
	 *
	 * @param string $language Language.
	 * @param string $country  Country.
	 * @return string
	 */
	private static function language( $language, $country ) {
		$language = strtolower( substr( preg_replace( '/[^a-z]/i', '', (string) $language ), 0, 2 ) );
		$country  = strtoupper( self::country( $country ) );
		if ( '' === $language ) {
			return '';
		}
		return '' !== $country ? $language . '-' . $country : $language;
	}
}
