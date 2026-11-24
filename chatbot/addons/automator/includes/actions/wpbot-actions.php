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
		$this->group = __( 'WPBot', 'chatbot' );
	}

	/**
	 * Get available actions
	 */
	public static function get_actions() {
		return array(
			'delete_session' => array(
				'title'       => __( 'Delete Chat Session', 'chatbot' ),
				'description' => __( 'Deletes a specific chat session from the database.', 'chatbot' ),
				'fields'      => array(
					array(
						'id'          => 'session_id',
						'type'        => 'text',
						'label'       => __( 'Session ID', 'chatbot' ),
						'description' => __( 'The ID of the session to delete.', 'chatbot' ),
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
			'message' => __( 'Action not found', 'chatbot' ),
		);
	}

	/**
	 * Delete a chat session
	 */
	private function delete_session( $config, $trigger_data ) {
		$session_id = $this->parse_tokens( isset( $config['session_id'] ) ? $config['session_id'] : '', $trigger_data );

		if ( empty( $session_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Session ID is required.', 'chatbot' ),
			);
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpbot_user';
		$conv_table = $wpdb->prefix . 'wpbot_conversation';

		// Conversation rows reference the session row's primary key, not the session_id string.
		$row_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE session_id = %s", $session_id ) );
		if ( empty( $row_ids ) ) {
			return array(
				'success' => false,
				/* translators: %s: chat session id. */
				'message' => sprintf( __( 'Chat session %s not found.', 'chatbot' ), $session_id ),
			);
		}

		foreach ( $row_ids as $row_id ) {
			$wpdb->delete( $conv_table, array( 'user_id' => (int) $row_id ), array( '%d' ) );
		}

		$result = $wpdb->delete(
			$table_name,
			array( 'session_id' => $session_id ),
			array( '%s' )
		);

		if ( $result ) {
			return array(
				'success' => true,
				/* translators: %s: chat session id. */
				'message' => sprintf( __( 'Chat session %s deleted successfully.', 'chatbot' ), $session_id ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to delete chat session.', 'chatbot' ),
		);
	}

	/**
	 * Get chat sessions by date
	 */
	private function get_chat_sessions( $config, $trigger_data ) {
		$start_date_raw = $this->parse_tokens( isset( $config['start_date'] ) ? $config['start_date'] : '', $trigger_data );
		$end_date_raw   = $this->parse_tokens( isset( $config['end_date'] ) ? $config['end_date'] : '', $trigger_data );

		if ( empty( $start_date_raw ) || empty( $end_date_raw ) ) {
			return array(
				'success' => false,
				'message' => __( 'Start Date and End Date are required.', 'chatbot' ),
			);
		}

		// Convert human-readable dates to timestamps
		$start_time = strtotime( $start_date_raw );
		$end_time   = strtotime( $end_date_raw );
		
		if ( ! $start_time || ! $end_time ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid date format provided.', 'chatbot' ),
			);
		}
		
		// Format to Y-m-d H:i:s
		$start_date = gmdate( 'Y-m-d 00:00:00', $start_time );
		$end_date   = gmdate( 'Y-m-d 23:59:59', $end_time );

		global $wpdb;
		$user_table = $wpdb->prefix . 'wpbot_user';
		$conv_table = $wpdb->prefix . 'wpbot_conversation';

		// conversation.user_id holds the session row ID (wpbot_user.id), not the WP user ID.
		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own tables, read once.
			$wpdb->prepare(
				'SELECT u.session_id, u.name, u.email, u.phone, u.date, c.conversation
				 FROM %i u
				 LEFT JOIN %i c ON c.user_id = u.id
				 WHERE u.date >= %s AND u.date <= %s',
				$user_table,
				$conv_table,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array(
				'success' => true, // Not an error, just empty
				'sessions' => array(),
				'message' => __( 'No chat sessions found for the specified date range.', 'chatbot' ),
			);
		}

		return array(
			'success'  => true,
			'sessions' => $results,
		);
	}
}
