<?php
/**
 * Reads the facts a technical scan needs from a page's HTML (no outside
 * libraries; tolerant of broken markup). Pure logic, unit-tested.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Technical;

defined( 'ABSPATH' ) || exit;

/**
 * HTML facts.
 */
final class Page_Parser {

	/**
	 * Facts from a page.
	 *
	 * @param string $html Page HTML.
	 * @param string $url  Page URL.
	 * @param string $home Site home URL.
	 * @return array title, description, canonical, noindex, h1, internal[], external[], images_no_alt, schema[], mixed[], hreflang[], words.
	 */
	public static function parse( $html, $url, $home ) {
		$html  = (string) $html;
		$head  = preg_match( '#<head\b.*?</head>#is', $html, $m ) ? $m[0] : $html;
		$facts = array(
			'title'         => self::decode( preg_match( '#<title\b[^>]*>(.*?)</title>#is', $head, $t ) ? $t[1] : '' ),
			'description'   => self::meta( $head, 'name', 'description' ),
			'canonical'     => self::link_rel( $head, 'canonical' ),
			'noindex'       => (bool) preg_match( '#noindex#i', self::meta( $head, 'name', 'robots' ) ),
			'h1'            => (int) preg_match_all( '#<h1\b#i', $html ),
			'internal'      => array(),
			'external'      => array(),
			'images_no_alt' => 0,
			'schema'        => array(),
			'mixed'         => array(),
			'hreflang'      => array(),
		);
		$host  = self::origin_host( $home );
		preg_match_all( '#<a\b[^>]*\bhref\s*=\s*("|\')([^"\']+)\1#i', $html, $links );
		foreach ( array_unique( $links[2] ) as $href ) {
			$abs = self::absolute( html_entity_decode( $href, ENT_QUOTES ), $url );
			if ( '' === $abs ) {
				continue;
			}
			if ( self::origin_host( $abs ) === $host ) {
				if ( ! preg_match( '#/(wp-admin|wp-login\.php|feed)(/|$)|\.(jpe?g|png|gif|webp|svg|pdf|zip|css|js)(\?|$)#i', $abs ) ) {
					$facts['internal'][] = strtok( $abs, '#' );
				}
			} else {
				$facts['external'][] = $abs;
			}
		}
		$facts['internal'] = array_values( array_unique( $facts['internal'] ) );
		$facts['external'] = array_slice( array_values( array_unique( $facts['external'] ) ), 0, 100 );
		preg_match_all( '#<img\b[^>]*>#i', $html, $images );
		foreach ( $images[0] as $tag ) {
			if ( ! preg_match( '#\balt\s*=\s*("|\')\s*[^"\'\s]#i', $tag ) ) {
				++$facts['images_no_alt'];
			}
		}
		preg_match_all( '#<script\b[^>]*type\s*=\s*("|\')application/ld\+json\1[^>]*>(.*?)</script>#is', $html, $scripts );
		foreach ( $scripts[2] as $json ) {
			$data = json_decode( trim( $json ), true );
			foreach ( self::schema_nodes( is_array( $data ) ? $data : array() ) as $node ) {
				$facts['schema'][] = $node;
			}
		}
		if ( 0 === strpos( $url, 'https://' ) ) {
			preg_match_all( '#<(?:img|script|iframe|source|audio|video)\b[^>]*\bsrc\s*=\s*("|\')(http://[^"\']+)\1|<link\b[^>]*rel\s*=\s*("|\')stylesheet\3[^>]*href\s*=\s*("|\')(http://[^"\']+)\4#i', $html, $mixed );
			$facts['mixed'] = array_slice( array_values( array_unique( array_filter( array_merge( $mixed[2], $mixed[5] ) ) ) ), 0, 20 );
		}
		preg_match_all( '#<link\b[^>]*rel\s*=\s*("|\')alternate\1[^>]*>#i', $head, $alternates );
		foreach ( $alternates[0] as $tag ) {
			if ( preg_match( '#hreflang\s*=\s*("|\')([^"\']+)\1#i', $tag, $lang ) && preg_match( '#href\s*=\s*("|\')([^"\']+)\1#i', $tag, $href ) ) {
				$facts['hreflang'][] = array(
					'lang' => strtolower( $lang[2] ),
					'href' => $href[2],
				);
			}
		}
		return $facts;
	}

	/**
	 * Schema nodes (type and present keys) from JSON-LD, following @graph.
	 *
	 * @param array $data Decoded JSON-LD.
	 * @return array List of type, keys[], values (a few scalar fields).
	 */
	public static function schema_nodes( array $data ) {
		$nodes = array();
		$queue = isset( $data['@graph'] ) ? (array) $data['@graph'] : ( array_keys( $data ) === range( 0, count( $data ) - 1 ) ? $data : array( $data ) );
		foreach ( $queue as $node ) {
			if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
				continue;
			}
			foreach ( (array) $node['@type'] as $type ) {
				$nodes[] = array(
					'type' => (string) $type,
					'keys' => array_values( array_filter( array_keys( $node ), 'is_string' ) ),
				);
			}
		}
		return $nodes;
	}

	/**
	 * Absolute http(s) URL, or ''.
	 *
	 * @param string $href Link.
	 * @param string $base Page URL.
	 * @return string
	 */
	public static function absolute( $href, $base ) {
		$href = trim( $href );
		if ( '' === $href || '#' === $href[0] || preg_match( '#^(mailto|tel|javascript|data|sms):#i', $href ) ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $href ) ) {
			return $href;
		}
		$scheme = (string) wp_parse_url( $base, PHP_URL_SCHEME );
		$host   = (string) wp_parse_url( $base, PHP_URL_HOST );
		$port   = wp_parse_url( $base, PHP_URL_PORT );
		$origin = $scheme . '://' . $host . ( $port ? ':' . $port : '' );
		if ( 0 === strpos( $href, '//' ) ) {
			return $scheme . ':' . $href;
		}
		if ( '/' === $href[0] ) {
			return $origin . $href;
		}
		if ( '?' === $href[0] ) {
			return strtok( $base, '?' ) . $href;
		}
		$path = (string) wp_parse_url( $base, PHP_URL_PATH );
		$dir  = substr( $path, 0, (int) strrpos( $path, '/' ) + 1 );
		return $origin . ( '' !== $dir ? $dir : '/' ) . $href;
	}

	/**
	 * Host with port (if any), lower-case, without "www.".
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function origin_host( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$port = wp_parse_url( $url, PHP_URL_PORT );
		return preg_replace( '/^www\./', '', $host ) . ( $port ? ':' . $port : '' );
	}

	/**
	 * Content of a meta tag.
	 *
	 * @param string $head  Head HTML.
	 * @param string $attr  name|property.
	 * @param string $value Attribute value.
	 * @return string
	 */
	private static function meta( $head, $attr, $value ) {
		preg_match_all( '#<meta\b[^>]*>#i', $head, $tags );
		foreach ( $tags[0] as $tag ) {
			if ( preg_match( '#\b' . $attr . '\s*=\s*("|\')' . preg_quote( $value, '#' ) . '\1#i', $tag ) && preg_match( '#\bcontent\s*=\s*("|\')(.*?)\1#is', $tag, $content ) ) {
				return self::decode( $content[2] );
			}
		}
		return '';
	}

	/**
	 * Href of a link rel.
	 *
	 * @param string $head Head HTML.
	 * @param string $rel  Rel.
	 * @return string
	 */
	private static function link_rel( $head, $rel ) {
		preg_match_all( '#<link\b[^>]*>#i', $head, $tags );
		foreach ( $tags[0] as $tag ) {
			if ( preg_match( '#\brel\s*=\s*("|\')' . preg_quote( $rel, '#' ) . '\1#i', $tag ) && preg_match( '#\bhref\s*=\s*("|\')(.*?)\1#i', $tag, $href ) ) {
				return html_entity_decode( $href[2], ENT_QUOTES );
			}
		}
		return '';
	}

	/**
	 * Decoded, trimmed text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function decode( $text ) {
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) );
	}
}
