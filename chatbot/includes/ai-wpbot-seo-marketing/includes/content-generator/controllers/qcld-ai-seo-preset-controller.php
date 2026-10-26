<?php
/**
 * Preset Controller — CRUD for generation presets.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: modules/content-generator/controllers/class-preset-controller.php
 * Copyright (C) Noor Alam (wpthemespace.com). Licensed GPLv2 or later.
 *
 * Modified by QuantumCloud for WPBot, 2026: Namespace, prefixes and text
 * domain renamed, integrated with WPBot.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qcld_Wpbot_Ai_Seo_Content_Preset_Controller {

	/* ── LIST presets ────────────────────────────────── */

	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p             = $wpdb->prefix;
		$presets_table = $p . 'qcld_wpbot_ai_seo_content_presets';

		$presets = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY is_default DESC, name ASC', $presets_table ) );

		return new \WP_REST_Response( $presets ?: array() );
	}

	/* ── UPDATE preset ───────────────────────────────── */

	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p             = $wpdb->prefix;
		$presets_table = $p . 'qcld_wpbot_ai_seo_content_presets';
		$id            = absint( $request->get_param( 'id' ) );

		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $presets_table, $id ) );
		if ( ! $existing ) {
			return new \WP_REST_Response( array( 'message' => __( 'Preset not found.', 'chatbot' ) ), 404 );
		}

		$data = array( 'updated_at' => current_time( 'mysql', true ) );

		$text_fields = array( 'name', 'tone', 'style', 'language' );
		foreach ( $text_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = sanitize_text_field( $request->get_param( $field ) );
			}
		}

		$textarea_fields = array( 'description', 'prompt_template', 'system_instructions' );
		foreach ( $textarea_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = sanitize_textarea_field( $request->get_param( $field ) );
			}
		}

		if ( $request->has_param( 'word_count' ) ) {
			$data['word_count'] = absint( $request->get_param( 'word_count' ) );
		}

		if ( $request->has_param( 'default_category_id' ) ) {
			$data['default_category_id'] = absint( $request->get_param( 'default_category_id' ) ) ?: null;
		}

		$wpdb->update( $presets_table, $data, array( 'id' => $id ) );

		return new \WP_REST_Response( array( 'message' => __( 'Preset updated.', 'chatbot' ) ) );
	}

	/* ── DELETE preset ───────────────────────────────── */

	public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p             = $wpdb->prefix;
		$presets_table = $p . 'qcld_wpbot_ai_seo_content_presets';
		$id            = absint( $request->get_param( 'id' ) );

		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT is_default FROM %i WHERE id = %d', $presets_table, $id ) );
		if ( ! $existing ) {
			return new \WP_REST_Response( array( 'message' => __( 'Preset not found.', 'chatbot' ) ), 404 );
		}

		// Prevent deletion of default presets.
		if ( $existing->is_default ) {
			return new \WP_REST_Response( array( 'message' => __( 'Cannot delete default presets.', 'chatbot' ) ), 400 );
		}

		$wpdb->delete( $presets_table, array( 'id' => $id ) );

		return new \WP_REST_Response( array( 'message' => __( 'Preset deleted.', 'chatbot' ) ) );
	}
}
