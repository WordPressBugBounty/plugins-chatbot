<?php
/**
 * Help Center → Design: layout, accent colour, light or dark, the home page's
 * title and introduction, and a contact address, with a live preview of the
 * help center (owner decisions D1, D5).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Front\Urls;
use QuantumCloud\HelpCenter\Permissions;
use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Design screen.
 */
final class Design_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'wpbot-help-center-design';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_qcld_wpbot_help_center_design', array( self::class, 'save' ) );
	}

	/**
	 * Add the page under Help Center.
	 *
	 * @return void
	 */
	public static function menu() {
		// Owner, 19 November 2026: Design is a tab of the Settings page. The old address stays
		// registered (not listed) and opens that tab.
		$hook = add_submenu_page( 'edit.php?post_type=' . Post_Type::NAME, __( 'Help Center design', 'chatbot' ), __( 'Design', 'chatbot' ), Permissions::cap( 'settings' ), self::SLUG, '__return_null', 85 );
		remove_submenu_page( 'edit.php?post_type=' . Post_Type::NAME, self::SLUG );
		if ( $hook ) {
			add_action( 'load-' . $hook, array( self::class, 'go_to_tab' ) );
		}
	}

	/**
	 * The Design tab of the Settings page.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'tab' => 'design' ), $args ), admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . Settings_Page::SLUG ) );
	}

	/**
	 * The old Design address opens the Design tab.
	 *
	 * @return void
	 */
	public static function go_to_tab() {
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Clean submitted design values.
	 *
	 * @param array $raw Submitted values.
	 * @return array
	 */
	public static function clean( array $raw ) {
		return array(
			'layout'      => isset( $raw['layout'] ) && in_array( $raw['layout'], Settings::LAYOUTS, true ) ? $raw['layout'] : 'help-center',
			'accent'      => Settings::color( isset( $raw['accent'] ) ? (string) $raw['accent'] : '' ),
			'scheme'      => isset( $raw['scheme'] ) && in_array( $raw['scheme'], Settings::SCHEMES, true ) ? $raw['scheme'] : 'auto',
			'title'       => isset( $raw['title'] ) ? substr( sanitize_text_field( (string) $raw['title'] ), 0, 120 ) : '',
			'lede'        => isset( $raw['lede'] ) ? substr( sanitize_text_field( (string) $raw['lede'] ), 0, 240 ) : '',
			'contact_url' => isset( $raw['contact_url'] ) ? esc_url_raw( (string) $raw['contact_url'] ) : '',
		);
	}

	/**
	 * Save the form.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( Permissions::cap( 'settings' ) ) ) {
			wp_die( esc_html__( 'You are not allowed to change the Help Center design.', 'chatbot' ), 403 );
		}
		check_admin_referer( 'qcld_wpbot_help_center_design' );
		$raw = array();
		foreach ( array( 'layout', 'accent', 'scheme', 'title', 'lede', 'contact_url' ) as $key ) {
			$raw[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		update_option( Settings::OPTION, array_merge( Settings::all(), self::clean( $raw ) ) );
		wp_safe_redirect( self::url( array( 'updated' => '1' ) ) );
		exit;
	}

	/**
	 * Render the Design tab (inside the Settings page).
	 *
	 * @return void
	 */
	public static function render() {
		$all    = Settings::all();
		$design = Settings::design();
		$labels = array(
			'help-center' => array( __( 'Help Center', 'chatbot' ), __( 'Search and the assistant up front, topics with their top articles, most read and recently updated.', 'chatbot' ) ),
			'docs'        => array( __( 'Docs', 'chatbot' ), __( 'Navigation on the left, the article in the middle, the table of contents on the right.', 'chatbot' ) ),
			'simple'      => array( __( 'Simple', 'chatbot' ), __( 'One column inside your theme, in your theme\'s fonts and colours.', 'chatbot' ) ),
		);
		echo '<div class="wpbot-help-design">';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Message after a redirect.
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Design saved.', 'chatbot' ) . '</p></div>';
		}
		echo '<div class="wpbot-help-design__grid"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wpbot-help-design__form">';
		wp_nonce_field( 'qcld_wpbot_help_center_design' );
		echo '<input type="hidden" name="action" value="qcld_wpbot_help_center_design">';
		echo '<fieldset><legend>' . esc_html__( 'Layout', 'chatbot' ) . '</legend>';
		foreach ( $labels as $value => $label ) {
			echo '<label class="wpbot-help-design__choice"><input type="radio" name="layout" value="' . esc_attr( $value ) . '"' . checked( $design['layout'], $value, false ) . '> <strong>' . esc_html( $label[0] ) . '</strong><span>' . esc_html( $label[1] ) . '</span></label>';
		}
		echo '</fieldset>';
		echo '<p><label for="hc-accent">' . esc_html__( 'Accent colour', 'chatbot' ) . '</label><br><input type="color" id="hc-accent" name="accent" value="' . esc_attr( $design['accent'] ) . '"></p>';
		echo '<p><label for="hc-scheme">' . esc_html__( 'Light or dark', 'chatbot' ) . '</label><br><select id="hc-scheme" name="scheme">';
		foreach ( array(
			'auto'  => __( 'Follow the visitor\'s device', 'chatbot' ),
			'light' => __( 'Light', 'chatbot' ),
			'dark'  => __( 'Dark', 'chatbot' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $design['scheme'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="hc-title">' . esc_html__( 'Home page title', 'chatbot' ) . '</label><br><input type="text" class="regular-text" id="hc-title" name="title" value="' . esc_attr( $all['title'] ) . '" placeholder="' . esc_attr__( 'How can we help?', 'chatbot' ) . '"></p>';
		echo '<p><label for="hc-lede">' . esc_html__( 'Introduction', 'chatbot' ) . '</label><br><input type="text" class="large-text" id="hc-lede" name="lede" value="' . esc_attr( $all['lede'] ) . '"></p>';
		echo '<p><label for="hc-contact">' . esc_html__( 'Contact page (optional)', 'chatbot' ) . '</label><br><input type="url" class="regular-text" id="hc-contact" name="contact_url" value="' . esc_attr( $all['contact_url'] ) . '" placeholder="https://"></p>';
		submit_button( __( 'Save design', 'chatbot' ) );
		echo '</form>';
		$preview = add_query_arg( 'hc_preview', '1', Urls::home() );
		echo '<div class="wpbot-help-design__preview"><p class="description">' . esc_html__( 'Preview (not saved yet)', 'chatbot' ) . '</p><iframe title="' . esc_attr__( 'Help Center preview', 'chatbot' ) . '" src="' . esc_url( $preview ) . '" data-base="' . esc_url( $preview ) . '"></iframe></div></div></div>';
		echo '<style>.wpbot-help-design__grid{display:grid;grid-template-columns:minmax(280px,380px) 1fr;gap:24px;align-items:start}.wpbot-help-design__choice{display:block;padding:10px 12px;border:1px solid #dcdcde;border-radius:6px;margin:6px 0;background:#fff}.wpbot-help-design__choice span{display:block;color:#646970;margin-left:24px}.wpbot-help-design fieldset legend{font-weight:600;margin-bottom:4px}.wpbot-help-design__preview iframe{width:100%;height:78vh;border:1px solid #dcdcde;border-radius:6px;background:#fff}@media (max-width:960px){.wpbot-help-design__grid{grid-template-columns:1fr}}</style>';
		echo '<script>(function(){var f=document.querySelector(".wpbot-help-design__form"),i=document.querySelector(".wpbot-help-design__preview iframe");if(!f||!i){return;}var t;f.addEventListener("input",function(){clearTimeout(t);t=setTimeout(function(){var u=new URL(i.dataset.base);var l=f.querySelector("[name=layout]:checked");u.searchParams.set("hc_layout",l?l.value:"");u.searchParams.set("hc_accent",f.accent.value);u.searchParams.set("hc_scheme",f.scheme.value);i.src=u.toString();},250);});})();</script>';
	}
}
