<?php
/**
 * Learning from tests (round 3, P4): wins, losses and ties per technique,
 * kind of copy and reader stage, so writing tools can suggest what has
 * worked on this site first. Only clear results count as wins or losses;
 * a test that ended without a clear difference counts as a tie for every
 * technique in it, and one without enough data does not count at all.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Experiments;

defined( 'ABSPATH' ) || exit;

use QuantumCloud\WPBotCore\Storage\Schema;

/**
 * Per-technique results.
 */
final class Learning {

	/**
	 * Record a finished test.
	 *
	 * @param array $view Experiment view (with assessment). The kept variant
	 *                    does not matter: only the measured result counts.
	 * @return void
	 */
	public static function record_test( array $view ) {
		$state = $view['assessment']['state'];
		if ( ! in_array( $state, array( 'clear', 'no_difference' ), true ) ) {
			return;
		}
		$winner = 'clear' === $state ? (int) $view['assessment']['winner'] : 0;
		$seen   = array();
		foreach ( $view['variants'] as $variant ) {
			$technique = '' !== $variant['technique'] ? $variant['technique'] : ( $variant['is_control'] ? 'original' : '' );
			if ( '' === $technique || isset( $seen[ $technique ] ) ) {
				continue;
			}
			$seen[ $technique ] = true;
			if ( 0 === $winner ) {
				$column = 'ties';
			} else {
				$column = (int) $variant['id'] === $winner ? 'wins' : 'losses';
			}
			self::bump( $view['module'], $view['kind'], $technique, $view['stage'], $column );
		}
	}

	/**
	 * Results matching filters, best first.
	 *
	 * @param array $args module, kind, stage, technique.
	 * @return array[] module, kind, technique, stage, wins, losses, ties, tests.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table = Schema::table( Schema::RESULTS );
		$where = array( '1=1' );
		$vals  = array();
		foreach ( array( 'module', 'kind', 'stage', 'technique' ) as $key ) {
			if ( isset( $args[ $key ] ) && '' !== (string) $args[ $key ] ) {
				$where[] = "{$key} = %s";
				$vals[]  = (string) $args[ $key ];
			}
		}
		$sql  = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY (CAST(wins AS SIGNED) - CAST(losses AS SIGNED)) DESC, wins DESC LIMIT 200';
		$rows = $vals ? $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; placeholders built above.
		return array_map(
			static function ( $row ) {
				return array(
					'module'    => (string) $row['module'],
					'kind'      => (string) $row['kind'],
					'technique' => (string) $row['technique'],
					'stage'     => (string) $row['stage'],
					'wins'      => (int) $row['wins'],
					'losses'    => (int) $row['losses'],
					'ties'      => (int) $row['ties'],
					'tests'     => (int) $row['wins'] + (int) $row['losses'] + (int) $row['ties'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Techniques that have won more than they lost, best first (stage
	 * matches first, then any stage).
	 *
	 * @param string $module Module.
	 * @param string $kind   Kind of copy.
	 * @param string $stage  Reader stage ('' for any).
	 * @return string[]
	 */
	public static function winners( $module, $kind, $stage = '' ) {
		$scores = array();
		foreach ( self::query(
			array(
				'module' => $module,
				'kind'   => $kind,
			)
		) as $row ) {
			if ( 'original' === $row['technique'] ) {
				continue;
			}
			$weight                      = '' !== $stage && $row['stage'] === $stage ? 2 : 1;
			$scores[ $row['technique'] ] = ( isset( $scores[ $row['technique'] ] ) ? $scores[ $row['technique'] ] : 0 ) + $weight * ( $row['wins'] - $row['losses'] );
		}
		$scores = array_filter(
			$scores,
			static function ( $score ) {
				return $score > 0;
			}
		);
		arsort( $scores );
		return array_keys( $scores );
	}

	/**
	 * A plain sentence per technique, for screens ("Loss angle beat the
	 * alternative in 3 of 4 tests on product copy").
	 *
	 * @param array $row Row from query().
	 * @return string
	 */
	public static function sentence( array $row ) {
		/* translators: 1: wins, 2: tests. */
		return sprintf( _n( 'won %1$d of %2$d test', 'won %1$d of %2$d tests', $row['tests'], 'chatbot' ), $row['wins'], $row['tests'] );
	}

	/**
	 * Add one to a column.
	 *
	 * @param string $module    Module.
	 * @param string $kind      Kind.
	 * @param string $technique Technique.
	 * @param string $stage     Stage.
	 * @param string $column    wins|losses|ties.
	 * @return void
	 */
	private static function bump( $module, $kind, $technique, $stage, $column ) {
		global $wpdb;
		if ( ! in_array( $column, array( 'wins', 'losses', 'ties' ), true ) ) {
			return;
		}
		$table = Schema::table( Schema::RESULTS );
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; the column comes from the allow-list above.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (module, kind, technique, stage, {$column}, updated_at) VALUES (%s, %s, %s, %s, 1, %s) ON DUPLICATE KEY UPDATE {$column} = {$column} + 1, updated_at = VALUES(updated_at)", sanitize_key( $module ), sanitize_key( $kind ), sanitize_key( $technique ), sanitize_key( $stage ), $now ) );
	}
}
