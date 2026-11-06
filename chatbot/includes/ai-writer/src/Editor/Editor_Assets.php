<?php
/**
 * Loads AI Writer on edit screens: the block editor sidebar, toolbar and
 * /ai block, or the Classic Editor box. Assets load only on post edit
 * screens of public post types, for users with the AI Writer Use level.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Editor;

use QuantumCloud\AiWriter\Images\Image_Search;
use QuantumCloud\AiWriter\Improve\Updates;
use QuantumCloud\AiWriter\Images\Unsplash;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Seo\Seo_Adapter;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\AiWriter\Voice\Voices;
use QuantumCloud\WPBotCore\AI\Key_Health;

defined( 'ABSPATH' ) || exit;

/**
 * Edit-screen integration.
 */
final class Editor_Assets {

	const BLOCK_HANDLE   = 'qcld-wpbot-writer-editor';
	const CLASSIC_HANDLE = 'qcld-wpbot-writer-classic';
	const AI_BLOCK       = 'qcld-wpbot-writer/ai';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_editor' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'classic_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'classic_editor' ) );
	}

	/**
	 * The /ai block only exists in the editor: it is replaced by what it
	 * writes, and renders nothing if it is ever saved by mistake.
	 *
	 * @return void
	 */
	public static function register_block() {
		register_block_type(
			self::AI_BLOCK,
			array(
				'api_version'     => 3,
				'render_callback' => '__return_empty_string',
				'attributes'      => array(
					'prompt' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Post type of the screen being edited, if AI Writer applies to it.
	 *
	 * @return string '' when not applicable.
	 */
	private static function screen_post_type() {
		if ( ! function_exists( 'get_current_screen' ) || ! current_user_can( Module::cap( 'use' ) ) ) {
			return '';
		}
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || empty( $screen->post_type ) ) {
			return '';
		}
		$object = get_post_type_object( $screen->post_type );
		if ( ! $object || ! $object->public || 'attachment' === $screen->post_type ) {
			return '';
		}

		/**
		 * Post types where AI Writer appears on the edit screen.
		 *
		 * @param bool   $enabled   Default: every public post type.
		 * @param string $post_type Post type.
		 */
		return apply_filters( 'qcld_wpbot_writer/editor_enabled', true, $screen->post_type ) ? (string) $screen->post_type : '';
	}

	/**
	 * Block editor assets.
	 *
	 * @return void
	 */
	public static function block_editor() {
		$post_type = self::screen_post_type();
		if ( '' === $post_type ) {
			return;
		}
		$asset = self::asset( 'editor' );
		wp_enqueue_script( self::BLOCK_HANDLE, Module::url( 'assets/build/editor.js' ), $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::BLOCK_HANDLE, 'chatbot' );
		wp_enqueue_style( self::BLOCK_HANDLE, Module::url( 'assets/build/style-editor.css' ), array( 'wp-components' ), $asset['version'] );
		\QuantumCloud\AiWriter\Admin\Ai_Readiness::enqueue_style();
		wp_style_add_data( self::BLOCK_HANDLE, 'rtl', 'replace' );
		wp_add_inline_script( self::BLOCK_HANDLE, 'window.qcldWpbotWriter = ' . wp_json_encode( self::app_data( $post_type ) ) . ';', 'before' );
	}

	/**
	 * Classic Editor: the AI Writer box.
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_Post $post      Post.
	 * @return void
	 */
	public static function classic_box( $post_type, $post = null ) {
		if ( ! $post instanceof \WP_Post || use_block_editor_for_post( $post ) || '' === self::screen_post_type() ) {
			return;
		}
		add_meta_box(
			'qcwr-classic',
			__( 'AI Writer', 'chatbot' ),
			array( __CLASS__, 'render_classic_box' ),
			$post_type,
			'side',
			'high'
		);
	}

	/**
	 * Box shell; the script renders into it.
	 *
	 * @return void
	 */
	public static function render_classic_box() {
		$post = get_post();
		echo '<div id="qcwr-classic-root"><p class="description">' . esc_html__( 'Loading AI Writer…', 'chatbot' ) . '</p></div>';
		// Search title and description chosen in the box are written to the SEO plugin when the post is saved.
		echo '<input type="hidden" name="qcwr_seo_pending" id="qcwr-seo-pending" value="" />';
		if ( $post ) {
			wp_nonce_field( 'qcwr_seo_' . $post->ID, 'qcwr_seo_nonce' );
		}
	}

	/**
	 * Classic Editor assets.
	 *
	 * @param string $hook_suffix Screen.
	 * @return void
	 */
	public static function classic_editor( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post || use_block_editor_for_post( $post ) ) {
			return;
		}
		$post_type = self::screen_post_type();
		if ( '' === $post_type ) {
			return;
		}
		$asset = self::asset( 'classic' );
		wp_enqueue_script( self::CLASSIC_HANDLE, Module::url( 'assets/build/classic.js' ), $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::CLASSIC_HANDLE, 'chatbot' );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( self::CLASSIC_HANDLE, Module::url( 'assets/build/style-classic.css' ), array( 'wp-components' ), $asset['version'] );
		\QuantumCloud\AiWriter\Admin\Ai_Readiness::enqueue_style();
		wp_style_add_data( self::CLASSIC_HANDLE, 'rtl', 'replace' );
		wp_add_inline_script( self::CLASSIC_HANDLE, 'window.qcldWpbotWriter = ' . wp_json_encode( self::app_data( $post_type ) ) . ';', 'before' );
	}

	/**
	 * Build asset file data.
	 *
	 * @param string $entry Entry name.
	 * @return array
	 */
	private static function asset( $entry ) {
		$file = QCLD_WPBOT_WRITER_DIR . '/assets/build/' . $entry . '.asset.php';
		return is_readable( $file ) ? require $file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => QCLD_WPBOT_WRITER_VERSION,
		);
	}

	/**
	 * Data for the edit-screen scripts. Never contains keys.
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	private static function app_data( $post_type ) {
		$post    = get_post();
		$post_id = $post ? (int) $post->ID : 0;
		$host    = Module::instance()->host();
		$brand   = $host ? (array) $host->get_brand() : array();
		$pending = $post_id ? (int) get_post_meta( $post_id, '_qcld_wpbot_writer_pending_job', true ) : 0;
		$voice   = Voices::site_voice();

		$data = array(
			'version'     => QCLD_WPBOT_WRITER_VERSION,
			'module'      => Module::ID,
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'postId'      => $post_id,
			// Pages that should link to this article (CR-6 W1); shown once it is published.
			'linkFrom'    => $post_id ? \QuantumCloud\AiWriter\Writing\Link_From::suggestions( $post_id ) : array(),
			// CR-10: open the AI Writer sidebar the first time a draft it wrote is opened.
			'openSidebar' => self::first_open( $post_id ),
			'postType'    => $post_type,
			// Round 3: technique labels and this piece's persuasion choices.
			'persuasion'  => \QuantumCloud\AiWriter\Persuasion\Choices::app_data(),
			'regenerate'  => \QuantumCloud\AiWriter\Regenerate\Regenerate::app_data(),
			'choices'     => $post_id ? get_post_meta( $post_id, \QuantumCloud\AiWriter\Persuasion\Choices::META, true ) : '',
			'can'         => array(
				'use'      => current_user_can( Module::cap( 'use' ) ),
				'publish'  => current_user_can( Module::cap( 'publish' ) ),
				'settings' => current_user_can( Module::cap( 'settings' ) ),
				'upload'   => current_user_can( 'upload_files' ),
			),
			'settings'    => array_intersect_key(
				Settings::all(),
				array_flip( array( 'default_type', 'default_length', 'lengths', 'ask_questions', 'runner_interval', 'show_estimates', 'chatbot_source', 'upload_limit_mb', 'image_max_width', 'reading_level' ) )
			),
			'health'      => Key_Health::notices( Module::ID ),
			'readiness'   => \QuantumCloud\AiWriter\Admin\Ai_Readiness::state(),
			'seo'         => array(
				'plugin' => Seo_Adapter::active(),
				'label'  => Seo_Adapter::active_label(),
			),
			'images'      => array(
				'stock'    => Image_Search::stock_sources(),
				'unsplash' => Unsplash::enabled(),
			),
			'hasVoice'    => (bool) $voice,
			'pendingJob'  => $pending,
			'update'      => self::update_info( $post_id ),
			'brand'       => array(
				'primary' => isset( $brand['primary'] ) ? sanitize_hex_color( (string) $brand['primary'] ) : '#5b4e96',
				'soft'    => isset( $brand['primary_soft'] ) ? sanitize_hex_color( (string) $brand['primary_soft'] ) : '#f3f1fa',
				'text'    => isset( $brand['text'] ) ? sanitize_hex_color( (string) $brand['text'] ) : '#2d2a3a',
				'name'    => get_bloginfo( 'name' ),
			),
			'homeUrl'     => esc_url_raw( home_url( '/' ) ),
			'knowledge'   => array(
				'available' => \QuantumCloud\AiWriter\Knowledge\Chatbot_Knowledge::can_add(),
				'added'     => $post_id ? (string) get_post_meta( $post_id, '_qcld_wpbot_writer_in_knowledge', true ) : '',
				'answer'    => $post_id ? (string) get_post_meta( $post_id, '_qcld_wpbot_writer_answer', true ) : '',
			),
			// CR-16: formats for the brief, and the format planned for this draft.
			'formats'     => \QuantumCloud\AiWriter\Formats\Formats::public_view(),
			'postFormat'  => $post_id ? (string) get_post_meta( $post_id, \QuantumCloud\AiWriter\Formats\Formats::META, true ) : '',
			'writerUrl'   => esc_url_raw( admin_url( 'admin.php?page=wpbot-ai-writer' ) ),
			// Inactive Pro previews (Free only; empty when hidden or in Pro).
			'proPreviews' => \QuantumCloud\AiWriter\Admin\Pro_Previews::items(),
			'adminUrl'    => esc_url_raw( admin_url() ),
		);

		/**
		 * Add data for the edit-screen tools (WooCommerce adds product details).
		 *
		 * @param array         $data Data.
		 * @param \WP_Post|null $post Post being edited.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/editor_data', $data, $post );
	}

	/**
	 * Improve updates: is this post an update copy, or waiting for one?
	 *
	 * @param int $post_id Post.
	 * @return array
	 */
	private static function update_info( $post_id ) {
		$original = $post_id ? Updates::original_of( $post_id ) : 0;
		$copy     = $post_id ? (int) get_post_meta( $post_id, Updates::META_NEW, true ) : 0;
		return array(
			'of'         => $original,
			'ofTitle'    => $original ? get_the_title( $original ) : '',
			'ofUrl'      => $original ? esc_url_raw( (string) get_edit_post_link( $original, 'raw' ) ) : '',
			'canPublish' => $original ? Updates::can_publish( $post_id ) : false,
			'waiting'    => $copy && get_post( $copy ) ? $copy : 0,
			'waitingUrl' => $copy && get_post( $copy ) ? esc_url_raw( (string) get_edit_post_link( $copy, 'raw' ) ) : '',
		);
	}

	/**
	 * Whether this is the first time a draft AI Writer wrote is opened in
	 * the editor (remembered once per post).
	 *
	 * @param int $post_id Post.
	 * @return bool
	 */
	private static function first_open( $post_id ) {
		if ( ! $post_id || ! get_post_meta( $post_id, '_qcld_wpbot_writer_job_id', true ) || get_post_meta( $post_id, '_qcld_wpbot_writer_sidebar_shown', true ) ) {
			return false;
		}
		update_post_meta( $post_id, '_qcld_wpbot_writer_sidebar_shown', 1 );
		return true;
	}
}
