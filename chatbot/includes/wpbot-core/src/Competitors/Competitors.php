<?php
/**
 * Shared competitor list (round 2, R2): one list of competitor domains that
 * every module reads (SEO & AI Search research and links, AI Writer topic
 * settings). Each entry keeps where it came from and why. Callers check the
 * user's permission before saving; this class only stores and cleans.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Competitors;

defined( 'ABSPATH' ) || exit;

/**
 * Competitor list storage.
 */
final class Competitors {

	const OPTION = 'qcld_wpbot_core_competitors';
	const MAX    = 20;

	/**
	 * Where an entry came from.
	 */
	const SOURCES = array( 'owner', 'ai', 'keyword_provider', 'search_console', 'ai_writer', 'seo' );

	/**
	 * A bare domain from a URL or host ("https://www.Rival.example/shop" → "rival.example"), or '' when it is not a public domain name.
	 *
	 * @param mixed $value URL, host or domain.
	 * @return string
	 */
	public static function domain( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( false === strpos( $value, '://' ) ) {
			$value = 'https://' . ltrim( $value, '/' );
		}
		$host = (string) wp_parse_url( $value, PHP_URL_HOST );
		$host = rtrim( (string) preg_replace( '/^www\./', '', $host ), '.' );
		// Letters, digits and hyphens per label, at least one dot, a letter top-level label (punycode allowed). No IP addresses, no localhost.
		if ( strlen( $host ) > 253 || ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/', $host ) ) {
			return '';
		}
		return $host;
	}

	/**
	 * The site's own domain.
	 *
	 * @return string
	 */
	public static function own_domain() {
		return self::domain( home_url( '/' ) );
	}

	/**
	 * The shared list, newest first as saved.
	 *
	 * @return array[] domain, reason, source, added_at.
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		return self::clean( is_array( $stored ) ? $stored : array(), array() );
	}

	/**
	 * Domains only.
	 *
	 * @return string[]
	 */
	public static function domains() {
		return array_column( self::all(), 'domain' );
	}

	/**
	 * Replace the list. The caller has checked the user's permission.
	 *
	 * @param array $items Strings (domains or URLs) or arrays (domain, reason, source).
	 * @return array[] The saved list.
	 */
	public static function save( array $items ) {
		$before = array_column( self::all(), null, 'domain' );
		$list   = self::clean( $items, $before );
		update_option( self::OPTION, $list, false );
		/**
		 * The shared competitor list changed.
		 *
		 * @param array[] $list domain, reason, source, added_at.
		 */
		do_action( 'qcld_wpbot_core/competitors_changed', $list );
		return $list;
	}

	/**
	 * Add entries to the list, keeping what is there.
	 *
	 * @param array $items Strings or arrays (domain, reason, source).
	 * @return array[] The saved list.
	 */
	public static function add( array $items ) {
		return self::save( array_merge( self::all(), $items ) );
	}

	/**
	 * Clean, de-duplicate and cap a list.
	 *
	 * @param array $items  Items.
	 * @param array $before domain => existing entry (keeps the first added date).
	 * @return array[]
	 */
	private static function clean( array $items, array $before ) {
		$own  = self::own_domain();
		$list = array();
		foreach ( $items as $item ) {
			$item   = is_array( $item ) ? $item : array( 'domain' => $item );
			$domain = self::domain( isset( $item['domain'] ) ? $item['domain'] : '' );
			if ( '' === $domain || $domain === $own || isset( $list[ $domain ] ) ) {
				continue;
			}
			$source          = isset( $item['source'] ) ? sanitize_key( (string) $item['source'] ) : 'owner';
			$list[ $domain ] = array(
				'domain'   => $domain,
				'reason'   => substr( sanitize_text_field( isset( $item['reason'] ) ? (string) $item['reason'] : '' ), 0, 200 ),
				'source'   => in_array( $source, self::SOURCES, true ) ? $source : 'owner',
				'added_at' => isset( $before[ $domain ]['added_at'] ) ? (string) $before[ $domain ]['added_at'] : ( isset( $item['added_at'] ) && '' !== (string) $item['added_at'] ? substr( sanitize_text_field( (string) $item['added_at'] ), 0, 19 ) : current_time( 'mysql', true ) ),
			);
			if ( count( $list ) >= self::MAX ) {
				break;
			}
		}
		return array_values( $list );
	}
}
