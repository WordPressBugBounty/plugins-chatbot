<?php
/**
 * Content AJAX Controller — central endpoint dispatcher.
 *
 * Registers every admin-ajax.php endpoint of the Content Generator module
 * (wp_ajax_qcld_wpbot_ai_seo_content_*) and delegates to the domain controllers.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: modules/content-generator/class-content-rest-controller.php
 * Copyright (C) Noor Alam (wpthemespace.com). Licensed GPLv2 or later.
 *
 * Modified by QuantumCloud for WPBot, 2026: Namespace, prefixes and text
 * domain renamed, integrated with WPBot.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qcld_Wpbot_Ai_Seo_Content_Rest_Controller {

	/* ────────────────────────────────────────────────────
	 *  Register all endpoints
	 * ──────────────────────────────────────────────────── */

	public function register_routes(): void {
		$this->register_article_routes();
		$this->register_generate_routes();
		$this->register_preset_routes();
		$this->register_analytics_routes();
		$this->register_settings_routes();
		$this->register_workflow_routes();
	}

	/**
	 * Register one admin-ajax endpoint (action qcld_wpbot_ai_seo_{$action}).
	 *
	 * @param string $action  Action name without prefix.
	 * @param string $method  Logical method passed to the controller.
	 * @param string $route   Route pattern; named groups are read from the payload.
	 * @param array  $handler callback, permission_callback, args.
	 */
	private function add( string $action, string $method, string $route, array $handler ): void {
		Qcld_Wpbot_Ai_Seo_Ajax::add( $action, $method, $route, $handler );
	}

	/* ── Permission callbacks ────────────────────────── */

	/**
	 * Admin-only permission check.
	 *
	 * No native return type: `bool|\WP_Error` is a PHP 8.0 union type and would
	 * be a parse error on PHP 7.4.
	 *
	 * @return bool|\WP_Error
	 */
	public function admin_permission() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'chatbot' ), array( 'status' => 403 ) );
	}

	/* ══════════════════════════════════════════════════════
	 *  ARTICLES
	 * ══════════════════════════════════════════════════════ */

	private function register_article_routes(): void {
		$c = new Qcld_Wpbot_Ai_Seo_Content_Article_Controller();

		// GET /content/articles
		$this->add( 'content_articles_list', 'GET', '/content/articles', array(
			'callback'            => array( $c, 'index' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
				'per_page' => array( 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ),
				'search'   => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				'status'   => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// POST /content/articles
		$this->add( 'content_article_create', 'POST', '/content/articles', array(
			'callback'            => array( $c, 'store' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'title' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'topic' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// GET /content/articles/{id}
		$this->add( 'content_article_get', 'GET', '/content/articles/(?P<id>\d+)', array(
			'callback'            => array( $c, 'show' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// PUT /content/articles/{id}
		$this->add( 'content_article_update', 'PUT', '/content/articles/(?P<id>\d+)', array(
			'callback'            => array( $c, 'update' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// DELETE /content/articles/{id}
		$this->add( 'content_article_delete', 'DELETE', '/content/articles/(?P<id>\d+)', array(
			'callback'            => array( $c, 'destroy' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// POST /content/articles/{id}/publish
		$this->add( 'content_article_publish', 'POST', '/content/articles/(?P<id>\d+)/publish', array(
			'callback'            => array( $c, 'publish' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'post_status' => array( 'type' => 'string', 'default' => 'draft', 'enum' => array( 'draft', 'publish', 'future', 'pending', 'private' ), 'sanitize_callback' => 'sanitize_text_field' ),
				'scheduled_publish_at' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		$this->add( 'content_article_schedule', 'POST', '/content/articles/(?P<id>\d+)/schedule', array(
			'callback'            => array( $c, 'schedule_publish' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'scheduled_publish_at' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// POST /content/articles/{id}/unpublish
		$this->add( 'content_article_unpublish', 'POST', '/content/articles/(?P<id>\d+)/unpublish', array(
			'callback'            => array( $c, 'unpublish' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// POST /content/articles/{id}/duplicate
		$this->add( 'content_article_duplicate', 'POST', '/content/articles/(?P<id>\d+)/duplicate', array(
			'callback'            => array( $c, 'duplicate' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// POST /content/articles/bulk
		// POST /content/articles/delete-preview — what a delete would touch (read-only).
		$this->add( 'content_articles_delete_preview', 'POST', '/content/articles/delete-preview', array(
			'callback'            => array( $c, 'delete_preview' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'ids' => array( 'type' => 'array', 'required' => true, 'items' => array( 'type' => 'integer' ) ),
			),
		) );

		$this->add( 'content_articles_bulk', 'POST', '/content/articles/bulk', array(
			'callback'            => array( $c, 'bulk' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'action' => array( 'type' => 'string', 'required' => true, 'enum' => array( 'delete' ) ),
				'ids'    => array( 'type' => 'array', 'required' => true, 'items' => array( 'type' => 'integer' ) ),
			),
		) );
	}

	/* ══════════════════════════════════════════════════════
	 *  AI GENERATION
	 * ══════════════════════════════════════════════════════ */

	private function register_generate_routes(): void {
		$c = new Qcld_Wpbot_Ai_Seo_Content_Generate_Controller();

		// POST /content/generate
		$this->add( 'content_generate', 'POST', '/content/generate', array(
			'callback'            => array( $c, 'generate' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'topic'      => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'keywords'   => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ),
				'tone'       => array( 'type' => 'string', 'default' => 'professional', 'sanitize_callback' => 'sanitize_text_field' ),
				'word_count' => array( 'type' => 'integer', 'default' => 1000, 'sanitize_callback' => 'absint' ),
				'preset_id'  => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
				'language'   => array( 'type' => 'string', 'default' => 'en', 'sanitize_callback' => 'sanitize_text_field' ),
				'outline'    => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
				'serp_context' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
				'post_type'  => array( 'type' => 'string', 'default' => 'post', 'sanitize_callback' => 'sanitize_key' ),
				'category_ids' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'integer' ) ),
				'tag_ids'    => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'string' ) ),
				// Existing article to regenerate in place (0 = create a new article).
				'article_id' => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			),
		) );

		// POST /content/generate/section
		$this->add( 'content_generate_section', 'POST', '/content/generate/section', array(
			'callback'            => array( $c, 'generate_section' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'topic'         => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'section_title' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'context'       => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
				'tone'          => array( 'type' => 'string', 'default' => 'professional', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// POST /content/generate/improve
		$this->add( 'content_generate_improve', 'POST', '/content/generate/improve', array(
			'callback'            => array( $c, 'improve_content' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'content'     => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'qcld_wpbot_ai_seo_kses_article' ),
				'instruction' => array( 'type' => 'string', 'default' => 'Improve this content for clarity and engagement', 'sanitize_callback' => 'sanitize_text_field' ),
				'tone'        => array( 'type' => 'string', 'default' => 'professional', 'sanitize_callback' => 'sanitize_text_field' ),
				'article_id'  => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			),
		) );

		// POST /content/generate/seo-optimize
		$this->add( 'content_generate_seo_optimize', 'POST', '/content/generate/seo-optimize', array(
			'callback'            => array( $c, 'seo_optimize' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'content'  => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'qcld_wpbot_ai_seo_kses_article' ),
				'keywords' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ),
				'title'    => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// POST /content/generate/meta
		$this->add( 'content_generate_meta', 'POST', '/content/generate/meta', array(
			'callback'            => array( $c, 'generate_meta' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'title'    => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'content'  => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'qcld_wpbot_ai_seo_kses_article' ),
				'keywords' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ),
			),
		) );

		// GET /content/stock-images/search — search Pexels/Pixabay.
		$this->add( 'content_stock_search', 'GET', '/content/stock-images/search', array(
			'callback'            => array( $c, 'stock_search' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'query'    => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
				'per_page' => array( 'type' => 'integer', 'default' => 12, 'sanitize_callback' => 'absint' ),
			),
		) );

		// POST /content/stock-images/import — sideload a stock image into the media library.
		$this->add( 'content_stock_import', 'POST', '/content/stock-images/import', array(
			'callback'            => array( $c, 'stock_import' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'url'        => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'esc_url_raw' ),
				'alt'        => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				'article_id' => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			),
		) );
	}

	/* ══════════════════════════════════════════════════════
	 *  PRESETS
	 * ══════════════════════════════════════════════════════ */

	private function register_preset_routes(): void {
		$c = new Qcld_Wpbot_Ai_Seo_Content_Preset_Controller();

		// GET /content/presets
		$this->add( 'content_presets_list', 'GET', '/content/presets', array(
			'callback'            => array( $c, 'index' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// PUT /content/presets/{id}
		$this->add( 'content_preset_update', 'PUT', '/content/presets/(?P<id>\d+)', array(
			'callback'            => array( $c, 'update' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// DELETE /content/presets/{id}
		$this->add( 'content_preset_delete', 'DELETE', '/content/presets/(?P<id>\d+)', array(
			'callback'            => array( $c, 'destroy' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );
	}

	/* ══════════════════════════════════════════════════════
	 *  ANALYTICS
	 * ══════════════════════════════════════════════════════ */

	private function register_analytics_routes(): void {
		$c = new Qcld_Wpbot_Ai_Seo_Content_Analytics_Controller();

		// GET /content/analytics/overview
		$this->add( 'content_analytics_overview', 'GET', '/content/analytics/overview', array(
			'callback'            => array( $c, 'overview' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'days' => array( 'type' => 'integer', 'default' => 30, 'sanitize_callback' => 'absint' ),
			),
		) );

		// GET /content/analytics/article/{id}
		$this->add( 'content_analytics_article', 'GET', '/content/analytics/article/(?P<id>\d+)', array(
			'callback'            => array( $c, 'article_report' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );
	}

	/* ══════════════════════════════════════════════════════
	 *  SETTINGS
	 * ══════════════════════════════════════════════════════ */

	private function register_settings_routes(): void {
		$c = new Qcld_Wpbot_Ai_Seo_Content_Settings_Controller();

		// GET /content/settings
		$this->add( 'content_settings_get', 'GET', '/content/settings', array(
			'callback'            => array( $c, 'get_settings' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// POST /content/settings
		$this->add( 'content_settings_save', 'POST', '/content/settings', array(
			'callback'            => array( $c, 'save_settings' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// GET /content/wp-taxonomies (helper: fetch WP categories & tags)
		$this->add( 'content_wp_taxonomies', 'GET', '/content/wp-taxonomies', array(
			'callback'            => array( $c, 'get_wp_taxonomies' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );
	}

	private function register_workflow_routes(): void {
		$c = new Qcld_Wpbot_Ai_Seo_Content_Workflow_Controller();

		$this->add( 'content_article_versions', 'GET', '/content/articles/(?P<id>\d+)/versions', array(
			'callback'            => array( $c, 'get_versions' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		$this->add( 'content_article_version_restore', 'POST', '/content/articles/(?P<id>\d+)/versions/(?P<version_id>\d+)/restore', array(
			'callback'            => array( $c, 'restore_version' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

	}
}
