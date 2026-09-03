<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Escape a database table name for use in SQL queries.
 *
 * @param string $table_name Table name.
 * @return string
 */
function qcld_chatbot_sql_table( $table_name ) {
	return '`' . esc_sql( $table_name ) . '`';
}

/******************************************
 * Get all conversations
 ******************************************/
function botreports_get_all_conversations() {
	global $wpdb;

	$table_user_sql         = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_user' );
	$table_conversation_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_conversation' );

	$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		"SELECT * FROM {$table_user_sql} JOIN {$table_conversation_sql} ON {$table_user_sql}.id = {$table_conversation_sql}.user_id"
	);

	return $results;
}

/******************************************
 * Get Latest 5 Conversations
 ******************************************/
function botreports_get_last5_conversations() {
	global $wpdb;

	$table_user_sql         = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_user' );
	$table_conversation_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_conversation' );

	$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT * FROM {$table_user_sql} users JOIN {$table_conversation_sql} conversations ON users.id = conversations.user_id ORDER BY users.date DESC LIMIT %d",
			5
		)
	);

	return $results;
}

/******************************************
 * Get total conversation count
 ******************************************/
function botreports_get_total_conversation_count() {
	global $wpdb;

	$table_conversation_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_conversation' );

	$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		"SELECT count(*) FROM {$table_conversation_sql}"
	);

	return (int) $count;
}

function wpbot_get_report_stats_count() {
	global $wpdb;
	$table_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_chat_report' );

	return array(
		'likes'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_sql} WHERE feedback = %s", 'like' ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		'dislikes'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_sql} WHERE feedback = %s", 'dislike' ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		'total_feedback' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_sql} WHERE feedback IS NOT NULL" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		'total_reports'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_sql}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		'reports_only'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_sql} WHERE feedback IS NULL" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	);
}

function wpbot_get_reports_list( $limit = 20 ) {
	global $wpdb;
	$table_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_chat_report' );

	$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT id, message, meta_info, created_at FROM {$table_sql} WHERE feedback IS NULL ORDER BY created_at DESC LIMIT %d",
			$limit
		),
		ARRAY_A
	);

	foreach ( $results as &$row ) {
		$email = '';
		if ( preg_match( '/Email:\s*([^\s]+)/i', $row['meta_info'], $matches ) ) {
			$email = sanitize_email( $matches[1] );
		}

		$row['email'] = $email ?: 'Unknown';
	}

	return $results;
}

/******************************************
 * Get today's conversation count
 ******************************************/
function botreports_get_todays_conversation_count() {
	global $wpdb;

	$table_user_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_user' );

	$wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		"SELECT * FROM {$table_user_sql} AS user WHERE user.date >= CURDATE() AND user.date < CURDATE() + INTERVAL 1 DAY"
	);

	return (int) $wpdb->num_rows; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

/******************************************
 * Get This weeks conversation count
 ******************************************/
function botreports_get_weeks_conversation_count() {
	global $wpdb;

	$table_user_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_user' );

	$wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT * FROM {$table_user_sql} AS user WHERE user.date >= CURDATE() AND user.date < CURDATE() + INTERVAL %d DAY",
			6
		)
	);

	return (int) $wpdb->num_rows; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

/******************************************
 * Get last 30 days conversation count
 ******************************************/
function botreports_get_last30days_conversation_count() {
	global $wpdb;

	$table_user_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_user' );

	$wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT * FROM {$table_user_sql} AS user WHERE user.date >= CURDATE() - INTERVAL %d DAY",
			30
		)
	);

	return (int) $wpdb->num_rows; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

/******************************************
 * Average daily conversation count in last 30 days
 ******************************************/
function botreports_get_last30days_conversation_average() {
	global $wpdb;

	$table_user_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_user' );

	$wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT * FROM {$table_user_sql} AS user WHERE user.date >= CURDATE() - INTERVAL %d DAY",
			30
		)
	);

	return round( $wpdb->num_rows / 30 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

/******************************************
 * Conversation Density in last 30 days
 ******************************************/
function botreports_get_last30days_conversation_density() {
	global $wpdb;

	$table_user_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_user' );

	$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT substring(date,1,10) as CONVERSATION_DATE, COUNT(*) as CONVERSATION_NUM FROM {$table_user_sql} WHERE date >= CURDATE() - INTERVAL %d DAY GROUP BY CONVERSATION_DATE",
			30
		)
	);

	return $results;
}

/******************************************
 * Find busiest period of the day
 ******************************************/
function botreports_get_busiest_period() {
	global $wpdb;

	$table_user_sql = qcld_chatbot_sql_table( $wpdb->prefix . 'wpbot_user' );

	$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		"SELECT DATE_FORMAT(date,'%H') as hours, count(*) as count FROM {$table_user_sql} GROUP BY hours ORDER BY count DESC LIMIT 1"
	);

	return $results;
}

/******************************************
 * Get user interactions count in a conversation
 ******************************************/
function get_user_interaction_count( $conversation ) {
	if ( ! empty( $conversation ) ) {
		$interaction = (int) substr_count( $conversation, 'wp-chat-user-msg' );

		if ( $interaction == 0 ) {
			$interaction = (int) substr_count( $conversation, 'woo-chat-user-msg' );
		}

		return $interaction;
	}
}
