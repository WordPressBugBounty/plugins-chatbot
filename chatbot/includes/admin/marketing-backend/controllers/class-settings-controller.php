<?php
/**
 * Settings Controller — tags, lists, custom fields, labels, general settings.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: modules/email-marketing/controllers/class-settings-controller.php
 * Copyright (C) Noor Alam (wpthemespace.com). Licensed GPLv2 or later.
 *
 * Modified by QuantumCloud for WPBot, 2026: Namespace, prefixes and text
 * domain renamed, integrated with WPBot, WPBot Free/Pro feature checks
 * added.
 *
 * @package WPBot\EmailMarketing\Controllers
 */

namespace WPBot\EmailMarketing\Controllers;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsController {

	/* ====================================================================
	 *  TAGS
	 * =================================================================== */

	public function list_tags( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p = $wpdb->prefix;

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', "{$p}qcwpbot_tags" ) );
		if ( ! $table_exists ) {
			return new \WP_REST_Response( array() );
		}

		$tags = $wpdb->get_results( "SELECT t.*, COUNT(sp.id) AS subscribers_count FROM {$p}qcwpbot_tags t LEFT JOIN {$p}qcwpbot_subscriber_pivot sp ON sp.object_id = t.id AND sp.object_type = 'tag' GROUP BY t.id ORDER BY t.title ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return new \WP_REST_Response( $tags ?: array() );
	}

	public function create_tag( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$title = sanitize_text_field( $request->get_param( 'title' ) );
		$slug  = sanitize_title( $title );
		$color = sanitize_text_field( $request->get_param( 'color' ) ?? '' );

		// Accept hex colors in various formats.
		if ( $color && ! preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
			$color = null;
		}

		$result = $wpdb->insert( "{$wpdb->prefix}qcwpbot_tags", array(
			'title'       => $title,
			'slug'        => $slug,
			'description' => sanitize_textarea_field( $request->get_param( 'description' ) ?? '' ),
			'color'       => $color ?: null,
			'created_at'  => current_time( 'mysql', true ),
		) );

		if ( false === $result ) {
			return new \WP_REST_Response( array( 'message' => __( 'Failed to create tag.', 'chatbot' ) ), 500 );
		}

		return new \WP_REST_Response( array( 'id' => (int) $wpdb->insert_id, 'message' => __( 'Tag created.', 'chatbot' ) ), 201 );
	}

	public function update_tag( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id = absint( $request->get_param( 'id' ) );
		$data = array( 'updated_at' => current_time( 'mysql', true ) );

		if ( $request->has_param( 'title' ) ) {
			$data['title'] = sanitize_text_field( $request->get_param( 'title' ) );
			$data['slug']  = sanitize_title( $data['title'] );
		}
		if ( $request->has_param( 'description' ) ) {
			$data['description'] = sanitize_textarea_field( $request->get_param( 'description' ) );
		}
		if ( $request->has_param( 'color' ) ) {
			$color = sanitize_text_field( $request->get_param( 'color' ) );
			if ( $color && ! preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
				$color = null;
			}
			$data['color'] = $color ?: null;
		}

		$result = $wpdb->update( "{$wpdb->prefix}qcwpbot_tags", $data, array( 'id' => $id ) );

		if ( false === $result ) {
			return new \WP_REST_Response( array( 'message' => __( 'Failed to update tag.', 'chatbot' ) ), 500 );
		}

		return new \WP_REST_Response( array( 'message' => __( 'Tag updated.', 'chatbot' ) ) );
	}

	public function delete_tag( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p  = $wpdb->prefix;
		$id = absint( $request->get_param( 'id' ) );
		$wpdb->delete( "{$p}qcwpbot_tags", array( 'id' => $id ) );
		$wpdb->delete( "{$p}qcwpbot_subscriber_pivot", array( 'object_id' => $id, 'object_type' => 'tag' ) );
		return new \WP_REST_Response( array( 'message' => __( 'Tag deleted.', 'chatbot' ) ) );
	}

	/* ====================================================================
	 *  LISTS
	 * =================================================================== */

	public function list_lists( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p = $wpdb->prefix;

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', "{$p}qcwpbot_lists" ) );
		if ( ! $table_exists ) {
			return new \WP_REST_Response( array() );
		}

		$lists = $wpdb->get_results( "SELECT l.*, COUNT(sp.id) AS subscribers_count FROM {$p}qcwpbot_lists l LEFT JOIN {$p}qcwpbot_subscriber_pivot sp ON sp.object_id = l.id AND sp.object_type = 'list' GROUP BY l.id ORDER BY l.title ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return new \WP_REST_Response( $lists ?: array() );
	}

	public function create_list( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$title = sanitize_text_field( $request->get_param( 'title' ) );
		$slug  = sanitize_title( $title );

		$wpdb->insert( "{$wpdb->prefix}qcwpbot_lists", array(
			'title'       => $title,
			'slug'        => $slug,
			'description' => sanitize_textarea_field( $request->get_param( 'description' ) ?? '' ),
			'is_public'   => $request->get_param( 'is_public' ) ? 1 : 0,
			'created_at'  => current_time( 'mysql', true ),
		) );

		return new \WP_REST_Response( array( 'id' => (int) $wpdb->insert_id, 'message' => __( 'List created.', 'chatbot' ) ), 201 );
	}

	public function update_list( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id = absint( $request->get_param( 'id' ) );
		$data = array( 'updated_at' => current_time( 'mysql', true ) );

		if ( $request->has_param( 'title' ) ) {
			$data['title'] = sanitize_text_field( $request->get_param( 'title' ) );
			$data['slug']  = sanitize_title( $data['title'] );
		}
		if ( $request->has_param( 'description' ) ) {
			$data['description'] = sanitize_textarea_field( $request->get_param( 'description' ) );
		}
		if ( $request->has_param( 'is_public' ) ) {
			$data['is_public'] = $request->get_param( 'is_public' ) ? 1 : 0;
		}

		$wpdb->update( "{$wpdb->prefix}qcwpbot_lists", $data, array( 'id' => $id ) );
		return new \WP_REST_Response( array( 'message' => __( 'List updated.', 'chatbot' ) ) );
	}

	public function delete_list( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p  = $wpdb->prefix;
		$id = absint( $request->get_param( 'id' ) );
		$wpdb->delete( "{$p}qcwpbot_lists", array( 'id' => $id ) );
		$wpdb->delete( "{$p}qcwpbot_subscriber_pivot", array( 'object_id' => $id, 'object_type' => 'list' ) );
		return new \WP_REST_Response( array( 'message' => __( 'List deleted.', 'chatbot' ) ) );
	}

	/* ====================================================================
	 *  CUSTOM FIELDS
	 * =================================================================== */

	public function list_custom_fields( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$object_type = sanitize_text_field( $request->get_param( 'object_type' ) ?? 'contact' );
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}qcwpbot_custom_fields WHERE object_type = %s ORDER BY position ASC", $object_type )
		);
		return new \WP_REST_Response( $rows );
	}

	public function save_custom_field( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p = $wpdb->prefix;
		$id = absint( $request->get_param( 'id' ) );
		$field_key = sanitize_key( $request->get_param( 'field_key' ) ?? '' );
		$label     = sanitize_text_field( $request->get_param( 'label' ) ?? '' );
		$type      = sanitize_key( $request->get_param( 'field_type' ) ?? 'text' );
		$options   = $request->get_param( 'options' ) ?? array();

		if ( '' === $field_key || '' === $label ) {
			return new \WP_REST_Response( array( 'message' => __( 'Field label and slug are required.', 'chatbot' ) ), 400 );
		}

		if ( ! in_array( $type, array( 'text', 'number', 'date', 'select', 'radio', 'checkbox', 'textarea' ), true ) ) {
			$type = 'text';
		}

		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options = array_values( array_filter( array_map( 'sanitize_text_field', $options ), 'strlen' ) );

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$p}qcwpbot_custom_fields WHERE object_type = %s AND field_key = %s AND id <> %d LIMIT 1",
				sanitize_text_field( $request->get_param( 'object_type' ) ?? 'contact' ),
				$field_key,
				$id
			)
		);

		if ( $existing_id ) {
			return new \WP_REST_Response( array( 'message' => __( 'A custom field with this slug already exists.', 'chatbot' ) ), 400 );
		}

		$data = array(
			'object_type' => sanitize_text_field( $request->get_param( 'object_type' ) ?? 'contact' ),
			'field_key'   => $field_key,
			'label'       => $label,
			'field_type'  => $type,
			'options'     => wp_json_encode( $options ),
			'is_required' => $request->get_param( 'is_required' ) ? 1 : 0,
			'position'    => absint( $request->get_param( 'position' ) ?? 0 ),
		);

		if ( $id ) {
			$data['updated_at'] = current_time( 'mysql', true );
			$wpdb->update( "{$p}qcwpbot_custom_fields", $data, array( 'id' => $id ) );
			return new \WP_REST_Response( array( 'id' => $id, 'message' => __( 'Custom field updated.', 'chatbot' ) ) );
		}

		$data['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( "{$p}qcwpbot_custom_fields", $data );
		return new \WP_REST_Response( array( 'id' => (int) $wpdb->insert_id, 'message' => __( 'Custom field created.', 'chatbot' ) ), 201 );
	}

	public function delete_custom_field( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id  = absint( $request->get_param( 'id' ) );
		$key = $wpdb->get_var( $wpdb->prepare( "SELECT field_key FROM {$wpdb->prefix}qcwpbot_custom_fields WHERE id = %d", $id ) );

		$wpdb->delete( "{$wpdb->prefix}qcwpbot_custom_fields", array( 'id' => $id ) );

		// Clean up subscriber_meta for this key.
		if ( $key ) {
			$wpdb->delete( "{$wpdb->prefix}qcwpbot_subscriber_meta", array( 'meta_key' => $key ) );
		}

		return new \WP_REST_Response( array( 'message' => __( 'Custom field deleted.', 'chatbot' ) ) );
	}

	/* ====================================================================
	 *  GENERAL SETTINGS
	 * =================================================================== */

	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$global_settings = get_option( 'qcwpbot_settings', array() );
		$double_optin    = get_option( 'qcwpbot_double_optin', $global_settings['double_optin'] ?? false );

		$site_name    = get_bloginfo( 'name' );
		$admin_email  = get_option( 'admin_email' );
		$default_footer = '<p>&copy; ' . gmdate( 'Y' ) . ' ' . esc_html( $site_name ) . '. ' . __( 'All rights reserved.', 'chatbot' ) . '</p>';

		$from_name        = get_option( 'qcwpbot_from_name', '' );
		$from_email       = get_option( 'qcwpbot_from_email', '' );
		$reply_to         = get_option( 'qcwpbot_reply_to', '' );
		$company_name     = get_option( 'qcwpbot_company_name', '' );
		$company_address  = get_option( 'qcwpbot_company_address', '' );
		$email_footer     = get_option( 'qcwpbot_email_footer', '' );
		$unsubscribe_text = get_option( 'qcwpbot_unsubscribe_text', '' );

		return new \WP_REST_Response( array(
			'from_name'               => ! empty( $from_name ) ? $from_name : $site_name,
			'from_email'              => ! empty( $from_email ) ? $from_email : $admin_email,
			'reply_to'                => ! empty( $reply_to ) ? $reply_to : ( ! empty( $from_email ) ? $from_email : $admin_email ),
			'unsubscribe_text'        => ! empty( $unsubscribe_text ) ? $unsubscribe_text : __( 'Unsubscribe', 'chatbot' ),
			'company_name'            => ! empty( $company_name ) ? $company_name : $site_name,
			'company_address'         => $company_address,
			'email_footer'            => ! empty( $email_footer ) ? $email_footer : $default_footer,
			'unsubscribe_heading'     => get_option( 'qcwpbot_unsubscribe_heading', '' ),
			'unsubscribe_message'     => get_option( 'qcwpbot_unsubscribe_message', '' ),
			'resubscribe_button_text' => get_option( 'qcwpbot_resubscribe_button_text', '' ),
			'double_optin'            => (bool) $double_optin,
			'double_opt_in'           => (bool) $double_optin,
			'emails_per_second'       => (int) get_option( 'qcwpbot_emails_per_second', 10 ),
			'batch_size'              => (int) get_option( 'qcwpbot_batch_size', 50 ),
			'batch_interval'          => (int) get_option( 'qcwpbot_batch_interval', 60 ),
			'track_opens'             => (bool) get_option( 'qcwpbot_track_opens', true ),
			'track_clicks'            => (bool) get_option( 'qcwpbot_track_clicks', true ),
			'gdpr_mode'               => (bool) get_option( 'qcwpbot_gdpr_mode', false ),
			'ai_provider'             => get_option( 'qcwpbot_ai_provider', 'openai' ),
			'ai_model'                => get_option( 'qcwpbot_ai_model', '' ),
			'openai_api_key'          => get_option( 'qcwpbot_openai_api_key', get_option( 'open_ai_api_key', get_option( 'qcld_openai_api_key', '' ) ) ),
			'gemini_api_key'          => get_option( 'qcwpbot_gemini_api_key', get_option( 'qcld_gemini_api_key', '' ) ),
			'claude_api_key'          => get_option( 'qcwpbot_claude_api_key', get_option( 'qcld_claude_api_key', '' ) ),
			'openrouter_api_key'      => get_option( 'qcwpbot_openrouter_api_key', get_option( 'qcld_openrouter_api_key', '' ) ),
			'grok_api_key'            => get_option( 'qcwpbot_grok_api_key', get_option( 'qcld_grok_api_key', '' ) ),
		) );
	}

	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		if ( $request->has_param( 'double_opt_in' ) && ! $request->has_param( 'double_optin' ) ) {
			$request->set_param( 'double_optin', $request->get_param( 'double_opt_in' ) );
		}

		$fields = array(
			'from_name'               => 'sanitize_text_field',
			'from_email'              => 'sanitize_email',
			'reply_to'                => 'sanitize_email',
			'unsubscribe_text'        => 'sanitize_text_field',
			'company_name'            => 'sanitize_text_field',
			'company_address'         => 'sanitize_textarea_field',
			'email_footer'            => 'wp_kses_post',
			'unsubscribe_heading'     => 'sanitize_text_field',
			'unsubscribe_message'     => 'sanitize_textarea_field',
			'resubscribe_button_text' => 'sanitize_text_field',
			'double_optin'            => 'boolval',
			'emails_per_second'       => 'absint',
			'batch_size'              => 'absint',
			'batch_interval'          => 'absint',
			'track_opens'             => 'boolval',
			'track_clicks'            => 'boolval',
			'gdpr_mode'               => 'boolval',
			'ai_provider'             => 'sanitize_text_field',
			'ai_model'                => 'sanitize_text_field',
			'openai_api_key'          => 'sanitize_text_field',
			'gemini_api_key'          => 'sanitize_text_field',
			'claude_api_key'          => 'sanitize_text_field',
			'openrouter_api_key'      => 'sanitize_text_field',
			'grok_api_key'            => 'sanitize_text_field',
		);

		// Free tier: enforce at least one footer line (custom footer, company info, or unsubscribe text).
		if ( ! qcwpbot_has_pro() ) {
			$footer           = wp_kses_post( $request->get_param( 'email_footer' ) ?? '' );
			$company_name     = sanitize_text_field( $request->get_param( 'company_name' ) ?? '' );
			$company_address  = sanitize_textarea_field( $request->get_param( 'company_address' ) ?? '' );
			$unsubscribe_text = sanitize_text_field( $request->get_param( 'unsubscribe_text' ) ?? '' );

			$has_footer_content = ! empty( $footer ) || ! empty( $company_name ) || ! empty( $company_address ) || ! empty( $unsubscribe_text );

			if ( ! $has_footer_content ) {
				return new \WP_REST_Response( array( 'message' => __( 'Free version requires at least one footer line: custom footer text, company name/address, or unsubscribe text.', 'chatbot' ) ), 400 );
			}
		}

		foreach ( $fields as $key => $sanitizer ) {
			if ( $request->has_param( $key ) ) {
				$value = call_user_func( $sanitizer, $request->get_param( $key ) );

				if ( 'emails_per_second' === $key ) {
					$value = max( 1, min( 100, (int) $value ) );
				}
				if ( 'from_email' === $key && empty( $value ) ) {
					$value = get_option( 'admin_email' );
				}
				if ( 'from_name' === $key && empty( $value ) ) {
					$value = get_bloginfo( 'name' );
				}

				update_option( 'qcwpbot_' . $key, $value, false );

				if ( 'double_optin' === $key ) {
					$global_settings                 = get_option( 'qcwpbot_settings', array() );
					$global_settings['double_optin'] = (bool) $value;
					update_option( 'qcwpbot_settings', $global_settings, false );
				}

				if ( 'openai_api_key' === $key && ! empty( $value ) ) {
					$lf_settings = get_option( 'qcwpbot_leadfinder_settings', array() );
					$lf_settings['openai_key'] = $value;
					update_option( 'qcwpbot_leadfinder_settings', $lf_settings, false );
					if ( empty( get_option( 'open_ai_api_key' ) ) ) {
						update_option( 'open_ai_api_key', $value, false );
					}
				}
				if ( 'gemini_api_key' === $key && ! empty( $value ) && empty( get_option( 'qcld_gemini_api_key' ) ) ) {
					update_option( 'qcld_gemini_api_key', $value, false );
				}
				if ( 'claude_api_key' === $key && ! empty( $value ) && empty( get_option( 'qcld_claude_api_key' ) ) ) {
					update_option( 'qcld_claude_api_key', $value, false );
				}
				if ( 'openrouter_api_key' === $key && ! empty( $value ) && empty( get_option( 'qcld_openrouter_api_key' ) ) ) {
					update_option( 'qcld_openrouter_api_key', $value, false );
				}
				if ( 'grok_api_key' === $key && ! empty( $value ) && empty( get_option( 'qcld_grok_api_key' ) ) ) {
					update_option( 'qcld_grok_api_key', $value, false );
				}
			}
		}

		// Keep global settings aligned
		$global_settings = get_option( 'qcwpbot_settings', array() );
		if ( ! is_array( $global_settings ) ) {
			$global_settings = array();
		}
		foreach ( array( 'double_optin', 'track_opens', 'track_clicks', 'batch_size', 'batch_interval', 'gdpr_mode', 'emails_per_second' ) as $g_key ) {
			if ( $request->has_param( $g_key ) ) {
				$global_settings[ $g_key ] = $request->get_param( $g_key );
			}
		}
		update_option( 'qcwpbot_settings', $global_settings, false );

		if ( function_exists( 'qcwpbot_clear_settings_cache' ) ) {
			\qcwpbot_clear_settings_cache( array_merge( array( 'qcwpbot_settings' ), array_map( function ( $key ) {
				return 'qcwpbot_' . $key;
			}, array_keys( $fields ) ) ) );
		}

		return new \WP_REST_Response( array( 'message' => __( 'Settings saved.', 'chatbot' ) ) );
	}
}
