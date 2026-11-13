<?php
/**
 * Public AI functions shared by every module.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_ai_readiness' ) ) {
	/**
	 * Whether AI is ready for a module, and how to fix it when it is not.
	 *
	 * @param string $module Module id (for example seo or writer).
	 * @return array status (ready, no_key, not_enabled, no_model, failing, budget_reached), ready, cause, message, action, action_label, provider, provider_label, url, warning.
	 */
	function qcld_wpbot_core_ai_readiness( $module ) {
		return \QuantumCloud\WPBotCore\AI\AI_Readiness::check( $module );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_kb_search' ) ) {
	/**
	 * Search the knowledge base.
	 *
	 * @param string $query Question.
	 * @param array  $args  top_k, min_score, types, labels (the visitor's access labels; see Kb_Access).
	 * @return array[] Each: title, url, content, score, source_type, source_id, heading_path, anchor.
	 */
	function qcld_wpbot_core_kb_search( $query, array $args = array() ) {
		return \QuantumCloud\WPBotCore\Knowledge\Kb::search( $query, $args );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_kb_context' ) ) {
	/**
	 * Knowledge base results for a question as one text block for a prompt.
	 *
	 * @param string $query Question.
	 * @param array  $args  See qcld_wpbot_core_kb_search().
	 * @return string
	 */
	function qcld_wpbot_core_kb_context( $query, array $args = array() ) {
		return \QuantumCloud\WPBotCore\Knowledge\Kb_Search::context( \QuantumCloud\WPBotCore\Knowledge\Kb::search( $query, $args ) );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_kb_store' ) ) {
	/**
	 * Add or update one item (modules with their own content: answers, files, sheets).
	 *
	 * @param string $source Source id (registered through qcld_wpbot_core/kb_sources).
	 * @param array  $item   id, title, url, content, language; optional access (labels), html (text with
	 *                       headings, for heading-aware chunks) and chunks (see Kb_Store::upsert()).
	 * @return string "stored", "unchanged" or "empty".
	 */
	function qcld_wpbot_core_kb_store( $source, array $item ) {
		return \QuantumCloud\WPBotCore\Knowledge\Kb_Sources::store( (string) $source, $item );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_kb_access' ) ) {
	/**
	 * An item's stored access labels ([] = public; null when it is not in the knowledge base).
	 *
	 * @param string $source Source id.
	 * @param string $id     Item id.
	 * @return string[]|null
	 */
	function qcld_wpbot_core_kb_access( $source, $id ) {
		return \QuantumCloud\WPBotCore\Knowledge\Kb_Store::access( (string) $source, (string) $id );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_kb_set_access' ) ) {
	/**
	 * Change one item's access labels without re-embedding it (chatbot features CB1c).
	 *
	 * @param string          $source Source id.
	 * @param string          $id     Item id.
	 * @param string|string[] $labels Labels: public, logged_in, role:*, membership:*, course:*, purchased:* ([] = public).
	 * @return bool Whether the item exists.
	 */
	function qcld_wpbot_core_kb_set_access( $source, $id, $labels ) {
		return \QuantumCloud\WPBotCore\Knowledge\Kb_Store::set_access( (string) $source, (string) $id, $labels );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_kb_remove' ) ) {
	/**
	 * Remove one item.
	 *
	 * @param string $source Source id.
	 * @param string $id     Item id.
	 * @return bool
	 */
	function qcld_wpbot_core_kb_remove( $source, $id ) {
		return \QuantumCloud\WPBotCore\Knowledge\Kb_Store::remove( (string) $source, (string) $id );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_kb_items' ) ) {
	/**
	 * What the knowledge base holds, newest first (settings screens; read only).
	 *
	 * @param array $args page, per_page (max 100), search (title or source id), type (source type, or prefix ending in ":").
	 * @return array{items: array[], total: int, page: int, per_page: int} Each item: source_type, source_id, title, url,
	 *               chunks, ready (pieces ready for search with the current embedding model), status, error, updated_at.
	 */
	function qcld_wpbot_core_kb_items( array $args = array() ) {
		return \QuantumCloud\WPBotCore\Knowledge\Kb_Store::items( $args, \QuantumCloud\WPBotCore\Knowledge\Kb_Embedder::model_id() );
	}
}
