<?php
/**
 * Ideas: topics to write about, from the owner (calendar ideas list) and,
 * later, from Topic Finder sources. Stored in the ideas table; other code
 * uses this class, never the table.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics;

use QuantumCloud\AiWriter\Storage\Schema;
use QuantumCloud\AiWriter\Writing\Prompts;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Idea storage.
 */
final class Ideas {

	/**
	 * Statuses: new (open), planned (a dated draft exists), done, dismissed.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'new', 'planned', 'done', 'dismissed' );

	/**
	 * Add an idea (a duplicate from the same source returns the existing one).
	 *
	 * @param string $title      Title.
	 * @param string $source     Source id (manual, search_log, search_console…).
	 * @param array  $extra      source_ref, reason, relevance, trend, sample, topic, main_query;
	 *                           demand (CR-6): volume (int|null), volume_range ([min, max]|null),
	 *                           volume_source, trend (int[12] or rising|steady|falling), competition,
	 *                           numbers (label => value, up to 8).
	 * @return int|WP_Error Idea id. A duplicate returns the existing id (its numbers are refreshed).
	 */
	public static function add( $title, $source = 'manual', array $extra = array() ) {
		global $wpdb;
		$title = Prompts::cut( trim( sanitize_text_field( (string) $title ) ), 255 );
		if ( '' === $title ) {
			return new WP_Error( 'qcld_wpbot_writer_no_title', __( 'Write the idea first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$source = substr( sanitize_key( $source ), 0, 40 );
		$ref    = isset( $extra['source_ref'] ) ? substr( sanitize_text_field( (string) $extra['source_ref'] ), 0, 191 ) : md5( strtolower( $title ) );
		$table  = Schema::table( Schema::IDEAS );
		$found  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE source = %s AND source_ref = %s", $source, $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		if ( $found ) {
			// Newer numbers for an idea already saved (SEO & AI Search sends them again).
			$demand = self::demand( $extra );
			if ( array() !== $demand ) {
				self::merge_sample( $found, array( 'demand' => $demand ) );
			}
			return $found;
		}
		$now    = gmdate( 'Y-m-d H:i:s' );
		$sample = isset( $extra['sample'] ) && is_array( $extra['sample'] ) ? $extra['sample'] : array();
		$demand = self::demand( $extra );
		if ( array() !== $demand ) {
			$sample['demand'] = $demand;
		}
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, write.
			$table,
			array(
				'source'     => $source,
				'source_ref' => $ref,
				'title'      => $title,
				'reason'     => isset( $extra['reason'] ) ? sanitize_textarea_field( (string) $extra['reason'] ) : '',
				'trend'      => self::trend_word( $extra ),
				'relevance'  => isset( $extra['relevance'] ) ? min( 100, absint( $extra['relevance'] ) ) : 0,
				'sample'     => array() !== $sample ? wp_json_encode( $sample ) : null,
				'run_id'     => isset( $extra['run_id'] ) ? substr( sanitize_key( (string) $extra['run_id'] ), 0, 40 ) : '',
				'topic'      => isset( $extra['topic'] ) && '' !== $extra['topic'] ? substr( sanitize_text_field( (string) $extra['topic'] ), 0, 191 ) : null,
				'main_query' => isset( $extra['main_query'] ) && '' !== $extra['main_query'] ? substr( sanitize_text_field( (string) $extra['main_query'] ), 0, 191 ) : null,
				'status'     => 'new',
				'first_seen' => $now,
				'last_seen'  => $now,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'qcld_wpbot_writer_idea_failed', __( 'The idea could not be saved.', 'chatbot' ), array( 'status' => 500 ) );
	}

	/**
	 * One idea, or null.
	 *
	 * @param int $id Idea id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::IDEAS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Clean search numbers from an idea's extras (CR-6 W2).
	 *
	 * @param array $extra Extras.
	 * @return array volume, volume_range, source, trend, trend_direction, competition, numbers; empty when none were given.
	 */
	public static function demand( array $extra ) {
		$has_volume = array_key_exists( 'volume', $extra ) && null !== $extra['volume'] && '' !== $extra['volume'];
		$range      = isset( $extra['volume_range'] ) && is_array( $extra['volume_range'] ) && 2 === count( $extra['volume_range'] ) ? array_map( 'absint', array_values( $extra['volume_range'] ) ) : null;
		$series     = isset( $extra['trend'] ) && is_array( $extra['trend'] ) ? array_slice( array_values( array_map( 'intval', $extra['trend'] ) ), -12 ) : array();
		$level      = isset( $extra['competition'] ) && is_string( $extra['competition'] ) ? sanitize_key( $extra['competition'] ) : '';
		$estimate   = isset( $extra['estimate'] ) && in_array( $extra['estimate'], array( 'low', 'medium', 'high' ), true ) ? $extra['estimate'] : '';
		$numbers    = array();
		foreach ( isset( $extra['numbers'] ) && is_array( $extra['numbers'] ) ? array_slice( $extra['numbers'], 0, 8, true ) : array() as $label => $value ) {
			$numbers[ substr( sanitize_text_field( (string) $label ), 0, 60 ) ] = substr( sanitize_text_field( (string) $value ), 0, 60 );
		}
		$word = isset( $extra['trend'] ) && is_string( $extra['trend'] ) && in_array( $extra['trend'], array( 'rising', 'steady', 'falling' ), true ) ? $extra['trend'] : '';
		if ( ! $has_volume && null === $range && array() === $series && '' === $level && array() === $numbers && '' === $estimate && '' === $word ) {
			return array();
		}
		return array(
			'volume'          => $has_volume ? absint( $extra['volume'] ) : null,
			'volume_range'    => $range,
			'source'          => Keyword_Numbers::source_label( isset( $extra['volume_source'] ) ? (string) $extra['volume_source'] : '' ),
			'trend'           => $series,
			'trend_direction' => Keyword_Numbers::direction( $series, isset( $extra['trend'] ) && is_string( $extra['trend'] ) ? $extra['trend'] : '' ),
			'competition'     => in_array( $level, array( 'low', 'medium', 'high' ), true ) ? $level : '',
			'numbers'         => $numbers,
			// Demand guessed by AI (Keyword Vault), always shown as "AI estimate".
			'estimate'        => $estimate,
		);
	}

	/**
	 * The idea's trend word (rising, steady, seasonal, falling) from extras.
	 *
	 * @param array $extra Extras (trend: a word or a monthly series).
	 * @return string
	 */
	private static function trend_word( array $extra ) {
		if ( ! isset( $extra['trend'] ) ) {
			return '';
		}
		if ( is_array( $extra['trend'] ) ) {
			return Keyword_Numbers::direction( array_map( 'intval', $extra['trend'] ) );
		}
		return substr( sanitize_key( (string) $extra['trend'] ), 0, 20 );
	}

	/**
	 * Merge keys into an idea's stored sample.
	 *
	 * @param int   $id     Idea id.
	 * @param array $values Keys to set.
	 * @return void
	 */
	private static function merge_sample( $id, array $values ) {
		global $wpdb;
		$table  = Schema::table( Schema::IDEAS );
		$raw    = (string) $wpdb->get_var( $wpdb->prepare( "SELECT sample FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		$sample = '' !== $raw ? json_decode( $raw, true ) : array();
		$sample = array_merge( is_array( $sample ) ? $sample : array(), $values );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		$wpdb->update(
			$table,
			array(
				'sample'     => wp_json_encode( $sample ),
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Ideas by status, best first.
	 *
	 * @param string $status Status.
	 * @param int    $limit  Max rows.
	 * @param string $source Optional source filter.
	 * @return array[]
	 */
	public static function query( $status = 'new', $limit = 50, $source = '' ) {
		global $wpdb;
		$table = Schema::table( Schema::IDEAS );
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s", in_array( $status, self::STATUSES, true ) ? $status : 'new' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		if ( '' !== $source ) {
			$sql .= $wpdb->prepare( ' AND source = %s', sanitize_key( $source ) );
		}
		$sql .= $wpdb->prepare( ' ORDER BY rank_score DESC, id DESC LIMIT %d', min( 500, max( 1, (int) $limit ) ) );
		return array_map( array( __CLASS__, 'shape' ), (array) $wpdb->get_results( $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Built from prepare() fragments.
	}

	/**
	 * Change an idea's status (and the post that covers it).
	 *
	 * @param int    $id      Idea id.
	 * @param string $status  Status.
	 * @param int    $post_id Covering post.
	 * @return bool
	 */
	public static function set_status( $id, $status, $post_id = 0 ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		$data = array(
			'status'     => $status,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( $post_id ) {
			$data['covered_post_id'] = absint( $post_id );
		}
		return false !== $wpdb->update( Schema::table( Schema::IDEAS ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
	}

	/**
	 * Delete an idea.
	 *
	 * @param int $id Idea id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( Schema::table( Schema::IDEAS ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
	}

	/**
	 * Add or refresh an idea from a Topic Finder source. An existing idea
	 * keeps its status (a dismissed idea stays dismissed) and gets the new
	 * reason, numbers and last-seen time.
	 *
	 * @param string $title  Title.
	 * @param string $source Source id.
	 * @param array  $extra  source_ref, reason, trend, relevance, sample (array), session_count, rank_score, covered_post_id, run_id, topic, main_query.
	 * @return int|WP_Error Idea id.
	 */
	public static function upsert( $title, $source, array $extra = array() ) {
		global $wpdb;
		$id = self::add( $title, $source, $extra );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$data = array(
			'last_seen'  => gmdate( 'Y-m-d H:i:s' ),
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( isset( $extra['reason'] ) ) {
			$data['reason'] = sanitize_textarea_field( (string) $extra['reason'] );
		}
		if ( isset( $extra['trend'] ) ) {
			$data['trend'] = self::trend_word( $extra );
		}
		if ( isset( $extra['relevance'] ) ) {
			$data['relevance'] = min( 100, absint( $extra['relevance'] ) );
		}
		if ( isset( $extra['sample'] ) ) {
			$sample = (array) $extra['sample'];
			// Keep saved numbers when a source refreshes the rest of the sample.
			$old = self::get( (int) $id );
			if ( $old && ! empty( $old['demand'] ) && ! isset( $sample['demand'] ) ) {
				$sample['demand'] = $old['demand'];
			}
			$demand = self::demand( $extra );
			if ( array() !== $demand ) {
				$sample['demand'] = $demand;
			}
			$data['sample'] = wp_json_encode( $sample );
		}
		if ( isset( $extra['session_count'] ) ) {
			$data['session_count'] = absint( $extra['session_count'] );
		}
		if ( isset( $extra['rank_score'] ) ) {
			$data['rank_score'] = round( (float) $extra['rank_score'], 3 );
		}
		if ( isset( $extra['covered_post_id'] ) ) {
			$data['covered_post_id'] = absint( $extra['covered_post_id'] );
		}
		if ( isset( $extra['run_id'] ) ) {
			$data['run_id'] = substr( sanitize_key( (string) $extra['run_id'] ), 0, 40 );
		}
		if ( isset( $extra['group_id'] ) ) {
			$data['group_id'] = absint( $extra['group_id'] );
		}
		foreach ( array( 'topic', 'main_query' ) as $field ) {
			if ( isset( $extra[ $field ] ) && '' !== $extra[ $field ] ) {
				$data[ $field ] = substr( sanitize_text_field( (string) $extra[ $field ] ), 0, 191 );
			}
		}
		if ( isset( $extra['title'] ) ) {
			$data['title'] = Prompts::cut( trim( sanitize_text_field( (string) $extra['title'] ) ), 255 );
		}
		$wpdb->update( Schema::table( Schema::IDEAS ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		return (int) $id;
	}

	/**
	 * Open ideas of one or more sources, best first.
	 *
	 * @param string[] $sources Source ids.
	 * @param int      $limit   Max rows.
	 * @param string[] $statuses Statuses.
	 * @return array[]
	 */
	public static function by_sources( array $sources, $limit = 50, array $statuses = array( 'new' ) ) {
		global $wpdb;
		$sources  = array_values( array_filter( array_map( 'sanitize_key', $sources ) ) );
		$statuses = array_values( array_intersect( $statuses, self::STATUSES ) );
		if ( array() === $sources || array() === $statuses ) {
			return array();
		}
		$table = Schema::table( Schema::IDEAS );
		$in_s  = implode( ',', array_fill( 0, count( $sources ), '%s' ) );
		$in_t  = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE source IN ({$in_s}) AND status IN ({$in_t}) ORDER BY rank_score DESC, last_seen DESC, id DESC LIMIT %d", array_merge( $sources, $statuses, array( min( 1000, max( 1, (int) $limit ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; placeholders built from counts.
		return array_map( array( __CLASS__, 'shape' ), (array) $wpdb->get_results( $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
	}

	/**
	 * Open ideas first seen after a time (for the weekly email).
	 *
	 * @param string $since MySQL datetime (UTC).
	 * @param int    $limit Max rows.
	 * @return array[]
	 */
	public static function new_since( $since, $limit = 50 ) {
		global $wpdb;
		$table = Schema::table( Schema::IDEAS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'new' AND source <> 'manual' AND first_seen > %s ORDER BY rank_score DESC, id DESC LIMIT %d", $since, max( 1, (int) $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	/**
	 * Remove open ideas of a source that were not seen in a run (stale
	 * headlines, events that passed). Dismissed and planned ideas stay.
	 *
	 * @param string $source Source id.
	 * @param string $run_id Run that just finished.
	 * @return int Rows removed.
	 */
	public static function prune( $source, $run_id ) {
		global $wpdb;
		$table = Schema::table( Schema::IDEAS );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE source = %s AND status = 'new' AND run_id <> %s", sanitize_key( $source ), sanitize_key( $run_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
	}

	/**
	 * Public shape.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$sample  = isset( $row['sample'] ) && '' !== (string) $row['sample'] ? json_decode( (string) $row['sample'], true ) : array();
		$sample  = is_array( $sample ) ? $sample : array();
		$covered = (int) $row['covered_post_id'];
		$post    = $covered ? get_post( $covered ) : null;
		return array(
			'id'            => (int) $row['id'],
			'title'         => (string) $row['title'],
			'source'        => (string) $row['source'],
			'source_ref'    => isset( $row['source_ref'] ) ? (string) $row['source_ref'] : '',
			'sources'       => isset( $sample['sources'] ) ? array_values( array_map( 'strval', (array) $sample['sources'] ) ) : array( (string) $row['source'] ),
			'reason'        => (string) $row['reason'],
			'trend'         => (string) $row['trend'],
			'relevance'     => (int) $row['relevance'],
			'status'        => (string) $row['status'],
			'post_id'       => $covered,
			'covered'       => $post && 'publish' === $post->post_status ? array(
				'id'    => $covered,
				'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'url'   => (string) get_permalink( $post ),
			) : null,
			'link'          => isset( $sample['link'] ) ? esc_url_raw( (string) $sample['link'] ) : '',
			'questions'     => isset( $sample['questions'] ) ? array_values( array_map( 'strval', (array) $sample['questions'] ) ) : array(),
			'metrics'       => isset( $sample['metrics'] ) && is_array( $sample['metrics'] ) ? $sample['metrics'] : array(),
			// Search numbers with their source (CR-6); empty when unknown.
			'demand'        => isset( $sample['demand'] ) && is_array( $sample['demand'] ) ? $sample['demand'] : array(),
			'session_count' => (int) $row['session_count'],
			'rank_score'    => (float) $row['rank_score'],
			'group_id'      => (int) $row['group_id'],
			// Shared keyword and topic map (brief 7.10); empty on older ideas.
			'topic'         => isset( $row['topic'] ) ? (string) $row['topic'] : '',
			'main_query'    => isset( $row['main_query'] ) ? (string) $row['main_query'] : '',
			// Another page that is planned or drafted for the same search.
			'planned_by'    => self::planned_by( $row ),
			'first_seen'    => $row['first_seen'] ? mysql_to_rfc3339( $row['first_seen'] ) : '',
			'last_seen'     => $row['last_seen'] ? mysql_to_rfc3339( $row['last_seen'] ) : '',
			'created'       => mysql_to_rfc3339( $row['created_at'] ),
			// CR-16: the article format suggested for this idea (null for none).
			'format'        => \QuantumCloud\AiWriter\Formats\Formats::suggesting() ? \QuantumCloud\AiWriter\Formats\Formats::suggest( (string) $row['title'] ) : null,
		);
	}

	/**
	 * Title of another planned page or draft for this idea's search ('' for none).
	 *
	 * @param array $row Row.
	 * @return string
	 */
	private static function planned_by( array $row ) {
		if ( ! Map_Sync::available() || 'new' !== $row['status'] ) {
			return '';
		}
		$query = isset( $row['main_query'] ) && '' !== (string) $row['main_query'] ? (string) $row['main_query'] : (string) $row['title'];
		$found = Map_Sync::lookup( $query, array( 'plan_refs' => array( Map_Sync::ref( (int) $row['id'] ) ) ) );
		return $found['planned'] ? (string) $found['planned']['title'] : '';
	}
}
