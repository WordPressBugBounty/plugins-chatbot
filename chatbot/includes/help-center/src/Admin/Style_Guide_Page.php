<?php
/**
 * Help Center → Settings → Style guide (HC3-C, owner's go 26 November 2026).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Permissions;
use QuantumCloud\HelpCenter\Settings;
use QuantumCloud\HelpCenter\Style_Guide;

defined( 'ABSPATH' ) || exit;

/**
 * Style guide tab.
 */
final class Style_Guide_Page {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_qcld_wpbot_help_center_style', array( self::class, 'save' ) );
	}

	/**
	 * The tab's address.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'tab' => 'style' ), $args ), admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . Settings_Page::SLUG ) );
	}

	/**
	 * Save (or reset, or prefill again from Business Context).
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( Permissions::cap( 'settings' ) ) ) {
			wp_die( esc_html__( 'You are not allowed to change the Help Center settings.', 'chatbot' ), 403 );
		}
		check_admin_referer( 'qcld_wpbot_help_center_style' );
		$do                        = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'save';
		$settings                  = Settings::all();
		$settings['style_prefill'] = empty( $_POST['style_prefill'] ) ? '' : '1';
		update_option( Settings::OPTION, $settings );
		if ( 'prefill' === $do ) {
			$found = Style_Guide::refill();
			wp_safe_redirect( self::url( array( 'hc_style' => $found ? 'prefilled' : 'nothing' ) ) );
			exit;
		}
		$raw = array();
		foreach ( array( 'audience', 'tone', 'words_use', 'words_avoid', 'paths', 'screenshots' ) as $key ) {
			$raw[ $key ] = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		$raw['templates'] = array();
		foreach ( Style_Guide::TYPES as $type ) {
			$raw['templates'][ $type ] = isset( $_POST['templates'][ $type ] ) ? sanitize_textarea_field( wp_unslash( $_POST['templates'][ $type ] ) ) : '';
		}
		Style_Guide::save( $raw );
		wp_safe_redirect( self::url( array( 'hc_style' => 'saved' ) ) );
		exit;
	}

	/**
	 * Render inside Settings.
	 *
	 * @return void
	 */
	public static function render() {
		$guide   = Style_Guide::get();
		$message = isset( $_GET['hc_style'] ) ? sanitize_key( wp_unslash( $_GET['hc_style'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A message after a redirect.
		$texts   = array(
			'saved'     => __( 'Style guide saved.', 'chatbot' ),
			'prefilled' => __( 'Filled in from your Business Context.', 'chatbot' ),
			'nothing'   => __( 'Your Business Context has no audience or word lists yet, so nothing was filled in.', 'chatbot' ),
		);
		if ( isset( $texts[ $message ] ) ) {
			echo '<div class="wpb-strip notice notice-success" role="status"><p>' . esc_html( $texts[ $message ] ) . '</p></div>';
		}
		echo '<p class="description" style="max-width:760px">' . esc_html__( 'How your help articles are written. Claude follows it when it writes over MCP, and AI Writer follows it for help articles.', 'chatbot' ) . '</p>';
		if ( '' !== $guide['prefilled'] ) {
			/* translators: %s: date. */
			echo '<p class="description">' . esc_html( sprintf( __( 'Filled in from your Business Context on %s. Change anything you like.', 'chatbot' ), $guide['prefilled'] ) ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wpb-hc-style">';
		wp_nonce_field( 'qcld_wpbot_help_center_style' );
		echo '<input type="hidden" name="action" value="qcld_wpbot_help_center_style">';
		echo '<table class="form-table" role="presentation">';
		$fields = array(
			'audience'    => array( __( 'Audience', 'chatbot' ), __( 'Who reads your help articles, for example "Shop owners using WordPress, not developers".', 'chatbot' ), 2 ),
			'tone'        => array( __( 'Tone', 'chatbot' ), '', 3 ),
			'words_use'   => array( __( 'Words to use', 'chatbot' ), __( 'Your product names and terms, spelled the way you want them.', 'chatbot' ), 2 ),
			'words_avoid' => array( __( 'Words to avoid', 'chatbot' ), __( 'Words and claims never to use.', 'chatbot' ), 2 ),
			'paths'       => array( __( 'Settings paths', 'chatbot' ), __( 'How menu paths are written.', 'chatbot' ), 2 ),
			'screenshots' => array( __( 'Screenshots', 'chatbot' ), __( 'How to mark where a screenshot goes.', 'chatbot' ), 2 ),
		);
		foreach ( $fields as $key => $field ) {
			echo '<tr><th scope="row"><label for="hc-style-' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label></th><td><textarea id="hc-style-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" rows="' . (int) $field[2] . '" class="large-text">' . esc_textarea( (string) $guide[ $key ] ) . '</textarea>';
			if ( '' !== $field[1] ) {
				echo '<p class="description">' . esc_html( $field[1] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</table><h2>' . esc_html__( 'Article templates', 'chatbot' ) . '</h2><p class="description">' . esc_html__( 'The outline for each kind of article. Lines that start with ## are headings.', 'chatbot' ) . '</p><table class="form-table" role="presentation">';
		foreach ( Style_Guide::type_labels() as $type => $label ) {
			echo '<tr><th scope="row"><label for="hc-style-t-' . esc_attr( $type ) . '">' . esc_html( $label ) . '</label></th><td><textarea id="hc-style-t-' . esc_attr( $type ) . '" name="templates[' . esc_attr( $type ) . ']" rows="7" class="large-text code">' . esc_textarea( (string) $guide['templates'][ $type ] ) . '</textarea></td></tr>';
		}
		echo '<tr><th scope="row">' . esc_html__( 'Business Context', 'chatbot' ) . '</th><td><label><input type="checkbox" name="style_prefill" value="1"' . checked( Settings::on( 'style_prefill' ), true, false ) . '> ' . esc_html__( 'Fill in the audience and word lists from your Business Context the first time', 'chatbot' ) . '</label>';
		echo '<p class="description"><button type="submit" name="do" value="prefill" class="button-link">' . esc_html__( 'Fill in from Business Context again', 'chatbot' ) . '</button> ' . esc_html__( '(replaces the audience and word lists)', 'chatbot' ) . '</p></td></tr>';
		echo '</table>';
		submit_button( __( 'Save style guide', 'chatbot' ), 'primary', 'submit', true, array( 'value' => 'save' ) );
		echo '</form>';
	}
}
