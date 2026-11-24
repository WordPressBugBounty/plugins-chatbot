<?php
/**
 * Live chat helpers for agents (live chat rebuild LC6, plan 4.2; owner D8 extras).
 *
 * - AI assistant: a suggested reply from the knowledge base, summarize, rewrite (shorter or
 *   friendlier), translate. Uses the AI Settings provider; the cost is returned with the text.
 * - Flags: urgent and unhappy visitor messages (cheap word checks, no AI call).
 * - Actions: block a visitor, add an agent's answer to Train, email the transcript.
 * - While a visitor waits: an answer from the knowledge base (background, once a minute at most),
 *   when the owner switches it on.
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

use QuantumCloud\Chatbot\Ai\Ai_Client;

defined( 'ABSPATH' ) || exit;

/**
 * Assistant.
 */
final class Assistant {

	/**
	 * Option: blocked visitors (hashes).
	 */
	const BLOCKED = 'qcld_wpbot_chat_live_blocked';

	/**
	 * Background hook for answers while waiting.
	 */
	const WAIT_HOOK = 'qcld_wpbot_live_wait_answer';

	/**
	 * Words that mark a message as urgent.
	 */
	const URGENT = '/\b(urgent|asap|immediately|right now|emergency|as soon as possible)\b|!!!/i';

	/**
	 * Words that mark a visitor as unhappy.
	 */
	const UNHAPPY = '/\b(angry|furious|terrible|awful|worst|ridiculous|unacceptable|refund now|scam|disappointed|complain(?:t)?)\b/i';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::WAIT_HOOK, array( __CLASS__, 'wait_answer' ), 10, 2 );
		add_action( 'qcld_wpbot_chat/live_event', array( __CLASS__, 'on_event' ), 10, 4 );
	}

	/**
	 * Flags for a visitor message.
	 *
	 * @param string $text Text.
	 * @return string[] urgent, unhappy.
	 */
	public static function flags( $text ) {
		$flags = array();
		if ( preg_match( self::URGENT, (string) $text ) ) {
			$flags[] = 'urgent';
		}
		$letters = preg_replace( '/[^A-Za-z]/', '', (string) $text );
		if ( preg_match( self::UNHAPPY, (string) $text ) || ( strlen( $letters ) >= 12 && strtoupper( $letters ) === $letters ) ) {
			$flags[] = 'unhappy';
		}
		return $flags;
	}

	/**
	 * After each event: flag visitor messages, plan an answer while waiting, and the automation hooks.
	 *
	 * @param int    $event_id        Event.
	 * @param int    $conversation_id Conversation.
	 * @param string $type            Type.
	 * @param array  $payload         Data.
	 * @return void
	 */
	public static function on_event( $event_id, $conversation_id, $type, $payload ) {
		if ( 'message' === $type && isset( $payload['role'] ) && 'user' === $payload['role'] ) {
			$flags = self::flags( isset( $payload['text'] ) ? (string) $payload['text'] : '' );
			if ( $flags ) {
				$conv = Conversations::get( $conversation_id );
				$have = $conv ? array_filter( explode( ',', (string) $conv['flags'] ) ) : array();
				Conversations::update( $conversation_id, array( 'flags' => implode( ',', array_unique( array_merge( $have, $flags ) ) ) ) );
			}
			if ( self::wait_answers_on() && ! get_transient( 'qcld_wpbot_chat_live_wait_' . (int) $conversation_id ) ) {
				$conv = isset( $conv ) ? $conv : Conversations::get( $conversation_id );
				if ( $conv && 'queued' === $conv['status'] ) {
					set_transient( 'qcld_wpbot_chat_live_wait_' . (int) $conversation_id, 1, MINUTE_IN_SECONDS );
					wp_schedule_single_event( time(), self::WAIT_HOOK, array( (int) $conversation_id, (int) $event_id ) );
				}
			}
		}
		$hooks = array(
			'queued' => 'qcld_wpbot_chat/live_handoff',
			'closed' => 'qcld_wpbot_chat/live_closed',
			'rating' => 'qcld_wpbot_chat/live_rated',
		);
		if ( isset( $hooks[ $type ] ) ) {
			/**
			 * Automation and CRM hooks: a visitor was handed to a person (live_handoff), a chat
			 * closed (live_closed), a visitor rated a chat (live_rated).
			 *
			 * @param int   $conversation_id Conversation.
			 * @param array $payload         Event data.
			 */
			do_action( $hooks[ $type ], (int) $conversation_id, (array) $payload ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The three names above, all prefixed.
		}
	}

	/**
	 * Whether waiting visitors get answers from the knowledge base.
	 *
	 * @return bool
	 */
	public static function wait_answers_on() {
		/**
		 * Answer waiting visitors from the knowledge base (WPBot Pro setting).
		 *
		 * @param bool $on Default false.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/live_wait_answers', false );
	}

	/**
	 * The knowledge base context for a question ('' when there is none).
	 *
	 * @param string $question Question.
	 * @return string
	 */
	public static function knowledge( $question ) {
		if ( ! function_exists( 'qcld_wpbot_core_kb_search' ) || ! class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Search' ) ) {
			return '';
		}
		$results = qcld_wpbot_core_kb_search( (string) $question, array() );
		return is_array( $results ) && $results ? (string) \QuantumCloud\WPBotCore\Knowledge\Kb_Search::context( $results ) : '';
	}

	/**
	 * The background answer while a visitor waits (only from the knowledge base).
	 *
	 * @param int $conversation_id Conversation.
	 * @param int $event_id        The visitor's message event.
	 * @return void
	 */
	public static function wait_answer( $conversation_id, $event_id ) {
		$conv  = Conversations::get( (int) $conversation_id );
		$event = Events::get( (int) $event_id );
		if ( ! $conv || 'queued' !== $conv['status'] || ! $event || empty( $event['payload']['text'] ) ) {
			return;
		}
		$knowledge = self::knowledge( (string) $event['payload']['text'] );
		if ( '' === $knowledge ) {
			return;
		}
		$res = Ai_Client::reply(
			(string) $event['payload']['text'],
			array(
				'raw'        => true,
				'feature'    => 'live_wait',
				'max_tokens' => 300,
				'context'    => "Answer the visitor's question only from the knowledge below, in two or three sentences, in the visitor's language. If the knowledge does not answer it, reply with exactly NONE.\n\n" . $knowledge,
			)
		);
		if ( is_wp_error( $res ) || empty( $res['text'] ) || 'NONE' === trim( (string) $res['text'] ) ) {
			return;
		}
		Conversations::message( (int) $conversation_id, 'bot', (string) $res['text'], 'bot' );
	}

	/**
	 * Ask the AI to help an agent.
	 *
	 * @param array  $conv Conversation.
	 * @param string $task suggest, summarize, rewrite_shorter, rewrite_friendlier, translate.
	 * @param string $text The agent's draft (rewrite, translate) or ''.
	 * @param string $lang Target language for translate (e.g. "Spanish").
	 * @return array|\WP_Error { text, cost }
	 */
	public static function assist( array $conv, $task, $text = '', $lang = '' ) {
		$transcript = array();
		foreach ( Conversations::transcript( $conv ) as $m ) {
			$transcript[] = ( 'user' === $m['role'] ? 'Visitor' : ( 'agent' === $m['role'] ? 'Agent' : 'Bot' ) ) . ': ' . wp_strip_all_tags( (string) $m['text'] );
		}
		$chat  = implode( "\n", array_slice( $transcript, -30 ) );
		$tasks = array(
			'suggest'            => "Write the agent's next reply to the visitor: short, friendly, specific, in the visitor's language. Use the knowledge when it helps. Reply with the message only.",
			'summarize'          => 'Summarize this support chat for a colleague in three short bullet points: what the visitor wants, what was tried, what is still open.',
			'rewrite_shorter'    => "Rewrite the agent's draft shorter and clearer, same meaning and language. Reply with the rewritten message only.",
			'rewrite_friendlier' => "Rewrite the agent's draft warmer and friendlier, same meaning and language. Reply with the rewritten message only.",
			'translate'          => 'Translate the text into ' . ( '' !== $lang ? sanitize_text_field( $lang ) : "the visitor's language" ) . '. Reply with the translation only.',
		);
		if ( ! isset( $tasks[ $task ] ) ) {
			return new \WP_Error( 'qcld_wpbot_live_task', __( 'Unknown task.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$context = $tasks[ $task ] . "\n\nChat so far:\n" . $chat;
		if ( 'suggest' === $task ) {
			$last      = '';
			$knowledge = '';
			foreach ( array_reverse( Conversations::transcript( $conv ) ) as $m ) {
				if ( 'user' === $m['role'] ) {
					$last = (string) $m['text'];
					break;
				}
			}
			if ( '' !== $last ) {
				$knowledge = self::knowledge( $last );
			}
			$context .= '' !== $knowledge ? "\n\nKnowledge:\n" . $knowledge : '';
		}
		$question = in_array( $task, array( 'rewrite_shorter', 'rewrite_friendlier', 'translate' ), true ) ? (string) $text : 'Go.';
		if ( '' === trim( $question ) ) {
			return new \WP_Error( 'qcld_wpbot_live_task', __( 'Write a draft first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$res = Ai_Client::reply(
			$question,
			array(
				'raw'        => true,
				'feature'    => 'live_assist',
				'max_tokens' => 500,
				'context'    => $context,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'text' => trim( (string) $res['text'] ),
			'cost' => isset( $res['cost'] ) ? (float) $res['cost'] : 0.0,
		);
	}

	/**
	 * The hash a visitor is blocked by (visitor key, or the session).
	 *
	 * @param array $conv Conversation.
	 * @return string
	 */
	public static function visitor_hash( array $conv ) {
		$who = '' !== (string) $conv['visitor_key'] ? 'k:' . $conv['visitor_key'] : 's:' . $conv['session_id'];
		return substr( hash_hmac( 'sha256', $who, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Block the visitor of a conversation (and close it).
	 *
	 * @param array  $conv  Conversation.
	 * @param string $actor Actor.
	 * @return void
	 */
	public static function block( array $conv, $actor ) {
		$list   = (array) get_option( self::BLOCKED, array() );
		$list[] = self::visitor_hash( $conv );
		update_option( self::BLOCKED, array_slice( array_values( array_unique( $list ) ), -1000 ), false );
		Conversations::close( (int) $conv['id'], $actor );
	}

	/**
	 * Whether a visitor (key or session) is blocked.
	 *
	 * @param string $visitor_key Visitor key.
	 * @param string $session     Session.
	 * @return bool
	 */
	public static function blocked( $visitor_key, $session ) {
		$list = (array) get_option( self::BLOCKED, array() );
		if ( ! $list ) {
			return false;
		}
		foreach ( array( 'k:' . $visitor_key, 's:' . $session ) as $who ) {
			if ( strlen( $who ) > 2 && in_array( substr( hash_hmac( 'sha256', $who, wp_salt( 'auth' ) ), 0, 32 ), $list, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add an agent's answer to Train (the visitor's first question, answered), so the bot can learn it.
	 *
	 * @param array  $conv   Conversation.
	 * @param string $answer Answer.
	 * @return bool
	 */
	public static function train( array $conv, $answer ) {
		$answer = trim( sanitize_textarea_field( (string) $answer ) );
		if ( '' === $answer || ! class_exists( '\QuantumCloud\Chatbot\Train\Gaps' ) ) {
			return false;
		}
		$question = '';
		foreach ( Conversations::transcript( $conv ) as $m ) {
			if ( 'user' === $m['role'] ) {
				$question = (string) $m['text'];
				break;
			}
		}
		if ( '' === $question ) {
			return false;
		}
		\QuantumCloud\Chatbot\Train\Gaps::record( $question, 'live_chat' );
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE gap_key = %s', \QuantumCloud\Chatbot\Sessions\Schema::gaps_table(), \QuantumCloud\Chatbot\Train\Gaps::key( $question ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $id > 0 && \QuantumCloud\Chatbot\Train\Gaps::set_status( $id, 'answered', $answer );
	}

	/**
	 * Email the transcript (to the visitor, or to an address).
	 *
	 * @param array  $conv Conversation.
	 * @param string $to   Address ('' = the visitor's).
	 * @return bool
	 */
	public static function email( array $conv, $to = '' ) {
		$to = '' !== $to ? sanitize_email( $to ) : (string) $conv['visitor_email'];
		if ( ! is_email( $to ) ) {
			return false;
		}
		$lines = array();
		foreach ( Conversations::transcript( $conv ) as $m ) {
			$lines[] = ( 'user' === $m['role'] ? __( 'You', 'chatbot' ) : ( 'agent' === $m['role'] ? __( 'Our team', 'chatbot' ) : __( 'Bot', 'chatbot' ) ) ) . ': ' . wp_strip_all_tags( (string) $m['text'] );
		}
		/* translators: %s: site name */
		return (bool) wp_mail( $to, sprintf( __( 'Your chat with %s', 'chatbot' ), get_bloginfo( 'name' ) ), implode( "\n\n", $lines ) );
	}
}
