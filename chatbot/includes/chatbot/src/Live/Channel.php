<?php
/**
 * An agent channel (Slack, Telegram, Microsoft Teams…): live chat plan, "Free and Pro". Adapters
 * implement this interface and register on `qcld_wpbot_chat/live_channels`; the engine sends them
 * every event in the background. Their inbound side (an agent's reply) calls
 * Conversations::message() with role "agent".
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

interface Channel {

	/**
	 * Channel id (slack, telegram, teams).
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Whether the channel is set up and switched on.
	 *
	 * @return bool
	 */
	public function enabled();

	/**
	 * Handle a live chat event (runs in the background, never in the visitor's request).
	 *
	 * @param array $event        Event (Events::shape()).
	 * @param array $conversation Conversation row.
	 * @return void
	 */
	public function handle( array $event, array $conversation );
}
