<?php
/**
 * Read-only search of the chatbot's knowledge base, through the host.
 *
 * The host runs the chatbot's own retrieval (same embedding model as the
 * stored chunks); AI Writer never reads or writes the knowledge tables.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Knowledge;

use QuantumCloud\WPBotCore\Core;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Chatbot knowledge access.
 */
final class Chatbot_Knowledge {

	/**
	 * Whether the host offers knowledge search.
	 *
	 * @return bool
	 */
	public static function available() {
		$host = Core::instance()->host();
		return $host && is_callable( $host->get_knowledge_search() );
	}

	/**
	 * Search the knowledge base.
	 *
	 * @param string $query Query.
	 * @param int    $limit Max chunks.
	 * @return string|WP_Error Context text ('' when nothing matched).
	 */
	public static function search( $query, $limit = 5 ) {
		$host   = Core::instance()->host();
		$search = $host ? $host->get_knowledge_search() : null;
		if ( ! is_callable( $search ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_knowledge', __( 'ChatBot knowledge is not available on this site.', 'chatbot' ) );
		}
		try {
			$result = call_user_func( $search, (string) $query, (int) $limit );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'qcld_wpbot_writer_knowledge_failed', __( 'ChatBot knowledge search failed. The draft continues without it.', 'chatbot' ) );
		}
		return is_wp_error( $result ) ? $result : (string) $result;
	}

	/**
	 * Whether the host can add pages to its chatbot knowledge.
	 *
	 * @return bool
	 */
	public static function can_add() {
		$host = Core::instance()->host();
		return $host && is_callable( $host->get_knowledge_writer() );
	}

	/**
	 * Add a published post to the chatbot's knowledge (owner action).
	 *
	 * @param int $post_id Post id.
	 * @return true|WP_Error
	 */
	public static function add_post( $post_id ) {
		$host   = Core::instance()->host();
		$writer = $host ? $host->get_knowledge_writer() : null;
		if ( ! is_callable( $writer ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_knowledge', __( 'ChatBot knowledge is not available on this site.', 'chatbot' ) );
		}
		return call_user_func( $writer, (int) $post_id );
	}
}
