<?php
/**
 * Version history (CR-14): the last versions of each AI-written piece, so
 * the owner can flip back and restore one. Pieces that belong to a post
 * are kept in post meta; other pieces (Quick write) per user for a week.
 *
 * A slot names the piece: title, excerpt, meta, faq, summary,
 * variations:<kind>, transform, write_here, quick:<template>, section:<n>…
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Regenerate;

defined( 'ABSPATH' ) || exit;

use QuantumCloud\AiWriter\Settings;

/**
 * Version history.
 */
final class History {

	/** Post meta holding versions per slot. */
	const META = '_qcld_wpbot_writer_versions';

	/** Transient prefix for pieces not tied to a post. */
	const TRANSIENT = 'qcwr_versions_';

	/**
	 * Clean a slot name.
	 *
	 * @param string $slot Slot.
	 * @return string
	 */
	public static function slot( $slot ) {
		return substr( preg_replace( '/[^a-z0-9_:\-]/', '', strtolower( (string) $slot ) ), 0, 80 );
	}

	/**
	 * How many versions to keep (setting versions_keep, 1-20).
	 *
	 * @return int
	 */
	public static function keep() {
		return max( 1, min( 20, (int) Settings::get( 'versions_keep' ) ) );
	}

	/**
	 * Add a version and return the list (newest last).
	 *
	 * @param int    $post_id Post (0 for none).
	 * @param string $slot    Slot.
	 * @param mixed  $data    What the generator returned (stored as is).
	 * @param array  $options Options used.
	 * @return array[] Versions.
	 */
	public static function add( $post_id, $slot, $data, array $options ) {
		$slot = self::slot( $slot );
		if ( '' === $slot ) {
			return array();
		}
		$all          = self::read( $post_id );
		$list         = isset( $all[ $slot ] ) ? (array) $all[ $slot ] : array();
		$list[]       = array(
			'data'    => $data,
			'options' => $options,
			'label'   => Options::summary( $options ),
			'time'    => time(),
			'user'    => get_current_user_id(),
		);
		$all[ $slot ] = array_slice( $list, -self::keep() );
		self::write( $post_id, $all );
		return self::view( $all[ $slot ] );
	}

	/**
	 * Versions of a slot (newest last).
	 *
	 * @param int    $post_id Post (0 for none).
	 * @param string $slot    Slot.
	 * @return array[]
	 */
	public static function get( $post_id, $slot ) {
		$all  = self::read( $post_id );
		$slot = self::slot( $slot );
		return isset( $all[ $slot ] ) ? self::view( (array) $all[ $slot ] ) : array();
	}

	/**
	 * Delete the versions of a post (uninstall and privacy).
	 *
	 * @param int $post_id Post.
	 * @return void
	 */
	public static function forget( $post_id ) {
		delete_post_meta( (int) $post_id, self::META );
	}

	/**
	 * Versions as sent to the screens.
	 *
	 * @param array[] $rows Stored versions.
	 * @return array[] data, options, label, time.
	 */
	private static function view( array $rows ) {
		return array_values(
			array_map(
				static function ( $row ) {
					return array(
						'data'    => isset( $row['data'] ) ? $row['data'] : null,
						'options' => isset( $row['options'] ) ? (array) $row['options'] : array(),
						'label'   => isset( $row['label'] ) ? (string) $row['label'] : '',
						'time'    => isset( $row['time'] ) ? (int) $row['time'] : 0,
					);
				},
				$rows
			)
		);
	}

	/**
	 * Stored slots.
	 *
	 * @param int $post_id Post (0: the current user's pieces).
	 * @return array
	 */
	private static function read( $post_id ) {
		$value = $post_id ? get_post_meta( (int) $post_id, self::META, true ) : get_transient( self::TRANSIENT . get_current_user_id() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Save slots.
	 *
	 * @param int   $post_id Post (0: the current user's pieces).
	 * @param array $all     Slots.
	 * @return void
	 */
	private static function write( $post_id, array $all ) {
		if ( $post_id ) {
			update_post_meta( (int) $post_id, self::META, $all );
			return;
		}
		// Not tied to a post: keep the 30 most recent slots for a week.
		set_transient( self::TRANSIENT . get_current_user_id(), array_slice( $all, -30, null, true ), WEEK_IN_SECONDS );
	}
}
