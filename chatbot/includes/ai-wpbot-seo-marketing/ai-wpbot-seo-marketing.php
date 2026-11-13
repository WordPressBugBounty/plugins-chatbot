<?php
/**
 * Plugin Name: WPBot AI SEO & Marketing
 * Plugin URI: https://www.wpbot.pro/
 * Description: AI Content Generator and AI SEO Analyzer. AI-powered blog content creation with SEO optimization and one-click WordPress publishing
 *
 * .
 *
 * @package QCLD_Wpbot_Ai_Seo
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The module's other files use PHP 7.4 syntax (typed properties, arrow functions). On older
// PHP, skip them instead of breaking the whole site, and tell admins why the AI pages are missing.
// Keep THIS file parseable on PHP 5.6: no return types, scalar type hints or other 7.x syntax
// here, or the guard below never gets to run. No file in this module may use PHP 8.0+ syntax
// (union types, match, nullsafe, named arguments, constructor promotion) while the guard is 7.4.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'WPBot AI Content Generator and AI SEO Analyzer need PHP 7.4 or newer.', 'chatbot' ) . '</p></div>';
			}
		}
	);
	return;
}

/**
 * Plugin constants.
 */
if ( ! defined( 'QCLD_WPBOT_AI_SEO_VERSION' ) ) {
	define( 'QCLD_WPBOT_AI_SEO_VERSION', '1.2.8' );
}
if ( ! defined( 'QCLD_WPBOT_AI_SEO_PLUGIN_DIR' ) ) {
	define( 'QCLD_WPBOT_AI_SEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'QCLD_WPBOT_AI_SEO_PLUGIN_URL' ) ) {
	define( 'QCLD_WPBOT_AI_SEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'QCLD_WPBOT_AI_SEO_PLUGIN_BASENAME' ) ) {
	define( 'QCLD_WPBOT_AI_SEO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Schema versions.
if ( ! defined( 'QCLD_WPBOT_AI_SEO_DB_VERSION' ) ) {
	define( 'QCLD_WPBOT_AI_SEO_DB_VERSION', '1.2.6' );
}
if ( ! defined( 'QCLD_WPBOT_AI_SEO_CONTENT_DB_VERSION' ) ) {
	define( 'QCLD_WPBOT_AI_SEO_CONTENT_DB_VERSION', '1.7.0' );
}
if ( ! defined( 'QCLD_WPBOT_AI_SEO_SEO_DB_VERSION' ) ) {
	define( 'QCLD_WPBOT_AI_SEO_SEO_DB_VERSION', '1.1.0' );
}

/**
 * Core classes (loaded explicitly, no autoloader).
 */
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/qcld-ai-seo-encryption.php';
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/qcld-ai-seo-database.php';
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/qcld-ai-seo-module.php';
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/qcld-ai-seo-module-manager.php';
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/qcld-ai-seo-ai-provider.php';
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/qcld-ai-seo-usage-tracker.php';
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/qcld-ai-seo-ajax.php';
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/qcld-ai-seo-admin.php';
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/qcld-ai-seo-plugin.php';

/**
 * Helper functions (globally available).
 */
require_once QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/helpers.php';

/**
 * Initialize the plugin.
 *
 * @return Qcld_Wpbot_Ai_Seo_Plugin
 */
if ( ! function_exists( 'qcld_wpbot_ai_seo' ) ) {
	function qcld_wpbot_ai_seo() {
		return Qcld_Wpbot_Ai_Seo_Plugin::instance();
	}
}

/**
 * Admin menu: "AI Content Generator" and "AI SEO Analyzer" under the WPBot menu.
 */
if ( ! function_exists( 'qcld_wpbot_ai_seo_has_wpbot_menu' ) ) {
	/**
	 * Whether WPBot's top-level menu has been registered.
	 *
	 * @return bool
	 */
	function qcld_wpbot_ai_seo_has_wpbot_menu() {
		global $admin_page_hooks;
		return ! empty( $admin_page_hooks['wpbot_openAi'] ) || ! empty( $admin_page_hooks['wpbot-panel'] );
	}
}

if ( ! function_exists( 'qcld_wpbot_ai_seo_admin_menu' ) ) {
	/**
	 * Register the two pages.
	 *
	 * With WPBot: submenus under WPBot menu.
	 * Without it: our own top-level "WPBot AI SEO" menu with both pages.
	 */
	function qcld_wpbot_ai_seo_admin_menu() {
		global $admin_page_hooks, $submenu;
		if ( qcld_wpbot_ai_seo_has_wpbot_menu() ) {
			$parent            = ! empty( $admin_page_hooks['wpbot_openAi'] ) ? 'wpbot_openAi' : 'wpbot-panel';
			$content_position  = null;
			$analyzer_position = null;
		} else {
			$parent            = 'wpbot-ai-content-generator';
			$content_position  = null;
			$analyzer_position = null;
			add_menu_page(
				esc_html__( 'WPBot AI SEO', 'chatbot' ),
				esc_html__( 'WPBot AI SEO', 'chatbot' ),
				'manage_options',
				$parent,
				'qcld_wpbot_ai_seo_content_generator_page',
				'dashicons-chart-line',
				58
			);
		}

		// Avoid duplicate submenu registration if already registered by WPBot
		$already_registered = false;
		if ( ! empty( $submenu[ $parent ] ) && is_array( $submenu[ $parent ] ) ) {
			foreach ( $submenu[ $parent ] as $item ) {
				if ( isset( $item[2] ) && $item[2] === 'wpbot-ai-content-generator' ) {
					$already_registered = true;
					break;
				}
			}
		}

		if ( ! $already_registered ) {
			add_submenu_page(
				$parent,
				esc_html__( 'AI Content Generator', 'chatbot' ),
				esc_html__( 'AI Content Generator', 'chatbot' ),
				'manage_options',
				'wpbot-ai-content-generator',
				'qcld_wpbot_ai_seo_content_generator_page',
				$content_position
			);
		}

		/*add_submenu_page(
			$parent,
			esc_html__( 'AI SEO Analyzer', 'chatbot' ),
			esc_html__( 'AI SEO Analyzer', 'chatbot' ),
			'manage_options',
			'wpbot-ai-seo-analyzer',
			'qcld_wpbot_ai_seo_seo_analyzer_page',
			$analyzer_position
		);*/
		
	}
	// After WPBot registers its top-level "wpbot-panel" menu.
	add_action( 'admin_menu', 'qcld_wpbot_ai_seo_admin_menu', 20 );
}

if ( ! function_exists( 'qcld_wpbot_ai_seo_content_generator_page' ) ) {
	/**
	 * AI Content Generator page.
	 */
	function qcld_wpbot_ai_seo_content_generator_page() {
		qcld_wpbot_ai_seo_render_app_page(
			array(
				'wrap_class' => 'qcld-wpbot-ai-content-generator-page',
				'icon'       => 'dashicons-edit-page',
				'title'      => __( 'AI Content Generator', 'chatbot' ),
				'text'       => __( 'Write SEO-ready blog posts with AI and publish them to WordPress in one click.', 'chatbot' ),
			)
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_ai_seo_seo_analyzer_page' ) ) {
	/**
	 * AI SEO Analyzer page.
	 */
	function qcld_wpbot_ai_seo_seo_analyzer_page() {
		qcld_wpbot_ai_seo_render_app_page(
			array(
				'wrap_class' => 'qcld-wpbot-ai-seo-analyzer-page',
				'icon'       => 'dashicons-chart-line',
				'title'      => __( 'AI SEO Analyzer', 'chatbot' ),
				'text'       => __( 'Research keywords, audit your pages, plan content and track rankings with AI.', 'chatbot' ),
			)
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_ai_seo_render_app_page' ) ) {
	/**
	 * Shared page shell: WPBot header, the jQuery app mount point and the Upgrade sidebar.
	 *
	 * @param array $args wrap_class, icon, title, text.
	 */
	function qcld_wpbot_ai_seo_render_app_page( $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'chatbot' ) );
		}

		$logo    = defined( 'QCLD_wpCHATBOT_IMG_URL' ) ? QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' : '';
		$version = defined( 'QCLD_wpCHATBOT_VERSION' ) ? QCLD_wpCHATBOT_VERSION : QCLD_WPBOT_AI_SEO_VERSION;
		$img_url  = defined( 'QCLD_wpCHATBOT_IMG_URL' ) ? QCLD_wpCHATBOT_IMG_URL : '';
		$show_pro = ! qcld_wpbot_ai_seo_has_pro();
		?>
		<div class="wrap">
			<?php /* Kept for WordPress notice placement; hidden (the WPBot header card is the page title). */ ?>
			<h1 class="screen-reader-text"><?php echo esc_html( $args['title'] ); ?></h1>
		<div class="qcld-main-wrapper <?php echo esc_attr( $args['wrap_class'] ); ?>">
			<div class="qcld-wp-chatbot-wrap-header-aisection">
				<div class="qcld-wp-chatbot-wrap-header">
					<div class="qcld-wp-chatbot-wrap-header-logo">
						<a href="#" class="qcld-wp-chatbot-wrap-site__logo"><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="WPBot"> <?php endif; ?><?php esc_html_e( 'WPBot Control Panel', 'chatbot' ); ?></a>
						<p><strong><?php esc_html_e( 'Core Version:', 'chatbot' ); ?></strong> v<?php echo esc_html( $version ); ?></p>
					</div>
					<ul class="qcld-wp-chatbot-wrap-version-wrapper">
					</ul>
				</div>
			</div>

			<?php /* Three columns: left menu card + content card (both drawn by the app), then the Upgrade sidebar. */ ?>
			<div class="qcld_wpbot_ai_seo-studio qcld_wpbot_ai_seo-studio--split<?php echo $show_pro ? ' has-pro-sidebar' : ''; ?>">
				<div id="qcld_wpbot_ai_seo_app" class="qcld_wpbot_ai_seo-dashboard-wrap"></div>

				<?php if ( $show_pro ) : ?>
				<aside class="wp-chatbot-admin-upgrade-pro-sidebar qcld_wpbot_ai_seo-pro-sidebar">
					<div class="wp-chatbot-admingradient-color">
						<?php if ( $img_url ) : ?><img class="wp-chatbot-admin-banner" src="<?php echo esc_url( $img_url . '/template-sample.webp' ); ?>" alt=""><?php endif; ?>
						<h3 class="wp-chatbot-admincart-title"><?php esc_html_e( 'Upgrade To', 'chatbot' ); ?> <span><?php esc_html_e( 'Pro', 'chatbot' ); ?></span></h3>
						<?php
						if ( class_exists( 'QCLD_WPBot_Free_Pro_Catalogue' ) ) {
							QCLD_WPBot_Free_Pro_Catalogue::print_overview( array( 'heading' => false, 'compact' => true ) );
						}
						?>
					</div>
				</aside>
				<?php endif; ?>
			</div>

			<?php if ( $show_pro ) : ?>
			<?php /* "Go Premium" button; the app moves it under its left menu. */ ?>
			<template id="qcld_wpbot_ai_seo_nav_footer">
				<div class="qcld-goPreemium qcld_wpbot_ai_seo-go-premium">
					<a href="<?php echo esc_url( 'https://www.wpbot.pro/' ); ?>" target="_blank" rel="noopener noreferrer"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none" aria-hidden="true">
						<path d="M19.6799 17.28H4.31988C3.52596 17.28 2.87988 17.9261 2.87988 18.72C2.87988 19.5139 3.52596 20.16 4.31988 20.16H19.6799C20.4738 20.16 21.1199 19.5139 21.1199 18.72C21.1199 17.9261 20.4738 17.28 19.6799 17.28Z" fill="#FF9900"></path>
						<path d="M22.08 2.88C21.0211 2.88 20.16 3.74114 20.16 4.8C20.16 5.51137 20.5536 6.1267 21.1305 6.45886C20.0198 9.08925 18.287 10.703 16.6675 10.5571C14.8665 10.4102 13.3977 8.28094 12.5875 4.71839C13.6262 4.45533 14.4 3.51933 14.4 2.4C14.4 1.07616 13.3238 0 12 0C10.6761 0 9.59995 1.07616 9.59995 2.4C9.59995 3.51938 10.3737 4.45538 11.4124 4.71839C10.6022 8.28094 9.13336 10.4102 7.33242 10.5571C5.71964 10.703 3.97913 9.08925 2.86941 6.45886C3.44634 6.1267 3.83995 5.51133 3.83995 4.8C3.83995 3.74114 2.97881 2.88 1.91995 2.88C0.861141 2.88 0 3.74114 0 4.8C0 5.78498 0.748781 6.58945 1.70494 6.69886L3.55392 16.32H20.4461L22.295 6.69886C23.2512 6.58945 24 5.78498 24 4.8C24 3.74114 23.1389 2.88 22.08 2.88Z" fill="url(#qcld_wpbot_ai_seo_crown)"></path>
						<defs><linearGradient id="qcld_wpbot_ai_seo_crown" x1="12" y1="0" x2="12" y2="16.32" gradientUnits="userSpaceOnUse"><stop stop-color="#FFC045"></stop><stop offset="1" stop-color="#FF9900"></stop></linearGradient></defs>
					</svg></span><?php esc_html_e( 'Go Premium', 'chatbot' ); ?></a>
				</div>
			</template>
			<?php endif; ?>
		</div>
		</div>
		<?php
	}
}

/**
 * Clear this module's scheduled jobs when WPBot is deactivated.
 * The modules schedule them again on init when WPBot is reactivated.
 */
if ( ! function_exists( 'qcld_wpbot_ai_seo_deactivate' ) ) {
	function qcld_wpbot_ai_seo_deactivate() {
		$hooks = array(
			'qcld_wpbot_ai_seo_daily_cleanup',
			'qcld_wpbot_ai_seo_content_sync_scheduled_articles',
			'qcld_wpbot_ai_seo_seo_rank_check',
			'qcld_wpbot_ai_seo_seo_automation_process',
		);
		
		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
	add_action( 'plugins_loaded', 'qcld_wpbot_ai_seo_register_deactivation' );
}

if ( ! function_exists( 'qcld_wpbot_ai_seo_register_deactivation' ) ) {
	function qcld_wpbot_ai_seo_register_deactivation() {
		$qcld_wpbot_ai_seo_host_file = dirname( dirname( __DIR__ ) ) . '/qcld-wpwbot.php';
		if ( file_exists( $qcld_wpbot_ai_seo_host_file ) ) {
			register_deactivation_hook( $qcld_wpbot_ai_seo_host_file, 'qcld_wpbot_ai_seo_deactivate' );
		}
	}
}

// Boot the plugin.
qcld_wpbot_ai_seo();