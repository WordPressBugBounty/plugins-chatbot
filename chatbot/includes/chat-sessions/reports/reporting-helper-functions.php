<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/******************************************
 * Get all conversations
 ******************************************/
function botreports_get_all_conversations() {
	global $wpdb;

	$tableUser         = $wpdb->prefix . 'wpbot_user';
	$tableConversation = $wpdb->prefix . 'wpbot_conversation';

	$preparedSqlStatement = $wpdb->prepare(
		'SELECT * FROM %i JOIN %i ON %i.id = %i.user_id',
		$tableUser,
		$tableConversation,
		$tableUser,
		$tableConversation
	);

	$results = $wpdb->get_results( $preparedSqlStatement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return $results;
}

/******************************************
 * Get Latest 5 Conversations
 ******************************************/
function botreports_get_last5_conversations() {
	global $wpdb;

	$tableUser         = $wpdb->prefix . 'wpbot_user';
	$tableConversation = $wpdb->prefix . 'wpbot_conversation';

	$preparedSqlStatement = $wpdb->prepare(
		'SELECT * FROM %i users JOIN %i conversations ON users.id = conversations.user_id ORDER BY users.date DESC LIMIT %d',
		$tableUser,
		$tableConversation,
		5
	);

	$results = $wpdb->get_results( $preparedSqlStatement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return $results;
}

/******************************************
 * Get total conversation count
 ******************************************/
function botreports_get_total_conversation_count() {
	global $wpdb;

	$tableConversation = $wpdb->prefix . 'wpbot_conversation';

	$preparedSqlStatement = $wpdb->prepare( 'SELECT count(*) FROM %i', $tableConversation );

	$count = $wpdb->get_var( $preparedSqlStatement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return (int) $count;
}

function wpbot_get_report_stats_count() {
	global $wpdb;
	$table = $wpdb->prefix . 'wpbot_chat_report';

	return array(
		'likes'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE feedback = 'like'", $table ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		'dislikes'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE feedback = 'dislike'", $table ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		'total_feedback' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE feedback IS NOT NULL', $table ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		'total_reports'  => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		'reports_only'   => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE feedback IS NULL', $table ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	);
}

function wpbot_get_reports_list( $limit = 20 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'wpbot_chat_report';

	// Fetch reports only (exclude feedback rows).
	$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			'SELECT id, message, meta_info, created_at FROM %i WHERE feedback IS NULL ORDER BY created_at DESC LIMIT %d',
			$table,
			$limit
		),
		ARRAY_A
	);

	// Try to extract shopper email from meta_info if stored
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

	$tableUser = $wpdb->prefix . 'wpbot_user';

	$preparedSqlStatement = $wpdb->prepare(
		'SELECT * FROM %i as user WHERE user.date >= CURDATE() AND user.date < CURDATE() + INTERVAL 1 DAY',
		$tableUser
	);

	$wpdb->get_results( $preparedSqlStatement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return (int) $wpdb->num_rows;
}

/******************************************
 * Get This weeks conversation count
 ******************************************/
function botreports_get_weeks_conversation_count() {
	global $wpdb;

	$tableUser = $wpdb->prefix . 'wpbot_user';

	$preparedSqlStatement = $wpdb->prepare(
		'SELECT * FROM %i as user WHERE user.date >= CURDATE() AND user.date < CURDATE() + INTERVAL %d DAY',
		$tableUser,
		6
	);

	$wpdb->get_results( $preparedSqlStatement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return (int) $wpdb->num_rows;
}

/******************************************
 * Get last 30 days conversation count
 ******************************************/
function botreports_get_last30days_conversation_count() {
	global $wpdb;

	$tableUser = $wpdb->prefix . 'wpbot_user';

	$preparedSqlStatement = $wpdb->prepare(
		'SELECT * FROM %i as user WHERE user.date >= CURDATE() - INTERVAL %d DAY',
		$tableUser,
		30
	);

	$wpdb->get_results( $preparedSqlStatement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return (int) $wpdb->num_rows;
}

/******************************************
 * Average daily conversation count in last 30 days
 ******************************************/
function botreports_get_last30days_conversation_average() {
	global $wpdb;

	$tableUser = $wpdb->prefix . 'wpbot_user';

	$preparedSqlStatement = $wpdb->prepare(
		'SELECT * FROM %i as user WHERE user.date >= CURDATE() - INTERVAL %d DAY',
		$tableUser,
		30
	);

	$wpdb->get_results( $preparedSqlStatement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return round( $wpdb->num_rows / 30 );
}

/******************************************
 * Conversation Density in last 30 days
 ******************************************/
function botreports_get_last30days_conversation_density() {
	global $wpdb;

	$tableUser = $wpdb->prefix . 'wpbot_user';

	$preparedSqlStatement = $wpdb->prepare(
		'SELECT substring(date,1,10) as CONVERSATION_DATE, COUNT(*) as CONVERSATION_NUM FROM %i WHERE date >= CURDATE() - INTERVAL %d DAY GROUP BY CONVERSATION_DATE',
		$tableUser,
		30
	);

	$results = $wpdb->get_results( $preparedSqlStatement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return $results;
}

/******************************************
 * Find busiest period of the day
 ******************************************/
function botreports_get_busiest_period() {
	global $wpdb;

	$tableUser = $wpdb->prefix . 'wpbot_user';

	$preparedSqlStatement = $wpdb->prepare(
		"SELECT DATE_FORMAT(date,'%%H') as hours, count(*) as count FROM %i GROUP BY hours ORDER BY count DESC LIMIT 1",
		$tableUser
	);

	$results = $wpdb->get_results( $preparedSqlStatement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

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
