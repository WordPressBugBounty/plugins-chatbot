<?php
/**
 * The GRID's status tiles: live numbers for WPBot's HUD (owner, 21 November 2026).
 *
 * The GRID look (admin theme mode "scifi") shows five tiles on the pages a host chooses
 * (filter qcld_wpbot_core/design_hud_tiles): conversations and AI answers in the last 24 hours,
 * leads, the share of conversations the AI resolved, and the bot's status. assets/js/scifi.js asks
 * for the numbers only while the GRID is on (admin-ajax, action qcld_wpbot_core_hud_stats) and
 * again every minute.
 *
 * WPBot Core adds what it owns: AI readiness, errors in the last 7 days and the setup checks
 * (AI provider, knowledge base, Business Context, notification emails). Hosts and modules add the
 * chat numbers through the filter qcld_wpbot_core/hud_stats. Real values only: a number nobody
 * provides stays null and the tile shows a dash.
 *
 * @package wpbot-core
 */

namespace QuantumCloud\WPBotCore\Admin;

use QuantumCloud\WPBotCore\AI\AI_Readiness;
use QuantumCloud\WPBotCore\Context\Context;
use QuantumCloud\WPBotCore\Diagnostics\Error_Log;
use QuantumCloud\WPBotCore\Knowledge\Kb_Embedder;
use QuantumCloud\WPBotCore\Knowledge\Kb_Store;
use QuantumCloud\WPBotCore\Notifications\Notification_Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Hud_Stats.
 */
final class Hud_Stats {

	/** The admin-ajax action. */
	const ACTION = 'qcld_wpbot_core_hud_stats';

	/** The nonce action. */
	const NONCE = 'qcld_wpbot_core_hud_stats';

	/** Cache (seconds): the tiles refresh every minute. */
	const TTL = 60;

	/** Transient. */
	const CACHE = 'qcld_wpbot_core_hud_stats';

	/**
	 * Register the admin-ajax handler.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax' ) );
	}

	/**
	 * Who may read the numbers.
	 *
	 * @return string Capability.
	 */
	public static function capability() {
		/**
		 * The capability needed to read the GRID's status tiles.
		 *
		 * @param string $cap Default "manage_options".
		 */
		return (string) apply_filters( 'qcld_wpbot_core/hud_stats_capability', 'manage_options' );
	}

	/**
	 * Admin-ajax: the numbers as JSON.
	 *
	 * @return void
	 */
	public static function ajax() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		wp_send_json_success( self::collect() );
	}

	/**
	 * All the numbers, cached for a minute.
	 *
	 * @return array conversations, answers, leads, resolved, status (see blank()).
	 */
	public static function collect() {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$stats = self::blank();

		$stats['status'] = array_merge( $stats['status'], self::status() );
		/**
		 * The GRID's status tiles: hosts and modules fill the chat numbers.
		 *
		 * @param array $stats {
		 *     @type array $conversations {now: int|null, prev: int|null, series: int[]} Sessions with a visitor message, last 24 h (series: per hour).
		 *     @type array $answers       {now: int|null, prev: int|null, series: int[]} AI answers shown, last 24 h (series: per 3 hours).
		 *     @type array $leads         {total: int|null, day: int|null, series: int[]} Leads captured (series: per day, 14 days).
		 *     @type array $resolved      {rate: int|null, sessions: int|null, handoffs: int|null, unanswered: int|null} Last 7 days.
		 *     @type array $status        {ai: string, ready: bool, errors: int, checks: array, sessions: bool|null}
		 * }
		 */
		$stats = (array) apply_filters( 'qcld_wpbot_core/hud_stats', $stats );
		$stats = self::clean( $stats );
		set_transient( self::CACHE, $stats, self::TTL );
		return $stats;
	}

	/**
	 * The shape every tile starts from.
	 *
	 * @return array
	 */
	public static function blank() {
		return array(
			'conversations' => array(
				'now'    => null,
				'prev'   => null,
				'series' => array(),
			),
			'answers'       => array(
				'now'    => null,
				'prev'   => null,
				'series' => array(),
			),
			'leads'         => array(
				'total'  => null,
				'day'    => null,
				'series' => array(),
			),
			'resolved'      => array(
				'rate'       => null,
				'sessions'   => null,
				'handoffs'   => null,
				'unanswered' => null,
			),
			'status'        => array(
				'ai'       => '',
				'ready'    => false,
				'errors'   => 0,
				'checks'   => array(),
				'sessions' => null,
			),
		);
	}

	/**
	 * What WPBot Core knows: AI readiness for the chatbot, errors in the last 7 days, setup checks.
	 *
	 * @return array ai, ready, errors, checks.
	 */
	public static function status() {
		$ai = class_exists( AI_Readiness::class ) ? AI_Readiness::check( 'chatbot' ) : array();
		return array(
			'ai'     => isset( $ai['status'] ) ? (string) $ai['status'] : '',
			'ready'  => ! empty( $ai['ready'] ),
			'errors' => class_exists( Error_Log::class ) && Error_Log::enabled() ? (int) Error_Log::count_recent() : 0,
			'checks' => self::setup( $ai ),
		);
	}

	/**
	 * Setup checks (each: label, done).
	 *
	 * @param array $ai AI readiness (AI_Readiness::check( 'chatbot' )).
	 * @return array[]
	 */
	public static function setup( array $ai = array() ) {
		$kb = 0;
		if ( class_exists( Kb_Store::class ) && class_exists( Kb_Embedder::class ) ) {
			$counts = Kb_Store::stats( Kb_Embedder::model_id() );
			$kb     = isset( $counts['sources'] ) ? (int) $counts['sources'] : 0;
		}
		$emails = class_exists( Notification_Emails::class ) ? Notification_Emails::saved() : null;
		return array(
			array(
				'id'    => 'ai',
				'label' => __( 'AI provider', 'chatbot' ),
				'done'  => ! empty( $ai['ready'] ),
			),
			array(
				'id'    => 'kb',
				'label' => __( 'Knowledge base', 'chatbot' ),
				'done'  => $kb > 0,
			),
			array(
				'id'    => 'context',
				'label' => __( 'Business Context', 'chatbot' ),
				'done'  => class_exists( Context::class ) && Context::saved(),
			),
			array(
				'id'    => 'emails',
				'label' => __( 'Notification emails', 'chatbot' ),
				'done'  => is_array( $emails ) && array() !== $emails,
			),
		);
	}

	/**
	 * Keep the shape and the types, whatever a filter returned.
	 *
	 * @param array $stats Stats.
	 * @return array
	 */
	public static function clean( array $stats ) {
		$out = self::blank();
		foreach ( array( 'conversations', 'answers' ) as $key ) {
			$out[ $key ]['now']    = self::int_or_null( $stats, $key, 'now' );
			$out[ $key ]['prev']   = self::int_or_null( $stats, $key, 'prev' );
			$out[ $key ]['series'] = self::series( $stats, $key );
		}
		$out['leads']['total']  = self::int_or_null( $stats, 'leads', 'total' );
		$out['leads']['day']    = self::int_or_null( $stats, 'leads', 'day' );
		$out['leads']['series'] = self::series( $stats, 'leads' );
		foreach ( array( 'rate', 'sessions', 'handoffs', 'unanswered' ) as $field ) {
			$out['resolved'][ $field ] = self::int_or_null( $stats, 'resolved', $field );
		}
		if ( null !== $out['resolved']['rate'] ) {
			$out['resolved']['rate'] = max( 0, min( 100, $out['resolved']['rate'] ) );
		}
		$status                    = isset( $stats['status'] ) && is_array( $stats['status'] ) ? $stats['status'] : array();
		$out['status']['ai']       = isset( $status['ai'] ) ? sanitize_key( (string) $status['ai'] ) : '';
		$out['status']['ready']    = ! empty( $status['ready'] );
		$out['status']['errors']   = isset( $status['errors'] ) ? max( 0, (int) $status['errors'] ) : 0;
		$out['status']['sessions'] = isset( $status['sessions'] ) && null !== $status['sessions'] ? (bool) $status['sessions'] : null;
		$checks                    = isset( $status['checks'] ) && is_array( $status['checks'] ) ? $status['checks'] : array();
		foreach ( $checks as $check ) {
			if ( is_array( $check ) && isset( $check['label'] ) ) {
				$out['status']['checks'][] = array(
					'id'    => isset( $check['id'] ) ? sanitize_key( (string) $check['id'] ) : '',
					'label' => wp_strip_all_tags( (string) $check['label'] ),
					'done'  => ! empty( $check['done'] ),
				);
			}
		}
		return $out;
	}

	/**
	 * The change from the previous period, in percent (null when it cannot be told).
	 *
	 * @param int|null $now  This period.
	 * @param int|null $prev The period before.
	 * @return int|null
	 */
	public static function delta( $now, $prev ) {
		if ( null === $now || null === $prev || $prev <= 0 ) {
			return null;
		}
		return (int) round( ( $now - $prev ) * 100 / $prev );
	}

	/**
	 * An integer field or null.
	 *
	 * @param array  $stats Stats.
	 * @param string $key   Tile.
	 * @param string $field Field.
	 * @return int|null
	 */
	private static function int_or_null( array $stats, $key, $field ) {
		if ( ! isset( $stats[ $key ] ) || ! is_array( $stats[ $key ] ) || ! isset( $stats[ $key ][ $field ] ) || ! is_numeric( $stats[ $key ][ $field ] ) ) {
			return null;
		}
		return max( 0, (int) $stats[ $key ][ $field ] );
	}

	/**
	 * A tile's series: up to 30 non-negative integers.
	 *
	 * @param array  $stats Stats.
	 * @param string $key   Tile.
	 * @return int[]
	 */
	private static function series( array $stats, $key ) {
		$raw = isset( $stats[ $key ]['series'] ) && is_array( $stats[ $key ]['series'] ) ? array_values( $stats[ $key ]['series'] ) : array();
		return array_map(
			function ( $value ) {
				return max( 0, (int) $value );
			},
			array_slice( $raw, -30 )
		);
	}
}
