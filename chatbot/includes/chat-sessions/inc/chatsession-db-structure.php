<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange

/******************************************
 * DB Install
 ******************************************/

global $wpdb;

$collate = '';

if ( $wpdb->has_cap( 'collation' ) ) {

	if ( ! empty( $wpdb->charset ) ) {

		$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
	}
	if ( ! empty( $wpdb->collate ) ) {

		$collate .= " COLLATE $wpdb->collate";

	}
}

$table1 = $wpdb->prefix . 'wpbot_user';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table1 ) ) ) != $table1 ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$sql_sliders_Table1 = "
		CREATE TABLE IF NOT EXISTS `$table1` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`session_id` varchar(256) NOT NULL,
		`name` varchar(256) NOT NULL,
		`email` varchar(256) NOT NULL,
		`phone` varchar(256) NOT NULL,
		`date` datetime NOT NULL,
		`user_id` int(11) NOT NULL,
		`interaction` int(11) NOT NULL,
		PRIMARY KEY (`id`)
		)  $collate AUTO_INCREMENT=1 ";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_sliders_Table1 );
}

$table1 = $wpdb->prefix . 'wpbot_conversation';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table1 ) ) ) != strtolower( $table1 ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$sql_sliders_Table1 = "
		CREATE TABLE IF NOT EXISTS `$table1` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`user_id` int(11) NOT NULL,
		`conversation` LONGTEXT NOT NULL,
		`interaction` int(11) NOT NULL,
		`environment_info` LONGTEXT NOT NULL,
		PRIMARY KEY (`id`)
		)  $collate AUTO_INCREMENT=1 ";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_sliders_Table1 );
}

$table_failed_response = $wpdb->prefix . 'wpbot_failed_response';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_failed_response ) ) ) != $table_failed_response ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$sql_failed_response = "
		CREATE TABLE IF NOT EXISTS `$table_failed_response` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`query` varchar(256) NOT NULL,
		`count` int(11) NOT NULL,
		`status` int(11) NOT NULL,
		PRIMARY KEY (`id`)
		)  $collate AUTO_INCREMENT=1 ";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_failed_response );
}

/******************************************
 * Helper Function: Check if a Database Column Exists
 */
if ( ! function_exists( 'qcwp_isset_table_column' ) ) {

	function qcwp_isset_table_column( $table_name, $column_name ) {
		global $wpdb;

		$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

		foreach ( $columns as $column ) {
			if ( $column['Field'] == $column_name ) {
				return true;
			}
		}
	}
}

/******************************************
 * When the main plugin is activated,
 * Build the necessary DB Structure.
 */
register_activation_hook( __FILE__, 'qcld_wb_chatboot_sessions_defualt_options' );

function qcld_wb_chatboot_sessions_defualt_options() {
	global $wpdb;

	$collate = '';

	if ( $wpdb->has_cap( 'collation' ) ) {

		if ( ! empty( $wpdb->charset ) ) {

			$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {

			$collate .= " COLLATE $wpdb->collate";

		}
	}

	// Create Table: wpbot_user

	$table1 = $wpdb->prefix . 'wpbot_user';

	$sql_sliders_Table1 = "
		CREATE TABLE IF NOT EXISTS `$table1` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `session_id` varchar(256) NOT NULL,
          `name` varchar(256) NOT NULL,
          `email` varchar(256) NOT NULL,
		  `phone` varchar(256) NOT NULL,
		  `date` datetime NOT NULL,
		  `user_id` int(11) NOT NULL,
		  `interaction` int(11) NOT NULL,
		  PRIMARY KEY (`id`)
		)  $collate AUTO_INCREMENT=1 ";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql_sliders_Table1 );

	if ( ! qcwp_isset_table_column( $table1, 'phone' ) ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD `phone` varchar(256) NOT NULL', $table1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	// Create Table: wpbot_conversation

	$table2 = $wpdb->prefix . 'wpbot_conversation';

	$sql_sliders_Table2 = "
		CREATE TABLE IF NOT EXISTS `$table2` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `user_id` int(11) NOT NULL,
		  `conversation` LONGTEXT NOT NULL,
		  `interaction` int(11) NOT NULL,
		  `environment_info` LONGTEXT NOT NULL,
		  PRIMARY KEY (`id`)
		)  $collate AUTO_INCREMENT=1 ";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql_sliders_Table2 );

	if ( ! qcwp_isset_table_column( $table2, 'interaction' ) ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD `interaction` int(11) NOT NULL', $table2 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	if ( ! qcwp_isset_table_column( $table1, 'interaction' ) ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD `interaction` int(11) NOT NULL', $table1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
}

/******************************************
 * Update DB Structure, If Necessary
 ******************************************/
function qcldwpbot_chatsession_db_update_check() {

	global $wpdb;

	$version = get_option( 'wpbot_chatsession_db_version', '1.0' );

	$table1 = $wpdb->prefix . 'wpbot_user';

	$table2 = $wpdb->prefix . 'wpbot_conversation';

	if ( version_compare( $version, '2.0' ) < 0 ) {

		if ( ! qcwp_isset_table_column( $table1, 'interaction' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD `interaction` int(11) NOT NULL', $table1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		if ( ! qcwp_isset_table_column( $table2, 'interaction' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD `interaction` int(11) NOT NULL', $table2 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		if ( ! qcwp_isset_table_column( $table2, 'environment_info' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD `environment_info` LONGTEXT NOT NULL', $table2 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		update_option( 'wpbot_chatsession_db_version', '2.0' );

	}
}

add_action( 'plugins_loaded', 'qcldwpbot_chatsession_db_update_check' );
