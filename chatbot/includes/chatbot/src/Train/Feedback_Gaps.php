<?php
/**
 * Train gets two more kinds of unanswered questions (owner, 14 November 2026):
 *
 * - "Thumbs down": a visitor disliked an answer. The widget sends the question that came before
 *   the answer with its feedback (`question`); Leads::feedback() passes it here.
 * - "Asked for a person": a visitor asks for a human ("talk to a person", "real agent"). The
 *   question they asked just before is the one the bot did not help with.
 *
 * Both go through `qcld_wpbot_chat/knowledge_gap`, like the engine's own gaps.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Train;

use QuantumCloud\Chatbot\Conversation\Message;

defined( 'ABSPATH' ) || exit;

/**
 * Thumbs-down and "asked for a person" gaps.
 */
final class Feedback_Gaps {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'qcld_wpbot_chat/message_received', array( __CLASS__, 'on_message' ) );
	}

	/**
	 * A visitor disliked an answer.
	 *
	 * @param string $question The visitor's question before the disliked answer.
	 * @return void
	 */
	public static function disliked( $question ) {
		$question = trim( (string) $question );
		if ( '' === $question ) {
			return;
		}
		/** This action is documented in src/Conversation/Steps/Ai_Step.php */
		do_action( 'qcld_wpbot_chat/knowledge_gap', $question, 'thumbs_down', null );
	}

	/**
	 * A visitor message: when it asks for a person, the question before it goes to Train.
	 *
	 * @param Message $message Message.
	 * @return void
	 */
	public static function on_message( $message ) {
		if ( ! $message instanceof Message || ! self::asks_for_person( $message->text ) ) {
			return;
		}
		$question = self::previous_question( $message->history );
		if ( '' === $question || self::asks_for_person( $question ) ) {
			return;
		}
		/** This action is documented in src/Conversation/Steps/Ai_Step.php */
		do_action( 'qcld_wpbot_chat/knowledge_gap', $question, 'handoff', $message );
	}

	/**
	 * Whether a message asks for a human.
	 *
	 * @param string $text Message.
	 * @return bool
	 */
	public static function asks_for_person( $text ) {
		$text = strtolower( trim( (string) $text ) );
		if ( '' === $text ) {
			return false;
		}
		$pattern = '/\b(talk|speak|chat|connect me)\s+(to|with)\s+(a |an |the |your |some )?(real |live )?(person|human|agent|operator|representative|someone|somebody|staff|support team|customer service)\b'
			. '|\b(real|live)\s+(person|human|agent|operator)\b'
			. '|\b(need|want)\s+(a |an )?(human|person|agent|operator|representative)\b'
			. '|^\s*(human|agent|operator|representative|customer service)\s*[.!?]*\s*$/';
		/**
		 * The pattern that finds a visitor asking for a person (lower-case text).
		 *
		 * @param string $pattern Regular expression.
		 */
		$pattern = (string) apply_filters( 'qcld_wpbot_chat/person_request_pattern', $pattern );
		return (bool) @preg_match( $pattern, $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A filtered pattern may be invalid.
	}

	/**
	 * The visitor's last question in the history.
	 *
	 * @param array $history role, content.
	 * @return string
	 */
	public static function previous_question( array $history ) {
		for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
			if ( isset( $history[ $i ]['role'], $history[ $i ]['content'] ) && 'user' === $history[ $i ]['role'] ) {
				return trim( (string) $history[ $i ]['content'] );
			}
		}
		return '';
	}
}
