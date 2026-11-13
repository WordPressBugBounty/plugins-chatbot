<?php
/**
 * AI answers for the messaging channels (Telegram, WhatsApp, Messenger).
 *
 * Channels keep their own menus, Simple Text Responses and forms; for free
 * text they ask the engine here: the AI provider that is switched on (not
 * only OpenAI), the knowledge base, the rate limit and the session store
 * (session "{channel}-{sender}", so history carries over between messages).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation;

use QuantumCloud\Chatbot\Ai\Provider_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Channel answers.
 */
final class Channel {

	/**
	 * Whether the engine can answer (a provider is on).
	 *
	 * @return bool
	 */
	public static function ai_available() {
		return '' !== Provider_Settings::answering_provider();
	}

	/**
	 * Ask the AI (with the knowledge base) for a channel message.
	 *
	 * @param string $text    Visitor text.
	 * @param string $sender  Sender id on the channel (chat id, phone number, PSID).
	 * @param string $channel telegram, whatsapp, messenger, instagram.
	 * @param string $name    Sender's name, when known.
	 * @param array  $extra   Optional scalar values carried on the message (`Message::$extra`), for example
	 *                        the channel's `event_id` and `account_remote_id`, so `qcld_wpbot_chat/message_sent`
	 *                        listeners can tell which inbound item the bot answered.
	 * @return string Plain text answer; '' when the AI did not answer (caller falls back).
	 */
	public static function ai_reply( $text, $sender, $channel, $name = '', array $extra = array() ) {
		if ( ! self::ai_available() ) {
			return '';
		}
		$message = self::message( $text, $sender, $channel, $name, $extra );
		if ( '' === $message->text ) {
			return '';
		}
		$reply = Chat::answer( $message, null, new Pipeline( array( new Steps\Knowledge_Step(), new Steps\Ai_Step() ) ) );
		return 'ai' === $reply->source ? self::plain( $reply->text ) : '';
	}

	/**
	 * The engine message for a channel item (session "{channel}-{sender}").
	 *
	 * @param string $text    Visitor text.
	 * @param string $sender  Sender id on the channel.
	 * @param string $channel Channel id.
	 * @param string $name    Sender's name, when known.
	 * @param array  $extra   Scalar extra values (see ai_reply()).
	 * @return Message
	 */
	public static function message( $text, $sender, $channel, $name = '', array $extra = array() ) {
		$session = sanitize_key( $channel ) . '-' . substr( preg_replace( '/[^A-Za-z0-9_\-.:]/', '', (string) $sender ), 0, 100 );
		return Message::from_array(
			array(
				'text'       => (string) $text,
				'session_id' => $session,
				'name'       => (string) $name,
				'channel'    => (string) $channel,
				'extra'      => $extra,
			)
		);
	}

	/**
	 * Markdown to plain text for chat apps (bold/italics markers, links as "text (url)").
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		$text = preg_replace( '/\[([^\]]+)\]\((https?:[^)\s]+)\)/', '$1 ($2)', (string) $text );
		$text = preg_replace( '/(\*\*|__)(.+?)\1/s', '$2', (string) $text );
		$text = preg_replace( '/^#{1,6}\s+/m', '', (string) $text );
		return trim( wp_strip_all_tags( (string) $text ) );
	}
}
