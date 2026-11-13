<?php
/**
 * The outcome of one publish step.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * Outcome of Network::publish().
 *
 * Statuses:
 * - done:    the post exists; remote_id is set.
 * - pending: more steps needed (for example a media container still
 *            processing); state holds what to resume with.
 * - failed:  the network definitely did not create the post. retryable
 *            says whether trying again later can help.
 * - unknown: the outcome is ambiguous. Never retry; reconcile first.
 */
final class Publish_Result {

	const DONE    = 'done';
	const PENDING = 'pending';
	const FAILED  = 'failed';
	const UNKNOWN = 'unknown';

	/**
	 * Status.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Remote post id when done.
	 *
	 * @var string
	 */
	public $remote_id = '';

	/**
	 * Public URL of the post, when the network returns one.
	 *
	 * @var string
	 */
	public $url = '';

	/**
	 * Machine code: auth_expired, rate_limited, bad_request, permission,
	 * media_error, timeout, server_error, ...
	 *
	 * @var string
	 */
	public $code = '';

	/**
	 * Plain-language reason for the owner.
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Whether a later retry can help (failed only).
	 *
	 * @var bool
	 */
	public $retryable = false;

	/**
	 * Whether the account must be reconnected (token expired or revoked).
	 *
	 * @var bool
	 */
	public $pause_account = false;

	/**
	 * For a retryable failure: seconds to wait instead of the usual backoff
	 * (for example Instagram's 24-hour posting limit). 0 = usual backoff.
	 *
	 * @var int
	 */
	public $retry_after = 0;

	/**
	 * State to save for the next step.
	 *
	 * @var array<string,mixed>
	 */
	public $state = array();

	/**
	 * A finished post.
	 *
	 * @param string $remote_id Remote id.
	 * @param string $url       Post URL or ''.
	 * @return Publish_Result
	 */
	public static function done( $remote_id, $url = '' ) {
		$result            = new self( self::DONE );
		$result->remote_id = (string) $remote_id;
		$result->url       = (string) $url;
		return $result;
	}

	/**
	 * More steps needed.
	 *
	 * @param array  $state   State to save.
	 * @param string $message Optional note.
	 * @return Publish_Result
	 */
	public static function pending( array $state, $message = '' ) {
		$result          = new self( self::PENDING );
		$result->state   = $state;
		$result->message = (string) $message;
		return $result;
	}

	/**
	 * A definite failure.
	 *
	 * @param string $code      Code.
	 * @param string $message   Reason.
	 * @param bool   $retryable Whether a retry later can help.
	 * @param array  $state     State to keep.
	 * @return Publish_Result
	 */
	public static function failed( $code, $message, $retryable = false, array $state = array() ) {
		$result            = new self( self::FAILED );
		$result->code      = (string) $code;
		$result->message   = (string) $message;
		$result->retryable = (bool) $retryable;
		$result->state     = $state;
		return $result;
	}

	/**
	 * An ambiguous outcome.
	 *
	 * @param string $code    Code.
	 * @param string $message Reason.
	 * @param array  $state   State to keep for reconcile().
	 * @return Publish_Result
	 */
	public static function unknown( $code, $message, array $state = array() ) {
		$result          = new self( self::UNKNOWN );
		$result->code    = (string) $code;
		$result->message = (string) $message;
		$result->state   = $state;
		return $result;
	}

	/**
	 * Build with a status.
	 *
	 * @param string $status Status.
	 */
	private function __construct( $status ) {
		$this->status = $status;
	}
}
