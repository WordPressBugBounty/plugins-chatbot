<?php
/**
 * Campaigns table access.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * SQL for wpbot_crm_campaigns. `audience` and `settings` are JSON arrays.
 */
class CampaignRepository extends Repository {

	const STATUSES = array( 'draft', 'scheduled', 'sending', 'paused', 'sent', 'failed' );

	/**
	 * Table suffix.
	 *
	 * @var string
	 */
	protected $table_name = 'campaigns';

	/**
	 * Writable columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'name', 'subject', 'preview_text', 'from_name', 'from_email', 'reply_to', 'content_html', 'template_id', 'audience', 'settings', 'status', 'scheduled_at', 'started_at', 'finished_at', 'recipients', 'sent', 'failed' );

	/**
	 * JSON columns.
	 *
	 * @var string[]
	 */
	protected $json = array( 'audience', 'settings' );

	/**
	 * Integer columns.
	 *
	 * @var string[]
	 */
	protected $ints = array( 'template_id', 'recipients', 'sent', 'failed' );

	/**
	 * Paginated list, newest first.
	 *
	 * @param array $args page (1), per_page (0 = all), search (name/subject), status.
	 * @return array{items:array[],total:int}
	 */
	public function search( array $args = array() ) {
		global $wpdb;
		$args   = wp_parse_args(
			$args,
			array(
				'page'     => 1,
				'per_page' => 0,
				'search'   => '',
				'status'   => '',
			)
		);
		$where  = array( '1=1' );
		$params = array();
		if ( '' !== (string) $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR subject LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( '' !== (string) $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}
		return $this->paginate( 'WHERE ' . implode( ' AND ', $where ), $params, 'id DESC', (int) $args['page'], (int) $args['per_page'] );
	}

	/**
	 * Campaigns created at or after a UTC datetime.
	 *
	 * @param string $since_utc MySQL datetime (UTC).
	 * @return int
	 */
	public function count_created_since( $since_utc ) {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", (string) $since_utc ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Campaigns with a status.
	 *
	 * @param string $status Status.
	 * @return int
	 */
	public function count_by_status( $status ) {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", (string) $status ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * All campaigns with a status, oldest first.
	 *
	 * @param string $status Status.
	 * @return array[]
	 */
	public function by_status( $status ) {
		global $wpdb;
		$table = $this->table();
		return $this->hydrate_all( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC", (string) $status ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Ids of scheduled campaigns whose scheduled_at has passed.
	 *
	 * @param string $now_utc Current UTC datetime.
	 * @return int[]
	 */
	public function due_scheduled( $now_utc ) {
		global $wpdb;
		$table = $this->table();
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= %s ORDER BY scheduled_at ASC", (string) $now_utc ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Change status, optionally with other columns (e.g. started_at).
	 *
	 * @param int    $id     Campaign id.
	 * @param string $status New status.
	 * @param array  $extra  Additional columns.
	 * @return bool
	 */
	public function set_status( $id, $status, array $extra = array() ) {
		$extra['status'] = (string) $status;
		return $this->update( $id, $extra );
	}

	/**
	 * Delete a campaign and its queued (unsent) messages. Sent messages are kept for stats.
	 *
	 * @param int $id Campaign id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		$wpdb->delete(
			Schema::table( 'messages' ),
			array(
				'campaign_id' => (int) $id,
				'status'      => 'queued',
			),
			array( '%d', '%s' )
		);
		return parent::delete( $id );
	}
}
