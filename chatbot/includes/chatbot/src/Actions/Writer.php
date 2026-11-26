<?php
/**
 * AI Action writer (owner, 25 November 2026: "I will also need AI to write a prompt for AI actions for
 * the admins. Could be in the preview window"). The AI Actions screen's preview panel has a "Write with
 * AI" tab: the admin describes the action (or asks to improve the open one) and the AI drafts its
 * three fields: title, AI Prompt (numbered questions with short example answers, the format the
 * engine follows best) and Reply when done. Nothing is saved here; the screen fills the fields and the
 * admin saves as usual.
 *
 * Request: admin-ajax action `qcld_wpbot_chat_ai_action_write` (manage_options, nonce with the same
 * name in `nonce`). Fields: mode (write|improve), description, title, prompt, reply. Answer:
 * { title, prompt, reply } or an error message. The site's own AI provider answers (Ai_Client raw,
 * usage feature `ai_action_writer`); WPBot Core's Business Context is added when it has a summary.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Actions;

use QuantumCloud\Chatbot\Ai\Ai_Client;
use QuantumCloud\Chatbot\Leads\Ai_Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Drafts AI Actions with the AI.
 */
final class Writer {

	/**
	 * The admin-ajax action and nonce name.
	 */
	const ACTION = 'qcld_wpbot_chat_ai_action_write';

	/**
	 * Longest description or field sent to the AI (characters).
	 */
	const MAX_INPUT = 4000;

	/**
	 * Hook in (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax' ) );
	}

	/**
	 * Whether the writer can be offered (an administrator).
	 *
	 * @return bool
	 */
	public static function available() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * The request handler.
	 *
	 * @return void
	 */
	public static function ajax() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'chatbot' ) ), 403 );
		}
		$field = static function ( $name, $text ) {
			$raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checked above; sanitized next.
			$raw = is_string( $raw ) ? $raw : '';
			$out = $text ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			return mb_substr( $out, 0, self::MAX_INPUT );
		};
		$mode  = 'improve' === $field( 'mode', false ) ? 'improve' : 'write';
		$input = array(
			'mode'        => $mode,
			'description' => $field( 'description', true ),
			'title'       => $field( 'title', false ),
			'prompt'      => $field( 'prompt', true ),
			'reply'       => $field( 'reply', true ),
		);
		if ( 'write' === $mode && '' === trim( $input['description'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Describe what the action should do first.', 'chatbot' ) ), 400 );
		}
		if ( 'improve' === $mode && '' === trim( $input['prompt'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Open an action with an AI Prompt to improve it.', 'chatbot' ) ), 400 );
		}
		$draft = self::draft( $input );
		if ( is_wp_error( $draft ) ) {
			wp_send_json_error( array( 'message' => $draft->get_error_message() ), 502 );
		}
		wp_send_json_success( $draft );
	}

	/**
	 * Ask the AI for a draft.
	 *
	 * @param array $input mode, description, title, prompt, reply.
	 * @return array|\WP_Error { title, prompt, reply }
	 */
	public static function draft( array $input ) {
		$request = self::request( $input );
		/**
		 * Answer the writer without calling the AI (tests): return the AI's text, or null to call it.
		 *
		 * @param string|null $text    Default null.
		 * @param string      $request What would be sent.
		 * @param array       $input   The admin's input.
		 */
		$text = apply_filters( 'qcld_wpbot_chat/pre_ai_action_write', null, $request, $input );
		if ( null === $text ) {
			$reply = Ai_Client::reply(
				$request,
				array(
					'raw'        => true,
					'json'       => true,
					'context'    => self::instructions(),
					'feature'    => 'ai_action_writer',
					'max_tokens' => 2500,
				)
			);
			if ( is_wp_error( $reply ) ) {
				return new \WP_Error( 'ai_failed', __( 'The AI could not write the action. Check AI Settings and try again.', 'chatbot' ) );
			}
			$text = (string) $reply['text'];
		}
		$draft = self::parse( (string) $text );
		if ( null === $draft ) {
			return new \WP_Error( 'bad_answer', __( 'The AI answer could not be read. Please try again.', 'chatbot' ) );
		}
		return $draft;
	}

	/**
	 * The writer's system instructions.
	 *
	 * @return string
	 */
	public static function instructions() {
		return implode(
			"\n",
			array(
				'You write AI Actions for a WordPress chatbot. An AI Action is a short guided conversation: the chatbot asks the visitor a few questions, one at a time, then answers using those answers. You write instructions FOR THE CHATBOT, not messages to the visitor.',
				'Return one JSON object with exactly these keys: "title", "prompt", "reply".',
				'- "title": the start menu button the visitor taps, 2 to 6 words, saying what they get (for example "Plan my event order", "Is our product right for me?"). No quotes or emoji.',
				'- "prompt": start with "Use this action when" and say when the chatbot should start it; then one sentence on tone (friendly, brief, ONE question at a time). Then the questions as a numbered list, one per line ("1. ...", "2. ..."), each with two to five short example answers in brackets. 3 to 8 questions; ask only what the reply needs. No greeting. Never ask for an email or phone number unless the owner asked for it.',
				'- "reply": instructions to the chatbot for its answer after the last question, written as commands ("Recommend ...", "Explain ...", "End with ..."): what to write, in what order, how to decide using the answers, and any rules (what never to promise, a link to end with). Never write the answer itself and never use sample values. Use facts from the business details when they help; never invent prices, plans, features or links that are not given.',
				'Example of the format (for a bike shop): {"title":"Find my bike","prompt":"Use this action when a visitor asks which bike to buy or wants a recommendation.\\nBe friendly and brief. Ask ONE question at a time.\\n1. Where will you ride most? (city, trails, road, mixed)\\n2. How tall are you? (under 160 cm, 160 to 180 cm, over 180 cm)\\n3. What is your budget? (under 500, 500 to 1,000, over 1,000)","reply":"Repeat their needs in one sentence. Recommend ONE bike type and frame size from their answers, with the main reason in two sentences. If their budget is tight, say what to prioritise. End by inviting them to visit the shop for a test ride."}',
				'Write in the language of the owner\'s description. Plain text inside the JSON strings (line breaks as \\n), no Markdown headings.',
			)
		);
	}

	/**
	 * The request text: the owner's description or the action to improve, and the business details.
	 *
	 * @param array $input mode, description, title, prompt, reply.
	 * @return string
	 */
	public static function request( array $input ) {
		$parts = array();
		if ( 'improve' === $input['mode'] ) {
			$parts[] = 'Improve this AI Action. Keep what it is for; make the questions clear and numbered with short example answers, and make the reply instructions complete.';
			$parts[] = 'Current title: ' . $input['title'] . "\nCurrent AI Prompt:\n" . $input['prompt'] . "\nCurrent Reply when done:\n" . ( '' !== trim( $input['reply'] ) ? $input['reply'] : '(empty)' );
			if ( '' !== trim( $input['description'] ) ) {
				$parts[] = "The owner's notes on what to change:\n" . $input['description'];
			}
		} else {
			$parts[] = "Write an AI Action from the owner's description:\n" . $input['description'];
		}
		$business = self::business();
		if ( '' !== $business ) {
			$parts[] = "Business details (from the site's Business Context):\n" . $business;
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * WPBot Core's Business Context as text ('' when it has none).
	 *
	 * @return string
	 */
	public static function business() {
		$class = '\QuantumCloud\WPBotCore\Context\Context';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'text' ) ) {
			return '';
		}
		$text = (string) call_user_func( array( $class, 'text' ) );
		return mb_substr( trim( $text ), 0, self::MAX_INPUT );
	}

	/**
	 * Read the AI's JSON answer (a fenced or wrapped object is accepted).
	 *
	 * @param string $text AI answer.
	 * @return array|null { title, prompt, reply }
	 */
	public static function parse( $text ) {
		$text = trim( (string) $text );
		if ( preg_match( '/\{[\s\S]*\}/', $text, $match ) ) {
			$text = $match[0];
		}
		$data = json_decode( $text, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$title  = isset( $data['title'] ) && is_scalar( $data['title'] ) ? trim( sanitize_text_field( (string) $data['title'] ) ) : '';
		$prompt = isset( $data['prompt'] ) && is_scalar( $data['prompt'] ) ? trim( sanitize_textarea_field( (string) $data['prompt'] ) ) : '';
		$reply  = isset( $data['reply'] ) && is_scalar( $data['reply'] ) ? trim( sanitize_textarea_field( (string) $data['reply'] ) ) : '';
		if ( '' === $prompt ) {
			return null;
		}
		return array(
			'title'  => mb_substr( $title, 0, 80 ),
			'prompt' => mb_substr( $prompt, 0, Ai_Forms::REPLY_MAX ),
			'reply'  => mb_substr( $reply, 0, Ai_Forms::REPLY_MAX ),
		);
	}
}
