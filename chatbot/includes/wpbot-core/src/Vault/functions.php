<?php
/**
 * Public Keyword Vault API (S14). Shared with AI Writer (CR-4).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_vault_query' ) ) {
	/**
	 * Vault keywords.
	 *
	 * @param array $args status (string|string[]: researched, targeted, ranking, archived), source, intent, topic_id, has_page (bool), search, language, country, orderby, order, limit (default 50, max 500), offset.
	 * @return array[] Rows: id, keyword, language, country, status, intent, topic_id, post_id, demand, difficulty_label, volume, volume_source, cpc, difficulty, difficulty_source, rank, rank_source, rank_at, notes, source, context (supporting, questions, competitor_notes, reason), module, created_at, updated_at.
	 */
	function qcld_wpbot_core_vault_query( array $args = array() ) {
		return \QuantumCloud\WPBotCore\Vault\Vault::query( $args );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_vault_get' ) ) {
	/**
	 * One Vault keyword.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	function qcld_wpbot_core_vault_get( $id ) {
		return \QuantumCloud\WPBotCore\Vault\Vault::get( $id );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_vault_add' ) ) {
	/**
	 * Add a keyword (or update the entry for the same keyword, language and country).
	 *
	 * @param array $fields Fields; keyword required.
	 * @return int|WP_Error Id.
	 */
	function qcld_wpbot_core_vault_add( array $fields ) {
		return \QuantumCloud\WPBotCore\Vault\Vault::add( $fields );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_vault_update' ) ) {
	/**
	 * Change a Vault keyword.
	 *
	 * @param int   $id     Id.
	 * @param array $fields Fields.
	 * @return bool|WP_Error
	 */
	function qcld_wpbot_core_vault_update( $id, array $fields ) {
		return \QuantumCloud\WPBotCore\Vault\Vault::update( $id, $fields );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_vault_delete' ) ) {
	/**
	 * Delete a Vault keyword.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	function qcld_wpbot_core_vault_delete( $id ) {
		return \QuantumCloud\WPBotCore\Vault\Vault::delete( $id );
	}
}
