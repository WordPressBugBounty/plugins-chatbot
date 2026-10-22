<?php
/**
 * Admin handler for the WPBot AI pages.
 *
 * The pages are registered by WPBot (wpbot-ai-content-generator, wpbot-ai-seo-analyzer);
 * this class loads the apps, the WPBot skin and the data the apps need.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qcld_Wpbot_Ai_Seo_Admin {

	/**
	 * Admin page slug registered by WPBot (AI Content Generator).
	 */
	public const PAGE_SLUG = 'wpbot-ai-content-generator';

	/**
	 * Admin page slug registered by WPBot (AI SEO Analyzer).
	 */
	public const SEO_PAGE_SLUG = 'wpbot-ai-seo-analyzer';

	/**
	 * Module Manager.
	 *
	 * @var Qcld_Wpbot_Ai_Seo_Module_Manager
	 */
	private Qcld_Wpbot_Ai_Seo_Module_Manager $modules;

	/**
	 * Constructor.
	 *
	 * @param Qcld_Wpbot_Ai_Seo_Module_Manager $modules Module manager instance.
	 */
	public function __construct( Qcld_Wpbot_Ai_Seo_Module_Manager $modules ) {
		$this->modules = $modules;

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'redirect_old_slug' ) );
		add_action( 'admin_page_access_denied', array( $this, 'redirect_old_slug' ) );
		add_filter( 'admin_body_class', array( $this, 'embedded_body_class' ) );
		add_action( 'in_admin_header', array( $this, 'hide_foreign_notices' ), 1000 );
	}

	/**
	 * Current admin page slug ('' outside admin.php?page=...).
	 *
	 * @return string
	 */
	private function current_page(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug check.
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}

	/**
	 * Whether the current admin request is one of the app pages
	 * (AI Content Generator or AI SEO Analyzer).
	 *
	 * @return bool
	 */
	private function is_app_page(): bool {
		return in_array( $this->current_page(), array( self::PAGE_SLUG, self::SEO_PAGE_SLUG ), true );
	}

	/**
	 * Send old page slugs to the new one (keeps bookmarks working).
	 */
	public function redirect_old_slug(): void {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'ai-content-generator', 'wpbot-ai-marketing-expert-content' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			exit;
		}
	}

	/**
	 * Enqueue assets on the WPBot AI pages.
	 *
	 * Both pages (AI Content Generator and AI SEO Analyzer) are jQuery apps
	 * that talk to admin-ajax.php through Qcld_Wpbot_Ai_Seo_Ajax.
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_app_page() ) {
			return;
		}

		// WPBot admin styles (header, wrapper, brand tokens) so the page matches WPBot screens.
		$skin_deps = array();
		if ( defined( 'QCLD_wpCHATBOT_PLUGIN_URL' ) ) {
			if ( ! wp_style_is( 'qlcd-wp-chatbot-admin-style', 'enqueued' ) ) {
				wp_enqueue_style( 'qlcd-wp-chatbot-admin-style', QCLD_wpCHATBOT_PLUGIN_URL . 'css/admin-style.css', array(), QCLD_wpCHATBOT_VERSION, 'screen' );
			}
			$skin_deps[] = 'qlcd-wp-chatbot-admin-style';
		}

		$is_seo = self::SEO_PAGE_SLUG === $this->current_page();
		$this->enqueue_app( $skin_deps, $is_seo );

		// Fade-in animation used by the app layout.
		wp_enqueue_style( 'animate-css', QCLD_WPBOT_AI_SEO_PLUGIN_URL . 'assets/css/animate.min.css', array(), qcld_wpbot_ai_seo_asset_version( '4.1.1' ) );

		if ( ! $is_seo ) {
			// Media library (featured images) and TinyMCE (article editor).
			wp_enqueue_media();
			wp_enqueue_editor();
		}
	}

	/**
	 * Version string for a file in this module (changes when the file changes).
	 *
	 * @param string $relative Path relative to the module folder.
	 * @return string
	 */
	private function file_version( string $relative ): string {
		$file = QCLD_WPBOT_AI_SEO_PLUGIN_DIR . $relative;
		return qcld_wpbot_ai_seo_asset_version( file_exists( $file ) ? QCLD_WPBOT_AI_SEO_VERSION . '.' . filemtime( $file ) : QCLD_WPBOT_AI_SEO_VERSION );
	}

	/**
	 * Enqueue one of the jQuery apps plus the shared core and styles.
	 *
	 * @param array $skin_deps Style handles the skin depends on.
	 * @param bool  $is_seo    True for the AI SEO Analyzer page.
	 */
	private function enqueue_app( array $skin_deps, bool $is_seo ): void {
		$url = QCLD_WPBOT_AI_SEO_PLUGIN_URL;

		wp_enqueue_style( 'qcld_wpbot_ai_seo_app', $url . 'assets/css/qcld-wpbot-ai-seo-app.css', array( 'dashicons' ), $this->file_version( 'assets/css/qcld-wpbot-ai-seo-app.css' ) );
		$skin_deps[] = 'qcld_wpbot_ai_seo_app';
		if ( $is_seo ) {
			wp_enqueue_style( 'qcld_wpbot_ai_seo-seo', $url . 'assets/css/qcld-wpbot-ai-seo-seo.css', array( 'qcld_wpbot_ai_seo_app' ), $this->file_version( 'assets/css/qcld-wpbot-ai-seo-seo.css' ) );
			$skin_deps[] = 'qcld_wpbot_ai_seo-seo';
		}
		wp_enqueue_style( 'qcld_wpbot_ai_seo-wpbot-embed', $url . 'assets/css/wpbot-embed.css', $skin_deps, $this->file_version( 'assets/css/wpbot-embed.css' ) );

		$scripts = array( 'qcld_wpbot_ai_seo-core' => array( 'qcld-wpbot-ai-seo-core.js', array( 'jquery', 'wp-i18n' ) ) );
		if ( $is_seo ) {
			$scripts['qcld_wpbot_ai_seo-seo']            = array( 'qcld-wpbot-ai-seo-seo.js', array( 'qcld_wpbot_ai_seo-core' ) );
			$scripts['qcld_wpbot_ai_seo-seo-research']   = array( 'qcld-wpbot-ai-seo-seo-research.js', array( 'qcld_wpbot_ai_seo-seo' ) );
			$scripts['qcld_wpbot_ai_seo-seo-automation'] = array( 'qcld-wpbot-ai-seo-seo-automation.js', array( 'qcld_wpbot_ai_seo-seo' ) );
			$scripts['qcld_wpbot_ai_seo-seo-app']        = array( 'qcld-wpbot-ai-seo-seo-app.js', array( 'qcld_wpbot_ai_seo-seo-research', 'qcld_wpbot_ai_seo-seo-automation' ) );
		} else {
			$scripts['qcld_wpbot_ai_seo-content']        = array( 'qcld-wpbot-ai-seo-content.js', array( 'qcld_wpbot_ai_seo-core' ) );
			$scripts['qcld_wpbot_ai_seo-content-editor'] = array( 'qcld-wpbot-ai-seo-content-editor.js', array( 'qcld_wpbot_ai_seo-content' ) );
			$scripts['qcld_wpbot_ai_seo-content-app']    = array( 'qcld-wpbot-ai-seo-content-app.js', array( 'qcld_wpbot_ai_seo-content-editor' ) );
		}

		foreach ( $scripts as $handle => $script ) {
			wp_enqueue_script( $handle, $url . 'assets/js/' . $script[0], $script[1], $this->file_version( 'assets/js/' . $script[0] ), true );
			wp_set_script_translations( $handle, 'chatbot' );
		}

		wp_localize_script(
			'qcld_wpbot_ai_seo-core',
			'qcldWpbotAiSeoData',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( Qcld_Wpbot_Ai_Seo_Ajax::NONCE ),
				'adminUrl'       => admin_url(),
				'siteUrl'        => home_url(),
				'contentPageUrl' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
				'seoPageUrl'     => admin_url( 'admin.php?page=' . self::SEO_PAGE_SLUG ),
				'aiSettingsUrl'  => admin_url( 'admin.php?page=wpbot_openAi' ),
				'version'        => QCLD_WPBOT_AI_SEO_VERSION,
				'hasPro'         => qcld_wpbot_ai_seo_has_pro(),
				'proUrl'         => apply_filters( 'qcld_wpbot_ai_seo_pro_url', 'https://www.wpbot.pro/pricing/' ),
				'modules'        => $this->modules->get_dashboard_configs(),
				'tones'          => qcld_wpbot_ai_seo_content_tones(),
				'aiConfigured'   => ! empty( Qcld_Wpbot_Ai_Seo_Ai_Provider::get_active_model( 'text' )['api_key'] ),
				'aiPaused'       => Qcld_Wpbot_Ai_Seo_Ai_Provider::get_paused_connections(),
			)
		);

		/**
		 * Fires after the app scripts are enqueued, before the app boots.
		 * Add-ons enqueue their own script here (depend on 'qcld_wpbot_ai_seo-core')
		 * and register features on QcldWpbotAiSeo.ext.
		 *
		 * @param string $app 'content' or 'seo'.
		 */
		do_action( 'qcld_wpbot_ai_seo_enqueue_app', $is_seo ? 'seo' : 'content' );
	}

	/**
	 * Body class so the WPBot skin only applies on this page.
	 *
	 * @param string $classes Space-separated admin body classes.
	 * @return string
	 */
	public function embedded_body_class( $classes ) {
		if ( $this->is_app_page() ) {
			$classes .= ' qcld_wpbot_ai_seo-embedded';
			if ( self::SEO_PAGE_SLUG === $this->current_page() ) {
				$classes .= ' qcld_wpbot_ai_seo-embedded--seo';
			}
		}
		return $classes;
	}

	/**
	 * Keep third-party admin notices out of the app screen.
	 */
	public function hide_foreign_notices(): void {
		if ( ! $this->is_app_page() ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}
}
