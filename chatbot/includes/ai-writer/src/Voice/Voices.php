<?php
/**
 * Voice profiles, stored in the voices table.
 *
 * The core keeps one site voice (stable key "site_voice"); the Pro module
 * adds more through the same store. A profile is plain, editable data:
 * summary, sliders (0-100), point of view, sentence length, openings,
 * signature phrases, words to avoid, formatting, spelling, reading level,
 * call-to-action style and two or three excerpts of the owner's own writing.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Voice;

use QuantumCloud\AiWriter\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Voice storage.
 */
final class Voices {

	const SITE_VOICE = 'site_voice';

	/**
	 * Fields of a profile with their type.
	 *
	 * @var array<string,string>
	 */
	const FIELDS = array(
		'summary'           => 'text',
		'point_of_view'     => 'text',
		'sentence_length'   => 'text',
		'openings'          => 'text',
		'formatting'        => 'text',
		'spelling'          => 'text',
		'reading_level'     => 'text',
		'cta_style'         => 'text',
		'signature_phrases' => 'list',
		'avoid_words'       => 'list',
		'excerpts'          => 'list',
	);

	/**
	 * The site voice, or null when none is saved.
	 *
	 * @return array|null array( id, name, profile ).
	 */
	public static function site_voice() {
		return self::get_by_key( self::SITE_VOICE );
	}

	/**
	 * A voice by stable key.
	 *
	 * @param string $key Stable key.
	 * @return array|null
	 */
	public static function get_by_key( $key ) {
		global $wpdb;
		$table = Schema::table( Schema::VOICES );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, profile FROM {$table} WHERE stable_key = %s AND type = 'voice'", $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		return $row ? self::row( $row ) : null;
	}

	/**
	 * A voice by id.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::VOICES );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, profile FROM {$table} WHERE id = %d AND type = 'voice'", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		return $row ? self::row( $row ) : null;
	}

	/**
	 * Save a voice under a stable key (insert or update).
	 *
	 * @param string $key     Stable key.
	 * @param string $name    Name.
	 * @param array  $profile Profile (sanitised here).
	 * @return array Saved voice.
	 */
	public static function save( $key, $name, array $profile ) {
		global $wpdb;
		$table   = Schema::table( Schema::VOICES );
		$now     = gmdate( 'Y-m-d H:i:s' );
		$profile = self::sanitise( $profile );
		$current = self::get_by_key( $key );
		$data    = array(
			'name'       => substr( sanitize_text_field( $name ), 0, 191 ),
			'profile'    => wp_json_encode( $profile ),
			'updated_at' => $now,
		);
		if ( $current ) {
			$wpdb->update( $table, $data, array( 'id' => $current['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table write.
		} else {
			$data['stable_key']    = substr( sanitize_key( $key ), 0, 64 );
			$data['type']          = 'voice';
			$data['owner_user_id'] = get_current_user_id();
			$data['created_at']    = $now;
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table write.
		}
		return self::get_by_key( $key );
	}

	/**
	 * Rows of one type from the voices table (voices, Pro saved briefs and
	 * custom templates share it, told apart by type).
	 *
	 * @param string $type Row type.
	 * @return array[] id, key, name, profile (decoded data).
	 */
	public static function items( $type = 'voice' ) {
		global $wpdb;
		$table = Schema::table( Schema::VOICES );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, stable_key, name, profile FROM {$table} WHERE type = %s ORDER BY name ASC LIMIT 200", sanitize_key( $type ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$item        = self::row( $row );
			$item['key'] = (string) $row['stable_key'];
			$out[]       = $item;
		}
		return $out;
	}

	/**
	 * Insert or update a row of any type. The caller sanitises $data.
	 *
	 * @param string $type Row type.
	 * @param string $key  Stable key.
	 * @param string $name Name.
	 * @param array  $data Data (stored as JSON).
	 * @return int Row id.
	 */
	public static function save_item( $type, $key, $name, array $data ) {
		global $wpdb;
		$table = Schema::table( Schema::VOICES );
		$key   = substr( sanitize_key( $key ), 0, 64 );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE stable_key = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		$row   = array(
			'name'       => substr( sanitize_text_field( $name ), 0, 191 ),
			'profile'    => wp_json_encode( $data ),
			'updated_at' => $now,
		);
		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table write.
			return $id;
		}
		$row['stable_key']    = $key;
		$row['type']          = sanitize_key( $type );
		$row['owner_user_id'] = get_current_user_id();
		$row['created_at']    = $now;
		$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table write.
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a row of a type (never the site voice).
	 *
	 * @param int    $id   Row id.
	 * @param string $type Row type.
	 * @return bool
	 */
	public static function delete_item( $id, $type ) {
		global $wpdb;
		$table = Schema::table( Schema::VOICES );
		return (bool) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id = %d AND type = %s AND stable_key <> %s", $id, sanitize_key( $type ), self::SITE_VOICE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
	}

	/**
	 * Keep only known fields, with safe values.
	 *
	 * @param array $profile Raw profile.
	 * @return array
	 */
	public static function sanitise( array $profile ) {
		$clean = array();
		foreach ( self::FIELDS as $field => $type ) {
			if ( ! isset( $profile[ $field ] ) ) {
				continue;
			}
			if ( 'list' === $type ) {
				$items           = is_array( $profile[ $field ] ) ? $profile[ $field ] : explode( ',', (string) $profile[ $field ] );
				$limit           = 'excerpts' === $field ? 3 : 30;
				$max             = 'excerpts' === $field ? 700 : 80;
				$items           = array_slice( array_values( array_filter( array_map( 'trim', array_map( 'sanitize_textarea_field', array_map( 'strval', $items ) ) ) ) ), 0, $limit );
				$clean[ $field ] = array_map(
					static function ( $item ) use ( $max ) {
						return function_exists( 'mb_substr' ) ? mb_substr( $item, 0, $max ) : substr( $item, 0, $max );
					},
					$items
				);
			} else {
				$clean[ $field ] = substr( sanitize_textarea_field( (string) $profile[ $field ] ), 0, 500 );
			}
		}
		$clean['sliders'] = array();
		foreach ( array( 'formal', 'serious', 'technical', 'reserved' ) as $slider ) {
			$clean['sliders'][ $slider ] = isset( $profile['sliders'][ $slider ] ) ? max( 0, min( 100, (int) $profile['sliders'][ $slider ] ) ) : 50;
		}
		/**
		 * Keep extra profile fields (Pro: per-voice style guide, channel variants).
		 * Filters must sanitise what they add.
		 *
		 * @param array $clean   Sanitised profile.
		 * @param array $profile Raw profile.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/voice_profile', $clean, $profile );
	}

	/**
	 * Decode a row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function row( array $row ) {
		$profile = json_decode( (string) $row['profile'], true );
		return array(
			'id'      => (int) $row['id'],
			'name'    => (string) $row['name'],
			'profile' => is_array( $profile ) ? $profile : array(),
		);
	}
}
