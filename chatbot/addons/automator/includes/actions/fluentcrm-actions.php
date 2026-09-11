<?php
/**
 * FluentCRM Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FluentCRM_Actions
 */
class FluentCRM_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'fluentcrm';
		$this->group = __( 'FluentCRM', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 *
	 * @param array $action_data Configuration for this action from the workflow.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return bool|array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'create_contact':
				return $this->create_contact( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	/**
	 * Create or Update Contact in FluentCRM
	 */
	private function create_contact( $action_data, $trigger_data ) {
		if ( ! function_exists( 'FluentCrmApi' ) ) {
			return array(
				'success' => false,
				'message' => 'FluentCRM plugin is not active.',
			);
		}

		$cfg   = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$email = trim( $this->parse_tokens( $cfg['email'] ?? '', $trigger_data ) );

		if ( empty( $email ) ) {
			error_log( 'WPbot Automator - FluentCRM: Email is empty after parsing tokens.' );
			return array(
				'success' => false,
				'message' => 'Email address is required for FluentCRM contact creation.',
			);
		}

		if ( ! is_email( $email ) ) {
			error_log( 'WPbot Automator - FluentCRM: Invalid email address: ' . $email );
			return array(
				'success' => false,
				'message' => 'Invalid email address provided for FluentCRM.',
			);
		}

		$contact_data = array(
			'email'      => $email,
			'first_name' => $this->parse_tokens( $cfg['first_name'] ?? '', $trigger_data ),
			'last_name'  => $this->parse_tokens( $cfg['last_name'] ?? '', $trigger_data ),
			'status'     => $cfg['status'] ?? 'subscribed',
		);

		error_log( 'WPbot Automator - FluentCRM Contact Data: ' . wp_json_encode( $contact_data ) );

		try {
			$api = FluentCrmApi( 'contacts' );
			error_log( 'WPbot Automator - FluentCRM API object type: ' . gettype( $api ) );
			if ( is_object( $api ) ) {
				error_log( 'WPbot Automator - FluentCRM API class: ' . get_class( $api ) );
			}

			$contact = $api->createOrUpdate( $contact_data );
			error_log( 'WPbot Automator - FluentCRM createOrUpdate result: ' . ( is_object( $contact ) ? 'OBJECT (ID: ' . $contact->id . ')' : ( $contact ? 'TRUE/POS' : 'FALSE/NEG' ) ) );

			if ( $contact ) {
				return array(
					'success' => true,
					'message' => sprintf( 'Contact %s successfully created/updated in FluentCRM.', $email ),
					'fluentcrm_contact_id' => isset( $contact->id ) ? $contact->id : 0,
				);
			}
		} catch ( \Exception $e ) {
			error_log( 'WPbot Automator - FluentCRM Exception: ' . $e->getMessage() );
			return array(
				'success' => false,
				'message' => 'FluentCRM Error: ' . $e->getMessage(),
			);
		}

		return array(
			'success' => false,
			'message' => 'Failed to create contact in FluentCRM.',
		);
	}
}
