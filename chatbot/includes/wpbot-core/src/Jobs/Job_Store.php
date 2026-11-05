<?php
/**
 * Jobs and their steps.
 *
 * A job is a list of short steps. A step is claimed with a unique attempt ID
 * and a lock that expires, runs one short call, and is completed only by the
 * attempt that claimed it. Completed steps are never claimed again, so a
 * retry or a second runner can never repeat work or charges.
 *
 * Job statuses: queued, running, paused, done, failed, cancelled.
 * Step statuses: pending, running, retry, done, failed, skipped.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Jobs;

use QuantumCloud\WPBotCore\Settings;
use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for jobs and steps.
 */
final class Job_Store {

	/**
	 * Seconds a claimed step stays locked before another runner may take it.
	 */
	const LOCK_SECONDS = 180;

	/**
	 * Job statuses a runner may work on.
	 *
	 * @var string[]
	 */
	const ACTIVE = array( 'queued', 'running' );

	/**
	 * Create a job with its first steps.
	 *
	 * @param string $module Module id.
	 * @param string $type   Job type.
	 * @param array  $args   title, object_type, object_id, payload (array), estimate_cost, budget, parent_id (a job whose budget also covers this one).
	 * @param array  $steps  List of array( kind, label, input ).
	 * @return int Job id (0 on failure).
	 */
	public static function create( $module, $type, array $args, array $steps ) {
		global $wpdb;
		$now = self::now();
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, write.
			Schema::table( Schema::JOBS ),
			array(
				'module'        => substr( sanitize_key( $module ), 0, 40 ),
				'type'          => substr( sanitize_key( $type ), 0, 60 ),
				'title'         => isset( $args['title'] ) ? substr( sanitize_text_field( $args['title'] ), 0, 255 ) : '',
				'object_type'   => isset( $args['object_type'] ) ? substr( sanitize_key( $args['object_type'] ), 0, 20 ) : '',
				'object_id'     => isset( $args['object_id'] ) ? absint( $args['object_id'] ) : 0,
				'user_id'       => get_current_user_id(),
				'parent_id'     => isset( $args['parent_id'] ) ? absint( $args['parent_id'] ) : 0,
				'status'        => 'queued',
				'payload'       => wp_json_encode( isset( $args['payload'] ) ? (array) $args['payload'] : array() ),
				'estimate_cost' => isset( $args['estimate_cost'] ) ? (float) $args['estimate_cost'] : 0,
				'budget'        => isset( $args['budget'] ) ? (float) $args['budget'] : 0,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%f', '%f', '%s', '%s' )
		);
		if ( ! $ok ) {
			return 0;
		}
		$job_id = (int) $wpdb->insert_id;
		self::add_steps( $job_id, $steps );
		Cron_Runner::kick();
		return $job_id;
	}

	/**
	 * Append steps to a job (for example one per outline section).
	 *
	 * @param int   $job_id Job id.
	 * @param array $steps  List of array( kind, label, input ).
	 * @return void
	 */
	public static function add_steps( $job_id, array $steps ) {
		global $wpdb;
		$table = Schema::table( Schema::STEPS );
		$seq   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(seq) FROM {$table} WHERE job_id = %d", $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		foreach ( $steps as $step ) {
			++$seq;
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, write.
				$table,
				array(
					'job_id' => (int) $job_id,
					'seq'    => $seq,
					'kind'   => substr( sanitize_key( isset( $step['kind'] ) ? $step['kind'] : '' ), 0, 60 ),
					'label'  => substr( sanitize_text_field( isset( $step['label'] ) ? $step['label'] : '' ), 0, 255 ),
					'status' => 'pending',
					'input'  => wp_json_encode( isset( $step['input'] ) ? $step['input'] : array() ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * One job as an array (payload decoded), or null.
	 *
	 * @param int $job_id Job id.
	 * @return array|null
	 */
	public static function get( $job_id ) {
		global $wpdb;
		$table = Schema::table( Schema::JOBS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		if ( ! $row ) {
			return null;
		}
		$payload        = json_decode( (string) $row['payload'], true );
		$row['payload'] = is_array( $payload ) ? $payload : array();
		foreach ( array( 'id', 'object_id', 'user_id', 'parent_id' ) as $int_key ) {
			$row[ $int_key ] = (int) $row[ $int_key ];
		}
		return $row;
	}

	/**
	 * Steps of a job, in order, with decoded input and output.
	 *
	 * @param int $job_id Job id.
	 * @return array[]
	 */
	public static function steps( $job_id ) {
		global $wpdb;
		$table = Schema::table( Schema::STEPS );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE job_id = %d ORDER BY seq ASC", $job_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		foreach ( $rows as &$row ) {
			$row['input']  = self::decode( $row['input'] );
			$row['output'] = self::decode( $row['output'] );
			foreach ( array( 'id', 'job_id', 'seq', 'attempts', 'tokens_in', 'tokens_out' ) as $int_key ) {
				$row[ $int_key ] = (int) $row[ $int_key ];
			}
			$row['cost'] = (float) $row['cost'];
		}
		unset( $row );
		return $rows;
	}

	/**
	 * Replace the job payload (merging keys).
	 *
	 * @param int   $job_id  Job id.
	 * @param array $changes Keys to set.
	 * @return void
	 */
	public static function merge_payload( $job_id, array $changes ) {
		$job = self::get( $job_id );
		if ( ! $job ) {
			return;
		}
		self::update_job( $job_id, array( 'payload' => wp_json_encode( array_merge( $job['payload'], $changes ) ) ) );
	}

	/**
	 * Claim the next runnable step of a job.
	 *
	 * The next step is the lowest-seq step that is not done or skipped. It is
	 * claimed only if it is pending/retry, or running with an expired lock.
	 * The UPDATE is conditional, so two runners cannot claim the same step.
	 *
	 * @param int $job_id Job id.
	 * @return array|null The claimed step (with attempt_id), or null.
	 */
	public static function claim_next( $job_id ) {
		global $wpdb;
		$job = self::get( $job_id );
		if ( ! $job || ! in_array( $job['status'], self::ACTIVE, true ) ) {
			return null;
		}

		$table = Schema::table( Schema::STEPS );
		$next  = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, fresh row needed.
			$wpdb->prepare(
				"SELECT id, status FROM {$table} WHERE job_id = %d AND status NOT IN ('done','skipped') ORDER BY seq ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Name from code.
				$job_id
			),
			ARRAY_A
		);
		if ( ! $next ) {
			self::finish_job( $job_id );
			return null;
		}
		if ( 'failed' === $next['status'] ) {
			return null;
		}

		$attempt = bin2hex( random_bytes( 16 ) );
		$now     = self::now();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
		$claimed = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic claim on own table.
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'running', attempt_id = %s, lock_until = %s, attempts = attempts + 1, started_at = %s
				WHERE id = %d AND ( status IN ('pending','retry') OR ( status = 'running' AND lock_until < %s ) )",
				$attempt,
				gmdate( 'Y-m-d H:i:s', time() + self::LOCK_SECONDS ),
				$now,
				(int) $next['id'],
				$now
			)
		);
		// phpcs:enable
		if ( 1 !== (int) $claimed ) {
			return null;
		}
		if ( 'queued' === $job['status'] ) {
			self::update_job( $job_id, array( 'status' => 'running' ) );
		}
		foreach ( self::steps( $job_id ) as $step ) {
			if ( (int) $step['id'] === (int) $next['id'] ) {
				return $step;
			}
		}
		return null;
	}

	/**
	 * Mark a claimed step done. Ignored if another attempt owns it now.
	 *
	 * @param array $step   Claimed step.
	 * @param array $output Output to store.
	 * @param array $usage  tokens_in, tokens_out, cost.
	 * @return bool Whether this attempt completed the step.
	 */
	public static function complete( array $step, array $output, array $usage = array() ) {
		global $wpdb;
		$table = Schema::table( Schema::STEPS );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
		$done = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Conditional write on own table.
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'done', output = %s, tokens_in = %d, tokens_out = %d, cost = %f, error_code = '', error_message = NULL, lock_until = NULL, finished_at = %s
				WHERE id = %d AND attempt_id = %s AND status = 'running'",
				wp_json_encode( $output ),
				isset( $usage['tokens_in'] ) ? (int) $usage['tokens_in'] : 0,
				isset( $usage['tokens_out'] ) ? (int) $usage['tokens_out'] : 0,
				isset( $usage['cost'] ) ? (float) $usage['cost'] : 0,
				self::now(),
				(int) $step['id'],
				(string) $step['attempt_id']
			)
		);
		// phpcs:enable
		if ( 1 !== (int) $done ) {
			return false;
		}
		self::update_job( (int) $step['job_id'], array( 'last_completed_at' => self::now() ) );
		return true;
	}

	/**
	 * Record a failed attempt. Retryable failures go back to the queue until
	 * the attempt limit; others stop the job until the owner retries.
	 *
	 * @param array  $step      Claimed step.
	 * @param string $code      Error code.
	 * @param string $message   Owner-facing message.
	 * @param bool   $retryable Whether trying again may help.
	 * @return string New step status.
	 */
	public static function fail( array $step, $code, $message, $retryable ) {
		global $wpdb;
		$max    = (int) Settings::get( 'max_step_attempts' );
		$status = $retryable && (int) $step['attempts'] < $max ? 'retry' : 'failed';
		$table  = Schema::table( Schema::STEPS );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Conditional write on own table.
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, error_code = %s, error_message = %s, lock_until = NULL, finished_at = %s WHERE id = %d AND attempt_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Name from code.
				$status,
				substr( sanitize_key( $code ), 0, 60 ),
				sanitize_text_field( $message ),
				self::now(),
				(int) $step['id'],
				(string) $step['attempt_id']
			)
		);
		if ( 'failed' === $status ) {
			self::update_job(
				(int) $step['job_id'],
				array(
					'status'        => 'failed',
					'error_message' => sanitize_text_field( $message ),
				)
			);
		}
		return $status;
	}

	/**
	 * Pause a job (owner pressed Stop). The running step may still finish.
	 *
	 * @param int $job_id Job id.
	 * @return void
	 */
	public static function pause( $job_id ) {
		$job = self::get( $job_id );
		if ( $job && in_array( $job['status'], self::ACTIVE, true ) ) {
			self::update_job( $job_id, array( 'status' => 'paused' ) );
		}
	}

	/**
	 * Money spent by a job: its own steps plus those of its child jobs
	 * (a bulk job's articles), in US dollars.
	 *
	 * @param int $job_id Job id.
	 * @return float
	 */
	public static function spent( $job_id ) {
		global $wpdb;
		$jobs  = Schema::table( Schema::JOBS );
		$steps = Schema::table( Schema::STEPS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables; names from code.
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(s.cost),0) FROM {$steps} s WHERE s.job_id = %d OR s.job_id IN (SELECT j.id FROM {$jobs} j WHERE j.parent_id = %d)", $job_id, $job_id ) );
	}

	/**
	 * The job whose budget applies: the job itself when it has one, else
	 * its parent's (null when no budget is set).
	 *
	 * @param array $job Job.
	 * @return array|null
	 */
	public static function budget_holder( array $job ) {
		if ( (float) $job['budget'] > 0 ) {
			return $job;
		}
		if ( ! empty( $job['parent_id'] ) ) {
			$parent = self::get( (int) $job['parent_id'] );
			if ( $parent && (float) $parent['budget'] > 0 ) {
				return $parent;
			}
		}
		return null;
	}

	/**
	 * Set a job's budget (US dollars, 0 for none) and, when it was paused
	 * for its budget, resume it and its paused child jobs.
	 *
	 * @param int   $job_id Job id.
	 * @param float $budget Budget.
	 * @return void
	 */
	public static function set_budget( $job_id, $budget ) {
		global $wpdb;
		self::update_job( $job_id, array( 'budget' => max( 0, round( (float) $budget, 4 ) ) ) );
		$ids   = array( (int) $job_id );
		$table = Schema::table( Schema::JOBS );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE parent_id = %d", $job_id ) ) as $child ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
			$ids[] = (int) $child;
		}
		foreach ( $ids as $id ) {
			$job = self::get( $id );
			if ( $job && isset( $job['payload']['awaiting'] ) && 'budget' === $job['payload']['awaiting'] ) {
				self::merge_payload( $id, array( 'awaiting' => '' ) );
				self::resume( $id );
			}
		}
	}

	/**
	 * Pause a job that reached its budget (owner input: raise it).
	 *
	 * @param int $job_id Job id.
	 * @return void
	 */
	public static function pause_for_budget( $job_id ) {
		self::merge_payload( $job_id, array( 'awaiting' => 'budget' ) );
		self::pause( $job_id );
		self::update_job( $job_id, array( 'error_message' => __( 'This job reached its budget. Raise the budget to continue.', 'chatbot' ) ) );
	}

	/**
	 * Resume a paused job, or retry a failed one from its failed step.
	 * Completed steps stay completed. A job waiting for owner input
	 * (payload "awaiting" set, for example an outline to approve) is not
	 * resumed here; the module clears "awaiting" when the input arrives.
	 *
	 * @param int $job_id Job id.
	 * @return void
	 */
	public static function resume( $job_id ) {
		global $wpdb;
		$job = self::get( $job_id );
		if ( ! $job || ! in_array( $job['status'], array( 'paused', 'failed' ), true ) || ! empty( $job['payload']['awaiting'] ) ) {
			return;
		}
		$table = Schema::table( Schema::STEPS );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table write.
			$wpdb->prepare( "UPDATE {$table} SET status = 'retry', attempts = 0 WHERE job_id = %d AND status = 'failed'", $job_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Name from code.
		);
		self::update_job(
			$job_id,
			array(
				'status'        => 'queued',
				'error_message' => null,
			)
		);
		Cron_Runner::kick();
	}

	/**
	 * Cancel a job for good.
	 *
	 * @param int $job_id Job id.
	 * @return void
	 */
	public static function cancel( $job_id ) {
		self::update_job( $job_id, array( 'status' => 'cancelled' ) );
	}

	/**
	 * Note that a browser screen is driving this job right now.
	 *
	 * @param int $job_id Job id.
	 * @return void
	 */
	public static function touch_browser( $job_id ) {
		self::update_job( $job_id, array( 'browser_seen_at' => self::now() ) );
	}

	/**
	 * Ids of jobs a runner may work on, oldest first.
	 *
	 * @param int $limit Max jobs.
	 * @return int[]
	 */
	public static function active_ids( $limit = 20 ) {
		global $wpdb;
		$table = Schema::table( Schema::JOBS );
		$ids   = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, fresh list.
			$wpdb->prepare( "SELECT id FROM {$table} WHERE status IN ('queued','running') ORDER BY id ASC LIMIT %d", $limit ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Name from code.
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Recent jobs of a module for a user (or everyone with $user_id = 0).
	 *
	 * @param string $module  Module id.
	 * @param int    $user_id User filter.
	 * @param int    $limit   Max rows.
	 * @return array[]
	 */
	public static function recent( $module, $user_id = 0, $limit = 20 ) {
		global $wpdb;
		$table = Schema::table( Schema::JOBS );
		$sql   = $wpdb->prepare( "SELECT id, type, title, status, object_type, object_id, user_id, created_at, updated_at, last_completed_at, error_message FROM {$table} WHERE module = %s", $module ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Name from code.
		if ( $user_id > 0 ) {
			$sql .= $wpdb->prepare( ' AND user_id = %d', $user_id );
		}
		$sql .= $wpdb->prepare( ' ORDER BY id DESC LIMIT %d', $limit );
		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Built from prepare() fragments.
	}

	/**
	 * Mark the job done when no step is left.
	 *
	 * @param int $job_id Job id.
	 * @return void
	 */
	private static function finish_job( $job_id ) {
		$job = self::get( $job_id );
		if ( $job && in_array( $job['status'], self::ACTIVE, true ) ) {
			self::update_job( $job_id, array( 'status' => 'done' ) );

			/**
			 * Fires when every step of a job is done.
			 *
			 * @param array $job Job.
			 */
			do_action( 'qcld_wpbot_core/job_done', self::get( $job_id ) );
		}
	}

	/**
	 * Update job columns and the updated_at stamp.
	 *
	 * @param int   $job_id Job id.
	 * @param array $data   Columns.
	 * @return void
	 */
	private static function update_job( $job_id, array $data ) {
		global $wpdb;
		$data['updated_at'] = self::now();
		$wpdb->update( Schema::table( Schema::JOBS ), $data, array( 'id' => (int) $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table write.
	}

	/**
	 * Decode a JSON column.
	 *
	 * @param string|null $json JSON.
	 * @return mixed
	 */
	private static function decode( $json ) {
		if ( null === $json || '' === $json ) {
			return null;
		}
		return json_decode( (string) $json, true );
	}

	/**
	 * Current UTC time in MySQL format.
	 *
	 * @return string
	 */
	private static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
