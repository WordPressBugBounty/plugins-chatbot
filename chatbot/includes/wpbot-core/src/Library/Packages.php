<?php
/**
 * Content packages: one record per finished piece, readable by every module.
 *
 * A package holds what other modules need to reuse a piece (summary, key
 * points, facts used, images, product data, channel versions) without
 * parsing the post. AI Writer writes packages; Social, AI Marketing and the
 * Growth Dashboard read them through qcld_wpbot_writer_get_package().
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Library;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Package storage.
 */
final class Packages {

	/**
	 * Fields kept in the JSON data column.
	 *
	 * @var string[]
	 */
	const DATA_FIELDS = array( 'key_points', 'audience', 'voice', 'facts_used', 'images', 'product', 'url' );

	/**
	 * Package of a post, or null.
	 *
	 * @param int $post_id Post id.
	 * @return array|null
	 */
	public static function get( $post_id ) {
		global $wpdb;
		$table = Schema::table( Schema::PACKAGES );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d", $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		if ( ! $row ) {
			return null;
		}
		$data     = json_decode( (string) $row['data'], true );
		$versions = json_decode( (string) $row['channel_versions'], true );
		$package  = array(
			'post_id'          => (int) $row['post_id'],
			'module'           => (string) $row['module'],
			'status'           => (string) $row['status'],
			'title'            => (string) $row['title'],
			'summary'          => (string) $row['summary'],
			'channel_versions' => is_array( $versions ) ? $versions : array(),
			'finalized_at'     => $row['finalized_at'],
		);
		foreach ( self::DATA_FIELDS as $field ) {
			$package[ $field ] = is_array( $data ) && isset( $data[ $field ] ) ? $data[ $field ] : null;
		}
		return $package;
	}

	/**
	 * Create or update the package of a post.
	 *
	 * @param int    $post_id Post id.
	 * @param string $module  Writing module.
	 * @param array  $fields  title, summary, status, channel_versions and DATA_FIELDS.
	 * @return bool
	 */
	public static function save( $post_id, $module, array $fields ) {
		global $wpdb;
		$current = self::get( $post_id );
		$data    = array();
		foreach ( self::DATA_FIELDS as $field ) {
			if ( array_key_exists( $field, $fields ) ) {
				$data[ $field ] = $fields[ $field ];
			} elseif ( $current && null !== $current[ $field ] ) {
				$data[ $field ] = $current[ $field ];
			}
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$row = array(
			'post_id'    => (int) $post_id,
			'module'     => substr( sanitize_key( $module ), 0, 40 ),
			'status'     => isset( $fields['status'] ) ? sanitize_key( $fields['status'] ) : ( $current ? $current['status'] : 'draft' ),
			'title'      => isset( $fields['title'] ) ? sanitize_text_field( $fields['title'] ) : ( $current ? $current['title'] : '' ),
			'summary'    => isset( $fields['summary'] ) ? sanitize_textarea_field( $fields['summary'] ) : ( $current ? $current['summary'] : '' ),
			'data'       => wp_json_encode( $data ),
			'updated_at' => $now,
		);
		if ( isset( $fields['channel_versions'] ) ) {
			$row['channel_versions'] = wp_json_encode( (array) $fields['channel_versions'] );
		}
		if ( 'final' === $row['status'] && ( ! $current || empty( $current['finalized_at'] ) ) ) {
			$row['finalized_at'] = $now;
		}

		$table = Schema::table( Schema::PACKAGES );
		if ( $current ) {
			return false !== $wpdb->update( $table, $row, array( 'post_id' => (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table write.
		}
		$row['created_at'] = $now;
		return (bool) $wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table write.
	}
}
