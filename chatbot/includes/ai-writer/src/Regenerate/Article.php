<?php
/**
 * Regenerate a whole article (CR-14 R2): keep the outline and rewrite the
 * sections, or write a new outline. It runs as a background job like a new
 * article and arrives as a proposed update (the Improve update copy), so
 * the live post never changes until the owner publishes the update.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Regenerate;

defined( 'ABSPATH' ) || exit;

use QuantumCloud\AiWriter\Writing\Article_Job;
use QuantumCloud\AiWriter\Writing\Brief;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

/**
 * Whole-article regeneration.
 */
final class Article {

	/** Modes. */
	const MODES = array( 'keep_outline', 'new_outline' );

	/**
	 * Clean the brief's regenerate part (empty array when not a regeneration).
	 *
	 * @param mixed $raw Raw.
	 * @return array mode, options, sections.
	 */
	public static function sanitise_brief_part( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['mode'] ) || ! in_array( $raw['mode'], self::MODES, true ) ) {
			return array();
		}
		$sections = array();
		foreach ( isset( $raw['sections'] ) ? array_slice( (array) $raw['sections'], 0, 30 ) : array() as $section ) {
			if ( is_array( $section ) && ! empty( $section['heading'] ) ) {
				$sections[] = array(
					'heading' => substr( sanitize_text_field( (string) $section['heading'] ), 0, 200 ),
					'purpose' => substr( sanitize_text_field( isset( $section['purpose'] ) ? (string) $section['purpose'] : '' ), 0, 300 ),
					'words'   => max( 60, min( 2000, absint( isset( $section['words'] ) ? $section['words'] : 200 ) ) ),
				);
			}
		}
		return array(
			'mode'     => $raw['mode'],
			'options'  => Options::sanitise( isset( $raw['options'] ) ? $raw['options'] : array() ),
			'sections' => $sections,
		);
	}

	/**
	 * The brief that rewrites a post.
	 *
	 * @param int    $post_id Post.
	 * @param string $mode    keep_outline|new_outline.
	 * @param array  $options Clean options.
	 * @return array|WP_Error Brief.
	 */
	public static function brief( $post_id, $mode, array $options ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'qcld_wpbot_writer_not_found', __( 'That post was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $mode, self::MODES, true ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_mode', __( 'Choose whether to keep the outline or write a new one.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$job_id   = (int) get_post_meta( $post_id, '_qcld_wpbot_writer_job_id', true );
		$job      = $job_id ? Job_Store::get( $job_id ) : null;
		$raw      = $job && ! empty( $job['payload']['brief'] ) ? (array) $job['payload']['brief'] : array(
			'type'      => 'page' === $post->post_type ? 'page_copy' : ( 'product' === $post->post_type ? 'product' : 'blog_post' ),
			'post_type' => 'page' === $post->post_type ? 'page' : 'post',
		);
		$sections = Sections::split( $post->post_content );
		if ( 'keep_outline' === $mode && array() === $sections ) {
			return new WP_Error( 'qcld_wpbot_writer_no_sections', __( 'This page has no sections (level-2 headings) to keep. Choose "Write a new outline".', 'chatbot' ), array( 'status' => 400 ) );
		}
		$raw['topic']        = '' !== trim( (string) ( isset( $raw['topic'] ) ? $raw['topic'] : '' ) ) ? $raw['topic'] : wp_strip_all_tags( $post->post_title );
		$raw['target_post']  = (int) $post_id;
		$raw['auto_outline'] = true;
		$raw['date']         = '';
		$raw['regenerate']   = array(
			'mode'     => $mode,
			'options'  => $options,
			'sections' => array_map(
				static function ( $section ) {
					return array(
						'heading' => $section['heading'],
						'purpose' => '',
						'words'   => max( 60, $section['words'] ),
					);
				},
				$sections
			),
		);
		if ( 'keep_outline' === $mode && empty( $raw['words'] ) ) {
			$raw['words'] = max( 300, array_sum( wp_list_pluck( $sections, 'words' ) ) );
		}
		return Brief::sanitise( $raw );
	}

	/**
	 * Cost estimate (always shown before a whole-article run).
	 *
	 * @param int    $post_id Post.
	 * @param string $mode    Mode.
	 * @return array|WP_Error
	 */
	public static function estimate( $post_id, $mode ) {
		$brief = self::brief( $post_id, $mode, array() );
		return is_wp_error( $brief ) ? $brief : Article_Job::estimate( $brief );
	}

	/**
	 * Start the job.
	 *
	 * @param int    $post_id Post.
	 * @param string $mode    Mode.
	 * @param array  $options Clean options.
	 * @return int|WP_Error Job id.
	 */
	public static function start( $post_id, $mode, array $options ) {
		$brief = self::brief( $post_id, $mode, $options );
		return is_wp_error( $brief ) ? $brief : Article_Job::start( $brief );
	}
}
