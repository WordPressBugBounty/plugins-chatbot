<?php
/**
 * Fires qcld_wpbot_writer/content_published when a finished piece (one
 * with a content package) goes live, for the first time or again after it
 * was unpublished. Public contract (brief 7.8).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\WPBotCore\Library\Packages;

defined( 'ABSPATH' ) || exit;

/**
 * Publish events.
 */
final class Publish_Events {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'transition_post_status', array( __CLASS__, 'transition' ), 20, 3 );
	}

	/**
	 * A post changed status.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || wp_is_post_revision( $post ) ) {
			return;
		}
		$package = Packages::get( (int) $post->ID );
		if ( null === $package || Module::ID !== $package['module'] ) {
			return;
		}
		/**
		 * A finished AI Writer piece went live.
		 *
		 * @param int   $post_id Post.
		 * @param array $package Content package (qcld_wpbot_writer_get_package()).
		 */
		do_action( 'qcld_wpbot_writer/content_published', (int) $post->ID, qcld_wpbot_writer_get_package( (int) $post->ID ) );
	}
}
