<?php
/**
 * Automations table access.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * SQL for wpbot_crm_automations. Status: published|draft. `steps` and `trigger_config` are JSON.
 */
class AutomationRepository extends Repository {

	const STATUSES = array( 'published', 'draft' );

	/**
	 * Table suffix.
	 *
	 * @var string
	 */
	protected $table_name = 'automations';

	/**
	 * Writable columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'name', 'status', 'trigger_type', 'trigger_config', 'steps', 'enrolled', 'completed' );

	/**
	 * JSON columns.
	 *
	 * @var string[]
	 */
	protected $json = array( 'trigger_config', 'steps' );

	/**
	 * Integer columns.
	 *
	 * @var string[]
	 */
	protected $ints = array( 'enrolled', 'completed' );

	/**
	 * All automations, newest first.
	 *
	 * @return array[]
	 */
	public function all() {
		$result = $this->paginate( '', array(), 'id DESC', 1, 0 );
		return $result['items'];
	}

	/**
	 * Published automations listening to a trigger.
	 *
	 * @param string $trigger_type Trigger name (e.g. subscriber_created).
	 * @return array[]
	 */
	public function published_for_trigger( $trigger_type ) {
		global $wpdb;
		$table = $this->table();
		return $this->hydrate_all( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'published' AND trigger_type = %s ORDER BY id ASC", (string) $trigger_type ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Totals for the automations list header.
	 *
	 * @return array{total_automations:int,total_enrolled:int,total_active:int,total_completed:int}
	 */
	public function overview() {
		global $wpdb;
		$table = $this->table();
		$runs  = Schema::table( 'automation_runs' );
		$row   = $wpdb->get_row( "SELECT COUNT(*) AS total, COALESCE(SUM(enrolled),0) AS enrolled, COALESCE(SUM(completed),0) AS completed FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'total_automations' => isset( $row['total'] ) ? (int) $row['total'] : 0,
			'total_enrolled'    => isset( $row['enrolled'] ) ? (int) $row['enrolled'] : 0,
			'total_active'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$runs} WHERE status = 'waiting'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'total_completed'   => isset( $row['completed'] ) ? (int) $row['completed'] : 0,
		);
	}

	/**
	 * Delete an automation and its runs.
	 *
	 * @param int $id Automation id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		$wpdb->delete( Schema::table( 'automation_runs' ), array( 'automation_id' => (int) $id ), array( '%d' ) );
		return parent::delete( $id );
	}
}
