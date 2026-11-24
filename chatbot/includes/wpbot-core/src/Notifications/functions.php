<?php
/**
 * Public notification email function for modules, hosts and add-ons.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_notification_emails' ) ) {
	/**
	 * The addresses WPBot's notification emails go to (AI / Settings → Notifications): the saved
	 * list, else the suggested one (WordPress's administration email, the hosts' staff addresses
	 * and every administrator).
	 *
	 * @return string[]
	 */
	function qcld_wpbot_core_notification_emails() {
		return \QuantumCloud\WPBotCore\Notifications\Notification_Emails::emails();
	}
}
