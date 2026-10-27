<?php
/**
 * Segments: a condition on sessions that features (e.g. Pro saved segments)
 * add to the Overview, Heatmaps and Recordings screens. The core itself
 * defines none; it applies whatever the qcin/segment filter returns.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Storage;

defined( 'ABSPATH' ) || exit;

final class Segment {

	/**
	 * SQL condition on the sessions table (alias "s").
	 *
	 * @var string
	 */
	public $where;

	/**
	 * Values for the %s / %d placeholders in $where, in order.
	 *
	 * @var array<int,mixed>
	 */
	public $params;

	/**
	 * Constructor.
	 *
	 * @param string           $where  Condition.
	 * @param array<int,mixed> $params Values.
	 */
	private function __construct( $where, array $params ) {
		$this->where  = $where;
		$this->params = $params;
	}

	/**
	 * Segment for a request, or null for all visits.
	 *
	 * @param array<string,mixed> $params Request parameters (e.g. seg=12).
	 * @return Segment|null
	 */
	public static function from_params( array $params ) {
		/**
		 * Turn request parameters into a condition on sessions (alias "s").
		 * Return null for no segment, or array( 'where' => string[], 'params' => array ):
		 * SQL with %s / %d placeholders and their values in order. Never put
		 * raw request values into 'where'. The condition is used on the
		 * Overview, the Heatmaps and the Recordings list.
		 *
		 * @param array|null          $segment Null.
		 * @param array<string,mixed> $params  Request parameters.
		 */
		return self::from_array( apply_filters( 'qcin/segment', null, $params ) );
	}

	/**
	 * Segment from a condition array( 'where' => string[], 'params' => array ).
	 *
	 * @param mixed $seg Condition.
	 * @return Segment|null
	 */
	public static function from_array( $seg ) {
		if ( ! is_array( $seg ) || empty( $seg['where'] ) || ! isset( $seg['params'] ) || ! is_array( $seg['params'] ) ) {
			return null;
		}
		$where = array_values( array_filter( array_map( 'strval', (array) $seg['where'] ) ) );
		if ( ! $where ) {
			return null;
		}
		return new self( '(' . implode( ' AND ', $where ) . ')', array_values( $seg['params'] ) );
	}

	/**
	 * " AND <condition>" for a query that already joins sessions as "s".
	 *
	 * @param Segment|null $seg Segment.
	 * @return string
	 */
	public static function and_sql( $seg ) {
		return $seg ? ' AND ' . $seg->where : '';
	}

	/**
	 * Values for and_sql().
	 *
	 * @param Segment|null $seg Segment.
	 * @return array<int,mixed>
	 */
	public static function and_params( $seg ) {
		return $seg ? $seg->params : array();
	}
}
