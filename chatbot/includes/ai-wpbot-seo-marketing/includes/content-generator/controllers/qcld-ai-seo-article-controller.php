<?php
/**
 * Article Controller — CRUD for generated articles.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: modules/content-generator/controllers/class-article-controller.php
 * Copyright (C) Noor Alam (wpthemespace.com). Licensed GPLv2 or later.
 *
 * Modified by QuantumCloud for WPBot, 2026: Namespace, prefixes and text
 * domain renamed, integrated with WPBot.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */


// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qcld_Wpbot_Ai_Seo_Content_Article_Controller {
	private const CACHE_GROUP = 'qcld_wpbot_ai_seo_content_articles';
	private const CACHE_TTL   = 60;

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
	 * Decode a JSON column that may have been double/triple-encoded historically.
	 */
	private static function decode_json_array( string $json ): array {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		// Un-nest: if the result is a single-element array whose only element is itself a JSON string, unwrap it.
		while ( count( $decoded ) === 1 && is_string( $decoded[0] ) ) {
			$inner = json_decode( $decoded[0], true );
			if ( is_array( $inner ) ) {
				$decoded = $inner;
			} else {
				break;
			}
		}
		return $decoded;
	}

	/**
	 * Sanitize content HTML: run qcld_wpbot_ai_seo_kses_article (post tags + embeds) and
	 * strip browser-injected junk (e.g. Google Translate widgets).
	 */
	private static function clean_content( string $content ): string {
		// Strip Google Translate injected div.
		$content = preg_replace( '/<div\b[^>]*id\s*=\s*["\']gtx-trans["\'][^>]*>.*?<\/div>/si', '', $content );
		// Strip any remaining empty divs that may have been wrappers.
		$content = preg_replace( '/<div\b[^>]*class\s*=\s*["\']gtx-[^"\']*["\'][^>]*>.*?<\/div>/si', '', $content );
		return qcld_wpbot_ai_seo_kses_article( trim( $content ) );
	}

	/* ── LIST articles ───────────────────────────────── */

	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		Qcld_Wpbot_Ai_Seo_Content_Publisher_Service::sync_due_scheduled_articles();

		$p        = $wpdb->prefix;
		$page     = max( 1, $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = $request->get_param( 'search' );
		$status   = $request->get_param( 'status' );

		$where = 'WHERE 1=1';
		$args  = array();

		if ( $search ) {
			$where .= ' AND (title LIKE %s OR topic LIKE %s)';
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$args[] = $like;
			$args[] = $like;
		}

		if ( $status ) {
			$where .= ' AND status = %s';
			$args[] = $status;
		}

		$cache_key = self::build_cache_key( 'index', array(
			'page'     => $page,
			'per_page' => $per_page,
			'search'   => (string) $search,
			'status'   => (string) $status,
		) );
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return new \WP_REST_Response( $cached );
		}

		// Count.
		$count_sql = "SELECT COUNT(*) FROM {$p}qcld_wpbot_ai_seo_content_articles {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$args ) ) : $wpdb->get_var( $count_sql ) );

		// Fetch.
		$query = "SELECT id, title, slug, status, topic, tone, word_count_target, actual_word_count, language, post_type,
					seo_score, readability_score, wp_post_id, preset_id, is_pro, created_at, updated_at, scheduled_publish_at, published_at
				  FROM {$p}qcld_wpbot_ai_seo_content_articles {$where}
				  ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$args[] = $per_page;
		$args[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );

		$response = array(
			'items'    => $items ?: array(),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		);

		wp_cache_set( $cache_key, $response, self::CACHE_GROUP, self::CACHE_TTL );

		return new \WP_REST_Response( $response );
	}

	/* ── SHOW single article ─────────────────────────── */

	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		Qcld_Wpbot_Ai_Seo_Content_Publisher_Service::sync_due_scheduled_articles();

		$p  = $wpdb->prefix;
		$id = absint( $request->get_param( 'id' ) );
		$cache_key = self::build_cache_key( 'show', array( 'id' => $id ) );
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return new \WP_REST_Response( $cached );
		}

		$article = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$p}qcld_wpbot_ai_seo_content_articles WHERE id = %d",
			$id
		) );

		if ( ! $article ) {
			return new \WP_REST_Response( array( 'message' => __( 'Article not found.', 'chatbot' ) ), 404 );
		}

		// Read-only: the stored content is returned exactly as saved. AI output
		// is cleaned once at generation time; re-cleaning here used to rewrite
		// the article and the live post on every view.

		// Decode JSON fields (with recursive unwrap for historically double-encoded data).
		$article->keywords     = self::decode_json_array( $article->keywords ?: '[]' );
		$article->category_ids = array_map( 'intval', self::decode_json_array( $article->category_ids ?: '[]' ) );
		$article->tag_ids      = self::decode_json_array( $article->tag_ids ?: '[]' );
		$article->outline      = json_decode( $article->outline ?: '[]', true );

		// Frontend permalink of the connected WP post (for the "View Post" button).
		$article->view_url = ( ! empty( $article->wp_post_id ) && get_post( (int) $article->wp_post_id ) )
			? (string) get_permalink( (int) $article->wp_post_id )
			: '';

		// Get history.
		$article->history = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$p}qcld_wpbot_ai_seo_content_history WHERE article_id = %d ORDER BY created_at DESC LIMIT 50",
			$id
		) );

		wp_cache_set( $cache_key, $article, self::CACHE_GROUP, self::CACHE_TTL );

		return new \WP_REST_Response( $article );
	}

	/* ── CREATE article ──────────────────────────────── */

	public function store( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p   = $wpdb->prefix;
		$now = current_time( 'mysql', true );

		$title = sanitize_text_field( $request->get_param( 'title' ) );
		$topic = sanitize_text_field( $request->get_param( 'topic' ) ?: $title );

		$post_type = sanitize_key( $request->get_param( 'post_type' ) ?: 'post' );
		if ( ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		$tone = qcld_wpbot_ai_seo_sanitize_tone( $request->get_param( 'tone' ) ?: 'professional' );

		$word_count_target = absint( $request->get_param( 'word_count_target' ) ?: $request->get_param( 'word_count' ) ?: 1000 );

		$data = array(
			'title'             => $title,
			'slug'              => sanitize_title( $title ),
			'content'           => self::clean_content( $request->get_param( 'content' ) ?: '' ),
			'excerpt'           => sanitize_textarea_field( $request->get_param( 'excerpt' ) ?: '' ),
			'status'            => 'draft',
			'post_type'         => $post_type,
			'topic'             => $topic,
			'keywords'          => wp_json_encode( array_map( 'sanitize_text_field', (array) ( $request->get_param( 'keywords' ) ?: array() ) ) ),
			'tone'              => $tone,
			'word_count_target' => $word_count_target,
			'actual_word_count' => str_word_count( wp_strip_all_tags( $request->get_param( 'content' ) ?: '' ) ),
			'language'          => sanitize_text_field( $request->get_param( 'language' ) ?: 'en' ),
			'outline'           => wp_json_encode( $request->get_param( 'outline' ) ?: array() ),
			'category_ids'      => wp_json_encode( array_map( 'absint', (array) ( $request->get_param( 'category_ids' ) ?: array() ) ) ),
			'tag_ids'           => wp_json_encode( array_map( 'sanitize_text_field', (array) ( $request->get_param( 'tag_ids' ) ?: array() ) ) ),
			'featured_image_url' => esc_url_raw( $request->get_param( 'featured_image_url' ) ?: '' ),
			'featured_image_id'  => absint( $request->get_param( 'featured_image_id' ) ) ?: null,
			'preset_id'         => absint( $request->get_param( 'preset_id' ) ) ?: null,
			'meta_title'        => sanitize_text_field( $request->get_param( 'meta_title' ) ?: '' ),
			'meta_description'  => sanitize_textarea_field( $request->get_param( 'meta_description' ) ?: '' ),
			'seo_score'         => absint( $request->get_param( 'seo_score' ) ?: 0 ),
			'readability_score' => absint( $request->get_param( 'readability_score' ) ?: 0 ),
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		/** This filter is documented in update(). Context: 'create'. */
		$data = (array) apply_filters( 'qcld_wpbot_ai_seo_content_article_data', $data, $request, 'create' );

		$result = $wpdb->insert( "{$p}qcld_wpbot_ai_seo_content_articles", $data );

		if ( false === $result ) {
			return new \WP_REST_Response( array( 'message' => __( 'Failed to create article.', 'chatbot' ) ), 500 );
		}

		$article_id = (int) $wpdb->insert_id;
		Qcld_Wpbot_Ai_Seo_Content_Workflow_Controller::save_article_version( $article_id, 'Created' );

		// Log creation.
		$wpdb->insert( "{$p}qcld_wpbot_ai_seo_content_history", array(
			'article_id' => $article_id,
			'action'     => 'created',
			'details'    => wp_json_encode( array( 'topic' => $topic ) ),
			'created_at' => $now,
		) );

		self::bump_cache_version();

		return new \WP_REST_Response( array(
			'id'      => $article_id,
			'message' => __( 'Article created.', 'chatbot' ),
		), 201 );
	}

	/* ── UPDATE article ──────────────────────────────── */

	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p  = $wpdb->prefix;
		$id = absint( $request->get_param( 'id' ) );
		$now = current_time( 'mysql', true );
		$cache_key = self::build_cache_key( 'exists', array( 'id' => $id ) );
		$existing  = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false === $existing ) {
			$existing = (bool) $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$p}qcld_wpbot_ai_seo_content_articles WHERE id = %d", $id ) );
			wp_cache_set( $cache_key, $existing, self::CACHE_GROUP, self::CACHE_TTL );
		}

		if ( ! $existing ) {
			return new \WP_REST_Response( array( 'message' => __( 'Article not found.', 'chatbot' ) ), 404 );
		}

		Qcld_Wpbot_Ai_Seo_Content_Workflow_Controller::save_article_version( $id, 'Before edit' );

		$data = array( 'updated_at' => $now );

		$text_fields = array( 'title', 'topic', 'language', 'meta_title', 'post_type' );
		foreach ( $text_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = sanitize_text_field( $request->get_param( $field ) );
			}
		}

		if ( $request->has_param( 'tone' ) ) {
			$data['tone'] = qcld_wpbot_ai_seo_sanitize_tone( $request->get_param( 'tone' ) );
		}

		if ( $request->has_param( 'featured_image_url' ) ) {
			$data['featured_image_url'] = esc_url_raw( $request->get_param( 'featured_image_url' ) );
		}

		if ( $request->has_param( 'featured_image_id' ) ) {
			$data['featured_image_id'] = absint( $request->get_param( 'featured_image_id' ) ) ?: null;
		}

		if ( $request->has_param( 'title' ) ) {
			$data['slug'] = sanitize_title( $data['title'] );
		}

		if ( $request->has_param( 'content' ) ) {
			$content               = self::clean_content( $request->get_param( 'content' ) );
			$data['content']       = $content;
			$data['actual_word_count'] = function_exists( 'qcld_wpbot_ai_seo_count_words' ) ? qcld_wpbot_ai_seo_count_words( $content ) : str_word_count( wp_strip_all_tags( $content ) );
		}

		if ( $request->has_param( 'excerpt' ) ) {
			$data['excerpt'] = sanitize_textarea_field( $request->get_param( 'excerpt' ) );
		}

		if ( $request->has_param( 'meta_description' ) ) {
			$data['meta_description'] = sanitize_textarea_field( $request->get_param( 'meta_description' ) );
		}

		if ( $request->has_param( 'status' ) ) {
			$s = sanitize_text_field( $request->get_param( 'status' ) );
			if ( in_array( $s, array( 'draft', 'ready', 'review', 'scheduled', 'published', 'archived' ), true ) ) {
				$data['status'] = $s;
			}
		}

		if ( $request->has_param( 'scheduled_publish_at' ) ) {
			$data['scheduled_publish_at'] = $this->normalize_scheduled_datetime( (string) $request->get_param( 'scheduled_publish_at' ) );
		}

		$json_fields = array( 'keywords', 'category_ids', 'tag_ids', 'outline' );
		foreach ( $json_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$raw = $request->get_param( $field );

				// If the value is a JSON string, decode it first to prevent double-encoding.
				if ( is_string( $raw ) ) {
					$decoded = json_decode( $raw, true );
					if ( json_last_error() === JSON_ERROR_NONE && ( is_array( $decoded ) || is_object( $decoded ) ) ) {
						$raw = $decoded;
					}
				}

				if ( in_array( $field, array( 'keywords' ), true ) ) {
					$raw = array_map( 'sanitize_text_field', (array) $raw );
				} elseif ( 'category_ids' === $field ) {
					$raw = array_map( 'absint', (array) $raw );
				} elseif ( 'tag_ids' === $field ) {
					$raw = array_map( 'sanitize_text_field', (array) $raw );
				}
				$data[ $field ] = wp_json_encode( $raw );
			}
		}

		$int_fields = array( 'seo_score', 'readability_score', 'preset_id' );
		foreach ( $int_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = absint( $request->get_param( $field ) );
			}
		}

		if ( $request->has_param( 'word_count_target' ) ) {
			$data['word_count_target'] = absint( $request->get_param( 'word_count_target' ) );
		}

		/**
		 * Filter the columns written when an article is updated. Add-ons use
		 * this to store their own fields.
		 *
		 * @param array            $data    Column => value.
		 * @param \WP_REST_Request $request Request.
		 * @param string           $context 'update'.
		 */
		$data = (array) apply_filters( 'qcld_wpbot_ai_seo_content_article_data', $data, $request, 'update' );

		$wpdb->update( "{$p}qcld_wpbot_ai_seo_content_articles", $data, array( 'id' => $id ) );

		// Log edit.
		$wpdb->insert( "{$p}qcld_wpbot_ai_seo_content_history", array(
			'article_id' => $id,
			'action'     => 'edited',
			'details'    => wp_json_encode( array( 'fields' => array_keys( $data ) ) ),
			'created_at' => $now,
		) );

		self::bump_cache_version();

		return new \WP_REST_Response( array( 'message' => __( 'Article updated.', 'chatbot' ) ) );
	}

	/* ── DELETE article ──────────────────────────────── */

	public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
		$result = self::delete_articles( array( absint( $request->get_param( 'id' ) ) ) );
		if ( ! $result['deleted'] ) {
			return new \WP_REST_Response( array( 'message' => __( 'Article not found.', 'chatbot' ) ), 404 );
		}

		return new \WP_REST_Response( array_merge(
			array( 'message' => self::delete_message( $result ) ),
			$result
		) );
	}

	/**
	 * What a delete would touch, so the confirm dialog can name each linked
	 * WordPress post and say what happens to it. Read-only.
	 */
	public function delete_preview( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p   = $wpdb->prefix;
		$ids = array_values( array_filter( array_map( 'absint', (array) ( $request->get_param( 'ids' ) ?: array() ) ) ) );
		if ( empty( $ids ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'No items selected.', 'chatbot' ) ), 400 );
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, wp_post_id FROM {$p}qcld_wpbot_ai_seo_content_articles WHERE id IN ($placeholders)", ...$ids ) );

		$statuses = get_post_statuses();
		$items    = array();
		foreach ( (array) $rows as $row ) {
			$item = array(
				'id'      => (int) $row->id,
				'title'   => (string) $row->title,
				'wp_post' => null,
			);
			$post = ! empty( $row->wp_post_id ) ? get_post( (int) $row->wp_post_id ) : null;
			if ( $post && 'trash' !== $post->post_status ) {
				$item['wp_post'] = array(
					'id'           => (int) $post->ID,
					'title'        => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
					'status'       => $post->post_status,
					'status_label' => $statuses[ $post->post_status ] ?? ( 'future' === $post->post_status ? __( 'Scheduled', 'chatbot' ) : $post->post_status ),
				);
			}
			$items[] = $item;
		}

		return new \WP_REST_Response( array(
			'items'         => $items,
			// With EMPTY_TRASH_DAYS = 0 WordPress has no Trash, and
			// wp_trash_post() would delete permanently. Linked posts are then
			// left untouched instead.
			'trash_enabled' => self::trash_enabled(),
		) );
	}

	/**
	 * Whether WordPress keeps a Trash on this site.
	 */
	private static function trash_enabled(): bool {
		return ! defined( 'EMPTY_TRASH_DAYS' ) || EMPTY_TRASH_DAYS > 0;
	}

	/**
	 * Delete plugin articles and their history and version rows. A linked
	 * WordPress post is moved to the Trash (restorable), never deleted
	 * permanently. If the site has no Trash, the post is left as it is.
	 *
	 * @param int[] $ids Article IDs.
	 * @return array{deleted:int,trashed:int,kept:int}
	 */
	private static function delete_articles( array $ids ): array {
		global $wpdb;
		$p   = $wpdb->prefix;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$out = array( 'deleted' => 0, 'trashed' => 0, 'kept' => 0 );
		if ( empty( $ids ) ) {
			return $out;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, wp_post_id FROM {$p}qcld_wpbot_ai_seo_content_articles WHERE id IN ($placeholders)", ...$ids ) );
		if ( empty( $rows ) ) {
			return $out;
		}

		$can_trash = self::trash_enabled();
		foreach ( $rows as $row ) {
			$post_id = (int) $row->wp_post_id;
			$post    = $post_id ? get_post( $post_id ) : null;
			if ( ! $post || 'trash' === $post->post_status ) {
				continue;
			}
			if ( $can_trash && current_user_can( 'delete_post', $post_id ) && wp_trash_post( $post_id ) ) {
				$out['trashed']++;
			} else {
				$out['kept']++;
			}
		}

		$found        = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		$placeholders = implode( ',', array_fill( 0, count( $found ), '%d' ) );
		foreach ( array( 'content_articles' => 'id', 'content_history' => 'article_id', 'content_versions' => 'article_id' ) as $table => $column ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}qcld_wpbot_ai_seo_{$table} WHERE {$column} IN ($placeholders)", ...$found ) );
		}

		$out['deleted'] = count( $found );
		self::bump_cache_version();

		return $out;
	}

	/**
	 * Result message for a delete.
	 *
	 * @param array{deleted:int,trashed:int,kept:int} $r Result of delete_articles().
	 */
	private static function delete_message( array $r ): string {
		/* translators: %d: number of articles deleted */
		$msg = sprintf( _n( '%d article deleted.', '%d articles deleted.', $r['deleted'], 'chatbot' ), $r['deleted'] );
		if ( $r['trashed'] ) {
			/* translators: %d: number of WordPress posts moved to the Trash */
			$msg .= ' ' . sprintf( _n( '%d WordPress post moved to the Trash.', '%d WordPress posts moved to the Trash.', $r['trashed'], 'chatbot' ), $r['trashed'] );
		}
		if ( $r['kept'] ) {
			/* translators: %d: number of WordPress posts left in place */
			$msg .= ' ' . sprintf( _n( '%d linked WordPress post was left in place.', '%d linked WordPress posts were left in place.', $r['kept'], 'chatbot' ), $r['kept'] );
		}
		return $msg;
	}

	/* ── PUBLISH to WordPress ────────────────────────── */

	public function publish( \WP_REST_Request $request ): \WP_REST_Response {
		$id          = absint( $request->get_param( 'id' ) );
		$post_status = sanitize_text_field( $request->get_param( 'post_status' ) ?: 'draft' );
		$scheduled_at = $this->normalize_scheduled_datetime( (string) ( $request->get_param( 'scheduled_publish_at' ) ?: '' ) );

		$publisher = new Qcld_Wpbot_Ai_Seo_Content_Publisher_Service();
		$result    = $publisher->publish( $id, $post_status, $scheduled_at );
		if ( ! empty( $result['success'] ) ) {
			self::bump_cache_version();
		}
		return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
	}

	public function schedule_publish( \WP_REST_Request $request ): \WP_REST_Response {
		$id           = absint( $request->get_param( 'id' ) );
		$scheduled_at = $this->normalize_scheduled_datetime( (string) $request->get_param( 'scheduled_publish_at' ) );

		if ( ! $scheduled_at ) {
			return new \WP_REST_Response( array( 'message' => __( 'Valid schedule date and time is required.', 'chatbot' ) ), 400 );
		}

		$publisher = new Qcld_Wpbot_Ai_Seo_Content_Publisher_Service();
		$result    = $publisher->publish( $id, 'future', $scheduled_at );
		if ( ! empty( $result['success'] ) ) {
			self::bump_cache_version();
		}
		return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
	}

	private function normalize_scheduled_datetime( string $value ): string {
		$value = sanitize_text_field( $value );
		if ( ! $value ) {
			return '';
		}

		$value = str_replace( 'T', ' ', $value );
		$format = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value ) ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
		$date   = \DateTimeImmutable::createFromFormat( $format, $value, wp_timezone() );

		return $date ? $date->format( 'Y-m-d H:i:s' ) : '';
	}

	/* ── UNPUBLISH (unlink from WP post) ─────────────── */

	public function unpublish( \WP_REST_Request $request ): \WP_REST_Response {
		$id = absint( $request->get_param( 'id' ) );

		$publisher = new Qcld_Wpbot_Ai_Seo_Content_Publisher_Service();
		$result    = $publisher->unpublish( $id );
		if ( ! empty( $result['success'] ) ) {
			self::bump_cache_version();
		}
		return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
	}

	/* ── DUPLICATE article ───────────────────────────── */

	public function duplicate( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$p  = $wpdb->prefix;
		$id = absint( $request->get_param( 'id' ) );
		$now = current_time( 'mysql', true );
		$cache_key = self::build_cache_key( 'duplicate_source', array( 'id' => $id ) );
		$original  = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false === $original ) {
			$original = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}qcld_wpbot_ai_seo_content_articles WHERE id = %d", $id ), ARRAY_A );
			wp_cache_set( $cache_key, $original ?: null, self::CACHE_GROUP, self::CACHE_TTL );
		}

		if ( ! $original ) {
			return new \WP_REST_Response( array( 'message' => __( 'Article not found.', 'chatbot' ) ), 404 );
		}

		unset( $original['id'], $original['wp_post_id'], $original['published_at'] );
		$original['title']      = $original['title'] . ' (Copy)';
		$original['slug']       = sanitize_title( $original['title'] );
		$original['status']     = 'draft';
		$original['created_at'] = $now;
		$original['updated_at'] = $now;

		$wpdb->insert( "{$p}qcld_wpbot_ai_seo_content_articles", $original );
		$new_id = (int) $wpdb->insert_id;
		self::bump_cache_version();

		return new \WP_REST_Response( array(
			'id'      => $new_id,
			'message' => __( 'Article duplicated.', 'chatbot' ),
		), 201 );
	}

	/* ── BULK action ─────────────────────────────────── */

	public function bulk( \WP_REST_Request $request ): \WP_REST_Response {
		$action = sanitize_text_field( $request->get_param( 'action' ) );
		$ids    = array_map( 'absint', $request->get_param( 'ids' ) ?: array() );

		if ( empty( $ids ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'No items selected.', 'chatbot' ) ), 400 );
		}

		if ( 'delete' !== $action ) {
			return new \WP_REST_Response( array( 'message' => __( 'Unknown bulk action.', 'chatbot' ) ), 400 );
		}

		$result = self::delete_articles( $ids );

		return new \WP_REST_Response( array_merge(
			array( 'message' => self::delete_message( $result ) ),
			$result
		) );
	}
}
