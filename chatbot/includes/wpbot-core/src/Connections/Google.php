<?php
/**
 * Shared Google connection through the site owner's own Google Cloud OAuth
 * client (Client ID and secret pasted in the Connections settings). Used for
 * Search Console now; SEO & AI Search reuses it later.
 *
 * The client secret and tokens are encrypted at rest, never sent to the
 * browser and never logged. Scope: read-only Search Console.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use QuantumCloud\WPBotCore\Crypto\Secret_Box;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Google OAuth connection in qcld_wpbot_core_connections['google'].
 */
final class Google {

	const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

	/**
	 * Admin-post action that receives Google's redirect.
	 */
	const CALLBACK_ACTION = 'qcld_wpbot_core_google';

	/**
	 * Write scopes modules may ask for through qcld_wpbot_core/google_write_scopes.
	 */
	const WRITE_SCOPES = array( 'https://www.googleapis.com/auth/youtube.upload' );

	/**
	 * Read-only Google Analytics scope (asked for when a module adds it).
	 */
	const SCOPE_ANALYTICS = 'https://www.googleapis.com/auth/analytics.readonly';

	/**
	 * Scopes asked for when the owner clicks Connect.
	 *
	 * Modules add read-only scopes through the filter; Search Console's
	 * scope is always included. Google keeps earlier grants because
	 * include_granted_scopes is on.
	 *
	 * @return string[]
	 */
	public static function scopes() {
		$scopes   = (array) apply_filters( 'qcld_wpbot_core/google_scopes', array( self::SCOPE ) );
		$scopes   = array_filter(
			array_map( 'strval', $scopes ),
			static function ( $scope ) {
				// Only Google's read-only scopes are accepted.
				return 0 === strpos( $scope, 'https://www.googleapis.com/auth/' ) && '.readonly' === substr( $scope, -9 );
			}
		);
		$scopes[] = self::SCOPE;
		// Google Ads has no read-only scope: "adwords" is asked for only after the owner turns the
		// Google Ads connection on, and Google_Ads::search() only ever reads reports.
		if ( Google_Ads::enabled() ) {
			$scopes[] = Google_Ads::SCOPE;
		}
		// Google Business Profile has no read-only scope either: "business.manage" is asked for only
		// after the owner turns Business Profile on; WPBot reads the profile and reviews and writes
		// nothing but a review reply, on the owner's click (round 3, I and J).
		if ( Business_Profile::wanted() ) {
			$scopes[] = Business_Profile::SCOPE;
		}
		/**
		 * Write scopes a module needs after the owner turned its feature on (for
		 * example WPBot Social's YouTube Shorts uploads). Only scopes on the
		 * WRITE_SCOPES list are accepted.
		 *
		 * @param string[] $scopes Scope URLs.
		 */
		$write  = (array) apply_filters( 'qcld_wpbot_core/google_write_scopes', array() );
		$scopes = array_merge( $scopes, array_values( array_intersect( array_map( 'strval', $write ), self::WRITE_SCOPES ) ) );
		return array_values( array_unique( $scopes ) );
	}

	/**
	 * Whether the saved Google access includes a scope.
	 *
	 * Connections made before scopes were recorded only have Search Console.
	 *
	 * @param string $scope Scope URL.
	 * @return bool
	 */
	public static function has_scope( $scope ) {
		if ( ! self::connected() ) {
			return false;
		}
		$granted = self::field( self::load(), 'scopes' );
		if ( '' === $granted ) {
			return self::SCOPE === $scope;
		}
		return in_array( (string) $scope, explode( ' ', $granted ), true );
	}

	/**
	 * Scopes modules asked for that the saved access does not include yet.
	 *
	 * @return string[]
	 */
	public static function missing_scopes() {
		if ( ! self::connected() ) {
			return array();
		}
		return array_values(
			array_filter(
				self::scopes(),
				static function ( $scope ) {
					return ! self::has_scope( $scope );
				}
			)
		);
	}

	/**
	 * Hook the OAuth callback.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_post_' . self::CALLBACK_ACTION, array( __CLASS__, 'callback' ) );
	}

	/**
	 * Google endpoint URL (filterable so tests can point at a local mock).
	 *
	 * @param string $name auth|token|revoke|api.
	 * @return string
	 */
	public static function endpoint( $name ) {
		$urls = array(
			'auth'           => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token'          => 'https://oauth2.googleapis.com/token',
			'revoke'         => 'https://oauth2.googleapis.com/revoke',
			'api'            => 'https://www.googleapis.com',
			// Added for SEO & AI Search (URL Inspection and Google Analytics).
			'searchconsole'  => 'https://searchconsole.googleapis.com',
			'analyticsdata'  => 'https://analyticsdata.googleapis.com',
			'analyticsadmin' => 'https://analyticsadmin.googleapis.com',
			// Added for Google Business Profile (round 3).
			'gbpaccount'     => 'https://mybusinessaccountmanagement.googleapis.com',
			'gbpinfo'        => 'https://mybusinessbusinessinformation.googleapis.com',
			'gbpreviews'     => 'https://mybusiness.googleapis.com',
			'gbpperf'        => 'https://businessprofileperformance.googleapis.com',
		);
		$url  = isset( $urls[ $name ] ) ? $urls[ $name ] : '';

		/**
		 * Google endpoint URL. For tests only.
		 *
		 * @param string $url  URL.
		 * @param string $name auth|token|revoke|api.
		 */
		return (string) apply_filters( 'qcld_wpbot_core/google_endpoint', $url, $name );
	}

	/**
	 * Redirect URI the owner adds to their OAuth client.
	 *
	 * @return string
	 */
	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=' . self::CALLBACK_ACTION );
	}

	/**
	 * Whether a Client ID and secret are saved.
	 *
	 * @return bool
	 */
	public static function has_client() {
		$google = self::load();
		return '' !== self::field( $google, 'client_id' ) && '' !== self::secret( $google, 'client_secret' );
	}

	/**
	 * Whether a refresh token is stored.
	 *
	 * @return bool
	 */
	public static function connected() {
		return self::has_client() && '' !== self::secret( self::load(), 'refresh_token' );
	}

	/**
	 * Connection state for status strips: connected, failing, not_connected.
	 *
	 * @return array{status: string, message: string}
	 */
	public static function status() {
		$google = self::load();
		if ( ! self::connected() ) {
			return array(
				'status'  => 'not_connected',
				'message' => self::has_client() ? __( 'Google client saved. Click Connect to give read-only access.', 'chatbot' ) : __( 'Not connected.', 'chatbot' ),
			);
		}
		$error = self::field( $google, 'error' );
		if ( '' !== $error ) {
			return array(
				'status'  => 'failing',
				'message' => $error,
			);
		}
		return array(
			'status'  => 'connected',
			'message' => '',
		);
	}

	/**
	 * Save the OAuth client and the Search Console property.
	 *
	 * @param array $changes client_id, client_secret, clear (bool), property.
	 * @return array Public view.
	 */
	public static function update( array $changes ) {
		$all    = self::load_all();
		$google = isset( $all['google'] ) ? (array) $all['google'] : array();
		if ( ! empty( $changes['clear'] ) ) {
			self::revoke( $google );
			$google = array();
		}
		if ( isset( $changes['client_id'] ) && '' !== trim( (string) $changes['client_id'] ) ) {
			$id = (string) preg_replace( '/[^A-Za-z0-9._\-]/', '', (string) $changes['client_id'] );
			if ( self::field( $google, 'client_id' ) !== $id ) {
				// A new client cannot use the old client's tokens.
				unset( $google['refresh_token'], $google['access_token'], $google['expires'], $google['error'] );
			}
			$google['client_id'] = $id;
		}
		if ( isset( $changes['client_secret'] ) && '' !== trim( (string) $changes['client_secret'] ) ) {
			$google['client_secret'] = Secret_Box::encrypt( (string) preg_replace( '/[^\x21-\x7E]/', '', (string) $changes['client_secret'] ) );
		}
		if ( isset( $changes['property'] ) ) {
			$google['property'] = self::clean_property( (string) $changes['property'] );
		}
		if ( isset( $changes['ga_property'] ) ) {
			$ga                    = (string) $changes['ga_property'];
			$google['ga_property'] = preg_match( '/^properties\/[0-9]{1,20}$/', $ga ) ? $ga : '';
		}
		$all['google'] = $google;
		update_option( Stock_Keys::OPTION, $all, false );
		return self::public_view();
	}

	/**
	 * Masked view for settings screens.
	 *
	 * @return array
	 */
	public static function public_view() {
		$google = self::load();
		$status = self::status();
		$id     = self::field( $google, 'client_id' );
		return array(
			'client_id'    => $id,
			'has_secret'   => '' !== self::secret( $google, 'client_secret' ),
			'secret_hint'  => Secret_Box::mask( self::secret( $google, 'client_secret' ) ),
			'connected'    => self::connected(),
			'status'       => $status['status'],
			'message'      => $status['message'],
			'property'     => self::field( $google, 'property' ),
			'redirect_uri' => self::redirect_uri(),
			'connected_at' => self::field( $google, 'connected_at' ),
			'ga_property'  => self::field( $google, 'ga_property' ),
			'analytics'    => self::has_scope( self::SCOPE_ANALYTICS ),
			'missing'      => self::missing_scopes(),
		);
	}

	/**
	 * Google consent URL. Stores a one-time state for the current user.
	 *
	 * @param string $return_url Admin URL to come back to.
	 * @return string|WP_Error
	 */
	public static function auth_url( $return_url = '' ) {
		$google = self::load();
		if ( ! self::has_client() ) {
			return new WP_Error( 'qcld_wpbot_core_google_no_client', __( 'Save your Google Client ID and secret first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$state = wp_generate_password( 32, false );
		set_transient(
			'qcld_wpbot_core_google_state_' . get_current_user_id(),
			array(
				'state'  => $state,
				'return' => wp_validate_redirect( $return_url, admin_url() ),
			),
			15 * MINUTE_IN_SECONDS
		);
		return add_query_arg(
			array(
				'client_id'              => rawurlencode( self::field( $google, 'client_id' ) ),
				'redirect_uri'           => rawurlencode( self::redirect_uri() ),
				'response_type'          => 'code',
				'scope'                  => rawurlencode( implode( ' ', self::scopes() ) ),
				'access_type'            => 'offline',
				'prompt'                 => 'consent',
				'include_granted_scopes' => 'true',
				'state'                  => $state,
			),
			self::endpoint( 'auth' )
		);
	}

	/**
	 * Google redirects here with a code (or an error). Administrators only;
	 * the one-time state protects against forged requests.
	 *
	 * @return void
	 */
	public static function callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect Google.', 'chatbot' ), '', array( 'response' => 403 ) );
		}
		$key    = 'qcld_wpbot_core_google_state_' . get_current_user_id();
		$stored = get_transient( $key );
		delete_transient( $key );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Google's redirect; checked against the one-time state.
		$state  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code   = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$denied = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable
		if ( ! is_array( $stored ) || '' === $state || ! hash_equals( (string) $stored['state'], $state ) ) {
			wp_die( esc_html__( 'This Google sign-in link has expired. Go back and click Connect again.', 'chatbot' ), '', array( 'response' => 400 ) );
		}
		$result = '' !== $denied
			? new WP_Error( 'qcld_wpbot_core_google_denied', __( 'Google access was not given.', 'chatbot' ) )
			: self::exchange( $code );
		$return = add_query_arg( 'qcld_google', is_wp_error( $result ) ? 'error' : 'connected', (string) $stored['return'] );
		if ( is_wp_error( $result ) ) {
			set_transient( 'qcld_wpbot_core_google_notice_' . get_current_user_id(), $result->get_error_message(), 5 * MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( $return );
		exit;
	}

	/**
	 * Last connect error for the current user (read once).
	 *
	 * @return string
	 */
	public static function take_notice() {
		$key     = 'qcld_wpbot_core_google_notice_' . get_current_user_id();
		$message = (string) get_transient( $key );
		delete_transient( $key );
		return $message;
	}

	/**
	 * Swap an authorization code for tokens.
	 *
	 * @param string $code Code.
	 * @return true|WP_Error
	 */
	public static function exchange( $code ) {
		$google = self::load();
		if ( '' === $code ) {
			return new WP_Error( 'qcld_wpbot_core_google_no_code', __( 'Google did not send an authorization code.', 'chatbot' ) );
		}
		$tokens = self::token_request(
			array(
				'code'          => $code,
				'client_id'     => self::field( $google, 'client_id' ),
				'client_secret' => self::secret( $google, 'client_secret' ),
				'redirect_uri'  => self::redirect_uri(),
				'grant_type'    => 'authorization_code',
			)
		);
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}
		if ( empty( $tokens['refresh_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_google_no_refresh', __( 'Google did not give lasting access. Remove this site\'s app from your Google account\'s third-party access and connect again.', 'chatbot' ) );
		}
		self::store_tokens( $tokens, true );
		return true;
	}

	/**
	 * A valid access token (refreshed when it expires).
	 *
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$google = self::load();
		if ( ! self::connected() ) {
			return new WP_Error( 'qcld_wpbot_core_google_not_connected', __( 'Google is not connected.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$token = self::secret( $google, 'access_token' );
		if ( '' !== $token && (int) self::field( $google, 'expires' ) > time() + 60 ) {
			return $token;
		}
		$tokens = self::token_request(
			array(
				'refresh_token' => self::secret( $google, 'refresh_token' ),
				'client_id'     => self::field( $google, 'client_id' ),
				'client_secret' => self::secret( $google, 'client_secret' ),
				'grant_type'    => 'refresh_token',
			)
		);
		if ( is_wp_error( $tokens ) ) {
			self::set_error( $tokens->get_error_message() );
			return $tokens;
		}
		self::store_tokens( $tokens, false );
		return (string) $tokens['access_token'];
	}

	/**
	 * Authorized GET or POST to a Google API path.
	 *
	 * @param string     $path   Path after https://www.googleapis.com.
	 * @param array|null $body   JSON body for POST, null for GET.
	 * @param string     $base Endpoint name: api (default), searchconsole, analyticsdata or analyticsadmin.
	 * @param bool       $mark Record a 401/403 as a connection error (default true). Optional APIs
	 *                         (YouTube) pass false, so a refusal there never flags Search Console.
	 * @return array|WP_Error Decoded JSON.
	 */
	public static function api( $path, $body = null, $base = 'api', $mark = true ) {
		return self::request( null === $body ? 'GET' : 'POST', $path, $body, $base, $mark );
	}

	/**
	 * Call a Google API with any method (round 3: PUT for a review reply).
	 *
	 * @param string     $method GET, POST, PUT or DELETE.
	 * @param string     $path   Path after the endpoint.
	 * @param array|null $body   JSON body, or null.
	 * @param string     $base   Endpoint name (see endpoint()).
	 * @param bool       $mark   Record a 401/403 as a connection error.
	 * @return array|WP_Error Decoded JSON.
	 */
	public static function request( $method, $path, $body = null, $base = 'api', $mark = true ) {
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args   = array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		);
		$url    = self::endpoint( in_array( $base, array( 'api', 'searchconsole', 'analyticsdata', 'analyticsadmin', 'gbpaccount', 'gbpinfo', 'gbpreviews', 'gbpperf' ), true ) ? $base : 'api' ) . $path;
		$method = in_array( strtoupper( (string) $method ), array( 'GET', 'POST', 'PUT', 'DELETE' ), true ) ? strtoupper( (string) $method ) : 'GET';
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$args['method'] = $method;
		$response       = wp_remote_request( $url, $args );
		$data           = self::decode( $response );
		if ( is_wp_error( $data ) ) {
			$code = (int) $data->get_error_data()['status'];
			if ( $mark && in_array( $code, array( 401, 403 ), true ) ) {
				self::set_error( $data->get_error_message() );
			}
			return $data;
		}
		if ( $mark ) {
			self::set_error( '' );
		}
		return $data;
	}

	/**
	 * Revoke access and forget the tokens (the client stays saved).
	 *
	 * @return array Public view.
	 */
	public static function disconnect() {
		$all    = self::load_all();
		$google = isset( $all['google'] ) ? (array) $all['google'] : array();
		self::revoke( $google );
		unset( $google['refresh_token'], $google['access_token'], $google['expires'], $google['error'], $google['connected_at'], $google['scopes'] );
		$all['google'] = $google;
		update_option( Stock_Keys::OPTION, $all, false );
		return self::public_view();
	}

	/**
	 * Saved Search Console property ('' when none).
	 *
	 * @return string
	 */
	public static function property() {
		return self::field( self::load(), 'property' );
	}

	/**
	 * Saved Google Analytics 4 property ("properties/123"), or ''.
	 *
	 * @return string
	 */
	public static function ga_property() {
		return self::field( self::load(), 'ga_property' );
	}

	/**
	 * Clean a property id: a URL-prefix property or sc-domain:host.
	 *
	 * @param string $property Raw.
	 * @return string
	 */
	public static function clean_property( $property ) {
		$property = trim( $property );
		if ( 0 === strpos( $property, 'sc-domain:' ) ) {
			$host = strtolower( (string) preg_replace( '/[^A-Za-z0-9.\-]/', '', substr( $property, 10 ) ) );
			return '' !== $host ? 'sc-domain:' . $host : '';
		}
		$url = esc_url_raw( $property, array( 'http', 'https' ) );
		return '' !== $url ? trailingslashit( $url ) : '';
	}

	/**
	 * POST to the token endpoint.
	 *
	 * @param array $body Form fields.
	 * @return array|WP_Error
	 */
	private static function token_request( array $body ) {
		$response = wp_remote_post(
			self::endpoint( 'token' ),
			array(
				'timeout' => 20,
				'body'    => $body,
			)
		);
		$data     = self::decode( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_google_bad_token', __( 'Google did not return an access token.', 'chatbot' ), array( 'status' => 502 ) );
		}
		return $data;
	}

	/**
	 * Decode a Google response, turning errors into owner-readable messages.
	 *
	 * @param array|WP_Error $response Response.
	 * @return array|WP_Error
	 */
	private static function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'qcld_wpbot_core_google_unreachable', __( 'Google could not be reached. Check that this server can make outgoing connections, then try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : array();
		if ( $code >= 200 && $code < 300 ) {
			return $data;
		}
		$reason = '';
		if ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
			$reason = $data['error'];
		} elseif ( isset( $data['error']['status'] ) ) {
			$reason = (string) $data['error']['status'];
		}
		switch ( true ) {
			case in_array( $reason, array( 'invalid_client', 'unauthorized_client' ), true ):
				$message = __( 'Google rejected the Client ID or secret. Copy both again from Google Cloud > APIs & Services > Credentials.', 'chatbot' );
				break;
			case 'invalid_grant' === $reason:
				$message = __( 'Google access has expired or was removed. Click Connect again.', 'chatbot' );
				break;
			case 'redirect_uri_mismatch' === $reason:
				$message = __( 'The redirect URI in your Google client does not match. Copy the redirect URI shown here into the client\'s Authorized redirect URIs.', 'chatbot' );
				break;
			case 403 === $code:
				$message = __( 'Google refused access. Check that the Search Console API (and the Google Analytics Data and Admin APIs, if you use Analytics) are enabled in your Google Cloud project and that your Google account can see this site.', 'chatbot' );
				break;
			case 429 === $code:
				$message = __( 'Google\'s usage limit was reached. Topic Finder tries again later.', 'chatbot' );
				break;
			default:
				/* translators: %d: HTTP status code. */
				$message = sprintf( __( 'Google returned an error (HTTP %d). Try again later.', 'chatbot' ), $code );
		}
		// Google's own reason (for example quotaExceeded, accessNotConfigured), so callers can explain it.
		$detail = '';
		if ( isset( $data['error']['errors'][0]['reason'] ) && is_string( $data['error']['errors'][0]['reason'] ) ) {
			$detail = $data['error']['errors'][0]['reason'];
		} elseif ( isset( $data['error']['details'][0]['reason'] ) && is_string( $data['error']['details'][0]['reason'] ) ) {
			$detail = $data['error']['details'][0]['reason'];
		}
		return new WP_Error(
			'qcld_wpbot_core_google_error',
			$message,
			array(
				'status' => $code ? $code : 502,
				'reason' => sanitize_key( '' !== $detail ? $detail : $reason ),
			)
		);
	}

	/**
	 * Save tokens (encrypted).
	 *
	 * @param array $tokens  Token response.
	 * @param bool  $initial First connection.
	 * @return void
	 */
	private static function store_tokens( array $tokens, $initial ) {
		$all    = self::load_all();
		$google = isset( $all['google'] ) ? (array) $all['google'] : array();
		if ( ! empty( $tokens['refresh_token'] ) ) {
			$google['refresh_token'] = Secret_Box::encrypt( (string) $tokens['refresh_token'] );
		}
		$google['access_token'] = Secret_Box::encrypt( (string) $tokens['access_token'] );
		$google['expires']      = time() + max( 60, isset( $tokens['expires_in'] ) ? (int) $tokens['expires_in'] : 3600 );
		if ( ! empty( $tokens['scope'] ) && is_string( $tokens['scope'] ) ) {
			// Granted scopes, so modules can ask the owner to reconnect for a new one.
			$google['scopes'] = (string) preg_replace( '/[^A-Za-z0-9:\/._ \-]/', '', $tokens['scope'] );
		}
		$google['error'] = '';
		if ( $initial ) {
			$google['connected_at'] = gmdate( 'c' );
		}
		$all['google'] = $google;
		update_option( Stock_Keys::OPTION, $all, false );
	}

	/**
	 * Record (or clear) the last failure so screens show a bold notice.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private static function set_error( $message ) {
		$all = self::load_all();
		if ( ! isset( $all['google'] ) || self::field( (array) $all['google'], 'error' ) === $message ) {
			return;
		}
		$all['google']['error'] = $message;
		update_option( Stock_Keys::OPTION, $all, false );
	}

	/**
	 * Ask Google to revoke the refresh token (best effort).
	 *
	 * @param array $google Stored entry.
	 * @return void
	 */
	private static function revoke( array $google ) {
		$token = self::secret( $google, 'refresh_token' );
		if ( '' !== $token ) {
			wp_remote_post(
				self::endpoint( 'revoke' ),
				array(
					'timeout' => 10,
					'body'    => array( 'token' => $token ),
				)
			);
		}
	}

	/**
	 * Plain stored field.
	 *
	 * @param array  $google Entry.
	 * @param string $name   Field.
	 * @return string
	 */
	private static function field( array $google, $name ) {
		return isset( $google[ $name ] ) && is_scalar( $google[ $name ] ) ? (string) $google[ $name ] : '';
	}

	/**
	 * Decrypted field.
	 *
	 * @param array  $google Entry.
	 * @param string $name   Field.
	 * @return string
	 */
	private static function secret( array $google, $name ) {
		$value = self::field( $google, $name );
		if ( '' === $value ) {
			return '';
		}
		$plain = Secret_Box::decrypt( $value );
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Google entry.
	 *
	 * @return array
	 */
	private static function load() {
		$all = self::load_all();
		return isset( $all['google'] ) && is_array( $all['google'] ) ? $all['google'] : array();
	}

	/**
	 * Whole connections option.
	 *
	 * @return array
	 */
	private static function load_all() {
		$all = get_option( Stock_Keys::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}
}
