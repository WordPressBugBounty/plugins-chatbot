<?php
/**
 * One identity per person (A6): the ids a contact is known by elsewhere —
 * WordPress user, WooCommerce customer, chat visitor, WhatsApp number …
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Audience;

use QuantumCloud\AiMarketing\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Identities.
 */
final class Identities {

	/**
	 * Link an id to a contact. An id already linked to someone else stays with them.
	 *
	 * @param int    $contact_id Contact id.
	 * @param string $kind       wp_user, woo_customer, chat_visitor, whatsapp, messenger, telegram ...
	 * @param string $value      The id.
	 * @return bool Whether the id now belongs to this contact.
	 */
	public static function link( $contact_id, $kind, $value ) {
		global $wpdb;
		$kind  = substr( sanitize_key( (string) $kind ), 0, 30 );
		$value = substr( trim( (string) $value ), 0, 190 );
		if ( ! $contact_id || '' === $kind || '' === $value ) {
			return false;
		}
		$table = Schema::table( 'identities' );
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$table}` (contact_id, kind, value, created_at) VALUES (%d, %s, %s, %s)", (int) $contact_id, $kind, $value, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return self::find( $kind, $value ) === (int) $contact_id;
	}

	/**
	 * The contact an id belongs to.
	 *
	 * @param string $kind  Kind.
	 * @param string $value Id.
	 * @return int Contact id (0 when unknown).
	 */
	public static function find( $kind, $value ) {
		global $wpdb;
		$table = Schema::table( 'identities' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM `{$table}` WHERE kind = %s AND value = %s", sanitize_key( (string) $kind ), (string) $value ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Every id of a contact.
	 *
	 * @param int $contact_id Contact id.
	 * @return array[] kind, value, created_at.
	 */
	public static function of_contact( $contact_id ) {
		global $wpdb;
		$table = Schema::table( 'identities' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT kind, value, created_at FROM `{$table}` WHERE contact_id = %d ORDER BY id ASC", (int) $contact_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}
}
