<?php
/**
 * The visitor's access labels for knowledge base searches (chatbot features CB1c).
 *
 * Everyone holds `public`. A logged-in visitor on the website also holds `logged_in` and
 * `role:<role>` for each role. Membership, course and store adapters add more labels
 * (`membership:*`, `course:*`, `purchased:*`) through the filter. Knowledge with labels the
 * visitor does not hold is left out of the search before scoring (WPBot Core Kb_Access).
 *
 * Messages from other channels (Messenger, WhatsApp, Telegram…) have no WordPress user, so
 * they only see public knowledge unless a filter says otherwise.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Knowledge;

use QuantumCloud\Chatbot\Conversation\Message;

defined( 'ABSPATH' ) || exit;

/**
 * Visitor labels.
 */
final class Visitor_Labels {

	/**
	 * Channels where the WordPress user is the visitor.
	 */
	const WEB_CHANNELS = array( 'web', 'rest', '' );

	/**
	 * Labels for a message.
	 *
	 * @param Message $message Message.
	 * @return string[]
	 */
	public static function for_message( Message $message ) {
		$labels = array( 'public' );
		$user   = null;
		if ( in_array( (string) $message->channel, self::WEB_CHANNELS, true ) && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$user     = wp_get_current_user();
			$labels[] = 'logged_in';
			foreach ( (array) $user->roles as $role ) {
				$labels[] = 'role:' . sanitize_key( $role );
			}
		}
		/**
		 * The visitor's access labels (add membership:*, course:*, purchased:* …).
		 *
		 * @param string[]      $labels  Labels.
		 * @param Message       $message Message.
		 * @param \WP_User|null $user    WordPress user (null for guests and other channels).
		 */
		$labels = (array) apply_filters( 'qcld_wpbot_chat/visitor_labels', $labels, $message, $user );
		return array_values( array_unique( array_filter( array_map( 'strval', $labels ), 'strlen' ) ) );
	}
}
