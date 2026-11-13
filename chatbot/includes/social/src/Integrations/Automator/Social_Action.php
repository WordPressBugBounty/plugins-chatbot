<?php
/**
 * Automator action "WPBot Social › Create social post".
 *
 * Loaded only when Automator is active (its Action base class exists).
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Integrations\Automator;

use QuantumCloud\Social\Integrations\Automator;

defined( 'ABSPATH' ) || exit;

/**
 * Social action for Automator.
 */
class Social_Action extends \WPbot_Automator\Actions\Action {

	/**
	 * Build.
	 */
	public function __construct() {
		$this->id    = 'wpbot_social';
		$this->title = __( 'Create social post', 'chatbot' );
		$this->group = __( 'WPBot Social', 'chatbot' );
	}

	/**
	 * Run.
	 *
	 * @param array $action_data Node data (actionId, config).
	 * @param array $trigger_data Data from earlier steps, for {tokens}.
	 * @return array
	 */
	public function execute( $action_data, $trigger_data ) {
		if ( ! isset( $action_data['actionId'] ) || 'social_create_post' !== $action_data['actionId'] ) {
			return false;
		}
		$cfg    = isset( $action_data['config'] ) && is_array( $action_data['config'] ) ? $action_data['config'] : array();
		$value  = function ( $key ) use ( $cfg, $trigger_data ) {
			return isset( $cfg[ $key ] ) ? $this->parse_tokens( (string) $cfg[ $key ], $trigger_data ) : '';
		};
		$result = Automator::create_post(
			array(
				'text'                   => $value( 'text' ),
				'link'                   => $value( 'link' ),
				'title'                  => $value( 'title' ),
				'networks'               => '' !== $value( 'networks' ) ? $value( 'networks' ) : 'all',
				'when'                   => '' !== $value( 'when' ) ? $value( 'when' ) : 'draft',
				'from_tokens'            => Automator::has_tokens( $cfg ),
				'publish_without_review' => isset( $cfg['publish_without_review'] ) && in_array( (string) $cfg['publish_without_review'], array( '1', 'yes', 'true' ), true ),
				'workflow_id'            => isset( $action_data['workflowId'] ) ? (int) $action_data['workflowId'] : ( isset( $trigger_data['workflow_id'] ) ? (int) $trigger_data['workflow_id'] : 0 ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => 'WPBot Social: ' . $result->get_error_message(),
			);
		}
		return array(
			'success' => true,
			'message' => 'WPBot Social post ' . $result['id'] . ' saved (' . $result['status'] . ').',
			'data'    => $result,
		);
	}
}
