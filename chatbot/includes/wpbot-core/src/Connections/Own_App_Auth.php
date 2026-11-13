<?php
/**
 * Social login through the site owner's own developer app (decision D1).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Own-app OAuth 2.0 provider.
 */
final class Own_App_Auth implements Auth_Provider {

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'own_app';
	}

	/**
	 * OAuth endpoints per app. Modules add apps (LinkedIn, X, ...) through
	 * the qcld_wpbot_core/social_oauth_endpoints filter.
	 *
	 * Optional keys: client_param (name of the client id parameter, default
	 * "client_id"; TikTok uses "client_key"), basic_auth (send the app id and
	 * secret as an HTTP Basic header on the token request, as X and Pinterest
	 * require, instead of in the body).
	 *
	 * @return array<string,array{authorize:string, token:string, separator:string, pkce:bool, client_param?:string, basic_auth?:bool}>
	 */
	public static function endpoints() {
		return (array) apply_filters(
			'qcld_wpbot_core/social_oauth_endpoints',
			array(
				'meta'    => array(
					'authorize' => 'https://www.facebook.com/' . Meta::VERSION . '/dialog/oauth',
					'token'     => Meta::HOST . '/' . Meta::VERSION . '/oauth/access_token',
					'separator' => ',',
					'pkce'      => false,
				),
				'threads' => array(
					'authorize' => 'https://threads.net/oauth/authorize',
					'token'     => Threads::HOST . '/oauth/access_token',
					'separator' => ',',
					'pkce'      => false,
				),
			)
		);
	}

	/**
	 * Whether this provider handles an app.
	 *
	 * @param string $app App id.
	 * @return bool
	 */
	public function supports( $app ) {
		return isset( self::endpoints()[ $app ] );
	}

	/**
	 * Consent URL.
	 *
	 * @param string $app        App id.
	 * @param array  $scopes     Scopes.
	 * @param string $return_url Return URL (admin only).
	 * @param string $capability Capability the callback requires.
	 * @param string $owner      Module that started the login (for example
	 *                           "social"); handed back in the payload so each
	 *                           module only takes its own logins.
	 * @return string|WP_Error
	 */
	public function auth_url( $app, array $scopes, $return_url, $capability, $owner = '' ) {
		$endpoints = self::endpoints();
		if ( ! isset( $endpoints[ $app ] ) ) {
			return new WP_Error( 'qcld_wpbot_core_social_app', __( 'Unknown network app.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! Social_Apps::has( $app ) ) {
			return new WP_Error( 'qcld_wpbot_core_social_no_app', __( 'Save your app ID and secret first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$endpoint = $endpoints[ $app ];
		$created  = Oauth_State::create(
			array(
				'app'        => $app,
				'provider'   => $this->id(),
				'return'     => Social_Auth::safe_return( $return_url ),
				'capability' => sanitize_key( $capability ),
				'owner'      => sanitize_key( $owner ),
			),
			! empty( $endpoint['pkce'] )
		);
		$param    = ! empty( $endpoint['client_param'] ) ? (string) $endpoint['client_param'] : 'client_id';
		$args     = array(
			$param          => Social_Apps::client_id( $app ),
			'redirect_uri'  => Social_Auth::redirect_uri(),
			'response_type' => 'code',
			'scope'         => implode( $endpoint['separator'], array_map( 'sanitize_text_field', $scopes ) ),
			'state'         => $created['state'],
		);
		if ( '' !== $created['challenge'] ) {
			$args['code_challenge']        = $created['challenge'];
			$args['code_challenge_method'] = 'S256';
		}
		return $endpoint['authorize'] . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Swap a code for tokens.
	 *
	 * @param string $app   App id.
	 * @param string $code  Code.
	 * @param array  $state State payload.
	 * @return array|WP_Error
	 */
	public function exchange( $app, $code, array $state ) {
		$endpoints = self::endpoints();
		if ( '' === (string) $code || ! isset( $endpoints[ $app ] ) ) {
			return new WP_Error( 'qcld_wpbot_core_social_no_code', __( 'The network did not send an authorization code.', 'chatbot' ) );
		}
		$endpoint = $endpoints[ $app ];
		$param    = ! empty( $endpoint['client_param'] ) ? (string) $endpoint['client_param'] : 'client_id';
		$headers  = array( 'Accept' => 'application/json' );
		$fields   = array(
			$param         => Social_Apps::client_id( $app ),
			'redirect_uri' => Social_Auth::redirect_uri(),
			'grant_type'   => 'authorization_code',
			'code'         => (string) $code,
		);
		if ( ! empty( $endpoint['basic_auth'] ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication (RFC 7617).
			$headers['Authorization'] = 'Basic ' . base64_encode( Social_Apps::client_id( $app ) . ':' . Social_Apps::client_secret( $app ) );
		} else {
			$fields['client_secret'] = Social_Apps::client_secret( $app );
		}
		if ( ! empty( $state['verifier'] ) ) {
			$fields['code_verifier'] = (string) $state['verifier'];
		}
		$data = Social_Http::send( 'POST', $endpoint['token'], $headers, $fields );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_social_no_token', __( 'The network did not return an access token.', 'chatbot' ) );
		}
		return self::normalise( $data );
	}

	/**
	 * Extend or refresh a token (Meta: long-lived exchange; Threads: refresh).
	 *
	 * @param string $app    App id.
	 * @param array  $tokens Tokens.
	 * @return array|WP_Error
	 */
	public function refresh( $app, array $tokens ) {
		if ( 'meta' === $app ) {
			return Meta::extend( $tokens );
		}
		if ( 'threads' === $app ) {
			return Threads::refresh( $tokens );
		}
		/**
		 * Refresh tokens for apps added by modules.
		 *
		 * @param array|WP_Error|null $result Result; null when nobody handled it.
		 * @param string              $app    App id.
		 * @param array               $tokens Tokens.
		 */
		$result = apply_filters( 'qcld_wpbot_core/social_refresh', null, $app, $tokens );
		return null === $result ? new WP_Error( 'qcld_wpbot_core_social_refresh', __( 'This connection cannot be refreshed. Reconnect it.', 'chatbot' ) ) : $result;
	}

	/**
	 * Common token shape: expires_in becomes expires_at.
	 *
	 * @param array $data Raw token response.
	 * @return array
	 */
	public static function normalise( array $data ) {
		$data['expires_at'] = ! empty( $data['expires_in'] ) ? time() + (int) $data['expires_in'] : ( isset( $data['expires_at'] ) ? (int) $data['expires_at'] : 0 );
		unset( $data['expires_in'] );
		return $data;
	}
}
