<?php
/**
 * The chatbot at its AI spending limit: it falls back quietly.
 *
 * When WPBot Core's spending limits (AI Settings > Spending and limits) stop the chatbot's AI,
 * visitors never see an error or the word budget. The chatbot answers from Simple Text
 * Responses, the FAQ and site search; with no match it runs the no-answer flow, with the
 * owner's optional message. The site admin is alerted by WPBot Core.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Spend;

use QuantumCloud\Chatbot\Conversation\Context;
use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Conversation\Pipeline;
use QuantumCloud\Chatbot\Conversation\Steps;

defined( 'ABSPATH' ) || exit;

/**
 * Quiet fallback.
 */
final class Fallback {

	/**
	 * Option: the owner's message while AI is paused ('' = only the no-answer flow).
	 */
	const MESSAGE = 'qcld_wpbot_chat_spend_message';

	/**
	 * Whether a limit stopped the chatbot's AI in this request.
	 *
	 * @var bool
	 */
	private static $limited = false;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/spend_fallback_reply', array( __CLASS__, 'filter_reply' ), 10, 2 );
	}

	/**
	 * Whether an error is a spending-limit refusal.
	 *
	 * @param mixed $error Value.
	 * @return bool
	 */
	public static function is_limit( $error ) {
		return is_wp_error( $error ) && 'qcld_wpbot_core_budget_reached' === $error->get_error_code();
	}

	/**
	 * Note that a limit stopped the AI in this request.
	 *
	 * @param Context|null $context Conversation context.
	 * @return void
	 */
	public static function mark( $context = null ) {
		self::$limited = true;
		if ( $context instanceof Context ) {
			$context->notes['spend_limit'] = 1;
		}
	}

	/**
	 * Whether a limit stopped the AI in this request.
	 *
	 * @return bool
	 */
	public static function limited() {
		return self::$limited;
	}

	/**
	 * The owner's message while AI is paused.
	 *
	 * @return string
	 */
	public static function message() {
		return (string) get_option( self::MESSAGE, '' );
	}

	/**
	 * Save the owner's message.
	 *
	 * @param string $text Text (basic HTML allowed).
	 * @return void
	 */
	public static function save_message( $text ) {
		$text = function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, 500 ) : substr( (string) $text, 0, 500 );
		update_option( self::MESSAGE, wp_kses_post( $text ), false );
	}

	/**
	 * Answer without AI: saved answers, site search, then the no-answer flow.
	 *
	 * @param Message $message Visitor's message.
	 * @return string Reply text.
	 */
	public static function answer( Message $message ) {
		$context = new Context();
		self::mark( $context );
		$steps = array( new Steps\Str_Step(), new Steps\Search_Step(), new Steps\Fallback_Step() );
		$reply = ( new Pipeline( $steps ) )->run( $message, $context );
		return $reply ? (string) $reply->text : self::no_answer_text( $message->language );
	}

	/**
	 * The no-answer text while AI is paused: the owner's message, else the usual "no result".
	 *
	 * @param string $language Language.
	 * @return string
	 */
	public static function no_answer_text( $language = '' ) {
		$own = trim( wp_strip_all_tags( self::message() ) );
		if ( '' !== $own ) {
			return $own;
		}
		return \QuantumCloud\Chatbot\Settings\Options::text( 'qlcd_wp_chatbot_no_result', (string) $language, __( 'Sorry, No result found!', 'chatbot' ) );
	}

	/**
	 * Filter for host code (legacy handlers): the reply while AI is paused.
	 *
	 * @param string $default_reply Host default.
	 * @param string $question Visitor's message.
	 * @return string
	 */
	public static function filter_reply( $default_reply, $question ) {
		if ( '' === trim( (string) $question ) ) {
			return self::no_answer_text();
		}
		return self::answer( Message::from_array( array( 'text' => (string) $question ) ) );
	}

	/**
	 * Forget the request state (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$limited = false;
	}
}
