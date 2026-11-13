<?php
/**
 * Competitors in Topic Finder's settings (CR-6 W3).
 *
 * WPBot Core keeps one shared competitor list (filled from Competitor Watch,
 * SEO & AI Search and the owner). Here AI Writer
 * - finds each listed site's news feed ("Use my competitors"): the feed the
 *   home page announces with <link rel="alternate">, else /feed/;
 * - asks WPBot Core for AI suggestions ("Suggest competitors"), only when
 *   the owner clicks, because it uses AI credits;
 * - adds the sites the owner ticks to the shared list (WPBot Core leaves
 *   the permission check to the caller; it is done here).
 * Nothing is added or changed without the owner's click. Without WPBot
 * Core's competitor functions, none of this is offered.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics;

use QuantumCloud\AiWriter\Module;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Competitor feeds.
 */
final class Competitor_Feeds {

	/**
	 * Cache of found feeds per domain (one day).
	 */
	const CACHE = 'qcld_wpbot_writer_feed_';

	/**
	 * Whether the shared competitor list exists.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'qcld_wpbot_core_competitors' );
	}

	/**
	 * Whether AI suggestions exist.
	 *
	 * @return bool
	 */
	public static function can_suggest() {
		return function_exists( 'qcld_wpbot_core_suggest_competitors' ) && function_exists( 'qcld_wpbot_core_save_competitors' );
	}

	/**
	 * The shared list with each site's feed.
	 *
	 * @return array[] domain, reason, source, feed ('' = no feed found).
	 */
	public static function shared_with_feeds() {
		if ( ! self::available() ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( (array) qcld_wpbot_core_competitors(), 0, 20 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['domain'] ) ) {
				continue;
			}
			$out[] = array(
				'domain' => (string) $item['domain'],
				'reason' => isset( $item['reason'] ) ? (string) $item['reason'] : '',
				'source' => isset( $item['source'] ) ? (string) $item['source'] : '',
				'feed'   => self::feed_for( (string) $item['domain'] ),
			);
		}
		return $out;
	}

	/**
	 * AI suggestions (on the owner's click), each with its feed.
	 *
	 * @return array|WP_Error suggestions[domain, reason, source, title, feed], dropped[domain, why], cost.
	 */
	public static function suggest() {
		if ( ! self::can_suggest() ) {
			return new WP_Error( 'qcld_wpbot_writer_no_suggest', __( 'Competitor suggestions need SEO & AI Search.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$known  = self::available() ? wp_list_pluck( (array) qcld_wpbot_core_competitors(), 'domain' ) : array();
		$result = qcld_wpbot_core_suggest_competitors(
			array(
				'module'  => Module::ID,
				'limit'   => 8,
				'exclude' => array_values( array_filter( array_map( 'strval', $known ) ) ),
			)
		);
		if ( is_wp_error( $result ) ) {
			$data = (array) $result->get_error_data();
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => isset( $data['status'] ) ? (int) $data['status'] : 502 ) );
		}
		$suggestions = array();
		foreach ( isset( $result['suggestions'] ) ? (array) $result['suggestions'] : array() as $item ) {
			if ( is_array( $item ) && ! empty( $item['domain'] ) ) {
				$suggestions[] = array(
					'domain' => (string) $item['domain'],
					'reason' => isset( $item['reason'] ) ? (string) $item['reason'] : '',
					'source' => isset( $item['source'] ) ? (string) $item['source'] : 'ai',
					'title'  => isset( $item['title'] ) ? (string) $item['title'] : '',
					'feed'   => self::feed_for( (string) $item['domain'] ),
				);
			}
		}
		return array(
			'suggestions' => $suggestions,
			'dropped'     => isset( $result['dropped'] ) ? array_values( (array) $result['dropped'] ) : array(),
			'cost'        => isset( $result['cost'] ) ? (float) $result['cost'] : 0.0,
		);
	}

	/**
	 * Add sites to the shared competitor list (keeps the ones already there).
	 *
	 * @param array[] $items domain, reason, source.
	 * @return array|WP_Error The shared list.
	 */
	public static function add_to_shared( array $items ) {
		if ( ! self::can_suggest() || ! self::available() ) {
			return new WP_Error( 'qcld_wpbot_writer_no_competitors', __( 'The shared competitor list needs SEO & AI Search.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( Module::cap( 'settings' ) ) ) {
			return new WP_Error( 'qcld_wpbot_writer_forbidden', __( 'You are not allowed to change the competitor list.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$list  = array_values( (array) qcld_wpbot_core_competitors() );
		$known = array_map( 'strtolower', wp_list_pluck( $list, 'domain' ) );
		foreach ( array_slice( $items, 0, 20 ) as $item ) {
			$domain = isset( $item['domain'] ) ? self::domain( (string) $item['domain'] ) : '';
			if ( '' === $domain || in_array( $domain, $known, true ) ) {
				continue;
			}
			$list[]  = array(
				'domain' => $domain,
				'reason' => isset( $item['reason'] ) ? sanitize_text_field( (string) $item['reason'] ) : '',
				'source' => isset( $item['source'] ) && in_array( $item['source'], array( 'ai', 'keyword_provider', 'search_console', 'owner' ), true ) ? $item['source'] : 'ai_writer',
			);
			$known[] = $domain;
		}
		return qcld_wpbot_core_save_competitors( $list );
	}

	/**
	 * Remove one site from the shared list (only when the owner asks).
	 *
	 * @param string $domain Domain.
	 * @return array|WP_Error The shared list.
	 */
	public static function remove_from_shared( $domain ) {
		if ( ! self::can_suggest() || ! self::available() ) {
			return new WP_Error( 'qcld_wpbot_writer_no_competitors', __( 'The shared competitor list needs SEO & AI Search.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( Module::cap( 'settings' ) ) ) {
			return new WP_Error( 'qcld_wpbot_writer_forbidden', __( 'You are not allowed to change the competitor list.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$domain = self::domain( $domain );
		$list   = array_values(
			array_filter(
				(array) qcld_wpbot_core_competitors(),
				static function ( $item ) use ( $domain ) {
					return is_array( $item ) && strtolower( (string) $item['domain'] ) !== $domain;
				}
			)
		);
		return qcld_wpbot_core_save_competitors( $list );
	}

	/**
	 * Sites seen in "Who ranks" results (domain => times seen).
	 */
	const SEEN = 'qcld_wpbot_writer_who_ranks_seen';

	/**
	 * Remember the sites in one "Who ranks" result (never this site).
	 *
	 * @param array[] $results Results with url.
	 * @return void
	 */
	public static function record_seen( array $results ) {
		$seen = get_option( self::SEEN, array() );
		$seen = is_array( $seen ) ? $seen : array();
		$own  = self::domain( home_url( '/' ) );
		$once = array();
		foreach ( $results as $row ) {
			$domain = is_array( $row ) && isset( $row['url'] ) ? self::domain( (string) $row['url'] ) : '';
			if ( '' === $domain || $domain === $own || isset( $once[ $domain ] ) ) {
				continue;
			}
			$once[ $domain ] = true;
			$seen[ $domain ] = isset( $seen[ $domain ] ) ? (int) $seen[ $domain ] + 1 : 1;
		}
		arsort( $seen );
		update_option( self::SEEN, array_slice( $seen, 0, 100, true ), false );
	}

	/**
	 * Sites that keep showing up in "Who ranks" (seen at least twice).
	 *
	 * @param string[] $exclude Domains to leave out (already listed).
	 * @param int      $limit   Max.
	 * @return array[] domain, count.
	 */
	public static function seen( array $exclude = array(), $limit = 8 ) {
		$seen    = get_option( self::SEEN, array() );
		$exclude = array_map( array( __CLASS__, 'domain' ), $exclude );
		$out     = array();
		foreach ( is_array( $seen ) ? $seen : array() as $domain => $count ) {
			if ( (int) $count >= 2 && ! in_array( (string) $domain, $exclude, true ) ) {
				$out[] = array(
					'domain' => (string) $domain,
					'count'  => (int) $count,
				);
			}
		}
		return array_slice( $out, 0, max( 1, (int) $limit ) );
	}

	/**
	 * The news feed of a site ('' when none was found). Cached for a day.
	 *
	 * @param string $domain Domain.
	 * @return string Feed URL.
	 */
	public static function feed_for( $domain ) {
		$domain = self::domain( $domain );
		if ( '' === $domain ) {
			return '';
		}
		$cached = get_transient( self::CACHE . md5( $domain ) );
		if ( is_string( $cached ) ) {
			return $cached;
		}
		$feed = self::discover( 'https://' . $domain . '/' );
		set_transient( self::CACHE . md5( $domain ), $feed, DAY_IN_SECONDS );
		return $feed;
	}

	/**
	 * Find a feed: <link rel="alternate" type="application/rss+xml|atom+xml">
	 * on the home page, else /feed/ when it answers with a feed.
	 *
	 * @param string $home Home page URL.
	 * @return string Feed URL or ''.
	 */
	public static function discover( $home ) {
		$args     = array(
			'timeout'             => 8,
			'redirection'         => 3,
			'limit_response_size' => 512 * KB_IN_BYTES,
			'user-agent'          => Module::agent_token() . ' AI Writer feed finder; ' . home_url( '/' ),
		);
		$response = self::fetch( $home, $args );
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$html = (string) wp_remote_retrieve_body( $response );
			if ( preg_match_all( '/<link\b[^>]*>/i', $html, $tags ) ) {
				foreach ( $tags[0] as $tag ) {
					if ( preg_match( '/\brel=["\']?alternate/i', $tag ) && preg_match( '/\btype=["\']?application\/(rss|atom)\+xml/i', $tag ) && preg_match( '/\bhref=["\']([^"\']+)["\']/i', $tag, $href ) ) {
						$url = self::absolute( html_entity_decode( $href[1], ENT_QUOTES ), $home );
						// Skip comment feeds; the posts feed is the one wanted.
						if ( '' !== $url && false === stripos( $url, 'comments' ) ) {
							return $url;
						}
					}
				}
			}
		}
		$guess    = trailingslashit( $home ) . 'feed/';
		$response = self::fetch( $guess, $args );
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = ltrim( (string) wp_remote_retrieve_body( $response ) );
			if ( preg_match( '/<(rss|feed|rdf:RDF)\b/i', substr( $body, 0, 2000 ) ) ) {
				return $guess;
			}
		}
		return '';
	}

	/**
	 * GET a URL (tests point *.test sites at a local server with the filter).
	 *
	 * @param string $url  URL.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error
	 */
	private static function fetch( $url, array $args ) {
		/**
		 * URL the feed finder actually requests.
		 *
		 * @param string $url URL.
		 */
		$target = (string) apply_filters( 'qcld_wpbot_writer/feed_finder_url', $url );
		return wp_safe_remote_get( esc_url_raw( $target ), $args );
	}

	/**
	 * Absolute http(s) URL from a link on a page ('' when not usable).
	 *
	 * @param string $href Link.
	 * @param string $base Page URL.
	 * @return string
	 */
	private static function absolute( $href, $base ) {
		$href = trim( $href );
		if ( 0 === strpos( $href, '//' ) ) {
			$href = 'https:' . $href;
		} elseif ( 0 === strpos( $href, '/' ) ) {
			$parts = wp_parse_url( $base );
			$href  = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . ( isset( $parts['host'] ) ? $parts['host'] : '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $href;
		} elseif ( ! preg_match( '#^https?://#i', $href ) ) {
			$href = trailingslashit( $base ) . $href;
		}
		return (string) esc_url_raw( $href, array( 'http', 'https' ) );
	}

	/**
	 * A bare domain from a domain or URL ('' when not valid).
	 *
	 * @param string $value Domain or URL.
	 * @return string
	 */
	public static function domain( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$host  = false !== strpos( $value, '://' ) ? (string) wp_parse_url( $value, PHP_URL_HOST ) : preg_replace( '#[/?\#].*$#', '', $value );
		$host  = preg_replace( '/^www\./', '', (string) $host );
		return preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}(:\d+)?$/', $host ) ? $host : '';
	}
}
