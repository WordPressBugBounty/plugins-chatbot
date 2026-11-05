<?php
/**
 * Admin page: menu item, shared WPBot shell, React app and its data.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Admin;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Research\Competitor_List;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;
use QuantumCloud\WPBotCore\Admin\Shell;
use QuantumCloud\WPBotCore\AI\Key_Health;
use QuantumCloud\WPBotCore\Connections\Google;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the module page.
 */
final class Admin_Controller {

	const SLUG   = 'wpbot-seo-ai-search';
	const HANDLE = 'qcld-wpbot-seo-admin';

	/**
	 * Hook suffix of the page.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function hook() {
		add_action( 'admin_menu', array( $this, 'menu' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Menu item, placed directly after the host's chosen item.
	 *
	 * @return void
	 */
	public function menu() {
		$host = Module::instance()->host();
		if ( ! $host ) {
			return;
		}
		$parent            = $host->get_menu_parent();
		$this->hook_suffix = (string) add_submenu_page(
			$parent,
			__( 'SEO & AI Search Growth', 'chatbot' ),
			$host->get_menu_title(),
			Module::cap( 'view' ),
			self::SLUG,
			array( $this, 'render' ),
			self::position_after( $parent, (array) $host->get_menu_after() )
		);
	}

	/**
	 * Position right after the first submenu item found, or null.
	 *
	 * @param string   $parent_slug Parent slug.
	 * @param string[] $after       Slugs to follow.
	 * @return int|null
	 */
	public static function position_after( $parent_slug, array $after ) {
		global $submenu;
		$after = array_filter( $after );
		if ( array() === $after || ! isset( $submenu[ $parent_slug ] ) ) {
			return null;
		}
		foreach ( array_values( (array) $submenu[ $parent_slug ] ) as $index => $item ) {
			if ( isset( $item[2] ) && in_array( $item[2], $after, true ) ) {
				return $index + 1;
			}
		}
		return null;
	}

	/**
	 * Scripts and styles on the module page only.
	 *
	 * @param string $hook_suffix Current page.
	 * @return void
	 */
	public function assets( $hook_suffix ) {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}
		$host = Module::instance()->host();
		foreach ( $host ? (array) $host->get_admin_styles() : array() as $style ) {
			if ( is_array( $style ) && ! empty( $style['handle'] ) ) {
				wp_enqueue_style( (string) $style['handle'], isset( $style['src'] ) ? (string) $style['src'] : false, array(), isset( $style['ver'] ) ? (string) $style['ver'] : false );
			} elseif ( is_string( $style ) && '' !== $style ) {
				wp_enqueue_style( $style );
			}
		}
		$asset_file = QCLD_WPBOT_SEO_DIR . '/assets/build/admin.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-hooks' ),
			'version'      => QCLD_WPBOT_SEO_VERSION,
		);
		Shell::enqueue();
		$deps = $asset['dependencies'];
		if ( wp_script_is( 'qcld-wpbot-core-ai-readiness', 'registered' ) ) {
			// WPBot Core's shared "is AI ready?" notice (follow-up 3.0).
			$deps[] = 'qcld-wpbot-core-ai-readiness';
			wp_enqueue_style( 'qcld-wpbot-core-ai-readiness' );
		}
		wp_enqueue_script( self::HANDLE, Module::url( 'assets/build/admin.js' ), $deps, $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'chatbot' );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( self::HANDLE, Module::url( 'assets/build/style-admin.css' ), array( 'wp-components' ), $asset['version'] );
		wp_add_inline_style( self::HANDLE, $this->brand_css() );
		wp_add_inline_script( self::HANDLE, 'window.qcldWpbotSeo = ' . wp_json_encode( $this->app_data() ) . ';', 'before' );
		/**
		 * The module page's scripts are enqueued; extensions (the Pro module)
		 * enqueue theirs here, depending on qcld-wpbot-seo-admin.
		 *
		 * @param string $handle Core admin script handle.
		 */
		do_action( 'qcld_wpbot_seo/admin_enqueue', self::HANDLE );
	}

	/**
	 * Page body: host header, left menu, app mount point.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Module::cap( 'view' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to use SEO & AI Search.', 'chatbot' ) );
		}
		$host = Module::instance()->host();
		$app  = static function () {
			echo '<div id="qcseo-app" class="qcseo-app"><p class="qcseo-loading">' . esc_html__( 'Loading SEO & AI Search…', 'chatbot' ) . '</p></div>';
			echo '<noscript><p>' . esc_html__( 'SEO & AI Search needs JavaScript.', 'chatbot' ) . '</p></noscript>';
		};
		echo '<div class="qcseo-wrap">';
		Shell::render(
			array(
				'module'  => Module::ID,
				'label'   => __( 'SEO & AI Search menu', 'chatbot' ),
				'items'   => self::menu_items(),
				'current' => self::current_item(),
				'brand'   => $host ? (array) $host->get_brand() : array(),
				'header'  => $host ? array( $host, 'render_admin_header' ) : null,
				'content' => $app,
			)
		);
		echo '</div>';
	}

	/**
	 * URL of a menu item.
	 *
	 * @param string $item Item id.
	 * @return string
	 */
	public static function item_url( $item ) {
		return admin_url( 'admin.php?page=' . self::SLUG . ( '' !== $item && 'overview' !== $item ? '&tab=' . rawurlencode( $item ) : '' ) );
	}

	/**
	 * Left menu items (plan 7.1).
	 *
	 * @return array
	 */
	public static function menu_items() {
		$items = array(
			array(
				'id'    => 'overview',
				'label' => __( 'Overview', 'chatbot' ),
				'icon'  => 'dashicons-dashboard',
			),
			array(
				'id'    => 'opportunities',
				'label' => __( 'Opportunities', 'chatbot' ),
				'icon'  => 'dashicons-lightbulb',
			),
			array(
				'id'    => 'keywords',
				'label' => __( 'Keywords & Topics', 'chatbot' ),
				'icon'  => 'dashicons-tag',
			),
			array(
				'id'    => 'fixes',
				'label' => __( 'Page Audit & Fixes', 'chatbot' ),
				'icon'  => 'dashicons-admin-tools',
			),
			array(
				'id'    => 'technical',
				'label' => __( 'Technical SEO', 'chatbot' ),
				'icon'  => 'dashicons-admin-site-alt3',
			),
			array(
				'id'    => 'ai-search',
				'label' => __( 'AI Search', 'chatbot' ),
				'icon'  => 'dashicons-format-chat',
			),
			array(
				'id'    => 'links',
				'label' => __( 'Links', 'chatbot' ),
				'icon'  => 'dashicons-admin-links',
			),
			array(
				'id'    => 'local',
				'label' => __( 'Local SEO', 'chatbot' ),
				'icon'  => 'dashicons-location',
			),
			array(
				'id'    => 'results',
				'label' => __( 'Results & Reports', 'chatbot' ),
				'icon'  => 'dashicons-chart-line',
			),
			array(
				'id'     => 'settings',
				'label'  => __( 'Settings & AI Provider', 'chatbot' ),
				'icon'   => 'dashicons-admin-generic',
				'hidden' => ! current_user_can( Module::cap( 'settings' ) ),
			),
		);

		$host = Module::instance()->host();
		if ( $host && ! Settings::get( 'hide_pro_previews' ) ) {
			foreach ( (array) $host->get_pro_previews() as $preview ) {
				// Previews placed as a tab inside a section are not left-menu items.
				if ( is_array( $preview ) && ! empty( $preview['id'] ) && ! empty( $preview['label'] ) && null === self::preview_tab( $preview ) ) {
					$items[] = array(
						'id'    => 'preview-' . sanitize_key( $preview['id'] ),
						'label' => (string) $preview['label'],
						'icon'  => 'dashicons-lock',
						'pro'   => true,
					);
				}
			}
		}

		/**
		 * Left menu items; extensions add items or badges.
		 *
		 * @param array $items Items.
		 */
		$items = (array) apply_filters( 'qcld_wpbot_seo/menu_items', $items );
		foreach ( $items as $index => $item ) {
			if ( is_array( $item ) && ! empty( $item['id'] ) && empty( $item['url'] ) ) {
				$items[ $index ]['url'] = self::item_url( sanitize_key( $item['id'] ) );
			}
		}
		return $items;
	}

	/**
	 * Open menu item from the URL.
	 *
	 * @return string
	 */
	public static function current_item() {
		$ids = array();
		foreach ( self::menu_items() as $item ) {
			if ( is_array( $item ) && ! empty( $item['id'] ) && empty( $item['hidden'] ) ) {
				$ids[] = sanitize_key( $item['id'] );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		return in_array( $tab, $ids, true ) ? $tab : 'overview';
	}

	/**
	 * One Pro preview for the app: id, label, text, the feature list and an
	 * optional link (plain text only; the app escapes it when printing).
	 *
	 * @param array $preview Item from the host's get_pro_previews().
	 * @return array
	 */
	private static function preview_data( array $preview ) {
		$features = array();
		foreach ( isset( $preview['features'] ) ? (array) $preview['features'] : array() as $feature ) {
			$feature = trim( wp_strip_all_tags( (string) $feature ) );
			if ( '' !== $feature ) {
				$features[] = $feature;
			}
		}
		$url = isset( $preview['url'] ) ? esc_url_raw( (string) $preview['url'] ) : '';
		$tab = self::preview_tab( $preview );
		return array(
			'id'       => 'preview-' . sanitize_key( $preview['id'] ),
			'label'    => isset( $preview['label'] ) ? (string) $preview['label'] : '',
			'text'     => isset( $preview['text'] ) ? (string) $preview['text'] : '',
			'features' => $features,
			'url'      => $url,
			'button'   => '' !== $url && isset( $preview['button'] ) ? (string) $preview['button'] : '',
			'tab'      => $tab,
		);
	}

	/**
	 * Where a preview shows as a tab: "section:name" (for example
	 * "keywords:competitors") becomes section and tab name, or null for a
	 * left-menu preview.
	 *
	 * @param array $preview Item from the host's get_pro_previews().
	 * @return array|null Section and name, or null.
	 */
	private static function preview_tab( array $preview ) {
		if ( empty( $preview['tab'] ) || ! is_string( $preview['tab'] ) || false === strpos( $preview['tab'], ':' ) ) {
			return null;
		}
		list( $section, $name ) = explode( ':', $preview['tab'], 2 );
		$section                = preg_replace( '/[^A-Za-z0-9_-]/', '', $section );
		$name                   = sanitize_key( $name );
		return '' !== $section && '' !== $name ? array(
			'section' => $section,
			'name'    => $name,
		) : null;
	}

	/**
	 * Data for the app (no secrets).
	 *
	 * @return array
	 */
	private function app_data() {
		$host = Module::instance()->host();
		$menu = array();
		foreach ( self::menu_items() as $item ) {
			if ( is_array( $item ) && ! empty( $item['id'] ) && empty( $item['hidden'] ) ) {
				$menu[] = array(
					'id'    => sanitize_key( $item['id'] ),
					'label' => (string) $item['label'],
					'pro'   => ! empty( $item['pro'] ),
				);
			}
		}
		$previews = array();
		foreach ( $host && ! Settings::get( 'hide_pro_previews' ) ? (array) $host->get_pro_previews() : array() as $preview ) {
			if ( is_array( $preview ) && ! empty( $preview['id'] ) ) {
				$previews[] = self::preview_data( $preview );
			}
		}
		/**
		 * Data for the admin app; extensions add their own keys.
		 *
		 * @param array $data Data.
		 */
		return (array) apply_filters(
			'qcld_wpbot_seo/admin_data',
			array(
				'version'    => QCLD_WPBOT_SEO_VERSION,
				'module'     => Module::ID,
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'can'        => array(
					'view'     => current_user_can( Module::cap( 'view' ) ),
					'use'      => current_user_can( Module::cap( 'use' ) ),
					'publish'  => current_user_can( Module::cap( 'publish' ) ),
					'settings' => current_user_can( Module::cap( 'settings' ) ),
					'keys'     => current_user_can( 'manage_options' ) && current_user_can( Module::cap( 'settings' ) ),
				),
				'settings'   => Settings::all(),
				// AI key problems come through `readiness` (WPBot Core's shared check); older cores keep the key notices.
				'health'     => array_merge( function_exists( 'qcld_wpbot_core_ai_readiness' ) ? array() : Key_Health::notices( Module::ID ), \QuantumCloud\SeoAiSearch\Keywords\Volumes::notices() ),
				'readiness'  => function_exists( 'qcld_wpbot_core_ai_readiness' ) ? qcld_wpbot_core_ai_readiness( Module::ID ) : null,
				'features'   => Module::instance()->features()->ids(),
				'menu'       => $menu,
				'current'    => self::current_item(),
				'previews'   => $previews,
				// Round 3 follow-up: WPBot > Business Context (WPBot Core) replaces the Site Context panel.
				'businessContext' => class_exists( '\\QuantumCloud\\WPBotCore\\Context\\Business_Context_Page' ) ? \QuantumCloud\WPBotCore\Context\Business_Context_Page::url() : '',
				'compPrefs'  => Competitor_List::available() ? Competitor_List::prefs() : null,
				'seoPlugin'  => Seo_Plugins::label(),
				'writer'     => function_exists( 'qcld_wpbot_writer_open_brief_url' ),
				'adminUrl'   => esc_url_raw( admin_url() ),
				'pageUrl'    => esc_url_raw( admin_url( 'admin.php?page=' . self::SLUG ) ),
				'siteUrl'    => esc_url_raw( home_url( '/' ) ),
				'googleNote' => Google::take_notice(),
				'aiSettings' => $host && method_exists( $host, 'get_ai_settings_url' ) ? esc_url_raw( $host->get_ai_settings_url() ) : '',
			)
		);
	}

	/**
	 * Brand colours as CSS variables.
	 *
	 * @return string
	 */
	private function brand_css() {
		$host  = Module::instance()->host();
		$brand = $host ? (array) $host->get_brand() : array();
		$vars  = '';
		foreach ( $brand as $key => $value ) {
			$key   = sanitize_key( $key );
			$value = preg_replace( '/[^#a-zA-Z0-9().,%\s\-]/', '', (string) $value );
			if ( '' !== $key && '' !== $value ) {
				$vars .= '--qcseo-' . str_replace( '_', '-', $key ) . ':' . $value . ';';
			}
		}
		return '' !== $vars ? '.qcseo-wrap{' . $vars . '}' : '';
	}
}
