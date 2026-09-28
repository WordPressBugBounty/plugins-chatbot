<?php
/**
 * Filters / Condition Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Filters_Actions
 *
 * Evaluates a single condition and either allows the workflow branch to
 * continue (success: true) or halts it (success: false).
 */
class Filters_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'filters';
		$this->group = __( 'Filters', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 *
	 * @param array $action_data Node configuration.
	 * @param array $trigger_data Accumulated workflow data.
	 * @return array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( 'filter_condition' === $action_id ) {
			return $this->filter_condition( $action_data, $trigger_data );
		}

		// Unknown action — pass through.
		return array( 'success' => true, 'message' => 'Filter passed (unknown action).' );
	}

	/**
	 * Evaluate a single condition.
	 */
	private function filter_condition( $action_data, $trigger_data ) {
		$config   = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$field    = isset( $config['field'] )    ? $this->parse_tokens( $config['field'],    $trigger_data ) : '';
		$operator = isset( $config['operator'] ) ? trim( $config['operator'] ) : 'equals';
		$value    = isset( $config['value'] )    ? $this->parse_tokens( $config['value'],    $trigger_data ) : '';

		$passed = $this->evaluate( $field, $operator, $value );

		// error_log( sprintf(
		// 	'WPbot Automator - Filter: field="%s" operator="%s" value="%s" => %s',
		// 	$field,
		// 	$operator,
		// 	$value,
		// 	$passed ? 'PASS' : 'FAIL'
		// ) );

		if ( $passed ) {
			return array( 'success' => true, 'message' => 'Filter condition passed.' );
		}

		// Return success: false — the runner will stop this branch.
		return array( 'success' => false, 'message' => 'Filter condition not met.' );
	}

	/**
	 * Evaluate the condition.
	 *
	 * @param string $field    The resolved left-hand value.
	 * @param string $operator Comparison operator.
	 * @param string $value    The right-hand comparison value.
	 * @return bool
	 */
	private function evaluate( $field, $operator, $value ) {
		$field_lower = strtolower( trim( $field ) );
		$value_lower = strtolower( trim( $value ) );

		switch ( $operator ) {
			case 'equals':
			case '==':
				return $field_lower === $value_lower;

			case 'not_equals':
			case '!=':
				return $field_lower !== $value_lower;

			case 'contains':
				return strpos( $field_lower, $value_lower ) !== false;

			case 'not_contains':
				return strpos( $field_lower, $value_lower ) === false;

			case 'greater_than':
			case '>':
				return is_numeric( $field ) && is_numeric( $value ) && floatval( $field ) > floatval( $value );

			case 'less_than':
			case '<':
				return is_numeric( $field ) && is_numeric( $value ) && floatval( $field ) < floatval( $value );

			case 'is_true':
				// Accept: true, "true", "1", 1, "yes"
				return in_array( $field_lower, array( 'true', '1', 'yes' ), true );

			case 'is_false':
				return in_array( $field_lower, array( 'false', '0', 'no', '' ), true );

			case 'is_empty':
				return '' === trim( $field );

			case 'is_not_empty':
				return '' !== trim( $field );

			default:
				// Unknown operator — pass through so the workflow continues.
				//error_log( 'WPbot Automator - Filter: unknown operator "' . $operator . '", defaulting to pass.' );
				return true;
		}
	}
}
