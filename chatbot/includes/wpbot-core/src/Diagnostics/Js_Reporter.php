<?php
/**
 * Browser errors for the error log: a small script (assets/js/error-reporter.js) on the front
 * end (the chat widget) and on WPBot's admin screens reports errors of WPBot's own scripts to
 * POST wpbot-core/v1/errors/js. The owner can turn it off (Error log settings).
 *
 * The endpoint is public (visitors have no account), so it only accepts errors whose script is
 * one of WPBot's, and limits each visitor to 10 reports per 10 minutes and the site to 100 per hour.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Diagnostics;

use QuantumCloud\WPBotCore\Core;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Browser error reporter.
 */
final class Js_Reporter {

	const HANDLE = 'qcld-wpbot-core-errors';

	/**
	 * Hook the script.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ), 1 );
	}

	/**
	 * The front-end reporter (the chat widget).
	 *
	 * @return void
	 */
	public static function enqueue_front() {
		/**
		 * Whether the browser error reporter loads on this front-end page.
		 *
		 * @param bool $load Default: on when the owner records browser errors.
		 */
		if ( ! apply_filters( 'qcld_wpbot_core/error_reporter_front', Error_Log::settings()['browser'] ) ) {
			return;
		}
		self::enqueue( 'widget', '' );
	}

	/**
	 * The admin reporter, on WPBot's screens.
	 *
	 * @return void
	 */
	public static function enqueue_admin() {
		if ( ! Error_Log::settings()['browser'] || ! self::is_wpbot_screen() ) {
			return;
		}
		self::enqueue( 'admin', wp_create_nonce( 'wp_rest' ) );
	}

	/**
	 * Whether the current admin page is one of WPBot's (under the host's menu).
	 *
	 * @return bool
	 */
	public static function is_wpbot_screen() {
		if ( class_exists( '\\QuantumCloud\\WPBotCore\\Admin\\Controls' ) ) {
			return \QuantumCloud\WPBotCore\Admin\Controls::is_wpbot_screen();
		}
		$host   = Core::instance()->host();
		$parent = $host ? (string) $host->get_menu_parent() : '';
		$is     = '' !== $parent && 'options-general.php' !== $parent && function_exists( 'get_admin_page_parent' ) && get_admin_page_parent() === $parent;
		/** This filter is documented in src/Admin/Controls.php (WPBot screens). */
		return (bool) apply_filters( 'qcld_wpbot_core/ui_screen', $is );
	}

	/**
	 * Enqueue the script in the page head, so it hears errors of later scripts.
	 *
	 * @param string $where widget or admin.
	 * @param string $nonce REST nonce (admin only).
	 * @return void
	 */
	private static function enqueue( $where, $nonce ) {
		$version = defined( 'QCLD_WPBOT_CORE_VERSION' ) ? QCLD_WPBOT_CORE_VERSION : false;
		wp_enqueue_script( self::HANDLE, Core::url( 'assets/js/error-reporter.js' ), array(), $version, false );
		wp_add_inline_script(
			self::HANDLE,
			'window.qcldWpbotErrors = ' . wp_json_encode(
				array(
					'url'      => esc_url_raw( rest_url( 'wpbot-core/v1/errors/js' ) ),
					'where'    => $where,
					'nonce'    => $nonce,
					'prefixes' => self::prefixes(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * URL beginnings of WPBot's scripts (each WPBot plugin folder's URL).
	 *
	 * @return string[]
	 */
	public static function prefixes() {
		$plugins  = defined( 'WP_PLUGIN_DIR' ) ? rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' ) . '/' : '';
		$prefixes = array();
		foreach ( Error_Capture::paths() as $path ) {
			if ( '' !== $plugins && 0 === strpos( $path . '/', $plugins ) ) {
				$prefixes[] = plugins_url( substr( $path, strlen( $plugins ) ) . '/' );
			}
		}
		return array_values( array_unique( $prefixes ) );
	}

	/**
	 * POST /errors/js: store one browser error.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function receive( WP_REST_Request $request ) {
		$where   = 'admin' === $request->get_param( 'where' ) && current_user_can( 'edit_posts' ) ? 'admin' : 'widget';
		$message = trim( (string) $request->get_param( 'message' ) );
		$source  = (string) $request->get_param( 'source' );
		$stack   = (string) $request->get_param( 'stack' );
		if ( '' === $message || ( 'widget' === $where && ! Error_Log::settings()['browser'] ) ) {
			return new WP_REST_Response( array( 'stored' => false ), 200 );
		}
		if ( ! self::ours( $source ) && ! self::ours( $stack ) ) {
			return new WP_REST_Response( array( 'stored' => false ), 200 );
		}
		if ( ! self::allowed() ) {
			return new WP_REST_Response( array( 'stored' => false ), 429 );
		}
		$site   = trailingslashit( home_url() );
		$stored = Error_Log::add(
			'js',
			substr( $message, 0, 500 ),
			array(
				'level'  => 'error',
				'module' => 'admin' === $where ? 'admin-screen' : 'widget',
				'file'   => str_replace( $site, '', substr( $source, 0, 500 ) ),
				'line'   => (int) $request->get_param( 'line' ),
				'col'    => (int) $request->get_param( 'col' ),
				'stack'  => str_replace( $site, '', substr( $stack, 0, 2000 ) ),
				'url'    => substr( (string) wp_parse_url( (string) $request->get_param( 'page' ), PHP_URL_PATH ), 0, 300 ),
				'agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 200 ) : '',
			)
		);
		return new WP_REST_Response( array( 'stored' => $stored ), 200 );
	}

	/**
	 * Whether a URL or stack names one of WPBot's scripts.
	 *
	 * @param string $text URL or stack.
	 * @return bool
	 */
	public static function ours( $text ) {
		if ( '' === (string) $text ) {
			return false;
		}
		foreach ( self::prefixes() as $prefix ) {
			if ( false !== strpos( (string) $text, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rate limits: 10 reports per visitor per 10 minutes, 100 per site per hour.
	 *
	 * @return bool
	 */
	private static function allowed() {
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$visitor = 'qcld_wpbot_core_jserr_' . substr( md5( $ip . wp_salt( 'nonce' ) ), 0, 16 );
		$site    = 'qcld_wpbot_core_jserr_all';
		$mine    = (int) get_transient( $visitor );
		$all     = (int) get_transient( $site );
		if ( $mine >= 10 || $all >= 100 ) {
			return false;
		}
		set_transient( $visitor, $mine + 1, 10 * MINUTE_IN_SECONDS );
		set_transient( $site, $all + 1, HOUR_IN_SECONDS );
		return true;
	}
}
