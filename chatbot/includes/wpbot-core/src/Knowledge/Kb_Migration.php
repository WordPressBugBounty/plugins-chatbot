<?php
/**
 * Moves WPBot's old knowledge table (rag_documents) into the new knowledge base.
 *
 * Every old row becomes one item with one piece, so nothing is re-split or paid
 * for again: when the old vector has the size of the current embedding model it
 * is kept; otherwise the piece waits for embedding (the settings screen shows the
 * cost first). Items are re-split the next time their content changes. The old
 * table is left in place (removed in a later major version).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

use QuantumCloud\WPBotCore\Jobs\Cron_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy migration.
 */
final class Kb_Migration {

	/**
	 * Job step kind.
	 */
	const STEP = 'core_kb_migrate';

	/**
	 * Rows per step.
	 */
	const BATCH = 100;

	/**
	 * Old table (without prefix).
	 */
	const LEGACY = 'rag_documents';

	/**
	 * Whether the old table exists and still has rows to move.
	 *
	 * @return bool
	 */
	public static function needed() {
		$state = get_option( Kb_Embedder::STATE, array() );
		if ( ! empty( $state['migrated'] ) ) {
			return false;
		}
		return self::legacy_count() > 0;
	}

	/**
	 * Whether the old table exists.
	 *
	 * @return bool
	 */
	public static function legacy_table_exists() {
		global $wpdb;
		$table = $wpdb->prefix . self::LEGACY;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One check, owner action.
	}

	/**
	 * Rows in the old table (0 when it does not exist).
	 *
	 * @return int
	 */
	public static function legacy_count() {
		global $wpdb;
		$table = $wpdb->prefix . self::LEGACY;
		if ( ! self::legacy_table_exists() ) {
			return 0;
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed legacy table.
	}

	/**
	 * Start the migration job.
	 *
	 * @return int Job id (0 when nothing to move).
	 */
	public static function start() {
		$count = self::legacy_count();
		if ( 0 === $count ) {
			self::mark_done();
			return 0;
		}
		$steps = array();
		for ( $offset = 0; $offset < $count; $offset += self::BATCH ) {
			$steps[] = array(
				'kind'  => self::STEP,
				'label' => __( 'Move knowledge from the previous version', 'chatbot' ),
				'input' => array( 'after' => 0 ),
			);
		}
		$job = Job_Store::create( 'kb', 'kb_migrate', array( 'title' => __( 'Move the knowledge base to the new format', 'chatbot' ) ), $steps );
		if ( $job ) {
			Cron_Runner::kick();
		}
		return (int) $job;
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
	 * Move the next batch (resumes after the last moved id, so steps are safe to repeat).
	 *
	 * @return array
	 */
	public static function step() {
		$state                = get_option( Kb_Embedder::STATE, array() );
		$state                = is_array( $state ) ? $state : array();
		$after                = isset( $state['migrated_to'] ) ? (int) $state['migrated_to'] : 0;
		$moved                = self::move( $after, self::BATCH, Kb_Embedder::profile() );
		$state['migrated_to'] = $moved['last'];
		if ( $moved['rows'] < self::BATCH ) {
			$state['migrated'] = current_time( 'mysql', true );
		}
		update_option( Kb_Embedder::STATE, $state, false );
		// Pieces whose old vector did not fit wait for the owner: the settings screen shows the cost first.
		return $moved;
	}

	/**
	 * Keep up with the old knowledge screens, which still write rag_documents
	 * (file uploads, sheets): move rows added since the last run and drop items
	 * whose old row was deleted. Cheap; hosts call it after those screens save.
	 *
	 * @return array{moved: int, removed: int}
	 */
	public static function catch_up() {
		global $wpdb;
		$state = get_option( Kb_Embedder::STATE, array() );
		// With the old table empty, still look for items whose old row was deleted (the last upload).
		if ( ! is_array( $state ) || empty( $state['migrated'] ) || ! self::legacy_table_exists() ) {
			return array(
				'moved'   => 0,
				'removed' => 0,
			);
		}
		$after = isset( $state['migrated_to'] ) ? (int) $state['migrated_to'] : 0;
		$moved = self::move( $after, 500, Kb_Embedder::profile() );
		if ( $moved['last'] > $after ) {
			$state['migrated_to'] = $moved['last'];
			update_option( Kb_Embedder::STATE, $state, false );
		}

		$sources = \QuantumCloud\WPBotCore\Storage\Kb_Schema::table( \QuantumCloud\WPBotCore\Storage\Kb_Schema::SOURCES );
		$legacy  = $wpdb->prefix . self::LEGACY;
		$gone    = (array) $wpdb->get_results( "SELECT s.source_type, s.source_id FROM {$sources} s LEFT JOIN {$legacy} l ON l.id = CAST( SUBSTRING( s.source_id, 8 ) AS UNSIGNED ) WHERE s.source_id LIKE 'legacy-%' AND l.id IS NULL LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own and fixed legacy tables.
		foreach ( $gone as $row ) {
			Kb_Store::remove( $row['source_type'], $row['source_id'] );
		}
		if ( $moved['kept'] + $moved['pending'] > 0 && $moved['pending'] > 0 ) {
			// New uploads were embedded by the old screen with another model: queue them like any change.
			Kb_Embedder::schedule();
		}
		return array(
			'moved'   => $moved['kept'] + $moved['pending'],
			'removed' => count( $gone ),
		);
	}

	/**
	 * Move rows with an id above $after.
	 *
	 * @param int        $after   Last moved id.
	 * @param int        $limit   Rows.
	 * @param array|null $profile Current embedding profile.
	 * @return array{rows: int, kept: int, pending: int, last: int}
	 */
	public static function move( $after, $limit, $profile ) {
		global $wpdb;
		$table = $wpdb->prefix . self::LEGACY;
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, doc_id, title, content, embedding, source_type, source_url, metadata FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT %d", (int) $after, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed legacy table.
		$out   = array(
			'rows'    => count( $rows ),
			'kept'    => 0,
			'pending' => 0,
			'last'    => (int) $after,
		);
		foreach ( $rows as $row ) {
			$out['last'] = (int) $row['id'];
			$item        = self::item( $row, $profile );
			if ( null === $item ) {
				continue;
			}
			Kb_Store::upsert( $item['source'], $item['id'], $item );
			if ( isset( $item['vector'] ) ) {
				++$out['kept'];
			} else {
				++$out['pending'];
			}
		}
		return $out;
	}

	/**
	 * One old row as a new item (pure; tested without a database).
	 *
	 * @param array      $row     Old row.
	 * @param array|null $profile Embedding profile (provider, model, dimensions).
	 * @return array|null source, id, title, url, content, chunks; vector and model when the old vector is kept.
	 */
	public static function item( array $row, $profile ) {
		$content = isset( $row['content'] ) ? trim( (string) $row['content'] ) : '';
		if ( '' === $content ) {
			return null;
		}
		$meta = isset( $row['metadata'] ) ? json_decode( (string) $row['metadata'], true ) : null;
		$type = isset( $row['source_type'] ) ? sanitize_key( (string) $row['source_type'] ) : '';
		if ( is_array( $meta ) && ! empty( $meta['post_id'] ) ) {
			$post_type = ! empty( $meta['post_type'] ) ? sanitize_key( $meta['post_type'] ) : ( '' !== $type ? $type : 'post' ); // Post type keys have no colon.
			$source    = 'post:' . $post_type;
			$id        = (string) absint( $meta['post_id'] );
		} elseif ( 'str' === $type || 'simple_text_response' === $type ) {
			$source = 'str';
			$id     = '' !== (string) $row['doc_id'] ? (string) $row['doc_id'] : 'legacy-' . (int) $row['id'];
		} else {
			// Uploaded files, sheets, summaries: one item per old row, keyed by its id.
			$source = 'legacy:' . ( '' !== $type ? $type : 'file' );
			$id     = 'legacy-' . (int) $row['id'];
		}
		$item = array(
			'source'  => $source,
			'id'      => $id,
			'title'   => isset( $row['title'] ) ? (string) $row['title'] : '',
			'url'     => isset( $row['source_url'] ) ? (string) $row['source_url'] : '',
			'content' => $content,
			'chunks'  => array( $content ),
		);
		if ( $profile && ! empty( $profile['dimensions'] ) && ! empty( $row['embedding'] ) ) {
			$vector = Vector_Codec::decode( (string) $row['embedding'] );
			if ( count( $vector ) === (int) $profile['dimensions'] ) {
				$item['vector'] = $vector;
				$item['model']  = $profile['provider'] . '/' . $profile['model'];
			}
		}
		return $item;
	}

	/**
	 * Remember that there is nothing (left) to move.
	 *
	 * @return void
	 */
	private static function mark_done() {
		$state             = get_option( Kb_Embedder::STATE, array() );
		$state             = is_array( $state ) ? $state : array();
		$state['migrated'] = current_time( 'mysql', true );
		update_option( Kb_Embedder::STATE, $state, false );
	}
}
