<?php
/**
 * WPBot > Business Context: the screen where the owner tells WPBot about
 * the business once, for every AI feature. WPBot Core owns it (permission
 * section "context"), placed right after "AI Settings" in the host's menu.
 *
 * The page prints the explanation and a container; assets/js/business-context.js
 * (plain JS on wp.element, no build step) renders the summary editor and the
 * sources from GET wpbot-core/v1/context.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Context;

use QuantumCloud\WPBotCore\Admin\Shell;
use QuantumCloud\WPBotCore\Core;
use QuantumCloud\WPBotCore\Permissions\Admin_Page;
use QuantumCloud\WPBotCore\Permissions\Sections;
use QuantumCloud\WPBotCore\Rest\Context_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * The Business Context screen.
 */
final class Business_Context_Page {

	const SLUG = 'wpbot-business-context';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/adopted_sections', array( __CLASS__, 'adopt' ) );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 55 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
			Context_Notice::hook();
		}
	}

	/**
	 * The "Business Context" row in WPBot > Permissions grants access.
	 *
	 * @param string[] $ids Adopted section ids.
	 * @return string[]
	 */
	public static function adopt( $ids ) {
		$ids   = (array) $ids;
		$ids[] = Context_Controller::SECTION;
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Add the page under the host's menu, after AI Settings.
	 *
	 * @return void
	 */
	public static function menu() {
		$host = Core::instance()->host();
		if ( ! $host ) {
			return;
		}
		/**
		 * Position of Business Context in the host's menu (after AI Settings).
		 *
		 * @param int $position Position.
		 */
		$position = (int) apply_filters( 'qcld_wpbot_core/business_context_menu_position', 1 );
		add_submenu_page(
			$host->get_menu_parent(),
			__( 'Business Context', 'chatbot' ),
			__( 'Business Context', 'chatbot' ),
			Sections::cap( Context_Controller::SECTION, 'view' ),
			self::SLUG,
			array( __CLASS__, 'render' ),
			$position
		);
	}

	/**
	 * The page URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Script and style on this screen only.
	 *
	 * @param string $hook_suffix Current screen.
	 * @return void
	 */
	public static function assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::SLUG ) ) {
			return;
		}
		$host = Core::instance()->host();
		foreach ( $host ? (array) $host->get_admin_styles() : array() as $style ) {
			Admin_Page::enqueue_host_style( $style );
		}
		Shell::enqueue();
		wp_enqueue_style( 'qcld-wpbot-core-business-context', Core::url( 'assets/css/business-context.css' ), array( 'wp-components', Shell::HANDLE ), QCLD_WPBOT_CORE_VERSION );
		$brand = $host ? $host->get_brand() : array();
		$vars  = '';
		foreach ( \QuantumCloud\WPBotCore\Host\Brand::styles( (array) $brand ) as $key => $value ) {
			$vars .= '--qcwc-' . sanitize_key( str_replace( '_', '-', $key ) ) . ':' . preg_replace( '/[^#a-zA-Z0-9.%() ,-]/', '', (string) $value ) . ';';
		}
		wp_add_inline_style( 'qcld-wpbot-core-business-context', '.qcwc-bc{' . $vars . '}' );
		wp_enqueue_script( 'qcld-wpbot-core-business-context', Core::url( 'assets/js/business-context.js' ), array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', Shell::HANDLE ), QCLD_WPBOT_CORE_VERSION, true );
		wp_localize_script(
			'qcld-wpbot-core-business-context',
			'qcldWpbotBusinessContext',
			array(
				'adminUrl' => admin_url(),
				'pageUrl'  => self::url(),
				'product'  => \QuantumCloud\WPBotCore\Host\Brand::product(),
			)
		);
	}

	/**
	 * The sections (left menu), in order.
	 *
	 * @return array[] id, label, icon.
	 */
	public static function tabs() {
		return array(
			array(
				'id'    => 'overview',
				'label' => __( 'Overview', 'chatbot' ),
				'icon'  => 'dashicons-dashboard',
			),
			array(
				'id'    => 'business',
				'label' => __( 'Your business', 'chatbot' ),
				'icon'  => 'dashicons-store',
			),
			array(
				'id'    => 'rules',
				'label' => __( 'Facts & rules', 'chatbot' ),
				'icon'  => 'dashicons-yes-alt',
			),
			array(
				'id'    => 'questions',
				'label' => __( 'Customer questions', 'chatbot' ),
				'icon'  => 'dashicons-format-chat',
			),
			array(
				'id'    => 'documents',
				'label' => __( 'Documents', 'chatbot' ),
				'icon'  => 'dashicons-media-document',
			),
			array(
				'id'    => 'sources',
				'label' => __( 'Data sources', 'chatbot' ),
				'icon'  => 'dashicons-database',
			),
			array(
				'id'    => 'settings',
				'label' => __( 'Settings', 'chatbot' ),
				'icon'  => 'dashicons-admin-generic',
			),
		);
	}

	/**
	 * The section in the address (&tab=), else Overview.
	 *
	 * @return string
	 */
	public static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		return in_array( $tab, array_column( self::tabs(), 'id' ), true ) ? $tab : 'overview';
	}

	/**
	 * The page, in the shared WPBot layout: the host's header, a left menu
	 * (one link per section, so links and the back button work) and the app.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( Sections::cap( Context_Controller::SECTION, 'view' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'chatbot' ) );
		}
		$host  = Core::instance()->host();
		$items = array();
		foreach ( self::tabs() as $tab ) {
			$tab['url'] = add_query_arg( 'tab', $tab['id'], self::url() );
			$items[]    = $tab;
		}
		$current = self::current_tab();
		Shell::render(
			array(
				'module'  => Context_Controller::SECTION,
				'label'   => __( 'Business Context menu', 'chatbot' ),
				'items'   => $items,
				'current' => $current,
				'brand'   => $host ? (array) $host->get_brand() : array(),
				'header'  => static function () use ( $host ) {
					// The host's own header (WPBot Pro's), else a plain title.
					ob_start();
					if ( $host ) {
						$host->render_admin_header( __( 'Business Context', 'chatbot' ) );
					}
					$header = (string) ob_get_clean();
					if ( '' !== trim( $header ) ) {
						echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The host's own header markup, escaped by the host.
					} else {
						echo '<h1 class="qcwc-bc__title">' . esc_html__( 'Business Context', 'chatbot' ) . '</h1>';
					}
				},
				'content' => static function () use ( $current ) {
					?>
					<div id="qcwc-business-context" class="qcwc-bc" data-tab="<?php echo esc_attr( $current ); ?>"><p><?php esc_html_e( 'Loading…', 'chatbot' ); ?></p></div>
					<?php
				},
			)
		);
	}
}
