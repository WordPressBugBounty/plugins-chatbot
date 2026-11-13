<?php
/**
 * Topic source and decay source: Google Search Console through the shared
 * Google connection in WPBot Core. Ideas are rising queries and queries
 * with impressions but no page that ranks well; decay rows compare clicks
 * per page over the last 28 days with the 28 days before.
 *
 * Search Console data lags about two days, so periods end three days ago.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics\Sources;

use QuantumCloud\WPBotCore\Connections\Google;
use QuantumCloud\WPBotCore\Connections\Search_Console;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Search Console source.
 */
final class Search_Console_Source {

	/**
	 * Whether the core version has the Google connection.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( Google::class ) && class_exists( Search_Console::class );
	}

	/**
	 * Connection state.
	 *
	 * @return array
	 */
	public static function status() {
		if ( ! self::available() ) {
			return array(
				'status'  => 'not_available',
				'message' => sprintf( /* translators: %s: product name, for example WPBot. */ __( 'Update %s to use Search Console.', 'chatbot' ), \QuantumCloud\AiWriter\Module::product() ),
			);
		}
		$state = Google::status();
		$fix   = array(
			'label' => 'failing' === $state['status'] ? __( 'Fix the connection', 'chatbot' ) : __( 'Connect Search Console', 'chatbot' ),
			'tab'   => 'settings',
		);
		return array(
			'status'  => $state['status'],
			'message' => 'not_connected' === $state['status'] ? __( 'Connect Google Search Console to see what people search for before they find you, and which searches have no page on your site.', 'chatbot' ) : $state['message'],
			'fix'     => 'connected' === $state['status'] ? null : $fix,
		);
	}

	/**
	 * Ideas from queries.
	 *
	 * @param array $context Context.
	 * @return array[]|WP_Error
	 */
	public static function ideas( array $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Source callback signature.
		if ( ! self::available() || ! Google::connected() ) {
			return array();
		}
		list( $recent, $before ) = self::periods( array( 'query' ), 1000 );
		if ( is_wp_error( $recent ) ) {
			return $recent;
		}
		$prior = array();
		foreach ( is_wp_error( $before ) ? array() : $before as $row ) {
			$prior[ $row['keys'][0] ] = $row;
		}
		$ideas = array();
		foreach ( $recent as $row ) {
			$query = isset( $row['keys'][0] ) ? (string) $row['keys'][0] : '';
			if ( '' === $query || $row['impressions'] < 10 ) {
				continue;
			}
			$old    = isset( $prior[ $query ] ) ? $prior[ $query ]['impressions'] : 0;
			$rising = $row['impressions'] >= 20 && $row['impressions'] >= 1.5 * max( 1, $old );
			$gap    = $row['position'] > 20 || ( $row['impressions'] >= 50 && $row['ctr'] < 0.01 );
			if ( ! $rising && ! $gap ) {
				continue;
			}
			$reasons = array();
			if ( $rising ) {
				/* translators: 1: impressions now, 2: impressions before. */
				$reasons[] = sprintf( __( 'Rising in Google: %1$d impressions in four weeks (%2$d before).', 'chatbot' ), (int) $row['impressions'], (int) $old );
			}
			if ( $gap ) {
				/* translators: %s: average position. */
				$reasons[] = sprintf( __( 'No page of yours ranks well for it (average position %s).', 'chatbot' ), number_format_i18n( $row['position'], 1 ) );
			}
			$ideas[] = array(
				'title'      => function_exists( 'mb_strtoupper' ) ? mb_strtoupper( mb_substr( $query, 0, 1 ) ) . mb_substr( $query, 1 ) : ucfirst( $query ),
				'ref'        => md5( $query ),
				'reason'     => implode( ' ', $reasons ),
				'trend'      => $rising ? 'rising' : 'steady',
				'rank_score' => $row['impressions'] / 10 + ( $rising ? 10 : 0 ) + ( $gap ? 5 : 0 ),
				'metrics'    => array(
					'impressions' => (int) $row['impressions'],
					'clicks'      => (int) $row['clicks'],
					'position'    => round( $row['position'], 1 ),
				),
			);
		}
		usort(
			$ideas,
			static function ( $a, $b ) {
				return $b['rank_score'] <=> $a['rank_score'];
			}
		);
		return array_slice( $ideas, 0, 40 );
	}

	/**
	 * Decay rows: clicks per page, last 28 days and the 28 before.
	 *
	 * @return array[]
	 */
	public static function decay_rows() {
		if ( ! self::available() || ! Google::connected() ) {
			return array();
		}
		list( $recent, $before ) = self::periods( array( 'page' ), 2000 );
		if ( is_wp_error( $recent ) || is_wp_error( $before ) ) {
			return array();
		}
		$by_post = array();
		foreach ( array(
			'after'  => $recent,
			'before' => $before,
		) as $period => $rows ) {
			foreach ( $rows as $row ) {
				$post_id = url_to_postid( (string) $row['keys'][0] );
				if ( ! $post_id ) {
					continue;
				}
				if ( ! isset( $by_post[ $post_id ] ) ) {
					$by_post[ $post_id ] = array(
						'post_id'            => $post_id,
						'source'             => 'Search Console',
						'metric'             => 'clicks',
						'before'             => 0,
						'after'              => 0,
						'impressions_before' => 0,
						'impressions_after'  => 0,
						'position_before'    => 0,
						'position_after'     => 0,
					);
				}
				$by_post[ $post_id ][ $period ]                  += $row['clicks'];
				$by_post[ $post_id ][ 'impressions_' . $period ] += $row['impressions'];
				$by_post[ $post_id ][ 'position_' . $period ]     = $row['position'];
			}
		}
		return array_values( $by_post );
	}

	/**
	 * Register as a decay source.
	 *
	 * @param array $sources id => callable.
	 * @return array
	 */
	public static function decay_source( $sources ) {
		if ( self::available() && Google::connected() ) {
			$sources['search_console'] = array( __CLASS__, 'decay_rows' );
		}
		return $sources;
	}

	/**
	 * The two 28-day periods.
	 *
	 * @param string[] $dimensions Dimensions.
	 * @param int      $limit      Rows.
	 * @return array{0: array|WP_Error, 1: array|WP_Error}
	 */
	private static function periods( array $dimensions, $limit ) {
		$end = time() - 3 * DAY_IN_SECONDS;
		return array(
			Search_Console::query( gmdate( 'Y-m-d', $end - 27 * DAY_IN_SECONDS ), gmdate( 'Y-m-d', $end ), $dimensions, $limit ),
			Search_Console::query( gmdate( 'Y-m-d', $end - 55 * DAY_IN_SECONDS ), gmdate( 'Y-m-d', $end - 28 * DAY_IN_SECONDS ), $dimensions, $limit ),
		);
	}
}
