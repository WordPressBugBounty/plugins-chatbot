<?php
/**
 * Help Center pages on the front end (HC1-07): picks the view (home, archive,
 * single, search), renders it in the chosen layout inside the theme, and loads
 * the styles, fonts and script only on Help Center pages.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Front;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;
use QuantumCloud\HelpCenter\Feedback\Votes;
use QuantumCloud\HelpCenter\Glossary\Glossary;
use QuantumCloud\HelpCenter\Permissions;
use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end routing.
 */
final class Front {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'template_include', array( self::class, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_action( 'pre_get_posts', array( self::class, 'query' ) );
	}

	/**
	 * Which Help Center view this request is, or an empty string.
	 *
	 * @return string home, archive, single, search, glossary or ''.
	 */
	public static function view() {
		if ( is_admin() || is_feed() || is_embed() ) {
			return '';
		}
		if ( Glossary::is_page() && is_post_type_archive( Post_Type::NAME ) ) {
			return 'glossary';
		}
		if ( is_search() && Post_Type::NAME === get_query_var( 'post_type' ) ) {
			return 'search';
		}
		if ( is_singular( Post_Type::NAME ) ) {
			return 'single';
		}
		if ( is_tax( array( Taxonomies::KB, Taxonomies::CATEGORY, Taxonomies::TAG ) ) ) {
			return 'archive';
		}
		if ( is_post_type_archive( Post_Type::NAME ) ) {
			return 'home';
		}
		return '';
	}

	/**
	 * Design for this request: the saved settings, or a preview for people who may change them.
	 *
	 * @return array
	 */
	public static function design() {
		$override = array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only preview for people who may change the design.
		if ( isset( $_GET['hc_preview'] ) && current_user_can( Permissions::cap( 'settings' ) ) ) {
			foreach ( array( 'layout', 'accent', 'scheme' ) as $key ) {
				if ( isset( $_GET[ 'hc_' . $key ] ) ) {
					$override[ $key ] = sanitize_text_field( wp_unslash( $_GET[ 'hc_' . $key ] ) );
				}
			}
		}
		// phpcs:enable
		return Settings::design( $override );
	}

	/**
	 * Search results: articles only; the home lists every article on one page (the archive is the home).
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public static function query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_search() && Post_Type::NAME === $query->get( 'post_type' ) ) {
			$query->set( 'posts_per_page', 20 );
		}
		if ( $query->is_tax( Taxonomies::KB ) ) {
			$query->set( 'posts_per_page', 100 );
			$query->set(
				'orderby',
				array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				)
			);
		}
	}

	/**
	 * Use the Help Center wrapper (or the theme's copy of it) on Help Center pages.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public static function template( $template ) {
		$view = self::view();
		if ( '' === $view ) {
			return $template;
		}
		$GLOBALS['qcld_wpbot_help_center_view'] = $view;
		$theme                                  = locate_template( array( 'wpbot-help-center/help-center.php' ) );
		return '' !== $theme ? $theme : self::dir() . '/templates/help-center.php';
	}

	/**
	 * Render the current view (called by the wrapper template through the global it reads).
	 *
	 * @return string
	 */
	public static function html() {
		$view = isset( $GLOBALS['qcld_wpbot_help_center_view'] ) ? (string) $GLOBALS['qcld_wpbot_help_center_view'] : self::view();
		if ( 'single' === $view ) {
			the_post();
		}
		return ( new View( self::design() ) )->render( $view );
	}

	/**
	 * Body classes.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$view = self::view();
		if ( '' !== $view ) {
			$classes[] = 'wpbot-help-center';
			$classes[] = 'wpbot-help-center--' . self::design()['layout'];
		}
		return $classes;
	}

	/**
	 * Styles, fonts and script on Help Center pages only.
	 *
	 * @return void
	 */
	public static function assets() {
		if ( '' === self::view() ) {
			return;
		}
		self::enqueue();
	}

	/**
	 * Load the Help Center styles and script (Help Center pages, and embeds on any page).
	 *
	 * @return void
	 */
	public static function enqueue() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$file    = self::dir() . '/help-center.php';
		$version = defined( 'QCLD_WPBOT_HELP_CENTER_VERSION' ) ? QCLD_WPBOT_HELP_CENTER_VERSION : '0';
		wp_enqueue_style( 'wpbot-help-front', plugins_url( 'assets/front.css', $file ), array(), $version );
		wp_enqueue_style( 'wpbot-help-blocks' );
		wp_enqueue_script(
			'wpbot-help-front',
			plugins_url( 'assets/front.js', $file ),
			array(),
			$version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script(
			'wpbot-help-front',
			'window.qcldWpbotHelpCenter = ' . wp_json_encode(
				array(
					'rest'      => esc_url_raw( rest_url( 'wp/v2/help-articles' ) ),
					'searchUrl' => Urls::home(),
					'api'       => esc_url_raw( rest_url( Votes::NS ) ),
					'post'      => is_singular( Post_Type::NAME ) ? get_queried_object_id() : 0,
					'i18n'      => array(
						'thanks'  => __( 'Thanks for letting us know.', 'chatbot' ),
						'sent'    => __( 'Thanks. Your feedback helps us improve this article.', 'chatbot' ),
						'failed'  => __( 'Sorry, that didn’t go through. Please try again later.', 'chatbot' ),
						'whyHint' => __( 'Please choose a reason or tell us what you were looking for.', 'chatbot' ),
						/* translators: %s: what the visitor typed. */
						'ask'     => __( 'Ask the assistant: “%s”', 'chatbot' ),
						/* translators: %s: what the visitor typed. */
						'none'    => __( 'No article matches “%s” yet.', 'chatbot' ),
						/* translators: %s: what the visitor typed. */
						'all'     => __( 'See all results for “%s”', 'chatbot' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Module folder.
	 *
	 * @return string
	 */
	private static function dir() {
		return defined( 'QCLD_WPBOT_HELP_CENTER_DIR' ) ? QCLD_WPBOT_HELP_CENTER_DIR : dirname( __DIR__, 2 );
	}
}
