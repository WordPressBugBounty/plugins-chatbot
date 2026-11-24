<?php
/**
 * Live chat availability and routing (plan 4.3). The engine counts the agents online in the
 * dashboard; WPBot Pro adds departments, office hours and the agent channels (a Slack, Telegram or
 * Teams team counts as available during its office hours).
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

/**
 * Availability and routing.
 */
final class Routing {

	/**
	 * Whether a person can answer now (decides between a live chat and "Leave a message").
	 *
	 * @param string $department Department ('' = any).
	 * @return bool
	 */
	public static function available( $department = '' ) {
		$available = (bool) Agents::online( $department );
		/**
		 * Whether a person can answer now.
		 *
		 * @param bool   $available  Default: an agent is online in the dashboard.
		 * @param string $department Department.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/live_available', $available, (string) $department );
	}

	/**
	 * The agent a new conversation goes to straight away (0: it waits in the queue for the first
	 * agent who takes it). WPBot Pro routes by department and load.
	 *
	 * @param array $conv Conversation row.
	 * @return int
	 */
	public static function pick( array $conv ) {
		/**
		 * Agent a new conversation is assigned to (0 = queue).
		 *
		 * @param int   $agent_id Default 0.
		 * @param array $conv     Conversation.
		 */
		return (int) apply_filters( 'qcld_wpbot_chat/live_route', 0, $conv );
	}
}
