<?php
/**
 * Lists and tags (one table, `type` = list|tag) and their memberships.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * SQL for wpbot_crm_groups and wpbot_crm_group_members.
 *
 * Membership writes here do NOT fire hooks; use ContactService for hook-firing changes.
 */
class GroupRepository extends Repository {

	const TYPES = array( 'list', 'tag' );

	/**
	 * Table suffix.
	 *
	 * @var string
	 */
	protected $table_name = 'groups';

	/**
	 * Writable columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'type', 'name', 'slug', 'description' );

	/**
	 * Members table.
	 *
	 * @return string
	 */
	public function members_table() {
		return Schema::table( 'group_members' );
	}

	/**
	 * All groups of a type with a `subscribers_count` member count, ordered by name.
	 *
	 * @param string $type   list|tag.
	 * @param string $search Optional name filter.
	 * @return array[]
	 */
	public function all_with_counts( $type, $search = '' ) {
		global $wpdb;
		$table   = $this->table();
		$members = $this->members_table();
		$sql     = "SELECT g.*, COUNT(m.contact_id) AS subscribers_count FROM {$table} g LEFT JOIN {$members} m ON m.group_id = g.id WHERE g.type = %s";
		$params  = array( self::type( $type ) );
		if ( '' !== (string) $search ) {
			$sql     .= ' AND g.name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $search ) . '%';
		}
		$sql .= ' GROUP BY g.id ORDER BY g.name ASC';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$row                      = $this->hydrate( $row );
			$row['subscribers_count'] = (int) $row['subscribers_count'];
			$out[]                    = $row;
		}
		return $out;
	}

	/**
	 * A group by id, only if it has the given type.
	 *
	 * @param int    $id   Group id.
	 * @param string $type list|tag.
	 * @return array|null
	 */
	public function find_typed( $id, $type ) {
		$group = $this->find( $id );
		return ( $group && self::type( $type ) === $group['type'] ) ? $group : null;
	}

	/**
	 * A group by type and slug.
	 *
	 * @param string $type list|tag.
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public function find_by_slug( $type, $slug ) {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE type = %s AND slug = %s", self::type( $type ), (string) $slug ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Create a group. The slug is derived from the name and made unique within the type.
	 *
	 * @param string $type        list|tag.
	 * @param string $name        Name.
	 * @param string $description Description.
	 * @return int New id, 0 on failure.
	 */
	public function create( $type, $name, $description = '' ) {
		$type = self::type( $type );
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return 0;
		}
		$base = self::slugify( $name );
		$slug = $base;
		for ( $i = 2; $this->find_by_slug( $type, $slug ); $i++ ) {
			$slug = substr( $base, 0, 180 ) . '-' . $i;
		}
		return $this->insert(
			array(
				'type'        => $type,
				'name'        => $name,
				'slug'        => $slug,
				'description' => (string) $description,
			)
		);
	}

	/**
	 * Id of the group with this name's slug, creating it when missing.
	 *
	 * @param string $type list|tag.
	 * @param string $name Name.
	 * @return int Group id, 0 when $name is empty or the insert failed.
	 */
	public function ensure( $type, $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return 0;
		}
		$existing = $this->find_by_slug( $type, self::slugify( $name ) );
		if ( $existing ) {
			return (int) $existing['id'];
		}
		return $this->create( $type, $name );
	}

	/**
	 * Delete a group and its memberships (contacts are kept).
	 *
	 * @param int $id Group id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		$wpdb->delete( $this->members_table(), array( 'group_id' => (int) $id ), array( '%d' ) );
		return parent::delete( $id );
	}

	/**
	 * Filter ids to those that exist (optionally of one type).
	 *
	 * @param int[]  $ids  Ids.
	 * @param string $type list|tag or '' for any.
	 * @return int[]
	 */
	public function existing_ids( array $ids, $type = '' ) {
		global $wpdb;
		$ids = self::ids( $ids );
		if ( empty( $ids ) ) {
			return array();
		}
		$table = $this->table();
		$in    = implode( ',', $ids );
		if ( '' === $type ) {
			$found = $wpdb->get_col( "SELECT id FROM {$table} WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ints.
		} else {
			$found = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE type = %s AND id IN ({$in})", self::type( $type ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return array_map( 'intval', (array) $found );
	}

	/**
	 * Number of members of a group.
	 *
	 * @param int $group_id Group id.
	 * @return int
	 */
	public function member_count( $group_id ) {
		global $wpdb;
		$members = $this->members_table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$members} WHERE group_id = %d", (int) $group_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Add a contact to a group.
	 *
	 * @param int $group_id   Group id.
	 * @param int $contact_id Contact id.
	 * @return bool True when the membership was newly created.
	 */
	public function add_member( $group_id, $contact_id ) {
		global $wpdb;
		$members = $this->members_table();
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$members} (group_id, contact_id, added_at) VALUES (%d, %d, %s)", (int) $group_id, (int) $contact_id, Dates::now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->rows_affected > 0;
	}

	/**
	 * Remove a contact from a group.
	 *
	 * @param int $group_id   Group id.
	 * @param int $contact_id Contact id.
	 * @return bool True when a membership was removed.
	 */
	public function remove_member( $group_id, $contact_id ) {
		global $wpdb;
		return (bool) $wpdb->delete(
			$this->members_table(),
			array(
				'group_id'   => (int) $group_id,
				'contact_id' => (int) $contact_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Group ids a contact belongs to.
	 *
	 * @param int    $contact_id Contact id.
	 * @param string $type       list|tag or '' for both.
	 * @return int[]
	 */
	public function ids_for_contact( $contact_id, $type = '' ) {
		global $wpdb;
		$table   = $this->table();
		$members = $this->members_table();
		$sql     = "SELECT m.group_id FROM {$members} m INNER JOIN {$table} g ON g.id = m.group_id WHERE m.contact_id = %d";
		$params  = array( (int) $contact_id );
		if ( '' !== $type ) {
			$sql     .= ' AND g.type = %s';
			$params[] = self::type( $type );
		}
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Make a contact's memberships of one type exactly $ids (ids of other types / unknown ids are ignored).
	 *
	 * @param int    $contact_id Contact id.
	 * @param string $type       list|tag.
	 * @param int[]  $ids        Desired group ids.
	 * @return array{added:int[],removed:int[]}
	 */
	public function set_members_for_contact( $contact_id, $type, array $ids ) {
		$desired = $this->existing_ids( $ids, $type );
		$current = $this->ids_for_contact( $contact_id, $type );
		$added   = array();
		$removed = array();
		foreach ( array_diff( $desired, $current ) as $group_id ) {
			if ( $this->add_member( $group_id, $contact_id ) ) {
				$added[] = (int) $group_id;
			}
		}
		foreach ( array_diff( $current, $desired ) as $group_id ) {
			if ( $this->remove_member( $group_id, $contact_id ) ) {
				$removed[] = (int) $group_id;
			}
		}
		return array(
			'added'   => $added,
			'removed' => $removed,
		);
	}

	/**
	 * Groups for many contacts in one query.
	 *
	 * @param int[] $contact_ids Contact ids.
	 * @return array<int,array{list:array[],tag:array[]}> contact_id => [ 'list' => [ ['id'=>,'name'=>] ], 'tag' => [...] ].
	 */
	public function groups_for_contacts( array $contact_ids ) {
		global $wpdb;
		$contact_ids = self::ids( $contact_ids );
		$out         = array();
		foreach ( $contact_ids as $id ) {
			$out[ $id ] = array(
				'list' => array(),
				'tag'  => array(),
			);
		}
		if ( empty( $contact_ids ) ) {
			return $out;
		}
		$table   = $this->table();
		$members = $this->members_table();
		$in      = implode( ',', $contact_ids );
		$rows    = $wpdb->get_results( "SELECT m.contact_id, g.id, g.type, g.name FROM {$members} m INNER JOIN {$table} g ON g.id = m.group_id WHERE m.contact_id IN ({$in}) ORDER BY g.name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ints.
		foreach ( (array) $rows as $row ) {
			$type = self::type( $row['type'] );
			$out[ (int) $row['contact_id'] ][ $type ][] = array(
				'id'   => (int) $row['id'],
				'name' => $row['name'],
			);
		}
		return $out;
	}

	/**
	 * Normalise a type value.
	 *
	 * @param string $type Raw type.
	 * @return string list|tag
	 */
	public static function type( $type ) {
		return 'tag' === $type ? 'tag' : 'list';
	}

	/**
	 * Slug for a group name.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function slugify( $name ) {
		$slug = sanitize_title( (string) $name );
		if ( '' === $slug ) {
			$slug = substr( md5( strtolower( (string) $name ) ), 0, 12 );
		}
		return substr( $slug, 0, 190 );
	}
}
