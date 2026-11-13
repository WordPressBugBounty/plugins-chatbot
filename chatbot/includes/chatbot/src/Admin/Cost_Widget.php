<?php
/**
 * WordPress dashboard card (chatbot features CB1a): the chatbot's AI answers and estimated cost
 * this month, from WPBot Core's usage log. Administrators only.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard cost card.
 */
final class Cost_Widget {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the card.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! current_user_can( 'manage_options' ) || ! class_exists( '\QuantumCloud\WPBotCore\AI\Usage' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'qcld_wpbot_chat_cost', esc_html__( 'Chatbot: AI answers this month', 'chatbot' ), array( __CLASS__, 'render' ) );
	}

	/**
	 * This month's totals: answers, tokens and estimated cost.
	 *
	 * @return array { answers, cost }
	 */
	public static function totals() {
		$usage   = '\QuantumCloud\WPBotCore\AI\Usage';
		$summary = $usage::summary( 'chatbot', 1 );
		$answers = 0;
		$cost    = 0.0;
		foreach ( isset( $summary['models'] ) ? (array) $summary['models'] : array() as $row ) {
			$answers += (int) $row['calls'];
			$cost    += (float) $row['cost'];
		}
		return array(
			'answers' => $answers,
			'cost'    => round( $cost, 4 ),
		);
	}

	/**
	 * Card content.
	 *
	 * @return void
	 */
	public static function render() {
		$totals = self::totals();
		echo '<p style="font-size:14px;margin:0 0 8px;">' . esc_html(
			sprintf(
				/* translators: 1: number of AI answers, 2: estimated cost in USD. */
				_n( '%1$s AI answer, about $%2$s.', '%1$s AI answers, about $%2$s.', $totals['answers'], 'chatbot' ),
				number_format_i18n( $totals['answers'] ),
				number_format_i18n( $totals['cost'], 2 )
			)
		) . '</p>';
		echo '<p class="description" style="margin:0;">' . esc_html__( 'Estimates from the providers\' list prices. Details by provider and model: AI Settings > Common AI Settings.', 'chatbot' ) . '</p>';
	}
}
