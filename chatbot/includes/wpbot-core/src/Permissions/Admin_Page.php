<?php
/**
 * WPBot > Permissions screen: sections × levels × roles.
 *
 * A plain server-rendered form (no JavaScript needed), saved through
 * admin-post with a nonce and manage_options.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Permissions;

use QuantumCloud\WPBotCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the grid.
 */
final class Admin_Page {

	const SLUG   = 'wpbot-permissions';
	const ACTION = 'qcld_wpbot_core_save_permissions';

	/**
	 * Hook the page.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	/**
	 * Add the submenu under the host's menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$host = Core::instance()->host();
		if ( ! $host ) {
			return;
		}
		add_submenu_page(
			$host->get_menu_parent(),
			__( 'Permissions', 'chatbot' ),
			__( 'Permissions', 'chatbot' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Host styles plus a small stylesheet, on this screen only.
	 *
	 * @param string $hook_suffix Current screen.
	 * @return void
	 */
	public static function styles( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::SLUG ) ) {
			return;
		}
		$host = Core::instance()->host();
		foreach ( $host ? (array) $host->get_admin_styles() : array() as $style ) {
			self::enqueue_host_style( $style );
		}
		wp_enqueue_style( 'qcld-wpbot-core-permissions', Core::url( 'assets/css/permissions.css' ), array(), QCLD_WPBOT_CORE_VERSION );
		$brand = $host ? $host->get_brand() : array();
		$vars  = '';
		foreach ( (array) $brand as $key => $value ) {
			$vars .= '--qcwc-' . sanitize_key( str_replace( '_', '-', $key ) ) . ':' . preg_replace( '/[^#a-zA-Z0-9.%() ,-]/', '', (string) $value ) . ';';
		}
		wp_add_inline_style( 'qcld-wpbot-core-permissions', '.qcwc-permissions{' . $vars . '}' );
	}

	/**
	 * Enqueue one host style: a registered handle, or array( handle, src, ver ).
	 *
	 * @param string|array $style Style.
	 * @return void
	 */
	public static function enqueue_host_style( $style ) {
		if ( is_array( $style ) && ! empty( $style['handle'] ) ) {
			wp_enqueue_style( (string) $style['handle'], isset( $style['src'] ) ? (string) $style['src'] : false, array(), isset( $style['ver'] ) ? (string) $style['ver'] : false );
		} elseif ( is_string( $style ) && '' !== $style ) {
			wp_enqueue_style( $style );
		}
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage permissions.', 'chatbot' ) );
		}
		$host     = Core::instance()->host();
		$sections = Sections::all();
		$levels   = Sections::level_labels();
		$grid     = Grid::get();
		$roles    = wp_roles()->get_names();
		unset( $roles['administrator'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag after redirect.
		$saved = isset( $_GET['qcwc_saved'] ) && '1' === sanitize_key( wp_unslash( $_GET['qcwc_saved'] ) );

		echo '<div class="wrap qcwc-permissions">';
		if ( $host ) {
			$host->render_admin_header();
		}
		echo '<h1>' . esc_html__( 'Permissions', 'chatbot' ) . '</h1>';
		echo '<p class="qcwc-intro">' . esc_html__( 'Choose which roles can use each WPBot area. Administrators always have full access, and API keys stay with administrators. WordPress rules for each post still apply: someone who may not edit a post cannot change it with AI either.', 'chatbot' ) . '</p>';

		if ( $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Permissions saved.', 'chatbot' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';

		echo '<p class="qcwc-presets"><span>' . esc_html__( 'Quick presets:', 'chatbot' ) . '</span> ';
		foreach ( Grid::presets() as $id => $label ) {
			echo '<button type="submit" class="button" name="preset" value="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</button> ';
		}
		echo '</p>';

		foreach ( $sections as $section_id => $section ) {
			echo '<div class="qcwc-section">';
			echo '<h2>' . esc_html( $section['label'] ) . '</h2>';
			if ( empty( $section['adopted'] ) ) {
				// No link: the old Menu Access page has been replaced by this screen (and WPBot Free never had one).
				echo '<p class="qcwc-muted">' . esc_html__( 'Only administrators can change access to this area for now. It moves here when the module is updated.', 'chatbot' ) . '</p>';
				echo '</div>';
				continue;
			}
			echo '<table class="widefat striped qcwc-grid"><thead><tr><th scope="col">' . esc_html__( 'Role', 'chatbot' ) . '</th>';
			foreach ( $levels as $label ) {
				echo '<th scope="col">' . esc_html( $label ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $roles as $role_id => $role_name ) {
				echo '<tr><th scope="row">' . esc_html( translate_user_role( $role_name ) ) . '</th>';
				foreach ( array_keys( $levels ) as $level ) {
					$checked = isset( $grid[ $section_id ][ $level ] ) && in_array( $role_id, (array) $grid[ $section_id ][ $level ], true );
					$name    = 'grid[' . $section_id . '][' . $level . '][]';
					/* translators: 1: role name, 2: permission level, 3: area name. */
					$aria = sprintf( __( '%1$s: %2$s in %3$s', 'chatbot' ), translate_user_role( $role_name ), $levels[ $level ], $section['label'] );
					echo '<td><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $role_id ) . '" aria-label="' . esc_attr( $aria ) . '"' . checked( $checked, true, false ) . '></td>';
				}
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '<p class="qcwc-muted">' . esc_html__( '"Manage settings and keys" lets a role change settings; API keys still need an administrator.', 'chatbot' ) . '</p>';
			echo '</div>';
		}

		submit_button( __( 'Save permissions', 'chatbot' ) );
		echo '</form></div>';
	}

	/**
	 * Save the grid or apply a preset.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage permissions.', 'chatbot' ), 403 );
		}
		check_admin_referer( self::ACTION );

		$preset = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : '';
		if ( '' !== $preset && array_key_exists( $preset, Grid::presets() ) ) {
			Grid::apply_preset( $preset );
		} else {
			// Sanitised per key inside Grid::save().
			$grid = isset( $_POST['grid'] ) && is_array( $_POST['grid'] ) ? wp_unslash( $_POST['grid'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			Grid::save( $grid );
		}

		wp_safe_redirect( add_query_arg( 'qcwc_saved', '1', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}
}
