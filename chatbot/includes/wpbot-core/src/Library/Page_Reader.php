<?php
/**
 * Reads one web page the owner named, politely: robots.txt is respected,
 * private and internal addresses are refused (wp_safe_remote_get), a
 * timeout and a size cap apply, and only the readable text comes back.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Library;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Page reader.
 */
final class Page_Reader {

	/**
	 * Most characters of text returned.
	 */
	const MAX_CHARS = 40000;

	/**
	 * Largest page read (2 MB).
	 */
	const MAX_BYTES = 2097152;

	/**
	 * Read a page.
	 *
	 * @param string $url Address.
	 * @return array|WP_Error url, title, text, truncated (bool).
	 */
	public static function read( $url ) {
		$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'qcld_wpbot_core_page_url', __( 'This is not a web address that can be read.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! self::allowed( $url ) ) {
			return new WP_Error( 'qcld_wpbot_core_page_robots', __( 'The site asks robots not to read this page (robots.txt), so it was skipped.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$response = wp_safe_remote_get( $url, self::args() );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'qcld_wpbot_core_page_fetch', __( 'The page could not be reached. Check the address and try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP status code. */
			return new WP_Error( 'qcld_wpbot_core_page_status', sprintf( __( 'The page answered with status %d (it may need a login or not exist).', 'chatbot' ), $code ), array( 'status' => 502 ) );
		}
		$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( '' !== $type && false === strpos( $type, 'html' ) && false === strpos( $type, 'text/plain' ) ) {
			return new WP_Error( 'qcld_wpbot_core_page_type', __( 'This address is not a web page (for a document, upload the file instead).', 'chatbot' ), array( 'status' => 415 ) );
		}
		$body  = (string) wp_remote_retrieve_body( $response );
		$title = preg_match( '/<title[^>]*>(.*?)<\/title>/is', $body, $m ) ? self::clean_line( $m[1] ) : '';
		$text  = false !== strpos( $type, 'text/plain' ) ? self::clean_text( $body ) : self::html_to_text( $body );
		return array(
			'url'       => $url,
			'title'     => $title,
			'text'      => self::cap( $text ),
			'truncated' => strlen( $text ) > self::MAX_CHARS,
		);
	}

	/**
	 * Readable text of HTML: scripts, styles, navigation and forms removed,
	 * one line per block, entities decoded.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html_to_text( $html ) {
		$html = (string) $html;
		$html = preg_replace( '/<(head|title|script|style|noscript|svg|iframe|template|form|nav|footer|header)\b[^>]*>.*?<\/\1>/is', ' ', $html );
		$html = preg_replace( '/<!--.*?-->/s', ' ', (string) $html );
		$html = preg_replace( '/<(br|hr)\b[^>]*>/i', "\n", (string) $html );
		$html = preg_replace( '/<\/(p|div|li|h[1-6]|blockquote|tr|section|article|figcaption|dd|dt)>/i', "\n", (string) $html );
		return self::clean_text( wp_strip_all_tags( (string) $html ) );
	}

	/**
	 * Tidy text: entities decoded, spaces collapsed per line, empty lines dropped.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function clean_text( $text ) {
		$text  = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$lines = array();
		foreach ( preg_split( '/\R/u', $text ) as $line ) {
			$line = self::clean_line( $line );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * One line: entities decoded, tags removed, spaces collapsed.
	 *
	 * @param string $line Line.
	 * @return string
	 */
	private static function clean_line( $line ) {
		$line = html_entity_decode( wp_strip_all_tags( (string) $line ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $line ) );
	}

	/**
	 * Cut text to MAX_CHARS at a line break.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function cap( $text ) {
		if ( strlen( $text ) <= self::MAX_CHARS ) {
			return $text;
		}
		$cut = substr( $text, 0, self::MAX_CHARS );
		$end = strrpos( $cut, "\n" );
		$cut = false !== $end && $end > self::MAX_CHARS / 2 ? substr( $cut, 0, $end ) : $cut;
		return function_exists( 'mb_scrub' ) ? mb_scrub( $cut, 'UTF-8' ) : $cut;
	}

	/**
	 * Request arguments.
	 *
	 * @return array
	 */
	private static function args() {
		/**
		 * Arguments for reading a page the owner named.
		 *
		 * @param array $args wp_safe_remote_get() arguments.
		 */
		return (array) apply_filters(
			'qcld_wpbot_core/page_reader_args',
			array(
				'timeout'             => 15,
				'redirection'         => 3,
				'limit_response_size' => self::MAX_BYTES,
				'user-agent'          => self::user_agent(),
				'headers'             => array( 'Accept' => 'text/html,text/plain;q=0.9' ),
			)
		);
	}

	/**
	 * User-Agent naming WPBot and the site.
	 *
	 * @return string
	 */
	private static function user_agent() {
		return 'WPBot-PageReader/1.0 (+' . home_url( '/' ) . ')';
	}

	/**
	 * Whether robots.txt lets WPBot (or any robot) read a URL. Rules for
	 * "WPBot" win over "*"; the longest matching rule wins; Allow wins a tie.
	 * When robots.txt can't be read, reading is allowed.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function allowed( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false;
		}
		$path  = ( isset( $parts['path'] ) ? $parts['path'] : '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		$rules = self::rules( ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) );
		$best  = -1;
		$allow = true;
		foreach ( $rules as $rule ) {
			if ( '' === $rule['path'] ) {
				continue;
			}
			$pattern = '#^' . str_replace( array( '\*', '\$' ), array( '.*', '$' ), preg_quote( $rule['path'], '#' ) ) . '#';
			if ( preg_match( $pattern, $path ) ) {
				$length = strlen( $rule['path'] );
				if ( $length > $best || ( $length === $best && $rule['allow'] ) ) {
					$best  = $length;
					$allow = $rule['allow'];
				}
			}
		}
		return $allow;
	}

	/**
	 * Robots rules that apply to WPBot on a host (cached a day).
	 *
	 * @param string $origin scheme://host[:port].
	 * @return array[] path, allow.
	 */
	private static function rules( $origin ) {
		$key    = 'qcld_wpbot_core_robots_' . md5( $origin );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$response = wp_safe_remote_get(
			$origin . '/robots.txt',
			array(
				'timeout'             => 8,
				'redirection'         => 2,
				'limit_response_size' => 262144,
				'user-agent'          => self::user_agent(),
			)
		);
		$text     = ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ? (string) wp_remote_retrieve_body( $response ) : '';
		$groups   = array();
		$agents   = array();
		$in_rules = false;
		foreach ( preg_split( '/\R/', $text ) as $line ) {
			$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
			if ( ! preg_match( '/^([A-Za-z-]+)\s*:\s*(.*)$/', $line, $m ) ) {
				continue;
			}
			$field = strtolower( $m[1] );
			$value = trim( $m[2] );
			if ( 'user-agent' === $field ) {
				if ( $in_rules ) {
					$agents   = array();
					$in_rules = false;
				}
				$agents[] = strtolower( $value );
				continue;
			}
			if ( 'allow' === $field || 'disallow' === $field ) {
				$in_rules = true;
				foreach ( $agents as $agent ) {
					$groups[ $agent ][] = array(
						'path'  => $value,
						'allow' => 'allow' === $field,
					);
				}
			}
		}
		$rules = array();
		foreach ( $groups as $agent => $list ) {
			if ( false !== strpos( $agent, 'wpbot' ) ) {
				$rules = array_merge( $rules, $list );
			}
		}
		if ( array() === $rules && isset( $groups['*'] ) ) {
			$rules = $groups['*'];
		}
		set_transient( $key, $rules, DAY_IN_SECONDS );
		return $rules;
	}
}
