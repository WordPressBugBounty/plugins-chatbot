<?php
/**
 * Workflow Controller — article versions.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qcld_Wpbot_Ai_Seo_Content_Workflow_Controller {

	use Qcld_Wpbot_Ai_Seo_Content_Parses_Ai_Json;

	private const CACHE_GROUP = 'qcld_wpbot_ai_seo_content_workflow';
	private const CACHE_TTL   = 300;

	private static function get_cache_version(): string {
		$version = wp_cache_get( 'version', self::CACHE_GROUP );
		if ( false === $version ) {
			$version = '1';
			wp_cache_set( 'version', $version, self::CACHE_GROUP, self::CACHE_TTL );
		}

		return (string) $version;
	}

	private static function bump_cache_version(): void {
		wp_cache_set( 'version', (string) microtime( true ), self::CACHE_GROUP, self::CACHE_TTL );
	}

	private static function build_cache_key( string $prefix, array $parts = array() ): string {
		return $prefix . ':' . md5( wp_json_encode( $parts ) . ':' . self::get_cache_version() );
	}

	/**
	 * Save a restorable version snapshot for an article.
	 */
	public static function save_article_version( int $article_id, string $label = 'Manual save' ): void {
		global $wpdb;
		$articles_table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_articles';
		$versions_table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_versions';
		$article_cache_key = self::build_cache_key( 'article_snapshot', array( 'article_id' => $article_id ) );
		$article           = wp_cache_get( $article_cache_key, self::CACHE_GROUP );
		if ( false === $article ) {
			$article = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $articles_table, $article_id ), ARRAY_A );
			wp_cache_set( $article_cache_key, $article ?: null, self::CACHE_GROUP, self::CACHE_TTL );
		}

		if ( ! $article ) {
			return;
		}

		$table_exists_cache_key = self::build_cache_key( 'versions_table_exists', array( 'table' => $versions_table ) );
		$table_exists           = wp_cache_get( $table_exists_cache_key, self::CACHE_GROUP );
		if ( false === $table_exists ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $versions_table ) );
			wp_cache_set( $table_exists_cache_key, $table_exists ?: '', self::CACHE_GROUP, self::CACHE_TTL );
		}
		if ( ! $table_exists ) {
			return;
		}

		$wpdb->insert( $versions_table, array(
			'article_id' => $article_id,
			'label'      => sanitize_text_field( $label ),
			'snapshot'   => wp_json_encode( $article ),
			'created_at' => current_time( 'mysql', true ),
		) );
		self::bump_cache_version();
	}

	public function get_versions( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$versions_table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_versions';
		$article_id     = absint( $request->get_param( 'id' ) );
		$cache_key      = self::build_cache_key( 'versions', array( 'article_id' => $article_id ) );
		$cached         = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return new \WP_REST_Response( array( 'items' => $cached ) );
		}

		$items = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, article_id, label, created_at FROM %i WHERE article_id = %d ORDER BY created_at DESC LIMIT 25',
			$versions_table,
			$article_id
		), ARRAY_A );
		wp_cache_set( $cache_key, $items ?: array(), self::CACHE_GROUP, self::CACHE_TTL );

		return new \WP_REST_Response( array( 'items' => $items ?: array() ) );
	}

	public function restore_version( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$articles_table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_articles';
		$versions_table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_versions';
		$article_id     = absint( $request->get_param( 'id' ) );
		$version_id     = absint( $request->get_param( 'version_id' ) );
		$cache_key      = self::build_cache_key( 'restore_version', array(
			'article_id' => $article_id,
			'version_id' => $version_id,
		) );
		$version        = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false === $version ) {
			$version = $wpdb->get_row( $wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d AND article_id = %d',
				$versions_table,
				$version_id,
				$article_id
			), ARRAY_A );
			wp_cache_set( $cache_key, $version ?: null, self::CACHE_GROUP, self::CACHE_TTL );
		}

		if ( ! $version ) {
			return new \WP_REST_Response( array( 'message' => __( 'Version not found.', 'chatbot' ) ), 404 );
		}

		$snapshot = json_decode( $version['snapshot'], true );
		if ( ! is_array( $snapshot ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'Version snapshot is invalid.', 'chatbot' ) ), 400 );
		}

		self::save_article_version( $article_id, 'Before restore' );

		$allowed = array(
			'title', 'slug', 'content', 'excerpt', 'topic', 'keywords', 'tone', 'word_count_target',
			'actual_word_count', 'language', 'seo_score', 'readability_score', 'meta_title',
			'meta_description', 'outline', 'category_ids', 'tag_ids', 'featured_image_url',
			'featured_image_id', 'preset_id', 'post_type', 'brand_voice_id',
		);
		$data = array();
		foreach ( $allowed as $field ) {
			if ( array_key_exists( $field, $snapshot ) ) {
				$data[ $field ] = $snapshot[ $field ];
			}
		}
		$data['updated_at'] = current_time( 'mysql', true );

		$wpdb->update( $articles_table, $data, array( 'id' => $article_id ) );
		self::bump_cache_version();

		return new \WP_REST_Response( array( 'message' => __( 'Article version restored.', 'chatbot' ) ) );
	}
}
