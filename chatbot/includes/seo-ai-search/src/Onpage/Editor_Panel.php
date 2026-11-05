<?php
/**
 * SEO score and top issues on the edit screen: a block editor panel and
 * pre-publish advice (never blocks publishing), or a Classic Editor box.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Onpage;

use QuantumCloud\SeoAiSearch\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Edit-screen panel.
 */
final class Editor_Panel {

	const HANDLE = 'qcld-wpbot-seo-editor';

	/**
	 * Hook the editor assets and the Classic box.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'classic_box' ), 10, 2 );
	}

	/**
	 * Whether the current user and screen get the panel.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private static function wanted( $post_type ) {
		return current_user_can( Module::cap( 'view' ) ) && in_array( $post_type, Audit::post_types(), true );
	}

	/**
	 * Block editor script.
	 *
	 * @return void
	 */
	public static function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::wanted( (string) $screen->post_type ) ) {
			return;
		}
		$asset_file = QCLD_WPBOT_SEO_DIR . '/assets/build/editor.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => QCLD_WPBOT_SEO_VERSION,
		);
		wp_enqueue_script( self::HANDLE, Module::url( 'assets/build/editor.js' ), $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'chatbot' );
		wp_add_inline_script(
			self::HANDLE,
			'window.qcldWpbotSeoEditor = ' . wp_json_encode(
				array(
					'fixesUrl' => admin_url( 'admin.php?page=wpbot-seo-ai-search&tab=fixes' ),
					'canUse'   => current_user_can( Module::cap( 'use' ) ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Classic Editor box (block editor pages use the panel instead).
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_Post $post      Post.
	 * @return void
	 */
	public static function classic_box( $post_type, $post ) {
		if ( ! self::wanted( (string) $post_type ) || ( function_exists( 'use_block_editor_for_post' ) && $post instanceof \WP_Post && use_block_editor_for_post( $post ) ) ) {
			return;
		}
		add_meta_box( 'qcld-wpbot-seo-box', __( 'SEO & AI Search', 'chatbot' ), array( __CLASS__, 'render_box' ), $post_type, 'side', 'default' );
	}

	/**
	 * Classic box content.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render_box( $post ) {
		$audit = Audit::stored( $post->ID );
		if ( ! $audit ) {
			echo '<p>' . esc_html__( 'Publish or update the page to see its SEO checks.', 'chatbot' ) . '</p>';
			return;
		}
		/* translators: %d: score out of 100. */
		echo '<p><strong>' . esc_html( sprintf( __( 'SEO score: %d/100', 'chatbot' ), (int) $audit['score'] ) ) . '</strong></p><ul>';
		foreach ( array_slice( (array) $audit['issues'], 0, 4 ) as $issue ) {
			echo '<li>' . esc_html( $issue['message'] ) . '</li>';
		}
		echo '</ul><p><a href="' . esc_url( admin_url( 'admin.php?page=wpbot-seo-ai-search&tab=fixes&post=' . (int) $post->ID ) ) . '">' . esc_html__( 'Fix in SEO & AI Search', 'chatbot' ) . '</a></p>';
	}
}
