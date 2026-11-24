<?php
/**
 * Agent channel registry and background delivery (plan 3.2: Slack, Telegram and other outgoing
 * calls run in the background, never inside the visitor's request).
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

/**
 * Channels.
 */
final class Channels {

	/**
	 * Background hook.
	 */
	const HOOK = 'qcld_wpbot_live_dispatch';

	/**
	 * Registered channels.
	 *
	 * @return Channel[] id => channel.
	 */
	public static function all() {
		$out = array();
		/**
		 * Agent channels (Slack, Telegram, Teams).
		 *
		 * @param Channel[] $channels Default none.
		 */
		foreach ( (array) apply_filters( 'qcld_wpbot_chat/live_channels', array() ) as $channel ) {
			if ( $channel instanceof Channel ) {
				$out[ $channel->id() ] = $channel;
			}
		}
		return $out;
	}

	/**
	 * Channels that are switched on.
	 *
	 * @return Channel[]
	 */
	public static function enabled() {
		return array_filter(
			self::all(),
			static function ( Channel $channel ) {
				return $channel->enabled();
			}
		);
	}

	/**
	 * Queue an event for the channels (Action Scheduler when present, else a one-off cron event).
	 *
	 * @param int $event_id Event.
	 * @return void
	 */
	public static function queue( $event_id ) {
		if ( ! self::enabled() ) {
			return;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( (int) $event_id ), 'wpbot-live' );
			return;
		}
		wp_schedule_single_event( time(), self::HOOK, array( (int) $event_id ) );
	}

	/**
	 * Deliver an event to every channel that is on (the background job).
	 *
	 * @param int $event_id Event.
	 * @return void
	 */
	public static function dispatch( $event_id ) {
		$event = Events::get( (int) $event_id );
		if ( ! $event ) {
			return;
		}
		$conv = Conversations::get( $event['conversation'] );
		if ( ! $conv ) {
			return;
		}
		foreach ( self::enabled() as $channel ) {
			// What came from this channel (a reply, a claim, a close) is not sent back to it.
			if ( isset( $event['payload']['channel'] ) && $channel->id() === $event['payload']['channel'] ) {
				continue;
			}
			try {
				$channel->handle( $event, $conv );
			} catch ( \Throwable $e ) {
				/**
				 * An agent channel failed to handle an event.
				 *
				 * @param string     $channel Channel id.
				 * @param \Throwable $e       Error.
				 * @param array      $event   Event.
				 */
				do_action( 'qcld_wpbot_chat/live_channel_error', $channel->id(), $e, $event );
			}
		}
	}

	/**
	 * Link a conversation to a remote thread.
	 *
	 * @param int    $conversation_id Conversation.
	 * @param string $channel         Channel id.
	 * @param string $remote_id       Remote thread id.
	 * @return void
	 */
	public static function link( $conversation_id, $channel, $remote_id ) {
		global $wpdb;
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::links(),
			array(
				'conversation_id' => (int) $conversation_id,
				'channel'         => substr( sanitize_key( $channel ), 0, 20 ),
				'remote_id'       => substr( (string) $remote_id, 0, 191 ),
				'created'         => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * The remote thread of a conversation on a channel ('' when there is none).
	 *
	 * @param int    $conversation_id Conversation.
	 * @param string $channel         Channel id.
	 * @return string
	 */
	public static function remote( $conversation_id, $channel ) {
		global $wpdb;
		$table = Schema::links();
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT remote_id FROM {$table} WHERE conversation_id = %d AND channel = %s ORDER BY id DESC LIMIT 1", (int) $conversation_id, $channel ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * The conversation of a remote thread (0 when unknown).
	 *
	 * @param string $channel   Channel id.
	 * @param string $remote_id Remote thread id.
	 * @return int
	 */
	public static function conversation_for( $channel, $remote_id ) {
		global $wpdb;
		$table = Schema::links();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT conversation_id FROM {$table} WHERE channel = %s AND remote_id = %s", $channel, (string) $remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
