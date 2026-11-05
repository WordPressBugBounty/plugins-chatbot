<?php
/**
 * Local SEO checks for a location: details filled in, opening hours,
 * schema, a page showing the address, and the same phone number across
 * the site (name, address and phone consistency).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Local;

use QuantumCloud\SeoAiSearch\Onpage\Checks;
use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;
use QuantumCloud\SeoAiSearch\Technical\Scan;

defined( 'ABSPATH' ) || exit;

/**
 * Local checks.
 */
final class Local_Checks {

	/**
	 * Checklist for a location.
	 *
	 * @param array $location Location.
	 * @return array items (id, label, pass, message), phones (other numbers found with pages).
	 */
	public static function run( array $location ) {
		$items   = array();
		$items[] = self::item( 'details', __( 'Name, address and phone', 'chatbot' ), '' !== $location['name'] && '' !== $location['street'] && '' !== $location['city'] && '' !== $location['phone'], __( 'Fill in the business name, street, town and phone.', 'chatbot' ) );
		$items[] = self::item( 'hours', __( 'Opening hours', 'chatbot' ), count( $location['hours'] ) >= 5, __( 'Add opening hours for each day (or mark it closed).', 'chatbot' ) );
		$plugin  = Seo_Plugins::label();
		$items[] = self::item(
			'schema',
			__( 'LocalBusiness schema', 'chatbot' ),
			'' === $plugin,
			/* translators: %s: SEO plugin name. */
			sprintf( __( '%s prints the schema on this site: add your business in its Local SEO settings, so it is not printed twice.', 'chatbot' ), $plugin )
		);
		$page_ok = false;
		if ( $location['page_id'] && 'publish' === get_post_status( $location['page_id'] ) ) {
			$text  = Checks::text( (string) get_post_field( 'post_content', $location['page_id'] ) );
			$phone = '' === $location['phone'];
			foreach ( self::phones_in( $text ) as $number ) {
				$phone = $phone || self::same( $number, self::digits( $location['phone'] ) );
			}
			$page_ok = ( '' === $location['street'] || false !== stripos( $text, $location['street'] ) ) && $phone;
		}
		$items[] = self::item( 'page', __( 'Contact or location page', 'chatbot' ), $page_ok, __( 'Choose the page that shows this address, and show the same address and phone on it.', 'chatbot' ) );
		$phones  = '' !== $location['phone'] ? self::other_phones( $location['phone'] ) : array();
		$items[] = self::item( 'nap', __( 'Same phone number everywhere', 'chatbot' ), array() === $phones, __( 'Other phone numbers appear on your site; use one number so Google and customers trust your details.', 'chatbot' ) );
		return array(
			'items'  => $items,
			'phones' => $phones,
		);
	}

	/**
	 * Phone numbers on the site that differ from the business number.
	 *
	 * @param string $phone Business phone.
	 * @return array List of number, pages[].
	 */
	public static function other_phones( $phone ) {
		$main  = self::digits( $phone );
		$found = array();
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded scan of recent content.
				's'              => '',
			)
		);
		$texts = array();
		foreach ( $posts as $post ) {
			$texts[ (string) get_permalink( $post ) ] = Checks::text( $post->post_content );
		}
		// The front page as visitors see it (footer and header numbers), cached an hour.
		$home = get_transient( 'qcld_wpbot_seo_home_text' );
		if ( false === $home ) {
			$fetched = Scan::fetch( home_url( '/' ), 3 );
			$home    = is_array( $fetched ) && 200 === $fetched['code'] ? Checks::text( $fetched['body'] ) : '';
			set_transient( 'qcld_wpbot_seo_home_text', $home, HOUR_IN_SECONDS );
		}
		if ( '' !== $home ) {
			$texts[ home_url( '/' ) ] = (string) $home;
		}
		foreach ( $texts as $url => $text ) {
			foreach ( self::phones_in( $text ) as $number ) {
				if ( ! self::same( $number, $main ) ) {
					$found[ $number ][] = $url;
				}
			}
		}
		$out = array();
		foreach ( $found as $number => $pages ) {
			$out[] = array(
				'number' => $number,
				'pages'  => array_slice( array_values( array_unique( $pages ) ), 0, 5 ),
			);
		}
		return $out;
	}

	/**
	 * Phone-like numbers in a text (8–15 digits), as digits.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function phones_in( $text ) {
		preg_match_all( '/(?<![\w\d])\+?\(?\d[\d ()\-.]{6,18}\d(?![\w\d])/u', (string) $text, $matches );
		$out = array();
		foreach ( $matches[0] as $match ) {
			$digits = self::digits( $match );
			// Skip dates, years and long codes.
			if ( strlen( $digits ) >= 8 && strlen( $digits ) <= 15 && ! preg_match( '/^(19|20)\d{6}$/', $digits ) && ! preg_match( '#\d{1,4}[-./]\d{1,2}[-./]\d{1,4}#', $match ) ) {
				$out[] = $digits;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether two numbers are the same (last 8 digits, so country codes match).
	 *
	 * @param string $a Digits.
	 * @param string $b Digits.
	 * @return bool
	 */
	public static function same( $a, $b ) {
		return substr( $a, -8 ) === substr( $b, -8 );
	}

	/**
	 * Digits only.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function digits( $text ) {
		return (string) preg_replace( '/\D/', '', (string) $text );
	}

	/**
	 * Checklist item.
	 *
	 * @param string $id      Id.
	 * @param string $label   Label.
	 * @param bool   $pass    Passed.
	 * @param string $message Advice.
	 * @return array
	 */
	private static function item( $id, $label, $pass, $message ) {
		return array(
			'id'      => $id,
			'label'   => $label,
			'pass'    => (bool) $pass,
			'message' => $pass ? '' : $message,
		);
	}
}
