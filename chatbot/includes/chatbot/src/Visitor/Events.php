<?php
/**
 * Chat events (shared contract C-5): one list of event types with one payload shape, kept in
 * {prefix}qcld_wpbot_chat_events and announced on qcld_wpbot_chat/event. Store Insights and the daily
 * store metrics read them; the live chat and the Start Menu add theirs.
 *
 * Payload: id, type, time (UTC), visitor, session, user, channel, object_type, object_id, value,
 * data (array). No personal data: emails and names never go in data.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Visitor;

defined( 'ABSPATH' ) || exit;

/**
 * Event list and recorder.
 */
final class Events {

	/**
	 * Days events are kept.
	 */
	const KEEP_DAYS = 180;

	/**
	 * Event types and what their object and value mean.
	 *
	 * @return array<string,array{object: string, value: string, label: string}>
	 */
	public static function types() {
		$types = array(
			// Chat.
			'knowledge_gap'    => array(
				'object' => '',
				'value'  => '',
				'label'  => 'Question without an answer',
			),
			'handoff'          => array(
				'object' => 'rule',
				'value'  => '',
				'label'  => 'Handed to a person (data: reason)',
			),
			'menu_click'       => array(
				'object' => 'page',
				'value'  => '',
				'label'  => 'Start menu item clicked (object: the page; data: item, place)',
			),
			'typed_instead'    => array(
				'object' => '',
				'value'  => '',
				'label'  => 'Typed instead of using the menu',
			),
			'livechat_closed'  => array(
				'object' => 'agent',
				'value'  => 'rating',
				'label'  => 'Live chat closed (data: resolved)',
			),
			// Store.
			'product_view'     => array(
				'object' => 'product',
				'value'  => '',
				'label'  => 'Product viewed (data: store, source)',
			),
			'product_search'   => array(
				'object' => '',
				'value'  => 'results',
				'label'  => 'Product search (data: words)',
			),
			'add_to_cart'      => array(
				'object' => 'product',
				'value'  => 'quantity',
				'label'  => 'Added to cart (data: store, source)',
			),
			'checkout_start'   => array(
				'object' => '',
				'value'  => 'cart value',
				'label'  => 'Checkout started (data: store)',
			),
			'finder_no_match'  => array(
				'object' => 'finder',
				'value'  => '',
				'label'  => 'Product finder found nothing (data: answers)',
			),
			'finder_result'    => array(
				'object' => 'finder',
				'value'  => 'products shown',
				'label'  => 'Product finder showed products (data: answers)',
			),
			'offer_shown'      => array(
				'object' => 'offer',
				'value'  => 'discount',
				'label'  => 'Offer shown (data: automation, variant)',
			),
			'order_attributed' => array(
				'object' => 'order',
				'value'  => 'net total',
				'label'  => 'Order credited (data: store, touch: bot, agent, finder, automation)',
			),
		);
		/**
		 * Event types (other modules add theirs).
		 *
		 * @param array $types Types.
		 */
		return (array) apply_filters( 'qcld_wpbot_chat/event_types', $types );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'qcld_wpbot_chat/knowledge_gap', array( __CLASS__, 'on_knowledge_gap' ), 20, 2 );
		add_action( 'qcld_wpbot_chat_events_prune', array( __CLASS__, 'prune' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'qcld_wpbot_chat_events_prune' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'qcld_wpbot_chat_events_prune' );
		}
	}

	/**
	 * The event shape with defaults.
	 *
	 * @param string $type    Type.
	 * @param array  $payload object_type, object_id, value, data, visitor, session, user, channel.
	 * @return array
	 */
	public static function shape( $type, array $payload = array() ) {
		$data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
		unset( $data['email'], $data['name'], $data['phone'] );
		return array(
			'type'        => sanitize_key( (string) $type ),
			'time'        => isset( $payload['time'] ) ? (string) $payload['time'] : gmdate( 'Y-m-d H:i:s' ),
			'visitor'     => isset( $payload['visitor'] ) ? (string) $payload['visitor'] : Visitor::key(),
			'session'     => isset( $payload['session'] ) ? substr( (string) $payload['session'], 0, 128 ) : '',
			'user'        => isset( $payload['user'] ) ? (int) $payload['user'] : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 ),
			'channel'     => isset( $payload['channel'] ) ? sanitize_key( (string) $payload['channel'] ) : 'web',
			'object_type' => isset( $payload['object_type'] ) ? sanitize_key( (string) $payload['object_type'] ) : '',
			'object_id'   => isset( $payload['object_id'] ) ? max( 0, (int) $payload['object_id'] ) : 0,
			'value'       => isset( $payload['value'] ) ? round( (float) $payload['value'], 4 ) : 0.0,
			'data'        => $data,
		);
	}

	/**
	 * Records an event and announces it on qcld_wpbot_chat/event.
	 *
	 * @param string $type    Type (one of types()).
	 * @param array  $payload See shape().
	 * @return array|null The event, or null for an unknown type.
	 */
	public static function emit( $type, array $payload = array() ) {
		global $wpdb;
		if ( ! isset( self::types()[ $type ] ) ) {
			return null;
		}
		$event = self::shape( $type, $payload );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::events_table(),
			array(
				'type'        => $event['type'],
				'created_at'  => $event['time'],
				'visitor_key' => $event['visitor'],
				'session_id'  => $event['session'],
				'user_id'     => $event['user'],
				'channel'     => $event['channel'],
				'object_type' => $event['object_type'],
				'object_id'   => $event['object_id'],
				'value'       => $event['value'],
				'data'        => $event['data'] ? wp_json_encode( $event['data'] ) : null,
			)
		);
		$event['id'] = (int) $wpdb->insert_id;
		/**
		 * A chat event happened (contract C-5).
		 *
		 * @param array $event Event.
		 */
		do_action( 'qcld_wpbot_chat/event', $event );
		return $event;
	}

	/**
	 * Counts events by type and day.
	 *
	 * @param string $type  Type.
	 * @param string $since Y-m-d (UTC).
	 * @param string $until Y-m-d (UTC), inclusive.
	 * @return array<string,int> Y-m-d => count.
	 */
	public static function daily_counts( $type, $since, $until ) {
		global $wpdb;
		$table = Schema::events_table();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS n FROM {$table} WHERE type = %s AND created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) GROUP BY DATE(created_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$type,
				$since . ' 00:00:00',
				$until
			),
			ARRAY_A
		);
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['day'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * The engine's knowledge gaps become events too.
	 *
	 * @param string $question Question.
	 * @param string $reason   Reason.
	 * @return void
	 */
	public static function on_knowledge_gap( $question, $reason = '' ) {
		self::emit(
			'knowledge_gap',
			array(
				'data' => array(
					'reason' => sanitize_key( (string) $reason ),
					'words'  => function_exists( 'mb_substr' ) ? mb_substr( (string) $question, 0, 200 ) : substr( (string) $question, 0, 200 ),
				),
			)
		);
	}

	/**
	 * Deletes events older than KEEP_DAYS (filter qcld_wpbot_chat/events_keep_days).
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;
		$days  = max( 30, (int) apply_filters( 'qcld_wpbot_chat/events_keep_days', self::KEEP_DAYS ) );
		$table = Schema::events_table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 5000", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
