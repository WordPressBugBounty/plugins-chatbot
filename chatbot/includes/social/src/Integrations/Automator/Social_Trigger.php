<?php
/**
 * Automator trigger "WPBot Social › Social post published / failed".
 *
 * Loaded only when Automator is active (its Trigger base class exists).
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Integrations\Automator;

defined( 'ABSPATH' ) || exit;

/**
 * Social trigger for Automator.
 */
class Social_Trigger extends \WPbot_Automator\Triggers\Trigger {

	/**
	 * Build.
	 */
	public function __construct() {
		$this->id    = 'wpbot_social';
		$this->title = __( 'WPBot Social', 'chatbot' );
		$this->group = __( 'WPBot Social', 'chatbot' );
	}

	/**
	 * Listen to Social's events.
	 *
	 * @return void
	 */
	public function register() {
		add_action(
			'qcld_wpbot_social/published',
			function ( $row, $fields ) {
				$this->fire( 'social_post_published', $row, $fields );
			},
			10,
			2
		);
		add_action(
			'qcld_wpbot_social/publish_failed',
			function ( $row, $fields ) {
				$this->fire( 'social_post_failed', $row, $fields );
			},
			10,
			2
		);
	}

	/**
	 * Start the workflows for one event. No tokens or secrets are passed.
	 *
	 * @param string $sub_id Sub-trigger.
	 * @param array  $row    Target row.
	 * @param array  $fields Saved fields.
	 * @return void
	 */
	private function fire( $sub_id, $row, $fields ) {
		$row    = (array) $row;
		$fields = (array) $fields;
		$this->run(
			array(
				'sub_id' => $sub_id,
				'data'   => array(
					'post_id'  => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
					'network'  => isset( $row['network'] ) ? (string) $row['network'] : '',
					'text'     => isset( $row['draft']['text'] ) ? (string) $row['draft']['text'] : '',
					'link'     => isset( $row['draft']['link'] ) ? (string) $row['draft']['link'] : '',
					'post_url' => isset( $fields['remote_url'] ) ? (string) $fields['remote_url'] : '',
					'state'    => isset( $fields['state'] ) ? (string) $fields['state'] : '',
					'reason'   => isset( $fields['error_message'] ) ? (string) $fields['error_message'] : '',
				),
			)
		);
	}
}
