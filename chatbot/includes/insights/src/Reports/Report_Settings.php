<?php
/**
 * Email report settings: on by default, weekly (Monday) or monthly (the
 * 1st), sent to the admin email from Settings > General until the owner
 * changes the list.
 *
 * The shape matches the Pro report settings (enabled, frequency, day,
 * recipients), so a feature that replaces this report can read them.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Reports;

defined( 'ABSPATH' ) || exit;

final class Report_Settings {

	const OPTION        = 'qcin_reports';
	const LAST_OPTION   = 'qcin_report_last';
	const RESULT_OPTION = 'qcin_report_result';
	const CHECK_OPTION  = 'qcin_report_checked';
	const SINCE_OPTION  = 'qcin_report_since';
	const CATCH_UP_DAYS = 2;
	const MAX_TO        = 10;

	/**
	 * Saved settings with defaults.
	 *
	 * @return array{enabled:bool,frequency:string,day:int,recipients:string}
	 */
	public static function all() {
		$s = get_option( self::OPTION, array() );
		$s = is_array( $s ) ? $s : array();
		return array(
			'enabled'    => isset( $s['enabled'] ) ? (bool) $s['enabled'] : true,
			'frequency'  => ( $s['frequency'] ?? '' ) === 'monthly' ? 'monthly' : 'weekly',
			'day'        => isset( $s['day'] ) ? max( 0, min( 6, (int) $s['day'] ) ) : 1,
			'recipients' => isset( $s['recipients'] ) && '' !== trim( (string) $s['recipients'] ) ? (string) $s['recipients'] : (string) get_option( 'admin_email' ),
		);
	}

	/**
	 * Settings for the admin screen, with the last send and the next one.
	 *
	 * @return array<string,mixed>
	 */
	public static function public_view() {
		$s      = self::all();
		$last   = (int) get_option( self::LAST_OPTION, 0 );
		$result = get_option( self::RESULT_OPTION, array() );
		$next   = $s['enabled'] ? self::next_send( time() ) : 0;
		return array_merge(
			$s,
			array(
				'adminEmail' => (string) get_option( 'admin_email' ),
				'lastSent'   => $last ? gmdate( 'c', $last ) : '',
				'nextSend'   => $next ? gmdate( 'c', $next ) : '',
				'lastResult' => is_array( $result ) ? $result : array(),
			)
		);
	}

	/**
	 * Validate and save.
	 *
	 * @param array<string,mixed> $in enabled, frequency, day, recipients.
	 * @return array<string,mixed>|\WP_Error Public view.
	 */
	public static function update( array $in ) {
		$cur    = self::all();
		$stored = get_option( self::OPTION, array() );
		$raw    = array_key_exists( 'recipients', $in ) ? (string) $in['recipients'] : (string) ( is_array( $stored ) ? ( $stored['recipients'] ?? '' ) : '' );
		// Empty means the admin email from Settings > General (it follows changes there).
		$list = '' === trim( $raw ) ? array() : self::parse_list( $raw );
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		update_option(
			self::OPTION,
			array(
				'enabled'    => array_key_exists( 'enabled', $in ) ? (bool) rest_sanitize_boolean( $in['enabled'] ) : $cur['enabled'],
				'frequency'  => ( $in['frequency'] ?? $cur['frequency'] ) === 'monthly' ? 'monthly' : 'weekly',
				'day'        => max( 0, min( 6, (int) ( $in['day'] ?? $cur['day'] ) ) ),
				'recipients' => implode( ', ', $list ),
			),
			false
		);
		return self::public_view();
	}

	/**
	 * Valid addresses from a list separated by commas, semicolons or spaces.
	 *
	 * @param string $raw List.
	 * @return string[]|\WP_Error
	 */
	public static function parse_list( $raw ) {
		$out = array();
		foreach ( (array) preg_split( '/[\s,;]+/', (string) $raw ) as $a ) {
			$a = trim( (string) $a );
			if ( '' === $a ) {
				continue;
			}
			if ( ! is_email( $a ) || sanitize_email( $a ) !== $a ) {
				return new \WP_Error(
					'qcin_report_email',
					/* translators: %s: email address */
					sprintf( __( '"%s" is not a valid email address.', 'qcin-textdomain' ), $a ),
					array( 'status' => 400 )
				);
			}
			$out[] = $a;
		}
		$out = array_values( array_unique( $out ) );
		if ( ! $out ) {
			return new \WP_Error( 'qcin_report_email', __( 'Add at least one email address.', 'qcin-textdomain' ), array( 'status' => 400 ) );
		}
		if ( count( $out ) > self::MAX_TO ) {
			return new \WP_Error(
				'qcin_report_email',
				/* translators: %d: maximum number of addresses */
				sprintf( __( 'Use at most %d addresses.', 'qcin-textdomain' ), self::MAX_TO ),
				array( 'status' => 400 )
			);
		}
		return $out;
	}

	/**
	 * The most recent scheduled day at or before $now (site time, 00:00),
	 * or null when it is too long ago to catch up.
	 *
	 * WP-Cron only runs when someone visits the site, so on a quiet site
	 * the job may miss the chosen day. A report missed that way still goes
	 * out on the next run, up to CATCH_UP_DAYS days later.
	 *
	 * @param int $now Timestamp.
	 * @return \DateTimeImmutable|null
	 */
	public static function scheduled_day( $now ) {
		$s     = self::all();
		$today = ( new \DateTimeImmutable( '@' . (int) $now ) )->setTimezone( wp_timezone() )->setTime( 0, 0 );
		if ( 'monthly' === $s['frequency'] ) {
			$day  = $today->modify( 'first day of this month' );
			$diff = (int) $today->format( 'j' ) - 1;
		} else {
			$diff = ( (int) $today->format( 'w' ) - $s['day'] + 7 ) % 7;
			$day  = $today->modify( '-' . $diff . ' days' );
		}
		return $diff <= self::CATCH_UP_DAYS ? $day : null;
	}

	/**
	 * Whether a report is due: its scheduled day has come (or was missed in
	 * the last few days), no report was sent or skipped since then, and the
	 * day is after reports started on this site (no surprise email right
	 * after an update).
	 *
	 * @param int $now Timestamp.
	 * @return bool
	 */
	public static function due( $now ) {
		$s = self::all();
		if ( ! $s['enabled'] ) {
			return false;
		}
		$day = self::scheduled_day( $now );
		if ( ! $day ) {
			return false;
		}
		$start = $day->getTimestamp();
		$since = (int) get_option( self::SINCE_OPTION, 0 );
		if ( ! $since || $start < $since ) {
			return false;
		}
		$last    = (int) get_option( self::LAST_OPTION, 0 );
		$checked = max( $last, (int) get_option( self::CHECK_OPTION, 0 ) );
		$gap     = ( 'monthly' === $s['frequency'] ? 20 : 5 ) * DAY_IN_SECONDS;
		return $checked < $start && $last < $start - $gap;
	}

	/**
	 * Remember that this period's report was handled (sent or skipped), so
	 * the catch-up does not try again.
	 *
	 * @param int $now Timestamp.
	 */
	public static function mark_checked( $now ) {
		update_option( self::CHECK_OPTION, (int) $now, false );
	}

	/**
	 * First time the job ran on this site; reports start after it.
	 *
	 * @param int $now Timestamp.
	 */
	public static function start( $now ) {
		if ( ! get_option( self::SINCE_OPTION ) ) {
			update_option( self::SINCE_OPTION, (int) $now, false );
		}
	}

	/**
	 * When the next report goes out (about 6:00 site time), for the screen.
	 *
	 * @param int $now Timestamp.
	 * @return int Timestamp.
	 */
	public static function next_send( $now ) {
		$s    = self::all();
		$tz   = wp_timezone();
		$day  = ( new \DateTimeImmutable( '@' . (int) $now ) )->setTimezone( $tz )->setTime( Reports::HOUR, 0 );
		$last = (int) get_option( self::LAST_OPTION, 0 );
		for ( $i = 0; $i < 62; $i++ ) {
			$at = $day->modify( '+' . $i . ' days' );
			if ( $at->getTimestamp() < $now ) {
				continue;
			}
			$ok = 'monthly' === $s['frequency']
				? '1' === $at->format( 'j' ) && $last < $at->getTimestamp() - 20 * DAY_IN_SECONDS
				: (int) $at->format( 'w' ) === $s['day'] && $last < $at->getTimestamp() - 5 * DAY_IN_SECONDS;
			if ( $ok ) {
				return $at->getTimestamp();
			}
		}
		return 0;
	}

	/**
	 * Remember the outcome of the last run (sent, skipped or failed).
	 *
	 * @param array<string,mixed> $result Result.
	 */
	public static function record_result( array $result ) {
		$result['at'] = gmdate( 'c' );
		update_option( self::RESULT_OPTION, $result, false );
	}
}
