<?php
/**
 * Topic source: what visitors searched for on the site, especially
 * searches that found nothing.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics\Sources;

use QuantumCloud\AiWriter\Settings;
use QuantumCloud\AiWriter\Topics\Search_Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Site search source.
 */
final class Search_Log_Source {

	/**
	 * Connection state.
	 *
	 * @return array
	 */
	public static function status() {
		if ( ! Settings::get( 'search_logger' ) ) {
			return array(
				'status'  => 'not_connected',
				'message' => __( 'The site search log is switched off.', 'chatbot' ),
				'fix'     => array(
					'label' => __( 'Switch it on', 'chatbot' ),
					'tab'   => 'settings',
				),
			);
		}
		return array(
			'status'  => 'connected',
			'message' => array() === Search_Logger::top( 28, 1 ) ? __( 'Counting searches. Ideas appear once visitors search your site.', 'chatbot' ) : '',
		);
	}

	/**
	 * Ideas: the most searched terms of the last four weeks.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function ideas( array $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Source callback signature.
		Search_Logger::prune();
		$recent = Search_Logger::top( 7, 200 );
		$before = array();
		foreach ( Search_Logger::top( 21, 500, 7 ) as $row ) {
			$before[ $row['term'] ] = $row['searches'];
		}
		$recent_by = array();
		foreach ( $recent as $row ) {
			$recent_by[ $row['term'] ] = $row['searches'];
		}
		$ideas = array();
		foreach ( Search_Logger::top( 28, 40 ) as $row ) {
			if ( $row['searches'] < 2 ) {
				continue;
			}
			$week    = isset( $recent_by[ $row['term'] ] ) ? $recent_by[ $row['term'] ] : 0;
			$prior   = isset( $before[ $row['term'] ] ) ? $before[ $row['term'] ] / 3 : 0;
			$trend   = $week >= 3 && $week > 1.5 * $prior ? 'rising' : 'steady';
			$zero    = $row['zero_results'];
			$ideas[] = array(
				'title'      => self::title( $row['term'] ),
				'ref'        => md5( $row['term'] ),
				'reason'     => $zero > 0
					/* translators: 1: searches, 2: searches with no results. */
					? sprintf( __( 'Searched %1$d times on your site in four weeks; %2$d found nothing.', 'chatbot' ), $row['searches'], $zero )
					/* translators: %d: searches. */
					: sprintf( __( 'Searched %d times on your site in four weeks.', 'chatbot' ), $row['searches'] ),
				'trend'      => $trend,
				'rank_score' => $row['searches'] + 2 * $zero + ( 'rising' === $trend ? 5 : 0 ),
				'metrics'    => array(
					'searches'     => $row['searches'],
					'zero_results' => $zero,
				),
			);
		}
		return $ideas;
	}

	/**
	 * Sentence-case a search term.
	 *
	 * @param string $term Term.
	 * @return string
	 */
	private static function title( $term ) {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( mb_substr( $term, 0, 1 ) ) . mb_substr( $term, 1 ) : ucfirst( $term );
	}
}
