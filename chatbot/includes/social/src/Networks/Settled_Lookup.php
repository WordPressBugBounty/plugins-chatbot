<?php
/**
 * The rule for saying "not found" after looking a post up in a feed.
 *
 * Meta (and Google) serve reads from replicas that can lag the write by a
 * few minutes, so one empty read straight after an ambiguous publish does
 * not prove the post is missing. Saying "not found" too early starts a retry
 * and can post twice (QA #37). A feed lookup therefore:
 *
 * 1. does not look at all until SETTLE seconds after the attempt started;
 * 2. needs MISSES empty reads, at least GAP (10 minutes) apart, before it says
 *    "not found"; until then it returns "wait".
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for feed-based reconcile().
 */
final class Settled_Lookup {

	/**
	 * Seconds after the attempt started before the first look.
	 */
	const SETTLE = 300;

	/**
	 * Seconds between two looks.
	 */
	const GAP = 600;

	/**
	 * Empty reads needed before "not found".
	 */
	const MISSES = 2;

	/**
	 * State key holding the number of empty reads.
	 */
	const KEY = 'reconcile_misses';

	/**
	 * "Wait" when it is too early to look, else null.
	 *
	 * @param Target   $target Target.
	 * @param int|null $now    Unix time (tests); defaults to time().
	 * @return Reconcile_Result|null
	 */
	public static function too_early( Target $target, $now = null ) {
		$now     = null === $now ? time() : (int) $now;
		$started = $target->attempt_started();
		$ready   = $started + self::SETTLE;
		if ( $started > 0 && $now < $ready ) {
			return Reconcile_Result::wait( $ready - $now, array(), 'Waiting for the network to show the post before checking.' );
		}
		return null;
	}

	/**
	 * The result of an empty read: "wait" until enough reads missed.
	 *
	 * @param Target $target Target.
	 * @return Reconcile_Result
	 */
	public static function missed( Target $target ) {
		$misses = (int) $target->state( self::KEY, 0 ) + 1;
		if ( $misses < self::MISSES ) {
			return Reconcile_Result::wait( self::GAP, array( self::KEY => $misses ), 'The post was not on the feed yet; WPBot will look once more.' );
		}
		return new Reconcile_Result( Reconcile_Result::NOT_FOUND );
	}
}
