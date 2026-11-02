<?php
/**
 * Automation runs (one per automation + contact).
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Jobs\Scheduler;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * SQL for wpbot_crm_automation_runs. Status: waiting|done|exited|failed.
 */
class RunRepository extends Repository {

	/**
	 * Table suffix.
	 *
	 * @var string
	 */
	protected $table_name = 'automation_runs';

	/**
	 * Writable columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'automation_id', 'contact_id', 'step_index', 'status', 'next_run_at' );

	/**
	 * Integer columns.
	 *
	 * @var string[]
	 */
	protected $ints = array( 'automation_id', 'contact_id', 'step_index' );

	/**
	 * Enroll a contact (at most once per automation).
	 *
	 * @param int    $automation_id Automation id.
	 * @param int    $contact_id    Contact id.
	 * @param string $next_run_at   UTC datetime when step 0 runs.
	 * @return int New run id, 0 when the contact was already enrolled.
	 */
	public function enroll( $automation_id, $contact_id, $next_run_at ) {
		global $wpdb;
		$table = $this->table();
		$now   = Dates::now();
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (automation_id, contact_id, step_index, status, next_run_at, created_at, updated_at) VALUES (%d, %d, 0, 'waiting', %s, %s, %s)", (int) $automation_id, (int) $contact_id, (string) $next_run_at, $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->rows_affected <= 0 ) {
			return 0;
		}
		Scheduler::wake();
		return (int) $wpdb->insert_id;
	}

	/**
	 * Waiting runs whose next_run_at has passed, oldest first.
	 *
	 * @param string $now_utc Current UTC datetime.
	 * @param int    $limit   Max rows.
	 * @return array[]
	 */
	public function due( $now_utc, $limit = 100 ) {
		global $wpdb;
		$table = $this->table();
		return $this->hydrate_all( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'waiting' AND next_run_at <= %s ORDER BY next_run_at ASC, id ASC LIMIT %d", (string) $now_utc, max( 1, (int) $limit ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Move a run to another step.
	 *
	 * @param int    $id          Run id.
	 * @param int    $step_index  Next step index.
	 * @param string $next_run_at UTC datetime.
	 * @return bool
	 */
	public function advance( $id, $step_index, $next_run_at ) {
		return $this->update(
			$id,
			array(
				'step_index'  => (int) $step_index,
				'next_run_at' => (string) $next_run_at,
				'status'      => 'waiting',
			)
		);
	}

	/**
	 * Finish a run.
	 *
	 * @param int    $id     Run id.
	 * @param string $status done|exited|failed.
	 * @return bool
	 */
	public function finish( $id, $status = 'done' ) {
		return $this->update(
			$id,
			array(
				'status'      => in_array( $status, array( 'done', 'exited', 'failed' ), true ) ? $status : 'done',
				'next_run_at' => null,
			)
		);
	}

	/**
	 * Run counts per status.
	 *
	 * @param int $automation_id Automation id (0 = all automations).
	 * @return array<string,int>
	 */
	public function count_by_status( $automation_id = 0 ) {
		global $wpdb;
		$table = $this->table();
		if ( (int) $automation_id > 0 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM {$table} WHERE automation_id = %d GROUP BY status", (int) $automation_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$out = array(
			'waiting' => 0,
			'done'    => 0,
			'exited'  => 0,
			'failed'  => 0,
		);
		foreach ( (array) $rows as $row ) {
			$out[ $row['status'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Whether a contact was ever enrolled in an automation.
	 *
	 * @param int $automation_id Automation id.
	 * @param int $contact_id    Contact id.
	 * @return bool
	 */
	public function exists( $automation_id, $contact_id ) {
		global $wpdb;
		$table = $this->table();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE automation_id = %d AND contact_id = %d", (int) $automation_id, (int) $contact_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Delete all runs of an automation.
	 *
	 * @param int $automation_id Automation id.
	 * @return int Rows deleted.
	 */
	public function delete_for_automation( $automation_id ) {
		global $wpdb;
		return (int) $wpdb->delete( $this->table(), array( 'automation_id' => (int) $automation_id ), array( '%d' ) );
	}
}
