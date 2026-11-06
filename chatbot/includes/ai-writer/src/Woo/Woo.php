<?php
/**
 * WooCommerce writing: loads only when WooCommerce is active, so no
 * WooCommerce code, route or asset runs without it.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Woo;

use QuantumCloud\AiWriter\Module;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce bootstrap.
 */
final class Woo {

	const TERM_HANDLE = 'qcld-wpbot-writer-term';

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Hook in when WooCommerce is active.
	 *
	 * @return void
	 */
	public static function hook() {
		if ( ! self::active() ) {
			return;
		}
		add_filter( 'qcld_wpbot_writer/content_types', array( __CLASS__, 'content_types' ) );
		add_filter( 'qcld_wpbot_writer/source_text', array( __CLASS__, 'source_text' ), 10, 2 );
		add_filter( 'qcld_wpbot_writer/job_steps', array( __CLASS__, 'job_steps' ) );
		add_filter( 'qcld_wpbot_writer/editor_data', array( __CLASS__, 'editor_data' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		if ( is_admin() ) {
			Bulk::hook();
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'term_assets' ) );
		}
	}

	/**
	 * Buying guides and comparisons built from chosen products.
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public static function content_types( $types ) {
		$rule                  = 'Link every product you mention to its URL from the sources with <a href>. Use only the facts given for each product (prices, attributes, variations); never invent features, ratings or claims.';
		$types['buying_guide'] = array(
			'label'        => __( 'Buying guide (from your products)', 'chatbot' ),
			'prompt'       => 'buying guide',
			'instructions' => 'Help the reader choose between the products in the sources: what to look for, then which product suits whom. ' . $rule,
			'products'     => 2,
		);
		$types['comparison']   = array(
			'label'        => __( 'Product comparison', 'chatbot' ),
			'prompt'       => 'product comparison',
			'instructions' => 'Compare the products in the sources side by side on what matters to the buyer, then say who each one suits best. ' . $rule,
			'products'     => 2,
		);
		return $types;
	}

	/**
	 * Product facts for product sources.
	 *
	 * @param string   $text Source text.
	 * @param \WP_Post $post Source post.
	 * @return string
	 */
	public static function source_text( $text, $post ) {
		if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
			return $text;
		}
		$facts = Product_Facts::get( $post->ID );
		return $facts ? Product_Facts::text( $facts ) : $text;
	}

	/**
	 * Step handler for bulk descriptions.
	 *
	 * @param array $handlers Handlers.
	 * @return array
	 */
	public static function job_steps( $handlers ) {
		$handlers['woo_product'] = array( Bulk::class, 'step' );
		return $handlers;
	}

	/**
	 * Product details for the edit-screen tools.
	 *
	 * @param array    $data Data.
	 * @param \WP_Post $post Post being edited.
	 * @return array
	 */
	public static function editor_data( $data, $post ) {
		if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
			return $data;
		}
		$product     = wc_get_product( $post->ID );
		$data['woo'] = array(
			'isProduct' => true,
			'variable'  => $product ? $product->is_type( 'variable' ) && array() !== $product->get_children() : false,
		);
		return $data;
	}

	/**
	 * REST routes.
	 *
	 * @return void
	 */
	public static function routes() {
		( new \QuantumCloud\AiWriter\Rest\Woo_Controller() )->register_routes();
	}

	/**
	 * "Write description with AI" on product category and tag screens.
	 *
	 * @param string $hook_suffix Screen.
	 * @return void
	 */
	public static function term_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'term.php', 'edit-tags.php' ), true ) || ! current_user_can( Module::cap( 'use' ) ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
			return;
		}
		$file  = QCLD_WPBOT_WRITER_DIR . '/assets/build/term.asset.php';
		$asset = is_readable( $file ) ? require $file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => QCLD_WPBOT_WRITER_VERSION,
		);
		wp_enqueue_script( self::TERM_HANDLE, Module::url( 'assets/build/term.js' ), $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::TERM_HANDLE, 'chatbot' );
		wp_enqueue_style( 'wp-components' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: which term is being edited; the route checks rights.
		$term_id = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0;
		wp_add_inline_script(
			self::TERM_HANDLE,
			'window.qcldWpbotWriter = ' . wp_json_encode(
				array(
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'taxonomy' => $screen->taxonomy,
					'termId'   => $term_id,
					'settings' => array(),
				)
			) . ';',
			'before'
		);
	}
}
