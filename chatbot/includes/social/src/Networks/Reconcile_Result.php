<?php
/**
 * The outcome of looking up an ambiguous publish attempt.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * Outcome of Network::reconcile().
 *
 * - found:        the post exists; mark the target done with remote_id.
 * - not_found:    the post does not exist; a retry is safe.
 * - wait:         too early to say. The network's read side may lag behind
 *                 the write (Meta feeds can take minutes), or the lookup hit a
 *                 temporary error. Look again after $after seconds; $state is
 *                 merged into the target's saved state (for example the
 *                 number of empty reads so far).
 * - not_found:    the post does not exist; a retry is safe. Feed lookups may
 *                 only say this once the read has settled (see Settled_Lookup).
 * - cannot_check: this network can't look posts up, or the lookup failed;
 *                 ask the owner to check and confirm. Never auto-retry.
 */
final class Reconcile_Result {

	const FOUND        = 'found';
	const NOT_FOUND    = 'not_found';
	const CANNOT_CHECK = 'cannot_check';
	const WAIT         = 'wait';

	/**
	 * Status.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Remote id when found.
	 *
	 * @var string
	 */
	public $remote_id = '';

	/**
	 * Note for the owner or the log.
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * For "wait": seconds until the next look.
	 *
	 * @var int
	 */
	public $after = 0;

	/**
	 * For "wait": values merged into the target's saved state.
	 *
	 * @var array<string,mixed>
	 */
	public $state = array();

	/**
	 * The account's connection is gone (expired or revoked token): pause it.
	 *
	 * @var bool
	 */
	public $pause_account = false;

	/**
	 * Build.
	 *
	 * @param string $status    Status.
	 * @param string $remote_id Remote id.
	 * @param string $message   Note.
	 */
	public function __construct( $status, $remote_id = '', $message = '' ) {
		$this->status    = $status;
		$this->remote_id = (string) $remote_id;
		$this->message   = (string) $message;
	}

	/**
	 * Look again later.
	 *
	 * @param int    $after   Seconds until the next look (at least 60).
	 * @param array  $state   Values merged into the target's saved state.
	 * @param string $message Note for the log.
	 * @return self
	 */
	public static function wait( $after, array $state = array(), $message = '' ) {
		$result        = new self( self::WAIT, '', $message );
		$result->after = max( 60, (int) $after );
		$result->state = $state;
		return $result;
	}
}
