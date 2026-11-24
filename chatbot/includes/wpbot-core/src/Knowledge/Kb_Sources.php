<?php
/**
 * What the knowledge base reads, and keeping it up to date.
 *
 * Sources are registered through the qcld_wpbot_core/kb_sources filter:
 * id => array( 'label' => string, 'count' => callable(): int,
 * 'items' => callable( int $offset, int $limit ): array[] ), each item with id,
 * title, url, content and optional language. WPBot Core adds one source per post
 * type listed by the qcld_wpbot_core/kb_post_types filter (hosts list the types
 * the owner turned on). Posts stay in sync on their own: saved, trashed,
 * unpublished or deleted posts are updated right away; their vectors follow in
 * the background.
 *
 * WooCommerce products are synced again at the end of the request in which
 * WooCommerce saved them, or their price or stock changed: WooCommerce writes
 * the price after save_post, and a price-only change (quick and bulk edit, REST,
 * variations, scheduled sales, $product->save()) fires no save_post at all.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

use QuantumCloud\WPBotCore\Jobs\Cron_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Source registry and sync.
 */
final class Kb_Sources {

	/**
	 * Job step kind: read one batch of one source.
	 */
	const STEP = 'core_kb_sync';

	/**
	 * Items per step.
	 */
	const BATCH = 20;

	/**
	 * Posts to sync again at the end of this request: post id => true.
	 *
	 * @var array<int,bool>
	 */
	private static $resync = array();

	/**
	 * Whether the end-of-request sync is hooked.
	 *
	 * @var bool
	 */
	private static $resync_hooked = false;

	/**
	 * Hook post changes.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 20, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
		// WooCommerce saves a product's price and stock after save_post, or without it.
		foreach ( array( 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status' ) as $wc_hook ) {
			add_action( $wc_hook, array( __CLASS__, 'resync_later' ), 20 );
		}
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_product_object' ), 20 );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_product_object' ), 20 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
	}

	/**
	 * Post types the knowledge base reads.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		/**
		 * Post types the knowledge base reads (hosts list the ones the owner turned on).
		 *
		 * @param string[] $types Post types.
		 */
		return array_values( array_filter( array_map( 'sanitize_key', (array) apply_filters( 'qcld_wpbot_core/kb_post_types', array() ) ) ) );
	}

	/**
	 * Post types that follow edits automatically: post_types() as it is during save_post.
	 * Hosts let the owner turn automatic sync off by returning no types from
	 * qcld_wpbot_core/kb_post_types while doing_action( 'save_post' ); the end-of-request
	 * sync asks the same way, so it follows the same owner setting.
	 *
	 * @return string[]
	 */
	public static function auto_sync_types() {
		global $wp_current_filter;
		if ( function_exists( 'doing_action' ) && doing_action( 'save_post' ) ) {
			return self::post_types();
		}
		if ( ! is_array( $wp_current_filter ) ) {
			$wp_current_filter = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress keeps this list; restored below.
		}
		$wp_current_filter[] = 'save_post'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Only while the filter runs.
		try {
			return self::post_types();
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Every source.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$sources = array();
		foreach ( self::post_types() as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object ) {
				continue;
			}
			$sources[ 'post:' . $type ] = array(
				'label' => $object->labels->name,
				'count' => static function () use ( $type ) {
					$counts = wp_count_posts( $type );
					return isset( $counts->publish ) ? (int) $counts->publish : 0;
				},
				'items' => static function ( $offset, $limit ) use ( $type ) {
					$ids = get_posts(
						array(
							'post_type'      => $type,
							'post_status'    => 'publish',
							'posts_per_page' => $limit,
							'offset'         => $offset,
							'orderby'        => 'ID',
							'order'          => 'ASC',
							'fields'         => 'ids',
						)
					);
					return array_map( array( __CLASS__, 'sync_item' ), $ids );
				},
			);
		}
		/**
		 * Knowledge sources besides post types.
		 *
		 * @param array $sources id => label, count, items.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/kb_sources', $sources );
	}

	/**
	 * One post as a knowledge item.
	 *
	 * @param int $post_id Post id.
	 * @return array
	 */
	public static function post_item( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'id' => (string) $post_id );
		}
		$text = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		if ( '' !== trim( (string) $post->post_excerpt ) ) {
			$text = wp_strip_all_tags( $post->post_excerpt ) . "\n\n" . $text;
		}
		$base = $text;
		/**
		 * Text of a post in the knowledge base (hosts add product data, custom fields…).
		 *
		 * @param string   $text Text.
		 * @param \WP_Post $post Post.
		 */
		$text = (string) apply_filters( 'qcld_wpbot_core/kb_post_text', $text, $post );
		$item = array(
			'id'       => (string) $post->ID,
			'title'    => get_the_title( $post ),
			'url'      => (string) get_permalink( $post ),
			'content'  => $text,
			'language' => self::language( $post->ID ),
			/**
			 * Access labels of a post in the knowledge base (CB1c): [] or ['public'] for everyone;
			 * membership, course and store adapters add labels such as membership:gold.
			 *
			 * @param string[] $labels Labels.
			 * @param \WP_Post $post   Post.
			 */
			'access'   => (array) apply_filters( 'qcld_wpbot_core/kb_post_access', array(), $post ),
		);
		// Heading-aware chunks when the post has headings: the same text, with its HTML. Text a host
		// added after the post's own text (product data, custom fields) follows as a last paragraph.
		$html = strip_shortcodes( (string) $post->post_content );
		if ( 1 === preg_match( '/<h[1-4][\s>]/i', $html ) && 0 === strpos( $text, $base ) ) {
			$excerpt      = trim( (string) $post->post_excerpt );
			$extra        = trim( (string) substr( $text, strlen( $base ) ) );
			$item['html'] = ( '' !== $excerpt ? '<p>' . esc_html( wp_strip_all_tags( $excerpt ) ) . '</p>' : '' ) . $html . ( '' !== $extra ? '<p>' . esc_html( $extra ) . '</p>' : '' );
		}
		return $item;
	}

	/**
	 * Whether a post may be in the knowledge base: published and not password protected.
	 *
	 * @param \WP_Post|object $post Post.
	 * @return bool
	 */
	public static function indexable( $post ) {
		return is_object( $post ) && isset( $post->post_status ) && 'publish' === $post->post_status && '' === (string) ( isset( $post->post_password ) ? $post->post_password : '' );
	}

	/**
	 * One post for a full sync: its item when it may be in the knowledge base, else only its id,
	 * so the store removes an item stored before (a post that got a password after it was synced).
	 *
	 * @param int $post_id Post id.
	 * @return array
	 */
	public static function sync_item( $post_id ) {
		$post = get_post( $post_id );
		return $post && self::indexable( $post ) ? self::post_item( $post_id ) : array( 'id' => (string) $post_id );
	}

	/**
	 * Store one item of a source and start embedding.
	 *
	 * @param string $source Source id.
	 * @param array  $item   Item.
	 * @return string upsert() result.
	 */
	public static function store( $source, array $item ) {
		if ( ! isset( $item['id'] ) ) {
			return 'empty';
		}
		$result = Kb_Store::upsert( $source, (string) $item['id'], $item );
		if ( 'stored' === $result ) {
			Kb_Embedder::schedule();
			/**
			 * An item of a source was stored with new content (a post was saved, a module stored an
			 * item). Source mode of a shared knowledge base sends it to the hub.
			 *
			 * @param string $source Source id (post:page, helpdesk_article, …).
			 * @param array  $item   The item as given.
			 */
			do_action( 'qcld_wpbot_core/kb_item_stored', (string) $source, $item );
		} elseif ( 'empty' === $result ) {
			self::removed( $source, (string) $item['id'] );
		}
		return $result;
	}

	/**
	 * A post was saved: store it when published and of a known type, remove it otherwise.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		// Handled now: the end-of-request sync only runs again if WooCommerce saves it after this.
		unset( self::$resync[ (int) $post_id ] );
		self::sync_post( $post, self::post_types() );
	}

	/**
	 * Store a post when published and of one of the given types, remove it otherwise.
	 *
	 * @param \WP_Post $post  Post.
	 * @param string[] $types Post types the knowledge base reads now.
	 * @return void
	 */
	private static function sync_post( $post, array $types ) {
		if ( ! in_array( $post->post_type, $types, true ) ) {
			return;
		}
		if ( self::indexable( $post ) ) {
			self::store( 'post:' . $post->post_type, self::post_item( $post->ID ) );
		} elseif ( Kb_Store::remove( 'post:' . $post->post_type, (string) $post->ID ) ) {
			self::removed( 'post:' . $post->post_type, (string) $post->ID );
		}
	}

	/**
	 * Sync a post again at the end of this request, once, the way saving it does
	 * (same post types, automatic sync setting and status checks). WPBot Core calls it
	 * when WooCommerce saved a product or its price or stock changed; a variation
	 * stands for its product. Hosts may call it for other late changes.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function resync_later( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		if ( 'product_variation' === get_post_type( $post_id ) ) {
			$post_id = (int) wp_get_post_parent_id( $post_id );
			if ( $post_id <= 0 ) {
				return;
			}
		}
		self::$resync[ $post_id ] = true;
		if ( ! self::$resync_hooked ) {
			self::$resync_hooked = true;
			add_action( 'shutdown', array( __CLASS__, 'flush_resync' ) );
		}
	}

	/**
	 * WooCommerce changed a product's stock (the product object is passed).
	 *
	 * @param object $product WC_Product.
	 * @return void
	 */
	public static function on_product_object( $product ) {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			self::resync_later( (int) $product->get_id() );
		}
	}

	/**
	 * A post meta value was added, changed or deleted: sync the post again when it is a price or stock field.
	 *
	 * @param int|int[] $meta_id   Meta id(s).
	 * @param int       $object_id Post id.
	 * @param string    $meta_key  Meta key.
	 * @return void
	 */
	public static function on_meta( $meta_id, $object_id, $meta_key ) {
		if ( is_string( $meta_key ) && in_array( $meta_key, self::resync_meta_keys(), true ) ) {
			self::resync_later( (int) $object_id );
		}
	}

	/**
	 * Post meta keys whose change syncs the post again.
	 *
	 * @return string[]
	 */
	public static function resync_meta_keys() {
		/**
		 * Post meta keys whose change syncs the post again at the end of the request
		 * (default: WooCommerce price and stock fields).
		 *
		 * @param string[] $keys Meta keys.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/kb_resync_meta_keys', array( '_price', '_regular_price', '_sale_price', '_stock_status', '_stock' ) );
	}

	/**
	 * End of the request: sync each post waiting for it once (shutdown).
	 *
	 * @return int Posts checked.
	 */
	public static function flush_resync() {
		$ids          = array_keys( self::$resync );
		self::$resync = array();
		if ( array() === $ids ) {
			return 0;
		}
		$types = self::auto_sync_types();
		if ( array() === $types ) {
			return 0;
		}
		$checked = 0;
		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
				continue;
			}
			self::sync_post( $post, $types );
			++$checked;
		}
		return $checked;
	}

	/**
	 * Posts waiting for the end-of-request sync (for tests and diagnostics).
	 *
	 * @return int[]
	 */
	public static function pending_resync() {
		return array_map( 'intval', array_keys( self::$resync ) );
	}

	/**
	 * A post left "publish" (draft, private, trash…): remove it.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function on_status( $new_status, $old_status, $post ) {
		if ( 'publish' === $old_status && 'publish' !== $new_status && in_array( $post->post_type, self::post_types(), true ) && Kb_Store::remove( 'post:' . $post->post_type, (string) $post->ID ) ) {
			self::removed( 'post:' . $post->post_type, (string) $post->ID );
		}
	}

	/**
	 * A post is being deleted: remove it.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function on_delete( $post_id ) {
		$type = get_post_type( $post_id );
		if ( $type && Kb_Store::remove( 'post:' . $type, (string) $post_id ) ) {
			self::removed( 'post:' . $type, (string) $post_id );
		}
	}

	/**
	 * Announce that an item left the knowledge base.
	 *
	 * @param string $source Source id.
	 * @param string $id     Id within the source.
	 * @return void
	 */
	public static function removed( $source, $id ) {
		/**
		 * An item left the knowledge base (unpublished, deleted, removed by a module). Source mode
		 * of a shared knowledge base removes it from the hub too.
		 *
		 * @param string $source Source id.
		 * @param string $id     Id within the source.
		 */
		do_action( 'qcld_wpbot_core/kb_item_removed', (string) $source, (string) $id );
	}

	/**
	 * Start a job that reads every source again (owner action "Sync now").
	 * Unchanged items cost nothing; changed ones are queued for embedding.
	 *
	 * @param string[] $only Source ids (empty = all).
	 * @return int|WP_Error Job id.
	 */
	public static function sync( array $only = array() ) {
		$steps = array();
		foreach ( self::all() as $id => $source ) {
			if ( array() !== $only && ! in_array( $id, $only, true ) ) {
				continue;
			}
			$count = is_callable( $source['count'] ) ? (int) call_user_func( $source['count'] ) : 0;
			for ( $offset = 0; $offset < $count; $offset += self::BATCH ) {
				$steps[] = array(
					'kind'  => self::STEP,
					/* translators: %s: source name, such as Pages. */
					'label' => sprintf( __( 'Read %s', 'chatbot' ), $source['label'] ),
					'input' => array(
						'source' => $id,
						'offset' => $offset,
					),
				);
			}
		}
		if ( array() === $steps ) {
			return new WP_Error( 'qcld_wpbot_core_kb_empty', __( 'There is nothing to add to the knowledge base. Turn on at least one source.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$job = Job_Store::create( 'kb', 'kb_sync', array( 'title' => __( 'Sync the knowledge base', 'chatbot' ) ), $steps );
		if ( $job ) {
			Cron_Runner::kick();
		}
		return $job ? (int) $job : new WP_Error( 'qcld_wpbot_core_job', __( 'The job could not be created.', 'chatbot' ), array( 'status' => 500 ) );
	}

	/**
	 * Register the step handler.
	 *
	 * @param array $handlers Handlers.
	 * @return array
	 */
	public static function handlers( $handlers ) {
		$handlers[ self::STEP ] = array( __CLASS__, 'step' );
		return $handlers;
	}

	/**
	 * Read one batch of one source.
	 *
	 * @param array $step Step.
	 * @return array|WP_Error
	 */
	public static function step( $step ) {
		$input  = isset( $step['input'] ) ? (array) $step['input'] : array();
		$id     = isset( $input['source'] ) ? (string) $input['source'] : '';
		$all    = self::all();
		$source = isset( $all[ $id ] ) ? $all[ $id ] : null;
		if ( ! $source || ! is_callable( $source['items'] ) ) {
			return array( 'skipped' => 'source_gone' );
		}
		$stored = 0;
		foreach ( (array) call_user_func( $source['items'], isset( $input['offset'] ) ? (int) $input['offset'] : 0, self::BATCH ) as $item ) {
			if ( is_array( $item ) && 'stored' === Kb_Store::upsert( $id, isset( $item['id'] ) ? (string) $item['id'] : '', $item ) ) {
				++$stored;
			}
		}
		if ( $stored > 0 ) {
			Kb_Embedder::schedule();
		}
		return array( 'stored' => $stored );
	}

	/**
	 * Language of a post: WPML or Polylang when present, else the site language.
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
