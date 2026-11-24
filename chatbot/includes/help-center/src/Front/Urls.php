<?php
/**
 * Help Center addresses.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Front;

use QuantumCloud\HelpCenter\Content\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * URLs.
 */
final class Urls {

	/**
	 * The help center home.
	 *
	 * @return string
	 */
	public static function home() {
		$link = get_post_type_archive_link( Post_Type::NAME );
		return $link ? (string) $link : home_url( '/' );
	}

	/**
	 * A search results address.
	 *
	 * @param string $query Search.
	 * @return string
	 */
	public static function search( $query = '' ) {
		return add_query_arg( 's', rawurlencode( (string) $query ), self::home() );
	}
}
