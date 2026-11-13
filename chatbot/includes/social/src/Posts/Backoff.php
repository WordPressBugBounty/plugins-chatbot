<?php
/**
 * Retry rules (build-brief A2): three attempts over about an hour, kept on
 * the target, never through WPBot Core's immediate retry.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

defined( 'ABSPATH' ) || exit;

/**
 * Backoff schedule.
 */
final class Backoff {

	/**
	 * Wait before attempt 2 and attempt 3 (seconds): 15 minutes, then 40
	 * minutes, so three attempts span about an hour.
	 *
	 * @var int[]
	 */
	const DELAYS = array( 900, 2400 );

	/**
	 * Most attempts per target.
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Seconds to wait before resuming a pending step (media still processing).
	 */
	const PENDING_DELAY = 30;

	/**
	 * After a failed attempt: when to try again, or null to give up.
	 *
	 * @param int $attempts Attempts made so far, including the failed one.
	 * @param int $now      Unix time.
	 * @return int|null Unix time of the next attempt.
	 */
	public static function next( $attempts, $now ) {
		$attempts = (int) $attempts;
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return null;
		}
		$index = max( 0, min( count( self::DELAYS ) - 1, $attempts - 1 ) );
		return (int) $now + self::DELAYS[ $index ];
	}
}
