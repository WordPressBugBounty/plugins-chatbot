<?php
/**
 * Auto meta on publish (off by default): when a post is published with an
 * empty search title or description, a background job writes them with the
 * module's AI key. It never overwrites existing meta and skips posts where
 * AI Writer already wrote meta. Each value is logged and can be undone.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Onpage;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\WPBotCore\Jobs\Job_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Auto meta for empty fields.
 */
final class Auto_Meta {

	/**
	 * Hook publishing.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 20, 3 );
	}

	/**
	 * Job step handlers.
	 *
	 * @return array
	 */
	public static function handlers() {
		return array( 'seo_auto_meta' => array( __CLASS__, 'step' ) );
	}

	/**
	 * Queue a job when a post is first published with empty meta.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function on_publish( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! Settings::get( 'auto_meta' ) || ! $post instanceof \WP_Post || ! in_array( $post->post_type, Audit::post_types(), true ) ) {
			return;
		}
		if ( ! self::needed( $post->ID ) ) {
			return;
		}
		Job_Store::create(
			Module::ID,
			'auto_meta',
			array(
				'title'       => __( 'Write search title and description', 'chatbot' ),
				'object_type' => 'post',
				'object_id'   => $post->ID,
			),
			array(
				array(
					'kind'  => 'seo_auto_meta',
					'label' => __( 'Write search title and description', 'chatbot' ),
					'input' => array( 'post_id' => $post->ID ),
				),
			)
		);
	}

	/**
	 * Whether a post needs auto meta (empty field, not written by AI Writer).
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function needed( $post_id ) {
		// AI Writer already wrote this post's meta (or it is an AI Writer piece).
		if ( function_exists( 'qcld_wpbot_writer_get_package' ) && qcld_wpbot_writer_get_package( $post_id ) ) {
			return false;
		}
		if ( '' !== trim( (string) get_post_meta( $post_id, '_qcld_wpbot_writer_meta_title', true ) . (string) get_post_meta( $post_id, '_qcld_wpbot_writer_meta_description', true ) ) ) {
			return false;
		}
		return '' === trim( (string) Seo_Bridge::get( $post_id, 'title' ) ) || '' === trim( (string) Seo_Bridge::get( $post_id, 'description' ) );
	}


	/**
	 * Job step: propose and save into empty fields only.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|\WP_Error
	 */
	public static function step( array $step, array $job ) {
		$post_id = (int) $step['input']['post_id'];
		if ( ! self::needed( $post_id ) ) {
			return array( 'skipped' => true );
		}
		$proposal = Meta_Fixer::propose( $post_id, array( 'job_id' => (int) $job['id'] ) );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}
		$saved = array();
		foreach ( array( 'title', 'description' ) as $field ) {
			if ( '' !== $proposal[ $field ] ) {
				$change = Seo_Bridge::set( $post_id, $field, $proposal[ $field ], 'auto_meta', array( 'only_if_empty' => true ) );
				if ( $change && ! is_wp_error( $change ) ) {
					$saved[ $field ] = $change;
				}
			}
		}
		return array(
			'saved'  => $saved,
			'_usage' => $proposal['usage'],
		);
	}
}
