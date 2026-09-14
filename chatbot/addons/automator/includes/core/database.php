<?php
/**
 * Database handler for WPbot Automator
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database class
 */
class Database {

	/**
	 * Table names
	 */
	const TABLE_WORKFLOWS        = 'wpbot_automator_workflows';
	const TABLE_LOGS             = 'wpbot_automator_logs';
	const TABLE_EMAIL_TEMPLATES  = 'wpbot_automator_email_templates';
	const TABLE_TABLES           = 'wpbot_automator_tables';
	const TABLE_SCHEDULED_TASKS  = 'wpbot_automator_scheduled_tasks';

	/**
	 * Install database tables
	 */
	public static function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Workflows table.
		$table_workflows = $wpdb->prefix . self::TABLE_WORKFLOWS;
		$sql_workflows   = "CREATE TABLE IF NOT EXISTS {$table_workflows} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			status varchar(20) NOT NULL DEFAULT 'active',
			workflow_data longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status)
		) {$charset_collate};";

		// Email templates table.
		$table_templates = $wpdb->prefix . self::TABLE_EMAIL_TEMPLATES;
		$sql_templates   = "CREATE TABLE IF NOT EXISTS {$table_templates} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			subject varchar(255),
			body_html longtext,
			body_json longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset_collate};";

		// Logs table.
		$table_logs = $wpdb->prefix . self::TABLE_LOGS;
		$sql_logs   = "CREATE TABLE IF NOT EXISTS {$table_logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) unsigned NOT NULL,
			trigger_type varchar(100),
			action_id varchar(100) DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'success',
			log_data longtext,
			error_message text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY workflow_id (workflow_id),
			KEY status (status)
		) {$charset_collate};";

		// Tables meta (stores user-defined table schemas).
		$table_tables = $wpdb->prefix . self::TABLE_TABLES;
		$sql_tables   = "CREATE TABLE IF NOT EXISTS {$table_tables} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(60) NOT NULL,
			columns_json longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		// Scheduled tasks table (used by Delay action).
		$table_scheduled = $wpdb->prefix . self::TABLE_SCHEDULED_TASKS;
		$sql_scheduled   = "CREATE TABLE IF NOT EXISTS {$table_scheduled} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) unsigned NOT NULL,
			trigger_id varchar(100) NOT NULL DEFAULT '',
			trigger_data longtext NOT NULL,
			remaining_nodes longtext NOT NULL,
			connections longtext NOT NULL,
			scheduled_at datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY workflow_id (workflow_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_workflows );
		dbDelta( $sql_logs );
		dbDelta( $sql_templates );
		dbDelta( $sql_tables );
		dbDelta( $sql_scheduled );

		// Store database version.
		update_option( 'wpbot_automator_db_version', WPBOT_AUTOMATOR_VERSION );

		self::seed_default_email_templates();
	}

	/**
	 * Insert one pre-built email template on first install.
	 * Guarded by an option flag so it only runs once.
	 */
	public static function seed_default_email_templates() {
		if ( get_option( 'wpbot_automator_email_templates_seeded' ) ) {
			return;
		}

		global $wpdb;
		$table = self::get_email_templates_table();

		// If the user already has templates (e.g. upgrading), just mark as done.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			update_option( 'wpbot_automator_email_templates_seeded', '1' );
			return;
		}

		$blocks = array(
			array(
				'id'      => 'b1',
				'type'    => 'text',
				'content' => '<h1 style="margin:0 0 8px;font-size:28px;color:#1a1a2e;">Hello, {name}! 👋</h1><p style="margin:0;font-size:16px;color:#555555;">Thank you for being with us. Here\'s a message just for you.</p>',
			),
			array(
				'id'      => 'b2',
				'type'    => 'divider',
				'content' => '',
				'styles'  => array( 'borderTop' => '1px solid #e5e7eb', 'margin' => '20px 0' ),
			),
			array(
				'id'      => 'b3',
				'type'    => 'text',
				'content' => '<p style="font-size:15px;color:#444444;line-height:1.7;">We wanted to reach out with an important update. If you have any questions or need assistance, don\'t hesitate to get in touch — we\'re always here to help.</p>',
			),
			array(
				'id'      => 'b4',
				'type'    => 'button',
				'content' => 'Visit Our Website',
				'styles'  => array(
					'backgroundColor' => '#3b82f6',
					'color'           => '#ffffff',
					'padding'         => '12px 28px',
					'borderRadius'    => '6px',
					'url'             => '#',
				),
			),
			array(
				'id'      => 'b5',
				'type'    => 'divider',
				'content' => '',
				'styles'  => array( 'borderTop' => '1px solid #e5e7eb', 'margin' => '24px 0' ),
			),
			array(
				'id'      => 'b6',
				'type'    => 'text',
				'content' => '<p style="font-size:13px;color:#9ca3af;text-align:center;margin:0;">You\'re receiving this because you\'re a valued customer.<br>© ' . gmdate( 'Y' ) . ' Your Brand. All rights reserved.</p>',
			),
		);

		$wpdb->insert(
			$table,
			array(
				'name'      => 'Welcome Email',
				'subject'   => 'Welcome, {name}!',
				'body_html' => self::render_blocks_to_html( $blocks ),
				'body_json' => wp_json_encode( $blocks ),
			)
		);

		update_option( 'wpbot_automator_email_templates_seeded', '1' );
	}

	/**
	 * Render block array to the same HTML structure the front-end generateHTML() produces.
	 *
	 * @param array $blocks
	 * @return string
	 */
	private static function render_blocks_to_html( array $blocks ) {
		$inner = '';
		foreach ( $blocks as $block ) {
			switch ( $block['type'] ) {
				case 'text':
					$inner .= '<div class="block-text">' . $block['content'] . '</div>';
					break;
				case 'button':
					$s      = $block['styles'];
					$inner .= '<div style="text-align:center;margin:20px 0;">'
						. '<a href="' . esc_attr( $s['url'] ) . '" class="btn" style="'
						. 'background-color:' . esc_attr( $s['backgroundColor'] ) . ';'
						. 'color:' . esc_attr( $s['color'] ) . ';'
						. 'padding:' . esc_attr( $s['padding'] ) . ';'
						. 'border-radius:' . esc_attr( $s['borderRadius'] ) . ';">'
						. esc_html( $block['content'] )
						. '</a></div>';
					break;
				case 'image':
					$inner .= '<div style="margin:20px 0;"><img src="' . esc_attr( $block['content'] ) . '" alt="Image"></div>';
					break;
				case 'divider':
					$inner .= '<hr style="border:none;border-top:' . esc_attr( $block['styles']['borderTop'] ) . ';margin:' . esc_attr( $block['styles']['margin'] ) . ';">';
					break;
				case 'spacer':
					$inner .= '<div style="height:' . esc_attr( $block['styles']['height'] ) . ';"></div>';
					break;
			}
		}

		return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
			. 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;background-color:#f4f7f9;}'
			. '.container{max-width:600px;margin:40px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);}'
			. '.content{padding:40px;}'
			. '.btn{display:inline-block;text-decoration:none;font-weight:bold;text-align:center;}'
			. 'img{max-width:100%;height:auto;display:block;}'
			. '@media only screen and (max-width:600px){.container{margin:0;border-radius:0;}}'
			. '</style></head><body>'
			. '<div class="container"><div class="content">' . $inner . '</div>'
			. '<div style="background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280;border-top:1px solid #f3f4f6;">Sent by Your Website</div>'
			. '</div></body></html>';
	}

	/**
	 * Get workflows table name
	 *
	 * @return string
	 */
	public static function get_workflows_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_WORKFLOWS;
	}

	/**
	 * Get logs table name
	 *
	 * @return string
	 */
	public static function get_logs_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_LOGS;
	}

	/**
	 * Get email templates table name
	 *
	 * @return string
	 */
	public static function get_email_templates_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_EMAIL_TEMPLATES;
	}

	/**
	 * Get tables meta table name
	 *
	 * @return string
	 */
	public static function get_tables_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_TABLES;
	}

	/**
	 * Get scheduled tasks table name.
	 *
	 * @return string
	 */
	public static function get_scheduled_tasks_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SCHEDULED_TASKS;
	}

	/**
	 * Insert a scheduled task checkpoint.
	 *
	 * @param array $data {
	 *   @type int    $workflow_id
	 *   @type string $trigger_id
	 *   @type array  $trigger_data
	 *   @type array  $remaining_nodes
	 *   @type array  $connections
	 *   @type string $scheduled_at  MySQL datetime string.
	 * }
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function add_scheduled_task( $data ) {
		global $wpdb;
		$table = self::get_scheduled_tasks_table();

		$result = $wpdb->insert(
			$table,
			array(
				'workflow_id'     => absint( $data['workflow_id'] ),
				'trigger_id'      => sanitize_text_field( $data['trigger_id'] ),
				'trigger_data'    => wp_json_encode( $data['trigger_data'] ),
				'remaining_nodes' => wp_json_encode( $data['remaining_nodes'] ),
				'connections'     => wp_json_encode( $data['connections'] ),
				'scheduled_at'    => $data['scheduled_at'],
				'status'          => 'pending',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a scheduled task by ID.
	 *
	 * @param int $task_id
	 * @return array|null
	 */
	public static function get_scheduled_task( $task_id ) {
		global $wpdb;
		$table = self::get_scheduled_tasks_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $task_id ) ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row['trigger_data']    = json_decode( $row['trigger_data'], true );
		$row['remaining_nodes'] = json_decode( $row['remaining_nodes'], true );
		$row['connections']     = json_decode( $row['connections'], true );
		return $row;
	}

	/**
	 * Mark a scheduled task as completed.
	 *
	 * @param int $task_id
	 */
	public static function complete_scheduled_task( $task_id ) {
		global $wpdb;
		$table = self::get_scheduled_tasks_table();
		$wpdb->update(
			$table,
			array( 'status' => 'completed' ),
			array( 'id'     => absint( $task_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a scheduled task by ID.
	 *
	 * @param int $task_id
	 */
	public static function delete_scheduled_task( $task_id ) {
		global $wpdb;
		$table = self::get_scheduled_tasks_table();
		$wpdb->delete( $table, array( 'id' => absint( $task_id ) ), array( '%d' ) );
	}

	/**
	 * Run schema upgrades for existing installs (adds columns that didn't exist at activation).
	 */
	public static function maybe_upgrade() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_LOGS;
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
			return;
		}

		self::seed_default_email_templates();

		// Ensure tables meta table exists for existing installs.
		$charset_collate = $wpdb->get_charset_collate();
		$table_tables    = $wpdb->prefix . self::TABLE_TABLES;
		$sql_tables      = "CREATE TABLE IF NOT EXISTS {$table_tables} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(60) NOT NULL,
			columns_json longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_tables );

		// Ensure scheduled tasks table exists for existing installs.
		$table_scheduled = $wpdb->prefix . self::TABLE_SCHEDULED_TASKS;
		$sql_scheduled   = "CREATE TABLE IF NOT EXISTS {$table_scheduled} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) unsigned NOT NULL,
			trigger_id varchar(100) NOT NULL DEFAULT '',
			trigger_data longtext NOT NULL,
			remaining_nodes longtext NOT NULL,
			connections longtext NOT NULL,
			scheduled_at datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY workflow_id (workflow_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) {$charset_collate};";
		dbDelta( $sql_scheduled );

		$col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'action_id'" );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `action_id` varchar(100) DEFAULT '' AFTER `trigger_type`" );
		}
		$key = $wpdb->get_results( "SHOW INDEX FROM `{$table}` WHERE Key_name = 'status'" );
		if ( empty( $key ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `status` (`status`)" );
		}
	}

	/**
	 * Add a log entry
	 *
	 * @param array $data Log data.
	 * @return int|false
	 */
	public static function add_log( $data ) {
		global $wpdb;
		$table = self::get_logs_table();

		$defaults = array(
			'workflow_id'  => 0,
			'trigger_type' => '',
			'action_id'    => '',
			'status'       => 'success',
			'log_data'     => '',
			'error_message' => '',
			'created_at'   => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		// Diagnostic check for non-scalar values before encoding.
		foreach ( array( 'trigger_type', 'status', 'error_message' ) as $key ) {
			if ( ! empty( $data[ $key ] ) && ! is_scalar( $data[ $key ] ) ) {
				//error_log( sprintf( 'WPBOT-AUTOMATOR-DIAGNOSTIC: Non-scalar value detected for field "%s" in add_log. Type: %s. Value: %s', $key, gettype( $data[ $key ] ), wp_json_encode( $data[ $key ] ) ) );
				//error_log( 'BACKTRACE: ' . wp_debug_backtrace_summary() );
			}
		}

		$result = $wpdb->insert(
			$table,
			array(
				'workflow_id'   => absint( $data['workflow_id'] ),
				'trigger_type'  => sanitize_text_field( ! is_scalar( $data['trigger_type'] ) ? wp_json_encode( $data['trigger_type'] ) : strval( $data['trigger_type'] ) ),
				'action_id'     => sanitize_text_field( ! is_scalar( $data['action_id'] ) ? '' : strval( $data['action_id'] ) ),
				'status'        => sanitize_text_field( ! is_scalar( $data['status'] ) ? wp_json_encode( $data['status'] ) : strval( $data['status'] ) ),
				'log_data'      => is_scalar( $data['log_data'] ) ? strval( $data['log_data'] ) : wp_json_encode( $data['log_data'] ),
				'error_message' => sanitize_textarea_field( ! is_scalar( $data['error_message'] ) ? wp_json_encode( $data['error_message'] ) : strval( $data['error_message'] ) ),
				'created_at'    => sanitize_text_field( ! is_scalar( $data['created_at'] ) ? wp_json_encode( $data['created_at'] ) : strval( $data['created_at'] ) ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			//error_log( 'WPbot Automator - Database::add_log failed. Table: ' . $table . '. DB Error: ' . $wpdb->last_error );
		} else {
			//error_log( 'WPbot Automator - Database::add_log success. ID: ' . $wpdb->insert_id );
		}

		return $result ? $wpdb->insert_id : false;
	}
}
