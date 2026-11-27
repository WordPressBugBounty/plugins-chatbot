<?php
/**
 * Help Center tools for the owner's own AI assistant (HC3-A, plan 6.1, route A).
 *
 * Claude (or another assistant) connects over MCP (Server, or the WordPress MCP Adapter through
 * Abilities), reads a GitHub repository with its own access and builds the help center with
 * these tools. Owner decisions, 20 November 2026:
 * - What the AI writes becomes a draft for review, or is published at once when the owner
 *   chose auto-publish (Settings::ai_publish()).
 * - Knowledge bases, categories and articles are matched by slug: running again never makes
 *   duplicates.
 * - The AI never deletes. It can only suggest unpublishing.
 * - When a person edited an article after the AI's last write, or the article is published and
 *   the owner reviews AI changes, the change is kept as a suggestion to apply or dismiss.
 * - Every write is logged (Change_Log) and rate-limited per user.
 *
 * Access follows the person's WordPress account: reading needs "edit_wpbot_articles", writing
 * structure needs "manage_wpbot_article_terms", publishing needs "publish_wpbot_articles".
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Mcp;

use QuantumCloud\HelpCenter\Admin\Order;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;
use QuantumCloud\HelpCenter\Settings;
use QuantumCloud\HelpCenter\Style_Guide;

defined( 'ABSPATH' ) || exit;

/**
 * Tool definitions and handlers.
 */
final class Tools {

	/**
	 * Meta: a suggested change kept for review.
	 */
	const PROPOSAL = '_wpbot_ai_proposal';

	/**
	 * Meta: a suggestion to unpublish.
	 */
	const UNPUBLISH = '_wpbot_ai_unpublish';

	/**
	 * Writes per user per hour.
	 */
	const RATE = 600;

	/**
	 * True while a tool writes (so the human-edit guard ignores the save).
	 *
	 * @var bool
	 */
	private static $writing = false;

	/**
	 * Hook in (only while the Help Center is on).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'save_post_' . Post_Type::NAME, array( self::class, 'guard' ), 10, 2 );
	}

	/**
	 * A person's save after the AI wrote the article marks it as edited by a person (when the
	 * title or text differs from what the AI wrote).
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function guard( $post_id, $post ) {
		if ( self::$writing || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$last = get_post_meta( $post_id, '_wpbot_ai_last_write', true );
		if ( ! is_array( $last ) || empty( $last['hash'] ) ) {
			return;
		}
		if ( self::hash( $post->post_title, $post->post_content ) !== $last['hash'] ) {
			update_post_meta( $post_id, '_wpbot_human_edited', true );
		}
	}

	/**
	 * Fingerprint of an article's title and text.
	 *
	 * @param string $title   Title.
	 * @param string $content Content.
	 * @return string
	 */
	public static function hash( $title, $content ) {
		return md5( trim( (string) $title ) . "\n" . trim( (string) $content ) );
	}

	/**
	 * Tool definitions: name => description, input (JSON schema), write, cap.
	 *
	 * @return array<string,array>
	 */
	public static function definitions() {
		$slug    = array(
			'type'        => 'string',
			'description' => 'URL slug (lowercase, hyphens). Used to find the item again, so the same slug updates instead of duplicating.',
		);
		$slugs   = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
		$source  = array(
			'type'        => 'object',
			'description' => 'Where the text comes from, for example the GitHub file.',
			'properties'  => array(
				'repo'   => array(
					'type'        => 'string',
					'description' => 'owner/name',
				),
				'path'   => array( 'type' => 'string' ),
				'commit' => array( 'type' => 'string' ),
				'url'    => array( 'type' => 'string' ),
			),
		);
		$publish = 'autopilot' === Settings::ai_mode() ? 'The owner chose Autopilot: new articles are published and changes apply at once (the owner can undo them).' : 'Review mode: new articles are saved as drafts for the owner to review.';
		return array(
			'helpcenter_get_structure'         => array(
				'description' => 'Read the whole help center: knowledge bases, the category tree, every article (title, slug, status, categories, order, source) and the publishing setting. Call this first.',
				'input'       => self::object( array() ),
				'write'       => false,
				'cap'         => 'edit_wpbot_articles',
			),
			'helpcenter_get_style_guide'       => array(
				'description' => 'The owner\'s style guide for help articles: audience, tone, words to use and avoid, how settings paths and screenshots are written, and a template per article type. Read it before you write, and follow it.',
				'input'       => self::object(
					array(
						'type' => array(
							'type'        => 'string',
							'enum'        => Style_Guide::TYPES,
							'description' => 'Only the template for this article type.',
						),
					)
				),
				'write'       => false,
				'cap'         => 'edit_wpbot_articles',
			),
			'helpcenter_search'                => array(
				'description' => 'Search articles (any status except trash) by words in the title or text.',
				'input'       => self::object(
					array(
						'query' => array( 'type' => 'string' ),
						'limit' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
						),
					),
					array( 'query' )
				),
				'write'       => false,
				'cap'         => 'edit_wpbot_articles',
			),
			'helpcenter_get_article'           => array(
				'description' => 'Read one article by slug, with its text as Markdown.',
				'input'       => self::object( array( 'slug' => $slug ), array( 'slug' ) ),
				'write'       => false,
				'cap'         => 'edit_wpbot_articles',
			),
			'helpcenter_upsert_knowledge_base' => array(
				'description' => 'Create or update a knowledge base (a separate set of docs, for example one per product), matched by slug.',
				'input'       => self::object(
					array(
						'slug'        => $slug,
						'name'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
					),
					array( 'slug', 'name' )
				),
				'write'       => true,
				'cap'         => 'manage_wpbot_article_terms',
			),
			'helpcenter_upsert_category'       => array(
				'description' => 'Create or update a category (a topic, can be nested), matched by slug.',
				'input'       => self::object(
					array(
						'slug'        => $slug,
						'name'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'parent'      => array(
							'type'        => 'string',
							'description' => 'Slug of the parent category (create it first).',
						),
						'order'       => array( 'type' => 'integer' ),
					),
					array( 'slug', 'name' )
				),
				'write'       => true,
				'cap'         => 'manage_wpbot_article_terms',
			),
			'helpcenter_upsert_article'        => array(
				'description' => 'Create or update an article, matched by slug. Write the text in Markdown (headings from ##, lists, code, tables, images; "> [!NOTE]", "> [!TIP]" and "> [!WARNING]" become callouts). Do not repeat the title as a heading. ' . $publish . ' If a person edited the article, or it is published and the owner reviews AI changes, the change waits for the owner\'s approval.',
				'input'       => self::object(
					array(
						'slug'            => $slug,
						'title'           => array( 'type' => 'string' ),
						'content'         => array(
							'type'        => 'string',
							'description' => 'Markdown (or HTML when format is "html").',
						),
						'format'          => array(
							'type' => 'string',
							'enum' => array( 'markdown', 'html' ),
						),
						'excerpt'         => array(
							'type'        => 'string',
							'description' => 'One or two sentences: what the article answers.',
						),
						'categories'      => array_merge( $slugs, array( 'description' => 'Category slugs (create them first).' ) ),
						'knowledge_bases' => array_merge( $slugs, array( 'description' => 'Knowledge base slugs.' ) ),
						'tags'            => array_merge( $slugs, array( 'description' => 'Tag names.' ) ),
						'parent'          => array(
							'type'        => 'string',
							'description' => 'Slug of a parent article, for sub-pages.',
						),
						'order'           => array( 'type' => 'integer' ),
						'type'            => array(
							'type'        => 'string',
							'enum'        => Style_Guide::TYPES,
							'description' => 'The kind of article; follow that template from the style guide.',
						),
						'source'          => $source,
					),
					array( 'slug', 'title', 'content' )
				),
				'write'       => true,
				'cap'         => 'edit_wpbot_articles',
			),
			'helpcenter_set_order'             => array(
				'description' => 'Set the order of articles and categories (lower numbers first).',
				'input'       => self::object(
					array(
						'articles'   => array(
							'type'  => 'array',
							'items' => self::object(
								array(
									'slug'  => array( 'type' => 'string' ),
									'order' => array( 'type' => 'integer' ),
								),
								array( 'slug', 'order' )
							),
						),
						'categories' => array(
							'type'  => 'array',
							'items' => self::object(
								array(
									'slug'  => array( 'type' => 'string' ),
									'order' => array( 'type' => 'integer' ),
								),
								array( 'slug', 'order' )
							),
						),
					)
				),
				'write'       => true,
				'cap'         => 'manage_wpbot_article_terms',
			),
			'helpcenter_list_gaps'             => array(
				'description' => 'Questions visitors asked that the chatbot or the help search could not answer: good topics for new articles.',
				'input'       => self::object(
					array(
						'limit' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
						),
					)
				),
				'write'       => false,
				'cap'         => 'edit_wpbot_articles',
			),
			'helpcenter_propose_unpublish'     => array(
				'description' => 'Suggest that the owner unpublishes an article (for example, the feature was removed). Nothing is deleted or unpublished by this tool.',
				'input'       => self::object(
					array(
						'slug'   => $slug,
						'reason' => array( 'type' => 'string' ),
					),
					array( 'slug', 'reason' )
				),
				'write'       => true,
				'cap'         => 'edit_wpbot_articles',
			),
			'helpcenter_get_changes'           => array(
				'description' => 'The log of changes AI assistants made, newest first.',
				'input'       => self::object(
					array(
						'limit' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 300,
						),
					)
				),
				'write'       => false,
				'cap'         => 'edit_wpbot_articles',
			),
		);
	}

	/**
	 * Object schema.
	 *
	 * @param array    $properties Properties.
	 * @param string[] $required   Required.
	 * @return array
	 */
	private static function object( array $properties, array $required = array() ) {
		$schema = array(
			'type'       => 'object',
			'properties' => (object) $properties,
		);
		if ( $required ) {
			$schema['required'] = $required;
		}
		return $schema;
	}

	/**
	 * Whether the current user may use a tool.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	public static function allowed( $name ) {
		$defs = self::definitions();
		return isset( $defs[ $name ] ) && current_user_can( $defs[ $name ]['cap'] );
	}

	/**
	 * Run a tool.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Arguments.
	 * @return array|\WP_Error Result data.
	 */
	public static function call( $name, array $args ) {
		$defs = self::definitions();
		if ( ! isset( $defs[ $name ] ) ) {
			return new \WP_Error( 'unknown_tool', 'Unknown tool: ' . $name );
		}
		if ( ! current_user_can( $defs[ $name ]['cap'] ) ) {
			return new \WP_Error( 'forbidden', 'Your WordPress account may not use ' . $name . '.' );
		}
		foreach ( isset( $defs[ $name ]['input']['required'] ) ? $defs[ $name ]['input']['required'] : array() as $key ) {
			if ( ! isset( $args[ $key ] ) || '' === $args[ $key ] ) {
				return new \WP_Error( 'missing', 'Missing argument: ' . $key );
			}
		}
		if ( $defs[ $name ]['write'] && ! self::within_rate() ) {
			return new \WP_Error( 'rate_limited', 'Too many changes in the last hour. Try again later.' );
		}
		$method        = substr( $name, strlen( 'helpcenter_' ) );
		self::$writing = true;
		try {
			$result = call_user_func( array( self::class, $method ), $args );
		} finally {
			self::$writing = false;
		}
		return $result;
	}

	/**
	 * Count a write against the user's hourly limit.
	 *
	 * @return bool False when over the limit.
	 */
	private static function within_rate() {
		$key   = 'qcld_wpbot_hc_mcp_rate_' . get_current_user_id();
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * The whole structure.
	 *
	 * @return array
	 */
	private static function get_structure() {
		$out = array(
			'publish_mode'    => Settings::ai_publish(),
			'mode'            => Settings::ai_mode(),
			'address'         => home_url( '/' . Settings::base() . '/' ),
			'knowledge_bases' => array(),
			'categories'      => array(),
			'articles'        => array(),
		);
		foreach ( self::terms( Taxonomies::KB ) as $term ) {
			$out['knowledge_bases'][] = array(
				'slug'        => $term->slug,
				'name'        => $term->name,
				'description' => $term->description,
				'articles'    => (int) $term->count,
			);
		}
		foreach ( self::terms( Taxonomies::CATEGORY ) as $term ) {
			$parent              = $term->parent ? get_term( $term->parent, Taxonomies::CATEGORY ) : null;
			$out['categories'][] = array(
				'slug'        => $term->slug,
				'name'        => $term->name,
				'description' => $term->description,
				'parent'      => $parent && ! is_wp_error( $parent ) ? $parent->slug : '',
				'order'       => (int) get_term_meta( $term->term_id, Order::TERM_META, true ),
				'articles'    => (int) $term->count,
			);
		}
		$posts = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- The whole help center for the assistant, admin only.
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			$out['articles'][] = self::summary( $post );
		}
		return $out;
	}

	/**
	 * Terms of a taxonomy (empty ones too).
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return \WP_Term[]
	 */
	private static function terms( $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Short facts about an article.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private static function summary( $post ) {
		$parent = $post->post_parent ? get_post( $post->post_parent ) : null;
		$source = get_post_meta( $post->ID, '_wpbot_source', true );
		return array(
			'id'               => (int) $post->ID,
			'slug'             => $post->post_name,
			'title'            => get_the_title( $post ),
			'status'           => $post->post_status,
			'parent'           => $parent ? $parent->post_name : '',
			'order'            => (int) $post->menu_order,
			'categories'       => wp_get_post_terms( $post->ID, Taxonomies::CATEGORY, array( 'fields' => 'slugs' ) ),
			'knowledge_bases'  => wp_get_post_terms( $post->ID, Taxonomies::KB, array( 'fields' => 'slugs' ) ),
			'source'           => is_array( $source ) ? $source : null,
			'written_by_ai'    => is_array( get_post_meta( $post->ID, '_wpbot_ai_last_write', true ) ),
			'edited_by_person' => (bool) get_post_meta( $post->ID, '_wpbot_human_edited', true ),
			'has_suggestion'   => is_array( get_post_meta( $post->ID, self::PROPOSAL, true ) ),
			'modified'         => get_post_modified_time( 'c', true, $post ),
			'link'             => get_permalink( $post ),
		);
	}

	/**
	 * Search.
	 *
	 * @param array $args query, limit.
	 * @return array
	 */
	private static function search( array $args ) {
		$posts = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				's'              => sanitize_text_field( (string) $args['query'] ),
				'posts_per_page' => isset( $args['limit'] ) ? max( 1, min( 50, (int) $args['limit'] ) ) : 10,
				'no_found_rows'  => true,
			)
		);
		$out   = array();
		foreach ( $posts as $post ) {
			$item            = self::summary( $post );
			$item['excerpt'] = wp_trim_words( wp_strip_all_tags( has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content ) ), 40 );
			$out[]           = $item;
		}
		return array( 'articles' => $out );
	}

	/**
	 * One article.
	 *
	 * @param array $args slug.
	 * @return array|\WP_Error
	 */
	private static function get_article( array $args ) {
		$post = self::find( (string) $args['slug'] );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', 'No article with the slug ' . sanitize_title( (string) $args['slug'] ) . '.' );
		}
		$item             = self::summary( $post );
		$item['excerpt']  = $post->post_excerpt;
		$item['tags']     = wp_get_post_terms( $post->ID, Taxonomies::TAG, array( 'fields' => 'names' ) );
		$item['markdown'] = \QuantumCloud\HelpCenter\Seo\Markdown::from_html( do_blocks( $post->post_content ) );
		return $item;
	}

	/**
	 * Find an article by slug (any status but trash).
	 *
	 * @param string $slug Slug.
	 * @return \WP_Post|null
	 */
	public static function find( $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'name'           => $slug,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $posts ? $posts[0] : null;
	}

	/**
	 * Create or update a term by slug.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param array  $args     slug, name, description, parent.
	 * @return array|\WP_Error
	 */
	private static function upsert_term( $taxonomy, array $args ) {
		$slug   = sanitize_title( (string) $args['slug'] );
		$name   = sanitize_text_field( (string) $args['name'] );
		$fields = array(
			'description' => isset( $args['description'] ) ? sanitize_textarea_field( (string) $args['description'] ) : '',
			'slug'        => $slug,
		);
		if ( isset( $args['parent'] ) && '' !== (string) $args['parent'] && is_taxonomy_hierarchical( $taxonomy ) ) {
			$parent = get_term_by( 'slug', sanitize_title( (string) $args['parent'] ), $taxonomy );
			if ( ! $parent ) {
				return new \WP_Error( 'no_parent', 'Create the parent first: ' . sanitize_title( (string) $args['parent'] ) );
			}
			$fields['parent'] = (int) $parent->term_id;
		}
		$existing = get_term_by( 'slug', $slug, $taxonomy );
		if ( $existing ) {
			$fields['name'] = $name;
			$result         = wp_update_term( $existing->term_id, $taxonomy, $fields );
			$action         = 'updated';
		} else {
			$result = wp_insert_term( $name, $taxonomy, $fields );
			$action = 'created';
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id = (int) $result['term_id'];
		if ( isset( $args['order'] ) ) {
			update_term_meta( $id, Order::TERM_META, (int) $args['order'] );
		}
		Change_Log::add( $action, Taxonomies::KB === $taxonomy ? 'knowledge_base' : 'category', $slug, $id, $name );
		return array(
			'result' => $action,
			'id'     => $id,
			'slug'   => $slug,
		);
	}

	/**
	 * Knowledge base.
	 *
	 * @param array $args Arguments.
	 * @return array|\WP_Error
	 */
	private static function upsert_knowledge_base( array $args ) {
		unset( $args['parent'] );
		return self::upsert_term( Taxonomies::KB, $args );
	}

	/**
	 * Category.
	 *
	 * @param array $args Arguments.
	 * @return array|\WP_Error
	 */
	private static function upsert_category( array $args ) {
		return self::upsert_term( Taxonomies::CATEGORY, $args );
	}

	/**
	 * Term ids from slugs (or names for tags); missing categories and knowledge bases are errors.
	 *
	 * @param mixed  $values   Slugs.
	 * @param string $taxonomy Taxonomy.
	 * @return int[]|string[]|\WP_Error
	 */
	private static function term_ids( $values, $taxonomy ) {
		$out = array();
		foreach ( array_filter( array_map( 'strval', (array) $values ), 'strlen' ) as $value ) {
			if ( Taxonomies::TAG === $taxonomy ) {
				$out[] = sanitize_text_field( $value );
				continue;
			}
			$term = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
			if ( ! $term ) {
				return new \WP_Error( 'no_term', 'Create it first: ' . sanitize_title( $value ) . ' (' . ( Taxonomies::KB === $taxonomy ? 'knowledge base' : 'category' ) . ')' );
			}
			$out[] = (int) $term->term_id;
		}
		return $out;
	}

	/**
	 * Create or update an article.
	 *
	 * @param array $args Arguments.
	 * @return array|\WP_Error
	 */
	private static function upsert_article( array $args ) {
		$slug    = sanitize_title( (string) $args['slug'] );
		$title   = sanitize_text_field( (string) $args['title'] );
		$format  = isset( $args['format'] ) && 'html' === $args['format'] ? 'html' : 'markdown';
		$content = 'html' === $format ? wp_kses_post( (string) $args['content'] ) : Markdown_Import::to_blocks( (string) $args['content'] );
		$terms   = array();
		foreach ( array(
			'categories'      => Taxonomies::CATEGORY,
			'knowledge_bases' => Taxonomies::KB,
			'tags'            => Taxonomies::TAG,
		) as $key => $taxonomy ) {
			if ( isset( $args[ $key ] ) ) {
				$ids = self::term_ids( $args[ $key ], $taxonomy );
				if ( is_wp_error( $ids ) ) {
					return $ids;
				}
				$terms[ $taxonomy ] = $ids;
			}
		}
		$parent_id = 0;
		if ( ! empty( $args['parent'] ) ) {
			$parent = self::find( (string) $args['parent'] );
			if ( ! $parent ) {
				return new \WP_Error( 'no_parent', 'Create the parent article first: ' . sanitize_title( (string) $args['parent'] ) );
			}
			$parent_id = (int) $parent->ID;
		}
		$source  = isset( $args['source'] ) && is_array( $args['source'] ) ? self::clean_source( $args['source'] ) : null;
		$excerpt = isset( $args['excerpt'] ) ? sanitize_textarea_field( (string) $args['excerpt'] ) : null;
		$type    = isset( $args['type'] ) && in_array( $args['type'], Style_Guide::TYPES, true ) ? (string) $args['type'] : '';
		$post    = self::find( $slug );
		$auto    = 'autopilot' === Settings::ai_mode() && current_user_can( 'publish_wpbot_articles' );
		$human   = $post && get_post_meta( $post->ID, '_wpbot_human_edited', true );
		$queue   = Review::active();

		if ( $post && $queue && ( $human || 'publish' === $post->post_status ) ) {
			// A published article, or one a person edited: through the chatbot's approval queue.
			$out = Review::propose(
				'help_article_update',
				$post,
				array(
					'title'   => $title,
					'blocks'  => $content,
					'content' => Review::preview( $content ),
					'excerpt' => $excerpt,
					'source'  => $source,
					'type'    => $type,
					'human'   => (bool) $human,
				),
				/* translators: %s: article title. */
				sprintf( __( 'New version of "%s" (from Claude)', 'chatbot' ), $title )
			);
			if ( is_wp_error( $out ) ) {
				return $out;
			}
			if ( 'applied' === $out['status'] ) {
				return array(
					'result' => 'updated',
					'id'     => (int) $post->ID,
					'slug'   => $slug,
					'status' => get_post_status( $post->ID ),
					'note'   => 'Autopilot applied the change; the owner can undo it in ChatBot → Train → Changes.',
				);
			}
			Change_Log::add( 'suggested', 'article', $slug, $post->ID, $human ? 'edited by a person' : 'published; the owner reviews AI changes' );
			return array(
				'result'    => 'suggested',
				'id'        => (int) $post->ID,
				'slug'      => $slug,
				'change_id' => (int) $out['change_id'],
				'note'      => 'Waiting for the owner in ChatBot → Train → Changes (' . ( $human ? 'a person edited this article' : 'the owner reviews changes to published articles' ) . ').',
			);
		}//end if

		if ( $post && ! $queue && ( $human || ( 'publish' === $post->post_status && ! $auto ) ) ) {
			// Keep as a suggestion: a person edited it, or the owner reviews changes to published articles.
			update_post_meta(
				$post->ID,
				self::PROPOSAL,
				array(
					'title'   => $title,
					'content' => $content,
					'excerpt' => $excerpt,
					'source'  => $source,
					'time'    => time(),
					'user'    => get_current_user_id(),
				)
			);
			$why = $human ? 'edited by a person' : 'published; the owner reviews AI changes';
			Change_Log::add( 'suggested', 'article', $slug, $post->ID, $why );
			return array(
				'result' => 'suggested',
				'id'     => (int) $post->ID,
				'slug'   => $slug,
				'note'   => 'Kept as a suggestion for the owner (' . $why . ').',
			);
		}//end if
		if ( $post && ! current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error( 'forbidden', 'Your WordPress account may not edit ' . $slug . '.' );
		}

		$data = array(
			'post_type'    => Post_Type::NAME,
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
		);
		if ( null !== $excerpt ) {
			$data['post_excerpt'] = $excerpt;
		}
		if ( isset( $args['parent'] ) ) {
			$data['post_parent'] = $parent_id;
		}
		if ( isset( $args['order'] ) ) {
			$data['menu_order'] = (int) $args['order'];
		}
		$direct = $auto && ! $queue;
		// Without the queue, Autopilot publishes here; with it, through the queue below.
		if ( $post ) {
			$data['ID'] = (int) $post->ID;
			if ( $direct && 'publish' !== $post->post_status ) {
				$data['post_status'] = 'publish';
			}
			$id     = wp_update_post( wp_slash( $data ), true );
			$action = 'updated';
		} else {
			$data['post_status'] = $direct ? 'publish' : 'draft';
			$data['post_author'] = get_current_user_id();
			$id                  = wp_insert_post( wp_slash( $data ), true );
			$action              = 'created';
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		foreach ( $terms as $taxonomy => $ids ) {
			wp_set_object_terms( $id, $ids, $taxonomy );
		}
		if ( null !== $source ) {
			update_post_meta( $id, '_wpbot_source', $source );
		}
		if ( '' !== $type ) {
			update_post_meta( $id, '_wpbot_article_type', $type );
		}
		update_post_meta(
			$id,
			'_wpbot_ai_last_write',
			array(
				'time' => time(),
				'user' => get_current_user_id(),
				'hash' => self::hash( $title, $content ),
			)
		);
		delete_post_meta( $id, '_wpbot_human_edited' );
		delete_post_meta( $id, self::PROPOSAL );
		Change_Log::add( $action, 'article', $slug, $id, $title );
		if ( $auto && $queue && 'publish' !== get_post_status( $id ) ) {
			// Autopilot: publish through the queue, so the owner can undo it.
			Review::propose(
				'help_article_create',
				get_post( $id ),
				array( 'content' => Review::preview( $content ) ),
				/* translators: %s: article title. */
				sprintf( __( 'Publish "%s" (new from Claude)', 'chatbot' ), $title )
			);
		}
		return array(
			'result' => $action,
			'id'     => (int) $id,
			'slug'   => get_post_field( 'post_name', $id ),
			'status' => get_post_status( $id ),
			'edit'   => admin_url( 'post.php?post=' . (int) $id . '&action=edit' ),
		);
	}

	/**
	 * The style guide.
	 *
	 * @param array $args type.
	 * @return array
	 */
	private static function get_style_guide( array $args ) {
		$type  = isset( $args['type'] ) && in_array( $args['type'], Style_Guide::TYPES, true ) ? (string) $args['type'] : '';
		$guide = Style_Guide::get();
		unset( $guide['prefilled'] );
		if ( '' !== $type ) {
			$guide['templates'] = array( $type => $guide['templates'][ $type ] );
		}
		return array(
			'style_guide' => $guide,
			'text'        => Style_Guide::text( $type ),
		);
	}

	/**
	 * Clean source facts.
	 *
	 * @param array $source repo, path, commit, url.
	 * @return array
	 */
	private static function clean_source( array $source ) {
		return array(
			'type'   => 'github',
			'repo'   => isset( $source['repo'] ) ? substr( sanitize_text_field( (string) $source['repo'] ), 0, 200 ) : '',
			'path'   => isset( $source['path'] ) ? substr( sanitize_text_field( (string) $source['path'] ), 0, 300 ) : '',
			'commit' => isset( $source['commit'] ) ? substr( preg_replace( '/[^0-9a-f]/i', '', (string) $source['commit'] ), 0, 40 ) : '',
			'url'    => isset( $source['url'] ) ? esc_url_raw( (string) $source['url'] ) : '',
		);
	}

	/**
	 * Order.
	 *
	 * @param array $args articles, categories.
	 * @return array
	 */
	private static function set_order( array $args ) {
		$done = 0;
		foreach ( isset( $args['articles'] ) ? (array) $args['articles'] : array() as $item ) {
			$post = is_array( $item ) && isset( $item['slug'] ) ? self::find( (string) $item['slug'] ) : null;
			if ( $post && current_user_can( 'edit_post', $post->ID ) ) {
				wp_update_post(
					array(
						'ID'         => $post->ID,
						'menu_order' => (int) $item['order'],
					)
				);
				++$done;
			}
		}
		foreach ( isset( $args['categories'] ) ? (array) $args['categories'] : array() as $item ) {
			$term = is_array( $item ) && isset( $item['slug'] ) ? get_term_by( 'slug', sanitize_title( (string) $item['slug'] ), Taxonomies::CATEGORY ) : null;
			if ( $term ) {
				update_term_meta( $term->term_id, Order::TERM_META, (int) $item['order'] );
				++$done;
			}
		}
		Change_Log::add( 'ordered', 'article', '', 0, $done . ' items' );
		return array( 'ordered' => $done );
	}

	/**
	 * Knowledge gaps (from the chatbot's Train screen).
	 *
	 * @param array $args limit.
	 * @return array
	 */
	private static function list_gaps( array $args ) {
		$limit = isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 30;
		if ( ! class_exists( '\QuantumCloud\Chatbot\Train\Gaps' ) || ! method_exists( '\QuantumCloud\Chatbot\Train\Gaps', 'items' ) ) {
			return array(
				'gaps' => array(),
				'note' => 'The chatbot module is not available.',
			);
		}
		$page = (array) \QuantumCloud\Chatbot\Train\Gaps::items( 'open', 1, $limit );
		$out  = array();
		foreach ( isset( $page['items'] ) ? (array) $page['items'] : array() as $item ) {
			$item  = (array) $item;
			$out[] = array(
				'question' => isset( $item['question'] ) ? (string) $item['question'] : '',
				'reason'   => isset( $item['reason'] ) ? (string) $item['reason'] : '',
				'asked'    => isset( $item['hits'] ) ? (int) $item['hits'] : 1,
			);
		}
		return array( 'gaps' => $out );
	}

	/**
	 * Suggest unpublishing.
	 *
	 * @param array $args slug, reason.
	 * @return array|\WP_Error
	 */
	private static function propose_unpublish( array $args ) {
		$post = self::find( (string) $args['slug'] );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', 'No article with the slug ' . sanitize_title( (string) $args['slug'] ) . '.' );
		}
		$reason = substr( sanitize_text_field( (string) $args['reason'] ), 0, 300 );
		if ( Review::active() ) {
			$out = Review::propose(
				'help_article_unpublish',
				$post,
				array(
					'reason'  => $reason,
					'content' => $reason,
				),
				/* translators: %s: article title. */
				sprintf( __( 'Unpublish "%s" (Claude suggests)', 'chatbot' ), $post->post_title )
			);
			if ( is_wp_error( $out ) ) {
				return $out;
			}
			if ( 'applied' === $out['status'] ) {
				return array(
					'result' => 'unpublished',
					'note'   => 'The owner lets Autopilot unpublish: the article is a draft now and can be undone in ChatBot → Train → Changes.',
				);
			}
			Change_Log::add( 'unpublish_suggested', 'article', $post->post_name, $post->ID, $reason );
			return array(
				'result'    => 'suggested',
				'change_id' => (int) $out['change_id'],
				'note'      => 'The owner will decide in ChatBot → Train → Changes. Nothing was unpublished.',
			);
		}//end if
		update_post_meta(
			$post->ID,
			self::UNPUBLISH,
			array(
				'reason' => $reason,
				'time'   => time(),
				'user'   => get_current_user_id(),
			)
		);
		Change_Log::add( 'unpublish_suggested', 'article', $post->post_name, $post->ID, $reason );
		return array(
			'result' => 'suggested',
			'note'   => 'The owner will decide. Nothing was unpublished.',
		);
	}

	/**
	 * Change log.
	 *
	 * @param array $args limit.
	 * @return array
	 */
	private static function get_changes( array $args ) {
		$limit = isset( $args['limit'] ) ? max( 1, min( 300, (int) $args['limit'] ) ) : 50;
		$out   = array();
		foreach ( Change_Log::all( $limit ) as $entry ) {
			$entry['time'] = gmdate( 'c', (int) $entry['time'] );
			$out[]         = $entry;
		}
		return array( 'changes' => $out );
	}

	/**
	 * Write a version of an article as Claude's (title, text, excerpt; the person-edited mark is
	 * cleared and the fingerprint kept). Used when the owner approves or undoes a change.
	 *
	 * @param int         $post_id Article.
	 * @param string      $title   Title.
	 * @param string      $content Block markup.
	 * @param string|null $excerpt Excerpt (null keeps it).
	 * @param array|null  $source  Source facts (null keeps them).
	 * @param string      $type    Article type ('' keeps it).
	 * @return true|\WP_Error
	 */
	public static function write_version( $post_id, $title, $content, $excerpt = null, $source = null, $type = '' ) {
		$data = array(
			'ID'           => (int) $post_id,
			'post_title'   => (string) $title,
			'post_content' => (string) $content,
		);
		if ( null !== $excerpt ) {
			$data['post_excerpt'] = (string) $excerpt;
		}
		$was           = self::$writing;
		self::$writing = true;
		$result        = wp_update_post( wp_slash( $data ), true );
		self::$writing = $was;
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( is_array( $source ) ) {
			update_post_meta( $post_id, '_wpbot_source', $source );
		}
		if ( in_array( $type, Style_Guide::TYPES, true ) ) {
			update_post_meta( $post_id, '_wpbot_article_type', $type );
		}
		update_post_meta(
			$post_id,
			'_wpbot_ai_last_write',
			array(
				'time' => time(),
				'user' => get_current_user_id(),
				'hash' => self::hash( $title, $content ),
			)
		);
		delete_post_meta( $post_id, '_wpbot_human_edited' );
		delete_post_meta( $post_id, self::PROPOSAL );
		return true;
	}

	/**
	 * Apply a suggestion (owner).
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function apply_proposal( $post_id ) {
		$proposal = get_post_meta( $post_id, self::PROPOSAL, true );
		if ( ! is_array( $proposal ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		$data = array(
			'ID'           => (int) $post_id,
			'post_title'   => (string) $proposal['title'],
			'post_content' => (string) $proposal['content'],
		);
		if ( null !== $proposal['excerpt'] ) {
			$data['post_excerpt'] = (string) $proposal['excerpt'];
		}
		self::$writing = true;
		$result        = wp_update_post( wp_slash( $data ), true );
		self::$writing = false;
		if ( is_wp_error( $result ) ) {
			return false;
		}
		if ( is_array( $proposal['source'] ) ) {
			update_post_meta( $post_id, '_wpbot_source', $proposal['source'] );
		}
		update_post_meta(
			$post_id,
			'_wpbot_ai_last_write',
			array(
				'time' => time(),
				'user' => get_current_user_id(),
				'hash' => self::hash( $proposal['title'], $proposal['content'] ),
			)
		);
		delete_post_meta( $post_id, '_wpbot_human_edited' );
		delete_post_meta( $post_id, self::PROPOSAL );
		Change_Log::add( 'applied', 'article', get_post_field( 'post_name', $post_id ), $post_id, 'Suggestion applied by the owner' );
		return true;
	}

	/**
	 * Dismiss a suggestion (change or unpublish).
	 *
	 * @param int    $post_id Post id.
	 * @param string $kind    proposal or unpublish.
	 * @return bool
	 */
	public static function dismiss( $post_id, $kind ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		delete_post_meta( $post_id, 'unpublish' === $kind ? self::UNPUBLISH : self::PROPOSAL );
		Change_Log::add( 'dismissed', 'article', get_post_field( 'post_name', $post_id ), $post_id, 'unpublish' === $kind ? 'Unpublish suggestion dismissed' : 'Suggestion dismissed' );
		return true;
	}

	/**
	 * Articles with an open suggestion.
	 *
	 * @return \WP_Post[]
	 */
	public static function with_suggestions() {
		return get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A short admin list.
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => self::PROPOSAL,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => self::UNPUBLISH,
						'compare' => 'EXISTS',
					),
				),
			)
		);
	}

	/**
	 * Drafts the AI wrote (for "Publish all AI drafts").
	 *
	 * @return int[]
	 */
	public static function ai_drafts() {
		return get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => array( 'draft', 'pending' ),
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Every AI draft, admin only.
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A short admin list.
				'meta_key'       => '_wpbot_ai_last_write',
			)
		);
	}
}
