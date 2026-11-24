<?php
/**
 * The chatbot and the Help Center (HC1-11, plan section 7).
 *
 * - Chat site search covers help articles (qcld_wpbot_chat/search_post_types).
 * - Article access labels reach WPBot Core's per-post access filter, so the
 *   chatbot's page context never reads a signed-in-only article to a visitor who
 *   may not read it (qcld_wpbot_core/kb_post_access).
 * - A question asked from an article page carries the article as context: the
 *   chatbot's page context when it is on (with room for a whole help article),
 *   otherwise the same text added here, plus a rule to point to the section.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Chat;

use QuantumCloud\HelpCenter\Access;
use QuantumCloud\HelpCenter\Content\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Chatbot hooks.
 */
final class Chat {

	/**
	 * Characters of an article sent as context.
	 */
	const CONTEXT_CHARS = 6000;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/search_post_types', array( self::class, 'search_post_types' ) );
		add_filter( 'qcld_wpbot_core/kb_post_access', array( self::class, 'post_access' ), 10, 2 );
		add_filter( 'qcld_wpbot_chat/page_context_max', array( self::class, 'context_max' ), 10, 2 );
		add_filter( 'qcld_wpbot_chat/ai_context', array( self::class, 'ai_context' ), 10, 2 );
		Gap_Writer::init();
	}

	/**
	 * Chat site search covers help articles.
	 *
	 * @param string[] $types Post types.
	 * @return string[]
	 */
	public static function search_post_types( $types ) {
		$types   = (array) $types;
		$types[] = Post_Type::NAME;
		return array_values( array_unique( $types ) );
	}

	/**
	 * Access labels of an article for WPBot Core and the chatbot's page context.
	 *
	 * @param string[] $labels Labels.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public static function post_access( $labels, $post ) {
		if ( $post instanceof \WP_Post && Post_Type::NAME === $post->post_type ) {
			$own = Access::labels( $post->ID );
			if ( array() !== $own ) {
				return array_values( array_unique( array_merge( (array) $labels, $own ) ) );
			}
		}
		return (array) $labels;
	}

	/**
	 * Room for a whole help article in the page context.
	 *
	 * @param int      $max  Characters.
	 * @param \WP_Post $post Post.
	 * @return int
	 */
	public static function context_max( $max, $post ) {
		return $post instanceof \WP_Post && Post_Type::NAME === $post->post_type ? max( (int) $max, self::CONTEXT_CHARS ) : (int) $max;
	}

	/**
	 * The article the visitor is reading, as context for the AI.
	 *
	 * @param string[] $parts   Context parts.
	 * @param object   $message The chatbot's message (page_url, page_title).
	 * @return string[]
	 */
	public static function ai_context( $parts, $message ) {
		$parts = (array) $parts;
		$url   = is_object( $message ) && isset( $message->page_url ) ? (string) $message->page_url : '';
		$post  = self::article_for_url( $url );
		if ( ! $post ) {
			return $parts;
		}
		$has_page = false;
		foreach ( $parts as $part ) {
			if ( 0 === strpos( (string) $part, 'The user is on this page:' ) ) {
				$has_page = true;
				break;
			}
		}
		if ( ! $has_page && class_exists( '\QuantumCloud\Chatbot\Ai\Page_Context' ) ) {
			$labels  = $message instanceof \QuantumCloud\Chatbot\Conversation\Message && class_exists( '\QuantumCloud\Chatbot\Knowledge\Visitor_Labels' ) ? \QuantumCloud\Chatbot\Knowledge\Visitor_Labels::for_message( $message ) : array( 'public' );
			$parts[] = \QuantumCloud\Chatbot\Ai\Page_Context::text( $url, get_the_title( $post ), $labels );
		}
		$parts[] = self::rule( get_the_title( $post ) );
		return $parts;
	}

	/**
	 * The instruction for questions asked from an article.
	 *
	 * @param string $title Article title.
	 * @return string
	 */
	public static function rule( $title ) {
		return 'The user is reading the help article "' . wp_strip_all_tags( (string) $title ) . '" and still needs help. Answer from that article first when it covers the question and name the section; otherwise answer from the rest of the knowledge base. Do not repeat the whole article.';
	}

	/**
	 * The published article at a URL of this site, or null.
	 *
	 * @param string $url URL.
	 * @return \WP_Post|null
	 */
	private static function article_for_url( $url ) {
		if ( '' === $url || ! function_exists( 'url_to_postid' ) ) {
			return null;
		}
		$home = wp_parse_url( home_url() );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_array( $home ) || ! isset( $home['host'] ) || strtolower( (string) $host ) !== strtolower( (string) $home['host'] ) ) {
			return null;
		}
		$post = get_post( (int) url_to_postid( $url ) );
		return $post && Post_Type::NAME === $post->post_type && 'publish' === $post->post_status ? $post : null;
	}
}
