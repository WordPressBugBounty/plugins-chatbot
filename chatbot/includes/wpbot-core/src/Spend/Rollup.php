<?php
/**
 * Retention: per-call rows are kept for the owner's number of days (default 90), daily totals
 * for their number of months (default 13). Runs daily, in small batches.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Pruning.
 */
final class Rollup {

	/**
	 * Rows removed per query.
	 */
	const BATCH = 5000;

	/**
	 * Remove rows older than the retention settings.
	 *
	 * @param int|null $now Unix time (tests).
	 * @return array{calls: int, days: int} Rows removed.
	 */
	public static function prune( $now = null ) {
		global $wpdb;
		$settings = Spend_Settings::all();
		$now      = null === $now ? time() : (int) $now;
		$calls_to = gmdate( 'Y-m-d H:i:s', $now - (int) $settings['retention_days'] * DAY_IN_SECONDS );
		$days_to  = gmdate( 'Y-m-d', strtotime( '-' . (int) $settings['retention_months'] . ' months', $now ) );
		$usage    = Schema::table( Schema::USAGE );
		$daily    = Schema::table( Spend_Schema::DAILY );
		$removed  = array(
			'calls' => 0,
			'days'  => 0,
		);
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own tables; retention.
		for ( $i = 0; $i < 20; $i++ ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
			$n                 = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$usage} WHERE created_at < %s LIMIT %d", $calls_to, self::BATCH ) );
			$removed['calls'] += $n;
			if ( $n < self::BATCH ) {
				break;
			}
		}
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$removed['days'] = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$daily} WHERE day < %s", $days_to ) );
		// phpcs:enable
		return $removed;
	}
}
