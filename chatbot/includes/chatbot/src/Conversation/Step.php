<?php
/**
 * One step of the conversation pipeline.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation;

defined( 'ABSPATH' ) || exit;

/**
 * Pipeline step. Steps run in order; the first that returns a Reply answers.
 */
interface Step {

	/**
	 * Step id (str, search, ai, ...).
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Whether the step runs on this site (its settings are on).
	 *
	 * @return bool
	 */
	public function enabled();

	/**
	 * Answer the message, or return null to pass it to the next step.
	 *
	 * @param Message $message Visitor message.
	 * @param Context $context Shared state for this message (stream callback, notes from earlier steps).
	 * @return Reply|null
	 */
	public function handle( Message $message, Context $context );
}
