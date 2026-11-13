<?php
/**
 * The social_publish and social_reconcile job steps (WPBot Core jobs).
 *
 * A step always completes from Core's point of view: the outcome lives on
 * the target (retry time, unknown, failed), so Core never retries a publish
 * on its own and a post can never go out twice (build-brief A2).
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Module;
use QuantumCloud\Social\Networks\Draft;
use QuantumCloud\Social\Networks\Manual_Network;
use QuantumCloud\Social\Networks\Publish_Result;
use QuantumCloud\Social\Networks\Reconcile_Result;
use QuantumCloud\Social\Networks\Settled_Lookup;
use QuantumCloud\Social\Support\Log;

defined( 'ABSPATH' ) || exit;

/**
 * Publisher.
 */
final class Publisher {

	/**
	 * Register the job step handlers.
	 *
	 * @param array $handlers Handlers.
	 * @return array
	 */
	public static function handlers( $handlers ) {
		$handlers                     = (array) $handlers;
		$handlers['social_publish']   = array( __CLASS__, 'publish_step' );
		$handlers['social_reconcile'] = array( __CLASS__, 'reconcile_step' );
		return $handlers;
	}

	/**
	 * Publish one claimed target.
	 *
	 * @param array $step Core job step (input: target_id, claim).
	 * @return array Step output.
	 */
	public static function publish_step( array $step ) {
		$input = self::input( $step );
		$now   = current_time( 'mysql', true );
		$row   = Target_Store::get( $input['target_id'] );
		if ( ! $row || ! Target_Store::start_sending( $row['id'], $input['claim'], $now ) ) {
			return array( 'skipped' => 'not_claimed' );
		}
		$row     = Target_Store::get( $row['id'] );
		$account = Account_Store::get( $row['account_id'] );
		$network = $account ? Module::instance()->network( $account['network'] ) : null;
		if ( $network instanceof Manual_Network ) {
			// Post yourself: hand it to the owner at the scheduled time.
			$fields = array(
				'state'         => 'manual',
				'error_code'    => '',
				'error_message' => '',
				'attempts'      => 0,
			);
			if ( ! self::write( $row, 'sending', $fields ) ) {
				return array( 'skipped' => 'lost_claim' );
			}
			Post_Store::refresh_status( $row['post_id'] );
			/**
			 * A Post yourself target is due: the owner is reminded.
			 *
			 * @param array $row     Target row.
			 * @param array $account Account.
			 */
			do_action( 'qcld_wpbot_social/manual_due', $row, $account );
			return array( 'state' => 'manual' );
		}
		$target = $account ? Target_Factory::make( $account, $row ) : null;
		if ( ! $network ) {
			// The network isn't registered on this site (a Master network after a move to
			// WPBot Free): nothing is sent and the owner is told why, without a reconnect.
			$fields = self::outcome( Publish_Result::failed( 'network_unavailable', __( 'This network is not available on this site: it needs the WPBot Social Master module. Nothing was sent.', 'chatbot' ), false ), $row['attempts'], time() );
			if ( ! self::write( $row, 'sending', $fields ) ) {
				return array( 'skipped' => 'lost_claim' );
			}
			self::after( $row, $account ? $account : array(), $fields );
			return array( 'state' => $fields['state'] );
		}
		if ( ! $target ) {
			$problem = $account ? Target_Factory::problem( $account ) : array(
				'code'    => 'reconnect',
				'message' => __( 'This account needs to be reconnected before WPBot can post to it.', 'chatbot' ),
			);
			$fields  = self::outcome( Publish_Result::failed( $problem['code'], $problem['message'], ! empty( $problem['retryable'] ) ), $row['attempts'], time() );
			if ( ! self::write( $row, 'sending', $fields ) ) {
				return array( 'skipped' => 'lost_claim' );
			}
			if ( $account && 'token_unreadable' === $problem['code'] ) {
				self::pause( $account, $problem['message'] );
			}
			self::after( $row, $account ? $account : array(), $fields );
			return array( 'state' => $fields['state'] );
		}
		$result = $network->publish( $target, Draft::from_array( $row['draft'] ) );
		$fields = self::outcome( $result, $row['attempts'], time() );
		if ( ! self::write( $row, 'sending', $fields ) ) {
			return array( 'skipped' => 'lost_claim' );
		}
		if ( $result->pause_account ) {
			self::pause( $account, $result->message );
		}
		self::after( $row, $account, $fields );
		return array( 'state' => $fields['state'] );
	}

	/**
	 * Reconcile one "unknown" target.
	 *
	 * @param array $step Core job step (input: target_id, claim).
	 * @return array Step output.
	 */
	public static function reconcile_step( array $step ) {
		$input = self::input( $step );
		$row   = Target_Store::get( $input['target_id'] );
		if ( ! $row || 'reconciling' !== $row['state'] || $row['claim'] !== $input['claim'] ) {
			return array( 'skipped' => 'not_claimed' );
		}
		$account = Account_Store::get( $row['account_id'] );
		$network = $account ? Module::instance()->network( $account['network'] ) : null;
		$target  = $account ? Target_Factory::make( $account, $row ) : null;
		$result  = ( $network && $target ) ? $network->reconcile( $target, Draft::from_array( $row['draft'] ) ) : new Reconcile_Result( Reconcile_Result::CANNOT_CHECK );
		$fields  = self::reconciled( $result, $row['attempts'], time(), (array) $row['step_state'] );
		if ( ! self::write( $row, 'reconciling', $fields ) ) {
			return array( 'skipped' => 'lost_claim' );
		}
		if ( $result->pause_account && $account ) {
			self::pause( $account, $result->message );
		}
		self::after( $row, $account ? $account : array(), $fields );
		return array( 'state' => $fields['state'] );
	}

	/**
	 * Map a publish result to target fields.
	 *
	 * @param Publish_Result $result   Result.
	 * @param int            $attempts Attempts made, including this one.
	 * @param int            $now      Unix time.
	 * @return array
	 */
	public static function outcome( Publish_Result $result, $attempts, $now ) {
		switch ( $result->status ) {
			case Publish_Result::DONE:
				return array(
					'state'         => 'done',
					'remote_id'     => $result->remote_id,
					'remote_url'    => $result->url,
					'error_code'    => '',
					'error_message' => '',
					'done_at'       => gmdate( 'Y-m-d H:i:s', $now ),
					'step_state'    => array(),
				);
			case Publish_Result::PENDING:
				return array(
					'state'           => 'retry',
					'attempts'        => max( 0, (int) $attempts - 1 ),
					'next_attempt_at' => gmdate( 'Y-m-d H:i:s', $now + Backoff::PENDING_DELAY ),
					'step_state'      => $result->state,
					'error_code'      => 'processing',
					'error_message'   => $result->message,
				);
			case Publish_Result::UNKNOWN:
				return array(
					'state'           => 'unknown',
					'next_attempt_at' => null,
					'step_state'      => $result->state,
					'error_code'      => $result->code,
					'error_message'   => $result->message,
				);
			default:
				$next = $result->retryable ? Backoff::next( $attempts, $now ) : null;
				if ( null !== $next && $result->retry_after > 0 ) {
					$next = (int) $now + (int) $result->retry_after;
				}
				return array(
					'state'           => null === $next ? 'failed' : 'retry',
					'next_attempt_at' => null === $next ? null : gmdate( 'Y-m-d H:i:s', $next ),
					'step_state'      => $result->state,
					'error_code'      => $result->code,
					'error_message'   => $result->message,
				);
		}
	}

	/**
	 * Write a step's outcome if this runner still owns the target; log a lost write.
	 *
	 * @param array  $row    Target row (id, claim, post_id).
	 * @param string $state  State the runner expects.
	 * @param array  $fields Outcome fields.
	 * @return bool
	 */
	private static function write( array $row, $state, array $fields ) {
		if ( Target_Store::finish_owned( $row['id'], (string) $row['claim'], $state, $fields ) ) {
			return true;
		}
		// Stale recovery took the target over (the step ran too long). Reconcile
		// decides from the network what happened; this late result is only logged.
		Log::add( 'error', 'late_result', sprintf( 'Target %d: a late %s result (%s) was not saved; WPBot checks the network instead.', $row['id'], $state, $fields['state'] ), array( 'post' => $row['post_id'] ) );
		return false;
	}

	/**
	 * Mark an account (and its login's siblings) as needing reconnection.
	 *
	 * @param array  $account Account.
	 * @param string $message Reason.
	 * @return void
	 */
	private static function pause( array $account, $message ) {
		if ( '' !== (string) $account['login'] ) {
			Account_Store::set_status( (string) $account['login'], 'expired', $message );
			return;
		}
		Account_Store::set_status( '', 'expired', $message, array( $account['id'] ) );
	}

	/**
	 * Map a reconcile result to target fields.
	 *
	 * @param Reconcile_Result $result     Result.
	 * @param int              $attempts   Attempts so far.
	 * @param int              $now        Unix time.
	 * @param array            $step_state The target's saved state.
	 * @return array
	 */
	public static function reconciled( Reconcile_Result $result, $attempts, $now, array $step_state = array() ) {
		if ( Reconcile_Result::WAIT === $result->status ) {
			// Too early to say: stay "unknown" and look again later.
			return array(
				'state'           => 'unknown',
				'next_attempt_at' => gmdate( 'Y-m-d H:i:s', (int) $now + max( 60, (int) $result->after ) ),
				'step_state'      => array_merge( $step_state, $result->state ),
				'error_code'      => 'checking',
				'error_message'   => __( 'WPBot is checking whether the post went out.', 'chatbot' ),
			);
		}
		if ( Reconcile_Result::FOUND === $result->status ) {
			return array(
				'state'         => 'done',
				'remote_id'     => $result->remote_id,
				'error_code'    => '',
				'error_message' => $result->message,
				'done_at'       => gmdate( 'Y-m-d H:i:s', $now ),
			);
		}
		if ( Reconcile_Result::NOT_FOUND === $result->status ) {
			$next = Backoff::next( $attempts, $now );
			unset( $step_state[ Settled_Lookup::KEY ] );
			return array(
				'step_state'      => $step_state,
				'state'           => null === $next ? 'failed' : 'retry',
				'next_attempt_at' => null === $next ? null : gmdate( 'Y-m-d H:i:s', $next ),
				'error_code'      => 'not_posted',
				'error_message'   => __( 'The post did not go out; WPBot will try again.', 'chatbot' ),
			);
		}
		return array(
			'state'         => 'check',
			'error_code'    => 'check_and_confirm',
			'error_message' => '' !== $result->message ? $result->message : __( 'WPBot could not tell whether this post went out. Please check the account and confirm.', 'chatbot' ),
		);
	}

	/**
	 * Post status, log, hooks and the failure email.
	 *
	 * @param array $row     Target row.
	 * @param array $account Account.
	 * @param array $fields  Saved fields.
	 * @return void
	 */
	private static function after( array $row, array $account, array $fields ) {
		Post_Store::refresh_status( $row['post_id'] );
		$name = isset( $account['name'] ) ? $account['name'] : '#' . $row['account_id'];
		if ( 'done' === $fields['state'] ) {
			Log::add( 'ok', 'published', sprintf( 'Published to %s', $name ), array( 'post' => $row['post_id'] ) );
			/**
			 * A target was published.
			 *
			 * @param array $row    Target row.
			 * @param array $fields Saved fields (remote_id, remote_url).
			 */
			do_action( 'qcld_wpbot_social/published', $row, $fields );
			return;
		}
		if ( in_array( $fields['state'], array( 'failed', 'check' ), true ) ) {
			Log::add( 'error', 'publish_failed', sprintf( '%s: %s', $name, $fields['error_message'] ), array( 'post' => $row['post_id'] ) );
			/**
			 * A target failed for good or needs the owner to check.
			 *
			 * @param array $row    Target row.
			 * @param array $fields Saved fields.
			 */
			do_action( 'qcld_wpbot_social/publish_failed', $row, $fields );
		}
	}

	/**
	 * Step input.
	 *
	 * @param array $step Step.
	 * @return array{target_id:int, claim:string}
	 */
	private static function input( array $step ) {
		$input = isset( $step['input'] ) ? ( is_array( $step['input'] ) ? $step['input'] : (array) json_decode( (string) $step['input'], true ) ) : array();
		return array(
			'target_id' => isset( $input['target_id'] ) ? (int) $input['target_id'] : 0,
			'claim'     => isset( $input['claim'] ) ? (string) $input['claim'] : '',
		);
	}
}
