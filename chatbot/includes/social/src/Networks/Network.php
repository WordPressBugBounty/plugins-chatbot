<?php
/**
 * The contract every network adapter implements.
 *
 * Publishing is two-phase (build-brief A2): the pipeline marks a target
 * "sending" before publish() and stores the remote id before "done". An
 * ambiguous outcome (timeout, 5xx, lost response) returns Publish_Result
 * status "unknown" and is never retried blindly: reconcile() looks the post
 * up first, and networks that cannot look posts up return "cannot_check" so
 * the owner is asked to check and confirm.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * A social network adapter.
 */
interface Network {

	/**
	 * Stable id, for example "facebook".
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Human label, for example "Facebook".
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Limits the composer and validator use: max_text, max_images,
	 * image_mimes, needs_image, needs_public_media, link_in_text.
	 *
	 * @return array<string,mixed>
	 */
	public function limits();

	/**
	 * Check a draft against this network's rules before it is scheduled.
	 *
	 * @param Draft $draft Draft.
	 * @return string[] Plain-language problems; empty when the draft is fine.
	 */
	public function validate( Draft $draft );

	/**
	 * Publish (or continue publishing) one target.
	 *
	 * Must finish well inside WPBot Core's 180 s job lock. Long work (media
	 * containers, uploads) returns status "pending" with state to resume.
	 *
	 * @param Target $target Target, including any state saved by an earlier step.
	 * @param Draft  $draft  Draft.
	 * @return Publish_Result
	 */
	public function publish( Target $target, Draft $draft );

	/**
	 * Find out whether an ambiguous attempt actually created the post.
	 *
	 * Rules (QA #37/#38):
	 * - "not_found" starts a retry, so say it only when the post is
	 *   certainly missing. A lookup in a feed or list must follow
	 *   Settled_Lookup: no look before SETTLE seconds after the attempt
	 *   started, and two empty reads at least GAP seconds apart. Until then
	 *   return "wait".
	 * - A failed lookup goes through the network's error classifier: a
	 *   rate limit or temporary error returns "wait"; an expired token
	 *   returns "cannot_check" with pause_account; anything else returns
	 *   "cannot_check".
	 * - A media container that has expired or errored is definite: it can
	 *   never publish, so "not_found" is safe at once.
	 *
	 * @param Target $target Target in state "unknown".
	 * @param Draft  $draft  Draft that was sent.
	 * @return Reconcile_Result
	 */
	public function reconcile( Target $target, Draft $draft );
}
