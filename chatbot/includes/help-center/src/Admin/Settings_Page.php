<?php
/**
 * Help Center → Settings: the public address (owner decision H11), with the
 * clash check. Changing it keeps the old address in a list (the redirects of
 * HC1-12 read it) and flushes rewrite rules once on the next request.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Address;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Glossary\Glossary;
use QuantumCloud\HelpCenter\Permissions;
use QuantumCloud\HelpCenter\Rewrite_Flush;
use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
final class Settings_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'wpbot-help-center-settings';

	/**
	 * Earlier addresses (newest last), for redirects.
	 */
	const PREVIOUS = 'qcld_wpbot_help_center_previous_bases';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_qcld_wpbot_help_center_settings', array( self::class, 'save' ) );
	}

	/**
	 * Add the page under Help Center.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . Post_Type::NAME, __( 'Help Center settings', 'chatbot' ), __( 'Settings', 'chatbot' ), Permissions::cap( 'settings' ), self::SLUG, array( self::class, 'render' ), 90 );
	}

	/**
	 * Check a new address against the current one.
	 *
	 * @param string   $raw     Submitted value.
	 * @param string   $current Current address.
	 * @param callable $check   Clash check (default Address::check()).
	 * @return array{0:string,1:string[]} Clean address and problem codes.
	 */
	public static function validate( $raw, $current, $check = null ) {
		$base = Address::sanitize( (string) $raw );
		if ( '' !== $base && $base === $current ) {
			return array( $base, array() );
		}
		$check = is_callable( $check ) ? $check : array( Address::class, 'check' );
		return array( $base, (array) call_user_func( $check, $base ) );
	}

	/**
	 * Save the form.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( Permissions::cap( 'settings' ) ) ) {
			wp_die( esc_html__( 'You are not allowed to change the Help Center settings.', 'chatbot' ), 403 );
		}
		check_admin_referer( 'qcld_wpbot_help_center_settings' );
		$current                 = Settings::base();
		$raw                     = isset( $_POST['base'] ) ? sanitize_text_field( wp_unslash( $_POST['base'] ) ) : '';
		list( $base, $problems ) = self::validate( $raw, $current );
		$back                    = admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG );
		if ( array() !== $problems ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'hc_problems' => implode( ',', $problems ),
						'hc_base'     => rawurlencode( $base ),
					),
					$back
				)
			);
			exit;
		}
		$settings                      = Settings::all();
		$settings['glossary_popovers'] = empty( $_POST['glossary_popovers'] ) ? '0' : '1';
		if ( $base !== $current ) {
			$settings['base'] = $base;
			self::remember( $current );
			update_option( Rewrite_Flush::FLAG, 1, false );
		}
		update_option( Settings::OPTION, $settings );
		wp_safe_redirect( add_query_arg( 'updated', '1', $back ) );
		exit;
	}

	/**
	 * Keep an earlier address (at most 10).
	 *
	 * @param string $base Address.
	 * @return void
	 */
	public static function remember( $base ) {
		$list   = get_option( self::PREVIOUS, array() );
		$list   = is_array( $list ) ? array_values( array_diff( $list, array( $base ) ) ) : array();
		$list[] = $base;
		update_option( self::PREVIOUS, array_slice( $list, -10 ), false );
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Messages after a redirect.
		$problems = isset( $_GET['hc_problems'] ) ? array_filter( explode( ',', sanitize_text_field( wp_unslash( $_GET['hc_problems'] ) ) ) ) : array();
		$value    = isset( $_GET['hc_base'] ) ? Address::sanitize( rawurldecode( sanitize_text_field( wp_unslash( $_GET['hc_base'] ) ) ) ) : Settings::base();
		$updated  = isset( $_GET['updated'] );
		$tab      = isset( $_GET['tab'] ) && 'design' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ? 'design' : 'general';
		// phpcs:enable
		echo '<div class="wrap wpbot-help-settings"><h1>' . esc_html__( 'Help Center settings', 'chatbot' ) . '</h1>';
		$base_url = admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG );
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Help Center settings', 'chatbot' ) . '">';
		foreach ( array(
			'general' => __( 'General', 'chatbot' ),
			'design'  => __( 'Design', 'chatbot' ),
		) as $id => $label ) {
			printf( '<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>', esc_url( 'general' === $id ? $base_url : Design_Page::url() ), $id === $tab ? ' nav-tab-active' : '', $id === $tab ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';
		if ( 'design' === $tab ) {
			Design_Page::render();
			echo '</div>';
			return;
		}
		if ( $updated ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'chatbot' ) . '</p></div>';
		}
		foreach ( $problems as $code ) {
			$message = Address::message( $code );
			if ( '' !== $message ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
			}
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'qcld_wpbot_help_center_settings' );
		echo '<input type="hidden" name="action" value="qcld_wpbot_help_center_settings">';
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="hc-base">' . esc_html__( 'Address', 'chatbot' ) . '</label></th><td>';
		echo '<code>' . esc_html( trailingslashit( home_url() ) ) . '</code><input type="text" id="hc-base" name="base" class="regular-text code" value="' . esc_attr( $value ) . '" aria-describedby="hc-base-help"><code>/</code>';
		echo '<p class="description" id="hc-base-help">' . esc_html__( 'Where the help center lives, for example help or docs.', 'chatbot' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Glossary', 'chatbot' ) . '</th><td><label><input type="checkbox" name="glossary_popovers" value="1"' . checked( Glossary::popovers_on(), true, false ) . '> ' . esc_html__( 'Explain glossary terms in articles (the first use of each term shows its definition)', 'chatbot' ) . '</label>';
		echo '<p class="description"><a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . Glossary::TAXONOMY . '&post_type=' . Post_Type::NAME ) ) . '">' . esc_html__( 'Edit the glossary', 'chatbot' ) . '</a></p></td></tr></table>';
		submit_button();
		echo '</form>';
		self::render_shortcodes();
		echo '</div>';
	}

	/**
	 * Shortcodes that show the Help Center, or one part of it, on any page.
	 *
	 * @return array<string,string> Shortcode => what it shows.
	 */
	public static function shortcodes() {
		return array(
			'[wpbot_help_center]'                    => __( 'The whole Help Center home: title, search, knowledge bases, topics with their top articles and the most read articles.', 'chatbot' ),
			'[wpbot_help_search]'                    => __( 'The search box.', 'chatbot' ),
			'[wpbot_help_kbs]'                       => __( 'Knowledge bases as cards.', 'chatbot' ),
			'[wpbot_help_topics articles="3"]'       => __( 'Topics (categories) with their top articles.', 'chatbot' ),
			'[wpbot_help_articles category="billing" limit="10"]' => __( 'A list of articles from a category, knowledge base or tag (slugs or ids, comma-separated). orderby: order, recent, title or popular.', 'chatbot' ),
			'[wpbot_help_popular limit="5"]'         => __( 'The most read articles.', 'chatbot' ),
			'[wpbot_help_glossary]'                  => __( 'The glossary, A to Z.', 'chatbot' ),
			'[wpbot_help_launcher label="Get help"]' => __( 'A button that opens the chatbot.', 'chatbot' ),
		);
	}

	/**
	 * The shortcode box.
	 *
	 * @return void
	 */
	private static function render_shortcodes() {
		echo '<h2 id="hc-shortcodes">' . esc_html__( 'Show the Help Center on any page', 'chatbot' ) . '</h2>';
		echo '<p>' . sprintf(
			/* translators: %s: the Help Center address, as a link. */
			esc_html__( 'Your Help Center has its own address, %s. To show it inside another page, paste a shortcode into that page (a Shortcode block in the block editor). The same parts are blocks in the block editor and widgets in Elementor, under "Help Center".', 'chatbot' ),
			'<a href="' . esc_url( \QuantumCloud\HelpCenter\Front\Urls::home() ) . '">' . esc_html( \QuantumCloud\HelpCenter\Front\Urls::home() ) . '</a>'
		) . '</p>';
		echo '<table class="widefat striped wpbot-help-shortcodes"><thead><tr><th scope="col">' . esc_html__( 'Shortcode', 'chatbot' ) . '</th><th scope="col">' . esc_html__( 'Shows', 'chatbot' ) . '</th></tr></thead><tbody>';
		foreach ( self::shortcodes() as $code => $what ) {
			echo '<tr><td><input type="text" readonly class="code" value="' . esc_attr( $code ) . '" aria-label="' . esc_attr__( 'Shortcode', 'chatbot' ) . '" onfocus="this.select()" size="' . esc_attr( (string) max( 24, strlen( $code ) + 2 ) ) . '"> <button type="button" class="button button-small" data-copy="' . esc_attr( $code ) . '">' . esc_html__( 'Copy', 'chatbot' ) . '</button></td><td>' . esc_html( $what ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<style>.wpbot-help-shortcodes td{vertical-align:middle}.wpbot-help-shortcodes input.code{max-width:100%}</style>';
		echo '<script>document.addEventListener("click",function(e){var b=e.target.closest(".wpbot-help-shortcodes [data-copy]");if(!b){return;}var t=b.getAttribute("data-copy"),d=b.textContent;var done=function(){b.textContent=' . wp_json_encode( __( 'Copied', 'chatbot' ) ) . ';setTimeout(function(){b.textContent=d;},1500);};if(navigator.clipboard){navigator.clipboard.writeText(t).then(done);}else{var i=b.previousElementSibling;i.select();document.execCommand("copy");done();}});</script>';
	}
}
