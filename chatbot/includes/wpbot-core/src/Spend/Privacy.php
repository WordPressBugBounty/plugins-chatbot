<?php
/**
 * Privacy tools: usage rows keep the id of the administrator who started an AI action (never a
 * visitor's). The WordPress personal data exporter lists those rows; the eraser removes the id
 * and keeps the cost, so the totals stay right.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Exporter and eraser.
 */
final class Privacy {

	const PAGE = 200;

	/**
	 * Register with WordPress.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	/**
	 * Add the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function exporters( $exporters ) {
		$exporters['qcld-wpbot-core-ai-usage'] = array(
			'exporter_friendly_name' => __( 'AI usage', 'chatbot' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Add the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function erasers( $erasers ) {
		$erasers['qcld-wpbot-core-ai-usage'] = array(
			'eraser_friendly_name' => __( 'AI usage', 'chatbot' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export one page of a user's AI usage rows.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page (1-based).
	 * @return array{data: array, done: bool}
	 */
	public static function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		global $wpdb;
		$usage = Schema::table( Schema::USAGE );
		$page  = max( 1, (int) $page );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; values prepared.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at, module, feature, provider, model, cost_estimate FROM {$usage} WHERE user_id = %d ORDER BY id LIMIT %d OFFSET %d", (int) $user->ID, self::PAGE, ( $page - 1 ) * self::PAGE ), ARRAY_A );
		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => 'qcld-wpbot-core-ai-usage',
				'group_label' => __( 'AI usage', 'chatbot' ),
				'item_id'     => 'ai-usage-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Date', 'chatbot' ),
						'value' => $row['created_at'],
					),
					array(
						'name'  => __( 'Feature', 'chatbot' ),
						'value' => $row['module'] . ' / ' . $row['feature'],
					),
					array(
						'name'  => __( 'Model', 'chatbot' ),
						'value' => $row['provider'] . ' ' . $row['model'],
					),
					array(
						'name'  => __( 'Estimated cost (USD)', 'chatbot' ),
						'value' => $row['cost_estimate'],
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => count( $rows ) < self::PAGE,
		);
	}

	/**
	 * Remove a user's id from their AI usage rows (the costs stay).
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array, done: bool}
	 */
	public static function erase( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress passes the page; one update covers every row.
		$user    = get_user_by( 'email', $email );
		$changed = 0;
		if ( $user ) {
			global $wpdb;
			$usage = Schema::table( Schema::USAGE );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; value prepared.
			$changed = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$usage} SET user_id = 0 WHERE user_id = %d", (int) $user->ID ) );
		}
		return array(
			'items_removed'  => $changed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
