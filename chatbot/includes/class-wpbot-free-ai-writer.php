<?php
/**
 * AI Writer in WPBot Free: loads the AI Writer core module
 * (includes/ai-writer/) on top of the bundled WPBot Core (includes/wpbot-core/)
 * and registers WPBot Free as its host.
 *
 * The folder includes/ai-writer is a synced copy of qcld/wpbot-ai-writer
 * (npm run sync:ai-writer); never edit it here. Everything WPBot Free
 * specific lives in class-wpbot-free-ai-writer-adapter.php. WPBot Free
 * bundles no Pro module: the Pro features show only as labelled, inactive
 * previews the owner can hide.
 *
 * AI Writer replaces the old AI Content Generator, whose page links now open
 * AI Writer. Its saved options and tables are kept.
 *
 * Loaded from qcld-wpwbot.php:
 *     require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/class-wpbot-free-ai-writer.php';
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) || ! file_exists( __DIR__ . '/wpbot-core/wpbot-core.php' ) || ! file_exists( __DIR__ . '/ai-writer/ai-writer.php' ) ) {
	return;
}

// The loaders inside pick the newest bundled copy of each module, so another
// WPBot plugin with the same modules never loads them twice.
require_once __DIR__ . '/wpbot-core/wpbot-core.php';
require_once __DIR__ . '/ai-writer/ai-writer.php';

if ( ! function_exists( 'qcld_wpbot_free_core_register' ) ) {
	/**
	 * Register WPBot Free as the WPBot Core host (also done by SEO & AI
	 * Search; whichever file loads first defines it).
	 *
	 * @return void
	 */
	function qcld_wpbot_free_core_register() {
		require_once __DIR__ . '/class-wpbot-free-core-adapter.php';
		\QuantumCloud\WPBotCore\Core::instance()->register_host( QCLD_WPBot_Free_Core_Adapter::instance() );
	}
	add_action( 'qcld_wpbot_core/loaded', 'qcld_wpbot_free_core_register' );
}

if ( ! function_exists( 'qcld_wpbot_free_ai_writer_register' ) ) {
	/**
	 * Register WPBot Free as the AI Writer host.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_ai_writer_register() {
		require_once __DIR__ . '/class-wpbot-free-core-adapter.php';
		require_once __DIR__ . '/class-wpbot-free-ai-writer-adapter.php';
		\QuantumCloud\AiWriter\Module::instance()->register_host( new QCLD_WPBot_Free_AI_Writer_Adapter() );
	}
	add_action( 'qcld_wpbot_writer/loaded', 'qcld_wpbot_free_ai_writer_register' );
}

if ( ! function_exists( 'qcld_wpbot_free_ai_writer_format_hints' ) ) {
	/**
	 * Hint-only format rules: when a topic fits a format that comes with
	 * WPBot Pro, the format picker says so ("A Case study (Pro) fits this
	 * topic") next to its Pro badge. They never choose a format, and they
	 * show only while Pro previews show.
	 *
	 * @param array[] $rules AI Writer's format rules.
	 * @return array[]
	 */
	function qcld_wpbot_free_ai_writer_format_hints( $rules ) {
		$hints = array(
			array( 'comparison', '/\s(vs\.?|versus)\s|\bcompared?\b|\bwhich is better\b|\bdifference between\b/', __( 'Comparison / vs (Pro)', 'chatbot' ), 10 ),
			array( 'stats_roundup', '/\b(statistics|stats|in numbers|by the numbers)\b/', __( 'Statistics roundup (Pro)', 'chatbot' ), 20 ),
			array( 'case_study', '/\b(case study|how we helped|success story|customer story)\b/', __( 'Case study (Pro)', 'chatbot' ), 30 ),
			array( 'original_research', '/\b(survey|we asked|our data|what (our )?customers (ask|asked|want|buy))\b/', __( 'Original research (Pro)', 'chatbot' ), 40 ),
			array( 'template', '/\b(template|framework|worksheet|planner)\b/', __( 'Template / framework (Pro)', 'chatbot' ), 60 ),
		);
		$rules = (array) $rules;
		foreach ( $hints as $hint ) {
			$rules[] = array(
				'id'    => $hint[0],
				'match' => $hint[1],
				'hint'  => $hint[2],
				'order' => $hint[3],
			);
		}
		return $rules;
	}
	add_filter( 'qcld_wpbot_writer/format_rules', 'qcld_wpbot_free_ai_writer_format_hints' );
}

if ( ! function_exists( 'qcld_wpbot_free_ai_writer_context_pages' ) ) {
	/**
	 * The Business Context fill-in notice also shows on AI Writer, which
	 * reads Business Context in its prompts (SEO & AI Search's own filter
	 * keeps the list to the screens that use it; this runs after it).
	 *
	 * @param string[] $pages Page slugs.
	 * @return string[]
	 */
	function qcld_wpbot_free_ai_writer_context_pages( $pages ) {
		$pages   = (array) $pages;
		$pages[] = 'wpbot-ai-writer';
		return array_values( array_unique( $pages ) );
	}
	add_filter( 'qcld_wpbot_core/context_notice_pages', 'qcld_wpbot_free_ai_writer_context_pages', 11 );
}

if ( ! function_exists( 'qcld_wpbot_free_ai_writer_retire_generator' ) ) {
	/**
	 * Take the old AI Content Generator out of the WPBot menu: AI Writer
	 * replaces it (same decision as WPBot Pro).
	 *
	 * @return void
	 */
	function qcld_wpbot_free_ai_writer_retire_generator() {
		remove_submenu_page( 'wpbot_openAi', 'wpbot-ai-content-generator' );
		remove_submenu_page( 'wpbot-ai-content-generator', 'wpbot-ai-content-generator' );
	}
	add_action( 'admin_menu', 'qcld_wpbot_free_ai_writer_retire_generator', 999 );
}

if ( ! function_exists( 'qcld_wpbot_free_ai_writer_old_slugs' ) ) {
	/**
	 * Old AI Content Generator links and bookmarks open AI Writer.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_ai_writer_old_slugs() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a page slug.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'wpbot-ai-content-generator', 'ai-content-generator', 'wpbot-ai-marketing-expert-content' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpbot-ai-writer' ) );
			exit;
		}
	}
	add_action( 'admin_init', 'qcld_wpbot_free_ai_writer_old_slugs', 1 );
	// A page that is no longer registered is refused before admin_init runs.
	add_action( 'admin_page_access_denied', 'qcld_wpbot_free_ai_writer_old_slugs', 1 );
}
