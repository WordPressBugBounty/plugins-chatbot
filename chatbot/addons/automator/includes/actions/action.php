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
	 * @param string $string The string containing tokens like {key} or {key.subkey}.
	 * @param array $data The trigger data to pull values from.
	 * @return string
	 */
	protected function parse_tokens( $string, $data ) {
		$result = (string) $string;
		if ( ! is_array( $data ) || empty( $result ) ) {
			return $result;
		}

		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$result = str_replace( '{' . $key . '}', $value, $result );
				$result = str_replace( '{{' . $key . '}}', $value, $result );
			} elseif ( is_array( $value ) ) {
				// Flatten arrays for tokens (e.g., {fields.1.value})
				$flattened = $this->flatten_array( $value, $key );
				foreach ( $flattened as $flat_key => $flat_value ) {
					if ( is_string( $flat_value ) || is_numeric( $flat_value ) ) {
						$result = str_replace( '{' . $flat_key . '}', $flat_value, $result );
						$result = str_replace( '{{' . $flat_key . '}}', $flat_value, $result );
					}
				}
				// Also allow replacing the base key with JSON if it's still present in the result.
				if ( strpos( $result, '{' . $key . '}' ) !== false || strpos( $result, '{{' . $key . '}}' ) !== false ) {
					$json_val = wp_json_encode( $value );
					$result = str_replace( '{' . $key . '}', $json_val, $result );
					$result = str_replace( '{{' . $key . '}}', $json_val, $result );
				}
			}
		}

		//error_log( sprintf( 'WPbot Automator - Token Parse - Input: "%s", Result: "%s"', $string, $result ) );
		return $result;
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
