<?php
/**
 * Contacts table access.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * SQL for wpbot_crm_contacts. Business rules live in ContactService.
 */
class ContactRepository extends Repository {

	/**
	 * Table suffix.
	 *
	 * @var string
	 */
	protected $table_name = 'contacts';

	/**
	 * Writable columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'email', 'first_name', 'last_name', 'phone', 'company', 'website', 'status', 'source', 'attributes', 'token', 'confirmed_at', 'unsubscribed_at', 'last_activity_at' );

	/**
	 * JSON columns.
	 *
	 * @var string[]
	 */
	protected $json = array( 'attributes' );

	/**
	 * Contact by email (case-insensitive; emails are stored lower-case).
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public function find_by_email( $email ) {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", strtolower( trim( (string) $email ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Contact by public token.
	 *
	 * @param string $token 32-char token.
	 * @return array|null
	 */
	public function find_by_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( '' === $token ) {
			return null;
		}
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Paginated, filtered list.
	 *
	 * @param array $args {
	 *     @type int    $page     1-based page (default 1).
	 *     @type int    $per_page Rows per page, 0 = all (default 20).
	 *     @type string $search   Matches email, first/last name, phone, company.
	 *     @type string $status   Exact status filter.
	 *     @type int    $list_id  Only members of this group.
	 *     @type int    $tag_id   Only members of this group.
	 *     @type string $orderby  id|email|created_at|first_name|last_name|status (default id).
	 *     @type string $order    ASC|DESC (default DESC).
	 * }
	 * @return array{items:array[],total:int}
	 */
	public function search( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'page'     => 1,
				'per_page' => 20,
				'search'   => '',
				'status'   => '',
				'list_id'  => 0,
				'tag_id'   => 0,
				'orderby'  => 'id',
				'order'    => 'DESC',
			)
		);

		$where   = array( '1=1' );
		$params  = array();
		$members = Schema::table( 'group_members' );

		if ( '' !== (string) $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR phone LIKE %s OR company LIKE %s)';
			$params   = array_merge( $params, array_fill( 0, 5, $like ) );
		}
		if ( '' !== (string) $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}
		foreach ( array( 'list_id', 'tag_id' ) as $key ) {
			if ( (int) $args[ $key ] > 0 ) {
				$where[]  = "id IN (SELECT contact_id FROM {$members} WHERE group_id = %d)";
				$params[] = (int) $args[ $key ];
			}
		}

		$orderby = in_array( $args['orderby'], array( 'id', 'email', 'created_at', 'first_name', 'last_name', 'status' ), true ) ? $args['orderby'] : 'id';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		return $this->paginate( 'WHERE ' . implode( ' AND ', $where ), $params, "{$orderby} {$order}", (int) $args['page'], (int) $args['per_page'] );
	}

	/**
	 * Number of contacts, optionally limited to statuses.
	 *
	 * @param string[] $statuses Statuses; empty = all.
	 * @return int
	 */
	public function count( array $statuses = array() ) {
		global $wpdb;
		$table = $this->table();
		if ( empty( $statuses ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status IN ({$placeholders})", array_values( $statuses ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Contact counts per status.
	 *
	 * @return array<string,int>
	 */
	public function count_by_status() {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['status'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Which of the given emails already exist (lower-cased).
	 *
	 * @param string[] $emails Emails.
	 * @return string[]
	 */
	public function existing_emails( array $emails ) {
		global $wpdb;
		$emails = array_values( array_unique( array_filter( array_map( 'strtolower', array_map( 'trim', array_map( 'strval', $emails ) ) ) ) ) );
		if ( empty( $emails ) ) {
			return array();
		}
		$table = $this->table();
		$found = array();
		foreach ( array_chunk( $emails, 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$found        = array_merge( $found, (array) $wpdb->get_col( $wpdb->prepare( "SELECT email FROM {$table} WHERE email IN ({$placeholders})", $chunk ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $found;
	}

	/**
	 * Keyset iteration for exports: up to $limit contacts with id > $after_id, ascending.
	 *
	 * @param int $after_id Last id seen.
	 * @param int $limit    Batch size.
	 * @return array[]
	 */
	public function chunk( $after_id, $limit = 500 ) {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT %d", (int) $after_id, max( 1, (int) $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->hydrate_all( $rows );
	}

	/**
	 * Set last_activity_at = now.
	 *
	 * @param int $id Contact id.
	 * @return bool
	 */
	public function touch_activity( $id ) {
		global $wpdb;
		return false !== $wpdb->update( $this->table(), array( 'last_activity_at' => Dates::now() ), array( 'id' => (int) $id ) );
	}

	/**
	 * Delete a contact plus its memberships and runs. Messages/events are kept for stats.
	 *
	 * @param int $id Contact id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		$wpdb->delete( Schema::table( 'group_members' ), array( 'contact_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'automation_runs' ), array( 'contact_id' => (int) $id ), array( '%d' ) );
		return parent::delete( $id );
	}
}
