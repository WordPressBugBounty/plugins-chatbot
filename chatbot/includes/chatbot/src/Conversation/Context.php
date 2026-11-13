<?php
/**
 * State shared by the steps while one message is answered.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation;

defined( 'ABSPATH' ) || exit;

/**
 * Pipeline context.
 */
final class Context {

	/**
	 * Called with each piece of text while an answer streams; null when not streaming.
	 *
	 * @var callable|null
	 */
	public $on_text = null;

	/**
	 * Notes earlier steps leave for later ones (e.g. search results for the AI).
	 *
	 * @var array
	 */
	public $notes = array();

	/**
	 * Steps that ran, with their outcome (for logs and tests).
	 *
	 * @var array
	 */
	public $trace = array();

	/**
	 * Only these steps run (empty = all). The legacy AJAX shims run one step each.
	 *
	 * @var string[]
	 */
	public $only = array();

	/**
	 * Constructor.
	 *
	 * @param callable|null $on_text Stream callback.
	 */
	public function __construct( $on_text = null ) {
		$this->on_text = is_callable( $on_text ) ? $on_text : null;
	}

	/**
	 * Whether the answer streams.
	 *
	 * @return bool
	 */
	public function streaming() {
		return null !== $this->on_text;
	}
}
