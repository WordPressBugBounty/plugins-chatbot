<?php
/**
 * Content Generator Module — Bootstrap.
 *
 * AI-powered blog content creation, SEO optimization, and WordPress publishing.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Module classes (loaded explicitly, no autoloader). Traits first.
 */
require_once __DIR__ . '/services/trait-parses-ai-json.php';
require_once __DIR__ . '/services/qcld-ai-seo-content-generator-service.php';
require_once __DIR__ . '/services/qcld-ai-seo-publisher-service.php';
require_once __DIR__ . '/services/qcld-ai-seo-seo-analyzer-service.php';
require_once __DIR__ . '/services/qcld-ai-seo-stock-image-service.php';
require_once __DIR__ . '/controllers/qcld-ai-seo-analytics-controller.php';
require_once __DIR__ . '/controllers/qcld-ai-seo-article-controller.php';
require_once __DIR__ . '/controllers/qcld-ai-seo-generate-controller.php';
require_once __DIR__ . '/controllers/qcld-ai-seo-preset-controller.php';
require_once __DIR__ . '/controllers/qcld-ai-seo-settings-controller.php';
require_once __DIR__ . '/controllers/qcld-ai-seo-workflow-controller.php';
require_once __DIR__ . '/qcld-ai-seo-content-rest-controller.php';

class Qcld_Wpbot_Ai_Seo_Content_Generator_Module extends Qcld_Wpbot_Ai_Seo_Module {

	/* ── Module identity ────────────────────────────────── */

	public function get_id(): string {
		return 'content-generator';
	}

	public function get_name(): string {
		return __( 'Content Generator', 'chatbot' );
	}

	public function get_description(): string {
		return __( 'AI-powered blog content creation with SEO optimization and one-click WordPress publishing.', 'chatbot' );
	}

	public function get_icon(): string {
		return 'edit-page';
	}

	public function get_version(): string {
		return '1.0.0';
	}

	/* ── Pro features ───────────────────────────────────── */

	public function get_pro_features(): array {
		// What the paid WPBot AI SEO Pro add-on adds. The free module has no
		// usage limits and none of this code; this list only feeds upsell text.
		return array(
			'outline'        => __( 'AI article outlines', 'chatbot' ),
			'deep_humanize'  => __( 'Deep Humanize rewriting', 'chatbot' ),
			'ai_images'      => __( 'AI featured images and image prompts', 'chatbot' ),
			'brand_voices'   => __( 'Brand voices', 'chatbot' ),
			'custom_presets' => __( 'Custom generation presets', 'chatbot' ),
			'extra_tones'    => __( 'Humorous, formal and conversational tones', 'chatbot' ),
			'toc'            => __( 'Automatic table of contents', 'chatbot' ),
			'automation'     => __( 'Automatic meta, excerpt and internal links', 'chatbot' ),
		);
	}

	/* ── Initialisation ─────────────────────────────────── */

	public function init(): void {
		$this->maybe_create_tables();
		wp_clear_scheduled_hook( 'qcld_wpbot_ai_seo_content_queue_process' );
		add_action( 'qcld_wpbot_ai_seo_content_sync_scheduled_articles', array( $this, 'sync_scheduled_articles' ) );
		if ( ! wp_next_scheduled( 'qcld_wpbot_ai_seo_content_sync_scheduled_articles' ) ) {
			wp_schedule_event( time(), 'hourly', 'qcld_wpbot_ai_seo_content_sync_scheduled_articles' );
		}
		add_action( 'transition_post_status', array( $this, 'sync_article_on_wp_publish' ), 10, 3 );

		// Once: fix articles that older releases marked "published" for draft sends.
		if ( ! get_option( 'qcld_wpbot_ai_seo_content_status_repair_v1' ) ) {
			Qcld_Wpbot_Ai_Seo_Content_Publisher_Service::repair_published_status();
			update_option( 'qcld_wpbot_ai_seo_content_status_repair_v1', time(), false );
		}

		// Dashboard stats hook.
		add_filter( 'qcld_wpbot_ai_seo_content-generator_dashboard_stats', array( $this, 'get_stats' ) );

		// admin-ajax.php endpoints (wp_ajax_qcld_wpbot_ai_seo_content_*).
		if ( wp_doing_ajax() ) {
			( new Qcld_Wpbot_Ai_Seo_Content_Rest_Controller() )->register_routes();
		}
	}

	public function sync_scheduled_articles(): void {
		Qcld_Wpbot_Ai_Seo_Content_Publisher_Service::sync_due_scheduled_articles( 50 );
	}

	public function sync_article_on_wp_publish( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		Qcld_Wpbot_Ai_Seo_Content_Publisher_Service::mark_article_published_by_post_id( (int) $post->ID, $post->post_date );
	}

	/* ── REST routes ─────────────────────────────────────── */

	/**
	 * No REST routes: the Content Generator talks to admin-ajax.php only
	 * (endpoints are registered in init()).
	 */
	public function register_routes(): void {}

	/* ── Database tables ─────────────────────────────────── */

	private function maybe_create_tables(): void {
		$installed = get_option( 'qcld_wpbot_ai_seo_content_generator_db_version', '' );
		if ( version_compare( $installed, QCLD_WPBOT_AI_SEO_CONTENT_DB_VERSION, '>=' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$this->create_tables( $charset );

		// Seed default presets.
		$this->seed_default_presets();

		// Run migrations.
		$this->run_migrations( $installed );

		update_option( 'qcld_wpbot_ai_seo_content_generator_db_version', QCLD_WPBOT_AI_SEO_CONTENT_DB_VERSION );
	}

	public function create_tables( string $charset_collate ): void {
		global $wpdb;
		$p = $wpdb->prefix;

		// Articles table.
		dbDelta( "CREATE TABLE {$p}qcld_wpbot_ai_seo_content_articles (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(500) NOT NULL DEFAULT '',
			slug VARCHAR(500) NOT NULL DEFAULT '',
			content LONGTEXT,
			excerpt TEXT,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			wp_post_id BIGINT UNSIGNED DEFAULT NULL,
			post_type VARCHAR(50) NOT NULL DEFAULT 'post',
			topic VARCHAR(500) NOT NULL DEFAULT '',
			keywords LONGTEXT,
			tone VARCHAR(50) NOT NULL DEFAULT 'professional',
			word_count_target INT UNSIGNED DEFAULT 1000,
			actual_word_count INT UNSIGNED DEFAULT 0,
			language VARCHAR(10) NOT NULL DEFAULT 'en',
			ai_provider VARCHAR(50) DEFAULT NULL,
			ai_model VARCHAR(100) DEFAULT NULL,
			seo_score TINYINT UNSIGNED DEFAULT 0,
			readability_score TINYINT UNSIGNED DEFAULT 0,
			meta_title VARCHAR(255) DEFAULT NULL,
			meta_description VARCHAR(500) DEFAULT NULL,
			outline LONGTEXT,
			category_ids LONGTEXT,
			tag_ids LONGTEXT,
			featured_image_url VARCHAR(1000) DEFAULT NULL,
			featured_image_id BIGINT UNSIGNED DEFAULT NULL,
			preset_id BIGINT UNSIGNED DEFAULT NULL,
			brand_voice_id BIGINT UNSIGNED DEFAULT NULL,
			is_pro TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			scheduled_publish_at DATETIME DEFAULT NULL,
			published_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY idx_status (status),
			KEY idx_wp_post_id (wp_post_id),
			KEY idx_created (created_at)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$p}qcld_wpbot_ai_seo_content_versions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			article_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(255) NOT NULL DEFAULT '',
			snapshot LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_article (article_id),
			KEY idx_created (created_at)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$p}qcld_wpbot_ai_seo_content_brand_voices (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT,
			tone_rules TEXT,
			style_rules TEXT,
			avoid_rules TEXT,
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_default (is_default)
		) $charset_collate;" );

		// Presets table.
		dbDelta( "CREATE TABLE {$p}qcld_wpbot_ai_seo_content_presets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT,
			tone VARCHAR(50) NOT NULL DEFAULT 'professional',
			style VARCHAR(50) NOT NULL DEFAULT 'blog_post',
			language VARCHAR(10) NOT NULL DEFAULT 'en',
			word_count INT UNSIGNED NOT NULL DEFAULT 1000,
			prompt_template LONGTEXT,
			system_instructions LONGTEXT,
			default_category_id BIGINT UNSIGNED DEFAULT NULL,
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			is_pro TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;" );

		// History / audit log table.
		dbDelta( "CREATE TABLE {$p}qcld_wpbot_ai_seo_content_history (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			article_id BIGINT UNSIGNED NOT NULL,
			action VARCHAR(50) NOT NULL,
			details LONGTEXT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_article (article_id),
			KEY idx_created (created_at)
		) $charset_collate;" );

		// Stock image usage log — powers duplicate avoidance: auto-pick skips
		// provider images used within the reuse window (Content → Settings → Images).
		dbDelta( "CREATE TABLE {$p}qcld_wpbot_ai_seo_content_images (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			provider VARCHAR(20) NOT NULL DEFAULT '',
			provider_image_id VARCHAR(100) NOT NULL DEFAULT '',
			attachment_id BIGINT UNSIGNED DEFAULT NULL,
			query VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_provider_image (provider, provider_image_id),
			KEY idx_created (created_at)
		) $charset_collate;" );
	}

	/* ── Seed default presets ────────────────────────────── */

	private function seed_default_presets(): void {
		global $wpdb;
		$p     = $wpdb->prefix;
		$table = "{$p}qcld_wpbot_ai_seo_content_presets";

		// Only seed if table is empty.
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		if ( $count > 0 ) {
			return;
		}

		$now     = current_time( 'mysql', true );
		$presets = array(
			array(
				'name'               => __( 'Blog Post', 'chatbot' ),
				'description'        => __( 'A well-structured blog article with H2/H3 headings, introduction, and conclusion.', 'chatbot' ),
				'tone'               => 'professional',
				'style'              => 'blog_post',
				'language'           => 'en',
				'word_count'         => 1200,
				'prompt_template'    => 'Write a comprehensive blog post about the given topic. Include an engaging introduction, well-organized sections with H2 and H3 headings, practical examples, and a compelling conclusion with a call to action.',
				'system_instructions' => 'You are an expert content writer specializing in informative, SEO-friendly blog posts. Write in a clear, engaging style. Use proper HTML heading tags (h2, h3). Include bullet points and numbered lists where appropriate.',
				'is_default'         => 1,
				'is_pro'             => 0,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array(
				'name'               => __( 'How-To Guide', 'chatbot' ),
				'description'        => __( 'Step-by-step instructional guide with clear numbered steps.', 'chatbot' ),
				'tone'               => 'instructional',
				'style'              => 'how_to',
				'language'           => 'en',
				'word_count'         => 1500,
				'prompt_template'    => 'Write a detailed how-to guide about the given topic. Include a brief intro explaining what the reader will learn, numbered step-by-step instructions, tips and warnings where relevant, and a summary of key takeaways.',
				'system_instructions' => 'You are a technical writer who creates clear, actionable how-to guides. Break complex processes into simple numbered steps. Use HTML heading tags (h2, h3). Include pro tips in bold or callout format.',
				'is_default'         => 1,
				'is_pro'             => 0,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array(
				'name'               => __( 'Product Review', 'chatbot' ),
				'description'        => __( 'Persuasive product review with pros, cons, and a verdict.', 'chatbot' ),
				'tone'               => 'persuasive',
				'style'              => 'product_review',
				'language'           => 'en',
				'word_count'         => 1000,
				'prompt_template'    => 'Write a thorough product review about the given topic. Include an overview, key features, detailed pros and cons, a comparison with alternatives if relevant, and a final verdict with a rating.',
				'system_instructions' => 'You are a product review expert who writes honest, detailed, and engaging reviews. Use HTML heading tags. Include a pros/cons section formatted as lists. Be balanced but persuasive when the product merits it.',
				'is_default'         => 1,
				'is_pro'             => 0,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array(
				'name'               => __( 'Listicle', 'chatbot' ),
				'description'        => __( 'Engaging numbered list article (e.g., "10 Best Tips for...").', 'chatbot' ),
				'tone'               => 'engaging',
				'style'              => 'listicle',
				'language'           => 'en',
				'word_count'         => 1200,
				'prompt_template'    => 'Write an engaging listicle about the given topic. Use a numbered format with each item having its own H2 heading. Include a brief introduction and conclusion. Each item should have 2-3 paragraphs of detail.',
				'system_instructions' => 'You are a content writer specializing in viral, shareable list articles. Each list item should have a compelling heading. Use HTML heading tags. Make the content scannable yet informative.',
				'is_default'         => 1,
				'is_pro'             => 0,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
		);

		foreach ( $presets as $preset ) {
			$wpdb->insert( $table, $preset );
		}
	}

	/* ── Migrations ──────────────────────────────────────── */

	private function run_migrations( string $installed_version ): void {
		// v1.7.0: Stock image usage log for duplicate avoidance.
		if ( version_compare( $installed_version, '1.7.0', '<' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_images';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( "CREATE TABLE {$table} (
					id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					provider VARCHAR(20) NOT NULL DEFAULT '',
					provider_image_id VARCHAR(100) NOT NULL DEFAULT '',
					attachment_id BIGINT UNSIGNED DEFAULT NULL,
					query VARCHAR(255) NOT NULL DEFAULT '',
					created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					UNIQUE KEY idx_provider_image (provider, provider_image_id),
					KEY idx_created (created_at)
				) {$wpdb->get_charset_collate()};" );
			}
		}

		// v1.5.0: Store scheduled WordPress publish time per article.
		if ( version_compare( $installed_version, '1.5.0', '<' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_articles';
			$col   = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'scheduled_publish_at'" );
			if ( empty( $col ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN scheduled_publish_at DATETIME DEFAULT NULL AFTER updated_at" );
			}
		}

		// v1.4.0: Workflow tables and brand voice link.
		if ( version_compare( $installed_version, '1.4.0', '<' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_articles';
			$col   = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'brand_voice_id'" );
			if ( empty( $col ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN brand_voice_id BIGINT UNSIGNED DEFAULT NULL AFTER preset_id" );
			}
		}

		// v1.3.0: Store target WordPress post type per article.
		if ( version_compare( $installed_version, '1.3.0', '<' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_articles';
			$col   = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'post_type'" );
			if ( empty( $col ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN post_type VARCHAR(50) NOT NULL DEFAULT 'post' AFTER wp_post_id" );
			}
		}

		// v1.2.0: Add featured_image_id column.
		if ( version_compare( $installed_version, '1.2.0', '<' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_articles';
			$col   = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'featured_image_id'" );
			if ( empty( $col ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN featured_image_id BIGINT UNSIGNED DEFAULT NULL AFTER featured_image_url" );
			}
		}

		// v1.1.0: Normalise preset style values from hyphens to underscores.
		if ( version_compare( $installed_version, '1.1.0', '<' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_presets';

			$style_map = array(
				'blog-post' => 'blog_post',
				'how-to'    => 'how_to',
				'review'    => 'product_review',
			);

			foreach ( $style_map as $old_style => $new_style ) {
				$wpdb->update(
					$table,
					array( 'style' => $new_style ),
					array( 'style' => $old_style ),
					array( '%s' ),
					array( '%s' )
				);
			}
		}
	}

	/* ── Dashboard stats ─────────────────────────────────── */

	public function get_stats(): array {
		global $wpdb;
		$p = $wpdb->prefix;

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', "{$p}qcld_wpbot_ai_seo_content_articles" ) );
		if ( ! $table_exists ) {
			return array(
				'total_articles'     => 0,
				'published_articles' => 0,
				'draft_articles'     => 0,
				'avg_seo_score'      => 0,
				'total_words'        => 0,
			);
		}

		return array(
			'total_articles'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}qcld_wpbot_ai_seo_content_articles" ),
			'published_articles' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}qcld_wpbot_ai_seo_content_articles WHERE status = %s", 'published' ) ),
			'draft_articles'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}qcld_wpbot_ai_seo_content_articles WHERE status = %s", 'draft' ) ),
			'avg_seo_score'      => (float) $wpdb->get_var( "SELECT ROUND(AVG(seo_score), 1) FROM {$p}qcld_wpbot_ai_seo_content_articles WHERE seo_score > 0" ) ?: 0,
			'total_words'        => (int) $wpdb->get_var( "SELECT SUM(actual_word_count) FROM {$p}qcld_wpbot_ai_seo_content_articles" ) ?: 0,
		);
	}

	/* ── Monthly article count (for free limit check) ──── */

	public static function get_monthly_article_count(): int {
		global $wpdb;
		$p = $wpdb->prefix;

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', "{$p}qcld_wpbot_ai_seo_content_articles" ) );
		if ( ! $table_exists ) {
			return 0;
		}

		// Count AI generations, not article rows: regenerating an article in
		// place adds no row but is still a generation. Manually written
		// articles are not AI generations and do not count.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$p}qcld_wpbot_ai_seo_content_history WHERE action = %s AND created_at >= %s",
				'generated',
				gmdate( 'Y-m-01 00:00:00' )
			)
		);
	}
}

/* ── Register with Module Manager ──────────────────────── */

add_action( 'qcld_wpbot_ai_seo_load_module_content-generator', function ( $manager ) {
	$manager->register( new Qcld_Wpbot_Ai_Seo_Content_Generator_Module() );
} );
