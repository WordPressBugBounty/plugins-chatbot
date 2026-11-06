<?php
/**
 * Topic source: holidays and shopping days for the owner's country, plus
 * the owner's own store events (sales, launches) from Settings. Events in
 * the next 60 days become ideas, so there is time to write ahead.
 *
 * Dates are computed, not fetched (no outside service). Moveable feasts
 * that need a lunar calendar are left out; the owner can add them as
 * store events. Filter: qcld_wpbot_writer/seasonal_events.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics\Sources;

use QuantumCloud\AiWriter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Seasonal and store events.
 */
final class Seasonal_Source {

	/**
	 * Days ahead that count.
	 */
	const WINDOW_DAYS = 60;

	/**
	 * Connection state (always available).
	 *
	 * @return array
	 */
	public static function status() {
		return array(
			'status'  => 'connected',
			/* translators: %s: country code. */
			'message' => sprintf( __( 'Events for %s and your own store events.', 'chatbot' ), self::country() ),
		);
	}

	/**
	 * The country used: the setting, else the site language's region.
	 *
	 * @return string Two-letter code.
	 */
	public static function country() {
		$country = (string) Settings::get( 'seasonal_country' );
		if ( '' !== $country ) {
			return $country;
		}
		$locale = (string) get_locale();
		return preg_match( '/_([A-Z]{2})/', $locale, $match ) ? $match[1] : 'US';
	}

	/**
	 * Events of a year: id => array( title, date Y-m-d ).
	 *
	 * @param int    $year    Year.
	 * @param string $country Two-letter code.
	 * @return array<string,array{title: string, date: string}>
	 */
	public static function events( $year, $country ) {
		$easter = self::easter( $year );
		$list   = array(
			'new-year'       => array( __( 'New Year', 'chatbot' ), "$year-01-01" ),
			'valentines'     => array( __( "Valentine's Day", 'chatbot' ), "$year-02-14" ),
			'womens-day'     => array( __( "International Women's Day", 'chatbot' ), "$year-03-08" ),
			'easter'         => array( __( 'Easter', 'chatbot' ), $easter ),
			'earth-day'      => array( __( 'Earth Day', 'chatbot' ), "$year-04-22" ),
			'back-to-school' => array( __( 'Back to school', 'chatbot' ), "$year-08-20" ),
			'halloween'      => array( __( 'Halloween', 'chatbot' ), "$year-10-31" ),
			'singles-day'    => array( __( "Singles' Day sales", 'chatbot' ), "$year-11-11" ),
			'black-friday'   => array( __( 'Black Friday', 'chatbot' ), self::nth_weekday( $year, 11, 4, 4, 1 ) ),
			'cyber-monday'   => array( __( 'Cyber Monday', 'chatbot' ), self::nth_weekday( $year, 11, 4, 4, 4 ) ),
			'christmas'      => array( __( 'Christmas', 'chatbot' ), "$year-12-25" ),
			'year-end'       => array( __( 'Year in review', 'chatbot' ), "$year-12-28" ),
		);
		$local  = array(
			'US' => array(
				'mothers-day'  => array( __( "Mother's Day", 'chatbot' ), self::nth_weekday( $year, 5, 0, 2 ) ),
				'memorial-day' => array( __( 'Memorial Day', 'chatbot' ), self::last_weekday( $year, 5, 1 ) ),
				'fathers-day'  => array( __( "Father's Day", 'chatbot' ), self::nth_weekday( $year, 6, 0, 3 ) ),
				'july-4'       => array( __( 'Independence Day', 'chatbot' ), "$year-07-04" ),
				'labor-day'    => array( __( 'Labor Day', 'chatbot' ), self::nth_weekday( $year, 9, 1, 1 ) ),
				'thanksgiving' => array( __( 'Thanksgiving', 'chatbot' ), self::nth_weekday( $year, 11, 4, 4 ) ),
			),
			'CA' => array(
				'mothers-day'  => array( __( "Mother's Day", 'chatbot' ), self::nth_weekday( $year, 5, 0, 2 ) ),
				'canada-day'   => array( __( 'Canada Day', 'chatbot' ), "$year-07-01" ),
				'thanksgiving' => array( __( 'Thanksgiving', 'chatbot' ), self::nth_weekday( $year, 10, 1, 2 ) ),
				'boxing-day'   => array( __( 'Boxing Day', 'chatbot' ), "$year-12-26" ),
			),
			'GB' => array(
				'mothers-day' => array( __( "Mother's Day", 'chatbot' ), gmdate( 'Y-m-d', strtotime( $easter . ' -21 days' ) ) ),
				'fathers-day' => array( __( "Father's Day", 'chatbot' ), self::nth_weekday( $year, 6, 0, 3 ) ),
				'bonfire'     => array( __( 'Bonfire Night', 'chatbot' ), "$year-11-05" ),
				'boxing-day'  => array( __( 'Boxing Day', 'chatbot' ), "$year-12-26" ),
			),
			'AU' => array(
				'australia-day' => array( __( 'Australia Day', 'chatbot' ), "$year-01-26" ),
				'mothers-day'   => array( __( "Mother's Day", 'chatbot' ), self::nth_weekday( $year, 5, 0, 2 ) ),
				'fathers-day'   => array( __( "Father's Day", 'chatbot' ), self::nth_weekday( $year, 9, 0, 1 ) ),
				'boxing-day'    => array( __( 'Boxing Day', 'chatbot' ), "$year-12-26" ),
			),
			'IN' => array(
				'republic-day'     => array( __( 'Republic Day', 'chatbot' ), "$year-01-26" ),
				'independence-day' => array( __( 'Independence Day', 'chatbot' ), "$year-08-15" ),
			),
			'BD' => array(
				'language-day'     => array( __( 'International Mother Language Day', 'chatbot' ), "$year-02-21" ),
				'independence-day' => array( __( 'Independence Day', 'chatbot' ), "$year-03-26" ),
				'pohela-boishakh'  => array( __( 'Pohela Boishakh (Bengali New Year)', 'chatbot' ), "$year-04-14" ),
				'victory-day'      => array( __( 'Victory Day', 'chatbot' ), "$year-12-16" ),
			),
			'DE' => array(
				'mothers-day' => array( __( "Mother's Day", 'chatbot' ), self::nth_weekday( $year, 5, 0, 2 ) ),
				'unity-day'   => array( __( 'German Unity Day', 'chatbot' ), "$year-10-03" ),
				'advent'      => array( __( 'Advent', 'chatbot' ), gmdate( 'Y-m-d', strtotime( "$year-12-25 -4 sundays" ) ) ),
			),
			'FR' => array(
				'mothers-day'  => array( __( "Mother's Day", 'chatbot' ), self::last_weekday( $year, 5, 0 ) ),
				'bastille-day' => array( __( 'Bastille Day', 'chatbot' ), "$year-07-14" ),
			),
		);
		if ( isset( $local[ $country ] ) ) {
			$list = array_merge( $list, $local[ $country ] );
		}
		$out = array();
		foreach ( $list as $id => $event ) {
			$out[ $id ] = array(
				'title' => $event[0],
				'date'  => $event[1],
			);
		}

		/**
		 * Seasonal events for a year and country.
		 *
		 * @param array  $out     id => array( title, date Y-m-d ).
		 * @param int    $year    Year.
		 * @param string $country Two-letter country code.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/seasonal_events', $out, $year, $country );
	}

	/**
	 * Events in the next 60 days (this year and next), plus store events.
	 *
	 * @param int $now Timestamp (tests).
	 * @return array[] id, title, date, days, store (bool).
	 */
	public static function upcoming( $now = 0 ) {
		$now     = $now ? $now : current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Local calendar days.
		$today   = gmdate( 'Y-m-d', $now );
		$limit   = gmdate( 'Y-m-d', $now + self::WINDOW_DAYS * DAY_IN_SECONDS );
		$year    = (int) gmdate( 'Y', $now );
		$country = self::country();
		$found   = array();
		foreach ( array( $year, $year + 1 ) as $y ) {
			foreach ( self::events( $y, $country ) as $id => $event ) {
				$found[] = array(
					'id'    => $id . '-' . $y,
					'title' => (string) $event['title'],
					'date'  => (string) $event['date'],
					'store' => false,
				);
			}
		}
		foreach ( (array) Settings::get( 'store_events' ) as $event ) {
			$found[] = array(
				'id'    => 'store-' . md5( $event['title'] . $event['date'] ),
				'title' => (string) $event['title'],
				'date'  => (string) $event['date'],
				'store' => true,
			);
		}
		$out = array();
		foreach ( $found as $event ) {
			if ( $event['date'] >= $today && $event['date'] <= $limit ) {
				$event['days'] = (int) round( ( strtotime( $event['date'] . ' UTC' ) - strtotime( $today . ' UTC' ) ) / DAY_IN_SECONDS );
				$out[]         = $event;
			}
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);
		return $out;
	}

	/**
	 * Ideas: one per upcoming event.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function ideas( array $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Source callback signature.
		$ideas = array();
		foreach ( self::upcoming() as $event ) {
			$year    = substr( $event['date'], 0, 4 );
			$ideas[] = array(
				'title'      => $event['store']
					/* translators: %s: store event. */
					? sprintf( __( '%s: what to know', 'chatbot' ), $event['title'] )
					/* translators: 1: event, 2: year. */
					: sprintf( __( '%1$s %2$s: ideas and offers', 'chatbot' ), $event['title'], $year ),
				'ref'        => $event['id'],
				'reason'     => $event['store']
					/* translators: 1: days, 2: date. */
					? sprintf( _n( 'Your store event is in %1$d day (%2$s).', 'Your store event is in %1$d days (%2$s).', $event['days'], 'chatbot' ), $event['days'], $event['date'] )
					/* translators: 1: days, 2: date. */
					: sprintf( _n( 'In %1$d day (%2$s). Publish two to four weeks ahead so it can be found in time.', 'In %1$d days (%2$s). Publish two to four weeks ahead so it can be found in time.', $event['days'], 'chatbot' ), $event['days'], $event['date'] ),
				'trend'      => 'seasonal',
				'rank_score' => max( 1, 60 - $event['days'] ) + ( $event['store'] ? 20 : 0 ),
				'metrics'    => array(
					'date' => $event['date'],
					'days' => $event['days'],
				),
				'coverage'   => false,
			);
		}
		return $ideas;
	}

	/**
	 * Nth weekday of a month (weekday 0 = Sunday), plus an offset in days.
	 *
	 * @param int $year    Year.
	 * @param int $month   Month.
	 * @param int $weekday Weekday.
	 * @param int $nth     Nth.
	 * @param int $plus    Days after.
	 * @return string Y-m-d.
	 */
	public static function nth_weekday( $year, $month, $weekday, $nth, $plus = 0 ) {
		$first = (int) gmdate( 'w', gmmktime( 0, 0, 0, $month, 1, $year ) );
		$day   = 1 + ( 7 + $weekday - $first ) % 7 + 7 * ( $nth - 1 ) + $plus;
		return gmdate( 'Y-m-d', gmmktime( 0, 0, 0, $month, $day, $year ) );
	}

	/**
	 * Last weekday of a month.
	 *
	 * @param int $year    Year.
	 * @param int $month   Month.
	 * @param int $weekday Weekday (0 = Sunday).
	 * @return string Y-m-d.
	 */
	public static function last_weekday( $year, $month, $weekday ) {
		$last = gmmktime( 0, 0, 0, $month + 1, 0, $year );
		$back = ( 7 + (int) gmdate( 'w', $last ) - $weekday ) % 7;
		return gmdate( 'Y-m-d', $last - $back * DAY_IN_SECONDS );
	}

	/**
	 * Western Easter Sunday (anonymous Gregorian algorithm).
	 *
	 * @param int $year Year.
	 * @return string Y-m-d.
	 */
	public static function easter( $year ) {
		$a     = $year % 19;
		$b     = intdiv( $year, 100 );
		$c     = $year % 100;
		$d     = intdiv( $b, 4 );
		$e     = $b % 4;
		$f     = intdiv( $b + 8, 25 );
		$g     = intdiv( $b - $f + 1, 3 );
		$h     = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i     = intdiv( $c, 4 );
		$k     = $c % 4;
		$l     = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m     = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}
}
