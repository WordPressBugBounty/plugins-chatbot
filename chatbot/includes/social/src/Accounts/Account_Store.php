<?php
/**
 * Connected accounts (Facebook Pages, Instagram, Threads, Google Business
 * Profile locations, Telegram channels). Tokens live in Token_Vault, never
 * in this table.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Accounts;

use QuantumCloud\Social\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Account storage.
 */
final class Account_Store {

	/**
	 * Allowed statuses: ok, expired (reconnect), error (temporary), paused.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'ok', 'expired', 'error', 'paused' );

	/**
	 * Every account, ordered for the Accounts screen.
	 *
	 * @param int $brand_id Only this brand (0 = all).
	 * @return array[]
	 */
	public static function all( $brand_id = 0 ) {
		global $wpdb;
		$table    = Schema::table( 'accounts' );
		$rows     = $brand_id > 0
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE brand_id = %d ORDER BY brand_id, network, name", (int) $brand_id ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			: $wpdb->get_results( "SELECT * FROM {$table} ORDER BY brand_id, network, name", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table, no input.
		$accounts = array_map( array( __CLASS__, 'shape' ), (array) $rows );
		// Brand members see only their brands' accounts (QA #25).
		return array_values(
			array_filter(
				$accounts,
				static function ( $account ) {
					return \QuantumCloud\Social\Support\Brand_Access::can( (int) $account['brand_id'] );
				}
			)
		);
	}

	/**
	 * One account.
	 *
	 * @param int $id Account id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( 'accounts' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Accounts that came from one login (for example one Facebook user).
	 *
	 * @param string $login Login key.
	 * @return array[]
	 */
	public static function by_login( $login ) {
		global $wpdb;
		$table = Schema::table( 'accounts' );
		return array_map( array( __CLASS__, 'shape' ), (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE login = %s", (string) $login ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Add or update an account (matched by network and remote id).
	 *
	 * @param array $data network, kind, remote_id, name, handle, login, parent_id, brand_id, meta.
	 * @return int Account id (0 on failure).
	 */
	public static function upsert( array $data ) {
		global $wpdb;
		$table  = Schema::table( 'accounts' );
		$now    = current_time( 'mysql', true );
		$fields = array(
			'network'        => sanitize_key( isset( $data['network'] ) ? $data['network'] : '' ),
			'kind'           => sanitize_key( isset( $data['kind'] ) ? $data['kind'] : '' ),
			'remote_id'      => substr( (string) preg_replace( '/[^A-Za-z0-9_@.:\/\-]/', '', isset( $data['remote_id'] ) ? (string) $data['remote_id'] : '' ), 0, 191 ),
			'name'           => substr( sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' ), 0, 191 ),
			'handle'         => substr( sanitize_text_field( isset( $data['handle'] ) ? $data['handle'] : '' ), 0, 191 ),
			'login'          => substr( sanitize_text_field( isset( $data['login'] ) ? $data['login'] : '' ), 0, 191 ),
			'parent_id'      => isset( $data['parent_id'] ) ? absint( $data['parent_id'] ) : 0,
			'brand_id'       => isset( $data['brand_id'] ) ? absint( $data['brand_id'] ) : 0,
			'status'         => 'ok',
			'status_message' => '',
			'meta'           => wp_json_encode( isset( $data['meta'] ) ? (array) $data['meta'] : array() ),
			'updated_at'     => $now,
		);
		if ( '' === $fields['network'] || '' === $fields['remote_id'] ) {
			return 0;
		}
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE network = %s AND remote_id = %s", $fields['network'], $fields['remote_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $existing ) {
			$wpdb->update( $table, $fields, array( 'id' => $existing ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			return $existing;
		}
		$fields['created_at'] = $now;
		$wpdb->insert( $table, $fields ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return (int) $wpdb->insert_id;
	}

	/**
	 * The account row for a Post yourself network, created on first use.
	 *
	 * @param string $network Manual network id.
	 * @return int Account id, or 0 when the network is not a manual one.
	 */
	public static function ensure_manual( $network ) {
		global $wpdb;
		$network = sanitize_key( (string) $network );
		$adapter = \QuantumCloud\Social\Module::instance()->network( $network );
		if ( ! $adapter instanceof \QuantumCloud\Social\Networks\Manual_Network ) {
			return 0;
		}
		$table    = Schema::table( 'accounts' );
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE network = %s AND remote_id = %s", $network, 'manual' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $existing ) {
			return $existing;
		}
		return self::upsert(
			array(
				'network'   => $network,
				'kind'      => 'manual',
				'remote_id' => 'manual',
				/* translators: %s: network, e.g. WhatsApp. */
				'name'      => sprintf( __( '%s (post yourself)', 'chatbot' ), $adapter->label() ),
			)
		);
	}

	/**
	 * Change editable fields: auto_share, brand_id.
	 *
	 * @param int   $id      Account id.
	 * @param array $changes Changes.
	 * @return bool
	 */
	public static function update( $id, array $changes ) {
		global $wpdb;
		$fields = array();
		if ( array_key_exists( 'auto_share', $changes ) ) {
			$fields['auto_share'] = empty( $changes['auto_share'] ) ? 0 : 1;
		}
		if ( array_key_exists( 'brand_id', $changes ) ) {
			$fields['brand_id'] = absint( $changes['brand_id'] );
		}
		if ( array() === $fields ) {
			return false;
		}
		$fields['updated_at'] = current_time( 'mysql', true );
		return false !== $wpdb->update( Schema::table( 'accounts' ), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Set the status of every account from one login, or of one account.
	 *
	 * @param string $login   Login key ('' to use $ids).
	 * @param string $status  Status.
	 * @param string $message Plain-language message.
	 * @param int[]  $ids     Account ids when no login is given.
	 * @return void
	 */
	public static function set_status( $login, $status, $message = '', array $ids = array() ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return;
		}
		$fields = array(
			'status'         => $status,
			'status_message' => substr( sanitize_text_field( $message ), 0, 255 ),
			'updated_at'     => current_time( 'mysql', true ),
		);
		if ( '' !== $login ) {
			$wpdb->update( Schema::table( 'accounts' ), $fields, array( 'login' => $login ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			return;
		}
		foreach ( $ids as $id ) {
			$wpdb->update( Schema::table( 'accounts' ), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		}
	}

	/**
	 * Remove an account and its token. Posts already published stay on the network.
	 *
	 * @param int $id Account id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$account = self::get( $id );
		if ( ! $account ) {
			return false;
		}
		Token_Vault::forget( self::key( $account ) );
		return false !== $wpdb->delete( Schema::table( 'accounts' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Token vault key for an account.
	 *
	 * @param array $account Account.
	 * @return string
	 */
	public static function key( array $account ) {
		return $account['network'] . ':' . $account['remote_id'];
	}

	/**
	 * Typed row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$row['id']         = (int) $row['id'];
		$row['parent_id']  = (int) $row['parent_id'];
		$row['brand_id']   = (int) $row['brand_id'];
		$row['auto_share'] = (bool) (int) $row['auto_share'];
		$meta              = json_decode( (string) $row['meta'], true );
		$row['meta']       = is_array( $meta ) ? $meta : array();
		return $row;
	}
}
