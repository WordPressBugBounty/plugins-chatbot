<?php
/**
 * Live chat conversations (plan 3.1): one row per human conversation, linked to the bot transcript
 * by session id. Messages are stored in the chat messages table next to the bot's.
 *
 * Statuses: queued (waiting for an agent), open (an agent answers), closed.
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

use QuantumCloud\Chatbot\Sessions\Schema as Sessions_Schema;
use QuantumCloud\Chatbot\Sessions\Session_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Conversations.
 */
final class Conversations {

	/**
	 * Longest message.
	 */
	const MAX_TEXT = 5000;

	/**
	 * Bot messages kept from the visitor's chat window at the handoff.
	 */
	const HISTORY = 40;

	/**
	 * Longest handed-over message.
	 */
	const HISTORY_TEXT = 1000;

	/**
	 * Start a conversation for a chat session (or return the one still running for it).
	 *
	 * @param array $data session_id, visitor_key, channel, department, visitor_name, visitor_email,
	 *                    user_id, page_url, page_title, reason.
	 * @return int Conversation id (0 on failure).
	 */
	public static function start( array $data ) {
		global $wpdb;
		$session = Session_Store::clean_id( isset( $data['session_id'] ) ? (string) $data['session_id'] : '' );
		if ( '' === $session ) {
			return 0;
		}
		$running = self::by_session( $session );
		if ( $running ) {
			return (int) $running['id'];
		}
		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::conversations(),
			array(
				'session_id'    => $session,
				'visitor_key'   => isset( $data['visitor_key'] ) ? substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $data['visitor_key'] ), 0, 64 ) : '',
				'channel'       => isset( $data['channel'] ) ? substr( sanitize_key( (string) $data['channel'] ), 0, 20 ) : 'web',
				'status'        => 'queued',
				'department'    => isset( $data['department'] ) ? substr( sanitize_key( (string) $data['department'] ), 0, 40 ) : '',
				'visitor_name'  => isset( $data['visitor_name'] ) ? substr( sanitize_text_field( (string) $data['visitor_name'] ), 0, 100 ) : '',
				'visitor_email' => isset( $data['visitor_email'] ) && is_email( $data['visitor_email'] ) ? substr( sanitize_email( (string) $data['visitor_email'] ), 0, 190 ) : '',
				'user_id'       => isset( $data['user_id'] ) ? max( 0, (int) $data['user_id'] ) : 0,
				'page_url'      => isset( $data['page_url'] ) ? substr( esc_url_raw( (string) $data['page_url'] ), 0, 255 ) : '',
				'page_title'    => isset( $data['page_title'] ) ? substr( sanitize_text_field( (string) $data['page_title'] ), 0, 255 ) : '',
				'reason'        => isset( $data['reason'] ) ? substr( sanitize_key( (string) $data['reason'] ), 0, 40 ) : '',
				'queued_at'     => $now,
				'created'       => $now,
				'updated'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( ! $ok ) {
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		Events::add( $id, 'queued', 'visitor', array( 'department' => isset( $data['department'] ) ? (string) $data['department'] : '' ) );
		/**
		 * A visitor asked for a person and a conversation started.
		 *
		 * @param int   $id   Conversation id.
		 * @param array $data Start data.
		 */
		do_action( 'qcld_wpbot_chat/live_started', $id, $data );
		return $id;
	}

	/**
	 * A conversation.
	 *
	 * @param int $id Conversation id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::conversations();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * The queued or open conversation of a chat session.
	 *
	 * @param string $session_id Session.
	 * @return array|null
	 */
	public static function by_session( $session_id ) {
		global $wpdb;
		$table = Schema::conversations();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE session_id = %s AND status IN ('queued','open') ORDER BY id DESC LIMIT 1", Session_Store::clean_id( $session_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Conversations an agent sees: queued ones in the agent's departments (all when the agent has
	 * none) and open ones assigned to the agent. Managers see every open conversation.
	 *
	 * @param int      $agent_id    Agent.
	 * @param string[] $departments Agent's departments ([] = all).
	 * @param bool     $all_open    Include other agents' open conversations.
	 * @return array[]
	 */
	public static function for_agent( $agent_id, array $departments = array(), $all_open = false ) {
		global $wpdb;
		$table  = Schema::conversations();
		$queued = "status = 'queued'";
		$args   = array();
		if ( $departments ) {
			$queued = "status = 'queued' AND department IN (" . implode( ',', array_fill( 0, count( $departments ) + 1, '%s' ) ) . ')';
			$args   = array_merge( array_values( $departments ), array( '' ) );
		}
		$open = $all_open ? "status = 'open'" : "status = 'open' AND agent_id = %d";
		if ( ! $all_open ) {
			$args[] = (int) $agent_id;
		}
		$sql  = "SELECT * FROM {$table} WHERE ({$queued}) OR ({$open}) ORDER BY id DESC LIMIT 100";
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Placeholders built above.
		return (array) $rows;
	}

	/**
	 * Closed conversations, newest first (imported classic chats included), optionally searched by
	 * the visitor's name or email.
	 *
	 * @param string $search Search ('' = all).
	 * @param int    $limit  Most rows.
	 * @param int    $offset Offset.
	 * @return array[]
	 */
	public static function history( $search = '', $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = Schema::conversations();
		$limit = max( 1, min( 100, (int) $limit ) );
		if ( '' !== trim( (string) $search ) ) {
			$like = '%' . $wpdb->esc_like( trim( (string) $search ) ) . '%';
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'closed' AND ( visitor_name LIKE %s OR visitor_email LIKE %s ) ORDER BY updated DESC LIMIT %d OFFSET %d", $like, $like, $limit, max( 0, (int) $offset ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'closed' ORDER BY updated DESC LIMIT %d OFFSET %d", $limit, max( 0, (int) $offset ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return (array) $rows;
	}

	/**
	 * Change fields of a conversation.
	 *
	 * @param int   $id     Conversation.
	 * @param array $fields Column => value.
	 * @return bool
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;
		$fields['updated'] = current_time( 'mysql' );
		return false !== $wpdb->update( Schema::conversations(), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Add a message (visitor, agent or system) to a conversation.
	 *
	 * @param int    $id    Conversation.
	 * @param string $role  user (the visitor), agent, system, or bot (an answer while waiting).
	 * @param string $text  Text.
	 * @param string $actor Event actor.
	 * @param array  $extra agent_name, channel, attachments.
	 * @return int Event id (0 when not stored).
	 */
	public static function message( $id, $role, $text, $actor, array $extra = array() ) {
		global $wpdb;
		$conv = self::get( $id );
		$text = trim( wp_kses( (string) $text, array() ) );
		if ( ! $conv || 'closed' === $conv['status'] || '' === $text || ! in_array( $role, array( 'user', 'agent', 'system', 'bot' ), true ) ) {
			return 0;
		}
		$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_TEXT ) : substr( $text, 0, self::MAX_TEXT );
		// Stored next to the bot's messages, whether or not Chat Sessions saves bot conversations.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Sessions_Schema::messages_table(),
			array(
				'session_id' => $conv['session_id'],
				'role'       => $role,
				'channel'    => isset( $extra['channel'] ) ? substr( sanitize_key( (string) $extra['channel'] ), 0, 20 ) : (string) $conv['channel'],
				'source'     => 'live',
				'text'       => $text,
				'payload'    => wp_json_encode(
					array(
						'conversation' => (int) $id,
						'actor'        => (string) $actor,
					)
				),
				'created'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$message_id = (int) $wpdb->insert_id;
		if ( 'agent' === $role && '0000-00-00 00:00:00' === (string) $conv['first_response_at'] ) {
			self::update( $id, array( 'first_response_at' => current_time( 'mysql' ) ) );
		}
		return Events::add(
			$id,
			'message',
			$actor,
			array(
				'message_id' => $message_id,
				'role'       => $role,
				'text'       => $text,
				'agent_name' => isset( $extra['agent_name'] ) ? sanitize_text_field( (string) $extra['agent_name'] ) : '',
				'channel'    => isset( $extra['channel'] ) ? sanitize_key( (string) $extra['channel'] ) : '',
			)
		);
	}

	/**
	 * An agent takes a conversation.
	 *
	 * @param int    $id       Conversation.
	 * @param int    $agent_id Agent (0: someone in an agent channel without a WordPress account).
	 * @param string $actor    Event actor.
	 * @param string $name     Name to show ('' = the agent's display name).
	 * @param string $channel  Agent channel the action came from ('' = the dashboard).
	 * @return bool
	 */
	public static function assign( $id, $agent_id, $actor = '', $name = '', $channel = '' ) {
		$conv = self::get( $id );
		if ( ! $conv || 'closed' === $conv['status'] ) {
			return false;
		}
		self::update(
			$id,
			array(
				'status'   => 'open',
				'agent_id' => (int) $agent_id,
			)
		);
		Events::add(
			$id,
			'assigned',
			'' !== $actor ? $actor : 'agent:' . (int) $agent_id,
			array(
				'agent_id'   => (int) $agent_id,
				'agent_name' => '' !== $name ? sanitize_text_field( $name ) : Agents::name( (int) $agent_id ),
				'channel'    => sanitize_key( $channel ),
			)
		);
		return true;
	}

	/**
	 * Hand a conversation to another agent or department (it waits in the queue for a department).
	 *
	 * @param int    $id         Conversation.
	 * @param int    $agent_id   New agent (0 for a department).
	 * @param string $department New department ('' keeps it).
	 * @param string $actor      Event actor.
	 * @return bool
	 */
	public static function transfer( $id, $agent_id, $department, $actor ) {
		$conv = self::get( $id );
		if ( ! $conv || 'closed' === $conv['status'] ) {
			return false;
		}
		$fields = array(
			'agent_id' => (int) $agent_id,
			'status'   => $agent_id > 0 ? 'open' : 'queued',
		);
		if ( '' !== $department ) {
			$fields['department'] = substr( sanitize_key( $department ), 0, 40 );
		}
		self::update( $id, $fields );
		Events::add(
			$id,
			'transferred',
			$actor,
			array(
				'agent_id'   => (int) $agent_id,
				'agent_name' => $agent_id > 0 ? Agents::name( (int) $agent_id ) : '',
				'department' => isset( $fields['department'] ) ? $fields['department'] : (string) $conv['department'],
			)
		);
		return true;
	}

	/**
	 * Close a conversation.
	 *
	 * @param int    $id    Conversation.
	 * @param string $actor   Event actor (visitor, agent:<id>, system, <channel>:<user>).
	 * @param string $channel Agent channel the action came from ('' = the dashboard or the visitor).
	 * @return bool
	 */
	public static function close( $id, $actor, $channel = '' ) {
		$conv = self::get( $id );
		if ( ! $conv || 'closed' === $conv['status'] ) {
			return false;
		}
		self::update(
			$id,
			array(
				'status'    => 'closed',
				'closed_at' => current_time( 'mysql' ),
			)
		);
		Events::add( $id, 'closed', $actor, '' !== $channel ? array( 'channel' => sanitize_key( $channel ) ) : array() );
		return true;
	}

	/**
	 * The visitor rates a closed or open conversation (1 to 5).
	 *
	 * @param int    $id      Conversation.
	 * @param int    $rating  Rating.
	 * @param string $comment Comment.
	 * @return bool
	 */
	public static function rate( $id, $rating, $comment = '' ) {
		$rating = max( 1, min( 5, (int) $rating ) );
		if ( ! self::get( $id ) ) {
			return false;
		}
		self::update(
			$id,
			array(
				'rating'         => $rating,
				'rating_comment' => substr( sanitize_text_field( (string) $comment ), 0, 255 ),
			)
		);
		Events::add( $id, 'rating', 'visitor', array( 'rating' => $rating ) );
		return true;
	}

	/**
	 * The whole conversation for an agent: the bot's messages before the handoff and the live ones.
	 *
	 * @param array $conv Conversation row.
	 * @return array[] { role, text, source, created }.
	 */
	public static function transcript( array $conv ) {
		$out = array();
		foreach ( Session_Store::messages( (string) $conv['session_id'] ) as $message ) {
			$out[] = array(
				'role'    => isset( $message['role'] ) ? (string) $message['role'] : '',
				'text'    => isset( $message['text'] ) ? (string) $message['text'] : '',
				'source'  => isset( $message['source'] ) ? (string) $message['source'] : '',
				'created' => isset( $message['created'] ) ? (string) $message['created'] : '',
			);
		}
		return $out;
	}

	/**
	 * The bot conversation before the handoff, sent by the visitor's chat window, stored for the
	 * agent when the site has nothing for this session (Chat Sessions off). Only for chats that
	 * reach a person; at most HISTORY messages of HISTORY_TEXT characters, as plain text.
	 *
	 * @param string $session_id Session.
	 * @param array  $history    [ { role: user|bot, text } ], oldest first.
	 * @return int Messages stored.
	 */
	public static function handoff_history( $session_id, array $history ) {
		$session_id = Session_Store::clean_id( (string) $session_id );
		if ( '' === $session_id || ! $history || Session_Store::count( $session_id ) > 0 ) {
			return 0;
		}
		$stored = 0;
		foreach ( array_slice( array_values( $history ), -self::HISTORY ) as $item ) {
			$item = is_array( $item ) ? $item : array();
			$role = isset( $item['role'] ) && 'user' === $item['role'] ? 'user' : 'bot';
			$text = trim( wp_strip_all_tags( isset( $item['text'] ) ? (string) $item['text'] : '' ) );
			$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::HISTORY_TEXT ) : substr( $text, 0, self::HISTORY_TEXT );
			if ( '' === $text ) {
				continue;
			}
			if ( Session_Store::add(
				$session_id,
				$role,
				$text,
				array(
					'channel' => 'web',
					'source'  => 'handoff',
					'force'   => true,
				)
			) ) {
				++$stored;
			}
		}//end foreach
		return $stored;
	}

	/**
	 * A conversation as the API shows it to an agent.
	 *
	 * @param array $conv Row.
	 * @return array
	 */
	public static function summary( array $conv ) {
		return array(
			'id'          => (int) $conv['id'],
			'status'      => (string) $conv['status'],
			'channel'     => (string) $conv['channel'],
			'department'  => (string) $conv['department'],
			'agent_id'    => (int) $conv['agent_id'],
			'agent_name'  => (int) $conv['agent_id'] ? Agents::name( (int) $conv['agent_id'] ) : '',
			'visitor'     => '' !== $conv['visitor_name'] ? (string) $conv['visitor_name'] : __( 'Visitor', 'chatbot' ),
			'email'       => (string) $conv['visitor_email'],
			'user_id'     => (int) $conv['user_id'],
			'page_url'    => (string) $conv['page_url'],
			'page_title'  => (string) $conv['page_title'],
			'reason'      => (string) $conv['reason'],
			'queued_at'   => (string) $conv['queued_at'],
			'updated'     => (string) $conv['updated'],
			'rating'      => (int) $conv['rating'],
			'last_event'  => (int) $conv['last_event_id'],
			'visitor_key' => (string) $conv['visitor_key'],
			'flags'       => isset( $conv['flags'] ) ? array_values( array_filter( explode( ',', (string) $conv['flags'] ) ) ) : array(),
			'imported'    => ! empty( $conv['imported'] ),
		);
	}
}
