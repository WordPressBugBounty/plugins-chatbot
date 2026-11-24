<?php
/**
 * Articles as a knowledge source for the chatbot (plan section 7, HC1-04).
 *
 * Source id helpdesk_article in WPBot Core's knowledge base. Published articles
 * without a password are stored with their headings (so answers cite the exact
 * section), access labels and alternative questions; they are removed when they
 * leave "publish" or are deleted. The post type is kept out of the generic post
 * type source so an article is never stored twice.
 *
 * When the Help Center is switched off its items are removed (the chatbot must
 * not cite pages that no longer exist); when it is switched on again, WPBot
 * Core's sync job reads every article.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

use QuantumCloud\HelpCenter\Content\Heading_Ids;
use QuantumCloud\HelpCenter\Content\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge source.
 */
final class Knowledge {

	/**
	 * Source id.
	 */
	const SOURCE = 'helpdesk_article';

	/**
	 * Flag: read every article on the next request (set when the Help Center is switched on).
	 */
	const SYNC_FLAG = 'qcld_wpbot_help_center_kb_sync';

	/**
	 * Hooks that run only while the Help Center is on.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_core/kb_sources', array( self::class, 'sources' ) );
		add_filter( 'qcld_wpbot_core/kb_post_types', array( self::class, 'post_types' ), 99 );
		add_action( 'save_post_' . Post_Type::NAME, array( self::class, 'on_save' ), 20, 2 );
		add_action( 'rest_after_insert_' . Post_Type::NAME, array( self::class, 'on_rest_save' ) );
		add_action( 'transition_post_status', array( self::class, 'on_status' ), 20, 3 );
		add_action( 'before_delete_post', array( self::class, 'on_delete' ) );
		add_filter( 'the_content', array( self::class, 'content' ), 20 );
		add_action( 'init', array( self::class, 'maybe_sync' ), 1000 );
	}

	/**
	 * Add the source.
	 *
	 * @param array $sources Sources.
	 * @return array
	 */
	public static function sources( $sources ) {
		$sources                 = (array) $sources;
		$sources[ self::SOURCE ] = array(
			'label' => __( 'Help Center articles', 'chatbot' ),
			'count' => array( self::class, 'count' ),
			'items' => array( self::class, 'items' ),
		);
		return $sources;
	}

	/**
	 * Keep articles out of the generic post type source.
	 *
	 * @param string[] $types Post types.
	 * @return string[]
	 */
	public static function post_types( $types ) {
		return array_values( array_diff( (array) $types, array( Post_Type::NAME ) ) );
	}

	/**
	 * Published articles.
	 *
	 * @return int
	 */
	public static function count() {
		$counts = wp_count_posts( Post_Type::NAME );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * One batch of published articles as knowledge items.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Batch size.
	 * @return array[]
	 */
	public static function items( $offset, $limit ) {
		$ids   = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => (int) $limit,
				'offset'         => (int) $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$items = array();
		foreach ( (array) $ids as $id ) {
			$post = get_post( $id );
			if ( $post ) {
				$items[] = self::item( $post );
			}
		}
		return $items;
	}

	/**
	 * One article as a knowledge item.
	 *
	 * @param \WP_Post $post Article.
	 * @return array
	 */
	public static function item( $post ) {
		$raw     = strip_shortcodes( (string) $post->post_content );
		$html    = Heading_Ids::add( $raw );
		$excerpt = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
		$text    = trim( wp_strip_all_tags( $raw ) );
		$alt     = self::alt_questions( $post->ID );
		$also    = array() !== $alt ? __( 'Also asked as:', 'chatbot' ) . ' ' . implode( '; ', $alt ) : '';
		$access  = get_post_meta( $post->ID, '_wpbot_access', true );
		$item    = array(
			'id'       => (string) $post->ID,
			'title'    => get_the_title( $post ),
			'url'      => (string) get_permalink( $post ),
			'content'  => trim( implode( "\n\n", array_filter( array( $excerpt, $text, $also ) ) ) ),
			'language' => self::language( $post->ID ),
			'access'   => is_array( $access ) ? array_values( array_filter( array_map( 'strval', $access ) ) ) : array(),
		);
		if ( 1 === preg_match( '/<h[1-4][\s>]/i', $html ) ) {
			$item['html'] = ( '' !== $excerpt ? '<p>' . esc_html( $excerpt ) . '</p>' : '' ) . $html . ( '' !== $also ? '<p>' . esc_html( $also ) . '</p>' : '' );
		}
		/**
		 * Filters an article's knowledge item before it is stored.
		 *
		 * @param array    $item Item (id, title, url, content, language, access, html).
		 * @param \WP_Post $post Article.
		 */
		return (array) apply_filters( 'qcld_wpbot_help_center/kb_item', $item, $post );
	}

	/**
	 * An article was saved (classic editor; the block editor also calls on_rest_save()).
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::sync_post( $post );
	}

	/**
	 * Saved through REST: meta (access labels, alternative questions) is saved by now.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function on_rest_save( $post ) {
		self::sync_post( $post );
	}

	/**
	 * An article left "publish": remove it.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function on_status( $new_status, $old_status, $post ) {
		if ( is_object( $post ) && Post_Type::NAME === $post->post_type && 'publish' === $old_status && 'publish' !== $new_status ) {
			self::remove( $post->ID );
		}
	}

	/**
	 * An article is being deleted: remove it.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function on_delete( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && Post_Type::NAME === $post->post_type ) {
			self::remove( $post_id );
		}
	}

	/**
	 * Add heading ids to an article's content on its page, the same ids the knowledge base links to.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function content( $content ) {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( Post_Type::NAME ) ) {
			return $content;
		}
		return Heading_Ids::add( $content );
	}

	/**
	 * The Help Center was switched on or off (called while it may be off, from Rewrite_Flush).
	 *
	 * @param bool $on Whether it is on now.
	 * @return void
	 */
	public static function on_switch( $on ) {
		if ( $on ) {
			update_option( self::SYNC_FLAG, 1, false );
			return;
		}
		delete_option( self::SYNC_FLAG );
		if ( class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Store' ) ) {
			\QuantumCloud\WPBotCore\Knowledge\Kb_Store::remove_type( self::SOURCE );
		}
	}

	/**
	 * After switching on: start WPBot Core's sync job for the articles, once.
	 *
	 * @return void
	 */
	public static function maybe_sync() {
		if ( ! get_option( self::SYNC_FLAG ) ) {
			return;
		}
		delete_option( self::SYNC_FLAG );
		if ( class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Sources' ) && self::count() > 0 ) {
			\QuantumCloud\WPBotCore\Knowledge\Kb_Sources::sync( array( self::SOURCE ) );
		}
	}

	/**
	 * Store a published article without a password, remove it otherwise.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	private static function sync_post( $post ) {
		if ( ! is_object( $post ) || Post_Type::NAME !== $post->post_type ) {
			return;
		}
		if ( 'publish' === $post->post_status && '' === (string) $post->post_password ) {
			if ( function_exists( 'qcld_wpbot_core_kb_store' ) ) {
				qcld_wpbot_core_kb_store( self::SOURCE, self::item( $post ) );
			}
		} else {
			self::remove( $post->ID );
		}
	}

	/**
	 * Remove one article.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	private static function remove( $post_id ) {
		if ( function_exists( 'qcld_wpbot_core_kb_remove' ) ) {
			qcld_wpbot_core_kb_remove( self::SOURCE, (string) $post_id );
		}
	}

	/**
	 * Alternative questions of an article.
	 *
	 * @param int $post_id Post id.
	 * @return string[]
	 */
	private static function alt_questions( $post_id ) {
		$questions = get_post_meta( $post_id, '_wpbot_alt_questions', true );
		if ( ! is_array( $questions ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', array_map( 'strval', $questions ) ) ) );
	}

	/**
	 * Language of an article: WPML or Polylang when present, else the site language.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function language( $post_id ) {
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post_id );
			if ( $lang ) {
				return (string) $lang;
			}
		}
		$wpml = apply_filters( 'wpml_post_language_details', null, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter.
		if ( is_array( $wpml ) && ! empty( $wpml['language_code'] ) ) {
			return (string) $wpml['language_code'];
		}
		return strtolower( substr( (string) get_locale(), 0, 2 ) );
	}
}
