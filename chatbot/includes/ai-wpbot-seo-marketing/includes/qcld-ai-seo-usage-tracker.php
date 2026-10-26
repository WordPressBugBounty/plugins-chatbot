<?php
/**
 * AI usage tracker — records token usage per generation call and
 * provides aggregated summaries with cost estimates.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: includes/class-usage-tracker.php
 * Copyright (C) Noor Alam (wpthemespace.com). Licensed GPLv2 or later.
 *
 * Modified by QuantumCloud for WPBot, 2026: Namespace, prefixes and text
 * domain renamed, integrated with WPBot.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qcld_Wpbot_Ai_Seo_Usage_Tracker {

	private const RETENTION_DAYS = 90;

	/**
	 * Table name (with prefix).
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_ai_seo_ai_usage';
	}

	/**
	 * Record a single generation attempt.
	 *
	 * @param array  $conn    Connection array (id, provider, name).
	 * @param string $model   Model ID used.
	 * @param string $task    Task type ('text', 'image', ...).
	 * @param array  $usage   { prompt_tokens: int, completion_tokens: int }.
	 * @param bool   $success Whether the call succeeded.
	 */
	public static function record( array $conn, string $model, string $task, array $usage, bool $success ): void {
		global $wpdb;

		/**
		 * Filter to disable AI usage tracking entirely.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'qcld_wpbot_ai_seo_track_ai_usage', true ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::table(),
			array(
				'connection_id'     => sanitize_text_field( $conn['id'] ?? '' ),
				'connection_name'   => sanitize_text_field( $conn['name'] ?? '' ),
				'provider'          => sanitize_key( $conn['provider'] ?? '' ),
				'model'             => sanitize_text_field( $model ),
				'task'              => sanitize_key( $task ),
				'prompt_tokens'     => absint( $usage['prompt_tokens'] ?? 0 ),
				'completion_tokens' => absint( $usage['completion_tokens'] ?? 0 ),
				'success'           => $success ? 1 : 0,
				'created_at'        => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Delete usage rows older than the retention window. Hooked to daily cleanup.
	 */
	public static function cleanup(): void {
		global $wpdb;

		$retention = absint( apply_filters( 'qcld_wpbot_ai_seo_ai_usage_retention_days', self::RETENTION_DAYS ) );
		if ( $retention < 1 ) {
			return;
		}

		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $retention * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}
}
