<?php
/**
 * The Help Center's public address: cleaning and clash checks (owner decision H11).
 *
 * The address is one or more URL segments such as "help" or "support/docs".
 * check() reports clashes before the owner saves a new address: WordPress's own
 * paths, an existing page or post with that path, another post type or taxonomy
 * using it, or a separate WordPress install in that folder (as on wpbot.pro/docs/).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

defined( 'ABSPATH' ) || exit;

/**
 * Address rules.
 */
final class Address {

	/**
	 * First segments WordPress or common setups reserve.
	 *
	 * @var string[]
	 */
	const RESERVED = array( 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'comments', 'search', 'author', 'page', 'embed', 'trackback', 'category', 'tag', 'attachment', 'type', 'xmlrpc.php', 'wp-login.php' );

	/**
	 * Clean an address: lower case, segments made of letters, digits and hyphens, at most 3 segments.
	 *
	 * @param string $value Raw value.
	 * @return string Cleaned address, or an empty string.
	 */
	public static function sanitize( $value ) {
		$parts = array();
		foreach ( explode( '/', strtolower( trim( (string) $value ) ) ) as $part ) {
			$part = preg_replace( '/[^a-z0-9-]+/', '-', $part );
			$part = trim( (string) $part, '-' );
			if ( '' !== $part ) {
				$parts[] = $part;
			}
		}
		return implode( '/', array_slice( $parts, 0, 3 ) );
	}

	/**
	 * Problems with an address. Empty when it can be used.
	 *
	 * @param string   $base        Cleaned address.
	 * @param callable $page_exists Returns true when a page or post has this path (default: get_page_by_path()).
	 * @param callable $slug_used   Returns the label of a post type or taxonomy (other than ours) whose URL uses it, or ''.
	 * @param string   $site_root   Absolute path of the site root (default: ABSPATH).
	 * @return string[] Problem codes: empty, reserved, page, slug, folder.
	 */
	public static function check( $base, $page_exists = null, $slug_used = null, $site_root = null ) {
		$base = self::sanitize( $base );
		if ( '' === $base ) {
			return array( 'empty' );
		}
		$problems = array();
		$first    = explode( '/', $base )[0];
		if ( in_array( $first, self::RESERVED, true ) ) {
			$problems[] = 'reserved';
		}
		$page_exists = is_callable( $page_exists ) ? $page_exists : array( self::class, 'page_exists' );
		if ( $page_exists( $base ) ) {
			$problems[] = 'page';
		}
		$slug_used = is_callable( $slug_used ) ? $slug_used : array( self::class, 'slug_used' );
		if ( '' !== (string) $slug_used( $base ) ) {
			$problems[] = 'slug';
		}
		$root = rtrim( null === $site_root ? ABSPATH : (string) $site_root, '/\\' );
		$dir  = $root . '/' . $base;
		if ( is_dir( $dir ) ) {
			$problems[] = 'folder';
		}
		return $problems;
	}

	/**
	 * Owner-facing text for a problem code.
	 *
	 * @param string $code Problem code.
	 * @return string
	 */
	public static function message( $code ) {
		$messages = array(
			'empty'    => __( 'Enter an address, for example help.', 'chatbot' ),
			'reserved' => __( 'WordPress already uses this address.', 'chatbot' ),
			'page'     => __( 'A page or post already has this address.', 'chatbot' ),
			'slug'     => __( 'Another content type or taxonomy already uses this address.', 'chatbot' ),
			'folder'   => __( 'A folder with this name exists in the site root (for example a separate WordPress install), so visitors would not reach the Help Center there.', 'chatbot' ),
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
	}

	/**
	 * Whether a page or post has this path.
	 *
	 * @param string $base Address.
	 * @return bool
	 */
	public static function page_exists( $base ) {
		return function_exists( 'get_page_by_path' ) && null !== get_page_by_path( $base, OBJECT, array( 'page', 'post' ) );
	}

	/**
	 * Label of a post type or taxonomy (not the Help Center's) whose URL starts with this address.
	 *
	 * @param string $base Address.
	 * @return string
	 */
	public static function slug_used( $base ) {
		$ours = array( Content\Post_Type::NAME, Content\Taxonomies::KB, Content\Taxonomies::CATEGORY, Content\Taxonomies::TAG );
		$all  = array();
		if ( function_exists( 'get_post_types' ) ) {
			$all = array_merge( $all, (array) get_post_types( array(), 'objects' ) );
		}
		if ( function_exists( 'get_taxonomies' ) ) {
			$all = array_merge( $all, (array) get_taxonomies( array(), 'objects' ) );
		}
		foreach ( $all as $object ) {
			if ( ! is_object( $object ) || in_array( $object->name, $ours, true ) || empty( $object->rewrite['slug'] ) ) {
				continue;
			}
			$slug = trim( (string) $object->rewrite['slug'], '/' );
			if ( $slug === $base || 0 === strpos( $slug . '/', $base . '/' ) ) {
				return isset( $object->label ) ? (string) $object->label : (string) $object->name;
			}
		}
		return '';
	}
}
