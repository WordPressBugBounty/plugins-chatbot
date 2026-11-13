<?php
/**
 * Page context for AI answers (chatbot features CB1a): a cleaned, size-limited text extract of
 * the page the visitor is on, built on the server from the published post, never from what the
 * browser sends (so hidden or private content is never included).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Page context.
 */
final class Page_Context {

	/**
	 * Default size limit of the extract, in characters.
	 */
	const MAX_CHARS = 2000;

	/**
	 * Text describing the page for the AI ('' when the URL is not a public post on this site).
	 *
	 * @param string        $url    Page URL the widget reported (the browser's Referer: anyone can set it).
	 * @param string        $title  Page title the widget reported (used only when there is no post).
	 * @param string[]|null $labels The visitor's access labels (null = public only).
	 * @return string
	 */
	public static function text( $url, $title = '', $labels = null ) {
		$url  = (string) $url;
		$post = self::post_for_url( $url );
		if ( $post && ! self::visible( $post, null === $labels ? array( 'public' ) : (array) $labels ) ) {
			// Members-only page: the visitor may not read it through the AI. Only its address is known.
			return 'The user is on this page: ' . esc_url_raw( $url );
		}
		if ( ! $post ) {
			$line = trim( (string) $title . ' ' . $url );
			return '' !== $line ? 'The user is on this page: ' . $line : '';
		}
		$parts   = array( 'The user is on this page: ' . get_the_title( $post ) . ' (' . get_permalink( $post ) . ')' );
		$excerpt = has_excerpt( $post ) ? self::clean( (string) $post->post_excerpt ) : '';
		if ( '' !== $excerpt ) {
			$parts[] = 'Summary: ' . $excerpt;
		}
		/**
		 * Size limit of the page text sent to the AI, in characters.
		 *
		 * @param int      $max  Characters.
		 * @param \WP_Post $post Post.
		 */
		$max  = max( 200, (int) apply_filters( 'qcld_wpbot_chat/page_context_max', self::MAX_CHARS, $post ) );
		$body = self::cut( self::clean( (string) $post->post_content ), $max );
		if ( '' !== $body ) {
			$parts[] = "Page text:\n" . $body;
		}
		/**
		 * Page context lines sent to the AI (Pro adds metadata such as product price and stock).
		 *
		 * @param string[] $parts Lines.
		 * @param \WP_Post $post  Post.
		 */
		$parts = (array) apply_filters( 'qcld_wpbot_chat/page_context', $parts, $post );
		return implode( "\n", array_filter( array_map( 'strval', $parts ), 'strlen' ) );
	}

	/**
	 * The public, published post a URL on this site points to.
	 *
	 * @param string $url URL.
	 * @return \WP_Post|null
	 */
	public static function post_for_url( $url ) {
		if ( '' === $url || ! function_exists( 'url_to_postid' ) || ! self::same_site( $url ) ) {
			return null;
		}
		$id   = (int) url_to_postid( $url );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return null;
		}
		$type = get_post_type_object( $post->post_type );
		return ( $type && ! empty( $type->public ) ) ? $post : null;
	}

	/**
	 * Whether the visitor may read a post through the AI: the same access labels as the knowledge
	 * base (the item's stored labels and the kb_post_access filter), so the page the browser
	 * claims to be on never reveals what a knowledge search would hide.
	 *
	 * @param \WP_Post $post   Post.
	 * @param string[] $labels Visitor labels.
	 * @return bool
	 */
	public static function visible( $post, array $labels ) {
		$access = '\QuantumCloud\WPBotCore\Knowledge\Kb_Access';
		/** This filter is documented in WPBot Core src/Knowledge/Kb_Sources.php */
		$filtered = (array) apply_filters( 'qcld_wpbot_core/kb_post_access', array(), $post ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPBot Core's filter.
		$stored   = function_exists( 'qcld_wpbot_core_kb_access' ) ? qcld_wpbot_core_kb_access( 'post:' . $post->post_type, (string) $post->ID ) : null;
		if ( ! class_exists( $access ) ) {
			$open = array( 'public' );
			return array() === array_diff( $filtered, $open ) && ( null === $stored || array() === array_diff( (array) $stored, $open ) );
		}
		$ok = $access::allows( '', $access::encode( $filtered ), $labels ) && ( null === $stored || $access::allows( '', $access::encode( (array) $stored ), $labels ) );
		/**
		 * Whether the visitor may read this post through the AI (membership plugins can say no).
		 *
		 * @param bool     $ok     Default from the access labels.
		 * @param \WP_Post $post   Post.
		 * @param string[] $labels Visitor labels.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/page_context_allowed', $ok, $post, $labels );
	}

	/**
	 * Whether a URL is on this site.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function same_site( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) && is_string( $home ) && strtolower( $host ) === strtolower( $home );
	}

	/**
	 * Visible text only: no shortcodes, blocks' markup, scripts, styles or HTML.
	 *
	 * @param string $text Raw post text.
	 * @return string
	 */
	public static function clean( $text ) {
		$text = preg_replace( '#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is', ' ', (string) $text );
		$text = preg_replace( '/<!--.*?-->/s', ' ', (string) $text );
		$text = function_exists( 'strip_shortcodes' ) ? strip_shortcodes( (string) $text ) : $text;
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Cut text at a word boundary.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	public static function cut( $text, $max ) {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $length <= $max ) {
			return $text;
		}
		$cut   = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		$space = strrpos( $cut, ' ' );
		return ( false !== $space && $space > $max * 0.6 ? substr( $cut, 0, $space ) : $cut ) . '…';
	}
}
