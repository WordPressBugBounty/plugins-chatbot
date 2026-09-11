<?php
/**
 * WPBot Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPBot_Actions
 */
class WPBot_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'wpbot';
		$this->group = __( 'WPBot', 'wpbot-automator' );
	}

	/**
	 * Get available actions
	 */
	public static function get_actions() {
		return array(
			'delete_session' => array(
				'title'       => __( 'Delete Chat Session', 'wpbot-automator' ),
				'description' => __( 'Deletes a specific chat session from the database.', 'wpbot-automator' ),
				'fields'      => array(
					array(
						'id'          => 'session_id',
						'type'        => 'text',
						'label'       => __( 'Session ID', 'wpbot-automator' ),
						'description' => __( 'The ID of the session to delete.', 'wpbot-automator' ),
						'required'    => true,
					),
				),
			),
		);
	}

	/**
	 * Execute the action
	 * 
	 * @param array $action_data Action data.
	 * @param array $trigger_data Trigger data.
	 * @return array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = $action_data['actionId'];
		$config    = isset( $action_data['config'] ) ? $action_data['config'] : array();

		switch ( $action_id ) {
			case 'delete_session':
				return $this->delete_session( $config, $trigger_data );
			case 'get_chat_sessions':
				return $this->get_chat_sessions( $config, $trigger_data );
		}

		return array(
			'success' => false,
			'message' => __( 'Action not found', 'wpbot-automator' ),
		);
	}

	/**
	 * Delete a chat session
	 */
	private function delete_session( $config, $trigger_data ) {
		$session_id = $this->parse_tags( $config['session_id'], $trigger_data );

		if ( empty( $session_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Session ID is required.', 'wpbot-automator' ),
			);
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpbot_user';
		
		$result = $wpdb->delete(
			$table_name,
			array( 'session_id' => $session_id ),
			array( '%s' )
		);

		if ( $result !== false ) {
			return array(
				'success' => true,
				'message' => sprintf( __( 'Chat session %s deleted successfully.', 'wpbot-automator' ), $session_id ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to delete chat session.', 'wpbot-automator' ),
		);
	}

	/**
	 * Get chat sessions by date
	 */
	private function get_chat_sessions( $config, $trigger_data ) {
		$start_date_raw = $this->parse_tags( $config['start_date'], $trigger_data );
		$end_date_raw   = $this->parse_tags( $config['end_date'], $trigger_data );

		if ( empty( $start_date_raw ) || empty( $end_date_raw ) ) {
			return array(
				'success' => false,
				'message' => __( 'Start Date and End Date are required.', 'wpbot-automator' ),
			);
		}

		// Convert human-readable dates to timestamps
		$start_time = strtotime( $start_date_raw );
		$end_time   = strtotime( $end_date_raw );
		
		if ( ! $start_time || ! $end_time ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid date format provided.', 'wpbot-automator' ),
			);
		}
		
		// Format to Y-m-d H:i:s
		$start_date = date( 'Y-m-d 00:00:00', $start_time );
		$end_date   = date( 'Y-m-d 23:59:59', $end_time );

		global $wpdb;
		$user_table = $wpdb->prefix . 'wpbot_user';
		$conv_table = $wpdb->prefix . 'wpbot_conversation';

		// Join user table with conversation table using user_id
		$query = $wpdb->prepare(
			"SELECT u.session_id, u.name, u.email, u.phone, u.date, c.conversation 
			 FROM {$user_table} u 
			 LEFT JOIN {$conv_table} c ON u.user_id = c.user_id 
			 WHERE u.date >= %s AND u.date <= %s",
			$start_date,
			$end_date
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			return array(
				'success' => true, // Not an error, just empty
				'sessions' => array(),
				'message' => __( 'No chat sessions found for the specified date range.', 'wpbot-automator' ),
			);
		}

		return array(
			'success'  => true,
			'sessions' => $results,
		);
	}
}
