<?php
/**
 * Find a link to a given page inside another page's HTML (pure, unit-tested).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Link lookup in HTML: href match, rel and anchor text.
 */
final class Link_Finder {

	/**
	 * Find the first link in $html pointing at $target (or at any page of
	 * $target's site when $any_page is true).
	 *
	 * @param string $html     Page HTML.
	 * @param string $target   Target URL.
	 * @param string $base     URL of the page (for relative links).
	 * @param bool   $any_page Match any page on the target's host.
	 * @return array|null href, rel (follow|nofollow|sponsored|ugc), anchor.
	 */
	public static function find( $html, $target, $base = '', $any_page = false ) {
		$want = self::key( $target );
		if ( '' === $want['host'] ) {
			return null;
		}
		if ( ! preg_match_all( '#<a\b([^>]*)>(.*?)</a>#is', (string) $html, $matches, PREG_SET_ORDER ) ) {
			return null;
		}
		foreach ( $matches as $match ) {
			if ( ! preg_match( '#\bhref\s*=\s*("|\')(.*?)\1#is', $match[1], $href ) && ! preg_match( '#\bhref\s*=\s*([^\s>]+)#i', $match[1], $href ) ) {
				continue;
			}
			$url = html_entity_decode( trim( end( $href ) ), ENT_QUOTES, 'UTF-8' );
			if ( '' !== $base && ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ) {
				$url = self::absolute( $url, $base );
			}
			$got = self::key( $url );
			if ( $got['host'] !== $want['host'] || ( ! $any_page && $got['path'] !== $want['path'] ) ) {
				continue;
			}
			$rel = '';
			if ( preg_match( '#\brel\s*=\s*("|\')(.*?)\1#is', $match[1], $rel_match ) ) {
				$rel = strtolower( $rel_match[2] );
			}
			return array(
				'href'   => $url,
				'rel'    => self::rel_kind( $rel ),
				'anchor' => self::text( $match[2] ),
			);
		}
		return null;
	}

	/**
	 * Main rel value: sponsored, ugc, nofollow or follow.
	 *
	 * @param string $rel rel attribute.
	 * @return string
	 */
	public static function rel_kind( $rel ) {
		$parts = preg_split( '/\s+/', strtolower( trim( (string) $rel ) ) );
		foreach ( array( 'sponsored', 'ugc', 'nofollow' ) as $kind ) {
			if ( in_array( $kind, $parts, true ) ) {
				return $kind;
			}
		}
		return 'follow';
	}

	/**
	 * Comparable form of a URL: host without www, path without trailing slash.
	 *
	 * @param string $url URL.
	 * @return array host, path.
	 */
	public static function key( $url ) {
		$parts = wp_parse_url( trim( (string) $url ) );
		$host  = isset( $parts['host'] ) ? strtolower( (string) preg_replace( '/^www\./i', '', $parts['host'] ) ) : '';
		$path  = isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';
		return array(
			'host' => $host,
			'path' => '' === $path ? '/' : strtolower( rawurldecode( $path ) ),
		);
	}

	/**
	 * Host of a URL without www.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function host( $url ) {
		return self::key( $url )['host'];
	}

	/**
	 * Visible anchor text (an image's alt when the link wraps an image).
	 *
	 * @param string $inner Inner HTML.
	 * @return string
	 */
	public static function text( $inner ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $inner ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $text && preg_match( '#<img\b[^>]*\balt\s*=\s*("|\')(.*?)\1#is', (string) $inner, $alt ) ) {
			$text = trim( html_entity_decode( $alt[2], ENT_QUOTES, 'UTF-8' ) );
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 250 ) : substr( $text, 0, 250 );
	}

	/**
	 * Absolute URL for a relative link.
	 *
	 * @param string $href Link.
	 * @param string $base Page URL.
	 * @return string
	 */
	public static function absolute( $href, $base ) {
		$parts = wp_parse_url( $base );
		if ( empty( $parts['host'] ) ) {
			return $href;
		}
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		if ( 0 === strpos( $href, '//' ) ) {
			return $scheme . ':' . $href;
		}
		$origin = $scheme . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		if ( 0 === strpos( $href, '/' ) ) {
			return $origin . $href;
		}
		$dir = isset( $parts['path'] ) ? preg_replace( '#/[^/]*$#', '/', $parts['path'] ) : '/';
		return $origin . $dir . $href;
	}
}
