<?php
/**
 * Abstract Action Class
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Class Action
 */
abstract class Action {

	/**
	 * Action ID
	 * 
	 * @var string
	 */
	protected $id;

	/**
	 * Action Title
	 * 
	 * @var string
	 */
	protected $title;

	/**
	 * Action Group
	 * 
	 * @var string
	 */
	protected $group;

	/**
	 * Get Action ID
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get Action Title
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Get Action Group
	 */
	public function get_group() {
		return $this->group;
	}

	/**
	 * Execute the action
	 * 
	 * @param array $action_data Configuration for this action from the workflow.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return bool|array Success or data to pass to next node.
	 */
	abstract public function execute( $action_data, $trigger_data );

	/**
	 * Parse tokens in string
	 *
	 * Replaces {{key}} and {key} (dot paths such as {fields.1.value} included) in a single pass,
	 * so a value is never re-wrapped in leftover braces. Unknown tokens are left as they are.
	 * With an escape callback, {{{key}}} inserts the value as HTML (filtered by wp_kses_post)
	 * instead of escaping it, e.g. an AI-written HTML email body.
	 *
	 * @param string $string The string containing tokens like {key} or {key.subkey}.
	 * @param array  $data   The trigger data to pull values from.
	 * @param bool|callable $escape true to escape values for a JSON string literal, or a callback
	 *                              (e.g. 'esc_html') applied to each substituted value.
	 * @return string
	 */
	protected function parse_tokens( $string, $data, $escape = false ) {
		$result = (string) $string;
		if ( ! is_array( $data ) || '' === $result || false === strpos( $result, '{' ) ) {
			return $result;
		}

		$values = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$values[ (string) $key ] = (string) $value;
			} elseif ( is_bool( $value ) ) {
				$values[ (string) $key ] = $value ? '1' : '';
			} elseif ( is_array( $value ) ) {
				foreach ( $this->flatten_array( $value, $key ) as $flat_key => $flat_value ) {
					if ( ( is_string( $flat_value ) || is_numeric( $flat_value ) ) && ! isset( $values[ (string) $flat_key ] ) ) {
						$values[ (string) $flat_key ] = (string) $flat_value;
					}
				}
				// The base key of an array resolves to its JSON.
				if ( ! isset( $values[ (string) $key ] ) ) {
					$values[ (string) $key ] = (string) wp_json_encode( $value );
				}
			}
		}

		return preg_replace_callback(
			'/\{\{\{\s*([^{}]+?)\s*\}\}\}|\{\{\s*([^{}]+?)\s*\}\}|\{([^{}\s"\':,]+)\}/',
			function ( $m ) use ( $values, $escape ) {
				$raw = isset( $m[1] ) && '' !== $m[1];
				$key = $raw ? $m[1] : ( isset( $m[2] ) && '' !== $m[2] ? $m[2] : ( isset( $m[3] ) ? $m[3] : '' ) );
				if ( ! array_key_exists( $key, $values ) ) {
					return $m[0];
				}
				if ( true === $escape ) {
					return substr( wp_json_encode( $values[ $key ] ), 1, -1 );
				}
				if ( is_callable( $escape ) ) {
					return $raw ? wp_kses_post( $values[ $key ] ) : (string) call_user_func( $escape, $values[ $key ] );
				}
				return $values[ $key ];
			},
			$result
		);
	}

	/**
	 * HTTP request to a URL built from workflow data (API, Webhook and XML steps).
	 *
	 * Uses wp_safe_remote_request(), which refuses localhost, private networks, cloud metadata
	 * addresses and ports other than 80/443/8080 (SSRF protection). A site that really needs to
	 * call internal services can opt out with the `wpbot_automator_allow_internal_requests` filter,
	 * or allow specific hosts/ports with WordPress's `http_request_host_is_external` /
	 * `http_allowed_safe_ports` filters.
	 *
	 * @param string $url  Request URL.
	 * @param array  $args wp_remote_request() arguments.
	 * @return array|\WP_Error
	 */
	protected function remote_request( $url, $args = array() ) {
		if ( apply_filters( 'wpbot_automator_allow_internal_requests', false, $url, $this->id ) ) {
			return wp_remote_request( $url, $args );
		}
		return wp_safe_remote_request( $url, $args );
	}

	/**
	 * Flatten a multi-dimensional array into a single level with dot notation keys
	 * 
	 * @param array $array The array to flatten.
	 * @param string $prefix Prefix for the keys.
	 * @return array
	 */
	private function flatten_array( $array, $prefix = '' ) {
		$result = array();
		foreach ( $array as $key => $value ) {
			$new_key = $prefix ? $prefix . '.' . $key : $key;
			if ( is_array( $value ) ) {
				$result = array_merge( $result, $this->flatten_array( $value, $new_key ) );
			} else {
				$result[ $new_key ] = $value;
			}
		}
		return $result;
	}
}
