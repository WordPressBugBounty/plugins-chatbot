<?php
/**
 * admin-ajax.php router for the WPBot AI pages.
 *
 * Every endpoint is a `wp_ajax_qcld_wpbot_ai_seo_{action}` hook. The browser posts:
 *   action  = qcld_wpbot_ai_seo_{action}
 *   nonce   = Ajax::NONCE nonce
 *   payload = JSON object with the endpoint's parameters (keeps types: arrays, booleans, numbers)
 *
 * Controllers are unchanged: each request is turned into a WP_REST_Request so the
 * declared args (defaults, required, sanitize/validate callbacks, types) are applied
 * exactly as before, then the controller callback runs.
 *
 * Responses use the standard wp_send_json_success() / wp_send_json_error() envelope.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qcld_Wpbot_Ai_Seo_Ajax {

	/**
	 * Nonce action shared by all endpoints.
	 */
	public const NONCE = 'qcld_wpbot_ai_seo_ajax';

	/**
	 * Action prefix.
	 */
	public const PREFIX = 'qcld_wpbot_ai_seo_';

	/**
	 * Registered endpoints, keyed by full action name.
	 *
	 * @var array<string,array>
	 */
	private static array $endpoints = array();

	/**
	 * Register one endpoint.
	 *
	 * @param string $action  Action name without prefix (e.g. 'content_articles_list').
	 * @param string $method  Logical method (GET, POST, PUT, DELETE) — passed to the controller.
	 * @param string $route   Route pattern; named groups like (?P<id>\d+) are read from the payload.
	 * @param array  $handler callback, permission_callback, args (same shape as register_rest_route).
	 */
	public static function add( string $action, string $method, string $route, array $handler ): void {
		$full = self::PREFIX . $action;

		self::$endpoints[ $full ] = array(
			'method'              => strtoupper( $method ),
			'route'               => $route,
			'callback'            => $handler['callback'],
			'permission_callback' => $handler['permission_callback'] ?? array( __CLASS__, 'admin_permission' ),
			'args'                => $handler['args'] ?? array(),
		);

		add_action(
			'wp_ajax_' . $full,
			static function () use ( $full ) {
				self::dispatch( $full );
			}
		);
	}

	/**
	 * Default capability check.
	 *
	 * @return bool
	 */
	public static function admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle one AJAX request.
	 *
	 * @param string $action Full action name.
	 */
	private static function dispatch( string $action ): void {
		$endpoint = self::$endpoints[ $action ] ?? null;
		if ( ! $endpoint ) {
			self::fail( 'qcld_wpbot_ai_seo_unknown_action', __( 'Unknown request.', 'chatbot' ), 400 );
		}

		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			self::fail( 'qcld_wpbot_ai_seo_invalid_nonce', __( 'Your session has expired. Reload the page and try again.', 'chatbot' ), 403 );
		}

		$allowed = call_user_func( $endpoint['permission_callback'] );
		if ( is_wp_error( $allowed ) ) {
			self::fail_wp_error( $allowed, 403 );
		}
		if ( ! $allowed ) {
			self::fail( 'qcld_wpbot_ai_seo_forbidden', __( 'Sorry, you are not allowed to do that.', 'chatbot' ), 403 );
		}

		$data = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above with check_ajax_referer().
		if ( isset( $_POST['payload'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; every field is sanitized by the endpoint args / controller.
			$decoded = json_decode( wp_unslash( $_POST['payload'] ), true );
			$data    = is_array( $decoded ) ? $decoded : array();
		}

		// URL params (e.g. id) come from the payload and must match the route pattern.
		$url_params = array();
		$path       = $endpoint['route'];
		if ( preg_match_all( '/\(\?P<(\w+)>([^)]+)\)/', $endpoint['route'], $groups, PREG_SET_ORDER ) ) {
			foreach ( $groups as $group ) {
				$name  = $group[1];
				$value = isset( $data[ $name ] ) && is_scalar( $data[ $name ] ) ? (string) $data[ $name ] : '';
				if ( ! preg_match( '/^(?:' . $group[2] . ')$/', $value ) ) {
					/* translators: %s: parameter name. */
					self::fail( 'qcld_wpbot_ai_seo_invalid_param', sprintf( __( 'Invalid parameter: %s', 'chatbot' ), $name ), 400 );
				}
				$url_params[ $name ] = $value;
				$path                = str_replace( $group[0], $value, $path );
				unset( $data[ $name ] );
			}
		}

		$request = new \WP_REST_Request( $endpoint['method'], '/' . qcld_wpbot_ai_seo_rest_namespace() . $path );
		$request->set_url_params( $url_params );
		if ( 'GET' === $endpoint['method'] ) {
			$request->set_query_params( $data );
		} else {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( (object) $data ) );
			$request->get_json_params(); // Parse now so sanitize_params() sees the JSON params.
		}
		$request->set_attributes( array( 'args' => $endpoint['args'] ) );

		$defaults = array();
		foreach ( $endpoint['args'] as $key => $arg ) {
			if ( is_array( $arg ) && array_key_exists( 'default', $arg ) ) {
				$defaults[ $key ] = $arg['default'];
			}
		}
		$request->set_default_params( $defaults );

		$valid = $request->has_valid_params();
		if ( is_wp_error( $valid ) ) {
			self::fail_wp_error( $valid, 400 );
		}
		$sanitized = $request->sanitize_params();
		if ( is_wp_error( $sanitized ) ) {
			self::fail_wp_error( $sanitized, 400 );
		}

		$result = null;
		try {
			$result = call_user_func( $endpoint['callback'], $request );
		} catch ( \Throwable $e ) {
			self::fail( 'qcld_wpbot_ai_seo_exception', $e->getMessage(), 500 );
		}

		if ( is_wp_error( $result ) ) {
			self::fail_wp_error( $result, 400 );
		}

		$response = rest_ensure_response( $result );
		if ( $response->is_error() ) {
			// Controllers often return array( 'success' => false, 'message' => ... ) with a 4xx/5xx
			// status. WP_REST_Response::as_error() expects 'code'/'message' keys and would warn,
			// so read the body ourselves.
			$body = $response->get_data();
			$body = is_array( $body ) ? $body : array();
			self::fail(
				isset( $body['code'] ) ? (string) $body['code'] : '',
				self::error_message( $body ),
				(int) $response->get_status(),
				isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array()
			);
		}

		$status = (int) $response->get_status();
		wp_send_json_success( $response->get_data(), $status >= 200 && $status < 300 ? $status : 200 );
	}

	/**
	 * User-facing message for a failed controller response.
	 *
	 * Services report the real cause in 'error' (e.g. "Scheduled publish date
	 * must be in the future", the provider's "invalid API key"), while some
	 * controllers put only a generic summary in 'message'. Show both: the
	 * summary first, then the cause.
	 *
	 * @param array $body Response body.
	 */
	private static function error_message( array $body ): string {
		$message = isset( $body['message'] ) && is_scalar( $body['message'] ) ? trim( (string) $body['message'] ) : '';
		$detail  = isset( $body['error'] ) && is_scalar( $body['error'] ) ? trim( (string) $body['error'] ) : '';

		if ( '' !== $detail && false === strpos( $message, $detail ) ) {
			$message = '' !== $message ? $message . ' ' . $detail : $detail;
		}

		return '' !== $message ? $message : __( 'An error occurred.', 'chatbot' );
	}

	/**
	 * Send a WP_Error as a JSON error.
	 *
	 * @param \WP_Error $error   Error.
	 * @param int       $default Fallback HTTP status.
	 */
	private static function fail_wp_error( \WP_Error $error, int $default ): void {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && ! empty( $data['status'] ) ? (int) $data['status'] : $default;
		$extra  = is_array( $data ) ? $data : array();
		unset( $extra['status'] );

		self::fail( (string) $error->get_error_code(), $error->get_error_message(), $status, $extra );
	}

	/**
	 * Send a JSON error and stop.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message shown to the user.
	 * @param int    $status  HTTP status.
	 * @param array  $data    Extra data.
	 */
	private static function fail( string $code, string $message, int $status, array $data = array() ): void {
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
				'data'    => $data,
			),
			$status
		);
	}
}
