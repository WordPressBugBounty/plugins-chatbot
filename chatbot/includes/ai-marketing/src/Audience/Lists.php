<?php
/**
 * Lists and tags, and who is in them (with the reason why).
 *
 * System lists (system_key set, e.g. "chatbot" for ChatBot Subscribers) can
 * be renamed but not deleted.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Audience;

use QuantumCloud\AiMarketing\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Lists and tags.
 */
final class Lists {

	const TYPES = array( 'list', 'tag' );

	/**
	 * All lists or tags with member counts.
	 *
	 * @param string $type   list|tag.
	 * @param string $search Name search.
	 * @return array[]
	 */
	public static function all( $type, $search = '' ) {
		global $wpdb;
		$lists   = Schema::table( 'lists' );
		$members = Schema::table( 'memberships' );
		$type    = in_array( $type, self::TYPES, true ) ? $type : 'list';
		$like    = '%' . $wpdb->esc_like( (string) $search ) . '%';
		$rows    = (array) $wpdb->get_results( $wpdb->prepare( "SELECT l.*, (SELECT COUNT(*) FROM `{$members}` m WHERE m.list_id = l.id) AS members FROM `{$lists}` l WHERE l.type = %s AND l.name LIKE %s ORDER BY l.system_key IS NULL, l.name ASC", $type, $like ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
		$out     = array_map( array( __CLASS__, 'shape' ), $rows );
		foreach ( $out as $i => $list ) {
			if ( 'segment' === $list['kind'] ) {
				$out[ $i ]['members'] = (int) Segments::members( $list['rules'], true ); // Live count.
			}
		}
		return $out;
	}

	/**
	 * One list or tag.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$lists = Schema::table( 'lists' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$lists}` WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Create a list or tag.
	 *
	 * @param string $type        list|tag.
	 * @param string $name        Name.
	 * @param string $description Description.
	 * @param string $origin      owner, scan, system, agent.
	 * @return int|WP_Error Id.
	 */
	public static function create( $type, $name, $description = '', $origin = 'owner' ) {
		global $wpdb;
		$type = in_array( $type, self::TYPES, true ) ? $type : 'list';
		$name = trim( sanitize_text_field( (string) $name ) );
		if ( '' === $name ) {
			return new WP_Error( 'qcld_mk_name', __( 'Give it a name.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$slug  = self::unique_slug( $type, sanitize_title( $name ) );
		$now   = current_time( 'mysql', true );
		$saved = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'lists' ),
			array(
				'type'        => $type,
				'name'        => substr( $name, 0, 190 ),
				'slug'        => $slug,
				'description' => sanitize_textarea_field( (string) $description ),
				'origin'      => sanitize_key( (string) $origin ),
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		return $saved ? (int) $wpdb->insert_id : new WP_Error( 'qcld_mk_db', __( 'It could not be saved. Please try again.', 'chatbot' ), array( 'status' => 500 ) );
	}

	/**
	 * The id of a system list, created on first use.
	 *
	 * @param string $key  System key, e.g. chatbot.
	 * @param string $name Name used when it is created.
	 * @param string $type list|tag.
	 * @return int
	 */
	public static function ensure_system( $key, $name, $type = 'list' ) {
		global $wpdb;
		$lists = Schema::table( 'lists' );
		$key   = sanitize_key( (string) $key );
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$lists}` WHERE system_key = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $id ) {
			return $id;
		}
		$created = self::create( $type, $name, '', 'system' );
		if ( is_wp_error( $created ) ) {
			return 0;
		}
		$wpdb->update( $lists, array( 'system_key' => $key ), array( 'id' => $created ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return $created;
	}

	/**
	 * Rename or describe a list or tag. An edit by the owner locks a smart
	 * list, so automation never overwrites it.
	 *
	 * @param int   $id       Id.
	 * @param array $data     name, description.
	 * @param bool  $by_owner The owner made the change.
	 * @return array|WP_Error
	 */
	public static function update( $id, array $data, $by_owner = true ) {
		global $wpdb;
		$list = self::get( $id );
		if ( ! $list ) {
			return self::not_found();
		}
		$changes = array();
		if ( isset( $data['name'] ) ) {
			$name = trim( sanitize_text_field( (string) $data['name'] ) );
			if ( '' === $name ) {
				return new WP_Error( 'qcld_mk_name', __( 'Give it a name.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$changes['name'] = substr( $name, 0, 190 );
		}
		if ( isset( $data['description'] ) ) {
			$changes['description'] = sanitize_textarea_field( (string) $data['description'] );
		}
		if ( $changes ) {
			if ( $by_owner && 'smart' === $list['kind'] ) {
				$changes['locked'] = 1;
			}
			$changes['updated_at'] = current_time( 'mysql', true );
			$wpdb->update( Schema::table( 'lists' ), $changes, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		return self::get( $id );
	}

	/**
	 * A smart list by its key (e.g. "category:shoes", "lifecycle:vip").
	 *
	 * @param string $key Smart key.
	 * @return array|null
	 */
	public static function by_smart_key( $key ) {
		global $wpdb;
		$lists = Schema::table( 'lists' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$lists}` WHERE smart_key = %s", substr( (string) $key, 0, 80 ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Every smart list.
	 *
	 * @return array[]
	 */
	public static function smart() {
		global $wpdb;
		$lists   = Schema::table( 'lists' );
		$members = Schema::table( 'memberships' );
		$rows    = (array) $wpdb->get_results( "SELECT l.*, (SELECT COUNT(*) FROM `{$members}` m WHERE m.list_id = l.id) AS members FROM `{$lists}` l WHERE l.kind = 'smart' ORDER BY l.name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
		return array_map( array( __CLASS__, 'shape' ), $rows );
	}

	/**
	 * Create a smart list (a list that automation fills from rules), or return
	 * the one that already has this key.
	 *
	 * @param string $key         Smart key, unique.
	 * @param string $name        Name.
	 * @param string $description Description.
	 * @param array  $rules       Rules the sorter reads.
	 * @param string $origin      scan, agent ...
	 * @return int|WP_Error
	 */
	public static function create_smart( $key, $name, $description, array $rules, $origin = 'scan' ) {
		global $wpdb;
		$existing = self::by_smart_key( $key );
		if ( $existing ) {
			return (int) $existing['id'];
		}
		$id = self::create( 'list', $name, $description, $origin );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'lists' ),
			array(
				'kind'      => 'smart',
				'smart_key' => substr( (string) $key, 0, 80 ),
				'rules'     => wp_json_encode( $rules ),
			),
			array( 'id' => (int) $id )
		);
		return (int) $id;
	}

	/**
	 * Replace a smart list's rules unless the owner locked it.
	 *
	 * @param int   $id    Id.
	 * @param array $rules Rules.
	 * @return bool Whether they changed.
	 */
	public static function set_rules( $id, array $rules ) {
		global $wpdb;
		$list = self::get( $id );
		if ( ! $list || $list['locked'] || 'smart' !== $list['kind'] ) {
			return false;
		}
		return (bool) $wpdb->update( Schema::table( 'lists' ), array( 'rules' => wp_json_encode( $rules ) ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Members added more than $days ago (for lists like "New subscribers").
	 *
	 * @param int $list_id List id.
	 * @param int $days    Days.
	 * @param int $limit   Max ids.
	 * @return int[]
	 */
	public static function members_older_than( $list_id, $days, $limit = 500 ) {
		global $wpdb;
		$members = Schema::table( 'memberships' );
		$before  = gmdate( 'Y-m-d H:i:s', time() - max( 0, (int) $days ) * DAY_IN_SECONDS );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT contact_id FROM `{$members}` WHERE list_id = %d AND added_at < %s LIMIT %d", (int) $list_id, $before, max( 1, (int) $limit ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Whether a contact is in a list.
	 *
	 * @param int $list_id    List id.
	 * @param int $contact_id Contact id.
	 * @return bool
	 */
	public static function has_member( $list_id, $contact_id ) {
		global $wpdb;
		$members = Schema::table( 'memberships' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `{$members}` WHERE list_id = %d AND contact_id = %d", (int) $list_id, (int) $contact_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Delete a list or tag (members stay as contacts). System lists cannot be deleted.
	 *
	 * @param int $id Id.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$list = self::get( $id );
		if ( ! $list ) {
			return self::not_found();
		}
		if ( $list['system'] ) {
			return new WP_Error( 'qcld_mk_system_list', __( 'This list is managed by WPBot and cannot be deleted. You can rename it.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$wpdb->delete( Schema::table( 'memberships' ), array( 'list_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->delete( Schema::table( 'lists' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		/**
		 * A list or tag was deleted.
		 *
		 * @param array $list The list as it was.
		 */
		do_action( 'qcld_wpbot_marketing/list_deleted', $list );
		return true;
	}

	/**
	 * Put a contact in a list or tag (no-op when already in it).
	 *
	 * @param int    $list_id    List id.
	 * @param int    $contact_id Contact id.
	 * @param string $reason     Why (shown to the owner).
	 * @param string $origin     owner, capture, scan, sorter, import, agent.
	 * @param int    $confidence 0–100.
	 * @return bool Whether the contact was added now.
	 */
	public static function add_member( $list_id, $contact_id, $reason = '', $origin = 'owner', $confidence = 100 ) {
		global $wpdb;
		$table = Schema::table( 'memberships' );
		$added = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$table}` (list_id, contact_id, reason, confidence, origin, added_at) VALUES (%d, %d, %s, %d, %s, %s)", (int) $list_id, (int) $contact_id, substr( (string) $reason, 0, 255 ), max( 0, min( 100, (int) $confidence ) ), sanitize_key( (string) $origin ), current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $added > 0 ) {
			/**
			 * A contact was added to a list or tag.
			 *
			 * @param int    $contact_id Contact id.
			 * @param int    $list_id    List or tag id.
			 * @param string $origin     Who added them.
			 */
			do_action( 'qcld_wpbot_marketing/list_added', (int) $contact_id, (int) $list_id, (string) $origin );
		}
		return $added > 0;
	}

	/**
	 * Take a contact out of a list or tag.
	 *
	 * @param int $list_id    List id.
	 * @param int $contact_id Contact id.
	 * @return bool Whether they were in it.
	 */
	public static function remove_member( $list_id, $contact_id ) {
		global $wpdb;
		$removed = (int) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'memberships' ),
			array(
				'list_id'    => (int) $list_id,
				'contact_id' => (int) $contact_id,
			)
		);
		if ( $removed > 0 ) {
			/**
			 * A contact was removed from a list or tag.
			 *
			 * @param int $contact_id Contact id.
			 * @param int $list_id    List or tag id.
			 */
			do_action( 'qcld_wpbot_marketing/list_removed', (int) $contact_id, (int) $list_id );
		}
		return $removed > 0;
	}

	/**
	 * Lists and tags of a contact.
	 *
	 * @param int $contact_id Contact id.
	 * @return array[] Each: id, type, name, reason, origin, added_at.
	 */
	public static function of_contact( $contact_id ) {
		global $wpdb;
		$lists   = Schema::table( 'lists' );
		$members = Schema::table( 'memberships' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT l.id, l.type, l.name, m.reason, m.origin, m.confidence, m.added_at FROM `{$members}` m JOIN `{$lists}` l ON l.id = m.list_id WHERE m.contact_id = %d ORDER BY l.type, l.name", (int) $contact_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
	}

	/**
	 * Lists and tags of many contacts at once (contact id => group ids).
	 *
	 * @param int[] $contact_ids Contact ids.
	 * @return array<int,int[]>
	 */
	public static function memberships( array $contact_ids ) {
		global $wpdb;
		$members = Schema::table( 'memberships' );
		$out     = array();
		foreach ( array_chunk( array_values( array_unique( array_map( 'intval', $contact_ids ) ) ), 1000 ) as $chunk ) {
			$in   = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT contact_id, list_id FROM `{$members}` WHERE contact_id IN ({$in})", $chunk ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- Own table; placeholders built above.
			foreach ( (array) $rows as $row ) {
				$out[ (int) $row['contact_id'] ][] = (int) $row['list_id'];
			}
		}
		return $out;
	}

	/**
	 * Make a contact's lists (or tags) exactly the given set.
	 *
	 * @param int    $contact_id Contact id.
	 * @param string $type       list|tag.
	 * @param int[]  $ids        Wanted ids.
	 * @return void
	 */
	public static function set_for_contact( $contact_id, $type, array $ids ) {
		$ids     = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		$current = array();
		foreach ( self::of_contact( $contact_id ) as $row ) {
			if ( $row['type'] === $type ) {
				$current[] = (int) $row['id'];
			}
		}
		foreach ( array_diff( $current, $ids ) as $remove ) {
			self::remove_member( $remove, $contact_id );
		}
		foreach ( array_diff( $ids, $current ) as $add ) {
			$list = self::get( $add );
			if ( $list && $list['type'] === $type ) {
				self::add_member( $add, $contact_id, __( 'Added by the owner', 'chatbot' ), 'owner' );
			}
		}
	}

	/**
	 * Row as returned to the app.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		return array(
			'id'          => (int) $row['id'],
			'type'        => (string) $row['type'],
			'name'        => (string) $row['name'],
			'description' => (string) $row['description'],
			'kind'        => (string) $row['kind'],
			'origin'      => (string) $row['origin'],
			'system'      => ! empty( $row['system_key'] ),
			'system_key'  => (string) $row['system_key'],
			'smart_key'   => isset( $row['smart_key'] ) ? (string) $row['smart_key'] : '',
			'rules'       => isset( $row['rules'] ) && is_string( $row['rules'] ) && '' !== $row['rules'] ? (array) json_decode( $row['rules'], true ) : array(),
			'locked'      => ! empty( $row['locked'] ),
			'members'     => isset( $row['members'] ) ? (int) $row['members'] : null,
			'created_at'  => (string) $row['created_at'],
		);
	}

	/**
	 * A slug not used yet for this type.
	 *
	 * @param string $type Type.
	 * @param string $slug Wanted slug.
	 * @return string
	 */
	private static function unique_slug( $type, $slug ) {
		global $wpdb;
		$lists = Schema::table( 'lists' );
		$slug  = '' !== $slug ? substr( $slug, 0, 180 ) : 'list';
		$base  = $slug;
		$i     = 2;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$lists}` WHERE type = %s AND slug = %s", $type, $slug ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			$slug = $base . '-' . ( $i++ );
		}
		return $slug;
	}

	/**
	 * Not-found error.
	 *
	 * @return WP_Error
	 */
	private static function not_found() {
		return new WP_Error( 'qcld_mk_not_found', __( 'That list or tag was not found.', 'chatbot' ), array( 'status' => 404 ) );
	}
}
