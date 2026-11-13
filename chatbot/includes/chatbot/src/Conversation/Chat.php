<?php
/**
 * Answers a visitor message end to end: history from the stored session,
 * the pipeline, and the transcript (structured messages plus the legacy rows).
 * REST, the legacy AJAX shims and the channels all go through here.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation;

use QuantumCloud\Chatbot\Sessions\Session_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Chat service.
 */
final class Chat {

	/**
	 * Answer a message.
	 *
	 * @param Message       $message Message.
	 * @param callable|null $on_text Stream callback.
	 * @param Pipeline|null $pipeline Pipeline (tests); default steps otherwise.
	 * @param array         $visitor name, email, phone, source_url, user_agent for the legacy session row.
	 * @return Reply
	 */
	public static function answer( Message $message, $on_text = null, $pipeline = null, array $visitor = array() ) {
		$pipeline = $pipeline instanceof Pipeline ? $pipeline : new Pipeline();
		if ( '' !== $message->session_id ) {
			$stored = self::history( $message->session_id );
			if ( $stored ) {
				$message->history = $stored;
				// The server's transcript wins over what the browser sent.
			}
			Session_Store::add(
				$message->session_id,
				'user',
				$message->text,
				array(
					'channel' => $message->channel,
					'payload' => '' !== $message->page_url ? array( 'page_url' => $message->page_url ) : array(),
				)
			);
		}

		$context = new Context( $on_text );
		$reply   = $pipeline->run( $message, $context );

		if ( '' !== $message->session_id ) {
			Session_Store::add(
				$message->session_id,
				'bot',
				'' !== $reply->html ? $reply->html : $reply->text,
				array(
					'channel'    => $message->channel,
					'source'     => $reply->source,
					'payload'    => isset( $context->notes['debug'] ) ? array_merge( $reply->data, array( 'debug' => $context->notes['debug'] ) ) : $reply->data,
					'model'      => isset( $reply->meta['model'] ) ? $reply->meta['model'] : '',
					'tokens_in'  => isset( $reply->meta['tokens_in'] ) ? $reply->meta['tokens_in'] : 0,
					'tokens_out' => isset( $reply->meta['tokens_out'] ) ? $reply->meta['tokens_out'] : 0,
					'provider'   => isset( $reply->meta['provider'] ) ? $reply->meta['provider'] : '',
					'cost'       => isset( $reply->meta['cost'] ) ? $reply->meta['cost'] : 0.0,
				)
			);
			Session_Store::save_legacy(
				$message->session_id,
				array_merge(
					array(
						'name'       => $message->name,
						'source_url' => $message->page_url,
						'rate_key'   => 'web' === $message->channel ? null : $message->channel . ':' . $message->session_id,
					),
					$visitor
				)
			);
		}//end if
		return $reply;
	}

	/**
	 * Stored messages as AI history (user/assistant, plain text, last 10 before the new one).
	 *
	 * @param string $session_id Session.
	 * @return array[]
	 */
	public static function history( $session_id ) {
		$history = array();
		foreach ( Session_Store::messages( $session_id ) as $row ) {
			$history[] = array(
				'role'    => 'user' === $row['role'] ? 'user' : 'assistant',
				'content' => trim( wp_strip_all_tags( (string) $row['text'] ) ),
			);
		}
		return Message::clean_history( array_slice( $history, -10 ) );
	}
}
