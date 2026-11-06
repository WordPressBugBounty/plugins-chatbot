<?php
/**
 * CR-16 F4: statistics roundups and listicles go out of date. Improve
 * lists published ones that have not been updated for a year, with a fix
 * that asks for current figures with their source and year.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Formats;

defined( 'ABSPATH' ) || exit;

/**
 * Stale figures in Improve.
 */
final class Stale_Figures {

	/**
	 * Formats whose figures date.
	 */
	const FORMATS = array( 'stats_roundup', 'listicle' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_writer/improve_tasks', array( __CLASS__, 'tasks' ) );
	}

	/**
	 * Days after which figures count as old.
	 *
	 * @return int
	 */
	public static function days() {
		/**
		 * Days after which a statistics roundup or listicle is flagged in Improve.
		 *
		 * @param int $days Days (default 365).
		 */
		return max( 30, (int) apply_filters( 'qcld_wpbot_writer/stale_figures_days', 365 ) );
	}

	/**
	 * Add the stale pieces to Improve's tasks.
	 *
	 * @param array[] $tasks Tasks.
	 * @return array[]
	 */
	public static function tasks( $tasks ) {
		$posts = get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => 'publish',
				'posts_per_page'   => 20,
				'orderby'          => 'modified',
				'order'            => 'ASC',
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small, indexed meta key; 20 posts at most.
					array(
						'key'     => Formats::META,
						'value'   => self::FORMATS,
						'compare' => 'IN',
					),
				),
				'date_query'       => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - self::days() * DAY_IN_SECONDS ),
					),
				),
				'suppress_filters' => false,
			)
		);
		foreach ( $posts as $post ) {
			$format  = Formats::get( (string) get_post_meta( $post->ID, Formats::META, true ) );
			$tasks[] = array(
				'post_id'       => $post->ID,
				'source_module' => 'ai_writer_formats',
				'reason'        => sprintf(
					/* translators: 1: format, 2: date. */
					__( 'Figures may be out of date: this %1$s was last updated on %2$s.', 'chatbot' ),
					null !== $format ? strtolower( $format['label'] ) : __( 'article', 'chatbot' ),
					mysql2date( get_option( 'date_format' ), $post->post_modified )
				),
				'fix'           => __( 'Check every figure, price and claim. Replace anything older than a year with a current figure, keeping its source and year; write an [add: …] note where no current figure is known. Keep the structure.', 'chatbot' ),
			);
		}
		return $tasks;
	}
}
