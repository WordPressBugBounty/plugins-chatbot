<?php
/**
 * Keeps WPBot Core's knowledge base (the store the chatbot answers from) in step with the
 * knowledge base screens of WPBot Free.
 *
 * The old screens write the documents table (rag_documents); since the move to WPBot Core the
 * chatbot answers from Core's own tables. This class:
 * - removes the matching Core items when documents are deleted (one, selected, by source, all);
 * - removes a source's items when the owner turns the source off under Sources to include;
 * - hands "Embed All Selected Sources" (Knowledge Base page and AI Setup Wizard) to Core's sync
 *   job, so items are split into pieces and embedded in the background like "Sync now";
 * - tells the screens whether an AI provider with embeddings is set up.
 *
 * Every call into WPBot Core is guarded, so the screens keep working without it.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Qcld_Kb_Live_Store' ) ) {

	/**
	 * WPBot Core knowledge base helpers for WPBot Free's screens.
	 */
	class Qcld_Kb_Live_Store {

		/** WPBot Core's knowledge base state option. */
		const STATE = 'qcld_wpbot_core_kb_state';

		/** Legacy documents table (without prefix). */
		const LEGACY = 'rag_documents';

		/**
		 * Hook in.
		 *
		 * @return void
		 */
		public static function init() {
			foreach ( array( 'rag_embed_pages', 'rag_embed_posts', 'rag_embed_str', 'rag_embed_cpts' ) as $option ) {
				add_action( 'update_option_' . $option, array( __CLASS__, 'source_option_changed' ), 10, 3 );
			}
			// "rag_" prefix: WPBot Chatbot's bridge also catches up with the documents table after these.
			add_action( 'wp_ajax_rag_bulk_delete_source', array( __CLASS__, 'ajax_delete_sources' ) );
			add_action( 'wp_ajax_qcld_rag_core_sync', array( __CLASS__, 'ajax_sync' ) );
			add_action( 'wp_ajax_qcld_rag_core_sync_status', array( __CLASS__, 'ajax_sync_status' ) );
		}

		/**
		 * Whether WPBot Core's knowledge base store is loaded.
		 *
		 * @return bool
		 */
		public static function available() {
			return class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Store' ) && class_exists( '\QuantumCloud\WPBotCore\Storage\Kb_Schema' );
		}

		/**
		 * Whether WPBot Core's knowledge base has taken over from the documents table.
		 *
		 * @return bool
		 */
		public static function active() {
			$bridge = '\QuantumCloud\Chatbot\Knowledge\Kb_Bridge';
			return self::available() && class_exists( $bridge ) && method_exists( $bridge, 'active' ) && $bridge::active() && class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Sources' );
		}

		/**
		 * Whether an AI provider that can create embeddings is set up: the same check as WPBot Core's
		 * knowledge base (Kb_Embedder::profile(), fed by the chatbot's provider settings) once it is in
		 * use, else the documents table's own provider check.
		 *
		 * @return bool
		 */
		public static function has_embeddings() {
			$embedder = '\QuantumCloud\WPBotCore\Knowledge\Kb_Embedder';
			if ( self::active() && class_exists( $embedder ) ) {
				return null !== $embedder::profile();
			}
			if ( class_exists( 'Qcld_Bot_Rag' ) ) {
				return ! is_wp_error( Qcld_Bot_Rag::instance()->embedding_provider() );
			}
			return false;
		}

		/**
		 * Core items (source type, id) that hold a documents table row.
		 *
		 * @param array $row Row: id, doc_id, title, content, source_type, source_url, metadata.
		 * @return array[] List of array( source type, id ).
		 */
		public static function item_refs( array $row ) {
			$row  = array_merge(
				array(
					'id'          => 0,
					'doc_id'      => '',
					'title'       => '',
					'content'     => '',
					'source_type' => '',
					'source_url'  => '',
					'metadata'    => '',
				),
				$row
			);
			$refs = array();
			$move = '\QuantumCloud\WPBotCore\Knowledge\Kb_Migration';
			if ( class_exists( $move ) ) {
				$probe = $row;
				// Kb_Migration::item() skips rows without text; only the source and id are needed here.
				if ( '' === trim( (string) $probe['content'] ) ) {
					$probe['content'] = '-';
				}
				$item = $move::item( $probe, null );
				if ( is_array( $item ) && isset( $item['source'], $item['id'] ) ) {
					$refs[] = array( (string) $item['source'], (string) $item['id'] );
				}
			}
			// A Simple Text Response embedded by the old screens is also its own item in Core's "str" source.
			$meta = json_decode( (string) $row['metadata'], true );
			if ( is_array( $meta ) && ! empty( $meta['str_id'] ) ) {
				$refs[] = array( 'str', (string) absint( $meta['str_id'] ) );
			}
			$unique = array();
			foreach ( $refs as $ref ) {
				$unique[ $ref[0] . '|' . $ref[1] ] = $ref;
			}
			return array_values( $unique );
		}

		/**
		 * Remove the Core items of documents table rows.
		 *
		 * @param array[] $rows Rows (see item_refs()).
		 * @return int Items removed.
		 */
		public static function remove_rows( array $rows ) {
			if ( ! self::available() ) {
				return 0;
			}
			$store   = '\QuantumCloud\WPBotCore\Knowledge\Kb_Store';
			$removed = 0;
			foreach ( $rows as $row ) {
				foreach ( self::item_refs( (array) $row ) as $ref ) {
					if ( $store::remove( $ref[0], $ref[1] ) ) {
						++$removed;
					}
				}
			}
			return $removed;
		}

		/**
		 * Empty WPBot Core's knowledge base: every item and piece, whatever its source. Background
		 * knowledge base jobs are cancelled, so a sync that was running does not fill it again.
		 * Empty the documents table (rag_documents) first: rows left there are moved back at the next
		 * catch-up.
		 *
		 * @return int Items removed (-1 when WPBot Core's knowledge base is not loaded).
		 */
		public static function remove_all() {
			if ( ! self::available() ) {
				return -1;
			}
			self::cancel_jobs();
			if ( function_exists( 'qcld_wpbot_core_kb_remove_all' ) ) {
				return (int) qcld_wpbot_core_kb_remove_all();
			}
			global $wpdb;
			$schema  = '\QuantumCloud\WPBotCore\Storage\Kb_Schema';
			$store   = '\QuantumCloud\WPBotCore\Knowledge\Kb_Store';
			$sources = $schema::table( $schema::SOURCES );
			$types   = (array) $wpdb->get_col( "SELECT DISTINCT source_type FROM {$sources}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- WPBot Core's table, owner action.
			$removed = 0;
			foreach ( $types as $type ) {
				$removed += (int) $store::remove_type( (string) $type );
			}
			// The documents table starts again from id 1: new uploads must be moved again.
			$state = get_option( self::STATE, array() );
			if ( is_array( $state ) ) {
				$state['migrated_to'] = 0;
				if ( isset( $state['migrated_count'] ) ) {
					$state['migrated_count'] = 0;
				}
				update_option( self::STATE, $state, false );
			}
			return $removed;
		}

		/**
		 * Cancel WPBot Core's queued and running knowledge base jobs (sync, embedding, move).
		 *
		 * @return void
		 */
		private static function cancel_jobs() {
			$jobs = '\QuantumCloud\WPBotCore\Jobs\Job_Store';
			if ( ! class_exists( $jobs ) ) {
				return;
			}
			foreach ( $jobs::active_ids( 50 ) as $job_id ) {
				$job = $jobs::get( $job_id );
				if ( $job && isset( $job['module'] ) && 'kb' === $job['module'] ) {
					$jobs::cancel( $job_id );
				}
			}
		}

		/**
		 * A knowledge source switch was saved: remove the items of every source the owner turned off.
		 *
		 * @param mixed  $old_value Old value.
		 * @param mixed  $value     New value.
		 * @param string $option    Option name.
		 * @return void
		 */
		public static function source_option_changed( $old_value, $value, $option ) {
			if ( ! self::available() ) {
				return;
			}
			$gone = array();
			if ( 'rag_embed_cpts' === $option ) {
				foreach ( array_diff( self::types( $old_value ), self::types( $value ) ) as $type ) {
					$gone[] = 'post:' . $type;
				}
			} elseif ( self::on( $old_value ) && ! self::on( $value ) ) {
				$map = array(
					'rag_embed_pages' => 'post:page',
					'rag_embed_posts' => 'post:post',
					'rag_embed_str'   => 'str',
				);
				if ( isset( $map[ $option ] ) ) {
					$gone[] = $map[ $option ];
				}
			}
			foreach ( $gone as $type ) {
				self::remove_source( $type );
			}
		}

		/**
		 * Remove every item of a source that is no longer read.
		 *
		 * @param string $type Source type (post:page, str…).
		 * @return int Items removed.
		 */
		public static function remove_source( $type ) {
			$type = (string) $type;
			if ( 0 === strpos( $type, 'post:' ) ) {
				$sources = '\QuantumCloud\WPBotCore\Knowledge\Kb_Sources';
				// Another plugin may still list the post type: then it stays.
				if ( class_exists( $sources ) && in_array( substr( $type, 5 ), $sources::post_types(), true ) ) {
					return 0;
				}
			}
			$store = '\QuantumCloud\WPBotCore\Knowledge\Kb_Store';
			return (int) $store::remove_type( $type );
		}

		/**
		 * Post types in a saved rag_embed_cpts value (array, JSON or comma list).
		 *
		 * @param mixed $value Value.
		 * @return string[]
		 */
		public static function types( $value ) {
			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				$value   = is_array( $decoded ) ? $decoded : array_filter( array_map( 'trim', explode( ',', $value ) ) );
			}
			return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', (array) $value ) ) ) ) );
		}

		/**
		 * Whether a legacy switch value is on.
		 *
		 * @param mixed $value Value.
		 * @return bool
		 */
		public static function on( $value ) {
			return in_array( $value, array( 1, '1', true, 'yes', 'on' ), true );
		}

		/**
		 * AJAX: Manage sources > Delete All and "Delete Selected Sources": delete every document of the
		 * given sources (source URLs; '' for manual uploads) and their knowledge base items.
		 *
		 * @return void
		 */
		public static function ajax_delete_sources() {
			check_ajax_referer( 'wp_chatbot', 'nonce' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to do this.', 'chatbot' ), 403 );
			}
			$urls = isset( $_POST['source_urls'] ) ? (array) wp_unslash( $_POST['source_urls'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared with stored values in prepared queries; sanitize_text_field() would change URLs (percent-encoding).
			$urls = array_values( array_unique( array_map( 'trim', array_map( 'wp_check_invalid_utf8', array_map( 'strval', $urls ) ) ) ) );
			if ( array() === $urls ) {
				wp_send_json_error( __( 'No source selected.', 'chatbot' ) );
			}
			global $wpdb;
			$table  = $wpdb->prefix . self::LEGACY;
			$named  = array_values( array_filter( $urls, 'strlen' ) );
			$where  = array();
			$params = array();
			if ( array() !== $named ) {
				$where[] = 'source_url IN (' . implode( ',', array_fill( 0, count( $named ), '%s' ) ) . ')';
				$params  = $named;
			}
			if ( count( $named ) < count( $urls ) ) {
				$where[] = "source_url IS NULL OR source_url = ''";
			}
			$sql  = "SELECT id, doc_id, title, content, source_type, source_url, metadata FROM {$table} WHERE " . implode( ' OR ', $where );
			$rows = (array) $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Placeholders built above; fixed table.
			if ( array() === $rows ) {
				wp_send_json_error( __( 'No documents were found for the selected sources.', 'chatbot' ) );
			}
			$removed = self::remove_rows( $rows );
			$ids     = array_map( 'absint', wp_list_pluck( $rows, 'id' ) );
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Placeholders built here; fixed table.
			if ( false === $deleted ) {
				wp_send_json_error( __( 'The documents could not be deleted.', 'chatbot' ) );
			}
			wp_send_json_success(
				array(
					'deleted' => (int) $deleted,
					'removed' => $removed,
					'message' => self::deleted_message( (int) $deleted, $removed ),
				)
			);
		}

		/**
		 * Result message of a delete: what left the documents table and the knowledge base.
		 *
		 * @param int $deleted Documents deleted from the documents table.
		 * @param int $removed Items removed from WPBot Core's knowledge base (-1 when it is not loaded).
		 * @return string
		 */
		public static function deleted_message( $deleted, $removed ) {
			/* translators: %d: number of documents. */
			$message = sprintf( _n( '%d document deleted from the documents table.', '%d documents deleted from the documents table.', (int) $deleted, 'chatbot' ), (int) $deleted );
			if ( $removed > 0 ) {
				/* translators: %d: number of items. */
				$message .= ' ' . sprintf( _n( '%d item removed from the knowledge base the chatbot answers from.', '%d items removed from the knowledge base the chatbot answers from.', (int) $removed, 'chatbot' ), (int) $removed );
			} elseif ( 0 === $removed && self::available() ) {
				$message .= ' ' . __( 'Nothing matching was in the knowledge base the chatbot answers from.', 'chatbot' );
			}
			return $message;
		}

		/**
		 * AJAX: "Embed All Selected Sources" with WPBot Core's knowledge base: start the same job as
		 * "Sync now". Replies mode "legacy" when the documents table is still in use (the screens then
		 * embed item by item as before).
		 *
		 * @return void
		 */
		public static function ajax_sync() {
			check_ajax_referer( 'wp_chatbot', 'nonce' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to do this.', 'chatbot' ), 403 );
			}
			if ( ! self::active() ) {
				wp_send_json_success( array( 'mode' => 'legacy' ) );
			}
			if ( ! self::has_embeddings() ) {
				wp_send_json_error( self::no_embeddings_message() );
			}
			$sources = '\QuantumCloud\WPBotCore\Knowledge\Kb_Sources';
			$job     = $sources::sync();
			if ( is_wp_error( $job ) ) {
				wp_send_json_error( $job->get_error_message() );
			}
			wp_send_json_success(
				array(
					'mode' => 'core',
					'job'  => (int) $job,
				)
			);
		}

		/**
		 * AJAX: progress of a sync started by ajax_sync(). Each call also runs one step (the sync's, then
		 * the embedding job's), so the work moves on while the screen is open even when WP-Cron is slow.
		 *
		 * @return void
		 */
		public static function ajax_sync_status() {
			check_ajax_referer( 'wp_chatbot', 'nonce' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to do this.', 'chatbot' ), 403 );
			}
			$jobs   = '\QuantumCloud\WPBotCore\Jobs\Job_Store';
			$runner = '\QuantumCloud\WPBotCore\Jobs\Job_Runner';
			$kb     = '\QuantumCloud\WPBotCore\Knowledge\Kb';
			if ( ! self::active() || ! class_exists( $jobs ) || ! class_exists( $runner ) || ! class_exists( $kb ) ) {
				wp_send_json_error( __( 'The knowledge base is not available.', 'chatbot' ) );
			}
			$job_id = isset( $_POST['job'] ) ? absint( wp_unslash( $_POST['job'] ) ) : 0;
			$sync   = $job_id ? $jobs::get( $job_id ) : null;
			if ( ! $sync || 'kb' !== $sync['module'] ) {
				wp_send_json_error( __( 'This sync was not found. Reload the page and try again.', 'chatbot' ) );
			}
			$active = array( 'queued', 'running' );
			$status = $kb::status();
			$embed  = ! empty( $status['job'] ) ? $jobs::get( (int) $status['job'] ) : null;
			if ( in_array( $sync['status'], $active, true ) ) {
				$jobs::touch_browser( $job_id );
				$runner::run_one( $job_id );
			} elseif ( $embed && in_array( $embed['status'], $active, true ) ) {
				$jobs::touch_browser( (int) $embed['id'] );
				$runner::run_one( (int) $embed['id'] );
			}
			$progress = $runner::progress( $job_id );
			$status   = $kb::status();
			$embed    = ! empty( $status['job'] ) ? $jobs::get( (int) $status['job'] ) : null;
			$read     = $progress && ! in_array( $progress['status'], $active, true );
			$embeds   = $embed && in_array( $embed['status'], array( 'queued', 'running' ), true );
			wp_send_json_success(
				array(
					'sync'      => array(
						'status' => $progress ? (string) $progress['status'] : '',
						'done'   => $progress ? (int) $progress['done'] : 0,
						'total'  => $progress ? (int) $progress['total'] : 0,
						'error'  => $progress ? (string) $progress['error'] : '',
					),
					'items'     => (int) $status['counts']['sources'],
					'chunks'    => (int) $status['counts']['chunks'],
					'ready'     => (int) $status['counts']['ready'],
					'waiting'   => (int) $status['estimate']['chunks'],
					'read'      => $read,
					'embedding' => $embeds,
					'embed'     => $embed ? (string) $embed['status'] : '',
					'finished'  => $read && ! $embeds,
				)
			);
		}

		/**
		 * The message shown when no AI provider with embeddings is set up.
		 *
		 * @return string
		 */
		public static function no_embeddings_message() {
			return __( 'Please connect to an AI service like OpenAI or Gemini before embedding.', 'chatbot' );
		}
	}

	Qcld_Kb_Live_Store::init();
}
