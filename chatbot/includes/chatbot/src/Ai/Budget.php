<?php
/**
 * The chatbot's AI cost and monthly budget (AI Settings > Limits and cost; moved from WPBot Pro's
 * analytics pack in round R2, so WPBot Free has them too).
 *
 * - This month: conversations with AI answers, AI cost, cost per conversation (the messages
 *   table).
 * - Budget: the chatbot's spending limit. With WPBot Core's AI spend (rebuild 6) it is the
 *   "Chatbot" limit in AI Settings > Spending and limits (monthly or weekly), which Ai_Client
 *   checks before every AI call, and WPBot Core sends the alerts. With an older WPBot Core it is
 *   a monthly budget (budgets.chatbot) with emails at 80% and 100% from here.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Budget and cost.
 */
final class Budget {

	/**
	 * Option: alerts sent this month ( 'Y-m' => [ 80, 100 ] ).
	 */
	const ALERTS = 'qcld_wpbot_chat_pro_budget_alerts';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/record_usage', array( __CLASS__, 'after_reply' ), 99 );
		add_action( 'qcld_wpbot_chat/ai_failed', array( __CLASS__, 'failed' ) );
	}

	/**
	 * The chatbot's monthly budget in USD (0 = none).
	 *
	 * @return float
	 */
	public static function budget() {
		if ( self::shared() ) {
			return \QuantumCloud\WPBotCore\Spend\Spend_Settings::limit( 'chatbot' );
		}
		return class_exists( '\QuantumCloud\WPBotCore\Settings' ) ? (float) \QuantumCloud\WPBotCore\Settings::budget( 'chatbot' ) : 0.0;
	}

	/**
	 * Save the budget into WPBot Core's settings.
	 *
	 * @param float $usd Budget (0 = none).
	 * @return void
	 */
	public static function set_budget( $usd ) {
		if ( self::shared() ) {
			\QuantumCloud\WPBotCore\Spend\Spend_Settings::update( array( 'limits' => array( 'chatbot' => max( 0.0, (float) $usd ) ) ) );
			return;
		}
		if ( ! class_exists( '\QuantumCloud\WPBotCore\Settings' ) ) {
			return;
		}
		$budgets            = (array) \QuantumCloud\WPBotCore\Settings::get( 'budgets' );
		$budgets['chatbot'] = max( 0.0, round( (float) $usd, 2 ) );
		\QuantumCloud\WPBotCore\Settings::update( array( 'budgets' => $budgets ) );
	}

	/**
	 * This month's chatbot AI cost (WPBot Core usage log).
	 *
	 * @return float
	 */
	public static function month_cost() {
		return class_exists( '\QuantumCloud\WPBotCore\AI\Usage' ) ? (float) \QuantumCloud\WPBotCore\AI\Usage::month_cost( 'chatbot' ) : 0.0;
	}

	/**
	 * This month: conversations with AI answers, their cost, cost per conversation.
	 *
	 * @return array{conversations: int, cost: float, per_conversation: float}
	 */
	public static function month() {
		global $wpdb;
		$table = $wpdb->prefix . 'qcld_wpbot_chat_messages';
		$start = gmdate( 'Y-m-01 00:00:00' );
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(DISTINCT session_id) AS n, COALESCE(SUM(cost),0) AS c FROM %i WHERE role = %s AND cost > 0 AND created >= %s', $table, 'bot', $start ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The chatbot module's table; read only.
		$n     = isset( $row['n'] ) ? (int) $row['n'] : 0;
		$c     = isset( $row['c'] ) ? (float) $row['c'] : 0.0;
		return array(
			'conversations'    => $n,
			'cost'             => round( $c, 4 ),
			'per_conversation' => $n > 0 ? round( $c / $n, 4 ) : 0.0,
		);
	}

	/**
	 * After each recorded reply: send the budget alerts that are due.
	 *
	 * @param bool $record Whether the module records usage (unchanged).
	 * @return bool
	 */
	public static function after_reply( $record ) {
		self::check();
		return $record;
	}

	/**
	 * The budget stopped an answer: make sure the 100% alert went out.
	 *
	 * @param mixed $error Error.
	 * @return void
	 */
	public static function failed( $error ) {
		if ( is_wp_error( $error ) && 'qcld_wpbot_core_budget_reached' === $error->get_error_code() ) {
			self::check();
		}
	}

	/**
	 * Which alert thresholds are due now (pure).
	 *
	 * @param float $cost   Month cost.
	 * @param float $budget Budget.
	 * @param int[] $sent   Thresholds already sent this month.
	 * @return int[]
	 */
	public static function due( $cost, $budget, array $sent ) {
		if ( $budget <= 0 ) {
			return array();
		}
		$due = array();
		foreach ( array( 80, 100 ) as $threshold ) {
			if ( $cost >= $budget * $threshold / 100 && ! in_array( $threshold, $sent, true ) ) {
				$due[] = $threshold;
			}
		}
		return $due;
	}

	/**
	 * Send due alerts (at most one check every 10 minutes).
	 *
	 * @return void
	 */
	public static function check() {
		if ( self::shared() ) {
			return;
			// WPBot Core's spending alerts cover the chatbot's limit.
		}
		$budget = self::budget();
		if ( $budget <= 0 || false !== get_transient( 'qcld_wpbot_chat_pro_budget_check' ) ) {
			return;
		}
		set_transient( 'qcld_wpbot_chat_pro_budget_check', 1, 10 * MINUTE_IN_SECONDS );
		$month  = gmdate( 'Y-m' );
		$alerts = (array) get_option( self::ALERTS, array() );
		$sent   = isset( $alerts[ $month ] ) ? array_map( 'intval', (array) $alerts[ $month ] ) : array();
		$cost   = self::month_cost();
		$due    = self::due( $cost, $budget, $sent );
		if ( ! $due ) {
			return;
		}
		$top  = max( $due );
		$name = class_exists( '\QuantumCloud\WPBotCore\Host\Brand' ) ? \QuantumCloud\WPBotCore\Host\Brand::name() : 'WPBot';
		wp_mail(
			get_option( 'admin_email' ),
			/* translators: 1: product name, 2: percent of the budget. */
			sprintf( __( '[%1$s] Chatbot AI budget: %2$d%% used', 'chatbot' ), $name, $top ),
			sprintf(
				/* translators: 1: cost this month, 2: budget, 3: settings URL. */
				__( "The chatbot's AI answers cost about $%1\$s this month, of a $%2\$s budget.\n\nAt 100%% the chatbot stops using AI until next month (visitors see your friendly message). Change the budget: %3\$s", 'chatbot' ),
				number_format_i18n( $cost, 2 ),
				number_format_i18n( $budget, 2 ),
				admin_url( 'admin.php?page=wpbot_openAi&section=limits' )
			)
		);
		$alerts = array( $month => array_values( array_unique( array_merge( $sent, $due ) ) ) );
		update_option( self::ALERTS, $alerts, false );
	}

	/**
	 * Whether WPBot Core's AI spend (limits and alerts for every feature) is there.
	 *
	 * @return bool
	 */
	public static function shared() {
		return class_exists( '\QuantumCloud\WPBotCore\Spend\Spend_Settings' );
	}
}
