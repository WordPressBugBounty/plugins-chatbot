<?php
/**
 * Default Workflows Installer
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default_Workflows class
 */
class Default_Workflows {

	/**
	 * Install default workflows
	 */
	public static function install() {
		global $wpdb;
		$table = Database::get_workflows_table();

		// Check if any workflows exist.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( intval( $count ) > 0 ) {
			return; // Workflows already exist, do not overwrite.
		}

		// Define default workflows.
		$workflows = self::get_defaults();

		foreach ( $workflows as $workflow ) {
			$wpdb->insert(
				$table,
				array(
					'name'          => $workflow['name'],
					'description'   => $workflow['description'],
					'workflow_data' => wp_json_encode( Webhook_Secrets::harden( self::normalize( $workflow['workflow_data'] ) ) ),
					// Templates need the user's sheet ID / API key first, so they start switched off.
					'status'        => 'inactive',
				),
				array( '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Convert template data to the shape the builder and runner use:
	 * connections as from/to (not source/target) and an appData entry on every node.
	 *
	 * @param array $data Workflow data.
	 * @return array
	 */
	public static function normalize( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		foreach ( isset( $data['connections'] ) && is_array( $data['connections'] ) ? $data['connections'] : array() as $i => $conn ) {
			if ( ! isset( $conn['from'] ) && isset( $conn['source'] ) ) {
				$data['connections'][ $i ]['from'] = $conn['source'];
			}
			if ( ! isset( $conn['to'] ) && isset( $conn['target'] ) ) {
				$data['connections'][ $i ]['to'] = $conn['target'];
			}
		}
		foreach ( isset( $data['nodes'] ) && is_array( $data['nodes'] ) ? $data['nodes'] : array() as $i => $node ) {
			if ( empty( $node['appData']['id'] ) && ! empty( $node['data']['appId'] ) ) {
				$data['nodes'][ $i ]['appData'] = array( 'id' => $node['data']['appId'] );
			}
		}
		return $data;
	}

	/**
	 * Repair workflows installed by older versions (source/target connections never ran).
	 */
	public static function repair_installed() {
		global $wpdb;
		$table = Database::get_workflows_table();
		$names = wp_list_pluck( self::get_defaults(), 'name' );
		$rows  = $wpdb->get_results( "SELECT id, name, workflow_data FROM {$table} WHERE workflow_data LIKE '%\"source\"%'", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$data = json_decode( $row['workflow_data'], true );
			// Only the bundled defaults, recognised by name and their source/target connections.
			if ( ! is_array( $data ) || ! in_array( $row['name'], $names, true ) || ! self::uses_source_target( $data ) ) {
				continue;
			}
			$fixed = self::normalize( $data );
			if ( $fixed !== $data ) {
				// These never ran before; keep them off until the user configures and enables them.
				$wpdb->update( $table, array( 'workflow_data' => wp_json_encode( $fixed ), 'status' => 'inactive' ), array( 'id' => (int) $row['id'] ), array( '%s', '%s' ), array( '%d' ) );
			}
		}
	}

	/**
	 * Whether any connection lacks from/to but has the old source/target keys.
	 */
	private static function uses_source_target( $data ) {
		foreach ( isset( $data['connections'] ) && is_array( $data['connections'] ) ? $data['connections'] : array() as $conn ) {
			if ( ! isset( $conn['from'] ) && isset( $conn['source'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get default workflow data
	 *
	 * @return array
	 */
	private static function get_defaults() {
		return array(
			array(
				'name'        => 'Contact form to google sheet',
				'description' => 'Submit contact form data to a google sheet.',
				'workflow_data' => array(
					'nodes'       => array(
						array(
							'id'       => 'trigger-1',
							'type'     => 'trigger',
							'position' => array(
								'x' => 100,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'cf7',
								'actionId' => 'cf7_submit',
								'label'    => 'Contact form submitted',
							),
						),
						array(
							'id'       => 'action-1',
							'type'     => 'action',
							'position' => array(
								'x' => 500,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'google_sheets',
								'actionId' => 'add_row',
								'label'    => 'Add row to Google Sheet',
								'config'   => array(
									'sheet_id'  => 'your_google_sheet_id_here',
									'worksheet' => 'Sheet1',
									'row_data'  => array(
										'{field_name_1}',
										'{field_name_2}',
										'{field_name_3}',
									),
								),
							),
						),
					),
					'connections' => array(
						array(
							'id'     => 'conn-1',
							'source' => 'trigger-1',
							'target' => 'action-1',
						),
					),
				),
			),
			array(
				'name'        => 'WPBot Session to Google Sheet',
				'description' => 'Automatically save WPBot chat session details (Name, Email, Phone) to a Google Sheet.',
				'workflow_data' => array(
					'nodes'       => array(
						array(
							'id'       => 'trigger-1',
							'type'     => 'trigger',
							'position' => array(
								'x' => 100,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'wpbot',
								'actionId' => 'wpbot_chat_session_saved',
								'label'    => 'WPBot Chat Session Saved',
							),
						),
						array(
							'id'       => 'action-1',
							'type'     => 'action',
							'position' => array(
								'x' => 500,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'google_sheets',
								'actionId' => 'add_row',
								'label'    => 'Add row to Google Sheet',
								'config'   => array(
									'sheet_id'  => 'your_google_sheet_id_here',
									'worksheet' => 'Sheet1',
									'row_data'  => array(
										'{{wpbot.wpbot_chat_session_saved.session_id}}',
										'{{wpbot.wpbot_chat_session_saved.name}}',
										'{{wpbot.wpbot_chat_session_saved.email}}',
										'{{wpbot.wpbot_chat_session_saved.phone}}',
									),
								),
							),
						),
					),
					'connections' => array(
						array(
							'id'     => 'conn-1',
							'source' => 'trigger-1',
							'target' => 'action-1',
						),
					),
				),
			),
			array(
				'name'        => 'WPBot AI Email Extractor to Google Sheets',
				'description' => 'Chat sessions are sent to AI for analysis to extract email addresses, which are then saved in a Google Sheet.',
				'workflow_data' => array(
					'nodes'       => array(
						array(
							'id'       => 'trigger-1',
							'type'     => 'trigger',
							'position' => array(
								'x' => 100,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'wpbot',
								'actionId' => 'wpbot_chat_session_saved',
								'label'    => 'WPBot Chat Session Saved',
							),
						),
						array(
							'id'       => 'action-1',
							'type'     => 'action',
							'position' => array(
								'x' => 420,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'openai',
								'actionId' => 'chat_completion',
								'label'    => 'Extract Emails',
								'config'   => array(
									'model'       => 'gpt-4o-mini',
									'prompt'      => "Extract any email addresses from the following chat transcript. If none are found, return 'No Email'. Return ONLY the email addresses comma-separated, with no other text.\n\nTranscript: {{wpbot.wpbot_chat_session_saved.conversation}}",
									'max_tokens'  => '100',
									'temperature' => '0.1',
								),
							),
						),
						array(
							'id'       => 'action-2',
							'type'     => 'action',
							'position' => array(
								'x' => 740,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'google_sheets',
								'actionId' => 'add_row',
								'label'    => 'Add row to Google Sheet',
								'config'   => array(
									'sheet_id'  => 'your_google_sheet_id_here',
									'worksheet' => 'Sheet1',
									'row_data'  => array(
										'{{wpbot.wpbot_chat_session_saved.session_id}}',
										'{{openai.chat_completion.response}}',
									),
								),
							),
						),
					),
					'connections' => array(
						array(
							'id'     => 'conn-1',
							'source' => 'trigger-1',
							'target' => 'action-1',
						),
						array(
							'id'     => 'conn-2',
							'source' => 'action-1',
							'target' => 'action-2',
						),
					),
				),
			),
			array(
				'name'        => 'Conversational Form to Sheet, AI & Email',
				'description' => 'Saves conversational form data to Google Sheets, generates an AI summary, and emails the response.',
				'workflow_data' => array(
					'nodes'       => array(
						array(
							'id'       => 'trigger-1',
							'type'     => 'trigger',
							'position' => array(
								'x' => 100,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'wpbot',
								'actionId' => 'conversationalforms_submit',
								'label'    => 'Conversational Forms Submitted',
							),
						),
						array(
							'id'       => 'action-1',
							'type'     => 'action',
							'position' => array(
								'x' => 400,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'google_sheets',
								'actionId' => 'add_row',
								'label'    => 'Add row to Google Sheet',
								'config'   => array(
									'sheet_id'  => 'your_google_sheet_id_here',
									'worksheet' => 'Sheet1',
									'row_data'  => array(
										'{{wpbot.conversationalforms_submit.name}}',
										'{{wpbot.conversationalforms_submit.email}}',
									),
								),
							),
						),
						array(
							'id'       => 'action-2',
							'type'     => 'action',
							'position' => array(
								'x' => 700,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'openai',
								'actionId' => 'chat_completion',
								'label'    => 'Generate Summary',
								'config'   => array(
									'model'       => 'gpt-4o-mini',
									'prompt'      => "Summarize the following form submission: Name: {{wpbot.conversationalforms_submit.name}}, Email: {{wpbot.conversationalforms_submit.email}}",
									'max_tokens'  => '150',
									'temperature' => '0.7',
								),
							),
						),
						array(
							'id'       => 'action-3',
							'type'     => 'action',
							'position' => array(
								'x' => 1000,
								'y' => 100,
							),
							'data'     => array(
								'appId'    => 'mail',
								'actionId' => 'send_email',
								'label'    => 'Send Email Response',
								'config'   => array(
									'to_email'    => '{{wpbot.conversationalforms_submit.email}}',
									'subject'     => 'Thank you for your submission',
									'body'        => "Hello {{wpbot.conversationalforms_submit.name}},\n\nHere is a summary of your submission:\n{{openai.chat_completion.response}}",
									'is_html'     => false,
								),
							),
						),
					),
					'connections' => array(
						array(
							'id'     => 'conn-1',
							'source' => 'trigger-1',
							'target' => 'action-1',
						),
						array(
							'id'     => 'conn-2',
							'source' => 'action-1',
							'target' => 'action-2',
						),
						array(
							'id'     => 'conn-3',
							'source' => 'action-2',
							'target' => 'action-3',
						),
					),
				),
			),
		);
	}
}
