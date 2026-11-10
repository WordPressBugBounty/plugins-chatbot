<?php
/**
 * Admin screen: one React app mounted inside the host's page shell
 * (host header, host stylesheets, host brand colours).
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Admin;

use QuantumCloud\Insights\AI\AI_Settings;
use QuantumCloud\Insights\Module;
use QuantumCloud\Insights\Settings;

defined( 'ABSPATH' ) || exit;

final class Admin_Controller {

	const PAGE = 'qcin-insights';

	/**
	 * Hook suffix of the screen.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Add the menu entry under the host menu (or as its own menu).
	 */
	public function menu() {
		$module = Module::instance();
		$host   = $module->host();
		$cap    = $module->capability( 'view' );
		$title  = self::title();
		$parent = $host ? $host->get_menu_parent() : null;

		if ( $parent ) {
			$position   = $this->position_before( $parent, $host->get_menu_before() );
			$this->hook = (string) add_submenu_page( $parent, $title, $title, $cap, self::PAGE, array( $this, 'render' ), $position );
		} else {
			$this->hook = (string) add_menu_page( $title, $title, $cap, self::PAGE, array( $this, 'render' ), 'dashicons-visibility', 58 );
		}
	}

	/**
	 * Menu and page title from the host (default "Insights").
	 *
	 * @return string
	 */
	public static function title() {
		$host  = Module::instance()->host();
		$title = $host ? trim( (string) $host->get_menu_title() ) : '';
		return '' !== $title ? $title : __( 'Insights', 'qcin-textdomain' );
	}

	/**
	 * Index in the parent's submenu of the first listed slug, so our item
	 * is inserted just above it. Runs after the host added its items.
	 *
	 * @param string   $parent Parent menu slug.
	 * @param string[] $before Slugs in order of preference.
	 * @return int|null Null when none is present (item goes last).
	 */
	private function position_before( $parent, $before ) {
		global $submenu;
		if ( empty( $submenu[ $parent ] ) || empty( $before ) ) {
			return null;
		}
		$items = array_values( (array) $submenu[ $parent ] );
		foreach ( (array) $before as $slug ) {
			foreach ( $items as $index => $item ) {
				if ( isset( $item[2] ) && $item[2] === $slug ) {
					return $index;
				}
			}
		}
		return null;
	}

	/**
	 * Whether the current screen is ours.
	 *
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	private function is_ours( $hook = '' ) {
		if ( '' !== $hook && $hook === $this->hook ) {
			return true;
		}
		return isset( $_GET['page'] ) && self::PAGE === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Body class for page-level background.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		return $this->is_ours() ? $classes . ' qcin-admin-page' : $classes;
	}

	/**
	 * Load host styles, our styles and the app.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function assets( $hook ) {
		if ( ! $this->is_ours( $hook ) ) {
			return;
		}
		$module = Module::instance();
		$host   = $module->host();
		$deps   = array();

		if ( $host ) {
			foreach ( (array) $host->get_admin_styles() as $style ) {
				if ( empty( $style['handle'] ) || empty( $style['src'] ) ) {
					continue;
				}
				wp_enqueue_style( $style['handle'], $style['src'], array(), $style['ver'] ?? null );
				$deps[] = $style['handle'];
			}
		}

		$base = plugins_url( 'assets/build/', QCIN_FILE );
		wp_enqueue_style( 'qcin-admin', $base . 'admin.css', $deps, QCIN_VERSION );
		wp_add_inline_style( 'qcin-admin', $this->brand_css() );

		wp_enqueue_script( 'qcin-admin', $base . 'admin.js', array( 'wp-element', 'wp-api-fetch', 'wp-i18n', 'wp-hooks' ), QCIN_VERSION, true );
		wp_add_inline_script( 'qcin-admin', 'window.qcInsightsAdmin=' . wp_json_encode( $this->app_data() ) . ';', 'before' );
		// The host's build replaces the placeholder domain with its own (e.g. "chatbot").
		wp_set_script_translations( 'qcin-admin', 'qcin-textdomain' );

		$this->feature_assets();
	}

	/**
	 * Scripts and styles of features (screens, panels, settings sections).
	 * They load after the app script, which shares its UI parts on
	 * window.qcInsightsUI and draws feature parts registered with wp.hooks.
	 */
	private function feature_assets() {
		/**
		 * Feature styles for the Insights screen.
		 * Each item: array( 'handle' => ..., 'src' => ..., 'deps' => array(), 'ver' => ... ).
		 *
		 * @param array<int,array<string,mixed>> $styles Styles.
		 */
		foreach ( (array) apply_filters( 'qcin/admin_styles', array() ) as $style ) {
			if ( ! is_array( $style ) || empty( $style['handle'] ) || empty( $style['src'] ) ) {
				continue;
			}
			wp_enqueue_style( (string) $style['handle'], (string) $style['src'], array_merge( array( 'qcin-admin' ), (array) ( $style['deps'] ?? array() ) ), $style['ver'] ?? QCIN_VERSION );
		}

		/**
		 * Feature scripts for the Insights screen. They always load after
		 * 'qcin-admin'. Each item: array( 'handle' => ..., 'src' => ...,
		 * 'deps' => array(), 'ver' => ..., 'data' => array() (optional, printed
		 * as window[ 'data_var' ]), 'data_var' => ..., 'domain' => text domain ).
		 *
		 * @param array<int,array<string,mixed>> $scripts Scripts.
		 */
		foreach ( (array) apply_filters( 'qcin/admin_scripts', array() ) as $script ) {
			if ( ! is_array( $script ) || empty( $script['handle'] ) || empty( $script['src'] ) ) {
				continue;
			}
			$handle = (string) $script['handle'];
			wp_enqueue_script( $handle, (string) $script['src'], array_merge( array( 'qcin-admin' ), (array) ( $script['deps'] ?? array() ) ), $script['ver'] ?? QCIN_VERSION, true );
			if ( ! empty( $script['data_var'] ) && preg_match( '/^[A-Za-z_$][A-Za-z0-9_$]*$/', (string) $script['data_var'] ) ) {
				wp_add_inline_script( $handle, 'window.' . $script['data_var'] . '=' . wp_json_encode( $script['data'] ?? array() ) . ';', 'before' );
			}
			if ( ! empty( $script['domain'] ) ) {
				wp_set_script_translations( $handle, (string) $script['domain'] );
			}
		}
	}

	/**
	 * Brand tokens as CSS variables scoped to the app.
	 *
	 * @return string
	 */
	private function brand_css() {
		$host  = Module::instance()->host();
		$brand = $host ? (array) $host->get_brand() : array();
		$map   = array(
			'primary'        => '--qcin-primary',
			'primary_dark'   => '--qcin-primary-dark',
			'primary_soft'   => '--qcin-primary-soft',
			'primary_border' => '--qcin-primary-border',
			'page_bg'        => '--qcin-page-bg',
			'surface'        => '--qcin-surface',
			'text'           => '--qcin-text',
			'muted'          => '--qcin-muted',
			'danger'         => '--qcin-danger',
			'success'        => '--qcin-success',
			'radius'         => '--qcin-radius',
			'font_family'    => '--qcin-font',
		);
		$vars  = array();
		foreach ( $map as $key => $var ) {
			if ( isset( $brand[ $key ] ) && '' !== $brand[ $key ] && preg_match( '/^[#a-zA-Z0-9(),.\s%"\'-]+$/', (string) $brand[ $key ] ) ) {
				$vars[] = $var . ':' . $brand[ $key ];
			}
		}
		return $vars ? 'body.qcin-admin-page{' . implode( ';', $vars ) . '}' : '';
	}

	/**
	 * Data the app needs on load.
	 *
	 * @return array<string,mixed>
	 */
	private function app_data() {
		$module = Module::instance();
		$host   = $module->host();
		$ai     = $module->ai();
		$roles  = array();
		foreach ( wp_roles()->get_names() as $key => $name ) {
			$roles[] = array(
				'value' => $key,
				'label' => translate_user_role( $name ),
			);
		}

		/**
		 * Screens shown in the Insights navigation. Features add their own
		 * (and draw them with the JS filter 'qcin.admin.screens').
		 *
		 * @param array<int,array<string,string>> $screens id + label (+ optional icon).
		 */
		$screens = (array) apply_filters(
			'qcin/admin_screens',
			array(
				array(
					'id'    => 'overview',
					'label' => __( 'Overview', 'qcin-textdomain' ),
				),
				array(
					'id'    => 'recordings',
					'label' => __( 'Recordings', 'qcin-textdomain' ),
				),
				array(
					'id'    => 'heatmaps',
					'label' => __( 'Heatmaps', 'qcin-textdomain' ),
				),
				array(
					'id'    => 'settings',
					'label' => __( 'Settings', 'qcin-textdomain' ),
				),
			)
		);

		$can_settings = $module->can( 'settings' );
		if ( ! $can_settings ) {
			$screens = array_filter(
				$screens,
				static function ( $screen ) {
					return ! ( is_array( $screen ) && isset( $screen['id'] ) && 'settings' === $screen['id'] );
				}
			);
		}

		$data = array(
			'version'     => QCIN_VERSION,
			'restRoot'    => esc_url_raw( rest_url( 'qcin/v1/admin' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'settings'    => Settings::all(),
			'roles'       => $roles,
			'screens'     => array_values( $screens ),
			'features'    => $module->registry()->ids(),
			'hostName'    => $host ? $host->get_name() : '',
			'title'       => self::title(),
			'hostSettingsUrl' => $host ? $host->get_settings_url() : admin_url(),
			'aiSettingsUrl'   => $host ? $host->get_ai_settings_url() : admin_url(),
			'aiAvailable' => $ai->is_available(),
			'aiSummary'   => $ai->is_available() && AI_Settings::feature_on( 'summary' ),
			'aiLabel'     => $ai->get_label(),
			'consentApi'  => function_exists( 'wp_has_consent' ),
			'privacyUrl'  => admin_url( 'options-privacy.php?tab=policyguide' ),
			'siteUrl'     => home_url( '/' ),
			'timezone'    => wp_timezone_string(),
			'can'         => array(
				'settings' => $can_settings,
			),
			// Email reports (false when a feature sends its own).
			'reports'     => \QuantumCloud\Insights\Reports\Reports::enabled(),
			// Extra frustration signal types from features (qcin/signal_types).
			'signalTypes' => array_map(
				static function ( $def ) {
					return array(
						'label' => $def['label'],
						'short' => $def['short'],
						'help'  => $def['help'],
					);
				},
				\QuantumCloud\Insights\Storage\Signal_Types::extra()
			),
		);

		/**
		 * Data the admin app gets on load (window.qcInsightsAdmin).
		 * Features may add their own keys; never add secrets.
		 *
		 * @param array<string,mixed> $data Data.
		 */
		$filtered = apply_filters( 'qcin/admin_data', $data );
		return is_array( $filtered ) ? $filtered : $data;
	}

	/**
	 * Page markup: host header, then the app.
	 */
	public function render() {
		$host = Module::instance()->host();
		echo '<div class="wrap qcin-wrap">';
		if ( $host ) {
			$host->render_admin_header();
		}
		echo '<div id="qcin-app" class="qcin-app"><div class="qcin-loading">' . esc_html(
			sprintf(
				/* translators: %s: menu title, e.g. "Site Insight". */
				__( 'Loading %s…', 'qcin-textdomain' ),
				self::title()
			)
		) . '</div></div>';
		if ( $host ) {
			$host->render_admin_footer();
		}
		echo '</div>';
	}
}
