<?php
/**
 * Fallback: nothing answered. Logs the question for the owner (the
 * "failed responses" list) and says the no-result text.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation\Steps;

use QuantumCloud\Chatbot\Conversation\Context;
use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Conversation\Reply;
use QuantumCloud\Chatbot\Conversation\Step;
use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Fallback step.
 */
final class Fallback_Step implements Step {

	/**
	 * Step id.
	 *
	 * @return string
	 */
	public function id() {
		return 'fallback';
	}

	/**
	 * Always on.
	 *
	 * @return bool
	 */
	public function enabled() {
		return true;
	}

	/**
	 * Log and answer.
	 *
	 * @param Message $message Message.
	 * @param Context $context Context.
	 * @return Reply
	 */
	public function handle( Message $message, Context $context ) {
		self::log( $message->text );
		$ai_failed = isset( $context->notes['ai_error'] ) && '' !== (string) $context->notes['ai_error'];
		if ( ! $ai_failed ) {
			/** This action is documented in src/Conversation/Steps/Ai_Step.php */
			do_action( 'qcld_wpbot_chat/knowledge_gap', $message->text, 'no_answer', $message );
		}
		if ( ! empty( $context->notes['spend_limit'] ) ) {
			// AI paused by a spending limit: the owner's message or the usual no-answer text.
			$text = \QuantumCloud\Chatbot\Spend\Fallback::no_answer_text( $message->language );
		} else {
			$text = $ai_failed ? self::ai_error_text() : Options::text( 'qlcd_wp_chatbot_no_result', $message->language, __( 'Sorry, No result found!', 'chatbot' ) );
		}
		return Reply::make(
			'fallback',
			array(
				'text' => wp_strip_all_tags( $text ),
				'data' => array( 'reason' => self::reason( $context ) ),
			)
		);
	}

	/**
	 * Text when the AI provider failed (wrong key, no credit, provider down): the host's friendly
	 * message (AI Settings > Common AI Settings), the same one the chat widget shows.
	 *
	 * @return string
	 */
	public static function ai_error_text() {
		$text = trim( (string) get_option( 'qcld_wpbot_ai_error_text', '' ) );
		if ( '' === $text ) {
			$text = __( "Sorry, I can't answer that right now. Please try again in a little while.", 'chatbot' );
		}
		return (string) apply_filters( 'qcld_wpbot_ai_friendly_error', $text ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The hosts' public filter for this text (WPBot Free and Pro), so one filter changes it everywhere.
	}

	/**
	 * Reason given with the reply: the provider's error code only for site admins.
	 *
	 * @param Context $context Context.
	 * @return string
	 */
	public static function reason( Context $context ) {
		if ( ! isset( $context->notes['ai_error'] ) || '' === (string) $context->notes['ai_error'] ) {
			return 'no_match';
		}
		return current_user_can( 'manage_options' ) ? (string) $context->notes['ai_error'] : 'ai_unavailable';
	}

	/**
	 * Count an unanswered question in wpbot_failed_response.
	 *
	 * @param string $text Question.
	 * @return void
	 */
	public static function log( $text ) {
		global $wpdb;
		$text = trim( (string) $text );
		$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 256 ) : substr( $text, 0, 256 );
		if ( '' === $text ) {
			return;
		}
		$table = $wpdb->prefix . 'wpbot_failed_response';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE `query` = %s LIMIT 1', $table, $text ) );
		if ( $id ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `count` = `count` + 1 WHERE id = %d', $table, (int) $id ) );
		} else {
			$wpdb->insert(
				$table,
				array(
					'query'  => $text,
					'count'  => 1,
					'status' => 0,
				),
				array( '%s', '%d', '%d' )
			);
		}
		// phpcs:enable
	}
}
