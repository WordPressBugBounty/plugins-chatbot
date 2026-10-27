<?php
/**
 * Date helpers. The database stores UTC; responses use the site timezone.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Converts between stored UTC MySQL datetimes and response formats.
 */
final class Dates {

	/**
	 * Current UTC time as a MySQL datetime.
	 *
	 * @return string
	 */
	public static function now() {
		return current_time( 'mysql', true );
	}

	/**
	 * Stored UTC datetime → ISO-8601 in site timezone ("Y-m-d\TH:i:s").
	 *
	 * @param string|null $utc MySQL datetime in UTC.
	 * @return string|null Null when empty.
	 */
	public static function iso( $utc ) {
		if ( self::is_empty( $utc ) ) {
			return null;
		}
		return get_date_from_gmt( (string) $utc, 'Y-m-d\TH:i:s' );
	}

	/**
	 * Stored UTC datetime → "Y-m-d H:i:s" in site timezone (used by templates).
	 *
	 * @param string|null $utc MySQL datetime in UTC.
	 * @return string Empty string when empty.
	 */
	public static function mysql( $utc ) {
		if ( self::is_empty( $utc ) ) {
			return '';
		}
		return get_date_from_gmt( (string) $utc, 'Y-m-d H:i:s' );
	}

	/**
	 * Parse a date string in site timezone (or with an explicit offset) into a UTC MySQL datetime.
	 *
	 * @param string $local Date string, e.g. "2026-10-26 14:00" or "2026-10-26T14:00:00+02:00".
	 * @return string UTC MySQL datetime, or '' when unparsable.
	 */
	public static function to_utc( $local ) {
		$local = trim( (string) $local );
		if ( '' === $local ) {
			return '';
		}
		try {
			$date = new \DateTimeImmutable( $local, wp_timezone() );
		} catch ( \Exception $e ) {
			return '';
		}
		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * UTC datetime of midnight today in the site timezone.
	 *
	 * @return string
	 */
	public static function today_start_utc() {
		return self::local_to_utc_string( 'today' );
	}

	/**
	 * UTC datetime of the first second of the current month in the site timezone.
	 *
	 * @return string
	 */
	public static function month_start_utc() {
		return self::local_to_utc_string( 'first day of this month midnight' );
	}

	/**
	 * UTC datetime $days days ago from now.
	 *
	 * @param int $days Number of days.
	 * @return string
	 */
	public static function days_ago_utc( $days ) {
		return gmdate( 'Y-m-d H:i:s', time() - max( 0, (int) $days ) * DAY_IN_SECONDS );
	}

	/**
	 * Add an interval to a UTC datetime.
	 *
	 * @param string $utc   Base UTC datetime ('' = now).
	 * @param int    $value Amount.
	 * @param string $unit  minutes|hours|days.
	 * @return string UTC MySQL datetime.
	 */
	public static function add( $utc, $value, $unit ) {
		$base  = self::is_empty( $utc ) ? time() : strtotime( $utc . ' UTC' );
		$units = array(
			'minutes' => MINUTE_IN_SECONDS,
			'hours'   => HOUR_IN_SECONDS,
			'days'    => DAY_IN_SECONDS,
		);
		$step  = isset( $units[ $unit ] ) ? $units[ $unit ] : MINUTE_IN_SECONDS;
		return gmdate( 'Y-m-d H:i:s', (int) $base + max( 0, (int) $value ) * $step );
	}

	/**
	 * Whether a stored datetime is empty.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_empty( $value ) {
		return null === $value || '' === $value || 0 === strpos( (string) $value, '0000-00-00' );
	}

	/**
	 * Resolve a relative expression in site timezone and return it as UTC.
	 *
	 * @param string $expression strtotime-compatible expression.
	 * @return string
	 */
	private static function local_to_utc_string( $expression ) {
		$date = new \DateTimeImmutable( $expression, wp_timezone() );
		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
