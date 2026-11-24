<?php
/**
 * Segments: live audiences defined by rules instead of members ("subscribed,
 * in Trail running, opened nothing in 60 days"). A segment is saved as a list
 * of kind "segment", so it can be used wherever lists are (campaigns,
 * filters); its members are worked out when it is used.
 *
 * Rules are cleaned against a fixed schema and turned into prepared SQL, so
 * rules written by the AI (Master, A8) cannot do anything else.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Audience;

use QuantumCloud\AiMarketing\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Segments.
 */
final class Segments {

	const FIELDS = array(
		'status'    => array( 'is', 'is_not' ),
		'list'      => array( 'in', 'not_in' ),
		'tag'       => array( 'in', 'not_in' ),
		'source'    => array( 'is', 'is_not' ),
		'language'  => array( 'is', 'is_not' ),
		'created'   => array( 'within_days', 'older_than_days' ),
		'opened'    => array( 'within_days', 'not_within_days' ),
		'clicked'   => array( 'within_days', 'not_within_days' ),
		'chatted'   => array( 'within_days', 'not_within_days' ),
		'attribute' => array( 'is', 'is_not' ),
	);

	const MAX_CONDITIONS = 12;

	/**
	 * Clean rules (pure; unit tested). Unknown fields, operators and values are dropped.
	 *
	 * @param mixed $rules Rules: match (all|any), conditions[] (field, op, value, key).
	 * @return array{match:string,conditions:array[]}
	 */
	public static function clean( $rules ) {
		$rules = is_array( $rules ) ? $rules : array();
		$out   = array(
			'match'      => isset( $rules['match'] ) && 'any' === $rules['match'] ? 'any' : 'all',
			'conditions' => array(),
		);
		foreach ( isset( $rules['conditions'] ) && is_array( $rules['conditions'] ) ? $rules['conditions'] : array() as $c ) {
			if ( ! is_array( $c ) || ! isset( $c['field'], $c['op'] ) || ! isset( self::FIELDS[ $c['field'] ] ) || ! in_array( $c['op'], self::FIELDS[ $c['field'] ], true ) ) {
				continue;
			}
			$value = isset( $c['value'] ) && is_scalar( $c['value'] ) ? $c['value'] : '';
			$clean = array(
				'field' => $c['field'],
				'op'    => $c['op'],
			);
			switch ( $c['field'] ) {
				case 'status':
					if ( ! in_array( $value, Rules::STATUSES, true ) ) {
						continue 2;
					}
					$clean['value'] = $value;
					break;
				case 'list':
				case 'tag':
				case 'created':
				case 'opened':
				case 'clicked':
				case 'chatted':
					$clean['value'] = max( 0, min( 3650, (int) $value ) );
					if ( 0 === $clean['value'] ) {
						continue 2;
					}
					break;
				case 'attribute':
					$key = isset( $c['key'] ) ? sanitize_key( (string) $c['key'] ) : '';
					if ( '' === $key ) {
						continue 2;
					}
					$clean['key']   = $key;
					$clean['value'] = sanitize_text_field( (string) $value );
					break;
				default:
					$clean['value'] = sanitize_key( (string) $value );
			}
			$out['conditions'][] = $clean;
			if ( count( $out['conditions'] ) >= self::MAX_CONDITIONS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * A WHERE fragment for contacts aliased "c", with its parameters (pure apart from table names; unit tested).
	 *
	 * @param array $rules Clean rules.
	 * @param int   $now   Unix time.
	 * @return array{0:string,1:array} SQL with placeholders, parameters.
	 */
	public static function where( array $rules, $now ) {
		$members = Schema::table( 'memberships' );
		$events  = Schema::table( 'events' );
		$parts   = array();
		$params  = array();
		foreach ( $rules['conditions'] as $c ) {
			$not = in_array( $c['op'], array( 'is_not', 'not_in', 'not_within_days' ), true ) ? 'NOT ' : '';
			switch ( $c['field'] ) {
				case 'status':
				case 'source':
				case 'language':
					$parts[]  = $not . "c.{$c['field']} = %s";
					$params[] = $c['value'];
					break;
				case 'list':
				case 'tag':
					$parts[]  = $not . "EXISTS ( SELECT 1 FROM `{$members}` m WHERE m.contact_id = c.id AND m.list_id = %d )";
					$params[] = $c['value'];
					break;
				case 'created':
					$parts[]  = 'within_days' === $c['op'] ? 'c.created_at >= %s' : 'c.created_at < %s';
					$params[] = gmdate( 'Y-m-d H:i:s', $now - $c['value'] * DAY_IN_SECONDS );
					break;
				case 'opened':
				case 'clicked':
				case 'chatted':
					$type     = array(
						'opened'  => "'open','click'",
						'clicked' => "'click'",
						'chatted' => "'chat_lead'",
					);
					$parts[]  = $not . "EXISTS ( SELECT 1 FROM `{$events}` e WHERE e.contact_id = c.id AND e.type IN ({$type[ $c['field'] ]}) AND e.created_at >= %s )";
					$params[] = gmdate( 'Y-m-d H:i:s', $now - $c['value'] * DAY_IN_SECONDS );
					break;
				case 'attribute':
					// Attributes are stored as a JSON object of strings; match "key":"value" (works on every MySQL version).
					$pair     = substr( (string) wp_json_encode( array( $c['key'] => (string) $c['value'] ) ), 1, -1 );
					$parts[]  = $not . 'c.attributes LIKE %s';
					$params[] = '%' . addcslashes( $pair, '_%\\' ) . '%';
					break;
			}
		}
		if ( ! $parts ) {
			return array( '1 = 1', array() );
		}
		return array( '( ' . implode( 'all' === $rules['match'] ? ' AND ' : ' OR ', $parts ) . ' )', $params );
	}

	/**
	 * The segment's WHERE fragment with its values filled in (for queries built elsewhere).
	 *
	 * @param array $rules Rules.
	 * @return string
	 */
	public static function sql( array $rules ) {
		global $wpdb;
		list( $sql, $ps ) = self::where( self::clean( $rules ), time() );
		return $ps ? (string) $wpdb->prepare( $sql, $ps ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fragment from the fixed schema; values as placeholders.
	}

	/**
	 * Contacts in a segment (ids), or the count.
	 *
	 * @param array $rules Rules (cleaned here).
	 * @param bool  $count Count only.
	 * @param int   $limit Max ids.
	 * @return int|int[]
	 */
	public static function members( array $rules, $count = false, $limit = 50000 ) {
		global $wpdb;
		$contacts         = Schema::table( 'contacts' );
		list( $sql, $ps ) = self::where( self::clean( $rules ), time() );
		if ( $count ) {
			return (int) $wpdb->get_var( $ps ? $wpdb->prepare( "SELECT COUNT(*) FROM `{$contacts}` c WHERE {$sql}", $ps ) : "SELECT COUNT(*) FROM `{$contacts}` c WHERE {$sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- Own tables; fragments from the fixed schema, values as placeholders.
		}
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT c.id FROM `{$contacts}` c WHERE {$sql} ORDER BY c.id LIMIT %d", array_merge( $ps, array( max( 1, (int) $limit ) ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- Own tables; fragments from the fixed schema.
	}

	/**
	 * Save a segment as a list of kind "segment".
	 *
	 * @param string $name        Name.
	 * @param array  $rules       Rules.
	 * @param string $description Description (the plain-language request, for example).
	 * @param string $origin      owner, agent ...
	 * @return int|WP_Error
	 */
	public static function save( $name, array $rules, $description = '', $origin = 'owner' ) {
		global $wpdb;
		$rules = self::clean( $rules );
		if ( ! $rules['conditions'] ) {
			return new WP_Error( 'qcld_mk_segment', __( 'A segment needs at least one rule.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$id = Lists::create( 'list', $name, $description, $origin );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'lists' ),
			array(
				'kind'  => 'segment',
				'rules' => wp_json_encode( $rules ),
			),
			array( 'id' => (int) $id )
		);
		return (int) $id;
	}
}
