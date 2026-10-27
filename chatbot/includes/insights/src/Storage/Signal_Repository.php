<?php
/**
 * Frustration signals (rage, dead, quick back, JavaScript error) and custom
 * events, stored per pageview. Kept as long as their session.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Storage;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom tables; names come from Schema::table().

final class Signal_Repository {

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
			'sg'  => Schema::table( 'signals' ),
			'sel' => Schema::table( 'selectors' ),
		);
	}

	/**
	 * Store signals in one query.
	 *
	 * @param int                            $session_id  Session id.
	 * @param int                            $pageview_id Pageview id.
	 * @param array<int,array<string,mixed>> $signals     Clean signals (type, ts, selector, data).
	 * @param array<string,int>              $ids         selector => id.
	 * @param string                         $now         UTC datetime.
	 * @return int Session flag bits these signals set.
	 */
	public function insert( $session_id, $pageview_id, array $signals, array $ids, $now ) {
		global $wpdb;
		$values = array();
		$bits   = 0;
		foreach ( $signals as $sig ) {
			$values[] = $wpdb->prepare(
				'(%d,%d,%s,%d,%d,%s,%s)',
				$session_id,
				$pageview_id,
				$sig['type'],
				$sig['ts'],
				$ids[ $sig['selector'] ] ?? 0,
				$sig['data'] ? wp_json_encode( $sig['data'] ) : '',
				$now
			);
			$bits |= Signal_Types::flags()[ $sig['type'] ] ?? 0;
		}
		if ( $values ) {
			$wpdb->query( "INSERT INTO {$this->t['sg']} (session_id,pageview_id,type,ts_ms,selector_id,data,created_at) VALUES " . implode( ',', $values ) );
		}
		return $bits;
	}

	/**
	 * Signals of a session, oldest first.
	 *
	 * @param int $session_id Session id.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_session( $session_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sg.pageview_id, p.uuid AS pageview, sg.type, sg.ts_ms, sel.selector, sg.data FROM {$this->t['sg']} sg
				 LEFT JOIN {$this->t['p']} p ON p.id = sg.pageview_id
				 LEFT JOIN {$this->t['sel']} sel ON sel.id = sg.selector_id
				 WHERE sg.session_id = %d ORDER BY sg.id ASC LIMIT 500",
				$session_id
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$data  = json_decode( (string) $r->data, true );
			$out[] = array(
				'pageview' => (string) $r->pageview,
				'type'     => (string) $r->type,
				'ts'       => (int) $r->ts_ms,
				'selector' => (string) $r->selector,
				'data'     => is_array( $data ) ? $data : new \stdClass(),
			);
		}
		return $out;
	}

	/**
	 * Rage and dead clicks per element for one page and device.
	 *
	 * @param int    $url_id URL id.
	 * @param string $device Device class.
	 * @param string       $since  UTC datetime.
	 * @param Segment|null $seg    Segment.
	 * @return array<int,array<string,int>> selector id => [ rage => n, dead => n ].
	 */
	public function by_element( $url_id, $device, $since, $seg = null ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sg.selector_id, sg.type, COUNT(*) AS n FROM {$this->t['sg']} sg
				 JOIN {$this->t['p']} p ON p.id = sg.pageview_id
				 JOIN {$this->t['s']} s ON s.id = sg.session_id
				 WHERE p.url_id = %d AND s.device = %s AND sg.created_at >= %s AND sg.type IN ('rage','dead')" . Segment::and_sql( $seg ) . '
				 GROUP BY sg.selector_id, sg.type',
				array_merge( array( $url_id, $device, $since ), Segment::and_params( $seg ) )
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->selector_id ][ (string) $r->type ] = (int) $r->n;
		}
		return $out;
	}

	/**
	 * Most frequent JavaScript errors since a time.
	 *
	 * @param string       $since UTC datetime.
	 * @param int          $limit Max rows.
	 * @param Segment|null $seg   Segment.
	 * @return array<int,array<string,mixed>> message, source, line, count, sessions.
	 */
	public function top_errors( $since, $limit = 5, $seg = null ) {
		global $wpdb;
		$join = $seg ? " JOIN {$this->t['s']} s ON s.id = sg.session_id" : '';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sg.data, COUNT(*) AS n, COUNT(DISTINCT sg.session_id) AS sessions FROM {$this->t['sg']} sg{$join}
				 WHERE sg.type = 'error' AND sg.created_at >= %s" . Segment::and_sql( $seg ) . ' GROUP BY sg.data ORDER BY n DESC LIMIT %d',
				array_merge( array( $since ), Segment::and_params( $seg ), array( $limit ) )
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$data  = json_decode( (string) $r->data, true );
			$data  = is_array( $data ) ? $data : array();
			$out[] = array(
				'message'  => (string) ( $data['message'] ?? '' ),
				'source'   => (string) ( $data['source'] ?? '' ),
				'line'     => (int) ( $data['line'] ?? 0 ),
				'count'    => (int) $r->n,
				'sessions' => (int) $r->sessions,
			);
		}
		return $out;
	}
}
