<?php
/**
 * Live chat events (plan 3.1): messages, assigned, transferred, closed, notes, rating. Typing is
 * not stored: it lives a few seconds in a transient.
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

/**
 * Events.
 */
final class Events {

	/**
	 * Event types.
	 */
	const TYPES = array( 'queued', 'message', 'assigned', 'transferred', 'closed', 'note', 'rating', 'system', 'reopened' );

	/**
	 * Seconds a typing flag lasts.
	 */
	const TYPING_TTL = 6;

	/**
	 * Add an event: stored, the conversation's last event id moves, the visitor's signal file is
	 * updated and the agent channels get it in the background.
	 *
	 * @param int    $conversation_id Conversation.
	 * @param string $type            Type.
	 * @param string $actor           visitor, agent:<user id>, bot, system or <channel>:<remote user>.
	 * @param array  $payload         Data.
	 * @param bool   $is_private      Agents only (notes).
	 * @return int Event id (0 when not stored).
	 */
	public static function add( $conversation_id, $type, $actor, array $payload = array(), $is_private = false ) {
		global $wpdb;
		$conversation_id = (int) $conversation_id;
		if ( $conversation_id <= 0 || ! in_array( $type, self::TYPES, true ) ) {
			return 0;
		}
		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::events(),
			array(
				'conversation_id' => $conversation_id,
				'type'            => $type,
				'actor'           => substr( (string) $actor, 0, 40 ),
				'private'         => $is_private ? 1 : 0,
				'payload'         => $payload ? wp_json_encode( $payload ) : null,
				'created'         => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( ! $ok ) {
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::conversations(),
			array(
				'last_event_id' => $id,
				'updated'       => $now,
			),
			array( 'id' => $conversation_id ),
			array( '%d', '%s' ),
			array( '%d' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $is_private ) {
			Signal::write( $conversation_id, $id );
		}
		Channels::queue( $id );
		/**
		 * A live chat event was added.
		 *
		 * @param int    $id              Event id.
		 * @param int    $conversation_id Conversation.
		 * @param string $type            Type.
		 * @param array  $payload         Data.
		 */
		do_action( 'qcld_wpbot_chat/live_event', $id, $conversation_id, $type, $payload );
		return $id;
	}

	/**
	 * One event.
	 *
	 * @param int $id Event id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::events();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Events of conversations after an id (oldest first, at most 200).
	 *
	 * @param int[] $conversation_ids Conversations.
	 * @param int   $after            Last event id seen.
	 * @param bool  $with_private     Include agents-only events.
	 * @return array[]
	 */
	public static function after( array $conversation_ids, $after, $with_private = false ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $conversation_ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$table = Schema::events();
		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql   = "SELECT * FROM {$table} WHERE conversation_id IN ({$in}) AND id > %d" . ( $with_private ? '' : ' AND private = 0' ) . ' ORDER BY id ASC LIMIT 200';
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $ids, array( (int) $after ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Placeholders built above.
		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	/**
	 * Event row as an array for the API.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$payload = isset( $row['payload'] ) && null !== $row['payload'] ? json_decode( (string) $row['payload'], true ) : array();
		return array(
			'id'           => (int) $row['id'],
			'conversation' => (int) $row['conversation_id'],
			'type'         => (string) $row['type'],
			'actor'        => (string) $row['actor'],
			'private'      => (bool) (int) $row['private'],
			'payload'      => is_array( $payload ) ? $payload : array(),
			'created'      => (string) $row['created'],
		);
	}

	/**
	 * Mark someone as typing in a conversation.
	 *
	 * @param int    $conversation_id Conversation.
	 * @param string $who             visitor or agent.
	 * @return void
	 */
	public static function typing( $conversation_id, $who ) {
		set_transient( 'qcld_wpbot_chat_live_typing_' . (int) $conversation_id . '_' . ( 'agent' === $who ? 'agent' : 'visitor' ), 1, self::TYPING_TTL );
	}

	/**
	 * Whether someone is typing.
	 *
	 * @param int    $conversation_id Conversation.
	 * @param string $who             visitor or agent.
	 * @return bool
	 */
	public static function is_typing( $conversation_id, $who ) {
		return (bool) get_transient( 'qcld_wpbot_chat_live_typing_' . (int) $conversation_id . '_' . ( 'agent' === $who ? 'agent' : 'visitor' ) );
	}
}
