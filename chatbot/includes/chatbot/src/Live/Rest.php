<?php
/**
 * Live chat REST API (namespace wpbot-chatbot/v1, under /live).
 *
 * Visitor (chat token, plus the conversation's signed visitor key after the start):
 *   POST /live/start, GET /live/{id}/events, POST /live/{id}/message, /typing, /end, /rate.
 * Agent (logged in, agent capability, REST nonce):
 *   POST /live/agent/sync: everything new across the agent's chats, plus presence;
 *   GET  /live/agent/conversation/{id}: the conversation with the bot history;
 *   POST /live/agent/conversation/{id}/claim, /message, /note, /transfer, /close, /typing.
 * Every query uses an index; nothing sleeps.
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

use QuantumCloud\Chatbot\RateLimit\Limiter;
use QuantumCloud\Chatbot\Rest\Routes;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Routes.
 */
final class Rest {

	/**
	 * Namespace (the chat's).
	 */
	const NS = Routes::NS;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register() {
		$visitor = array( Routes::class, 'check_token' );
		$agent   = array( __CLASS__, 'agent_allowed' );
		$id      = '(?P<id>\d+)';
		$routes  = array(
			array( '/live/start', 'POST', 'start', $visitor ),
			array( '/live/' . $id . '/events', 'GET', 'events', '__return_true' ),
			array( '/live/' . $id . '/message', 'POST', 'visitor_message', '__return_true' ),
			array( '/live/' . $id . '/typing', 'POST', 'visitor_typing', '__return_true' ),
			array( '/live/' . $id . '/end', 'POST', 'visitor_end', '__return_true' ),
			array( '/live/' . $id . '/rate', 'POST', 'visitor_rate', '__return_true' ),
			array( '/live/thumbs', 'POST', 'thumbs', $visitor ),
			array( '/live/agent/sync', 'POST', 'agent_sync', $agent ),
			array( '/live/agent/conversation/' . $id, 'GET', 'agent_conversation', $agent ),
			array( '/live/agent/history', 'GET', 'agent_history', $agent ),
			array( '/live/agent/conversation/' . $id . '/(?P<action>claim|message|note|transfer|close|typing|assist|block|train|email)', 'POST', 'agent_action', $agent ),
			array( '/live/push/key', 'GET', 'push_key', '__return_true' ),
			array( '/live/push/pending', 'POST', 'push_pending', '__return_true' ),
			array( '/live/push/agent', 'POST', 'push_agent', $agent ),
			array( '/live/' . $id . '/push', 'POST', 'push_visitor', '__return_true' ),
		);
		foreach ( $routes as $route ) {
			register_rest_route(
				self::NS,
				$route[0],
				array(
					'methods'             => $route[1],
					'callback'            => array( __CLASS__, $route[2] ),
					'permission_callback' => $route[3],
				)
			);
		}
	}

	/**
	 * Agent routes: a logged-in agent (the REST nonce proves the cookie session).
	 *
	 * @return bool|WP_Error
	 */
	public static function agent_allowed() {
		if ( Agents::is_agent() ) {
			return true;
		}
		return new WP_Error( 'qcld_wpbot_live_forbidden', __( 'You do not answer live chats on this site.', 'chatbot' ), array( 'status' => 403 ) );
	}

	/**
	 * The visitor's conversation, or an error (missing, or the key doesn't match).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	private static function visitor_conversation( WP_REST_Request $request ) {
		$conv = Conversations::get( (int) $request['id'] );
		$key  = (string) $request->get_header( 'x-wpbot-live' );
		if ( '' === $key ) {
			$key = (string) $request->get_param( 'key' );
		}
		if ( ! $conv || ! Live::visitor_can( $conv, $key ) ) {
			return new WP_Error( 'qcld_wpbot_live_not_found', __( 'This chat is not available.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return $conv;
	}

	/**
	 * A visitor asks for a person.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function start( WP_REST_Request $request ) {
		if ( ! Limiter::hit( 'live_start' ) ) {
			return new WP_Error( 'qcld_wpbot_live_limit', __( 'Too many requests. Please wait a moment.', 'chatbot' ), array( 'status' => 429 ) );
		}
		$department = sanitize_key( (string) $request->get_param( 'department' ) );
		$user       = wp_get_current_user();
		$visitor    = class_exists( '\QuantumCloud\Chatbot\Visitor\Visitor' ) ? (string) \QuantumCloud\Chatbot\Visitor\Visitor::key( true ) : '';
		if ( Assistant::blocked( $visitor, (string) $request->get_param( 'session_id' ) ) ) {
			return new WP_Error( 'qcld_wpbot_live_blocked', __( 'Live chat is not available.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$name  = (string) $request->get_param( 'name' );
		$email = (string) $request->get_param( 'email' );
		if ( $user && $user->ID ) {
			$name  = '' !== $name ? $name : (string) $user->display_name;
			$email = '' !== $email ? $email : (string) $user->user_email;
		}
		$id   = Conversations::start(
			array(
				'session_id'    => (string) $request->get_param( 'session_id' ),
				'visitor_key'   => $visitor,
				'channel'       => 'web',
				'department'    => $department,
				'visitor_name'  => $name,
				'visitor_email' => $email,
				'user_id'       => $user ? (int) $user->ID : 0,
				'page_url'      => (string) $request->get_param( 'page_url' ),
				'page_title'    => (string) $request->get_param( 'page_title' ),
				'reason'        => (string) $request->get_param( 'reason' ),
			)
		);
		$conv = $id ? Conversations::get( $id ) : null;
		if ( ! $conv ) {
			return new WP_Error( 'qcld_wpbot_live_start', __( 'The chat could not start. Please try again.', 'chatbot' ), array( 'status' => 400 ) );
		}
		// The bot conversation so far, when the site doesn't keep chat sessions (agents need it).
		$history = $request->get_param( 'history' );
		if ( is_array( $history ) ) {
			Conversations::handoff_history( (string) $conv['session_id'], $history );
		}
		$first = trim( (string) $request->get_param( 'message' ) );
		if ( '' !== $first ) {
			Conversations::message( $id, 'user', $first, 'visitor' );
		}
		if ( 'queued' === $conv['status'] ) {
			$agent = Routing::pick( $conv );
			if ( $agent > 0 ) {
				Conversations::assign( $id, $agent, 'system' );
			}
		}
		$conv = Conversations::get( $id );
		return new WP_REST_Response(
			array(
				'conversation' => (int) $conv['id'],
				'key'          => Live::visitor_key( $conv ),
				'signal'       => Signal::url( (int) $conv['id'] ),
				'status'       => (string) $conv['status'],
				'available'    => Routing::available( (string) $conv['department'] ),
				'last_event'   => 0,
			)
		);
	}

	/**
	 * New events for the visitor (asked only when the signal file changed, or as the fallback).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function events( WP_REST_Request $request ) {
		$conv = self::visitor_conversation( $request );
		if ( is_wp_error( $conv ) ) {
			return $conv;
		}
		Push::visitor_seen( (int) $conv['id'] );
		$events = array();
		foreach ( Events::after( array( (int) $conv['id'] ), (int) $request->get_param( 'after' ) ) as $event ) {
			$events[] = self::for_visitor( $event );
		}
		return new WP_REST_Response(
			array(
				'events'     => $events,
				'status'     => (string) $conv['status'],
				'agent'      => (int) $conv['agent_id'] ? Agents::name( (int) $conv['agent_id'] ) : '',
				'typing'     => Events::is_typing( (int) $conv['id'], 'agent' ),
				'last_event' => (int) $conv['last_event_id'],
			)
		);
	}

	/**
	 * An event as the visitor sees it (no agent ids, no internal fields).
	 *
	 * @param array $event Event.
	 * @return array
	 */
	private static function for_visitor( array $event ) {
		$payload = $event['payload'];
		$out     = array(
			'id'   => $event['id'],
			'type' => $event['type'],
			'mine' => 'visitor' === $event['actor'],
		);
		if ( 'message' === $event['type'] ) {
			$out['role'] = isset( $payload['role'] ) ? (string) $payload['role'] : '';
			// "bot" messages are answers from the knowledge base while the visitor waits.
			$out['text'] = isset( $payload['text'] ) ? (string) $payload['text'] : '';
			$out['name'] = isset( $payload['agent_name'] ) ? (string) $payload['agent_name'] : '';
		} elseif ( in_array( $event['type'], array( 'assigned', 'transferred' ), true ) ) {
			$out['name'] = isset( $payload['agent_name'] ) ? (string) $payload['agent_name'] : '';
		}
		return $out;
	}

	/**
	 * The visitor sends a message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function visitor_message( WP_REST_Request $request ) {
		$conv = self::visitor_conversation( $request );
		if ( is_wp_error( $conv ) ) {
			return $conv;
		}
		Push::visitor_seen( (int) $conv['id'] );
		if ( ! Limiter::hit( 'live_message' ) ) {
			return new WP_Error( 'qcld_wpbot_live_limit', __( 'Too many messages. Please wait a moment.', 'chatbot' ), array( 'status' => 429 ) );
		}
		$event = Conversations::message( (int) $conv['id'], 'user', (string) $request->get_param( 'text' ), 'visitor' );
		if ( ! $event ) {
			return new WP_Error( 'qcld_wpbot_live_message', __( 'The message could not be sent.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array( 'event' => $event ) );
	}

	/**
	 * The visitor is typing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function visitor_typing( WP_REST_Request $request ) {
		$conv = self::visitor_conversation( $request );
		if ( is_wp_error( $conv ) ) {
			return $conv;
		}
		Push::visitor_seen( (int) $conv['id'] );
		Events::typing( (int) $conv['id'], 'visitor' );
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * The visitor ends the chat.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function visitor_end( WP_REST_Request $request ) {
		$conv = self::visitor_conversation( $request );
		if ( is_wp_error( $conv ) ) {
			return $conv;
		}
		Conversations::close( (int) $conv['id'], 'visitor' );
		return new WP_REST_Response( array( 'status' => 'closed' ) );
	}

	/**
	 * The visitor rates the chat.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function visitor_rate( WP_REST_Request $request ) {
		$conv = self::visitor_conversation( $request );
		if ( is_wp_error( $conv ) ) {
			return $conv;
		}
		Conversations::rate( (int) $conv['id'], (int) $request->get_param( 'rating' ), (string) $request->get_param( 'comment' ) );
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * A thumbs down on a bot answer (escalation rule "the answer didn't help"); returns the offer when
	 * it reached the not-sure count.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function thumbs( WP_REST_Request $request ) {
		$reason = '';
		if ( Limiter::hit( 'feedback' ) ) {
			$reason = Escalation::thumbs_down( (string) preg_replace( '/[^A-Za-z0-9_\-.:]/', '', (string) $request->get_param( 'session_id' ) ) );
		}
		// The offer ("Chat with our team", or "Leave a message" when nobody is available), shown under
		// the thumbs down when it reached the not-sure count.
		return new WP_REST_Response(
			array(
				'ok'    => true,
				'offer' => '' === $reason ? '' : Escalation::offer_html( $reason ),
			)
		);
	}

	/**
	 * The agent console's one request: presence, the agent's conversations and every event after
	 * the last one it saw.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function agent_sync( WP_REST_Request $request ) {
		$user = get_current_user_id();
		$away = 'away' === $request->get_param( 'status' );
		Agents::heartbeat( $user, $away ? 'away' : 'online' );
		// The Inbox shows new chats itself while it is open and visible: no push to this agent then.
		Push::agent_seen( $user, ! $away && '1' === (string) $request->get_param( 'visible' ) );
		$conversations = Conversations::for_agent( $user, Agents::departments( $user ), Agents::is_manager( $user ) );
		$ids           = array_map( 'intval', wp_list_pluck( $conversations, 'id' ) );
		$typing        = array();
		foreach ( $ids as $cid ) {
			if ( Events::is_typing( $cid, 'visitor' ) ) {
				$typing[] = $cid;
			}
		}
		return new WP_REST_Response(
			array(
				'conversations' => array_map( array( Conversations::class, 'summary' ), $conversations ),
				'events'        => Events::after( $ids, (int) $request->get_param( 'after' ), true ),
				'typing'        => $typing,
				'online'        => count( Agents::online() ),
				'me'            => array(
					'id'     => $user,
					'name'   => Agents::name( $user ),
					'status' => $away ? 'away' : 'online',
				),
			)
		);
	}

	/**
	 * Closed conversations for agents (the History view), with a search.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function agent_history( WP_REST_Request $request ) {
		$rows = Conversations::history( sanitize_text_field( (string) $request->get_param( 'search' ) ), 50, max( 0, (int) $request->get_param( 'offset' ) ) );
		$user = get_current_user_id();
		$deps = Agents::departments( $user );
		$rows = array_filter(
			$rows,
			static function ( $row ) use ( $user, $deps ) {
				return Agents::is_manager( $user ) || (int) $row['agent_id'] === $user || ! $deps || '' === $row['department'] || in_array( $row['department'], $deps, true );
			}
		);
		return new WP_REST_Response( array( 'conversations' => array_values( array_map( array( Conversations::class, 'summary' ), $rows ) ) ) );
	}

	/**
	 * A conversation for an agent, with the bot history and notes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function agent_conversation( WP_REST_Request $request ) {
		$conv = Conversations::get( (int) $request['id'] );
		if ( ! $conv || ! self::agent_sees( $conv ) ) {
			return new WP_Error( 'qcld_wpbot_live_not_found', __( 'This chat is not available.', 'chatbot' ), array( 'status' => 404 ) );
		}
		/**
		 * More about a conversation's visitor for the agent (WPBot Pro: store customer and orders,
		 * Site Insight visits).
		 *
		 * @param array $context Default none.
		 * @param array $conv    Conversation.
		 */
		$context = (array) apply_filters( 'qcld_wpbot_chat/live_agent_context', array(), $conv );
		return new WP_REST_Response(
			array(
				'conversation' => Conversations::summary( $conv ),
				'transcript'   => Conversations::transcript( $conv ),
				'events'       => Events::after( array( (int) $conv['id'] ), 0, true ),
				'context'      => $context,
			)
		);
	}

	/**
	 * Whether the current agent may see a conversation.
	 *
	 * @param array $conv Conversation.
	 * @return bool
	 */
	private static function agent_sees( array $conv ) {
		$user = get_current_user_id();
		if ( Agents::is_manager( $user ) || (int) $conv['agent_id'] === $user ) {
			return true;
		}
		$departments = Agents::departments( $user );
		return in_array( $conv['status'], array( 'queued', 'closed' ), true ) && ( ! $departments || '' === $conv['department'] || in_array( $conv['department'], $departments, true ) );
	}

	/**
	 * Agent actions on a conversation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function agent_action( WP_REST_Request $request ) {
		$conv = Conversations::get( (int) $request['id'] );
		if ( ! $conv || ! self::agent_sees( $conv ) ) {
			return new WP_Error( 'qcld_wpbot_live_not_found', __( 'This chat is not available.', 'chatbot' ), array( 'status' => 404 ) );
		}
		// Blocking a visitor and teaching the bot are for administrators (owner, 20 November 2026).
		if ( in_array( (string) $request['action'], array( 'block', 'train' ), true ) && ! Agents::is_manager( get_current_user_id() ) ) {
			return new WP_Error( 'qcld_wpbot_live_forbidden', __( 'Only administrators can do this.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$user  = get_current_user_id();
		$actor = 'agent:' . $user;
		$id    = (int) $conv['id'];
		$ok    = false;
		switch ( (string) $request['action'] ) {
			case 'claim':
				$ok = Conversations::assign( $id, $user, $actor );
				break;
			case 'message':
				if ( 'queued' === $conv['status'] ) {
					Conversations::assign( $id, $user, $actor );
				}
				$ok = (bool) Conversations::message( $id, 'agent', (string) $request->get_param( 'text' ), $actor, array( 'agent_name' => Agents::name( $user ) ) );
				break;
			case 'note':
				$text = trim( sanitize_textarea_field( (string) $request->get_param( 'text' ) ) );
				$ok   = '' !== $text && (bool) Events::add(
					$id,
					'note',
					$actor,
					array(
						'text'       => $text,
						'agent_name' => Agents::name( $user ),
					),
					true
				);
				break;
			case 'transfer':
				$ok = Conversations::transfer( $id, (int) $request->get_param( 'agent' ), (string) $request->get_param( 'department' ), $actor );
				break;
			case 'close':
				$ok = Conversations::close( $id, $actor );
				break;
			case 'typing':
				Events::typing( $id, 'agent' );
				$ok = true;
				break;
			case 'assist':
				$res = Assistant::assist( $conv, (string) $request->get_param( 'task' ), (string) $request->get_param( 'text' ), (string) $request->get_param( 'lang' ) );
				return is_wp_error( $res ) ? $res : new WP_REST_Response( $res );
			case 'block':
				Assistant::block( $conv, $actor );
				$ok = true;
				break;
			case 'train':
				$ok = Assistant::train( $conv, (string) $request->get_param( 'text' ) );
				break;
			case 'email':
				$ok = Assistant::email( $conv, (string) $request->get_param( 'to' ) );
				break;
		}//end switch
		if ( ! $ok ) {
			return new WP_Error( 'qcld_wpbot_live_action', __( 'That did not work. Reload the chat and try again.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array( 'conversation' => Conversations::summary( Conversations::get( $id ) ) ) );
	}

	/**
	 * The public key browsers subscribe with.
	 *
	 * @return WP_REST_Response
	 */
	public static function push_key() {
		return new WP_REST_Response(
			array(
				'available' => Push::available(),
				'key'       => Push::public_key(),
			)
		);
	}

	/**
	 * A service worker asks for its notes after a push (the endpoint is the device's secret).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function push_pending( WP_REST_Request $request ) {
		$endpoint = (string) $request->get_param( 'endpoint' );
		return new WP_REST_Response( array( 'notes' => 0 === strpos( $endpoint, 'https://' ) ? Push::pending( $endpoint ) : array() ) );
	}

	/**
	 * An agent switches notifications on or off for this device.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function push_agent( WP_REST_Request $request ) {
		$sub  = $request->get_param( 'subscription' );
		$user = get_current_user_id();
		if ( $request->get_param( 'remove' ) ) {
			Push::unsubscribe_agent( $user, is_array( $sub ) && isset( $sub['endpoint'] ) ? (string) $sub['endpoint'] : '' );
			return new WP_REST_Response( array( 'devices' => count( Push::agent_devices( $user ) ) ) );
		}
		if ( ! Push::subscribe_agent( $user, $sub ) ) {
			return new WP_Error( 'qcld_wpbot_live_push', __( 'Notifications could not be switched on for this device.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array( 'devices' => count( Push::agent_devices( $user ) ) ) );
	}

	/**
	 * A visitor asks to hear about replies in this chat.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function push_visitor( WP_REST_Request $request ) {
		$conv = self::visitor_conversation( $request );
		if ( is_wp_error( $conv ) ) {
			return $conv;
		}
		if ( ! Push::subscribe_visitor( (int) $conv['id'], $request->get_param( 'subscription' ) ) ) {
			return new WP_Error( 'qcld_wpbot_live_push', __( 'Notifications could not be switched on.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array( 'ok' => true ) );
	}
}
