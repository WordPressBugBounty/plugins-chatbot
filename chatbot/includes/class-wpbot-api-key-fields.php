<?php
/**
 * AI provider API keys on the settings screens: saved keys never reach the browser.
 *
 * Settings fields show a mask with the last 4 characters (********abcd) instead of the key.
 * Saving the mask keeps the saved key; an empty field clears it. Model lists that used to be
 * fetched from the browser with the key are fetched by the server (qcld_wpbot_provider_models).
 * The same file ships in WPBot Free and Pro.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_mask_api_key' ) ) {
	/**
	 * Mask of a key: eight asterisks and its last 4 characters ('' when no key). API keys never
	 * contain "*", so a posted value with "****" is always a mask.
	 *
	 * @param mixed $key Key.
	 * @return string
	 */
	function qcld_wpbot_mask_api_key( $key ) {
		$key = trim( (string) $key );
		if ( '' === $key ) {
			return '';
		}
		return str_repeat( '*', 8 ) . ( strlen( $key ) > 8 ? substr( $key, -4 ) : '' );
	}

	/**
	 * Value for a key field on a settings screen: the mask of the saved key.
	 *
	 * @param string $option Option name.
	 * @return string
	 */
	function qcld_wpbot_api_key_field_value( $option ) {
		return qcld_wpbot_mask_api_key( get_option( $option, '' ) );
	}

	/**
	 * True when a posted value is a mask (the field was not changed).
	 *
	 * @param string $value Posted value.
	 * @return bool
	 */
	function qcld_wpbot_is_masked_api_key( $value ) {
		return false !== strpos( (string) $value, '****' );
	}

	/**
	 * Key to save from a settings form field.
	 *
	 * Field not posted, or posted with the mask: the saved key. Empty: '' (the owner cleared it), or
	 * the saved key with $empty_keeps (setup wizard, key checks). Otherwise the new key, cleaned.
	 *
	 * @param string $field       POST field name.
	 * @param string $option      Option that holds the key.
	 * @param bool   $empty_keeps Keep the saved key when the field is empty.
	 * @return string
	 */
	function qcld_wpbot_posted_api_key( $field, $option, $empty_keeps = false ) {
		$saved = (string) get_option( $option, '' );
		if ( ! isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce.
			return $saved;
		}
		$value = trim( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( qcld_wpbot_is_masked_api_key( $value ) ) {
			return $saved;
		}
		if ( '' === $value ) {
			return $empty_keeps ? $saved : '';
		}
		return function_exists( 'qcld_wpbot_clean_api_key' ) ? qcld_wpbot_clean_api_key( $value ) : (string) preg_replace( '/[^\x21-\x7E]/', '', $value );
	}
}

if ( ! function_exists( 'qcld_wpbot_provider_models_ajax' ) ) {
	/**
	 * AJAX (admins): model list of an AI provider, fetched by the server so the key stays on the server.
	 * Uses the key typed in the field when it is new, else the saved key.
	 *
	 * @return void
	 */
	function qcld_wpbot_provider_models_ajax() {
		if ( ! check_ajax_referer( 'wp_chatbot', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json( array( 'error' => array( 'message' => 'Not allowed' ) ), 403 );
		}
		$provider  = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$providers = array(
			'mistral'    => array( 'qcld_mistral_api_key', 'https://api.mistral.ai/v1/models' ),
			'openrouter' => array( 'qcld_openrouter_api_key', 'https://openrouter.ai/api/v1/models' ),
		);
		if ( ! isset( $providers[ $provider ] ) ) {
			wp_send_json( array( 'error' => array( 'message' => 'Unknown provider' ) ), 400 );
		}
		list( $option, $url ) = $providers[ $provider ];
		$key = qcld_wpbot_posted_api_key( 'api_key', $option, true );
		if ( 'openrouter' === $provider ) {
			// Only the saved endpoint: the saved key is never sent to an address given in the request.
			$endpoint = trim( (string) get_option( 'qcld_openrouter_endpoint_url', '' ) );
			if ( '' !== $endpoint && preg_match( '#^https?://#i', $endpoint ) ) {
				$url = str_replace( '/chat/completions', '/models', $endpoint );
			}
		}
		if ( '' === $key ) {
			wp_send_json( array( 'error' => array( 'message' => 'No API key is set.' ) ), 400 );
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json( array( 'error' => array( 'message' => $response->get_error_message() ) ), 502 );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json( is_array( $body ) ? $body : array( 'error' => array( 'message' => 'Invalid response' ) ), (int) wp_remote_retrieve_response_code( $response ) );
	}
	add_action( 'wp_ajax_qcld_wpbot_provider_models', 'qcld_wpbot_provider_models_ajax' );
}

if ( ! function_exists( 'qcld_wpbot_fall_back_to_openai' ) ) {
	/**
	 * After the owner switched an AI provider off: switch OpenAI on only when no other provider is on
	 * and an OpenAI key is saved. The settings screens used to switch OpenAI on every time a provider
	 * was saved switched off, even while another provider was on (two providers on, OpenAI won).
	 *
	 * @return void
	 */
	function qcld_wpbot_fall_back_to_openai() {
		$switches = array_diff( qcld_wpbot_ai_provider_switches(), array( 'ai_enabled' ) );
		foreach ( $switches as $option ) {
			if ( '1' === (string) get_option( $option ) ) {
				return;
			}
		}
		if ( '' !== trim( (string) get_option( 'open_ai_api_key', '' ) ) ) {
			update_option( 'ai_enabled', 1 );
		}
	}
}

if ( ! function_exists( 'qcld_wpbot_protect_secret_option' ) ) {
	/**
	 * For secrets saved through the WordPress settings API (options.php): when the form sends the
	 * mask back unchanged, keep the saved value. Use with qcld_wpbot_api_key_field_value() in the field.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	function qcld_wpbot_protect_secret_option( $option ) {
		add_filter(
			'pre_update_option_' . $option,
			function ( $value, $old_value ) {
				return ( is_string( $value ) && qcld_wpbot_is_masked_api_key( $value ) ) ? $old_value : $value;
			},
			10,
			2
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_ai_provider_switches' ) ) {
	/**
	 * The on/off option of every AI provider (OpenAI uses ai_enabled). Only one may be on.
	 *
	 * @return string[]
	 */
	function qcld_wpbot_ai_provider_switches() {
		return array( 'ai_enabled', 'qcld_gemini_enabled', 'qcld_grok_enabled', 'qcld_openrouter_enabled', 'qcld_mistral_enabled', 'qcld_claude_enabled', 'qcld_ollama_enabled', 'qcld_deepseekai_enabled' );
	}
}

if ( ! function_exists( 'qcld_wpbot_ai_provider_switched_on' ) ) {
	/**
	 * A provider's settings were saved with the provider switched on: switch every other provider
	 * off and turn site search off (site search is only a stop-gap for sites with no AI connected).
	 *
	 * @param string $switch The saved provider's on/off option (see qcld_wpbot_ai_provider_switches()).
	 * @return void
	 */
	function qcld_wpbot_ai_provider_switched_on( $switch ) {
		foreach ( qcld_wpbot_ai_provider_switches() as $option ) {
			if ( $option !== $switch ) {
				update_option( $option, 0 );
			}
		}
		update_option( 'disable_wp_chatbot_site_search', 1 );
	}
}

if ( ! function_exists( 'qcld_wpbot_channel_secret_options' ) ) {
	/**
	 * Messaging-channel tokens and secrets (Facebook, Instagram, Slack, Teams, Telegram, Twilio,
	 * WhatsApp). Their fields show the mask; saving the mask keeps the saved value.
	 *
	 * @return string[]
	 */
	function qcld_wpbot_channel_secret_options() {
		return (array) apply_filters(
			'qcld_wpbot_channel_secret_options',
			array(
				'wpfb_app_secret',
				'wpfb_user_access_token',
				'wpfb_page_access_token',
				'wpfb_verify_token',
				'wpig_page_access_token',
				'wpig_verify_token',
				'slack_webhook_url',
				'slack_secret_key',
				'slack_auth_token',
				'teams_app_password',
				'tg_access_token',
				'wa_auth_token',
				'wa_meta_access_token',
			)
		);
	}
	foreach ( qcld_wpbot_channel_secret_options() as $qcld_wpbot_channel_secret ) {
		qcld_wpbot_protect_secret_option( $qcld_wpbot_channel_secret );
	}
	unset( $qcld_wpbot_channel_secret );
}
