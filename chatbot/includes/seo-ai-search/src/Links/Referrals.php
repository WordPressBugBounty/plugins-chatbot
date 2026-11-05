<?php
/**
 * Sites that send visitors, read from Site Insight when it is installed
 * (through its PHP reporting API; nothing is copied).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Referral sources.
 */
final class Referrals {

	/**
	 * Hosts that are search engines, not links.
	 */
	const SEARCH = '/(^|\.)(google|bing|yahoo|duckduckgo|baidu|yandex|ecosia|startpage|qwant|naver|brave|search\.)/i';

	/**
	 * Is Site Insight available?
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( '\QuantumCloud\Insights\Reports\Report_Builder' );
	}

	/**
	 * Referring sites in the last days with sessions, and whether the monitor knows a link from them.
	 *
	 * @param int $days Days.
	 * @return array List of host, sessions, known.
	 */
	public static function sources( $days = 28 ) {
		$rows = array();
		if ( self::available() ) {
			$builder = new \QuantumCloud\Insights\Reports\Report_Builder();
			$rows    = (array) $builder->sources( gmdate( 'Y-m-d H:i:s', time() - (int) $days * DAY_IN_SECONDS ), gmdate( 'Y-m-d H:i:s' ), 100 );
		}
		/**
		 * Referral rows (label, sessions) before filtering.
		 *
		 * @param array $rows Rows.
		 * @param int   $days Days.
		 */
		$rows  = (array) apply_filters( 'qcld_wpbot_seo/referrals', $rows, $days );
		$own   = Link_Finder::host( home_url() );
		$known = array_flip( Link_Store::hosts() );
		$out   = array();
		foreach ( $rows as $row ) {
			$host = strtolower( (string) preg_replace( '/^www\./i', '', trim( (string) ( isset( $row['label'] ) ? $row['label'] : '' ) ) ) );
			if ( '' === $host || false === strpos( $host, '.' ) || ! preg_match( '/^[a-z0-9.-]+$/', $host ) || $host === $own || preg_match( self::SEARCH, $host ) ) {
				continue;
			}
			$out[] = array(
				'host'     => $host,
				'sessions' => (int) $row['sessions'],
				'known'    => isset( $known[ $host ] ),
			);
		}
		return $out;
	}
}
