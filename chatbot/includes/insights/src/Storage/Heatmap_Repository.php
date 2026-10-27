<?php
/**
 * Clicks, CSS selectors and the daily heatmap and scroll aggregates.
 *
 * Raw clicks are kept for 7 days. An hourly job folds them into
 * heatmap_bins (10 x 10 grid inside each element, per URL, device and UTC
 * day) and finished pageviews into scroll_bins (5% depth steps). Queries
 * read the aggregates plus whatever has not been aggregated yet, so new
 * data shows up immediately.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Storage;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- Custom tables; names come from Schema::table().

final class Heatmap_Repository {

	const WATERMARK_OPTION = 'qcin_hm_watermark';
	const DEVICES          = array( 'desktop', 'tablet', 'mobile' );

	/**
	 * Table names.
	 *
	 * @var array<string,string>
	 */
	private $t;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->t = array(
			's'   => Schema::table( 'sessions' ),
			'p'   => Schema::table( 'pageviews' ),
			'u'   => Schema::table( 'urls' ),
			'sel' => Schema::table( 'selectors' ),
			'c'   => Schema::table( 'clicks' ),
			'hb'  => Schema::table( 'heatmap_bins' ),
			'sb'  => Schema::table( 'scroll_bins' ),
		);
	}

	/**
	 * Ids for CSS selectors, creating the missing ones.
	 *
	 * @param string[] $selectors Selectors.
	 * @return array<string,int> selector => id.
	 */
	public function selector_ids( array $selectors ) {
		global $wpdb;
		$out     = array();
		$missing = array();
		foreach ( array_unique( array_filter( $selectors, 'strlen' ) ) as $selector ) {
			$cached = wp_cache_get( 'sel_' . md5( $selector ), 'qcin' );
			if ( false !== $cached ) {
				$out[ $selector ] = (int) $cached;
			} else {
				$missing[ md5( $selector ) ] = $selector;
			}
		}
		if ( $missing ) {
			$hashes       = array_keys( $missing );
			$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
			$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT id, hash FROM {$this->t['sel']} WHERE hash IN ({$placeholders})", $hashes ) );
			foreach ( (array) $rows as $row ) {
				$selector         = $missing[ $row->hash ];
				$out[ $selector ] = (int) $row->id;
				wp_cache_set( 'sel_' . $row->hash, (int) $row->id, 'qcin', DAY_IN_SECONDS );
				unset( $missing[ $row->hash ] );
			}
			foreach ( $missing as $hash => $selector ) {
				if ( $wpdb->insert(
					$this->t['sel'],
					array(
						'hash'     => $hash,
						'selector' => $selector,
					)
				) ) {
					$id = (int) $wpdb->insert_id;
				} else {
					// Lost a race with a parallel request.
					$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->t['sel']} WHERE hash = %s", $hash ) );
				}
				if ( $id ) {
					$out[ $selector ] = $id;
					wp_cache_set( 'sel_' . $hash, $id, 'qcin', DAY_IN_SECONDS );
				}
			}
		}
		return $out;
	}

	/**
	 * Store a batch of clicks in one query.
	 *
	 * @param object                          $pageview Pageview row.
	 * @param string                          $device   Session device class.
	 * @param array<int,array<string,mixed>>  $clicks   Clean clicks from the validator.
	 * @param array<string,int>               $ids      selector => id.
	 * @param string                          $now      UTC datetime.
	 * @return int Rows stored.
	 */
	public function insert_clicks( $pageview, $device, array $clicks, array $ids, $now ) {
		global $wpdb;
		$values = array();
		foreach ( $clicks as $c ) {
			if ( empty( $ids[ $c['selector'] ] ) ) {
				continue;
			}
			$values[] = $wpdb->prepare(
				'(%d,%d,%s,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%s)',
				$pageview->id,
				$pageview->url_id,
				$device,
				$ids[ $c['selector'] ],
				$c['off_x'],
				$c['off_y'],
				$c['bin_x'],
				$c['bin_y'],
				$c['page_x'],
				$c['page_y'],
				$c['doc_w'],
				$c['doc_h'],
				$c['dead'],
				$c['rage'],
				$now
			);
		}
		if ( ! $values ) {
			return 0;
		}
		$wpdb->query(
			"INSERT INTO {$this->t['c']} (pageview_id,url_id,device,selector_id,off_x,off_y,bin_x,bin_y,page_x,page_y,doc_w,doc_h,is_dead,is_rage,created_at) VALUES "
			. implode( ',', $values )
		);
		return count( $values );
	}

	/**
	 * Fold raw clicks past the watermark into heatmap_bins.
	 *
	 * @param int $limit Max clicks this run.
	 * @return int Clicks processed.
	 */
	public function aggregate_clicks( $limit = 5000 ) {
		global $wpdb;
		$mark = $this->watermark();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, url_id, device, selector_id, bin_x, bin_y, created_at FROM {$this->t['c']} WHERE id > %d ORDER BY id ASC LIMIT %d",
				$mark,
				$limit
			)
		);
		if ( ! $rows ) {
			return 0;
		}
		$groups = array();
		$max    = $mark;
		foreach ( $rows as $r ) {
			$day = substr( (string) $r->created_at, 0, 10 );
			$key = implode( '|', array( $r->url_id, $r->device, $day, $r->selector_id, $r->bin_x, $r->bin_y ) );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'url_id'      => (int) $r->url_id,
					'device'      => (string) $r->device,
					'day'         => $day,
					'selector_id' => (int) $r->selector_id,
					'bin_x'       => (int) $r->bin_x,
					'bin_y'       => (int) $r->bin_y,
					'n'           => 0,
				);
			}
			++$groups[ $key ]['n'];
			$max = max( $max, (int) $r->id );
		}
		foreach ( $groups as $g ) {
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->t['hb']} SET clicks = clicks + %d WHERE url_id = %d AND device = %s AND day = %s AND selector_id = %d AND bin_x = %d AND bin_y = %d",
					$g['n'],
					$g['url_id'],
					$g['device'],
					$g['day'],
					$g['selector_id'],
					$g['bin_x'],
					$g['bin_y']
				)
			);
			if ( ! $updated ) {
				$wpdb->insert(
					$this->t['hb'],
					array(
						'url_id'      => $g['url_id'],
						'device'      => $g['device'],
						'day'         => $g['day'],
						'selector_id' => $g['selector_id'],
						'bin_x'       => $g['bin_x'],
						'bin_y'       => $g['bin_y'],
						'clicks'      => $g['n'],
					)
				);
			}
		}
		update_option( self::WATERMARK_OPTION, $max, false );
		return count( $rows );
	}

	/**
	 * Fold finished pageviews (no activity for 30 minutes) into scroll_bins.
	 *
	 * @param string $before UTC datetime; pageviews last seen before this are final.
	 * @param int    $limit  Max pageviews this run.
	 * @return int Pageviews processed.
	 */
	public function aggregate_scroll( $before, $limit = 5000 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id, p.url_id, p.max_scroll_pct, p.started_at, s.device FROM {$this->t['p']} p
				 JOIN {$this->t['s']} s ON s.id = p.session_id
				 WHERE p.aggregated = 0 AND p.last_seen_at < %s ORDER BY p.id ASC LIMIT %d",
				$before,
				$limit
			)
		);
		if ( ! $rows ) {
			return 0;
		}
		$groups = array();
		$ids    = array();
		foreach ( $rows as $r ) {
			$ids[]  = (int) $r->id;
			$bucket = self::bucket( (int) $r->max_scroll_pct );
			$day    = substr( (string) $r->started_at, 0, 10 );
			$key    = $r->url_id . '|' . $r->device . '|' . $day . '|' . $bucket;
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array( (int) $r->url_id, (string) $r->device, $day, $bucket, 0 );
			}
			++$groups[ $key ][4];
		}
		foreach ( $groups as $g ) {
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->t['sb']} SET views = views + %d WHERE url_id = %d AND device = %s AND day = %s AND depth_bucket = %d",
					$g[4],
					$g[0],
					$g[1],
					$g[2],
					$g[3]
				)
			);
			if ( ! $updated ) {
				$wpdb->insert(
					$this->t['sb'],
					array(
						'url_id'       => $g[0],
						'device'       => $g[1],
						'day'          => $g[2],
						'depth_bucket' => $g[3],
						'views'        => $g[4],
					)
				);
			}
		}
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$wpdb->query( "UPDATE {$this->t['p']} SET aggregated = 1 WHERE id IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ')' );
		}
		return count( $rows );
	}

	/**
	 * 5% scroll bucket (0, 5, ... 100) for a depth.
	 *
	 * @param int $pct Depth 0-100.
	 * @return int
	 */
	public static function bucket( $pct ) {
		return (int) min( 100, max( 0, intdiv( (int) $pct, 5 ) * 5 ) );
	}

	/**
	 * Last raw click id folded into heatmap_bins.
	 *
	 * @return int
	 */
	public function watermark() {
		global $wpdb;
		$mark = (int) get_option( self::WATERMARK_OPTION, 0 );
		if ( $mark > 0 ) {
			// Raw clicks are pruned oldest first, so the newest aggregated row normally
			// stays. If every row up to the mark is gone (all pruned or deleted) or the
			// ids restarted (older MySQL resets AUTO_INCREMENT on an empty table after a
			// restart), all remaining rows are new: start again from zero.
			$max = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$this->t['c']}" );
			if ( $max < $mark ) {
				update_option( self::WATERMARK_OPTION, 0, false );
				return 0;
			}
		}
		return $mark;
	}

	/**
	 * Click heatmap for one page and device: aggregated days plus clicks not
	 * aggregated yet.
	 *
	 * @param int    $url_id   URL id.
	 * @param string $device   Device class.
	 * @param string $from_day First UTC day (Y-m-d).
	 * @return array{elements: array<int,array<string,mixed>>, total: int}
	 */
	public function clicks( $url_id, $device, $from_day ) {
		global $wpdb;
		$agg  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT selector_id, bin_x, bin_y, SUM(clicks) AS n FROM {$this->t['hb']}
				 WHERE url_id = %d AND device = %s AND day >= %s GROUP BY selector_id, bin_x, bin_y",
				$url_id,
				$device,
				$from_day
			)
		);
		$live = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT selector_id, bin_x, bin_y, COUNT(*) AS n FROM {$this->t['c']}
				 WHERE id > %d AND url_id = %d AND device = %s AND created_at >= %s GROUP BY selector_id, bin_x, bin_y",
				$this->watermark(),
				$url_id,
				$device,
				$from_day . ' 00:00:00'
			)
		);
		return $this->elements_from_rows( array_merge( (array) $agg, (array) $live ) );
	}

	/**
	 * Elements with their bins from rows of selector_id, bin_x, bin_y, n.
	 *
	 * @param array<int,object> $rows Rows.
	 * @return array{elements: array<int,array<string,mixed>>, total: int}
	 */
	private function elements_from_rows( array $rows ) {
		$by    = array();
		$total = 0;
		foreach ( $rows as $r ) {
			$sid = (int) $r->selector_id;
			$key = (int) $r->bin_x . ',' . (int) $r->bin_y;
			if ( ! isset( $by[ $sid ] ) ) {
				$by[ $sid ] = array();
			}
			$by[ $sid ][ $key ] = ( $by[ $sid ][ $key ] ?? 0 ) + (int) $r->n;
			$total             += (int) $r->n;
		}
		$selectors = $this->selector_text( array_keys( $by ) );
		$elements  = array();
		foreach ( $by as $sid => $bins ) {
			$list  = array();
			$count = 0;
			foreach ( $bins as $key => $n ) {
				list( $bx, $byy ) = array_map( 'intval', explode( ',', $key ) );
				$list[]           = array( $bx, $byy, $n );
				$count           += $n;
			}
			$elements[] = array(
				'id'       => $sid,
				'selector' => $selectors[ $sid ] ?? '',
				'clicks'   => $count,
				'bins'     => $list,
			);
		}
		usort(
			$elements,
			static function ( $a, $b ) {
				return $b['clicks'] <=> $a['clicks'];
			}
		);
		return array(
			'elements' => $elements,
			'total'    => $total,
		);
	}

	/**
	 * Days raw clicks are kept after they are folded into the daily
	 * heatmaps. Segmented heatmaps (built from raw clicks) cover this long.
	 *
	 * @return int
	 */
	public static function raw_days() {
		/**
		 * Days to keep raw clicks (1-90, default 7).
		 *
		 * @param int $days Days.
		 */
		return max( 1, min( 90, (int) apply_filters( 'qcin/raw_click_days', 7 ) ) );
	}

	/**
	 * Click heatmap for one page and device for the visits in a segment,
	 * from raw clicks (kept raw_days() days).
	 *
	 * @param int     $url_id URL id.
	 * @param string  $device Device class.
	 * @param string  $since  UTC datetime.
	 * @param Segment $seg    Segment.
	 * @return array{elements: array<int,array<string,mixed>>, total: int}
	 */
	public function clicks_in_segment( $url_id, $device, $since, Segment $seg ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.selector_id, c.bin_x, c.bin_y, COUNT(*) AS n FROM {$this->t['c']} c
				 JOIN {$this->t['p']} p ON p.id = c.pageview_id
				 JOIN {$this->t['s']} s ON s.id = p.session_id
				 WHERE c.url_id = %d AND c.device = %s AND c.created_at >= %s AND {$seg->where}
				 GROUP BY c.selector_id, c.bin_x, c.bin_y",
				array_merge( array( $url_id, $device, $since ), $seg->params )
			)
		);
		return $this->elements_from_rows( (array) $rows );
	}

	/**
	 * Scroll reach for the visits in a segment (from pageviews).
	 *
	 * @param int     $url_id URL id.
	 * @param string  $device Device class.
	 * @param string  $since  UTC datetime.
	 * @param Segment $seg    Segment.
	 * @return array{buckets: array<int,int>, views: int, fold: int}
	 */
	public function scroll_in_segment( $url_id, $device, $since, Segment $seg ) {
		global $wpdb;
		$buckets = array_fill_keys( range( 0, 100, 5 ), 0 );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.max_scroll_pct AS pct, COUNT(*) AS n FROM {$this->t['p']} p JOIN {$this->t['s']} s ON s.id = p.session_id
				 WHERE p.url_id = %d AND s.device = %s AND p.started_at >= %s AND {$seg->where} GROUP BY p.max_scroll_pct",
				array_merge( array( $url_id, $device, $since ), $seg->params )
			)
		);
		foreach ( (array) $rows as $r ) {
			$buckets[ self::bucket( (int) $r->pct ) ] += (int) $r->n;
		}
		$fold = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CASE WHEN p.viewport_h * 100 / p.doc_h > 100 THEN 100 ELSE p.viewport_h * 100 / p.doc_h END) FROM {$this->t['p']} p
				 JOIN {$this->t['s']} s ON s.id = p.session_id
				 WHERE p.url_id = %d AND s.device = %s AND p.started_at >= %s AND p.doc_h > 0 AND p.viewport_h > 0 AND {$seg->where}",
				array_merge( array( $url_id, $device, $since ), $seg->params )
			)
		);
		return array(
			'buckets' => $buckets,
			'views'   => array_sum( $buckets ),
			'fold'    => (int) round( $fold ),
		);
	}

	/**
	 * Scroll reach for one page and device: views per 5% bucket of maximum
	 * depth, plus the average fold.
	 *
	 * @param int    $url_id   URL id.
	 * @param string $device   Device class.
	 * @param string $from_day First UTC day (Y-m-d).
	 * @return array{buckets: array<int,int>, views: int, fold: int}
	 */
	public function scroll( $url_id, $device, $from_day ) {
		global $wpdb;
		$buckets = array_fill_keys( range( 0, 100, 5 ), 0 );
		$agg     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT depth_bucket, SUM(views) AS n FROM {$this->t['sb']} WHERE url_id = %d AND device = %s AND day >= %s GROUP BY depth_bucket",
				$url_id,
				$device,
				$from_day
			)
		);
		foreach ( (array) $agg as $r ) {
			$buckets[ self::bucket( (int) $r->depth_bucket ) ] += (int) $r->n;
		}
		$live = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.max_scroll_pct AS pct, COUNT(*) AS n FROM {$this->t['p']} p JOIN {$this->t['s']} s ON s.id = p.session_id
				 WHERE p.aggregated = 0 AND p.url_id = %d AND s.device = %s AND p.started_at >= %s GROUP BY p.max_scroll_pct",
				$url_id,
				$device,
				$from_day . ' 00:00:00'
			)
		);
		foreach ( (array) $live as $r ) {
			$buckets[ self::bucket( (int) $r->pct ) ] += (int) $r->n;
		}
		$fold = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CASE WHEN p.viewport_h * 100 / p.doc_h > 100 THEN 100 ELSE p.viewport_h * 100 / p.doc_h END) FROM {$this->t['p']} p
				 JOIN {$this->t['s']} s ON s.id = p.session_id
				 WHERE p.url_id = %d AND s.device = %s AND p.started_at >= %s AND p.doc_h > 0 AND p.viewport_h > 0",
				$url_id,
				$device,
				$from_day . ' 00:00:00'
			)
		);
		return array(
			'buckets' => $buckets,
			'views'   => array_sum( $buckets ),
			'fold'    => (int) round( $fold ),
		);
	}

	/**
	 * Pages with tracked views, most viewed first, for the page picker.
	 *
	 * @param string $since  UTC datetime.
	 * @param string $device Device class or '' for all.
	 * @param string $search Substring of the URL or title.
	 * @param int          $limit  Max rows.
	 * @param Segment|null $seg    Segment.
	 * @return array<int,object> url_id, url, title, views.
	 */
	public function pages( $since, $device = '', $search = '', $limit = 50, $seg = null ) {
		global $wpdb;
		$where  = array( 'p.started_at >= %s' );
		$params = array( $since );
		if ( $seg ) {
			$where[] = $seg->where;
			$params  = array_merge( $params, $seg->params );
		}
		if ( '' !== $device ) {
			$where[]  = 's.device = %s';
			$params[] = $device;
		}
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(u.url LIKE %s OR u.title LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		$params[] = (int) $limit;
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.url_id, u.url, u.title, COUNT(*) AS views FROM {$this->t['p']} p
				 JOIN {$this->t['s']} s ON s.id = p.session_id
				 JOIN {$this->t['u']} u ON u.id = p.url_id
				 WHERE " . implode( ' AND ', $where ) . ' GROUP BY p.url_id, u.url, u.title ORDER BY views DESC LIMIT %d',
				$params
			)
		);
		return $rows ? $rows : array();
	}

	/**
	 * Most recent recorded pageview of a page on a device, used to draw the
	 * heatmap over a recorded snapshot instead of the live page.
	 *
	 * @param int    $url_id URL id.
	 * @param string $device Device class.
	 * @return object|null session (uuid), pageview (uuid), viewport_w, viewport_h.
	 */
	public function latest_recording( $url_id, $device ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.uuid AS session, p.uuid AS pageview, p.viewport_w, p.viewport_h FROM {$this->t['p']} p
				 JOIN {$this->t['s']} s ON s.id = p.session_id
				 WHERE p.url_id = %d AND s.device = %s AND p.chunk_count > 0 ORDER BY p.started_at DESC LIMIT 1",
				$url_id,
				$device
			)
		);
	}

	/**
	 * Selector text by id.
	 *
	 * @param int[] $ids Selector ids.
	 * @return array<int,string>
	 */
	public function selector_text( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$rows = $wpdb->get_results( "SELECT id, selector FROM {$this->t['sel']} WHERE id IN (" . implode( ',', $ids ) . ')' );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->id ] = (string) $r->selector;
		}
		return $out;
	}

	/**
	 * URL row by id.
	 *
	 * @param int $url_id URL id.
	 * @return object|null
	 */
	public function url( $url_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, url, title FROM {$this->t['u']} WHERE id = %d", $url_id ) );
	}

	/**
	 * Delete raw clicks older than a time, in batches.
	 *
	 * @param string $before UTC datetime.
	 * @param int    $limit  Max rows.
	 * @return int Rows deleted.
	 */
	public function prune_clicks( $before, $limit = 5000 ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$this->t['c']} WHERE created_at < %s ORDER BY id ASC LIMIT %d", $before, $limit ) );
		if ( ! $ids ) {
			return 0;
		}
		$wpdb->query( "DELETE FROM {$this->t['c']} WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' );
		return count( $ids );
	}

	/**
	 * Delete daily aggregates older than a day.
	 *
	 * @param string $before_day UTC day (Y-m-d).
	 */
	public function prune_bins( $before_day ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->t['hb']} WHERE day < %s", $before_day ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->t['sb']} WHERE day < %s", $before_day ) );
	}

	/**
	 * Row counts for the health panel.
	 *
	 * @return array<string,int>
	 */
	public function counts() {
		global $wpdb;
		return array(
			'raw_clicks'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->t['c']}" ),
			'pending'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->t['c']} WHERE id > %d", $this->watermark() ) ),
			'heatmap_bins' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->t['hb']}" ),
		);
	}
}
