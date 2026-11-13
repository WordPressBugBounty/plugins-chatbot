<?php
/**
 * Admin URLs of the spending screens (the host decides where they live).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use QuantumCloud\WPBotCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * URL helpers.
 */
final class Spend_Urls {

	/**
	 * AI Settings > Spending and limits, optionally at a group's row.
	 *
	 * @param string $group Group id ('' for the top).
	 * @return string
	 */
	public static function settings( $group = '' ) {
		$host = Core::instance()->host();
		$base = $host ? (string) $host->get_ai_settings_url() : '';
		$url  = '' !== $base ? add_query_arg( 'section', 'spending', $base ) : admin_url();

		/**
		 * URL of the Spending and limits screen.
		 *
		 * @param string $url   Default: the host's AI Settings with section=spending.
		 * @param string $group Group id or ''.
		 */
		$url = (string) apply_filters( 'qcld_wpbot_core/spend_settings_url', $url, $group );
		return '' !== $group ? $url . '#spend-' . sanitize_key( $group ) : $url;
	}

	/**
	 * The AI Usage report.
	 *
	 * @return string
	 */
	public static function report() {
		/**
		 * URL of the AI Usage report page (hosts register the page).
		 *
		 * @param string $url Default: the Spending and limits screen.
		 */
		return (string) apply_filters( 'qcld_wpbot_core/spend_report_url', self::settings() );
	}
}
