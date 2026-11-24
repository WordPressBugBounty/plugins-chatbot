<?php
/**
 * Searches the knowledge base.
 *
 * 1. Greetings and very short messages are not searched.
 * 2. Candidates: the chunks MySQL's FULLTEXT index finds for the question, plus,
 *    when the question has a vector, the most recent ready chunks of the same
 *    model (up to a limit), so a question worded differently from the page
 *    still finds it. Without FULLTEXT hits, a keyword (LIKE) search.
 * 3. Ranking: cosine similarity of the vectors plus a small boost per keyword
 *    found; at most two pieces per source; below the minimum score is dropped.
 * Works without any AI key (keyword ranking only).
 *
 * CB1c: with `labels` (the visitor's access labels), chunks the visitor may not see are left
 * out in SQL, before any scoring (see Kb_Access). Results carry heading_path and anchor.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

use QuantumCloud\WPBotCore\Storage\Kb_Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge search.
 */
final class Kb_Search {

	/**
	 * Boost per keyword found in a chunk.
	 */
	const KEYWORD_BOOST = 0.05;

	/**
	 * Words too common to search for.
	 */
	const STOPWORDS = array( 'a', 'an', 'and', 'are', 'about', 'can', 'do', 'does', 'for', 'from', 'have', 'how', 'i', 'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'our', 'please', 'tell', 'that', 'the', 'there', 'this', 'to', 'we', 'what', 'when', 'where', 'which', 'who', 'why', 'with', 'you', 'your' );

	/**
	 * Search.
	 *
	 * @param string $query Question.
	 * @param array  $args  top_k (default 5, at most 50), min_score (default 0.3 with vectors), types (source types to include;
	 *                      an entry ending in ":" is a prefix, such as "site:3:"), exclude_types (the same, left out),
	 *                      weights (source type or prefix => multiplier from 0 to 5; the longest match counts; 0 leaves the
	 *                      results out), scan_limit, labels (the visitor's access labels; labelled chunks need one of them;
	 *                      omitted = public knowledge only; false = no filter, for admin tools).
	 * @return array[] Each: title, url, content, score, source_type, source_id, heading_path, anchor.
	 */
	public static function search( $query, array $args = array() ) {
		$query = trim( wp_strip_all_tags( (string) $query ) );
		if ( self::is_small_talk( $query ) ) {
			return array();
		}
		/**
		 * Arguments of a knowledge base search, before it runs (modules add per-source rules: types,
		 * exclude_types, weights).
		 *
		 * @param array  $args  Arguments (see Kb_Search::search()).
		 * @param string $query Question.
		 */
		$args = (array) apply_filters( 'qcld_wpbot_core/kb_search_args', $args, $query );
		/**
		 * Answer a search elsewhere (a site connected to a shared knowledge base asks its hub). Return
		 * an array of results to skip the local search; null searches here.
		 *
		 * @param array[]|null $results Null.
		 * @param string       $query   Question.
		 * @param array        $args    Arguments.
		 */
		$pre = apply_filters( 'qcld_wpbot_core/kb_pre_search', null, $query, $args );
		if ( is_array( $pre ) ) {
			return self::clean_results( $pre, isset( $args['top_k'] ) ? max( 1, min( 50, (int) $args['top_k'] ) ) : 5 );
		}
		$top_k    = isset( $args['top_k'] ) ? max( 1, min( 50, (int) $args['top_k'] ) ) : 5;
		$keywords = self::keywords( $query );
		$vector   = Kb_Embedder::query_vector( $query );
		$model    = Kb_Embedder::model_id();
		$types    = isset( $args['types'] ) ? array_filter( array_map( array( Kb_Store::class, 'type' ), (array) $args['types'] ) ) : array();
		$exclude  = isset( $args['exclude_types'] ) ? array_filter( array_map( array( Kb_Store::class, 'type' ), (array) $args['exclude_types'] ) ) : array();
		/**
		 * Most recent chunks scored by vector besides the FULLTEXT hits.
		 *
		 * @param int $limit Limit.
		 */
		$scan = (int) apply_filters( 'qcld_wpbot_core/kb_scan_limit', isset( $args['scan_limit'] ) ? (int) $args['scan_limit'] : 2000 );

		// Without labels only public knowledge is searched; false searches everything (admin tools).
		$labels     = array_key_exists( 'labels', $args ) && false === $args['labels'] ? null : Kb_Access::clean( isset( $args['labels'] ) ? (array) $args['labels'] : array( Kb_Access::PUBLIC_LABEL ) );
		$candidates = self::candidates( $query, $keywords, self::type_sql( $types, $exclude ), null !== $vector ? $model : '', $scan, $labels );
		return self::rank(
			$candidates,
			$vector,
			$keywords,
			array(
				'top_k'     => $top_k,
				'min_score' => isset( $args['min_score'] ) ? (float) $args['min_score'] : ( null !== $vector ? 0.3 : 0.0 ),
				'model'     => $model,
				'weights'   => isset( $args['weights'] ) ? self::clean_weights( (array) $args['weights'] ) : array(),
			)
		);
	}

	/**
	 * SQL condition for the included and excluded source types (pure). Entries ending in ":" are
	 * prefixes. Types are cleaned by Kb_Store::type() (letters, digits, "_", "-", ":"), so they are
	 * safe inside quotes; "_" is escaped for LIKE.
	 *
	 * @param string[] $types   Types or prefixes to include ([] = all).
	 * @param string[] $exclude Types or prefixes to leave out.
	 * @return string '' or " AND ( … )" conditions on c.source_type.
	 */
	public static function type_sql( array $types, array $exclude = array() ) {
		$sql  = '';
		$part = static function ( $type ) {
			$type = Kb_Store::type( $type );
			if ( '' === $type ) {
				return '';
			}
			return ':' === substr( $type, -1 ) ? "c.source_type LIKE '" . str_replace( '_', '\\_', $type ) . "%'" : "c.source_type = '" . $type . "'";
		};
		$in   = array_values( array_filter( array_map( $part, $types ) ) );
		if ( array() !== $in ) {
			$sql .= ' AND ( ' . implode( ' OR ', $in ) . ' )';
		}
		$out = array_values( array_filter( array_map( $part, $exclude ) ) );
		if ( array() !== $out ) {
			$sql .= ' AND NOT ( ' . implode( ' OR ', $out ) . ' )';
		}
		return $sql;
	}

	/**
	 * Clean weights: source type or prefix => multiplier between 0 and 5 (pure).
	 *
	 * @param array $weights Weights.
	 * @return array<string,float>
	 */
	public static function clean_weights( array $weights ) {
		$out = array();
		foreach ( $weights as $type => $weight ) {
			$type = Kb_Store::type( (string) $type );
			if ( '' !== $type && is_numeric( $weight ) ) {
				$out[ $type ] = max( 0.0, min( 5.0, (float) $weight ) );
			}
		}
		return $out;
	}

	/**
	 * The weight of a source type: the longest matching key (exact type, or a prefix ending in ":"); 1 when none.
	 *
	 * @param string              $type    Source type.
	 * @param array<string,float> $weights Clean weights.
	 * @return float
	 */
	public static function weight( $type, array $weights ) {
		$best = -1;
		$out  = 1.0;
		foreach ( $weights as $key => $weight ) {
			$key = (string) $key;
			$hit = $key === $type || ( ':' === substr( $key, -1 ) && 0 === strpos( $type, $key ) );
			if ( $hit && strlen( $key ) > $best ) {
				$best = strlen( $key );
				$out  = (float) $weight;
			}
		}
		return $out;
	}

	/**
	 * Results given by kb_pre_search, in the shape of search() results, at most $top_k (pure).
	 *
	 * @param array $results Results.
	 * @param int   $top_k   Most results.
	 * @return array[]
	 */
	public static function clean_results( array $results, $top_k ) {
		$out = array();
		foreach ( $results as $result ) {
			if ( ! is_array( $result ) || ! isset( $result['content'] ) ) {
				continue;
			}
			$out[] = array(
				'title'        => isset( $result['title'] ) ? (string) $result['title'] : '',
				'url'          => isset( $result['url'] ) ? (string) $result['url'] : '',
				'content'      => (string) $result['content'],
				'score'        => isset( $result['score'] ) ? round( (float) $result['score'], 4 ) : 0.0,
				'source_type'  => isset( $result['source_type'] ) ? Kb_Store::type( (string) $result['source_type'] ) : '',
				'source_id'    => isset( $result['source_id'] ) ? (string) $result['source_id'] : '',
				'heading_path' => isset( $result['heading_path'] ) ? (string) $result['heading_path'] : '',
				'anchor'       => isset( $result['anchor'] ) ? (string) $result['anchor'] : '',
			);
			if ( count( $out ) >= $top_k ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Search results as one text block for a prompt (title, URL, text per result).
	 *
	 * @param array[] $results Results of search().
	 * @return string
	 */
	public static function context( array $results ) {
		$parts = array();
		foreach ( $results as $result ) {
			$text = '';
			if ( '' !== $result['title'] ) {
				$text .= 'Title: ' . $result['title'] . "\n";
			}
			if ( '' !== $result['url'] ) {
				$text .= 'URL: ' . $result['url'] . ( ! empty( $result['anchor'] ) ? '#' . $result['anchor'] : '' ) . "\n";
			}
			if ( ! empty( $result['heading_path'] ) ) {
				$text .= 'Section: ' . $result['heading_path'] . "\n";
			}
			$parts[] = $text . $result['content'];
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * Rank candidates (pure; tested without a database).
	 *
	 * @param array[]      $candidates Rows: id, source_ref, source_type, source_id, title, url, content, embedding, embedding_model, ft.
	 * @param float[]|null $vector     Question vector.
	 * @param string[]     $keywords   Keywords.
	 * @param array        $args       top_k, min_score, model; optional weights (from clean_weights()).
	 * @return array[]
	 */
	public static function rank( array $candidates, $vector, array $keywords, array $args ) {
		$norm   = null !== $vector ? Vector_Codec::norm( $vector ) : 0.0;
		$scored = array();
		foreach ( $candidates as $row ) {
			$haystack = strtolower( $row['title'] . ' ' . $row['content'] );
			$hits     = 0;
			foreach ( $keywords as $keyword ) {
				if ( false !== strpos( $haystack, $keyword ) ) {
					++$hits;
				}
			}
			$similarity = 0.0;
			if ( null !== $vector && $row['embedding_model'] === $args['model'] ) {
				$similarity = Vector_Codec::cosine( $vector, Vector_Codec::decode( (string) $row['embedding'] ), $norm );
			} elseif ( null !== $vector && 0 === $hits ) {
				continue; // Not embedded with this model and no keyword: nothing to rank it on.
			}
			$score = null !== $vector ? $similarity + self::KEYWORD_BOOST * $hits : ( $hits + ( isset( $row['ft'] ) ? (float) $row['ft'] / 100 : 0.0 ) );
			if ( null === $vector && 0 === $hits && empty( $row['ft'] ) ) {
				continue;
			}
			if ( $score < $args['min_score'] ) {
				continue;
			}
			// Per-source weights reorder results that passed the minimum; they never hide one, except 0.
			if ( ! empty( $args['weights'] ) ) {
				$weight = self::weight( (string) $row['source_type'], (array) $args['weights'] );
				if ( $weight <= 0.0 ) {
					continue;
				}
				$score *= $weight;
			}
			$scored[] = array(
				'title'        => (string) $row['title'],
				'url'          => (string) $row['url'],
				'content'      => (string) $row['content'],
				'score'        => round( $score, 4 ),
				'source_type'  => (string) $row['source_type'],
				'source_id'    => isset( $row['source_id'] ) ? (string) $row['source_id'] : '',
				'heading_path' => isset( $row['heading_path'] ) ? (string) $row['heading_path'] : '',
				'anchor'       => isset( $row['anchor'] ) ? (string) $row['anchor'] : '',
				'ref'          => isset( $row['source_ref'] ) ? (int) $row['source_ref'] : 0,
			);
		}
		usort(
			$scored,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);
		$out  = array();
		$seen = array();
		foreach ( $scored as $result ) {
			$ref = $result['ref'];
			if ( $ref && isset( $seen[ $ref ] ) && $seen[ $ref ] >= 2 ) {
				continue;
			}
			$seen[ $ref ] = isset( $seen[ $ref ] ) ? $seen[ $ref ] + 1 : 1;
			unset( $result['ref'] );
			$out[] = $result;
			if ( count( $out ) >= $args['top_k'] ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Greetings, thanks and one-word replies are not questions for the knowledge base.
	 *
	 * @param string $query Message.
	 * @return bool
	 */
	public static function is_small_talk( $query ) {
		$text = strtolower( trim( preg_replace( '/[^\p{L}\p{N} ]+/u', ' ', (string) $query ) ) );
		if ( mb_strlen( $text ) < 3 ) {
			return true;
		}
		return 1 === preg_match( '/^(hi|hello|hey|hiya|yo|thanks|thank you|thank you very much|thx|ok|okay|cool|great|bye|goodbye|good (morning|afternoon|evening|night)|yes|no|sure)( there| bot| so much)?$/', $text );
	}

	/**
	 * Keywords of a question: lowercase words of 3+ letters that are not stopwords.
	 *
	 * @param string $query Question.
	 * @return string[]
	 */
	public static function keywords( $query ) {
		$words = preg_split( '/[^\p{L}\p{N}]+/u', strtolower( (string) $query ), -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();
		foreach ( (array) $words as $word ) {
			if ( mb_strlen( $word ) >= 3 && ! in_array( $word, self::STOPWORDS, true ) ) {
				$out[ $word ] = true;
			}
		}
		return array_slice( array_keys( $out ), 0, 12 );
	}

	/**
	 * Load candidate rows.
	 *
	 * @param string        $query    Question.
	 * @param string[]      $keywords Keywords.
	 * @param string        $type_sql Source type conditions from type_sql() ('' = all).
	 * @param string        $model    Model to scan by vector ('' = no vector scan).
	 * @param int           $scan     Vector scan limit.
	 * @param string[]|null $labels Visitor labels (null = no access filter).
	 * @return array[]
	 */
	private static function candidates( $query, array $keywords, $type_sql, $model, $scan, $labels = null ) {
		global $wpdb;
		$chunks  = Kb_Schema::table( Kb_Schema::CHUNKS );
		$sources = Kb_Schema::table( Kb_Schema::SOURCES );
		$fields  = 'c.id, c.source_ref, c.source_type, s.source_id, c.title, c.url, c.content, c.embedding, c.embedding_model, c.heading_path, c.anchor';
		$where   = "c.status IN ('ready','pending')";
		$where  .= (string) $type_sql;
		if ( null !== $labels ) {
			$where .= ' AND ' . Kb_Access::sql( $labels );
		}
		$rows = array();
		if ( array() !== $keywords ) {
			$ft   = implode( ' ', $keywords );
			$hits = $wpdb->get_results( $wpdb->prepare( "SELECT {$fields}, MATCH(c.title, c.content) AGAINST (%s) AS ft FROM {$chunks} c LEFT JOIN {$sources} s ON s.id = c.source_ref WHERE {$where} AND MATCH(c.title, c.content) AGAINST (%s) ORDER BY ft DESC LIMIT 200", $ft, $ft ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables; values prepared.
			foreach ( (array) $hits as $row ) {
				$rows[ (int) $row['id'] ] = $row;
			}
			if ( array() === $rows ) {
				$likes = array();
				foreach ( $keywords as $keyword ) {
					$like    = '%' . $wpdb->esc_like( $keyword ) . '%';
					$likes[] = $wpdb->prepare( '(c.content LIKE %s OR c.title LIKE %s)', $like, $like );
				}
				$hits = $wpdb->get_results( "SELECT {$fields}, 0 AS ft FROM {$chunks} c LEFT JOIN {$sources} s ON s.id = c.source_ref WHERE {$where} AND (" . implode( ' OR ', $likes ) . ') ORDER BY c.id DESC LIMIT 200', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables; LIKE parts prepared above.
				foreach ( (array) $hits as $row ) {
					$rows[ (int) $row['id'] ] = $row;
				}
			}
		}
		if ( '' !== $model && $scan > 0 ) {
			$recent = $wpdb->get_results( $wpdb->prepare( "SELECT {$fields}, 0 AS ft FROM {$chunks} c LEFT JOIN {$sources} s ON s.id = c.source_ref WHERE {$where} AND c.status = 'ready' AND c.embedding_model = %s ORDER BY c.id DESC LIMIT %d", $model, $scan ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables; values prepared.
			foreach ( (array) $recent as $row ) {
				if ( ! isset( $rows[ (int) $row['id'] ] ) ) {
					$rows[ (int) $row['id'] ] = $row;
				}
			}
		}
		return array_values( $rows );
	}
}
