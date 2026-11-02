<?php
/**
 * Third Party Integrations Controller — Syncing contacts from other form plugins
 * and capturing submissions on the fly via AI JSON extraction + rule fallback.
 *
 * @package WPBot\EmailMarketing\Controllers
 */

namespace WPBot\EmailMarketing\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ThirdPartyController {

	/**
	 * Register hooks for live form submissions.
	 */
	public static function init_form_hooks() {
		static $initialized = false;
		if ( $initialized ) {
			return;
		}
		$initialized = true;

		// 1. Contact Form 7
		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'on_cf7_submission' ), 10, 1 );

		// 2. WPForms
		add_action( 'wpforms_process_complete', array( __CLASS__, 'on_wpforms_submission' ), 10, 4 );

		// 3. Gravity Forms
		add_action( 'gform_after_submission', array( __CLASS__, 'on_gravity_submission' ), 10, 2 );

		// 4. Chatbot Session Subscription
		add_action( 'wp_ajax_qcld_wb_chatbot_email_subscription', array( __CLASS__, 'on_chatbot_submission' ), 5 );
		add_action( 'wp_ajax_nopriv_qcld_wb_chatbot_email_subscription', array( __CLASS__, 'on_chatbot_submission' ), 5 );

		// 5. Conversational Forms
		add_action( 'caldera_forms_submit_complete', array( __CLASS__, 'on_conversational_submission' ), 10, 4 );
	}

	/**
	 * Check if live auto-capture is enabled for a given source.
	 *
	 * @param string $source Source key (cf7, wpforms, gravity, chatbot, conversational).
	 * @return bool
	 */
	public static function is_capture_enabled( string $source ): bool {
		if ( function_exists( 'qcwpbot_has_pro' ) && ! qcwpbot_has_pro() && 'wpforms' !== $source ) {
			return false;
		}
		$settings = get_option( 'qcwpbot_lead_capture_settings', array() );
		if ( ! is_array( $settings ) ) {
			return false;
		}
		return ! empty( $settings[ $source ] );
	}

	/* ══════════════════════════════════════════════════════
	 *  LIVE SUBMISSION HOOK HANDLERS
	 * ══════════════════════════════════════════════════════ */

	/**
	 * Hooked to Contact Form 7 on successful email delivery.
	 *
	 * @param \WPCF7_ContactForm $contact_form CF7 form instance.
	 */
	public static function on_cf7_submission( $contact_form ) {
		if ( ! self::is_capture_enabled( 'cf7' ) ) {
			return;
		}

		if ( ! class_exists( '\WPCF7_Submission' ) ) {
			return;
		}
		$submission = \WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}
		$posted_data = $submission->get_posted_data();
		if ( empty( $posted_data ) || ! is_array( $posted_data ) ) {
			return;
		}

		$fields = array();
		foreach ( $posted_data as $key => $val ) {
			// Skip CF7 internal fields
			if ( strpos( (string) $key, '_' ) === 0 ) {
				continue;
			}
			if ( is_array( $val ) ) {
				$val = implode( ', ', $val );
			}
			$fields[ (string) $key ] = sanitize_text_field( (string) $val );
		}

		self::process_and_save_contact( $fields, 'cf7', 'Contact Form 7' );
	}

	/**
	 * Hooked to WPForms process complete.
	 *
	 * @param array $fields Form fields submitted.
	 * @param array $entry Raw entry.
	 * @param array $form_data Form data.
	 * @param int   $entry_id Entry ID.
	 */
	public static function on_wpforms_submission( $fields, $entry, $form_data, $entry_id ) {
		if ( ! self::is_capture_enabled( 'wpforms' ) ) {
			return;
		}

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return;
		}

		$clean_fields = array();
		foreach ( $fields as $field ) {
			$label = ! empty( $field['name'] ) ? $field['name'] : ( $field['id'] ?? 'field' );
			$val   = $field['value'] ?? '';
			if ( is_array( $val ) ) {
				$val = implode( ', ', $val );
			}
			$clean_fields[ (string) $label ] = sanitize_text_field( (string) $val );
		}

		self::process_and_save_contact( $clean_fields, 'wpforms', 'WPForms' );
	}

	/**
	 * Hooked to Gravity Forms after submission.
	 *
	 * @param array $entry Submission entry.
	 * @param array $form Form structure.
	 */
	public static function on_gravity_submission( $entry, $form ) {
		if ( ! self::is_capture_enabled( 'gravity' ) ) {
			return;
		}

		if ( empty( $entry ) || ! is_array( $entry ) ) {
			return;
		}

		$clean_fields = array();
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$field_id = is_object( $field ) ? ( $field->id ?? null ) : ( $field['id'] ?? null );
				$label    = is_object( $field ) ? ( $field->label ?? "field_{$field_id}" ) : ( $field['label'] ?? "field_{$field_id}" );

				if ( ! $field_id ) {
					continue;
				}

				if ( isset( $entry[ (string) $field_id ] ) ) {
					$clean_fields[ $label ] = sanitize_text_field( (string) $entry[ (string) $field_id ] );
				} elseif ( is_object( $field ) && ! empty( $field->inputs ) && is_array( $field->inputs ) ) {
					$sub_vals = array();
					foreach ( $field->inputs as $input ) {
						$inp_id = $input['id'] ?? '';
						if ( $inp_id && ! empty( $entry[ $inp_id ] ) ) {
							$sub_vals[] = sanitize_text_field( (string) $entry[ $inp_id ] );
						}
					}
					if ( ! empty( $sub_vals ) ) {
						$clean_fields[ $label ] = implode( ' ', $sub_vals );
					}
				}
			}
		} else {
			foreach ( $entry as $k => $v ) {
				if ( is_numeric( $k ) && is_string( $v ) ) {
					$clean_fields[ "field_{$k}" ] = sanitize_text_field( $v );
				}
			}
		}

		self::process_and_save_contact( $clean_fields, 'gravity', 'Gravity Forms' );
	}

	/**
	 * Hooked to Chatbot Session Subscription.
	 */
	public static function on_chatbot_submission() {
		if ( ! self::is_capture_enabled( 'chatbot' ) ) {
			return;
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $email ) || ! is_email( $email ) ) {
			return;
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$parts = explode( ' ', $name, 2 );
		$contact = array(
			'email'      => $email,
			'first_name' => $parts[0] ?? '',
			'last_name'  => $parts[1] ?? '',
			'phone'      => $phone,
			'name'       => $name ?: 'Chatbot Lead',
		);

		self::save_subscriber( $contact, 'chatbot', 'Chatbot' );
	}

	/**
	 * Hooked to Conversational Forms after submit.
	 *
	 * @param array $form Caldera form.
	 * @param mixed $referrer Referrer.
	 * @param mixed $process_id Process ID.
	 * @param mixed $entry_id Entry ID.
	 */
	public static function on_conversational_submission( $form, $referrer, $process_id, $entry_id = null ) {
		if ( ! self::is_capture_enabled( 'conversational' ) ) {
			return;
		}

		if ( ! empty( $entry_id ) && class_exists( 'Caldera_Forms' ) ) {
			$entry = \Caldera_Forms::get_entry( $entry_id, $form );
			if ( ! empty( $entry['data'] ) && is_array( $entry['data'] ) ) {
				$fields = array();
				foreach ( $entry['data'] as $field_id => $data ) {
					$val = $data['value'] ?? '';
					if ( is_array( $val ) ) {
						$val = implode( ', ', $val );
					}
					$label = $form['fields'][ $field_id ]['label'] ?? $field_id;
					$fields[ $label ] = (string) $val;
				}
				self::process_and_save_contact( $fields, 'conversational', 'Conversational Form' );
			}
		}
	}

	/* ══════════════════════════════════════════════════════
	 *  EXTRACTION & PROCESSING
	 * ══════════════════════════════════════════════════════ */

	/**
	 * Process form fields, extract contact with AI (or fallback rules), and save to lists.
	 *
	 * @param array  $fields Form fields map.
	 * @param string $source_slug Slug identifier (e.g. cf7, wpforms, gravity).
	 * @param string $source_label Human readable name.
	 * @return int|null Subscriber ID or null.
	 */
	public static function process_and_save_contact( array $fields, string $source_slug, string $source_label ) {
		if ( empty( $fields ) ) {
			return null;
		}

		// 1. Try AI extraction first if OpenAI API key is present
		$contact = self::extract_with_ai( $fields );

		// 2. If AI did not return a valid email or was unavailable, use rule-based parser
		if ( empty( $contact['email'] ) || ! is_email( $contact['email'] ) ) {
			$contact = self::extract_with_rules( $fields );
		}

		if ( empty( $contact['email'] ) || ! is_email( $contact['email'] ) ) {
			return null;
		}

		return self::save_subscriber( $contact, $source_slug, $source_label );
	}

	/**
	 * Get OpenAI API key from available plugin settings.
	 *
	 * @return string
	 */
	public static function get_openai_api_key(): string {
		$key = get_option( 'qcwpbot_openai_api_key', '' );
		if ( ! empty( $key ) ) {
			return trim( (string) $key );
		}
		$key = get_option( 'open_ai_api_key', '' );
		if ( ! empty( $key ) ) {
			return trim( (string) $key );
		}
		$key = get_option( 'qcld_openai_api_key', '' );
		if ( ! empty( $key ) ) {
			return trim( (string) $key );
		}
		$chatbot_key = get_option( 'qlcd_wp_chatbot_admin_open_api_key' );
		if ( ! empty( $chatbot_key ) ) {
			return trim( (string) $chatbot_key );
		}
		$settings = get_option( 'qcwpbot_leadfinder_settings', array() );
		if ( ! empty( $settings['openai_key'] ) ) {
			return trim( (string) $settings['openai_key'] );
		}
		$key = get_option( 'openai_api_key' );
		return ! empty( $key ) ? trim( (string) $key ) : '';
	}

	/**
	 * Extract contact details using OpenAI JSON response.
	 *
	 * @param array $fields Form fields.
	 * @return array
	 */
	public static function extract_with_ai( array $fields ): array {
		$api_key = self::get_openai_api_key();
		if ( empty( $api_key ) ) {
			return array();
		}

		// Clean and truncate values to avoid huge prompt payloads
		$compact_fields = array();
		foreach ( $fields as $k => $v ) {
			$compact_fields[ $k ] = mb_substr( (string) $v, 0, 300 );
		}

		$prompt = "You are a contact data extraction assistant. Given this submitted form data:\n"
				. wp_json_encode( $compact_fields ) . "\n\n"
				. "Identify the contact person's name, email, and phone.\n"
				. "Return ONLY a valid JSON object with exactly these keys:\n"
				. "{\n"
				. "  \"first_name\": \"...\",\n"
				. "  \"last_name\": \"...\",\n"
				. "  \"email\": \"...\",\n"
				. "  \"phone\": \"...\"\n"
				. "}\n"
				. "If the contact's name is a single full name, split it into first_name and last_name. If any field is not found, leave it as an empty string. Return JSON only, with no markdown fences.";

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 8,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'       => 'gpt-4o-mini',
				'messages'    => array(
					array( 'role' => 'system', 'content' => 'You parse contact information from form submissions into strict JSON.' ),
					array( 'role' => 'user', 'content' => $prompt ),
				),
				'temperature' => 0.1,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		$content = $data['choices'][0]['message']['content'] ?? '';

		if ( ! empty( $content ) ) {
			$content = preg_replace( '/^```(?:json)?\s*/i', '', trim( $content ) );
			$content = preg_replace( '/\s*```$/', '', $content );
			$parsed = json_decode( trim( $content ), true );

			if ( is_array( $parsed ) && ! empty( $parsed['email'] ) && is_email( trim( $parsed['email'] ) ) ) {
				return array(
					'email'      => sanitize_email( trim( $parsed['email'] ) ),
					'first_name' => sanitize_text_field( $parsed['first_name'] ?? '' ),
					'last_name'  => sanitize_text_field( $parsed['last_name'] ?? '' ),
					'phone'      => sanitize_text_field( $parsed['phone'] ?? '' ),
				);
			}
		}

		return array();
	}

	/**
	 * Smart rule-based extraction fallback (no external API calls required).
	 *
	 * @param array $fields Form fields.
	 * @return array
	 */
	public static function extract_with_rules( array $fields ): array {
		$email      = '';
		$first_name = '';
		$last_name  = '';
		$phone      = '';

		foreach ( $fields as $key => $val ) {
			if ( ! is_string( $val ) ) {
				continue;
			}
			$val = trim( $val );
			$k   = strtolower( (string) $key );

			// 1. Detect Email
			if ( empty( $email ) && is_email( $val ) ) {
				$email = sanitize_email( $val );
				continue;
			}

			// 2. Detect First Name / Last Name / Full Name
			if ( strpos( $k, 'first' ) !== false && strpos( $k, 'name' ) !== false && empty( $first_name ) ) {
				$first_name = sanitize_text_field( $val );
			} elseif ( strpos( $k, 'last' ) !== false && strpos( $k, 'name' ) !== false && empty( $last_name ) ) {
				$last_name = sanitize_text_field( $val );
			} elseif ( ( $k === 'name' || $k === 'your-name' || strpos( $k, 'full' ) !== false || strpos( $k, 'name' ) !== false ) && empty( $first_name ) ) {
				$parts = explode( ' ', sanitize_text_field( $val ), 2 );
				$first_name = $parts[0] ?? '';
				$last_name  = $parts[1] ?? '';
			}

			// 3. Detect Phone
			if ( ( strpos( $k, 'phone' ) !== false || strpos( $k, 'tel' ) !== false || strpos( $k, 'mobile' ) !== false ) && empty( $phone ) ) {
				$phone = sanitize_text_field( $val );
			}
		}

		return array(
			'email'      => $email,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'phone'      => $phone,
		);
	}

	/**
	 * Insert or update subscriber, link to source list in pivot table, and trigger automations.
	 *
	 * @param array  $contact Contact data (email, first_name, last_name, phone).
	 * @param string $source_slug Source identifier (e.g. cf7, wpforms, gravity).
	 * @param string $source_label Source display name.
	 * @return int|null Subscriber ID or null.
	 */
	public static function save_subscriber( array $contact, string $source_slug, string $source_label ) {
		global $wpdb;
		$email = sanitize_email( $contact['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return null;
		}

		$subscribers_table = $wpdb->prefix . 'qcwpbot_subscribers';
		$lists_table       = $wpdb->prefix . 'qcwpbot_lists';
		$pivot_table       = $wpdb->prefix . 'qcwpbot_subscriber_pivot';

		// 1. Ensure dedicated list exists for this source
		$list_slug  = sanitize_title( $source_slug . '-leads' );
		$list_title = $source_label . ' Leads';
		$list_id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $lists_table WHERE slug = %s", $list_slug ) );

		if ( ! $list_id ) {
			$wpdb->insert( $lists_table, array(
				'title'       => $list_title,
				'slug'        => $list_slug,
				'description' => sprintf( 'Automatically captured leads from %s submissions.', $source_label ),
				'is_public'   => 0,
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			) );
			$list_id = (int) $wpdb->insert_id;
		}

		// 2. Insert or update subscriber
		$subscriber_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $subscribers_table WHERE email = %s", $email ) );
		$is_new = false;

		$first_name = sanitize_text_field( $contact['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $contact['last_name'] ?? '' );
		$phone      = sanitize_text_field( $contact['phone'] ?? '' );

		if ( ! $subscriber_id ) {
			$global_settings = get_option( 'qcwpbot_settings', array() );
			$initial_status  = ! empty( $global_settings['double_optin'] ) ? 'pending' : 'subscribed';
			$wpdb->insert( $subscribers_table, array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'phone'      => $phone,
				'status'     => $initial_status,
				'source'     => $source_slug,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			) );
			$subscriber_id = (int) $wpdb->insert_id;
			$is_new = true;
		} else {
			$existing_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $subscribers_table WHERE id = %d", $subscriber_id ) );
			if ( 'unsubscribed' === $existing_status ) {
				// Respect contact's opt-out decision: do not resubscribe or add to mailing list
				return null;
			}
			$update_data = array( 'updated_at' => current_time( 'mysql' ) );
			if ( ! empty( $first_name ) ) {
				$update_data['first_name'] = $first_name;
			}
			if ( ! empty( $last_name ) ) {
				$update_data['last_name'] = $last_name;
			}
			if ( ! empty( $phone ) ) {
				$update_data['phone'] = $phone;
			}
			$wpdb->update( $subscribers_table, $update_data, array( 'id' => $subscriber_id ) );
		}

		// 3. Link subscriber to the source list in the pivot table
		if ( $subscriber_id && $list_id ) {
			$pivot_exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $pivot_table WHERE subscriber_id = %d AND object_id = %d AND object_type = 'list'",
				$subscriber_id,
				$list_id
			) );
			if ( ! $pivot_exists ) {
				$wpdb->insert( $pivot_table, array(
					'subscriber_id' => $subscriber_id,
					'object_id'     => $list_id,
					'object_type'   => 'list',
					'status'        => 'subscribed',
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				) );
				do_action( 'qcwpbot_subscriber_list_added', (int) $subscriber_id, (int) $list_id, 'list' );
			}
		}

		if ( $is_new ) {
			do_action( 'qcwpbot_subscriber_created', (int) $subscriber_id, $contact );

			$lf_settings = get_option( 'qcwpbot_leadfinder_settings', array() );
			if ( is_array( $lf_settings ) ) {
				$lf_settings['total_leads'] = (int) ( $lf_settings['total_leads'] ?? 0 ) + 1;
				update_option( 'qcwpbot_leadfinder_settings', $lf_settings, false );
			}
		}

		return (int) $subscriber_id;
	}

	/* ══════════════════════════════════════════════════════
	 *  REST ENDPOINTS: STATUS & HISTORICAL SYNC
	 * ══════════════════════════════════════════════════════ */

	public function status( \WP_REST_Request $request ): \WP_REST_Response {
		$cf7_active            = defined( 'WPCF7_VERSION' ) || function_exists( 'wpcf7' );
		$wpforms_active        = function_exists( 'wpforms' );
		$gravity_active        = class_exists( 'GFForms' ) || class_exists( 'GFAPI' );
		$chatbot_active        = class_exists( 'qcld_wb_Chatbot' ) || class_exists( 'qcld_wb_Chatbot_free' ) || class_exists( 'QCLD_Woo_Chatbot' );
		$conversational_active = defined( 'WFBCORE_VER' );

		$saved_capturing = get_option( 'qcwpbot_lead_capture_settings', array() );
		if ( ! is_array( $saved_capturing ) ) {
			$saved_capturing = array();
		}

		$has_pro   = function_exists( 'qcwpbot_has_pro' ) ? qcwpbot_has_pro() : false;
		$capturing = array(
			'cf7'            => $has_pro && ! empty( $saved_capturing['cf7'] ),
			'wpforms'        => ! empty( $saved_capturing['wpforms'] ),
			'gravity'        => $has_pro && ! empty( $saved_capturing['gravity'] ),
			'chatbot'        => $has_pro && ! empty( $saved_capturing['chatbot'] ),
			'conversational' => $has_pro && ! empty( $saved_capturing['conversational'] ),
		);

		return new \WP_REST_Response( array(
			'cf7'            => $cf7_active,
			'wpforms'        => $wpforms_active,
			'gravity'        => $gravity_active,
			'chatbot'        => $chatbot_active,
			'conversational' => $conversational_active,
			'capturing'      => $capturing,
		) );
	}

	/**
	 * Toggle live auto-capture status for a form source.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function toggle_capture( \WP_REST_Request $request ): \WP_REST_Response {
		$source  = sanitize_text_field( (string) $request->get_param( 'source' ) );
		$allowed = array( 'cf7', 'wpforms', 'gravity', 'chatbot', 'conversational' );

		if ( ! in_array( $source, $allowed, true ) ) {
			return new \WP_REST_Response( array( 'message' => 'Invalid source specified.' ), 400 );
		}

		if ( function_exists( 'qcwpbot_has_pro' ) && ! qcwpbot_has_pro() && 'wpforms' !== $source ) {
			return new \WP_REST_Response( array(
				'message' => __( 'Only WPForms Live Capture is available in the Free version. Please upgrade to Pro for all integrations.', 'chatbot' ),
			), 403 );
		}

		$settings = get_option( 'qcwpbot_lead_capture_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( $request->has_param( 'enabled' ) ) {
			$new_state = (bool) $request->get_param( 'enabled' );
		} else {
			$current_state = ! empty( $settings[ $source ] );
			$new_state     = ! $current_state;
		}

		$settings[ $source ] = $new_state;
		update_option( 'qcwpbot_lead_capture_settings', $settings, false );

		$source_labels = array(
			'cf7'            => 'Contact Form 7',
			'wpforms'        => 'WPForms',
			'gravity'        => 'Gravity Forms',
			'chatbot'        => 'Chatbot Sessions',
			'conversational' => 'Conversational Forms',
		);
		$label = $source_labels[ $source ] ?? $source;

		return new \WP_REST_Response( array(
			'success'   => true,
			'source'    => $source,
			'capturing' => $new_state,
			'all'       => $settings,
			'message'   => $new_state
				? sprintf( 'Auto lead capture enabled for %s.', $label )
				: sprintf( 'Auto lead capture disabled for %s.', $label ),
		) );
	}

	public function sync( \WP_REST_Request $request ): \WP_REST_Response {
		if ( function_exists( 'qcwpbot_has_pro' ) && ! qcwpbot_has_pro() ) {
			return new \WP_REST_Response( array(
				'message' => __( 'Historical form submission sync is a Pro feature. Please upgrade to Pro.', 'chatbot' ),
			), 403 );
		}

		global $wpdb;
		$source         = sanitize_text_field( $request->get_param( 'source' ) );
		$extracted_data = array();
		$source_label   = 'Form';

		switch ( $source ) {
			case 'cf7':
				if ( ! ( defined( 'WPCF7_VERSION' ) || function_exists( 'wpcf7' ) ) ) {
					return new \WP_REST_Response( array( 'message' => 'Contact Form 7 is not active.' ), 400 );
				}
				$source_label = 'Contact Form 7';

				// 1. Flamingo Inbound messages (official CF7 database storage)
				$flamingo_rows = $wpdb->get_results(
					"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'flamingo_inbound' ORDER BY ID DESC LIMIT 500"
				);
				if ( ! empty( $flamingo_rows ) ) {
					foreach ( $flamingo_rows as $frow ) {
						$email = get_post_meta( $frow->ID, '_from_email', true );
						$name  = get_post_meta( $frow->ID, '_from_name', true );
						if ( is_email( $email ) ) {
							$parts = explode( ' ', sanitize_text_field( (string) $name ), 2 );
							$extracted_data[] = array(
								'email'      => sanitize_email( $email ),
								'first_name' => $parts[0] ?? '',
								'last_name'  => $parts[1] ?? '',
								'name'       => sanitize_text_field( $name ?: 'CF7 Contact' ),
							);
						}
					}
				}

				// 2. CFDB7 (Database for Contact Form 7) table if active
				$cfdb7_table = $wpdb->prefix . 'db7_forms';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '$cfdb7_table'" ) === $cfdb7_table ) {
					$cfdb7_rows = $wpdb->get_results( "SELECT form_value FROM $cfdb7_table ORDER BY form_id DESC LIMIT 500" );
					foreach ( $cfdb7_rows as $cfrow ) {
						$val = maybe_unserialize( $cfrow->form_value );
						if ( is_array( $val ) ) {
							$parsed = self::extract_with_rules( $val );
							if ( ! empty( $parsed['email'] ) ) {
								$extracted_data[] = array(
									'email'      => $parsed['email'],
									'first_name' => $parsed['first_name'],
									'last_name'  => $parsed['last_name'],
									'phone'      => $parsed['phone'],
									'name'       => trim( $parsed['first_name'] . ' ' . $parsed['last_name'] ) ?: 'CF7 Contact',
								);
							}
						}
					}
				}
				break;

			case 'wpforms':
				if ( ! function_exists( 'wpforms' ) ) {
					return new \WP_REST_Response( array( 'message' => 'WPForms is not active.' ), 400 );
				}
				$source_label = 'WPForms';

				$table = $wpdb->prefix . 'wpforms_entries';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
					$entries = $wpdb->get_results( "SELECT fields FROM $table ORDER BY entry_id DESC LIMIT 500" );
					if ( ! empty( $entries ) ) {
						foreach ( $entries as $entry_row ) {
							$fields = json_decode( (string) $entry_row->fields, true );
							if ( is_array( $fields ) ) {
								$flat_fields = array();
								foreach ( $fields as $f ) {
									$k = $f['name'] ?? ( $f['id'] ?? 'field' );
									$v = $f['value'] ?? '';
									if ( is_array( $v ) ) {
										$v = implode( ', ', $v );
									}
									$flat_fields[ (string) $k ] = (string) $v;
								}
								$parsed = self::extract_with_rules( $flat_fields );
								if ( ! empty( $parsed['email'] ) ) {
									$extracted_data[] = array(
										'email'      => $parsed['email'],
										'first_name' => $parsed['first_name'],
										'last_name'  => $parsed['last_name'],
										'phone'      => $parsed['phone'],
										'name'       => trim( $parsed['first_name'] . ' ' . $parsed['last_name'] ) ?: 'WPForms Contact',
									);
								}
							}
						}
					}
				}
				break;

			case 'gravity':
				if ( ! ( class_exists( 'GFForms' ) || class_exists( 'GFAPI' ) ) ) {
					return new \WP_REST_Response( array( 'message' => 'Gravity Forms is not active.' ), 400 );
				}
				$source_label = 'Gravity Forms';

				if ( class_exists( 'GFAPI' ) ) {
					$entries = \GFAPI::get_entries( 0, array(), null, array( 'offset' => 0, 'page_size' => 500 ) );
					if ( is_array( $entries ) ) {
						foreach ( $entries as $entry ) {
							$flat = array();
							foreach ( $entry as $k => $v ) {
								if ( is_string( $v ) && is_numeric( $k ) ) {
									$flat[ "field_{$k}" ] = $v;
								}
							}
							$parsed = self::extract_with_rules( $flat );
							if ( ! empty( $parsed['email'] ) ) {
								$extracted_data[] = array(
									'email'      => $parsed['email'],
									'first_name' => $parsed['first_name'],
									'last_name'  => $parsed['last_name'],
									'phone'      => $parsed['phone'],
									'name'       => trim( $parsed['first_name'] . ' ' . $parsed['last_name'] ) ?: 'Gravity Contact',
								);
							}
						}
					}
				} else {
					$gf_entry_table = $wpdb->prefix . 'gf_entry';
					$gf_meta_table  = $wpdb->prefix . 'gf_entry_meta';
					if ( $wpdb->get_var( "SHOW TABLES LIKE '$gf_entry_table'" ) === $gf_entry_table && $wpdb->get_var( "SHOW TABLES LIKE '$gf_meta_table'" ) === $gf_meta_table ) {
						$rows = $wpdb->get_results( "SELECT meta_value FROM $gf_meta_table WHERE meta_value LIKE '%@%.%' LIMIT 500" );
						foreach ( $rows as $row ) {
							if ( is_email( $row->meta_value ) ) {
								$extracted_data[] = array(
									'email'      => sanitize_email( $row->meta_value ),
									'first_name' => '',
									'last_name'  => '',
									'name'       => 'Gravity Contact',
								);
							}
						}
					}
				}
				break;

			case 'chatbot':
				if ( ! ( class_exists( 'qcld_wb_Chatbot' ) || class_exists( 'qcld_wb_Chatbot_free' ) || class_exists( 'QCLD_Woo_Chatbot' ) ) ) {
					return new \WP_REST_Response( array( 'message' => 'Chatbot is not active.' ), 400 );
				}
				$source_label = 'Chatbot';

				$tables = array( $wpdb->prefix . 'wpbot_subscription', $wpdb->prefix . 'wpbot_user' );
				foreach ( $tables as $table ) {
					if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
						$results = $wpdb->get_results( "SELECT name, email FROM $table WHERE email != '' LIMIT 500" );
						if ( ! empty( $results ) ) {
							foreach ( $results as $row ) {
								if ( is_email( $row->email ) ) {
									$parts = explode( ' ', sanitize_text_field( (string) $row->name ), 2 );
									$extracted_data[] = array(
										'email'      => sanitize_email( $row->email ),
										'first_name' => $parts[0] ?? '',
										'last_name'  => $parts[1] ?? '',
										'name'       => sanitize_text_field( $row->name ?: 'Chatbot Lead' ),
									);
								}
							}
						}
					}
				}
				break;

			case 'conversational':
				if ( ! defined( 'WFBCORE_VER' ) ) {
					return new \WP_REST_Response( array( 'message' => 'Conversational Forms is not active.' ), 400 );
				}
				$source_label = 'Conversational Form';

				$table = $wpdb->prefix . 'wfb_form_entry_values';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
					$results = $wpdb->get_results( "SELECT entry_id, value FROM $table WHERE value LIKE '%@%.%'" );
					$emails  = array();
					foreach ( $results as $row ) {
						if ( is_email( $row->value ) ) {
							$emails[ $row->entry_id ] = $row->value;
						}
					}
					foreach ( $emails as $entry_id => $email ) {
						$name_row = $wpdb->get_row( $wpdb->prepare( "SELECT value FROM $table WHERE entry_id = %d AND slug = 'current_user_name' LIMIT 1", $entry_id ) );
						$name     = $name_row ? $name_row->value : 'Conversational Form Lead';
						$parts    = explode( ' ', sanitize_text_field( (string) $name ), 2 );
						$extracted_data[] = array(
							'email'      => sanitize_email( $email ),
							'first_name' => $parts[0] ?? '',
							'last_name'  => $parts[1] ?? '',
							'name'       => sanitize_text_field( $name ),
						);
					}
				}
				break;

			default:
				return new \WP_REST_Response( array( 'message' => 'Unknown source.' ), 400 );
		}

		// Insert/link all extracted contacts into subscribers and their source list
		$added_count = 0;
		$seen_emails = array();
		foreach ( $extracted_data as $data ) {
			if ( empty( $data['email'] ) ) {
				continue;
			}
			$clean_email = strtolower( trim( $data['email'] ) );
			if ( isset( $seen_emails[ $clean_email ] ) ) {
				continue;
			}
			$seen_emails[ $clean_email ] = true;

			$sub_id = self::save_subscriber( $data, $source, $source_label );
			if ( $sub_id ) {
				$added_count++;
			}
		}

		return new \WP_REST_Response( array(
			'message'   => sprintf( 'Sync complete. Processed %d records into "%s Leads" list.', $added_count, $source_label ),
			'extracted' => $extracted_data,
		) );
	}
}

// Automatically activate form hooks whenever this controller file is loaded
ThirdPartyController::init_form_hooks();
