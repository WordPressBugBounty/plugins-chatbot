<?php
/**
 * Local citations: a checklist of key directories for the owner's country
 * and business type, with a name, address and phone (NAP) consistency
 * check against the main location in Local SEO.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Local\Local_Checks;
use QuantumCloud\SeoAiSearch\Local\Locations;
use QuantumCloud\SeoAiSearch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Citation checklist.
 */
final class Citations {

	/**
	 * Option with the owner's entries.
	 */
	const OPTION = 'qcld_wpbot_seo_citations';

	/**
	 * Directories for a country (ISO code) and business type.
	 *
	 * @param string $country Country code.
	 * @param string $type    schema.org business type.
	 * @return array id => name, url.
	 */
	public static function directories( $country, $type = '' ) {
		$list       = array(
			'google'   => array( 'Google Business Profile', 'https://business.google.com/' ),
			'bing'     => array( 'Bing Places for Business', 'https://www.bingplaces.com/' ),
			'apple'    => array( 'Apple Business Connect', 'https://businessconnect.apple.com/' ),
			'facebook' => array( 'Facebook Page', 'https://www.facebook.com/pages/create/' ),
			'yelp'     => array( 'Yelp for Business', 'https://business.yelp.com/' ),
		);
		$by_country = array(
			'us' => array(
				'bbb'         => array( 'Better Business Bureau', 'https://www.bbb.org/' ),
				'yellowpages' => array( 'YP (Yellow Pages)', 'https://www.yellowpages.com/' ),
			),
			'gb' => array(
				'yell'    => array( 'Yell', 'https://www.yell.com/' ),
				'thomson' => array( 'Thomson Local', 'https://www.thomsonlocal.com/' ),
				'scoot'   => array( 'Scoot', 'https://www.scoot.co.uk/' ),
			),
			'ca' => array(
				'ypca'      => array( 'Yellow Pages Canada', 'https://www.yellowpages.ca/' ),
				'canada411' => array( 'Canada411', 'https://www.canada411.ca/' ),
			),
			'au' => array(
				'ypau'      => array( 'Yellow Pages Australia', 'https://www.yellowpages.com.au/' ),
				'truelocal' => array( 'True Local', 'https://www.truelocal.com.au/' ),
			),
			'in' => array(
				'justdial' => array( 'Justdial', 'https://www.justdial.com/' ),
				'sulekha'  => array( 'Sulekha', 'https://www.sulekha.com/' ),
			),
			'de' => array(
				'gelbeseiten' => array( 'Gelbe Seiten', 'https://www.gelbeseiten.de/' ),
				'oertliche'   => array( 'Das Örtliche', 'https://www.dasoertliche.de/' ),
			),
			'fr' => array(
				'pagesjaunes' => array( 'PagesJaunes', 'https://www.pagesjaunes.fr/' ),
			),
		);
		$country    = strtolower( (string) $country );
		if ( 'uk' === $country ) {
			$country = 'gb';
		}
		if ( isset( $by_country[ $country ] ) ) {
			$list += $by_country[ $country ];
		}
		if ( in_array( $type, array( 'Restaurant', 'CafeOrCoffeeShop', 'BarOrPub', 'Bakery', 'Hotel', 'LodgingBusiness', 'TouristAttraction' ), true ) ) {
			$list['tripadvisor'] = array( 'Tripadvisor', 'https://www.tripadvisor.com/Owners' );
		}
		if ( in_array( $type, array( 'HomeAndConstructionBusiness', 'GeneralContractor', 'Plumber', 'Electrician', 'RoofingContractor', 'HousePainter' ), true ) ) {
			$list['houzz'] = array( 'Houzz', 'https://www.houzz.com/pro' );
		}
		/**
		 * Citation directories for a country and business type.
		 *
		 * @param array  $list    id => [ name, url ].
		 * @param string $country Country code.
		 * @param string $type    Business type.
		 */
		$list = (array) apply_filters( 'qcld_wpbot_seo/citation_directories', $list, $country, $type );
		$out  = array();
		foreach ( $list as $id => $item ) {
			$out[ sanitize_key( $id ) ] = array(
				'name' => (string) $item[0],
				'url'  => (string) $item[1],
			);
		}
		return $out;
	}

	/**
	 * Checklist with the owner's entries and the NAP check.
	 *
	 * @return array location (name, address, phone), items, summary.
	 */
	public static function view() {
		$location = Locations::main();
		$country  = $location && '' !== $location['country'] ? $location['country'] : (string) Settings::get( 'country' );
		if ( '' === $country ) {
			$parts   = explode( '_', (string) get_locale() );
			$country = isset( $parts[1] ) ? $parts[1] : '';
		}
		$nap   = self::nap( $location );
		$saved = self::saved();
		$items = array();
		$sum   = array(
			'listed'     => 0,
			'mismatched' => 0,
			'total'      => 0,
		);
		foreach ( self::directories( $country, $location ? $location['type'] : '' ) as $id => $dir ) {
			$entry   = isset( $saved[ $id ] ) ? $saved[ $id ] : array();
			$status  = isset( $entry['status'] ) ? $entry['status'] : 'unknown';
			$issues  = 'listed' === $status ? self::compare( $nap, $entry ) : array();
			$items[] = array_merge(
				$dir,
				array(
					'id'      => $id,
					'status'  => $status,
					'listing' => isset( $entry['listing'] ) ? $entry['listing'] : '',
					'name_as' => isset( $entry['name'] ) ? $entry['name'] : '',
					'address' => isset( $entry['address'] ) ? $entry['address'] : '',
					'phone'   => isset( $entry['phone'] ) ? $entry['phone'] : '',
					'issues'  => $issues,
				)
			);
			++$sum['total'];
			if ( 'listed' === $status ) {
				++$sum['listed'];
				if ( $issues ) {
					++$sum['mismatched'];
				}
			}
		}
		return array(
			'country'  => strtoupper( $country ),
			'location' => $nap,
			'items'    => $items,
			'summary'  => $sum,
		);
	}

	/**
	 * Save the owner's entry for a directory.
	 *
	 * @param string $id    Directory id.
	 * @param array  $entry status (listed|missing|unknown), listing URL, name, address, phone as shown.
	 * @return array View.
	 */
	public static function save( $id, array $entry ) {
		$saved                        = self::saved();
		$saved[ sanitize_key( $id ) ] = array(
			'status'  => isset( $entry['status'] ) && in_array( $entry['status'], array( 'listed', 'missing', 'unknown' ), true ) ? $entry['status'] : 'unknown',
			'listing' => esc_url_raw( isset( $entry['listing'] ) ? (string) $entry['listing'] : '', array( 'http', 'https' ) ),
			'name'    => sanitize_text_field( isset( $entry['name'] ) ? (string) $entry['name'] : '' ),
			'address' => sanitize_text_field( isset( $entry['address'] ) ? (string) $entry['address'] : '' ),
			'phone'   => sanitize_text_field( isset( $entry['phone'] ) ? (string) $entry['phone'] : '' ),
		);
		update_option( self::OPTION, array_slice( $saved, 0, 200, true ), false );
		return self::view();
	}

	/**
	 * Differences between a listing and the main location (pure).
	 *
	 * @param array $nap   name, address, phone from Local SEO.
	 * @param array $entry name, address, phone as shown on the listing.
	 * @return string[] name, address, phone.
	 */
	public static function compare( array $nap, array $entry ) {
		$issues = array();
		if ( '' !== $nap['name'] && ! empty( $entry['name'] ) && self::norm( $nap['name'] ) !== self::norm( $entry['name'] ) ) {
			$issues[] = 'name';
		}
		if ( '' !== $nap['phone'] && ! empty( $entry['phone'] ) && ! Local_Checks::same( Local_Checks::digits( $nap['phone'] ), Local_Checks::digits( $entry['phone'] ) ) ) {
			$issues[] = 'phone';
		}
		if ( '' !== $nap['street'] && ! empty( $entry['address'] ) ) {
			$listing = self::norm( self::abbreviations( $entry['address'] ) );
			if ( false === strpos( $listing, self::norm( self::abbreviations( $nap['street'] ) ) ) || ( '' !== $nap['postcode'] && false === strpos( str_replace( ' ', '', $listing ), str_replace( ' ', '', self::norm( $nap['postcode'] ) ) ) ) ) {
				$issues[] = 'address';
			}
		}
		return $issues;
	}

	/**
	 * Name, address and phone of the main location.
	 *
	 * @param array|null $location Location.
	 * @return array
	 */
	private static function nap( $location ) {
		$location = $location ? $location : Locations::blank();
		return array(
			'name'     => $location['name'],
			'street'   => $location['street'],
			'postcode' => $location['postcode'],
			'address'  => trim( implode( ', ', array_filter( array( $location['street'], $location['city'], $location['postcode'] ) ) ) ),
			'phone'    => $location['phone'],
		);
	}

	/**
	 * Common street abbreviations written out.
	 *
	 * @param string $text Address.
	 * @return string
	 */
	private static function abbreviations( $text ) {
		$map = array(
			'/\bst\b\.?/i'   => 'street',
			'/\brd\b\.?/i'   => 'road',
			'/\bave?\b\.?/i' => 'avenue',
			'/\bblvd\b\.?/i' => 'boulevard',
			'/\bdr\b\.?/i'   => 'drive',
			'/\bln\b\.?/i'   => 'lane',
		);
		return (string) preg_replace( array_keys( $map ), array_values( $map ), (string) $text );
	}

	/**
	 * Lowercase letters and digits.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function norm( $text ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text ) : strtolower( (string) $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text ) ) );
	}

	/**
	 * Saved entries.
	 *
	 * @return array
	 */
	private static function saved() {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}
}
