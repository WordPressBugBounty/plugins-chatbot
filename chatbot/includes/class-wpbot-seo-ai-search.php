<?php
/**
 * SEO & AI Search in WPBot Free: loads the bundled WPBot Core
 * (includes/wpbot-core/) and the SEO & AI Search core module
 * (includes/seo-ai-search/), and registers WPBot Free as their host.
 *
 * Both folders are synced copies of their own repositories
 * (npm run sync:seo-ai-search); never edit them here. Everything WPBot
 * Free specific lives in the two adapters loaded below. WPBot Free bundles
 * no Pro module: WPBot Pro is standalone and ships its own copies.
 *
 * Replaces the old SEO Analyzer of the AI SEO & Marketing module. That
 * module still loads for the AI Content Generator; only its SEO Analyzer
 * leftovers are retired here.
 *
 * Loaded from qcld-wpwbot.php:
 *     require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/class-wpbot-seo-ai-search.php';
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) || ! file_exists( __DIR__ . '/wpbot-core/wpbot-core.php' ) || ! file_exists( __DIR__ . '/seo-ai-search/seo-ai-search.php' ) ) {
	return;
}

// The loaders inside pick the newest bundled copy of each module, so another
// WPBot plugin with the same modules never loads them twice.
require_once __DIR__ . '/wpbot-core/wpbot-core.php';
require_once __DIR__ . '/seo-ai-search/seo-ai-search.php';

if ( ! function_exists( 'qcld_wpbot_free_core_register' ) ) {
	/**
	 * Register WPBot Free as the WPBot Core host.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_core_register() {
		require_once __DIR__ . '/class-wpbot-free-core-adapter.php';
		\QuantumCloud\WPBotCore\Core::instance()->register_host( QCLD_WPBot_Free_Core_Adapter::instance() );
	}
	add_action( 'qcld_wpbot_core/loaded', 'qcld_wpbot_free_core_register' );
}

if ( ! function_exists( 'qcld_wpbot_free_seo_register' ) ) {
	/**
	 * Register WPBot Free as the SEO & AI Search host.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_seo_register() {
		require_once __DIR__ . '/class-wpbot-free-core-adapter.php';
		require_once __DIR__ . '/class-wpbot-free-seo-ai-search-adapter.php';
		\QuantumCloud\SeoAiSearch\Module::instance()->register_host( new QCLD_WPBot_Free_Seo_Ai_Search_Adapter() );
	}
	add_action( 'qcld_wpbot_seo/loaded', 'qcld_wpbot_free_seo_register' );
}

if ( ! function_exists( 'qcld_wpbot_free_context_notice_pages' ) ) {
	/**
	 * The Business Context fill-in notice shows only on screens that use
	 * Business Context. In WPBot Free that is SEO & AI Search alone: AI
	 * Marketing here does not read it, and AI Writer and social posts are not
	 * bundled. (Free's code stops when WPBot Pro is installed, so Pro keeps
	 * WPBot Core's own list.)
	 *
	 * @param string[] $pages Page slugs.
	 * @return string[]
	 */
	function qcld_wpbot_free_context_notice_pages( $pages ) {
		return array_values( array_intersect( (array) $pages, array( 'wpbot-seo-ai-search' ) ) );
	}
	add_filter( 'qcld_wpbot_core/context_notice_pages', 'qcld_wpbot_free_context_notice_pages' );
}

if ( ! function_exists( 'qcld_wpbot_free_seo_old_slugs' ) ) {
	/**
	 * Old SEO Analyzer links and bookmarks open SEO & AI Search.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_seo_old_slugs() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a page slug.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'wpbot-ai-seo-analyzer' === $page ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpbot-seo-ai-search' ) );
			exit;
		}
	}
	add_action( 'admin_init', 'qcld_wpbot_free_seo_old_slugs', 1 );
	// The old page is not registered: WordPress refuses it before admin_init runs.
	add_action( 'admin_page_access_denied', 'qcld_wpbot_free_seo_old_slugs', 1 );
}

if ( ! function_exists( 'qcld_wpbot_free_seo_retire_analyzer' ) ) {
	/**
	 * Clear the old SEO Analyzer's scheduled jobs once: its code is gone, so
	 * its rank checks and automation would fire into nothing. The AI Content
	 * Generator's jobs are left alone, and the old tables and options are
	 * kept (owner decision: no migration, no cleanup).
	 *
	 * @return void
	 */
	function qcld_wpbot_free_seo_retire_analyzer() {
		if ( get_option( 'qcld_wpbot_free_seo_analyzer_retired' ) ) {
			return;
		}
		foreach ( array( 'qcld_wpbot_ai_seo_seo_rank_check', 'qcld_wpbot_ai_seo_seo_automation_process' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		update_option( 'qcld_wpbot_free_seo_analyzer_retired', gmdate( 'c' ), false );
	}
	add_action( 'init', 'qcld_wpbot_free_seo_retire_analyzer', 20 );
}
