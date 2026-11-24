<?php
/**
 * Suppression list: addresses (or whole domains) that are never emailed and
 * never added again, for example after a hard bounce or a complaint.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Audience;

use QuantumCloud\AiMarketing\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Suppression list.
 */
final class Suppression {

	/**
	 * Whether an email (or its domain) is suppressed.
	 *
	 * @param string $email Email.
	 * @return bool
	 */
	public static function blocked( $email ) {
		global $wpdb;
		$email = Rules::email( $email );
		if ( '' === $email ) {
			return false;
		}
		$domain = substr( strrchr( $email, '@' ), 1 );
		$table  = Schema::table( 'suppression' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE value = %s OR (kind = 'domain' AND value = %s) LIMIT 1", $email, $domain ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Add an address or domain.
	 *
	 * @param string $value  Email or domain.
	 * @param string $reason bounce, complaint, owner ...
	 * @param string $source Where it came from.
	 * @return bool
	 */
	public static function add( $value, $reason, $source = '' ) {
		global $wpdb;
		$value = strtolower( trim( (string) $value ) );
		$kind  = false === strpos( $value, '@' ) ? 'domain' : 'email';
		if ( 'email' === $kind ) {
			$value = Rules::email( $value );
		}
		if ( '' === $value ) {
			return false;
		}
		$table = Schema::table( 'suppression' );
		return (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$table}` (value, kind, reason, source, created_at) VALUES (%s, %s, %s, %s, %s)", $value, $kind, sanitize_key( (string) $reason ), sanitize_key( (string) $source ), current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}
}
