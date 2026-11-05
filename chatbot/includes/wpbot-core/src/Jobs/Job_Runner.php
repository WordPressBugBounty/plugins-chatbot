<?php
/**
 * Runs job steps through the registered step handlers.
 *
 * A handler is registered per step kind with the qcld_wpbot_core/job_handlers
 * filter: kind => callable( array $step, array $job ): array|WP_Error. It
 * returns the step output; it may add more steps with Job_Store::add_steps().
 * A WP_Error may carry data 'retryable' (bool), 'tokens_in', 'tokens_out'.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Jobs;

use QuantumCloud\WPBotCore\AI\Error_Classifier;

defined( 'ABSPATH' ) || exit;

/**
 * Executes steps within a time budget.
 */
final class Job_Runner {

	/**
	 * Registered handlers.
	 *
	 * @return array<string,callable>
	 */
	public static function handlers() {
		/**
		 * Register step handlers: kind => callable.
		 *
		 * @param array $handlers Handlers.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/job_handlers', array() );
	}

	/**
	 * Run at most one step of a job.
	 *
	 * @param int $job_id Job id.
	 * @return array{ran: bool, status: string} Whether a step ran and its new status.
	 */
	public static function run_one( $job_id ) {
		// A job (or the bulk job it belongs to) that spent its budget pauses before the next step.
		$current = Job_Store::get( $job_id );
		$holder  = $current ? Job_Store::budget_holder( $current ) : null;
		if ( $holder && Job_Store::spent( (int) $holder['id'] ) >= (float) $holder['budget'] ) {
			Job_Store::pause_for_budget( $job_id );
			if ( (int) $holder['id'] !== (int) $job_id && in_array( $holder['status'], Job_Store::ACTIVE, true ) ) {
				Job_Store::pause_for_budget( (int) $holder['id'] );
			}
			return array(
				'ran'    => false,
				'status' => 'budget',
			);
		}
		$step = Job_Store::claim_next( $job_id );
		if ( ! $step ) {
			return array(
				'ran'    => false,
				'status' => '',
			);
		}
		$job      = Job_Store::get( $job_id );
		$handlers = self::handlers();

		if ( ! isset( $handlers[ $step['kind'] ] ) || ! is_callable( $handlers[ $step['kind'] ] ) ) {
			$status = Job_Store::fail( $step, 'no_handler', __( 'This step cannot run because its feature is not active.', 'chatbot' ), false );
			return array(
				'ran'    => true,
				'status' => $status,
			);
		}

		// Give the step room on hosts that allow it; harmless where they don't.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Disabled on some hosts; failure is fine.
		}

		// Steps run as the person who started the job, so WP-Cron (no user)
		// gets their rights, checked again now, never more.
		$previous = get_current_user_id();
		$owner    = (int) $job['user_id'];
		$switched = $owner > 0 && $owner !== $previous && get_userdata( $owner );
		if ( $switched ) {
			wp_set_current_user( $owner );
		}
		try {
			$result = call_user_func( $handlers[ $step['kind'] ], $step, $job );
		} catch ( \Throwable $e ) {
			$result = new \WP_Error( 'step_exception', __( 'The step stopped with an unexpected error.', 'chatbot' ), array( 'retryable' => true ) );
		} finally {
			if ( $switched ) {
				wp_set_current_user( $previous );
			}
		}

		if ( is_wp_error( $result ) ) {
			$data      = (array) $result->get_error_data();
			$retryable = isset( $data['retryable'] ) ? (bool) $data['retryable'] : ( isset( $data['cause'] ) && in_array( $data['cause'], Error_Classifier::RETRYABLE, true ) );
			$status    = Job_Store::fail( $step, (string) $result->get_error_code(), $result->get_error_message(), $retryable );
			return array(
				'ran'    => true,
				'status' => $status,
			);
		}

		$usage = array();
		if ( isset( $result['_usage'] ) && is_array( $result['_usage'] ) ) {
			$usage = $result['_usage'];
			unset( $result['_usage'] );
		}
		$done = Job_Store::complete( $step, (array) $result, $usage );
		return array(
			'ran'    => true,
			'status' => $done ? 'done' : 'lost',
		);
	}

	/**
	 * Run steps of one job until it stops or the time budget is spent.
	 *
	 * @param int $job_id  Job id.
	 * @param int $seconds Time budget.
	 * @return int Steps run.
	 */
	public static function run_for( $job_id, $seconds ) {
		$deadline = microtime( true ) + max( 1, (int) $seconds );
		$count    = 0;
		while ( microtime( true ) < $deadline ) {
			$result = self::run_one( $job_id );
			if ( ! $result['ran'] || in_array( $result['status'], array( 'failed', 'retry' ), true ) ) {
				break;
			}
			++$count;
		}
		return $count;
	}

	/**
	 * Progress of a job for screens: counts, current label, step summaries.
	 *
	 * @param int $job_id Job id.
	 * @return array|null
	 */
	public static function progress( $job_id ) {
		$job = Job_Store::get( $job_id );
		if ( ! $job ) {
			return null;
		}
		$steps   = Job_Store::steps( $job_id );
		$done    = 0;
		$current = '';
		$list    = array();
		$cost    = 0.0;
		foreach ( $steps as $step ) {
			if ( in_array( $step['status'], array( 'done', 'skipped' ), true ) ) {
				++$done;
			} elseif ( '' === $current ) {
				$current = $step['label'];
			}
			$cost  += $step['cost'];
			$list[] = array(
				'seq'      => $step['seq'],
				'kind'     => $step['kind'],
				'label'    => $step['label'],
				'status'   => $step['status'],
				'attempts' => $step['attempts'],
				'error'    => (string) $step['error_message'],
			);
		}
		$holder = Job_Store::budget_holder( $job );
		return array(
			'id'                => $job['id'],
			'type'              => $job['type'],
			'parent_id'         => (int) $job['parent_id'],
			// Per-job budget (US dollars; 0 for none) and what it has spent, children included.
			'budget'            => $holder ? (float) $holder['budget'] : 0.0,
			'spent'             => $holder ? round( Job_Store::spent( (int) $holder['id'] ), 4 ) : round( $cost, 4 ),
			'title'             => $job['title'],
			'status'            => $job['status'],
			'error'             => (string) $job['error_message'],
			'total'             => count( $steps ),
			'done'              => $done,
			'current'           => $current,
			'cost'              => round( $cost, 4 ),
			'estimate'          => (float) $job['estimate_cost'],
			'last_completed_at' => $job['last_completed_at'],
			'awaiting'          => isset( $job['payload']['awaiting'] ) ? (string) $job['payload']['awaiting'] : '',
			'steps'             => $list,
		);
	}
}
