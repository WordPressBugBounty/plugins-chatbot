<?php
/**
 * REST routes of the chat (namespace wpbot-chatbot/v1).
 *
 * Visitor routes need a visitor token (POST /token) and are rate limited;
 * there are no admin write routes here (settings screens keep their forms).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Rest;

use QuantumCloud\Chatbot\Conversation\Chat;
use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Leads\Leads;
use QuantumCloud\Chatbot\RateLimit\Limiter;
use QuantumCloud\Chatbot\Sessions\Session_Store;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes.
 */
final class Routes {

	/**
	 * Namespace.
	 */
	const NS = 'wpbot-chatbot/v1';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register() {
		$visitor = array( __CLASS__, 'check_token' );
		register_rest_route(
			self::NS,
			'/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'token' ),
				'permission_callback' => array( __CLASS__, 'token_allowed' ),
			)
		);
		register_rest_route(
			self::NS,
			'/message',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'message' ),
				'permission_callback' => $visitor,
				'args'                => array(
					'text'       => array(
						'type'     => 'string',
						'required' => true,
					),
					'session_id' => array( 'type' => 'string' ),
					'stream'     => array( 'type' => 'boolean' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/session/(?P<id>[A-Za-z0-9_\-.:]{6,128})',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'session_save' ),
				'permission_callback' => $visitor,
			)
		);
		foreach ( array( 'lead', 'feedback', 'report' ) as $route ) {
			register_rest_route(
				self::NS,
				'/' . $route,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, $route ),
					'permission_callback' => $visitor,
				)
			);
		}
	}

	/**
	 * Token route rate limit.
	 *
	 * @return true|WP_Error
	 */
	public static function token_allowed() {
		return Limiter::hit( 'token' ) ? true : self::limited();
	}

	/**
	 * Issue a visitor token.
	 *
	 * @return WP_REST_Response
	 */
	public static function token() {
		$response = new WP_REST_Response(
			array(
				'token'   => Visitor_Token::issue(),
				'expires' => time() + Visitor_Token::TTL,
			)
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Permission check for visitor routes: a valid token (header X-WPBot-Token or "token").
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function check_token( WP_REST_Request $request ) {
		$token = $request->get_header( 'x-wpbot-token' );
		if ( ! $token ) {
			$token = $request->get_param( 'token' );
		}
		if ( Visitor_Token::valid( (string) $token ) ) {
			return true;
		}
		return new WP_Error( 'qcld_wpbot_chat_token', __( 'The chat session expired. Please reload the page.', 'chatbot' ), array( 'status' => 403 ) );
	}

	/**
	 * Answer a message (JSON, or server-sent events with stream=true).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error|void
	 */
	public static function message( WP_REST_Request $request ) {
		if ( ! Limiter::hit( 'message' ) ) {
			return self::limited();
		}
		$message = Message::from_array(
			array(
				'text'       => (string) $request->get_param( 'text' ),
				'session_id' => (string) $request->get_param( 'session_id' ),
				'name'       => (string) $request->get_param( 'name' ),
				'language'   => (string) $request->get_param( 'language' ),
				'channel'    => 'web',
				'page_url'   => (string) $request->get_param( 'page_url' ),
				'page_title' => (string) $request->get_param( 'page_title' ),
				'history'    => (array) $request->get_param( 'history' ),
				'extra'      => (array) $request->get_param( 'extra' ),
			)
		);
		if ( '' === $message->text && '' === $message->extra( 'strid' ) && '' === $message->extra( 'intent' ) ) {
			return new WP_Error( 'qcld_wpbot_chat_empty', __( 'Please type a message.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$visitor = array( 'user_agent' => (string) $request->get_header( 'user_agent' ) );

		if ( $request->get_param( 'stream' ) ) {
			Sse::start();
			$sent  = false;
			$reply = Chat::answer(
				$message,
				static function ( $text ) use ( &$sent ) {
					$sent = true;
					Sse::json(
						array(
							'type' => 'text',
							'text' => (string) $text,
						)
					);
				},
				null,
				$visitor
			);
			if ( ! $sent && '' !== $reply->text ) {
				// Answers that did not stream (a provider with streaming off) come as one piece.
				Sse::json(
					array(
						'type' => 'text',
						'text' => $reply->text,
					)
				);
			}
			Sse::json(
				array(
					'type'  => 'done',
					'reply' => $reply->to_array(),
				)
			);
			exit;
		}//end if
		return new WP_REST_Response( Chat::answer( $message, null, null, $visitor )->to_array() );
	}

	/**
	 * Save the visitor's details on the session (name, email, phone).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function session_save( WP_REST_Request $request ) {
		$id = Session_Store::save_legacy(
			(string) $request['id'],
			array(
				'name'       => (string) $request->get_param( 'name' ),
				'email'      => (string) $request->get_param( 'email' ),
				'phone'      => (string) $request->get_param( 'phone' ),
				'source_url' => (string) $request->get_param( 'source_url' ),
				'user_agent' => (string) $request->get_header( 'user_agent' ),
			)
		);
		return new WP_REST_Response( array( 'status' => $id ? 'success' : 'fail' ) );
	}

	/**
	 * Lead capture.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function lead( WP_REST_Request $request ) {
		if ( ! Limiter::hit( 'lead' ) ) {
			return self::limited();
		}
		return new WP_REST_Response(
			Leads::subscribe(
				array(
					'name'       => (string) $request->get_param( 'name' ),
					'email'      => (string) $request->get_param( 'email' ),
					'phone'      => (string) $request->get_param( 'phone' ),
					'url'        => (string) $request->get_param( 'url' ),
					'language'   => (string) $request->get_param( 'language' ),
					'user_agent' => (string) $request->get_header( 'user_agent' ),
				)
			)
		);
	}

	/**
	 * Feedback on a message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function feedback( WP_REST_Request $request ) {
		if ( ! Limiter::hit( 'feedback' ) ) {
			return self::limited();
		}
		$ok = Leads::feedback( (array) $request->get_params() );
		return new WP_REST_Response( array( 'success' => $ok ), $ok ? 200 : 400 );
	}

	/**
	 * Report a message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function report( WP_REST_Request $request ) {
		if ( ! Limiter::hit( 'report' ) ) {
			return self::limited();
		}
		$ok = Leads::report( (array) $request->get_params(), Limiter::ip() );
		return new WP_REST_Response( array( 'success' => $ok ), $ok ? 200 : 400 );
	}

	/**
	 * Rate limit error.
	 *
	 * @return WP_Error
	 */
	private static function limited() {
		return new WP_Error( 'qcld_wpbot_chat_rate_limited', __( 'Too many requests. Please wait a little and try again.', 'chatbot' ), array( 'status' => 429 ) );
	}
}
