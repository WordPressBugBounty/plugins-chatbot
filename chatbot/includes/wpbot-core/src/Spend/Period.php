<?php
/**
 * The spending period: monthly from a reset day (1–28) or weekly from a weekday, in the
 * site's time zone.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Period maths (pure apart from the settings and the clock).
 */
final class Period {

	/**
	 * The period that holds a moment.
	 *
	 * @param array|null $settings Spend settings (null = saved settings).
	 * @param int|null   $now      Unix time (null = now).
	 * @return array{key: string, start: string, end: string, start_ts: int, end_ts: int, type: string}
	 *         start/end are site-local dates (Y-m-d); end is the first day of the next period.
	 */
	public static function current( $settings = null, $now = null ) {
		$settings = is_array( $settings ) ? $settings : Spend_Settings::all();
		$zone     = self::zone();
		$moment   = ( new DateTimeImmutable( '@' . ( null === $now ? time() : (int) $now ) ) )->setTimezone( $zone );
		$today    = $moment->setTime( 0, 0, 0 );

		if ( 'weekly' === $settings['period'] ) {
			$weekday = min( 7, max( 1, (int) $settings['reset_weekday'] ) );
			$back    = ( (int) $today->format( 'N' ) - $weekday + 7 ) % 7;
			$start   = $today->sub( new DateInterval( 'P' . $back . 'D' ) );
			$end     = $start->add( new DateInterval( 'P7D' ) );
		} else {
			$day   = min( 28, max( 1, (int) $settings['reset_day'] ) );
			$start = $today->setDate( (int) $today->format( 'Y' ), (int) $today->format( 'n' ), $day );
			if ( $start > $today ) {
				$start = $start->sub( new DateInterval( 'P1M' ) );
			}
			$end = $start->add( new DateInterval( 'P1M' ) );
		}

		return array(
			'key'      => $start->format( 'Y-m-d' ),
			'start'    => $start->format( 'Y-m-d' ),
			'end'      => $end->format( 'Y-m-d' ),
			'start_ts' => $start->getTimestamp(),
			'end_ts'   => $end->getTimestamp(),
			'type'     => 'weekly' === $settings['period'] ? 'weekly' : 'monthly',
		);
	}

	/**
	 * The period before the current one.
	 *
	 * @param array|null $settings Spend settings.
	 * @param int|null   $now      Unix time.
	 * @return array Same shape as current().
	 */
	public static function previous( $settings = null, $now = null ) {
		$current = self::current( $settings, $now );
		return self::current( $settings, $current['start_ts'] - 1 );
	}

	/**
	 * Owner-facing period name ("Monthly" / "Weekly").
	 *
	 * @param string $type monthly or weekly.
	 * @return string
	 */
	public static function label( $type ) {
		return 'weekly' === $type ? __( 'Weekly', 'chatbot' ) : __( 'Monthly', 'chatbot' );
	}

	/**
	 * The site's time zone.
	 *
	 * @return DateTimeZone
	 */
	private static function zone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}
		return new DateTimeZone( 'UTC' );
	}
}
