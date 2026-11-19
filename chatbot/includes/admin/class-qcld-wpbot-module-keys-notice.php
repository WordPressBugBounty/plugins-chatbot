<?php
/**
 * AI Settings > Providers: say which other WPBot features keep API keys of their own.
 *
 * AI Writer, SEO & AI Search and Social can use their own copy of the AI keys (WPBot Core's
 * qcld_wpbot_core_keys, per feature). Removing a key on the Providers screen (the chatbot module)
 * does not remove those copies, and nothing on that screen said so. This notice names the features
 * that still hold a saved key and where to remove it.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Qcld_WPBot_Module_Keys_Notice' ) ) {

	/**
	 * Notice on AI Settings > Providers.
	 */
	final class Qcld_WPBot_Module_Keys_Notice {

		/** WPBot Core's per-feature key sets. */
		const OPTION = 'qcld_wpbot_core_keys';

		/**
		 * Hook in.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		}

		/**
		 * Whether this request shows AI Settings > Providers.
		 *
		 * @return bool
		 */
		private static function on_providers() {
			$page = '\QuantumCloud\Chatbot\Admin\Ai_Settings_Page';
			if ( ! class_exists( $page ) || ! defined( $page . '::SLUG' ) ) {
				return false;
			}
			$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Screen check only.
			if ( strtolower( (string) constant( $page . '::SLUG' ) ) !== $slug ) {
				return false;
			}
			return ! method_exists( $page, 'section' ) || 'providers' === $page::section();
		}

		/**
		 * Display names of the features that hold a saved key of their own (in their own set, or in
		 * the copy kept while they follow AI Settings).
		 *
		 * @return string[]
		 */
		public static function features_with_keys() {
			$all = get_option( self::OPTION, array() );
			if ( ! is_array( $all ) ) {
				return array();
			}
			$labels = array(
				'writer' => __( 'AI Writer', 'chatbot' ),
				'seo'    => __( 'SEO & AI Search', 'chatbot' ),
				'social' => __( 'Social', 'chatbot' ),
			);
			$found  = array();
			foreach ( $all as $module => $set ) {
				if ( ! is_array( $set ) ) {
					continue;
				}
				$sets = array( $set );
				if ( isset( $set['own'] ) && is_array( $set['own'] ) ) {
					$sets[] = $set['own'];
				}
				foreach ( $sets as $one ) {
					if ( self::has_key( $one ) ) {
						$module           = sanitize_key( (string) $module );
						$found[ $module ] = isset( $labels[ $module ] ) ? $labels[ $module ] : ucfirst( str_replace( array( '-', '_' ), ' ', $module ) );
						break;
					}
				}
			}
			return array_values( $found );
		}

		/**
		 * Whether a key set holds at least one saved key.
		 *
		 * @param array $set Key set.
		 * @return bool
		 */
		private static function has_key( array $set ) {
			if ( empty( $set['providers'] ) || ! is_array( $set['providers'] ) ) {
				return false;
			}
			foreach ( $set['providers'] as $provider ) {
				if ( is_array( $provider ) && isset( $provider['key'] ) && '' !== trim( (string) $provider['key'] ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Print the notice.
		 *
		 * @return void
		 */
		public static function render() {
			if ( ! current_user_can( 'manage_options' ) || ! self::on_providers() ) {
				return;
			}
			$features = self::features_with_keys();
			if ( array() === $features ) {
				return;
			}
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: features, for example "AI Writer, SEO & AI Search". */
						__( 'These features keep API keys of their own: %s. Removing a key here does not remove theirs. To remove them, open each feature\'s Settings > AI providers.', 'chatbot' ),
						implode( ', ', $features )
					)
				)
			);
		}
	}

	Qcld_WPBot_Module_Keys_Notice::init();
}
