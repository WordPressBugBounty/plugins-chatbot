<?php
/**
 * Database access for sessions, pageviews and URLs. All times are UTC.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Storage;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom tables; names come from Schema::table().

final class Session_Repository {

	const STATUS_ACTIVE = 0;
	const STATUS_CLOSED = 1;

	// Frustration flags on sessions.flags.
	const FLAG_RAGE      = 1;
	const FLAG_DEAD      = 2;
	const FLAG_QUICKBACK = 4;
	const FLAG_ERROR     = 8;

	/**
	 * Flag bit per signal type.
	 *
	 * @var array<string,int>
	 */
	const FLAGS = array(
		'rage'      => self::FLAG_RAGE,
		'dead'      => self::FLAG_DEAD,
		'quickback' => self::FLAG_QUICKBACK,
		'error'     => self::FLAG_ERROR,
	);

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
			's'  => Schema::table( 'sessions' ),
			'p'  => Schema::table( 'pageviews' ),
			'u'  => Schema::table( 'urls' ),
			'c'  => Schema::table( 'clicks' ),
			'sg' => Schema::table( 'signals' ),
			'ai' => Schema::table( 'ai_results' ),
		);
	}

	/**
	 * Id for a URL, creating it if new.
	 *
	 * @param string $url   URL without query string (or with allow-listed params).
	 * @param string $title Page title.
	 * @return int
	 */
	public function url_id( $url, $title = '' ) {
		global $wpdb;
		$url  = substr( $url, 0, 2048 );
		$hash = md5( $url );
		$id   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->t['u']} WHERE url_hash = %s", $hash ) );
		if ( $id ) {
			if ( '' !== $title ) {
				$wpdb->update( $this->t['u'], array( 'title' => substr( $title, 0, 255 ) ), array( 'id' => $id ) );
			}
			return $id;
		}
		$ok = $wpdb->insert(
			$this->t['u'],
			array(
				'url_hash' => $hash,
				'url'      => $url,
				'title'    => substr( $title, 0, 255 ),
			)
		);
		if ( $ok ) {
			return (int) $wpdb->insert_id;
		}
		// Lost a race with a parallel request.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->t['u']} WHERE url_hash = %s", $hash ) );
	}

	/**
	 * Session row by public id.
	 *
	 * @param string $uuid Session uuid.
	 * @return object|null
	 */
	public function find_session( $uuid ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->t['s']} WHERE uuid = %s", $uuid ) );
	}

	/**
	 * Session row by internal id.
	 *
	 * @param int $id Session id.
	 * @return object|null
	 */
	public function find_session_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->t['s']} WHERE id = %d", $id ) );
	}

	/**
	 * Insert a session.
	 *
	 * @param array<string,mixed> $data Column values.
	 * @return int Session id (0 on failure).
	 */
	public function create_session( array $data ) {
		global $wpdb;
		if ( ! $wpdb->insert( $this->t['s'], $data ) ) {
			$row = $this->find_session( $data['uuid'] );
			return $row ? (int) $row->id : 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Whether a visitor hash appeared in an earlier session.
	 *
	 * @param string $visitor_hash Hash.
	 * @return bool
	 */
	public function visitor_seen( $visitor_hash ) {
		global $wpdb;
		if ( '' === $visitor_hash ) {
			return false;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$this->t['s']} WHERE visitor_hash = %s LIMIT 1", $visitor_hash ) );
	}

	/**
	 * Recorded sessions started since a UTC time (for the daily recording cap).
	 *
	 * @param string $since MySQL datetime (UTC).
	 * @return int
	 */
	public function count_recordings_since( $since ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->t['s']} WHERE started_at >= %s AND has_recording = 1", $since ) );
	}

	/**
	 * Pageview row by public id.
	 *
	 * @param string $uuid Pageview uuid.
	 * @return object|null
	 */
	public function find_pageview( $uuid ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->t['p']} WHERE uuid = %s", $uuid ) );
	}

	/**
	 * Insert a pageview and bump the session's pageview count.
	 *
	 * @param array<string,mixed> $data Column values (session_id, uuid, url_id, seq, started_at, last_seen_at, viewport_w, viewport_h).
	 * @return int Pageview id.
	 */
	public function create_pageview( array $data ) {
		global $wpdb;
		if ( ! $wpdb->insert( $this->t['p'], $data ) ) {
			$row = $this->find_pageview( $data['uuid'] );
			return $row ? (int) $row->id : 0;
		}
		$id = (int) $wpdb->insert_id;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->t['s']} SET pageviews = pageviews + 1, exit_url_id = %d WHERE id = %d",
				$data['url_id'],
				$data['session_id']
			)
		);
		return $id;
	}

	/**
	 * Update a pageview and its session after a batch: time on page, scroll,
	 * page height and, when a recording chunk was stored, its size.
	 *
	 * @param object $pageview     Pageview row.
	 * @param int    $bytes        Stored chunk size (0 when the batch had no recording).
	 * @param string $now          UTC datetime.
	 * @param int    $duration_ms  Time on page so far.
	 * @param int    $scroll_pct   Max scroll depth seen.
	 * @param object $session      Session row (for the running duration).
	 * @param int    $doc_h        Document height in px.
	 */
	public function touch( $pageview, $bytes, $now, $duration_ms, $scroll_pct, $session, $doc_h = 0 ) {
		global $wpdb;
		$bytes = (int) $bytes;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->t['p']} SET chunk_count = chunk_count + %d, bytes = bytes + %d, last_seen_at = %s,
				 duration_ms = %d, max_scroll_pct = %d, doc_h = %d WHERE id = %d",
				$bytes > 0 ? 1 : 0,
				$bytes,
				$now,
				max( (int) $pageview->duration_ms, (int) $duration_ms ),
				min( 100, max( (int) $pageview->max_scroll_pct, (int) $scroll_pct ) ),
				max( (int) $pageview->doc_h, (int) $doc_h ),
				$pageview->id
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->t['s']} SET bytes = bytes + %d, last_seen_at = %s, duration_s = %d, status = %d"
				. ( $bytes > 0 ? ', has_recording = 1' : '' ) . ' WHERE id = %d',
				$bytes,
				$now,
				max( 0, strtotime( $now . ' UTC' ) - strtotime( $session->started_at . ' UTC' ) ),
				self::STATUS_ACTIVE,
				$pageview->session_id
			)
		);
	}

	/**
	 * Add frustration flags to a session.
	 *
	 * @param int $session_id Session id.
	 * @param int $bits       FLAG_* bits.
	 */
	public function add_flags( $session_id, $bits ) {
		global $wpdb;
		if ( $bits <= 0 ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$this->t['s']} SET flags = flags | %d WHERE id = %d", (int) $bits, (int) $session_id ) );
	}

	/**
	 * Pageviews of a session in order, for quick-back detection.
	 *
	 * @param int $session_id Session id.
	 * @return array<int,object> id, url_id, duration_ms.
	 */
	public function pageview_sequence( $session_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, url_id, duration_ms FROM {$this->t['p']} WHERE session_id = %d ORDER BY started_at ASC, id ASC", $session_id )
		);
		return $rows ? $rows : array();
	}

	/**
	 * Close sessions idle since before a time and compute their duration.
	 *
	 * @param string $before UTC datetime.
	 * @param int    $limit  Max rows.
	 * @return int[] Ids of the sessions closed.
	 */
	public function close_idle( $before, $limit = 500 ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$this->t['s']} WHERE status = %d AND last_seen_at < %s LIMIT %d",
				self::STATUS_ACTIVE,
				$before,
				$limit
			)
		);
		foreach ( $ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT started_at, last_seen_at FROM {$this->t['s']} WHERE id = %d", $id ) );
			if ( ! $row ) {
				continue;
			}
			$duration = max( 0, strtotime( $row->last_seen_at . ' UTC' ) - strtotime( $row->started_at . ' UTC' ) );
			$wpdb->update(
				$this->t['s'],
				array(
					'status'     => self::STATUS_CLOSED,
					'duration_s' => $duration,
				),
				array( 'id' => $id )
			);
		}
		return array_map( 'intval', $ids );
	}

	/**
	 * Paged, filtered session list.
	 *
	 * @param array<string,mixed> $filters  from, to (UTC), device, url (substring), min_duration.
	 * @param int                 $page     1-based page.
	 * @param int                 $per_page Rows per page.
	 * @return array{rows: array<int,object>, total: int}
	 */
	public function list_sessions( array $filters, $page = 1, $per_page = 20 ) {
		global $wpdb;
		$where  = array( 's.has_recording = 1' );
		$params = array();

		if ( ! empty( $filters['from'] ) ) {
			$where[]  = 's.started_at >= %s';
			$params[] = $filters['from'];
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[]  = 's.started_at <= %s';
			$params[] = $filters['to'];
		}
		if ( ! empty( $filters['device'] ) ) {
			$where[]  = 's.device = %s';
			$params[] = $filters['device'];
		}
		if ( ! empty( $filters['frustration'] ) ) {
			$flags = Signal_Types::flags();
			$bits  = 'any' === $filters['frustration'] ? array_sum( $flags ) : ( $flags[ $filters['frustration'] ] ?? 0 );
			if ( $bits ) {
				$where[]  = '(s.flags & %d) > 0';
				$params[] = $bits;
			}
		}
		if ( ! empty( $filters['min_duration'] ) ) {
			$where[]  = 's.duration_s >= %d';
			$params[] = (int) $filters['min_duration'];
		}
		if ( ! empty( $filters['segment'] ) && $filters['segment'] instanceof Segment ) {
			$where[] = $filters['segment']->where;
			$params  = array_merge( $params, $filters['segment']->params );
		}
		if ( ! empty( $filters['url'] ) ) {
			$where[]  = "s.id IN (SELECT p2.session_id FROM {$this->t['p']} p2 JOIN {$this->t['u']} u2 ON u2.id = p2.url_id WHERE u2.url LIKE %s)";
			$params[] = '%' . $wpdb->esc_like( $filters['url'] ) . '%';
		}

		/**
		 * Add conditions to the recordings list (segments, feature filters).
		 * Add SQL to 'where' with %s / %d placeholders and the values to
		 * 'params', in the same order. The sessions table alias is "s".
		 * Never put raw request values into 'where'.
		 *
		 * @param array{where:string[],params:array} $query   Conditions so far.
		 * @param array<string,mixed>                $filters Filters, with 'extra' = other request params.
		 */
		$query = apply_filters(
			'qcin/session_query',
			array(
				'where'  => $where,
				'params' => $params,
			),
			$filters
		);
		if ( is_array( $query ) && isset( $query['where'], $query['params'] ) && is_array( $query['where'] ) && is_array( $query['params'] ) ) {
			$where  = array_values( array_filter( array_map( 'strval', $query['where'] ) ) );
			$params = array_values( $query['params'] );
		}
		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$this->t['s']} s WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$offset   = max( 0, ( (int) $page - 1 ) * (int) $per_page );
		$list_sql = "SELECT s.*, u.url AS entry_url, u.title AS entry_title
			FROM {$this->t['s']} s LEFT JOIN {$this->t['u']} u ON u.id = s.entry_url_id
			WHERE {$where_sql} ORDER BY s.started_at DESC LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( (int) $per_page, $offset ) ) ) );

		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Pageviews of a session with their URLs, in order.
	 *
	 * @param int $session_id Session id.
	 * @return array<int,object>
	 */
	public function pageviews( $session_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, u.url, u.title FROM {$this->t['p']} p LEFT JOIN {$this->t['u']} u ON u.id = p.url_id
				 WHERE p.session_id = %d ORDER BY p.started_at ASC, p.seq ASC",
				$session_id
			)
		);
		return $rows ? $rows : array();
	}

	/**
	 * Delete a session's rows.
	 *
	 * @param int $session_id Session id.
	 */
	public function delete_session( $session_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->t['c']} WHERE pageview_id IN (SELECT id FROM {$this->t['p']} WHERE session_id = %d)", (int) $session_id ) );
		$wpdb->delete( $this->t['sg'], array( 'session_id' => (int) $session_id ) );
		$wpdb->delete( $this->t['ai'], array( 'session_id' => (int) $session_id ) );
		$wpdb->delete( $this->t['p'], array( 'session_id' => (int) $session_id ) );
		$wpdb->delete( $this->t['s'], array( 'id' => (int) $session_id ) );
		/**
		 * A session's rows were deleted (retention, storage cap, "Delete",
		 * privacy eraser). Features remove their own rows for it.
		 *
		 * @param int $session_id Session id.
		 */
		do_action( 'qcin/session_deleted', (int) $session_id );
	}

	/**
	 * Sessions older than a time that are not favourites.
	 *
	 * @param string $before UTC datetime.
	 * @param int    $limit  Max rows.
	 * @return array<int,object> id + uuid.
	 */
	public function expired( $before, $limit = 200 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, uuid FROM {$this->t['s']} WHERE started_at < %s AND favourite = 0 ORDER BY started_at ASC LIMIT %d",
				$before,
				$limit
			)
		);
		return $rows ? $rows : array();
	}

	/**
	 * Oldest non-favourite sessions (for the storage cap).
	 *
	 * @param int $limit Max rows.
	 * @return array<int,object> id + uuid + bytes.
	 */
	public function oldest( $limit = 100 ) {
		global $wpdb;
		/**
		 * Extra conditions for the sessions the storage limit may delete
		 * (e.g. leave out recordings already moved off this server).
		 * Same shape as qcin/session_query; the sessions table alias is "s".
		 *
		 * @param array{where:string[],params:array} $query Conditions.
		 */
		$query  = apply_filters(
			'qcin/oldest_query',
			array(
				'where'  => array( 's.favourite = 0' ),
				'params' => array(),
			)
		);
		$where  = array( 's.favourite = 0' );
		$params = array();
		if ( is_array( $query ) && isset( $query['where'], $query['params'] ) && is_array( $query['where'] ) && is_array( $query['params'] ) ) {
			$where  = array_values( array_filter( array_map( 'strval', $query['where'] ) ) );
			$params = array_values( $query['params'] );
		}
		$params[] = (int) $limit;
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.uuid, s.bytes FROM {$this->t['s']} s WHERE " . implode( ' AND ', $where ) . ' ORDER BY s.started_at ASC LIMIT %d',
				$params
			)
		);
		return $rows ? $rows : array();
	}

	/**
	 * Total stored bytes.
	 *
	 * @return int
	 */
	public function total_bytes() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COALESCE(SUM(bytes), 0) FROM {$this->t['s']}" );
	}

	/**
	 * Simple counts for the dashboard strip.
	 *
	 * @param string $since UTC datetime.
	 * @return array<string,float|int>
	 */
	public function stats( $since ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS sessions, COALESCE(AVG(duration_s), 0) AS avg_duration, COALESCE(AVG(pageviews), 0) AS avg_pages
				 FROM {$this->t['s']} WHERE started_at >= %s AND has_recording = 1",
				$since
			)
		);
		return array(
			'sessions'     => $row ? (int) $row->sessions : 0,
			'avg_duration' => $row ? round( (float) $row->avg_duration ) : 0,
			'avg_pages'    => $row ? round( (float) $row->avg_pages, 1 ) : 0,
		);
	}

	/**
	 * Delete every row in the module tables.
	 */
	public function truncate() {
		global $wpdb;
		foreach ( Schema::names() as $name ) {
			$table = Schema::table( $name );
			$wpdb->query( "DELETE FROM {$table}" );
		}
		foreach ( Schema::feature_tables() as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Names checked in feature_tables().
		}
	}

	/**
	 * Overview figures for all tracked sessions (recorded or not) since a time.
	 *
	 * @param string       $since UTC datetime.
	 * @param Segment|null $seg   Segment.
	 * @return array<string,mixed>
	 */
	public function overview( $since, $seg = null ) {
		global $wpdb;
		$and = Segment::and_sql( $seg );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS sessions, COALESCE(AVG(s.duration_s), 0) AS avg_duration, COALESCE(AVG(s.pageviews), 0) AS avg_pages,
				 COALESCE(SUM(CASE WHEN s.flags > 0 THEN 1 ELSE 0 END), 0) AS frustrated,
				 COALESCE(SUM(CASE WHEN s.has_recording = 1 THEN 1 ELSE 0 END), 0) AS recorded
				 FROM {$this->t['s']} s WHERE s.started_at >= %s AND s.pageviews > 0{$and}",
				array_merge( array( $since ), Segment::and_params( $seg ) )
			)
		);
		$scroll = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(AVG(p.max_scroll_pct), 0) FROM {$this->t['p']} p JOIN {$this->t['s']} s ON s.id = p.session_id WHERE p.started_at >= %s{$and}",
				array_merge( array( $since ), Segment::and_params( $seg ) )
			)
		);
		$flags = array();
		foreach ( Signal_Types::flags() as $type => $bit ) {
			$flags[ $type ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->t['s']} s WHERE s.started_at >= %s AND (s.flags & %d) > 0{$and}",
					array_merge( array( $since, $bit ), Segment::and_params( $seg ) )
				)
			);
		}
		$sessions = $row ? (int) $row->sessions : 0;
		return array(
			'sessions'       => $sessions,
			'recorded'       => $row ? (int) $row->recorded : 0,
			'avg_duration'   => $row ? round( (float) $row->avg_duration ) : 0,
			'avg_pages'      => $row ? round( (float) $row->avg_pages, 1 ) : 0,
			'avg_scroll'     => round( $scroll ),
			'frustrated'     => $row ? (int) $row->frustrated : 0,
			'frustrated_pct' => $sessions ? round( 100 * (int) $row->frustrated / $sessions, 1 ) : 0,
			'flags'          => $flags,
		);
	}

	/**
	 * Sessions per UTC day since a time.
	 *
	 * @param string       $since UTC datetime.
	 * @param Segment|null $seg   Segment.
	 * @return array<string,array<string,int>> day => sessions, frustrated.
	 */
	public function sessions_per_day( $since, $seg = null ) {
		global $wpdb;
		$and  = Segment::and_sql( $seg );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(s.started_at) AS day, COUNT(*) AS sessions, SUM(CASE WHEN s.flags > 0 THEN 1 ELSE 0 END) AS frustrated
				 FROM {$this->t['s']} s WHERE s.started_at >= %s AND s.pageviews > 0{$and} GROUP BY DATE(s.started_at)",
				array_merge( array( $since ), Segment::and_params( $seg ) )
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (string) $r->day ] = array(
				'sessions'   => (int) $r->sessions,
				'frustrated' => (int) $r->frustrated,
			);
		}
		return $out;
	}

	/**
	 * Most viewed pages since a time.
	 *
	 * @param string       $since UTC datetime.
	 * @param int          $limit Max rows.
	 * @param Segment|null $seg   Segment.
	 * @return array<int,object> url_id, url, title, views, avg_time_ms, avg_scroll.
	 */
	public function top_pages( $since, $limit = 10, $seg = null ) {
		global $wpdb;
		$join = $seg ? " JOIN {$this->t['s']} s ON s.id = p.session_id" : '';
		$and  = Segment::and_sql( $seg );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.url_id, u.url, u.title, COUNT(*) AS views, AVG(p.duration_ms) AS avg_time_ms, AVG(p.max_scroll_pct) AS avg_scroll
				 FROM {$this->t['p']} p LEFT JOIN {$this->t['u']} u ON u.id = p.url_id{$join}
				 WHERE p.started_at >= %s{$and} GROUP BY p.url_id, u.url, u.title ORDER BY views DESC LIMIT %d",
				array_merge( array( $since ), Segment::and_params( $seg ), array( $limit ) )
			)
		);
		return $rows ? $rows : array();
	}
}
