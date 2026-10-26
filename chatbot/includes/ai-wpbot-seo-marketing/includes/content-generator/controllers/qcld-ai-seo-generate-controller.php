<?php
/**
 * Generate Controller — AI content generation endpoints.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: modules/content-generator/controllers/class-generate-controller.php
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

class Qcld_Wpbot_Ai_Seo_Content_Generate_Controller {

	private Qcld_Wpbot_Ai_Seo_Content_Generator_Service $generator;

	public function __construct() {
		$this->generator = new Qcld_Wpbot_Ai_Seo_Content_Generator_Service();
	}

	/* ── GENERATE full article ───────────────────────── */

	public function generate( \WP_REST_Request $request ): \WP_REST_Response {
		$word_count = absint( $request->get_param( 'word_count' ) ?: 1000 );

		$topic    = sanitize_text_field( $request->get_param( 'topic' ) );
		$keywords = array_map( 'sanitize_text_field', $request->get_param( 'keywords' ) ?: array() );
		$tone     = qcld_wpbot_ai_seo_sanitize_tone( $request->get_param( 'tone' ) ?: 'professional' );
		$language = sanitize_text_field( $request->get_param( 'language' ) ?: 'en' );
		$outline  = sanitize_textarea_field( $request->get_param( 'outline' ) ?: '' );
		$serp_context = sanitize_textarea_field( $request->get_param( 'serp_context' ) ?: '' );
		if ( $serp_context ) {
			$outline = trim( $outline . "\n\nSERP-aware brief context:\n" . $serp_context );
		}
		$post_type = sanitize_key( $request->get_param( 'post_type' ) ?: 'post' );
		if ( ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		// Load preset if specified.
		$preset_id = absint( $request->get_param( 'preset_id' ) );
		$preset    = null;
		if ( $preset_id ) {
			global $wpdb;
			$preset = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}qcld_wpbot_ai_seo_content_presets WHERE id = %d",
				$preset_id
			) );
		}

		/**
		 * Filter the outline and preset used for generation. Add-ons use this
		 * to add instructions (e.g. a table of contents, a brand voice).
		 *
		 * @param array            $args    { outline: string, preset: object|null }.
		 * @param \WP_REST_Request $request Generate request.
		 */
		$gen_args = (array) apply_filters( 'qcld_wpbot_ai_seo_content_generation_args', array( 'outline' => $outline, 'preset' => $preset ), $request );
		$outline  = (string) ( $gen_args['outline'] ?? $outline );
		$preset   = $gen_args['preset'] ?? $preset;

		// In-body stock images (site setting): the AI embeds placeholders,
		// which are swapped for imported stock photos after generation.
		$cg_settings   = get_option( 'qcld_wpbot_ai_seo_content-generator_settings', array() );
		$inline_images = min( 3, absint( $cg_settings['inline_images'] ?? 0 ) );
		if ( $inline_images > 0 && ! Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service::is_configured() ) {
			$inline_images = 0;
		}

		$include_quick_answer = $request->get_param( 'include_quick_answer' );
		$include_quick_answer = null === $include_quick_answer ? true : (bool) $include_quick_answer;

		$include_faq = $request->get_param( 'include_faq' );
		$include_faq = null === $include_faq ? true : (bool) $include_faq;

		// Regenerate in place: with article_id the existing row is updated
		// instead of inserting a new article (edit mode must not fork copies).
		$target_id = absint( $request->get_param( 'article_id' ) );
		$target    = null;
		if ( $target_id ) {
			global $wpdb;
			$target = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, status FROM {$wpdb->prefix}qcld_wpbot_ai_seo_content_articles WHERE id = %d",
				$target_id
			) );
			if ( ! $target ) {
				return new \WP_REST_Response( array( 'message' => __( 'Article not found.', 'chatbot' ) ), 404 );
			}
		}

		$result = $this->generator->generate_article(
			$topic,
			$keywords,
			$tone,
			$word_count,
			$language,
			$outline,
			$preset,
			$inline_images,
			0,
			$include_quick_answer,
			$include_faq
		);

		if ( ! $result['success'] ) {
			return new \WP_REST_Response( array(
				'message' => __( 'AI generation failed.', 'chatbot' ),
				'error'   => $result['error'] ?? '',
			), 500 );
		}

		// Save as article.
		global $wpdb;
		$p   = $wpdb->prefix;
		$now = current_time( 'mysql', true );

		$content       = $result['content'];
		$generated     = $result['parsed'] ?? array();
		$article_title = $generated['title'] ?? $topic;
		$article_body  = $generated['body'] ?? $content;
		$excerpt       = $generated['excerpt'] ?? '';

		// Clean up the body: remove any residual JSON wrapper and convert literal \n to real newlines.
		$article_body = self::clean_ai_body( $article_body, sanitize_text_field( (string) $article_title ) );

		// Swap AI image placeholders for stock photos (fail-soft; also strips
		// any leftover placeholders when the feature is off).
		$stock_service = new Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service();
		$article_body  = $stock_service->embed_inline_images( (string) $article_body, $inline_images, $topic );
		if ( empty( $keywords ) && ! empty( $generated['focus_keyword'] ) ) {
			$keywords = array( sanitize_text_field( (string) $generated['focus_keyword'] ) );
		}


		$row = array(
			'title'             => sanitize_text_field( $article_title ),
			'slug'              => sanitize_title( $article_title ),
			'content'           => qcld_wpbot_ai_seo_kses_article( $article_body ),
			'excerpt'           => '',
			'status'            => 'ready',
			'post_type'         => $post_type,
			'topic'             => $topic,
			'keywords'          => wp_json_encode( $keywords ),
			'tone'              => $tone,
			'word_count_target' => $word_count,
			'actual_word_count' => function_exists( 'qcld_wpbot_ai_seo_count_words' ) ? qcld_wpbot_ai_seo_count_words( $article_body ) : str_word_count( wp_strip_all_tags( $article_body ) ),
			'meta_title'        => '',
			'meta_description'  => '',
			'language'          => $language,
			'ai_provider'       => $result['provider'] ?? '',
			'ai_model'          => $result['model'] ?? '',
			'outline'           => wp_json_encode( $generated['outline'] ?? array() ),
			'category_ids'      => wp_json_encode( array_map( 'absint', (array) ( $request->get_param( 'category_ids' ) ?: array() ) ) ),
			'tag_ids'           => wp_json_encode( array_map( 'sanitize_text_field', (array) ( $request->get_param( 'tag_ids' ) ?: array() ) ) ),
			'preset_id'         => $preset_id ?: null,
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		/** This filter is documented in the article controller. Context: 'generate'. */
		$row = (array) apply_filters( 'qcld_wpbot_ai_seo_content_article_data', $row, $request, 'generate' );

		if ( $target ) {
			// Keep the previous text restorable from Versions, then overwrite.
			Qcld_Wpbot_Ai_Seo_Content_Workflow_Controller::save_article_version( (int) $target->id, 'Before regenerate' );
			unset( $row['created_at'] );
			// The linked WP post keeps its old content until the user publishes
			// again, so a published or scheduled article keeps that status.
			if ( in_array( (string) $target->status, array( 'draft', 'ready', 'review', 'generating', 'archived' ), true ) ) {
				$row['status'] = 'ready';
			} else {
				unset( $row['status'] );
			}
			$updated = $wpdb->update( "{$p}qcld_wpbot_ai_seo_content_articles", $row, array( 'id' => (int) $target->id ) );
			if ( false === $updated ) {
				return new \WP_REST_Response( array(
					'message' => __( 'The article was generated but could not be saved: ', 'chatbot' ) . ( $wpdb->last_error ?: __( 'unknown database error.', 'chatbot' ) ),
				), 500 );
			}
			$article_id = (int) $target->id;
		} else {
			$inserted = $wpdb->insert( "{$p}qcld_wpbot_ai_seo_content_articles", $row );

			// A failed insert must not fall through: the AI cost was already paid,
			// but returning id:0 would create a phantom history row and a broken
			// 201 response. Surface the failure instead.
			if ( false === $inserted || 0 === (int) $wpdb->insert_id ) {
				return new \WP_REST_Response( array(
					'message' => __( 'The article was generated but could not be saved: ', 'chatbot' ) . ( $wpdb->last_error ?: __( 'unknown database error.', 'chatbot' ) ),
				), 500 );
			}
			$article_id = (int) $wpdb->insert_id;
		}

		Qcld_Wpbot_Ai_Seo_Content_Workflow_Controller::save_article_version( $article_id, $target ? 'Regenerated' : 'Generated' );

		/**
		 * Fires after an article was generated and saved, before the response.
		 * Add-ons use it for follow-up AI steps (meta, excerpt, ...).
		 *
		 * @param int              $article_id Article ID.
		 * @param array            $context    { title, body, excerpt, keywords, regenerated }.
		 * @param \WP_REST_Request $request    Generate request.
		 */
		do_action( 'qcld_wpbot_ai_seo_content_article_generated', $article_id, array(
			'title'       => sanitize_text_field( (string) $article_title ),
			'body'        => qcld_wpbot_ai_seo_kses_article( $article_body ),
			'excerpt'     => (string) $excerpt,
			'keywords'    => $keywords,
			'regenerated' => (bool) $target,
		), $request );

		// Log.

		$wpdb->insert( "{$p}qcld_wpbot_ai_seo_content_history", array(
			'article_id' => $article_id,
			'action'     => 'generated',
			'details'    => wp_json_encode( array(
				'topic'     => $topic,
				'regenerated' => (bool) $target,
				'provider'  => $result['provider'] ?? '',
				'model'     => $result['model'] ?? '',
				'providers' => $result['providers'] ?? array(),
				'continued' => (int) ( $result['continued'] ?? 0 ),
				'partial'   => ! empty( $result['truncated'] ),
				'words'     => function_exists( 'qcld_wpbot_ai_seo_count_words' ) ? qcld_wpbot_ai_seo_count_words( $article_body ) : str_word_count( wp_strip_all_tags( $article_body ) ),
			) ),
			'created_at' => $now,
		) );

		// Auto SEO score: calculate baseline quick score so article has accurate initial SEO metrics.
		$seo = new Qcld_Wpbot_Ai_Seo_Content_Seo_Analyzer_Service();
		$seo->quick_score( $article_id );

		$article = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}qcld_wpbot_ai_seo_content_articles WHERE id = %d", $article_id ) );

		$continued = (int) ( $result['continued'] ?? 0 );
		$partial   = ! empty( $result['truncated'] );

		if ( $continued > 0 ) {
			qcld_wpbot_ai_seo_log( sprintf(
				'Article #%d completed across %d provider(s) after the first one stopped early.',
				$article_id,
				count( $result['providers'] ?? array() )
			), 'info', 'content-generator' );
		}

		if ( $partial ) {
			$message = __( 'Article saved, but the AI providers ran out of capacity before finishing it. Review the ending in the editor and use Improve Content to extend it.', 'chatbot' );
		} elseif ( $continued > 0 ) {
			$message = __( 'Article generated successfully (finished by a second AI provider after the first hit its limit).', 'chatbot' );
		} else {
			$message = __( 'Article generated successfully.', 'chatbot' );
		}

		return new \WP_REST_Response( array(
			'id'           => $article_id,
			'article'      => $article,
			'provider'     => $result['provider'] ?? '',
			'model'        => $result['model'] ?? '',
			'providers'    => $result['providers'] ?? array(),
			'continued'    => $continued,
			'partial'      => $partial,
			'image_search' => sanitize_text_field( (string) ( $generated['image_search'] ?? '' ) ),
			'message'      => $message,
			'regenerated'  => (bool) $target,
		), $target ? 200 : 201 );
	}

	/* ── GENERATE single section ─────────────────────── */

	public function generate_section( \WP_REST_Request $request ): \WP_REST_Response {
		$topic         = sanitize_text_field( $request->get_param( 'topic' ) );
		$section_title = sanitize_text_field( $request->get_param( 'section_title' ) );
		$context       = sanitize_textarea_field( $request->get_param( 'context' ) ?: '' );
		$tone          = sanitize_text_field( $request->get_param( 'tone' ) ?: 'professional' );

		$result = $this->generator->generate_section( $topic, $section_title, $context, $tone );

		if ( ! $result['success'] ) {
			return new \WP_REST_Response( array( 'message' => __( 'AI generation failed.', 'chatbot' ), 'error' => (string) ( $result['error'] ?? '' ) ), 500 );
		}

		return new \WP_REST_Response( $result );
	}

	/* ── IMPROVE existing content ────────────────────── */

	public function improve_content( \WP_REST_Request $request ): \WP_REST_Response {
		$content     = qcld_wpbot_ai_seo_kses_article( $request->get_param( 'content' ) );
		$instruction = sanitize_text_field( $request->get_param( 'instruction' ) );
		$tone        = sanitize_text_field( $request->get_param( 'tone' ) ?: 'professional' );
		$article_id  = absint( $request->get_param( 'article_id' ) );

		if ( $article_id > 0 ) {
			Qcld_Wpbot_Ai_Seo_Content_Workflow_Controller::save_article_version( $article_id, __( 'Before Humanize', 'chatbot' ) );
		}

		$result = $this->generator->improve_content( $content, $instruction, $tone );

		if ( ! $result['success'] ) {
			return new \WP_REST_Response( array( 'message' => __( 'AI generation failed.', 'chatbot' ), 'error' => (string) ( $result['error'] ?? '' ) ), 500 );
		}

		return new \WP_REST_Response( $result );
	}

	/* ── SEO OPTIMIZE ────────────────────────────────── */

	public function seo_optimize( \WP_REST_Request $request ): \WP_REST_Response {
		$content    = qcld_wpbot_ai_seo_kses_article( $request->get_param( 'content' ) );
		$keywords   = array_map( 'sanitize_text_field', $request->get_param( 'keywords' ) ?: array() );
		$title      = sanitize_text_field( $request->get_param( 'title' ) ?: '' );
		$article_id = absint( $request->get_param( 'article_id' ) );

		$seo    = new Qcld_Wpbot_Ai_Seo_Content_Seo_Analyzer_Service();
		$result = $seo->analyze_and_optimize( $content, $keywords, $title );

		if ( ! $result['success'] ) {
			return new \WP_REST_Response( array( 'message' => __( 'AI analysis failed.', 'chatbot' ), 'error' => (string) ( $result['error'] ?? '' ) ), 500 );
		}

		if ( $article_id > 0 && ! empty( $result['analysis'] ) && is_array( $result['analysis'] ) ) {
			global $wpdb;
			$update = array();
			if ( isset( $result['analysis']['seo_score'] ) ) {
				$update['seo_score'] = absint( $result['analysis']['seo_score'] );
			}
			if ( isset( $result['analysis']['readability_score'] ) ) {
				$update['readability_score'] = absint( $result['analysis']['readability_score'] );
			}
			if ( ! empty( $update ) ) {
				$update['updated_at'] = current_time( 'mysql', true );
				$wpdb->update(
					$wpdb->prefix . 'qcld_wpbot_ai_seo_content_articles',
					$update,
					array( 'id' => $article_id )
				);
			}
		}

		return new \WP_REST_Response( $result );
	}

	/* ── GENERATE META (title + description) ─────────── */

	public function generate_meta( \WP_REST_Request $request ): \WP_REST_Response {
		$title    = sanitize_text_field( $request->get_param( 'title' ) );
		$content  = qcld_wpbot_ai_seo_kses_article( $request->get_param( 'content' ) ?: '' );
		$keywords = array_map( 'sanitize_text_field', $request->get_param( 'keywords' ) ?: array() );

		$result = $this->generator->generate_meta( $title, $content, $keywords );

		if ( ! $result['success'] ) {
			return new \WP_REST_Response( array( 'message' => __( 'AI generation failed.', 'chatbot' ), 'error' => (string) ( $result['error'] ?? '' ) ), 500 );
		}

		return new \WP_REST_Response( $result );
	}

	/* ── AI OUTPUT CLEANUP ───────────────────────────── */

	/**
	 * Clean up raw AI body output. Call this ONCE, on fresh model output at
	 * generation time. Never run it on stored content (article view, publish):
	 * stored HTML is the user's, and may legitimately contain backslashes or
	 * JSON (code samples, Windows paths).
	 *
	 * Unwraps a JSON envelope the model may have put around the body, decodes
	 * JSON escapes only when the body came out of such an envelope, strips
	 * AI safety-classification lines ("User Safety: safe"), drops a duplicate
	 * title heading (the theme renders the H1), and wraps bare text.
	 *
	 * @param string $body  Raw body from the model.
	 * @param string $title Optional article title for duplicate-H1 stripping.
	 */
	public static function clean_ai_body( string $body, string $title = '' ): string {
		$body = trim( $body );

		// Remove a Markdown code fence wrapping the whole response (```json ... ```).
		if ( preg_match( '/^```(?:json|html)?\s*([\s\S]*?)\s*```$/i', $body, $fence ) ) {
			$body = trim( $fence[1] );
		}

		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) && isset( $decoded['body'] ) && is_string( $decoded['body'] ) ) {
			// A complete JSON envelope: json_decode already unescaped the body.
			$body = $decoded['body'];
		} elseif ( preg_match( '/^\{\s*(?:"[^"]*"\s*:\s*"(?:[^"\\\\]|\\\\.)*"\s*,\s*)*"body"\s*:\s*"/s', $body, $prefix ) ) {
			// A broken or cut-off envelope: take the body string value and
			// decode its JSON escapes. Only here, where we know it is a JSON
			// string, are the trailing-key and closing-brace cuts safe.
			$body = substr( $body, strlen( $prefix[0] ) );
			$body = (string) preg_replace( '/"\s*,\s*"(?:excerpt|outline|title|tags|focus_keyword|meta_title|meta_description|image_search)"\s*:[\s\S]*$/i', '', $body );
			$body = (string) preg_replace( '/"\s*\}\s*$/', '', $body );
			$body = qcld_wpbot_ai_seo_json_unescape( $body );
		} else {
			// Plain HTML. Some models double-encode line breaks between tags
			// (<p>a</p>\n<p>b</p> with a literal backslash-n). Fix only those
			// spots between tags; text and code inside elements is left alone.
			$body = (string) preg_replace( '/>(?:\\\\[nrt])+(?=\s*<)/', ">\n", $body );
		}

		// Strip Google Translate injected divs.
		$body = (string) preg_replace( '/<div\b[^>]*id\s*=\s*["\']gtx-trans["\'][^>]*>.*?<\/div>/si', '', $body );
		$body = (string) preg_replace( '/<div\b[^>]*class\s*=\s*["\']gtx-[^"\']*["\'][^>]*>.*?<\/div>/si', '', $body );

		// Strip AI safety-classification lines that some Gemini-family models
		// append (e.g. "User Safety: safe"). Whole lines only.
		$body = (string) preg_replace( '/^[ \t]*(?:User\s+Safety|Safety|Harm\s+Category|Harm_Policy|Category|Severity)[ \t]*:[ \t]*[^\n\r]*(?:\r\n|\n|\r)?/im', '', $body );

		$body = trim( $body );

		// Single-H1 rule: drop a leading duplicate title heading when the
		// title is known (theme renders H1; body must not repeat it).
		if ( '' !== $title && '' !== $body ) {
			$body = Qcld_Wpbot_Ai_Seo_Content_Generator_Service::strip_duplicate_title_heading( $body, $title );
		}

		// If any raw text remains un-wrapped, let wpautop handle it.
		if ( $body && ! preg_match( '/^\s*</', $body ) ) {
			$body = wpautop( $body );
		}

		return $body;
	}

	/* ── STOCK IMAGES (Pexels / Pixabay) ─────────────── */

	public function stock_search( \WP_REST_Request $request ): \WP_REST_Response {
		$service = new Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service();

		$result = $service->search(
			sanitize_text_field( $request->get_param( 'query' ) ),
			absint( $request->get_param( 'per_page' ) ?: 12 ),
			absint( $request->get_param( 'page' ) ?: 1 )
		);

		if ( empty( $result['success'] ) ) {
			return new \WP_REST_Response( array(
				'message'        => $result['error'] ?? __( 'Stock image search failed.', 'chatbot' ),
				'not_configured' => ! empty( $result['not_configured'] ),
			), ! empty( $result['not_configured'] ) ? 400 : 502 );
		}

		return new \WP_REST_Response( $result );
	}

	public function stock_import( \WP_REST_Request $request ): \WP_REST_Response {
		$service = new Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service();

		$url        = esc_url_raw( $request->get_param( 'url' ) );
		$alt        = sanitize_text_field( $request->get_param( 'alt' ) ?: '' );
		$article_id = absint( $request->get_param( 'article_id' ) );

		$result = $service->import_to_media_library( $url, $alt, 0 );

		if ( empty( $result['success'] ) ) {
			return new \WP_REST_Response( array(
				'message' => $result['error'] ?? __( 'Image import failed.', 'chatbot' ),
			), 400 );
		}

		// Update the article if an id is provided.
		if ( $article_id ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'qcld_wpbot_ai_seo_content_articles',
				array(
					'featured_image_url' => $result['url'],
					'featured_image_id'  => $result['attachment_id'],
				),
				array( 'id' => $article_id ),
				array( '%s', '%d' ),
				array( '%d' )
			);
		}

		return new \WP_REST_Response( array(
			'attachment_id' => $result['attachment_id'],
			'url'           => $result['url'],
		) );
	}
}
