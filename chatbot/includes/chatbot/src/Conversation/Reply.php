<?php
/**
 * The bot's answer to one message.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation;

defined( 'ABSPATH' ) || exit;

/**
 * Bot reply.
 */
final class Reply {

	/**
	 * Which step answered: str, search, ai, dialogflow, fallback, or a step id added by an extension.
	 *
	 * @var string
	 */
	public $source = '';

	/**
	 * Plain or Markdown text (AI, fallback).
	 *
	 * @var string
	 */
	public $text = '';

	/**
	 * Safe HTML (site search cards, Simple Text Responses).
	 *
	 * @var string
	 */
	public $html = '';

	/**
	 * Structured payload (STR matches, search results, buttons, ...).
	 *
	 * @var array
	 */
	public $data = array();

	/**
	 * Provider, model, token counts.
	 *
	 * @var array
	 */
	public $meta = array();

	/**
	 * Make a reply.
	 *
	 * @param string $source Step id.
	 * @param array  $fields text, html, data, meta.
	 * @return Reply
	 */
	public static function make( $source, array $fields = array() ) {
		$reply         = new self();
		$reply->source = (string) $source;
		$reply->text   = isset( $fields['text'] ) ? (string) $fields['text'] : '';
		$reply->html   = isset( $fields['html'] ) ? (string) $fields['html'] : '';
		$reply->data   = isset( $fields['data'] ) ? (array) $fields['data'] : array();
		$reply->meta   = isset( $fields['meta'] ) ? (array) $fields['meta'] : array();
		return $reply;
	}

	/**
	 * Text for the transcript: the text, or the HTML with tags removed.
	 *
	 * @return string
	 */
	public function plain() {
		return '' !== $this->text ? $this->text : trim( wp_strip_all_tags( $this->html ) );
	}

	/**
	 * For JSON responses.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'source' => $this->source,
			'text'   => $this->text,
			'html'   => $this->html,
			'data'   => $this->data,
			'meta'   => $this->meta,
		);
	}
}
