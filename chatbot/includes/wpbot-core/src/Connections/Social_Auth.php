<?php
/**
 * Social auth registry and the single OAuth callback.
 *
 * The callback checks the one-time state (same user, single use, not
 * expired) and the capability the caller asked for, swaps the code through
 * the provider, then hands the tokens to the module that started the login
 * through the qcld_wpbot_core/social_authorized filter. Core never stores
 * account tokens itself; the module does, the same way for every provider.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Social auth.
 */
final class Social_Auth {

	/**
	 * Admin-post action that receives every network's redirect.
	 */
	const CALLBACK_ACTION = 'qcld_wpbot_core_social_oauth';

	/**
	 * Hook the callback (logged-in users only).
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_post_' . self::CALLBACK_ACTION, array( __CLASS__, 'callback' ) );
	}

	/**
	 * Registered providers, keyed by id. A QuantumCloud connect app can be
	 * added later through qcld_wpbot_core/social_auth_providers.
	 *
	 * @return array<string,Auth_Provider>
	 */
	public static function providers() {
		$list = (array) apply_filters( 'qcld_wpbot_core/social_auth_providers', array( new Own_App_Auth() ) );
		$out  = array();
		foreach ( $list as $provider ) {
			if ( $provider instanceof Auth_Provider ) {
				$out[ $provider->id() ] = $provider;
			}
		}
		return $out;
	}

	/**
	 * The provider for an app: the owner's choice when given, else the first
	 * that supports it.
	 *
	 * @param string $app       App id.
	 * @param string $preferred Preferred provider id.
	 * @return Auth_Provider|null
	 */
	public static function provider( $app, $preferred = 'own_app' ) {
		$providers = self::providers();
		if ( isset( $providers[ $preferred ] ) && $providers[ $preferred ]->supports( $app ) ) {
			return $providers[ $preferred ];
		}
		foreach ( $providers as $provider ) {
			if ( $provider->supports( $app ) ) {
				return $provider;
			}
		}
		return null;
	}

	/**
	 * Redirect URI the owner adds to their app.
	 *
	 * @return string
	 */
	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=' . self::CALLBACK_ACTION );
	}

	/**
	 * Return URL limited to this site's admin (no open redirect).
	 *
	 * @param string $url Requested URL.
	 * @return string
	 */
	public static function safe_return( $url ) {
		$admin = admin_url();
		$url   = (string) $url;
		return ( '' !== $url && 0 === strpos( $url, $admin ) ) ? $url : $admin;
	}

	/**
	 * Handle a network's redirect.
	 *
	 * @return void
	 */
	public static function callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Network redirect; checked against the one-time state.
		$state  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code   = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$denied = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable
		$result = self::complete( $state, $code, $denied );
		if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'qcld_wpbot_core_oauth_state', 'qcld_wpbot_core_oauth_user', 'qcld_wpbot_core_social_forbidden' ), true ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}
		$data   = is_wp_error( $result ) ? $result->get_error_data() : $result;
		$return = self::safe_return( is_array( $data ) && isset( $data['return'] ) ? (string) $data['return'] : '' );
		if ( is_wp_error( $result ) ) {
			set_transient( 'qcld_wpbot_core_social_notice_' . get_current_user_id(), Social_Http::scrub( $result->get_error_message() ), 5 * MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( add_query_arg( 'qcld_social', is_wp_error( $result ) ? 'error' : 'connected', $return ) );
		exit;
	}

	/**
	 * Complete a login (testable without the redirect).
	 *
	 * @param string $state  State.
	 * @param string $code   Code.
	 * @param string $denied Error from the network, if the owner declined.
	 * @return array|WP_Error ['return' => URL] on success.
	 */
	public static function complete( $state, $code, $denied = '' ) {
		$payload = Oauth_State::consume( $state );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		$capability = isset( $payload['capability'] ) && '' !== $payload['capability'] ? (string) $payload['capability'] : 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			return new WP_Error( 'qcld_wpbot_core_social_forbidden', __( 'You are not allowed to connect accounts.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$fail = static function ( WP_Error $error ) use ( $payload ) {
			return new WP_Error( $error->get_error_code(), $error->get_error_message(), array( 'return' => (string) $payload['return'] ) );
		};
		if ( '' !== $denied ) {
			return $fail( new WP_Error( 'qcld_wpbot_core_social_denied', __( 'Access was not given. Click Connect again and allow every permission asked for.', 'chatbot' ) ) );
		}
		$app      = (string) $payload['app'];
		$provider = self::provider( $app, isset( $payload['provider'] ) ? (string) $payload['provider'] : 'own_app' );
		if ( null === $provider ) {
			return $fail( new WP_Error( 'qcld_wpbot_core_social_app', __( 'Unknown network app.', 'chatbot' ) ) );
		}
		$tokens = $provider->exchange( $app, $code, $payload );
		if ( ! is_wp_error( $tokens ) && in_array( $app, array( 'meta', 'threads' ), true ) ) {
			// Short-lived tokens become long-lived right away.
			$tokens = 'meta' === $app ? Meta::extend( $tokens ) : Threads::extend( $tokens );
		}
		if ( is_wp_error( $tokens ) ) {
			return $fail( $tokens );
		}

		/**
		 * The module that started the login stores the tokens.
		 *
		 * @param true|WP_Error|null $handled Null until a module handles it.
		 * @param string             $app     App id.
		 * @param array              $tokens  Token array.
		 * @param array              $payload State payload (return, capability, plus the module's own keys).
		 */
		$handled = apply_filters( 'qcld_wpbot_core/social_authorized', null, $app, $tokens, $payload );
		if ( is_wp_error( $handled ) ) {
			return $fail( $handled );
		}
		if ( true !== $handled ) {
			return $fail( new WP_Error( 'qcld_wpbot_core_social_unhandled', __( 'No module is waiting for this sign-in. Start again from the Accounts screen.', 'chatbot' ) ) );
		}
		return array( 'return' => (string) $payload['return'] );
	}

	/**
	 * Last connect error for the current user (read once).
	 *
	 * @return string
	 */
	public static function take_notice() {
		$key     = 'qcld_wpbot_core_social_notice_' . get_current_user_id();
		$message = (string) get_transient( $key );
		delete_transient( $key );
		return $message;
	}
}
