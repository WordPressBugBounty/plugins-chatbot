<?php
/**
 * Opportunities: build from the rules, store, read, dismiss.
 *
 * Rebuilt after every Search Console or Analytics pull. Extensions (the Pro
 * module) add types with qcld_wpbot_seo/opportunity_types.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Opportunities;

use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Opportunity store and builder.
 */
final class Opportunities {

	/**
	 * Hook the rebuilds.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_seo/gsc_pulled', array( __CLASS__, 'rebuild' ) );
		add_action( 'qcld_wpbot_seo/ga_pulled', array( __CLASS__, 'rebuild' ) );
	}

	/**
	 * Types: built in, then extensions'.
	 *
	 * Each: id => [ label, callback( array $context ): array $candidates, action ].
	 *
	 * @return array
	 */
	public static function types() {
		$types = array(
			'near_page_one'   => array(
				'label'    => __( 'Near page one', 'chatbot' ),
				'callback' => static function ( $c ) {
					return Rules::near_page_one( $c['page_query'], $c['config'] );
				},
				'action'   => 'improve',
			),
			'low_ctr'         => array(
				'label'    => __( 'Low click-through', 'chatbot' ),
				'callback' => static function ( $c ) {
					return Rules::low_ctr( $c['pages'], $c['config'] );
				},
				'action'   => 'rewrite_meta',
			),
			'missing_page'    => array(
				'label'    => __( 'Missing page', 'chatbot' ),
				'callback' => static function ( $c ) {
					return Rules::missing_page( $c['queries'], $c['page_query'], $c['config'] );
				},
				'action'   => 'write',
			),
			'poor_engagement' => array(
				'label'    => __( 'Poor engagement', 'chatbot' ),
				'callback' => static function ( $c ) {
					return Rules::poor_engagement( $c['pages'], $c['ga'] );
				},
				'action'   => 'improve',
			),
			'decaying'        => array(
				'label'    => __( 'Decaying page', 'chatbot' ),
				'callback' => static function ( $c ) {
					return Rules::decaying( $c['pages'], $c['pages_before'], $c['config'] );
				},
				'action'   => 'refresh',
			),
		);
		/**
		 * Opportunity types. The Pro module adds chat questions and
		 * competitor gaps; other modules can add their own.
		 *
		 * @param array $types id => [ label, callback( array $context ): array, action ].
		 */
		return (array) apply_filters( 'qcld_wpbot_seo/opportunity_types', $types );
	}

	/**
	 * Data the rules work on (last 28 days of final data and the 28 before).
	 *
	 * @return array
	 */
	public static function context() {
		$period = Period::last( 28 );
		$config = array(
			'near_min_position'  => (float) Settings::get( 'near_min_position' ),
			'near_max_position'  => (float) Settings::get( 'near_max_position' ),
			'min_impressions'    => (int) Settings::get( 'min_impressions' ),
			'decay_drop_percent' => (int) Settings::get( 'decay_drop_percent' ),
		);
		$min    = max( 1, (int) floor( $config['min_impressions'] / 2 ) );
		return array(
			'period'       => $period,
			'config'       => $config,
			'page_query'   => Data_Store::period_rows( 'page_query', $period['start'], $period['end'], $min, 5000 ),
			'pages'        => Data_Store::period_rows( 'page', $period['start'], $period['end'], 1, 3000 ),
			'pages_before' => Data_Store::period_rows( 'page', $period['prev_start'], $period['prev_end'], 1, 3000 ),
			'queries'      => Data_Store::period_rows( 'query', $period['start'], $period['end'], $min, 3000 ),
			'ga'           => Data_Store::ga_period( $period['start'], $period['end'] ),
		);
	}

	/**
	 * Rebuild the list: new and changed opportunities open, those no longer
	 * found removed, dismissed ones left dismissed.
	 *
	 * @return int Open opportunities.
	 */
	public static function rebuild() {
		global $wpdb;
		$table = Schema::table( Schema::OPPS );
		/**
		 * A rebuild starts (types clear their per-rebuild caches).
		 */
		do_action( 'qcld_wpbot_seo/opportunities_rebuild_start' );
		$context = self::context();
		$now     = current_time( 'mysql', true );
		$seen    = array();
		foreach ( self::types() as $id => $type ) {
			if ( empty( $type['callback'] ) || ! is_callable( $type['callback'] ) ) {
				continue;
			}
			foreach ( (array) call_user_func( $type['callback'], $context ) as $candidate ) {
				if ( empty( $candidate['ref'] ) ) {
					continue;
				}
				$candidate['type'] = isset( $candidate['type'] ) ? sanitize_key( $candidate['type'] ) : sanitize_key( $id );
				/**
				 * Business relevance of an opportunity, 0–1 (topics from the
				 * Business Profile or the topic map can lower unrelated ones).
				 *
				 * @param float $relevance Default 1.
				 * @param array $candidate Candidate.
				 */
				$relevance = (float) apply_filters( 'qcld_wpbot_seo/opportunity_relevance', 1.0, $candidate );
				$hash      = md5( (string) $candidate['ref'] );
				$seen[]    = $hash;
				$post_id   = ! empty( $candidate['url_id'] ) ? Data_Store::post_for_url( (int) $candidate['url_id'] ) : ( isset( $candidate['post_id'] ) ? (int) $candidate['post_id'] : 0 );
				$numbers   = isset( $candidate['numbers'] ) ? (array) $candidate['numbers'] : array();
				$numbers   = array_merge(
					$numbers,
					array(
						'url'   => isset( $candidate['url'] ) ? (string) $candidate['url'] : '',
						'query' => isset( $candidate['query'] ) ? (string) $candidate['query'] : '',
					)
				);
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$table} (ref_hash,type,url_id,query_id,post_id,score,potential,effort,action,numbers,status,created_at,updated_at)
						VALUES (%s,%s,%d,%d,%d,%f,%f,%d,%s,%s,'open',%s,%s)
						ON DUPLICATE KEY UPDATE post_id=VALUES(post_id), score=VALUES(score), potential=VALUES(potential), effort=VALUES(effort), action=VALUES(action), numbers=VALUES(numbers), updated_at=VALUES(updated_at)",
						$hash,
						$candidate['type'],
						isset( $candidate['url_id'] ) ? (int) $candidate['url_id'] : 0,
						isset( $candidate['query_id'] ) ? (int) $candidate['query_id'] : 0,
						$post_id,
						Rules::score( $candidate['potential'], isset( $candidate['effort'] ) ? $candidate['effort'] : 2, $relevance ),
						(float) $candidate['potential'],
						isset( $candidate['effort'] ) ? (int) $candidate['effort'] : 2,
						isset( $candidate['action'] ) ? sanitize_key( $candidate['action'] ) : 'improve',
						wp_json_encode( $numbers ),
						$now,
						$now
					)
				);
				// phpcs:enable
			}
		}
		// Open opportunities the rules no longer find are gone.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status = 'open' AND updated_at < %s", $now ) );
		$open = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'open'" );
		// phpcs:enable
		update_option( 'qcld_wpbot_seo_opps_built', gmdate( 'c' ), false );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$new = (array) $wpdb->get_col( $wpdb->prepare( "SELECT type FROM {$table} WHERE status = 'open' AND created_at = %s", $now ) );
		/**
		 * The list was rebuilt (Pro raises an alert for new opportunities).
		 *
		 * @param array $new  type => count of opportunities new in this rebuild.
		 * @param int   $open Open opportunities.
		 */
		do_action( 'qcld_wpbot_seo/opportunities_rebuilt', array_count_values( $new ), $open );
		return $open;
	}

	/**
	 * Open (or dismissed) opportunities, best first.
	 *
	 * @param array $args type, status (open|dismissed|done), limit, offset, post_id.
	 * @return array items, total.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table  = Schema::table( Schema::OPPS );
		$status = isset( $args['status'] ) && in_array( $args['status'], array( 'open', 'dismissed', 'done' ), true ) ? $args['status'] : 'open';
		$where  = $wpdb->prepare( 'status = %s', $status );
		if ( ! empty( $args['type'] ) ) {
			$where .= $wpdb->prepare( ' AND type = %s', sanitize_key( $args['type'] ) );
		}
		if ( ! empty( $args['post_id'] ) ) {
			$where .= $wpdb->prepare( ' AND post_id = %d', (int) $args['post_id'] );
		}
		$limit  = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant; $where built with prepare().
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY score DESC, id ASC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		$types = $wpdb->get_results( "SELECT type, COUNT(*) AS n FROM {$table} WHERE status = 'open' GROUP BY type", ARRAY_A );
		// phpcs:enable
		$counts = array();
		foreach ( (array) $types as $row ) {
			$counts[ $row['type'] ] = (int) $row['n'];
		}
		return array(
			'items'  => array_map( array( __CLASS__, 'shape' ), (array) $rows ),
			'total'  => $total,
			'counts' => $counts,
		);
	}

	/**
	 * One opportunity, or null.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::OPPS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * Set the status (dismissed, done, open).
	 *
	 * @param int    $id     Id.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'open', 'dismissed', 'done' ), true ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return false !== $wpdb->update( Schema::table( Schema::OPPS ), array( 'status' => $status ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Row for screens and the API, with label and reason.
	 *
	 * @param array $row Table row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$numbers = json_decode( (string) $row['numbers'], true );
		$numbers = is_array( $numbers ) ? $numbers : array();
		$types   = self::types();
		$type    = (string) $row['type'];
		$post_id = (int) $row['post_id'];
		return array(
			'id'        => (int) $row['id'],
			'type'      => $type,
			'label'     => isset( $types[ $type ]['label'] ) ? (string) $types[ $type ]['label'] : $type,
			'post_id'   => $post_id,
			'title'     => $post_id ? (string) get_the_title( $post_id ) : '',
			'url'       => isset( $numbers['url'] ) ? esc_url_raw( (string) $numbers['url'], array( 'http', 'https' ) ) : '',
			'query'     => isset( $numbers['query'] ) ? (string) $numbers['query'] : '',
			'score'     => (float) $row['score'],
			'potential' => (float) $row['potential'],
			'effort'    => (int) $row['effort'],
			'action'    => (string) $row['action'],
			'numbers'   => $numbers,
			'reason'    => self::reason( $type, $numbers ),
			'status'    => (string) $row['status'],
		);
	}

	/**
	 * One-line reason in plain words.
	 *
	 * @param string $type    Type.
	 * @param array  $numbers Numbers.
	 * @return string
	 */
	public static function reason( $type, array $numbers ) {
		$n = static function ( $key, $decimals = 0 ) use ( $numbers ) {
			return isset( $numbers[ $key ] ) ? number_format_i18n( (float) $numbers[ $key ], $decimals ) : '0';
		};
		$p = static function ( $key ) use ( $numbers ) {
			return isset( $numbers[ $key ] ) ? number_format_i18n( 100 * (float) $numbers[ $key ], 1 ) . '%' : '0%';
		};
		switch ( $type ) {
			case 'near_page_one':
				/* translators: 1: search, 2: position, 3: impressions. */
				return sprintf( __( 'Ranks at position %2$s for "%1$s" with %3$s impressions in 28 days. A few places higher puts it on page one.', 'chatbot' ), isset( $numbers['query'] ) ? $numbers['query'] : '', $n( 'position', 1 ), $n( 'impressions' ) );
			case 'low_ctr':
				/* translators: 1: CTR, 2: position, 3: expected CTR. */
				return sprintf( __( 'Only %1$s of searchers click at position %2$s, where about %3$s is usual. A clearer title and description can win more clicks.', 'chatbot' ), $p( 'ctr' ), $n( 'position', 1 ), $p( 'expected_ctr' ) );
			case 'missing_page':
				/* translators: 1: search, 2: impressions, 3: best position. */
				return sprintf( __( 'People searched "%1$s" %2$s times, but your best page ranks at %3$s. A page made for this search can rank.', 'chatbot' ), isset( $numbers['query'] ) ? $numbers['query'] : '', $n( 'impressions' ), $n( 'best_position', 1 ) );
			case 'poor_engagement':
				/* translators: 1: page engagement rate, 2: site rate. */
				return sprintf( __( 'Visitors from search leave quickly: %1$s engaged, against %2$s for your site. Answer the search sooner and make the next step clear.', 'chatbot' ), $p( 'engagement_rate' ), $p( 'site_rate' ) );
			case 'decaying':
				/* translators: 1: clicks before, 2: clicks now. */
				return sprintf( __( 'Clicks fell from %1$s to %2$s compared with the 28 days before. A refresh with current facts can bring them back.', 'chatbot' ), $n( 'clicks_before' ), $n( 'clicks' ) );
		}
		/**
		 * Reason text for types added by extensions.
		 *
		 * @param string $reason  Default ''.
		 * @param string $type    Type.
		 * @param array  $numbers Numbers.
		 */
		return (string) apply_filters( 'qcld_wpbot_seo/opportunity_reason', '', $type, $numbers );
	}
}
