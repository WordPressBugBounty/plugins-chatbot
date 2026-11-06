<?php
/**
 * Builds the WordPress draft from the finished steps: native blocks (or
 * clean HTML for the Classic Editor), featured image, excerpt, tags, meta,
 * flags for review, and the content package.
 *
 * Nothing is published: the draft opens in the normal editor for review.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Output\Block_Builder;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use QuantumCloud\WPBotCore\Library\Packages;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Draft assembly.
 */
final class Assembler {

	/**
	 * Section outputs with the stitched joins applied.
	 *
	 * @param int $job_id Job id.
	 * @return array[]
	 */
	public static function stitched_sections( $job_id ) {
		$sections = Article_Steps::section_outputs( $job_id );
		$stitch   = Article_Steps::output_of( $job_id, 'writer_stitch' );
		foreach ( $stitch && isset( $stitch['joins'] ) ? (array) $stitch['joins'] : array() as $join ) {
			$i = (int) $join['join'];
			if ( ! isset( $sections[ $i ], $sections[ $i + 1 ] ) ) {
				continue;
			}
			$last  = Article_Steps::last_paragraph( $sections[ $i ]['blocks'] );
			$first = Article_Steps::first_paragraph( $sections[ $i + 1 ]['blocks'] );
			if ( null !== $last && '' !== $join['last'] ) {
				$sections[ $i ]['blocks'][ $last ]['text'] = $join['last'];
			}
			if ( null !== $first && '' !== $join['first'] ) {
				$sections[ $i + 1 ]['blocks'][ $first ]['text'] = $join['first'];
			}
		}
		/**
		 * Sections after stitching (Pro: deep humanize rewrites).
		 *
		 * @param array[] $sections Sections with blocks.
		 * @param int     $job_id   Article job.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/stitched_sections', $sections, $job_id );
	}

	/**
	 * Step handler: create the draft.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function step( array $step, array $job ) {
		$brief     = $job['payload']['brief'];
		$outline   = $job['payload']['outline'];
		$user_id   = (int) $job['user_id'];
		$post_type = 'page' === $brief['post_type'] ? 'page' : 'post';
		if ( ! empty( $brief['target_post'] ) && get_post_type( (int) $brief['target_post'] ) ) {
			$post_type = (string) get_post_type( (int) $brief['target_post'] );
		}
		$type_obj = get_post_type_object( $post_type );
		if ( ! $type_obj || ! user_can( $user_id, $type_obj->cap->edit_posts ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_create', __( 'You are not allowed to create this kind of content.', 'chatbot' ) );
		}

		$sections = self::stitched_sections( $job['id'] );
		$meta     = (array) Article_Steps::output_of( $job['id'], 'writer_meta' );
		$faq      = Article_Steps::output_of( $job['id'], 'writer_faq' );
		$checks   = (array) Article_Steps::output_of( $job['id'], 'writer_checks' );
		$images   = (array) Article_Steps::output_of( $job['id'], 'writer_images' );
		$sources  = (array) Article_Steps::output_of( $job['id'], 'writer_sources' );

		$items = array();
		if ( ! empty( $outline['summary'] ) && ! empty( $meta['summary'] ) ) {
			$items[] = array(
				'type' => 'paragraph',
				'text' => '<strong>' . esc_html__( 'In short:', 'chatbot' ) . '</strong> ' . esc_html( $meta['summary'] ),
			);
		}
		foreach ( $sections as $index => $section ) {
			$items[] = array(
				'type'  => 'heading',
				'text'  => $section['heading'],
				'level' => 2,
			);
			$placed  = false;
			foreach ( $section['blocks'] as $block ) {
				$items[] = $block;
				// Place the section's image after its first paragraph.
				if ( ! $placed && 'paragraph' === $block['type'] ) {
					$placed = true;
					$image  = isset( $images['sections'][ $index ] ) ? $images['sections'][ $index ] : null;
					if ( $image ) {
						$items[] = $image;
					}
				}
			}
			// CR-16 F3: the approved research (tables, charts, method) after the first section.
			if ( 0 === $index && ! empty( $brief['research'] ) ) {
				$items = array_merge( $items, \QuantumCloud\AiWriter\Formats\Research_Data::blocks( $brief['research'] ) );
			}
		}
		if ( $faq && ! empty( $faq['items'] ) ) {
			$items[] = array(
				'type'  => 'heading',
				'text'  => __( 'Frequently asked questions', 'chatbot' ),
				'level' => 2,
			);
			foreach ( $faq['items'] as $item ) {
				$items[] = array(
					'type'  => 'heading',
					'text'  => $item['q'],
					'level' => 3,
				);
				$items[] = array(
					'type' => 'paragraph',
					'text' => $item['a'],
				);
			}
		}

		$use_blocks = function_exists( 'use_block_editor_for_post_type' ) ? use_block_editor_for_post_type( $post_type ) : true;
		$content    = $use_blocks ? Block_Builder::to_blocks( $items ) : Block_Builder::to_html( $items );
		$title      = ! empty( $meta['title'] ) ? $meta['title'] : $outline['title'];

		if ( ! empty( $brief['target_post'] ) ) {
			return self::into_draft( (int) $brief['target_post'], $job, $content, $title, $meta, $faq, $checks, $images );
		}

		// wp_insert_post() unslashes its input: slash it so backslashes survive.
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $post_type,
					'post_status'  => 'draft',
					'post_author'  => $user_id,
					'post_title'   => wp_strip_all_tags( $title ),
					'post_content' => $content,
					'post_excerpt' => isset( $meta['excerpt'] ) ? $meta['excerpt'] : '',
				) + self::planned_date( $brief )
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( 'post' === $post_type && ! empty( $meta['tags'] ) ) {
			wp_set_post_tags( $post_id, $meta['tags'], false );
		}
		if ( ! empty( $images['featured_id'] ) ) {
			set_post_thumbnail( $post_id, (int) $images['featured_id'] );
		}
		foreach ( isset( $images['attachment_ids'] ) ? (array) $images['attachment_ids'] : array() as $attachment_id ) {
			if ( ! wp_get_post_parent_id( (int) $attachment_id ) ) {
				wp_update_post(
					array(
						'ID'          => (int) $attachment_id,
						'post_parent' => $post_id,
					)
				);
			}
		}

		update_post_meta( $post_id, '_qcld_wpbot_writer_job_id', (int) $job['id'] );
		if ( ! empty( $brief['question'] ) ) {
			update_post_meta( $post_id, '_qcld_wpbot_writer_answer', wp_slash( $brief['question'] ) );
		}
		update_post_meta( $post_id, '_qcld_wpbot_writer_sources', wp_slash( isset( $sources['used'] ) ? $sources['used'] : array() ) );
		update_post_meta(
			$post_id,
			'_qcld_wpbot_writer_flags',
			wp_slash(
				array(
					'claims'       => isset( $checks['claims'] ) ? $checks['claims'] : array(),
					'ai_patterns'  => isset( $checks['ai_patterns'] ) ? $checks['ai_patterns'] : array(),
					'placeholders' => isset( $checks['placeholders'] ) ? (int) $checks['placeholders'] : 0,
					'format_flags' => isset( $checks['format'] ) ? (array) $checks['format'] : array(),
				)
			)
		);
		if ( $faq && ! empty( $faq['items'] ) ) {
			update_post_meta( $post_id, '_qcld_wpbot_writer_faq', wp_slash( $faq['items'] ) );
		}

		/**
		 * Write meta to the active SEO plugin, or AI Writer's own meta when
		 * none is active (Seo\Seo_Adapter hooks here).
		 *
		 * @param int   $post_id Post id.
		 * @param array $meta    meta_title, meta_description.
		 */
		do_action( 'qcld_wpbot_writer/meta_ready', $post_id, $meta );

		Packages::save(
			$post_id,
			Module::ID,
			array(
				'status'     => 'draft',
				'title'      => $title,
				'summary'    => isset( $meta['summary'] ) ? $meta['summary'] : '',
				'key_points' => isset( $meta['key_points'] ) ? $meta['key_points'] : array(),
				'audience'   => $brief['audience'],
				'voice'      => array(
					'id'   => 0,
					'name' => __( 'Site voice', 'chatbot' ),
				),
				'facts_used' => Brief::facts( $brief ),
				'images'     => isset( $images['package'] ) ? $images['package'] : array(),
			)
		);

		/**
		 * A piece is finished: its draft and content package are saved.
		 * The shared keyword and topic map records its main search and
		 * topic here (Topics\Map_Sync).
		 *
		 * @param int        $post_id Post id.
		 * @param array      $brief   Sanitised brief (main_query, map_topic, keywords, idea_id…).
		 * @param array|null $package Content package (qcld_wpbot_writer_get_package()).
		 */
		do_action( 'qcld_wpbot_writer/content_finalized', $post_id, $brief, qcld_wpbot_writer_get_package( (int) $post_id ) );

		$words = Prompts::word_count( wp_strip_all_tags( $content ) );
		$edit  = get_edit_post_link( $post_id, 'raw' );
		Job_Store::merge_payload(
			$job['id'],
			array(
				'result' => array(
					'post_id'      => (int) $post_id,
					'edit_url'     => $edit ? $edit : admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ),
					'answer'       => ! empty( $brief['question'] ),
					'words'        => $words,
					'target'       => Brief::target_words( $brief ),
					'claims'       => isset( $checks['claims'] ) ? count( $checks['claims'] ) : 0,
					'ai_patterns'  => isset( $checks['ai_patterns'] ) ? count( $checks['ai_patterns'] ) : 0,
					'placeholders' => isset( $checks['placeholders'] ) ? (int) $checks['placeholders'] : 0,
					'format_flags' => isset( $checks['format'] ) ? (array) $checks['format'] : array(),
					'images'       => isset( $images['summary'] ) ? $images['summary'] : array(),
				),
			)
		);
		return array(
			'post_id' => (int) $post_id,
			'words'   => $words,
		);
	}

	/**
	 * "Write with AI" from the edit screen: the content goes back to the
	 * editor, which inserts it into the open draft. Nothing is saved to the
	 * post here; the owner saves it.
	 *
	 * @param int        $post_id Target post.
	 * @param array      $job     Job.
	 * @param string     $content Block markup.
	 * @param string     $title   Title.
	 * @param array      $meta    Meta step output.
	 * @param array|null $faq     FAQ step output.
	 * @param array      $checks  Checks step output.
	 * @param array      $images  Images step output.
	 * @return array|WP_Error
	 */
	private static function into_draft( $post_id, array $job, $content, $title, array $meta, $faq, array $checks, array $images ) {
		if ( ! user_can( (int) $job['user_id'], 'edit_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_edit', __( 'You are not allowed to edit this content.', 'chatbot' ) );
		}
		$words = Prompts::word_count( wp_strip_all_tags( $content ) );
		// CR-14: a regenerated article arrives as a proposed update; a live post does not change.
		// An unpublished draft is rewritten in place, with its earlier text kept as a revision.
		$update_id = 0;
		$in_place  = false;
		if ( ! empty( $job['payload']['brief']['regenerate'] ) ) {
			$target = get_post( $post_id );
			if ( $target && in_array( $target->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
				wp_save_post_revision( $post_id );
				$in_place = ! is_wp_error(
					wp_update_post(
						array(
							'ID'           => (int) $post_id,
							'post_content' => $content,
						),
						true
					)
				);
				if ( $in_place && ( ! empty( $meta['meta_title'] ) || ! empty( $meta['meta_description'] ) ) ) {
					\QuantumCloud\AiWriter\Seo\Seo_Adapter::write( (int) $post_id, isset( $meta['meta_title'] ) ? $meta['meta_title'] : '', isset( $meta['meta_description'] ) ? $meta['meta_description'] : '' );
				}
			} else {
				$seo       = ! empty( $meta['meta_title'] ) || ! empty( $meta['meta_description'] ) ? array(
					'title'       => isset( $meta['meta_title'] ) ? $meta['meta_title'] : '',
					'description' => isset( $meta['meta_description'] ) ? $meta['meta_description'] : '',
				) : null;
				$update_id = \QuantumCloud\AiWriter\Improve\Updates::create( $target, $content, $seo );
				$update_id = is_wp_error( $update_id ) ? 0 : (int) $update_id;
			}
		}
		Job_Store::merge_payload(
			$job['id'],
			array(
				'result' => array(
					'post_id'          => $post_id,
					'into_draft'       => true,
					'edit_url'         => (string) get_edit_post_link( $post_id, 'raw' ),
					'content'          => $content,
					'title'            => wp_strip_all_tags( $title ),
					'excerpt'          => isset( $meta['excerpt'] ) ? $meta['excerpt'] : '',
					'meta_title'       => isset( $meta['meta_title'] ) ? $meta['meta_title'] : '',
					'meta_description' => isset( $meta['meta_description'] ) ? $meta['meta_description'] : '',
					'featured_id'      => ! empty( $images['featured_id'] ) ? (int) $images['featured_id'] : 0,
					'faq'              => $faq && ! empty( $faq['items'] ) ? $faq['items'] : array(),
					'words'            => $words,
					'target'           => Brief::target_words( $job['payload']['brief'] ),
					'claims'           => isset( $checks['claims'] ) ? count( $checks['claims'] ) : 0,
					'ai_patterns'      => isset( $checks['ai_patterns'] ) ? count( $checks['ai_patterns'] ) : 0,
					'placeholders'     => isset( $checks['placeholders'] ) ? (int) $checks['placeholders'] : 0,
					'format_flags'     => isset( $checks['format'] ) ? (array) $checks['format'] : array(),
					'images'           => isset( $images['summary'] ) ? $images['summary'] : array(),
					'update_id'        => $update_id,
					'update_url'       => $update_id ? (string) get_edit_post_link( $update_id, 'raw' ) : '',
					'in_place'         => $in_place,
				),
			)
		);
		return array(
			'post_id' => $post_id,
			'words'   => $words,
		);
	}

	/**
	 * Date fields for a draft planned on a calendar day (none otherwise).
	 *
	 * @param array $brief Brief.
	 * @return array
	 */
	private static function planned_date( array $brief ) {
		if ( empty( $brief['date'] ) ) {
			return array();
		}
		$local = $brief['date'] . ' 09:00:00';
		return array(
			'post_date'     => $local,
			'post_date_gmt' => get_gmt_from_date( $local ),
			'edit_date'     => true,
		);
	}
}
