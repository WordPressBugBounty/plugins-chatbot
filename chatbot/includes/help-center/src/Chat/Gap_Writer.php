<?php
/**
 * Write article from a knowledge gap (HC1-11, plan section 7).
 *
 * Train → Knowledge gaps → Write article asks qcld_wpbot_chat/gap_write_article
 * for a URL. The Help Center answers for people who may write articles:
 * - when the question is an article's title (a "not helpful" vote without
 *   words), that article opens for editing;
 * - otherwise a help article draft is made, titled with the question, in the
 *   category and knowledge base of the closest existing article, and opens in
 *   the editor. The editor shows what visitors asked, how often, and a link to
 *   AI Writer with the question as the topic. Writing straight into the draft
 *   comes with qcld/wpbot-ai-writer#67.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Chat;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Gap → help article.
 */
final class Gap_Writer {

	/**
	 * Meta with the gap a draft came from.
	 */
	const META = '_wpbot_gap';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/gap_write_article', array( self::class, 'write' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_notice' ) );
		add_action( 'admin_notices', array( self::class, 'classic_notice' ) );
	}

	/**
	 * Open an article for a gap.
	 *
	 * @param string $url URL from an earlier handler.
	 * @param array  $gap Gap row (id, question, reason, hits, language).
	 * @return string
	 */
	public static function write( $url, $gap ) {
		$type = get_post_type_object( Post_Type::NAME );
		if ( '' !== (string) $url || ! is_array( $gap ) || ! $type || ! current_user_can( $type->cap->edit_posts ) ) {
			return (string) $url;
		}
		$question = trim( wp_strip_all_tags( (string) ( isset( $gap['question'] ) ? $gap['question'] : '' ) ) );
		if ( '' === $question ) {
			return (string) $url;
		}
		$existing = self::article_titled( $question );
		if ( $existing && current_user_can( 'edit_post', $existing->ID ) ) {
			return (string) get_edit_post_link( $existing->ID, 'raw' );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => Post_Type::NAME,
				'post_status'  => 'draft',
				'post_title'   => $question,
				'post_content' => '',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return (string) $url;
		}
		$near = self::closest( $question );
		if ( $near ) {
			foreach ( array( Taxonomies::CATEGORY, Taxonomies::KB ) as $taxonomy ) {
				$terms = wp_get_object_terms( $near, $taxonomy, array( 'fields' => 'ids' ) );
				if ( is_array( $terms ) && $terms ) {
					wp_set_object_terms( $id, array_map( 'intval', $terms ), $taxonomy );
				}
			}
		}
		update_post_meta(
			$id,
			self::META,
			array(
				'id'       => isset( $gap['id'] ) ? (int) $gap['id'] : 0,
				'question' => $question,
				'hits'     => isset( $gap['hits'] ) ? (int) $gap['hits'] : 1,
				'reason'   => isset( $gap['reason'] ) ? sanitize_key( (string) $gap['reason'] ) : '',
			)
		);
		return (string) get_edit_post_link( $id, 'raw' );
	}

	/**
	 * The text of the editor notice for a draft made from a gap, and AI Writer's link.
	 *
	 * @param int $post_id Draft id.
	 * @return array{text:string,url:string}|null
	 */
	public static function notice( $post_id ) {
		$gap = get_post_meta( (int) $post_id, self::META, true );
		if ( ! is_array( $gap ) || empty( $gap['question'] ) || 'draft' !== get_post_status( (int) $post_id ) ) {
			return null;
		}
		$hits = max( 1, (int) $gap['hits'] );
		/* translators: 1: the visitors' question, 2: how many times. */
		$text = sprintf( _n( 'Visitors asked “%1$s” (%2$d time) and found no answer. This draft answers it.', 'Visitors asked “%1$s” (%2$d times) and found no answer. This draft answers it.', $hits, 'chatbot' ), (string) $gap['question'], $hits );
		$url  = '';
		if ( function_exists( 'qcld_wpbot_writer_open_brief_url' ) ) {
			$url = (string) qcld_wpbot_writer_open_brief_url(
				array(
					'mode'          => 'new',
					'topic'         => (string) $gap['question'],
					'source_module' => 'help-center',
					'questions'     => array( (string) $gap['question'] ),
				)
			);
		}
		return array(
			'text' => $text,
			'url'  => $url,
		);
	}

	/**
	 * Notice in the block editor.
	 *
	 * @return void
	 */
	public static function editor_notice() {
		$post = get_post();
		if ( ! $post || Post_Type::NAME !== $post->post_type ) {
			return;
		}
		$notice = self::notice( $post->ID );
		if ( null === $notice ) {
			return;
		}
		$actions = '' !== $notice['url'] ? array(
			array(
				'label' => __( 'Draft it with AI Writer', 'chatbot' ),
				'url'   => $notice['url'],
			),
		) : array();
		wp_add_inline_script(
			'wp-notices',
			'window.addEventListener("load",function(){if(window.wp&&wp.data){wp.data.dispatch("core/notices").createNotice("info",' . wp_json_encode( $notice['text'] ) . ',{id:"wpbot-help-gap",isDismissible:true,actions:' . wp_json_encode( $actions ) . '});}});'
		);
	}

	/**
	 * Notice in the classic editor.
	 *
	 * @return void
	 */
	public static function classic_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || Post_Type::NAME !== $screen->post_type || $screen->is_block_editor() ) {
			return;
		}
		$notice = self::notice( get_the_ID() );
		if ( null === $notice ) {
			return;
		}
		echo '<div class="notice notice-info"><p>' . esc_html( $notice['text'] );
		if ( '' !== $notice['url'] ) {
			echo ' <a href="' . esc_url( $notice['url'] ) . '">' . esc_html__( 'Draft it with AI Writer', 'chatbot' ) . '</a>';
		}
		echo '</p></div>';
	}

	/**
	 * A published article whose title is the question (any case).
	 *
	 * @param string $question Question.
	 * @return \WP_Post|null
	 */
	private static function article_titled( $question ) {
		$plain = rtrim( $question, '?' );
		foreach ( array_unique( array( $question, $plain ) ) as $title ) {
			$found = get_posts(
				array(
					'post_type'      => Post_Type::NAME,
					'post_status'    => 'publish',
					'title'          => $title,
					'posts_per_page' => 1,
				)
			);
			if ( $found ) {
				return $found[0];
			}
		}
		return null;
	}

	/**
	 * The published article closest to a question: from the knowledge base when WPBot Core has it, else a title search.
	 *
	 * @param string $question Question.
	 * @return int Article id or 0.
	 */
	private static function closest( $question ) {
		if ( function_exists( 'qcld_wpbot_core_kb_search' ) ) {
			$hits = (array) qcld_wpbot_core_kb_search(
				$question,
				array(
					'top_k'  => 3,
					'types'  => array( \QuantumCloud\HelpCenter\Knowledge::SOURCE ),
					'labels' => array( 'public', 'logged_in' ),
				)
			);
			foreach ( $hits as $hit ) {
				$id = isset( $hit['source_id'] ) ? (int) $hit['source_id'] : 0;
				if ( $id && Post_Type::NAME === get_post_type( $id ) ) {
					return $id;
				}
			}
		}
		$found = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				's'              => $question,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return $found ? (int) $found[0] : 0;
	}
}
