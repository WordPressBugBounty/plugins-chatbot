<?php
/**
 * Knowledge base storage: sources and their chunks.
 *
 * Upsert() stores one item (a post, a file, a set of answers). When its text is
 * unchanged nothing happens; when it changed, its old chunks are replaced by new
 * ones waiting for embedding. Vectors are saved with their model and size, so a
 * search only compares vectors from the model it uses for the question.
 *
 * CB1c: items may carry access labels (see Kb_Access) and heading-aware chunks (an `html` text
 * with headings, or `chunks` given as arrays with heading_path and anchor). Changing only an
 * item's labels updates them in place, without new chunks or embeddings.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

use QuantumCloud\WPBotCore\Storage\Kb_Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Sources and chunks.
 */
final class Kb_Store {

	/**
	 * Store one item.
	 *
	 * @param string $type Source type (post, str, file, business_context, …).
	 * @param string $id   Id within the type.
	 * @param array  $item title, url, content, language; optional chunks (pre-split pieces: strings, or arrays
	 *                     of content, heading_path, anchor, access), html (the same text with headings, for
	 *                     heading-aware chunks), access (labels; empty = public) and vector + model (legacy reuse).
	 * @return string "unchanged", "stored" or "empty".
	 */
	public static function upsert( $type, $id, array $item ) {
		global $wpdb;
		$type    = self::type( $type );
		$id      = substr( (string) $id, 0, 191 );
		$title   = isset( $item['title'] ) ? substr( wp_strip_all_tags( (string) $item['title'] ), 0, 255 ) : '';
		$url     = isset( $item['url'] ) ? substr( esc_url_raw( (string) $item['url'] ), 0, 2048 ) : '';
		$lang    = isset( $item['language'] ) ? substr( sanitize_key( $item['language'] ), 0, 12 ) : '';
		$content = Chunker::clean( isset( $item['content'] ) ? (string) $item['content'] : '' );
		if ( '' === $content ) {
			self::remove( $type, $id );
			return 'empty';
		}
		$access = Kb_Access::encode( isset( $item['access'] ) ? $item['access'] : array() );
		$pieces = self::pieces( $item, $content );
		$headed = array() !== array_filter( array_column( $pieces, 'heading_path' ), 'strlen' );
		// Heading chunks change the pieces, not the text: mark them so such items are chunked again once.
		$parts    = array_map(
			static function ( $piece ) {
				return $piece['access'] . '|' . $piece['heading_path'] . '|' . $piece['anchor'];
			},
			$pieces
		);
		$sections = array() !== array_filter(
			$parts,
			static function ( $part ) {
				return '||' !== $part;
			}
		) ? "\npieces:" . md5( implode( "\n", $parts ) ) : '';
		// Heading chunks and chunk labels change the pieces, not the text: include them so a change is stored.
		$hash    = sha1( $title . "\n" . $url . "\n" . $content . ( $headed ? "\nheadings:1" : '' ) . $sections );
		$sources = Kb_Schema::table( Kb_Schema::SOURCES );
		$chunks  = Kb_Schema::table( Kb_Schema::CHUNKS );

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, content_hash, status, access FROM {$sources} WHERE source_type = %s AND source_id = %s", $type, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		if ( $existing && $existing['content_hash'] === $hash && 'error' !== $existing['status'] ) {
			if ( isset( $existing['access'] ) && $existing['access'] !== $access ) {
				$wpdb->update( $sources, array( 'access' => $access ), array( 'id' => (int) $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			}
			return 'unchanged';
		}

		$now = current_time( 'mysql', true );
		$row = array(
			'source_type'  => $type,
			'source_id'    => $id,
			'title'        => $title,
			'url'          => $url,
			'language'     => $lang,
			'content_hash' => $hash,
			'chunks'       => count( $pieces ),
			'status'       => 'pending',
			'error'        => '',
			'access'       => $access,
			'updated_at'   => $now,
		);
		if ( $existing ) {
			$ref = (int) $existing['id'];
			$wpdb->update( $sources, $row, array( 'id' => $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$wpdb->delete( $chunks, array( 'source_ref' => $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		} else {
			$wpdb->insert( $sources, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$ref = (int) $wpdb->insert_id;
		}

		$vector = isset( $item['vector'] ) && is_array( $item['vector'] ) && 1 === count( $pieces ) ? $item['vector'] : null;
		foreach ( $pieces as $index => $piece ) {
			$chunk = array(
				'source_ref'      => $ref,
				'source_type'     => $type,
				'chunk_index'     => $index,
				'title'           => $title,
				'url'             => $url,
				'language'        => $lang,
				'content'         => $piece['content'],
				'content_hash'    => sha1( $piece['content'] ),
				'access'          => $piece['access'],
				'heading_path'    => $piece['heading_path'],
				'anchor'          => $piece['anchor'],
				'embedding'       => null,
				'embedding_model' => '',
				'dimensions'      => 0,
				'status'          => 'pending',
				'updated_at'      => $now,
			);
			if ( null !== $vector && ! empty( $item['model'] ) ) {
				$chunk['embedding']       = Vector_Codec::encode( $vector );
				$chunk['embedding_model'] = substr( (string) $item['model'], 0, 120 );
				$chunk['dimensions']      = count( $vector );
				$chunk['status']          = 'ready';
			}
			$wpdb->insert( $chunks, $chunk ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		if ( null !== $vector && ! empty( $item['model'] ) ) {
			$wpdb->update( $sources, array( 'status' => 'ready' ), array( 'id' => $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		return 'stored';
	}

	/**
	 * The pieces of an item: its own chunks, else heading chunks of its html, else split content.
	 *
	 * @param array  $item    Item.
	 * @param string $content Cleaned content.
	 * @return array[] Each: content, heading_path, anchor, access (stored form).
	 */
	public static function pieces( array $item, $content ) {
		$raw = array();
		if ( isset( $item['chunks'] ) && is_array( $item['chunks'] ) ) {
			$raw = $item['chunks'];
		} elseif ( isset( $item['html'] ) && '' !== trim( (string) $item['html'] ) ) {
			$raw = Chunker::split_headings( (string) $item['html'] );
		}
		if ( array() === $raw ) {
			$raw = Chunker::split( $content );
		}
		$pieces = array();
		foreach ( $raw as $piece ) {
			$piece = is_array( $piece ) ? $piece : array( 'content' => $piece );
			$text  = Chunker::clean( isset( $piece['content'] ) ? (string) $piece['content'] : '' );
			if ( '' === $text ) {
				continue;
			}
			$pieces[] = array(
				'content'      => $text,
				'heading_path' => isset( $piece['heading_path'] ) ? mb_substr( wp_strip_all_tags( (string) $piece['heading_path'] ), 0, 255, 'UTF-8' ) : '',
				'anchor'       => isset( $piece['anchor'] ) ? substr( (string) preg_replace( '/[^\p{L}\p{N}_\-:.]/u', '', (string) $piece['anchor'] ), 0, 191 ) : '',
				'access'       => isset( $piece['access'] ) ? Kb_Access::encode( $piece['access'] ) : '',
			);
		}
		return $pieces;
	}

	/**
	 * Change an item's access labels without touching its chunks or vectors.
	 *
	 * @param string          $type   Source type.
	 * @param string          $id     Id within the type.
	 * @param string|string[] $labels Labels (empty or "public" = everyone).
	 * @return bool Whether the item exists.
	 */
	public static function set_access( $type, $id, $labels ) {
		global $wpdb;
		$sources = Kb_Schema::table( Kb_Schema::SOURCES );
		$ref     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$sources} WHERE source_type = %s AND source_id = %s", self::type( $type ), (string) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		if ( ! $ref ) {
			return false;
		}
		$wpdb->update( $sources, array( 'access' => Kb_Access::encode( $labels ) ), array( 'id' => $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return true;
	}

	/**
	 * An item's stored labels ([] = public; null when the item is not in the knowledge base).
	 *
	 * @param string $type Source type.
	 * @param string $id   Id within the type.
	 * @return string[]|null
	 */
	public static function access( $type, $id ) {
		global $wpdb;
		$sources = Kb_Schema::table( Kb_Schema::SOURCES );
		$stored  = $wpdb->get_var( $wpdb->prepare( "SELECT access FROM {$sources} WHERE source_type = %s AND source_id = %s", self::type( $type ), (string) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		return null === $stored ? null : Kb_Access::decode( (string) $stored );
	}

	/**
	 * Remove one item and its chunks.
	 *
	 * @param string $type Source type.
	 * @param string $id   Id within the type.
	 * @return bool Whether something was removed.
	 */
	public static function remove( $type, $id ) {
		global $wpdb;
		$sources = Kb_Schema::table( Kb_Schema::SOURCES );
		$ref     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$sources} WHERE source_type = %s AND source_id = %s", self::type( $type ), (string) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		if ( ! $ref ) {
			return false;
		}
		$wpdb->delete( Kb_Schema::table( Kb_Schema::CHUNKS ), array( 'source_ref' => $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->delete( $sources, array( 'id' => $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return true;
	}

	/**
	 * Remove every item of a type (owner turned a source off).
	 *
	 * @param string $type Source type.
	 * @return int Items removed.
	 */
	public static function remove_type( $type ) {
		global $wpdb;
		$type    = self::type( $type );
		$sources = Kb_Schema::table( Kb_Schema::SOURCES );
		$chunks  = Kb_Schema::table( Kb_Schema::CHUNKS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$chunks} WHERE source_type = %s", $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$sources} WHERE source_type = %s", $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
	}

	/**
	 * Chunks that need a vector from the given model: new ones, and ones embedded by another model.
	 *
	 * @param string $model Embedding model.
	 * @param int    $limit Most rows.
	 * @return array[] id, content.
	 */
	public static function to_embed( $model, $limit ) {
		global $wpdb;
		$chunks = Kb_Schema::table( Kb_Schema::CHUNKS );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, title, content FROM {$chunks} WHERE status = 'pending' OR ( status = 'ready' AND embedding_model <> %s ) ORDER BY id ASC LIMIT %d", (string) $model, max( 1, (int) $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
	}

	/**
	 * How many chunks need a vector from the given model, and their size.
	 *
	 * @param string $model Embedding model.
	 * @return array{chunks: int, chars: int}
	 */
	public static function pending( $model ) {
		global $wpdb;
		$chunks = Kb_Schema::table( Kb_Schema::CHUNKS );
		$row    = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(SUM(CHAR_LENGTH(content)),0) AS c FROM {$chunks} WHERE status = 'pending' OR ( status = 'ready' AND embedding_model <> %s )", (string) $model ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		return array(
			'chunks' => isset( $row['n'] ) ? (int) $row['n'] : 0,
			'chars'  => isset( $row['c'] ) ? (int) $row['c'] : 0,
		);
	}

	/**
	 * Save vectors.
	 *
	 * @param array<int,float[]> $vectors Chunk id => vector.
	 * @param string             $model   Embedding model.
	 * @return void
	 */
	public static function save_vectors( array $vectors, $model ) {
		global $wpdb;
		$chunks = Kb_Schema::table( Kb_Schema::CHUNKS );
		$refs   = array();
		foreach ( $vectors as $id => $vector ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				$chunks,
				array(
					'embedding'       => Vector_Codec::encode( $vector ),
					'embedding_model' => substr( (string) $model, 0, 120 ),
					'dimensions'      => count( $vector ),
					'status'          => 'ready',
				),
				array( 'id' => (int) $id )
			);
			$refs[] = (int) $id;
		}
		if ( array() !== $refs ) {
			$sources = Kb_Schema::table( Kb_Schema::SOURCES );
			$ids     = implode( ',', array_map( 'intval', $refs ) );
			// A source is ready when none of its chunks is still pending.
			$wpdb->query( "UPDATE {$sources} s SET s.status = 'ready' WHERE s.id IN ( SELECT DISTINCT source_ref FROM {$chunks} WHERE id IN ( {$ids} ) ) AND NOT EXISTS ( SELECT 1 FROM {$chunks} c WHERE c.source_ref = s.id AND c.status = 'pending' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables, integer ids.
		}
	}

	/**
	 * Counts for the settings screen.
	 *
	 * @param string $model Current embedding model ('' when none).
	 * @return array{sources: int, chunks: int, ready: int, pending: int}
	 */
	public static function stats( $model ) {
		global $wpdb;
		$chunks  = Kb_Schema::table( Kb_Schema::CHUNKS );
		$sources = Kb_Schema::table( Kb_Schema::SOURCES );
		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$chunks}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		$ready   = '' === $model ? 0 : (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$chunks} WHERE status = 'ready' AND embedding_model = %s", $model ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		return array(
			'sources' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sources}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
			'chunks'  => $total,
			'ready'   => $ready,
			'pending' => $total - $ready,
		);
	}

	/**
	 * Clean the arguments of items(): page (from 1), per_page (1-100, default 20), search
	 * (title or source id, at most 100 characters), type (a source type, or a prefix ending in ":"),
	 * status ('ready': every piece has a vector of the current model; 'waiting': some do not; '' = all).
	 *
	 * @param array $args Arguments.
	 * @return array{page:int,per_page:int,search:string,type:string,status:string}
	 */
	public static function items_args( array $args ) {
		$search = isset( $args['search'] ) ? trim( wp_strip_all_tags( (string) $args['search'] ) ) : '';
		return array(
			'page'     => max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 ),
			'per_page' => min( 100, max( 1, isset( $args['per_page'] ) ? (int) $args['per_page'] : 20 ) ),
			'search'   => function_exists( 'mb_substr' ) ? mb_substr( $search, 0, 100 ) : substr( $search, 0, 100 ),
			'type'     => isset( $args['type'] ) ? self::type( (string) $args['type'] ) : '',
			'status'   => isset( $args['status'] ) && in_array( $args['status'], array( 'ready', 'waiting' ), true ) ? (string) $args['status'] : '',
		);
	}

	/**
	 * What the knowledge base holds, newest first, for settings screens (read only).
	 *
	 * @param array  $args  See items_args().
	 * @param string $model Current embedding model ('' when none): counts the pieces ready for search.
	 * @return array{items: array[], total: int, page: int, per_page: int} Each item: source_type,
	 *               source_id, title, url, chunks, ready (pieces with a vector of the current model),
	 *               status, error, updated_at.
	 */
	public static function items( array $args = array(), $model = '' ) {
		global $wpdb;
		$args    = self::items_args( $args );
		$sources = Kb_Schema::table( Kb_Schema::SOURCES );
		$chunks  = Kb_Schema::table( Kb_Schema::CHUNKS );
		$where   = array( '1=1' );
		$params  = array();
		if ( '' !== $args['type'] ) {
			if ( ':' === substr( $args['type'], -1 ) ) {
				$where[]  = 's.source_type LIKE %s';
				$params[] = $wpdb->esc_like( $args['type'] ) . '%';
			} else {
				$where[]  = 's.source_type = %s';
				$params[] = $args['type'];
			}
		}
		if ( '' !== $args['search'] ) {
			$where[]  = '( s.title LIKE %s OR s.source_id = %s )';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $args['search'];
		}
		if ( '' !== $args['status'] ) {
			$ready_sql = "( SELECT COUNT(*) FROM {$chunks} c WHERE c.source_ref = s.id AND c.status = 'ready' AND c.embedding_model = %s )";
			$where[]   = 'ready' === $args['status'] ? "( s.chunks > 0 AND {$ready_sql} >= s.chunks )" : "( s.chunks = 0 OR {$ready_sql} < s.chunks )";
			$params[]  = (string) $model;
		}
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$sources} s WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables; values prepared.
		$list_sql  = "SELECT s.id, s.source_type, s.source_id, s.title, s.url, s.chunks, s.status, s.error, s.updated_at,
			( SELECT COUNT(*) FROM {$chunks} c WHERE c.source_ref = s.id AND c.status = 'ready' AND c.embedding_model = %s ) AS ready
			FROM {$sources} s WHERE {$where_sql} ORDER BY s.updated_at DESC, s.id DESC LIMIT %d OFFSET %d";
		$rows      = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( array( (string) $model ), $params, array( $args['per_page'], ( $args['page'] - 1 ) * $args['per_page'] ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables; values prepared.
		$items     = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'source_type' => (string) $row['source_type'],
				'source_id'   => (string) $row['source_id'],
				'title'       => (string) $row['title'],
				'url'         => (string) $row['url'],
				'chunks'      => (int) $row['chunks'],
				'ready'       => (int) $row['ready'],
				'status'      => (string) $row['status'],
				'error'       => (string) $row['error'],
				'updated_at'  => (string) $row['updated_at'],
			);
		}
		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $args['page'],
			'per_page' => $args['per_page'],
		);
	}

	/**
	 * Clean a source type: lowercase letters, digits, "_", "-" and ":" (post:page, legacy:csv).
	 *
	 * @param string $type Source type.
	 * @return string
	 */
	public static function type( $type ) {
		return substr( (string) preg_replace( '/[^a-z0-9_:\-]/', '', strtolower( (string) $type ) ), 0, 40 );
	}
}
