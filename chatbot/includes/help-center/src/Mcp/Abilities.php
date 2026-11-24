<?php
/**
 * The same Help Center tools as WordPress Abilities (WordPress 6.9+), so the official WordPress
 * MCP Adapter, when installed, offers them too (HC3-A, owner 20 November 2026, adds to H14).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Abilities.
 */
final class Abilities {

	/**
	 * Category and name prefix.
	 */
	const PREFIX = 'wpbot-help-center';

	/**
	 * Hook in (only while the Help Center is on).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register' ) );
	}

	/**
	 * Ability name for a tool: helpcenter_upsert_article => wpbot-help-center/upsert-article.
	 *
	 * @param string $tool Tool name.
	 * @return string
	 */
	public static function name( $tool ) {
		return self::PREFIX . '/' . str_replace( '_', '-', substr( $tool, strlen( 'helpcenter_' ) ) );
	}

	/**
	 * Category.
	 *
	 * @return void
	 */
	public static function category() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				self::PREFIX,
				array(
					'label'       => __( 'Help Center', 'chatbot' ),
					'description' => __( 'Read and build the help center: knowledge bases, categories and articles.', 'chatbot' ),
				)
			);
		}
	}

	/**
	 * Register every tool.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		foreach ( Tools::definitions() as $tool => $def ) {
			wp_register_ability(
				self::name( $tool ),
				array(
					'label'               => ucfirst( str_replace( array( 'helpcenter_', '_' ), array( '', ' ' ), $tool ) ),
					'description'         => $def['description'],
					'category'            => self::PREFIX,
					'input_schema'        => array_merge( $def['input'], array( 'default' => array() ) ),
					'output_schema'       => array( 'type' => 'object' ),
					'execute_callback'    => static function ( $input = array() ) use ( $tool ) {
						return Tools::call( $tool, is_array( $input ) ? $input : array() );
					},
					'permission_callback' => static function () use ( $tool ) {
						return Tools::allowed( $tool );
					},
					'meta'                => array(
						'show_in_rest' => true,
						'annotations'  => array(
							'readonly'    => ! $def['write'],
							'destructive' => false,
							'idempotent'  => true,
						),
						'mcp'          => array( 'public' => true ),
					),
				)
			);
		}//end foreach
	}
}
