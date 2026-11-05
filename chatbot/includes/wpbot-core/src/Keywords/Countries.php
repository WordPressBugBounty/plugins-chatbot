<?php
/**
 * Country names and Google Ads location codes (2000 + ISO 3166 numeric)
 * for keyword lookups; the default comes from the site locale.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords;

defined( 'ABSPATH' ) || exit;

/**
 * Country table.
 */
final class Countries {

	/**
	 * ISO alpha-2 => [ name, ISO numeric ].
	 */
	const LIST = array(
		'AE' => array( 'United Arab Emirates', 784 ),
		'AR' => array( 'Argentina', 32 ),
		'AT' => array( 'Austria', 40 ),
		'AU' => array( 'Australia', 36 ),
		'BD' => array( 'Bangladesh', 50 ),
		'BE' => array( 'Belgium', 56 ),
		'BR' => array( 'Brazil', 76 ),
		'CA' => array( 'Canada', 124 ),
		'CH' => array( 'Switzerland', 756 ),
		'CL' => array( 'Chile', 152 ),
		'CN' => array( 'China', 156 ),
		'CO' => array( 'Colombia', 170 ),
		'CZ' => array( 'Czechia', 203 ),
		'DE' => array( 'Germany', 276 ),
		'DK' => array( 'Denmark', 208 ),
		'EG' => array( 'Egypt', 818 ),
		'ES' => array( 'Spain', 724 ),
		'FI' => array( 'Finland', 246 ),
		'FR' => array( 'France', 250 ),
		'GB' => array( 'United Kingdom', 826 ),
		'GR' => array( 'Greece', 300 ),
		'HK' => array( 'Hong Kong', 344 ),
		'ID' => array( 'Indonesia', 360 ),
		'IE' => array( 'Ireland', 372 ),
		'IL' => array( 'Israel', 376 ),
		'IN' => array( 'India', 356 ),
		'IT' => array( 'Italy', 380 ),
		'JP' => array( 'Japan', 392 ),
		'KE' => array( 'Kenya', 404 ),
		'KR' => array( 'South Korea', 410 ),
		'MX' => array( 'Mexico', 484 ),
		'MY' => array( 'Malaysia', 458 ),
		'NG' => array( 'Nigeria', 566 ),
		'NL' => array( 'Netherlands', 528 ),
		'NO' => array( 'Norway', 578 ),
		'NZ' => array( 'New Zealand', 554 ),
		'PH' => array( 'Philippines', 608 ),
		'PK' => array( 'Pakistan', 586 ),
		'PL' => array( 'Poland', 616 ),
		'PT' => array( 'Portugal', 620 ),
		'RO' => array( 'Romania', 642 ),
		'SA' => array( 'Saudi Arabia', 682 ),
		'SE' => array( 'Sweden', 752 ),
		'SG' => array( 'Singapore', 702 ),
		'TH' => array( 'Thailand', 764 ),
		'TR' => array( 'Turkey', 792 ),
		'TW' => array( 'Taiwan', 158 ),
		'UA' => array( 'Ukraine', 804 ),
		'US' => array( 'United States', 840 ),
		'VN' => array( 'Vietnam', 704 ),
		'ZA' => array( 'South Africa', 710 ),
	);

	/**
	 * Countries for a select field.
	 *
	 * @return array code => name.
	 */
	public static function names() {
		$out = array();
		foreach ( self::LIST as $code => $row ) {
			$out[ $code ] = $row[0];
		}
		return $out;
	}

	/**
	 * Google Ads location code, or 0.
	 *
	 * @param string $country ISO alpha-2.
	 * @return int
	 */
	public static function location_code( $country ) {
		$country = strtoupper( (string) $country );
		return isset( self::LIST[ $country ] ) ? 2000 + (int) self::LIST[ $country ][1] : 0;
	}

	/**
	 * Clean country code (default from the site locale, then US).
	 *
	 * @param string $country Code or ''.
	 * @return string
	 */
	public static function clean( $country ) {
		$country = strtoupper( (string) $country );
		if ( 'UK' === $country ) {
			$country = 'GB';
		}
		if ( isset( self::LIST[ $country ] ) ) {
			return $country;
		}
		$parts = explode( '_', (string) get_locale() );
		$guess = isset( $parts[1] ) ? strtoupper( $parts[1] ) : '';
		return isset( self::LIST[ $guess ] ) ? $guess : 'US';
	}

	/**
	 * Clean language code (default from the site locale).
	 *
	 * @param string $language Code or ''.
	 * @return string
	 */
	public static function language( $language ) {
		$language = strtolower( (string) $language );
		if ( preg_match( '/^[a-z]{2}$/', $language ) ) {
			return $language;
		}
		$parts = explode( '_', (string) get_locale() );
		return preg_match( '/^[a-z]{2}$/', $parts[0] ) ? $parts[0] : 'en';
	}
}
