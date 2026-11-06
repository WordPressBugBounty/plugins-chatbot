<?php
/**
 * Honesty flags kept with a piece (round 3, CR-13). AI-written pieces are
 * checked when AI Writer finishes them and on every save; the flags are
 * stored in post meta and cannot be dismissed. The owner can still
 * publish; when a piece goes live with flags, who and when is recorded.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Persuasion;

defined( 'ABSPATH' ) || exit;

/**
 * Honesty record.
 */
final class Honesty_Record {

	/** Post meta: flags, checked_at, log (published with flags). */
	const META = '_qcld_wpbot_writer_honesty';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_writer/content_finalized', array( __CLASS__, 'finalized' ), 5, 2 );
		add_action( 'save_post', array( __CLASS__, 'saved' ), 30, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'published' ), 30, 3 );
	}

	/**
	 * Store the choices and check a piece AI Writer just finished.
	 *
	 * @param int   $post_id Post.
	 * @param array $brief   Brief.
	 * @return void
	 */
	public static function finalized( $post_id, $brief ) {
		if ( ! Persuasion::available() || ! $post_id ) {
			return;
		}
		if ( is_array( $brief ) && isset( $brief['persuasion'] ) ) {
			update_post_meta( (int) $post_id, Choices::META, wp_slash( Choices::sanitise( $brief['persuasion'] ) + array( 'owner' => ! empty( $brief['persuasion']['owner'] ) ) ) );
		}
		self::check( (int) $post_id, is_array( $brief ) ? \QuantumCloud\AiWriter\Writing\Brief::facts( $brief ) : array() );
	}

	/**
	 * Re-check on save (pieces AI Writer wrote or checked before).
	 *
	 * @param int      $post_id Post.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function saved( $post_id, $post ) {
		if ( ! Persuasion::available() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post instanceof \WP_Post ) {
			return;
		}
		if ( ! get_post_meta( $post_id, Choices::META, true ) && ! get_post_meta( $post_id, self::META, true ) && ! get_post_meta( $post_id, '_qcld_wpbot_writer_job_id', true ) ) {
			return;
		}
		self::check( (int) $post_id );
	}

	/**
	 * Record a piece going live with flags (it is never blocked).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function published( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! $post instanceof \WP_Post ) {
			return;
		}
		$record = self::get( $post->ID );
		if ( array() === $record['flags'] ) {
			return;
		}
		$record['log'][] = array(
			'user'  => get_current_user_id(),
			'at'    => gmdate( 'c' ),
			'codes' => array_values( array_unique( wp_list_pluck( $record['flags'], 'code' ) ) ),
		);
		$record['log']   = array_slice( $record['log'], -20 );
		update_post_meta( $post->ID, self::META, wp_slash( $record ) );
	}

	/**
	 * Check a post now and store the flags.
	 *
	 * @param int      $post_id Post.
	 * @param string[] $facts   Facts the writer was given (they back claims).
	 * @return array[] Flags.
	 */
	public static function check( $post_id, array $facts = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		$record = self::get( $post_id );
		if ( array() !== $facts ) {
			$record['facts'] = substr( implode( "\n", array_map( 'strval', $facts ) ), 0, 20000 );
		}
		$product = 'product' === $post->post_type ? (int) $post_id : 0;
		$text    = $post->post_title . "\n" . $post->post_excerpt . "\n" . $post->post_content;
		$flags   = qcld_wpbot_core_honesty_check( $text, Context::honesty( $record['facts'], $product ) );

		$record['flags']      = $flags;
		$record['checked_at'] = gmdate( 'c' );
		update_post_meta( $post_id, self::META, wp_slash( $record ) );
		return $flags;
	}

	/**
	 * Stored record.
	 *
	 * @param int $post_id Post.
	 * @return array flags, checked_at, log, facts.
	 */
	public static function get( $post_id ) {
		$record = get_post_meta( (int) $post_id, self::META, true );
		$record = is_array( $record ) ? $record : array();
		return $record + array(
			'flags'      => array(),
			'checked_at' => '',
			'log'        => array(),
			'facts'      => '',
		);
	}
}
