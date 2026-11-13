<?php
/**
 * One visitor message on its way through the pipeline.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation;

defined( 'ABSPATH' ) || exit;

/**
 * Visitor message.
 */
final class Message {

	/**
	 * Most characters accepted in one visitor message.
	 */
	const MAX_LENGTH = 4000;

	/**
	 * Text as typed (sanitized, trimmed).
	 *
	 * @var string
	 */
	public $text = '';

	/**
	 * Session id (the widget's botsessionid or a channel's sender id).
	 *
	 * @var string
	 */
	public $session_id = '';

	/**
	 * Visitor's name, when given.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Locale the widget runs in (en_US, ...).
	 *
	 * @var string
	 */
	public $language = '';

	/**
	 * Channel: web, messenger, whatsapp, telegram, ...
	 *
	 * @var string
	 */
	public $channel = 'web';

	/**
	 * Earlier messages: role (user|assistant) and content.
	 *
	 * @var array[]
	 */
	public $history = array();

	/**
	 * Page the visitor is on.
	 *
	 * @var string
	 */
	public $page_url = '';

	/**
	 * Page title.
	 *
	 * @var string
	 */
	public $page_title = '';

	/**
	 * Extra input: strid (a chosen answer), intent, ai_action, stream, ...
	 *
	 * @var array
	 */
	public $extra = array();

	/**
	 * Build from request-like input. Every field is sanitized here.
	 *
	 * @param array $input text, session_id, name, language, channel, history, page_url, page_title, extra.
	 * @return Message
	 */
	public static function from_array( array $input ) {
		$message             = new self();
		$text                = isset( $input['text'] ) ? (string) $input['text'] : '';
		$message->text       = self::clip( trim( sanitize_textarea_field( $text ) ) );
		$message->session_id = isset( $input['session_id'] ) ? substr( preg_replace( '/[^A-Za-z0-9_\-.:]/', '', (string) $input['session_id'] ), 0, 128 ) : '';
		$message->name       = isset( $input['name'] ) ? substr( sanitize_text_field( (string) $input['name'] ), 0, 100 ) : '';
		$message->language   = isset( $input['language'] ) ? substr( preg_replace( '/[^A-Za-z_\-]/', '', (string) $input['language'] ), 0, 20 ) : '';
		$message->channel    = isset( $input['channel'] ) ? sanitize_key( (string) $input['channel'] ) : 'web';
		$message->page_url   = isset( $input['page_url'] ) ? substr( esc_url_raw( (string) $input['page_url'] ), 0, 2000 ) : '';
		$message->page_title = isset( $input['page_title'] ) ? substr( sanitize_text_field( (string) $input['page_title'] ), 0, 200 ) : '';
		$message->history    = isset( $input['history'] ) && is_array( $input['history'] ) ? self::clean_history( $input['history'] ) : array();
		$message->extra      = isset( $input['extra'] ) && is_array( $input['extra'] ) ? array_map( 'sanitize_text_field', array_filter( $input['extra'], 'is_scalar' ) ) : array();
		if ( '' === $message->channel ) {
			$message->channel = 'web';
		}
		return $message;
	}

	/**
	 * Clean history entries: role and plain text, at most 20.
	 *
	 * @param array $history Raw history.
	 * @return array[]
	 */
	public static function clean_history( array $history ) {
		$clean = array();
		foreach ( $history as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$role = isset( $item['role'] ) ? (string) $item['role'] : '';
			if ( 'bot' === $role ) {
				$role = 'assistant';
			}
			$content = isset( $item['content'] ) ? (string) $item['content'] : ( isset( $item['text'] ) ? (string) $item['text'] : '' );
			$content = self::clip( trim( wp_strip_all_tags( $content ) ) );
			if ( in_array( $role, array( 'user', 'assistant' ), true ) && '' !== $content ) {
				$clean[] = array(
					'role'    => $role,
					'content' => $content,
				);
			}
		}
		return array_slice( $clean, -20 );
	}

	/**
	 * Extra input value.
	 *
	 * @param string $key     Key.
	 * @param string $fallback_value Default.
	 * @return string
	 */
	public function extra( $key, $fallback_value = '' ) {
		return isset( $this->extra[ $key ] ) ? (string) $this->extra[ $key ] : $fallback_value;
	}

	/**
	 * Cut text to MAX_LENGTH characters.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function clip( $text ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_LENGTH ) : substr( $text, 0, self::MAX_LENGTH );
	}
}
