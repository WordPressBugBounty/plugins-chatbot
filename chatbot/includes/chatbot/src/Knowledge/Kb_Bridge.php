<?php
/**
 * Connects the chatbot's knowledge base settings to WPBot Core's knowledge base.
 *
 * Reads today's options (the existing knowledge base settings screen keeps
 * saving them):
 * - post types: rag_embed_pages, rag_embed_posts, rag_embed_cpts;
 * - Simple Text Responses as a source: rag_embed_str;
 * - automatic sync on save: rag_auto_sync_enabled;
 * - the embedding model: the provider order and models WPBot's knowledge base
 *   has always used (OpenAI > Gemini > OpenRouter > Mistral > Claude via Voyage),
 *   so vectors stored before are reused, plus Ollama.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Knowledge;

use QuantumCloud\Chatbot\Ai\Provider_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge base bridge.
 */
final class Kb_Bridge {

	/**
	 * Source id of Simple Text Responses.
	 */
	const STR_SOURCE = 'str';

	/**
	 * Embedding provider order and model per chat provider (as stored before).
	 *
	 * @return array<string,array{0:string,1:string}> chat provider => ( embedding provider, model ).
	 */
	public static function embedding_map() {
		return array(
			'openai'     => array( 'openai', 'text-embedding-3-large' ),
			'gemini'     => array( 'gemini', 'gemini-embedding-001' ),
			'openrouter' => array( 'openrouter', 'openai/text-embedding-3-small' ),
			'mistral'    => array( 'mistral', 'mistral-embed' ),
			'claude'     => array( 'voyage', 'voyage-4' ),
			'ollama'     => array( 'ollama', 'nomic-embed-text' ),
		);
	}

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_core/kb_post_types', array( __CLASS__, 'post_types' ) );
		add_filter( 'qcld_wpbot_core/kb_embedding', array( __CLASS__, 'embedding' ) );
		add_filter( 'qcld_wpbot_core/kb_sources', array( __CLASS__, 'sources' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ) );
		// The old knowledge screens (uploads, sheets, deletes) still write rag_documents: follow them.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks the moment to catch up; the old handlers check their own nonces.
		if ( wp_doing_ajax() && preg_match( '/^(qcld_)?rag_/', $action ) ) {
			add_action( 'shutdown', array( __CLASS__, 'catch_up' ) );
		}
	}

	/**
	 * Move the old knowledge table once (free: no new embedding is started).
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( ! class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Migration' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$migration = '\QuantumCloud\WPBotCore\Knowledge\Kb_Migration';
		$state     = get_option( 'qcld_wpbot_core_kb_state', array() );
		if ( is_array( $state ) && ! empty( $state['migration_job'] ) ) {
			return;
		}
		if ( $migration::needed() ) {
			$state                  = is_array( $state ) ? $state : array();
			$state['migration_job'] = $migration::start();
			update_option( 'qcld_wpbot_core_kb_state', array_merge( get_option( 'qcld_wpbot_core_kb_state', array() ), array( 'migration_job' => $state['migration_job'] ) ), false );
		} elseif ( ! is_array( $state ) || empty( $state['migrated'] ) ) {
			// Nothing to move (a new site): the move is done. Without this mark, the first file uploaded
			// on the old screens made the site "not migrated" again: answers went back to the old table
			// (without the synced posts) and uploads were not caught up until a migration job ran.
			update_option(
				'qcld_wpbot_core_kb_state',
				array_merge(
					is_array( $state ) ? $state : array(),
					array(
						'migrated'    => current_time( 'mysql', true ),
						'migrated_to' => 0,
					)
				),
				false
			);
		}
	}

	/**
	 * Follow the old knowledge screens.
	 *
	 * @return void
	 */
	public static function catch_up() {
		if ( class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Migration' ) ) {
			\QuantumCloud\WPBotCore\Knowledge\Kb_Migration::catch_up();
		}
	}

	/**
	 * Whether the new knowledge base has taken over from rag_documents (migrated, or nothing to migrate).
	 *
	 * @return bool
	 */
	public static function active() {
		if ( ! class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Migration' ) ) {
			return false;
		}
		$state = get_option( 'qcld_wpbot_core_kb_state', array() );
		return ( is_array( $state ) && ! empty( $state['migrated'] ) ) || ! \QuantumCloud\WPBotCore\Knowledge\Kb_Migration::needed();
	}

	/**
	 * Post types the owner turned on. With automatic sync off, posts are still
	 * read by "Sync now", but saving a post does not update the knowledge base.
	 *
	 * @param string[] $types Types.
	 * @return string[]
	 */
	public static function post_types( $types ) {
		$types = (array) $types;
		if ( self::on( 'rag_embed_pages' ) ) {
			$types[] = 'page';
		}
		if ( self::on( 'rag_embed_posts' ) ) {
			$types[] = 'post';
		}
		$cpts = get_option( 'rag_embed_cpts', array() );
		if ( is_string( $cpts ) ) {
			$decoded = json_decode( $cpts, true );
			$cpts    = is_array( $decoded ) ? $decoded : array_filter( array_map( 'trim', explode( ',', $cpts ) ) );
		}
		foreach ( (array) $cpts as $type ) {
			$types[] = sanitize_key( (string) $type );
		}
		$types = array_values( array_unique( array_filter( $types ) ) );
		if ( doing_action( 'save_post' ) && ! self::on( 'rag_auto_sync_enabled' ) ) {
			return array();
		}
		return $types;
	}

	/**
	 * Embedding profile: the first provider in the stored order that is on and has a key.
	 *
	 * @param array|null $profile Profile.
	 * @return array|null
	 */
	public static function embedding( $profile ) {
		if ( is_array( $profile ) ) {
			return $profile;
		}
		foreach ( self::embedding_map() as $chat => $embed ) {
			$settings = Provider_Settings::get( $chat );
			if ( ! $settings || ! $settings['enabled'] ) {
				continue;
			}
			$key = 'claude' === $chat ? trim( (string) get_option( 'qcld_voyage_api_key', '' ) ) : $settings['key'];
			$url = 'ollama' === $chat ? $settings['url'] : '';
			if ( '' === $key && 'ollama' !== $chat ) {
				continue;
			}
			if ( 'ollama' === $chat && '' === $url ) {
				continue;
			}
			return array(
				'provider' => $embed[0],
				'model'    => $embed[1],
				'key'      => $key,
				'url'      => $url,
			);
		}
		return self::fallback_embedding();
	}

	/**
	 * Embeddings when the AI provider that answers has none the plugin can use (Grok, DeepSeek,
	 * Claude without a Voyage key): the first other key saved on the site, whether or not that
	 * provider answers chats. Indexing and searching use the same profile, so they stay comparable.
	 *
	 * @return array|null Null when no AI provider is on or no other key is saved.
	 */
	public static function fallback_embedding() {
		if ( '' === Provider_Settings::answering_provider() ) {
			return null;
		}
		$map = self::embedding_map();
		/**
		 * Order of the providers whose saved key is used for knowledge base embeddings when the AI
		 * provider that answers has no embeddings.
		 *
		 * @param string[] $order Provider ids.
		 */
		$order = (array) apply_filters( 'qcld_wpbot_chat/kb_embedding_fallback', array( 'openai', 'gemini', 'mistral', 'openrouter' ) );
		foreach ( $order as $chat ) {
			$settings = isset( $map[ $chat ] ) ? Provider_Settings::get( $chat ) : null;
			if ( ! $settings || '' === trim( (string) $settings['key'] ) ) {
				continue;
			}
			return array(
				'provider' => $map[ $chat ][0],
				'model'    => $map[ $chat ][1],
				'key'      => trim( (string) $settings['key'] ),
				'url'      => '',
			);
		}
		return null;
	}

	/**
	 * Simple Text Responses as a knowledge source, when the owner turned it on.
	 *
	 * @param array $sources Sources.
	 * @return array
	 */
	public static function sources( $sources ) {
		if ( ! self::on( 'rag_embed_str' ) ) {
			return $sources;
		}
		$sources[ self::STR_SOURCE ] = array(
			'label' => __( 'Simple Text Responses', 'chatbot' ),
			'count' => array( __CLASS__, 'str_count' ),
			'items' => array( __CLASS__, 'str_items' ),
		);
		return $sources;
	}

	/**
	 * Number of Simple Text Responses.
	 *
	 * @return int
	 */
	public static function str_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpbot_response" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The chatbot's own table.
	}

	/**
	 * A page of Simple Text Responses as knowledge items.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array[]
	 */
	public static function str_items( $offset, $limit ) {
		global $wpdb;
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, query, response FROM {$wpdb->prefix}wpbot_response ORDER BY id ASC LIMIT %d OFFSET %d", (int) $limit, (int) $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The chatbot's own table.
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'id'      => (string) $row['id'],
				'title'   => wp_strip_all_tags( (string) $row['query'] ),
				'url'     => '',
				'content' => 'Q: ' . wp_strip_all_tags( (string) $row['query'] ) . "\nA: " . wp_strip_all_tags( (string) $row['response'] ),
			);
		}
		return $items;
	}

	/**
	 * Whether a legacy "1" option is on.
	 *
	 * @param string $option Option.
	 * @return bool
	 */
	private static function on( $option ) {
		return in_array( get_option( $option, '' ), array( 1, '1', true, 'yes', 'on' ), true );
	}
}
