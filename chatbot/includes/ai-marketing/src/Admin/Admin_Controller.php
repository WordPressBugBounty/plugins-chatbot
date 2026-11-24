<?php
/**
 * The AI Marketing admin page: WPBot Core's Shell (host header, left menu)
 * with the React app (assets/build/admin.js) in the content area.
 *
 * Every menu item has its own URL (?page=wpbot-marketing&view=…); the app
 * takes over clicks through the Shell's "wpbot-shell:navigate" event.
 * Master items show as inactive previews outside Master mode.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Admin;

use QuantumCloud\AiMarketing\Module;
use QuantumCloud\WPBotCore\Admin\Shell;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page.
 */
final class Admin_Controller {

	const SLUG = 'wpbot-marketing';

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Add the page under the host's menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$host = Module::instance()->host();
		if ( ! $host ) {
			return;
		}
		self::$hook = (string) add_submenu_page(
			(string) $host->get_menu_parent(),
			(string) $host->get_menu_title(),
			(string) $host->get_menu_title(),
			'wpbot_marketing_view',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Load the Shell and the app on this page only.
	 *
	 * @param string $hook Current page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}
		Shell::enqueue();
		$host = Module::instance()->host();
		foreach ( $host ? (array) $host->get_admin_styles() : array() as $style ) {
			$style = self::style( $style );
			if ( $style ) {
				wp_enqueue_style( $style[0], $style[1], array(), $style[2] );
			}
		}

		$asset_file = QCLD_WPBOT_MARKETING_DIR . '/assets/build/admin.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-api-fetch', 'wp-components', 'wp-i18n' ),
			'version'      => QCLD_WPBOT_MARKETING_VERSION,
		);
		$base       = plugins_url( 'assets/build/', QCLD_WPBOT_MARKETING_FILE );

		/**
		 * Fires before the app's script is added: the Pro module enqueues its screens here.
		 */
		do_action( 'qcld_wpbot_marketing/admin_enqueue' );

		/**
		 * Script handles the app waits for (the Pro module adds its own, so its screens are registered first).
		 *
		 * @param string[] $dependencies Handles.
		 */
		$deps = (array) apply_filters( 'qcld_wpbot_marketing/admin_dependencies', (array) $asset['dependencies'] );
		wp_enqueue_script( 'qcld-wpbot-marketing-admin', $base . 'admin.js', $deps, (string) $asset['version'], true );
		wp_set_script_translations( 'qcld-wpbot-marketing-admin', 'chatbot' );
		wp_enqueue_style( 'wp-components' );
		if ( is_readable( QCLD_WPBOT_MARKETING_DIR . '/assets/build/admin.css' ) ) {
			wp_enqueue_style( 'qcld-wpbot-marketing-admin', $base . 'admin.css', array( Shell::HANDLE, 'wp-components' ), (string) $asset['version'] );
		}
		wp_add_inline_script( 'qcld-wpbot-marketing-admin', 'window.qcldWpbotMarketing = ' . wp_json_encode( self::app_config() ) . ';', 'before' );
	}

	/**
	 * Data for the app.
	 *
	 * @return array
	 */
	public static function app_config() {
		$module = Module::instance();
		$host   = $module->host();
		return array(
			'restNamespace' => 'wpbot-marketing/v1',
			'mode'          => $module->mode(),
			'view'          => self::view(),
			'pageUrl'       => admin_url( 'admin.php?page=' . self::SLUG ),
			'upgradeUrl'    => $host ? (string) $host->get_upgrade_url() : '',
			'aiSettingsUrl' => $host ? (string) $host->get_ai_settings_url() : '',
			'exportUrl'     => \QuantumCloud\AiMarketing\Audience\Exporter::url(),
			'woocommerce'   => class_exists( 'WooCommerce' ),
			'pending'       => $module->master() ? \QuantumCloud\AiMarketing\Autopilot\Suggestions::pending_count() : 0,
			'features'      => array_keys( (array) $module->pro_features() ),
			'can'           => array(
				'use'      => current_user_can( 'wpbot_marketing_use' ),
				'publish'  => current_user_can( 'wpbot_marketing_publish' ),
				'settings' => current_user_can( 'wpbot_marketing_settings' ),
			),
		);
	}

	/**
	 * Current view id.
	 *
	 * @return string
	 */
	public static function view() {
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$ids  = wp_list_pluck( self::items(), 'id' );
		return in_array( $view, $ids, true ) ? $view : 'dashboard';
	}

	/**
	 * URL of a view.
	 *
	 * @param string $view View id.
	 * @return string
	 */
	public static function url( $view ) {
		return add_query_arg(
			array(
				'page' => self::SLUG,
				'view' => $view,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Menu items. Master items are previews outside Master mode.
	 *
	 * @return array[]
	 */
	public static function items() {
		$master = Module::instance()->master();
		$items  = array(
			array( 'dashboard', __( 'Dashboard', 'chatbot' ), 'dashicons-chart-area', false ),
			array( 'contacts', __( 'Contacts', 'chatbot' ), 'dashicons-groups', false ),
			array( 'lists', __( 'Lists and tags', 'chatbot' ), 'dashicons-list-view', false ),
			array( 'capture', __( 'Capture', 'chatbot' ), 'dashicons-download', false ),
			array( 'campaigns', __( 'Campaigns', 'chatbot' ), 'dashicons-megaphone', false ),
			array( 'journeys', __( 'Journeys', 'chatbot' ), 'dashicons-randomize', false ),
			array( 'approvals', __( 'Approval inbox', 'chatbot' ), 'dashicons-yes-alt', true ),
			array( 'autopilot', __( 'Autopilot', 'chatbot' ), 'dashicons-superhero-alt', true ),
			array( 'leads', __( 'Lead Finder', 'chatbot' ), 'dashicons-search', true ),
			array( 'reports', __( 'Reports', 'chatbot' ), 'dashicons-chart-bar', false ),
			array( 'settings', __( 'Settings', 'chatbot' ), 'dashicons-admin-settings', false ),
		);

		/**
		 * Filters the AI Marketing menu items.
		 *
		 * @param array[] $items Each: id, label, icon, master (bool).
		 */
		$items = (array) apply_filters( 'qcld_wpbot_marketing/menu_items', $items );

		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || count( $item ) < 4 ) {
				continue;
			}
			$out[] = array(
				'id'    => (string) $item[0],
				'label' => (string) $item[1],
				'icon'  => (string) $item[2],
				'url'   => self::url( (string) $item[0] ),
				'pro'   => ! empty( $item[3] ) && ! $master,
			);
		}
		return $out;
	}

	/**
	 * One host style as handle, src and version (pure; unit tested). Hosts
	 * give [ handle, src, ver ] or WPBot Core's keyed form (handle, src, ver).
	 *
	 * @param mixed $style Style.
	 * @return array|null
	 */
	public static function style( $style ) {
		if ( ! is_array( $style ) ) {
			return null;
		}
		$handle = isset( $style['handle'] ) ? $style['handle'] : ( isset( $style[0] ) ? $style[0] : '' );
		$src    = isset( $style['src'] ) ? $style['src'] : ( isset( $style[1] ) ? $style[1] : '' );
		$ver    = isset( $style['ver'] ) ? $style['ver'] : ( isset( $style[2] ) ? $style[2] : false );
		if ( ! is_string( $handle ) || '' === $handle || ! is_string( $src ) || '' === $src ) {
			return null;
		}
		return array( $handle, $src, $ver );
	}

	/**
	 * Print the page.
	 *
	 * @return void
	 */
	public static function render() {
		$host = Module::instance()->host();
		Shell::render(
			array(
				'module'  => Module::ID,
				'label'   => __( 'AI Marketing', 'chatbot' ),
				'items'   => self::items(),
				'current' => self::view(),
				'brand'   => $host ? (array) $host->get_brand() : array(),
				'header'  => static function () use ( $host ) {
					if ( $host ) {
						$host->render_admin_header();
					}
				},
				'content' => static function () {
					$app = '<div id="qcld-wpbot-marketing-app" class="qcld-mk-app"><p>' . esc_html__( 'Loading…', 'chatbot' ) . '</p></div>';
					if ( ! has_action( 'qcld_wpbot_marketing/admin_aside' ) ) {
						echo $app; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
						return;
					}
					echo '<div class="qcld-mk-with-aside">' . $app . '<aside class="qcld-mk-aside">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					/**
					 * Prints the host's sidebar next to the app (WPBot Free: its Upgrade To Pro card).
					 */
					do_action( 'qcld_wpbot_marketing/admin_aside' );
					echo '</aside></div>';
				},
			)
		);
	}
}
