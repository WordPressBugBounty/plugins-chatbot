<?php
/**
 * Google Analytics 4 client (read-only), through the shared Google connection.
 *
 * Uses the Admin API to list the owner's properties and key events, and
 * the Data API to run reports. Needs the analytics.readonly scope, which a
 * module asks for with the qcld_wpbot_core/google_scopes filter; the owner
 * then reconnects once.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Google Analytics 4 Admin and Data API calls.
 */
final class Analytics {

	/**
	 * Whether Analytics can be used: Google connected with the scope.
	 *
	 * @return bool
	 */
	public static function available() {
		return Google::has_scope( Google::SCOPE_ANALYTICS );
	}

	/**
	 * Status for settings screens.
	 *
	 * @return array{status:string,message:string}
	 */
	public static function status() {
		if ( ! Google::connected() ) {
			return array(
				'status'  => 'not_connected',
				'message' => __( 'Connect Google first.', 'chatbot' ),
			);
		}
		if ( ! self::available() ) {
			return array(
				'status'  => 'needs_reconnect',
				'message' => __( 'Click Connect again to add read-only Google Analytics access.', 'chatbot' ),
			);
		}
		if ( '' === Google::ga_property() ) {
			return array(
				'status'  => 'no_property',
				'message' => __( 'Choose your Google Analytics property.', 'chatbot' ),
			);
		}
		return array(
			'status'  => 'connected',
			'message' => '',
		);
	}

	/**
	 * GA4 properties the owner can read.
	 *
	 * @return array|WP_Error List of id ("properties/123"), name, account.
	 */
	public static function properties() {
		$ready = self::ready( false );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$data = Google::api( '/v1beta/accountSummaries?pageSize=200', null, 'analyticsadmin' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['accountSummaries'] ) ? (array) $data['accountSummaries'] : array() as $account ) {
			foreach ( isset( $account['propertySummaries'] ) ? (array) $account['propertySummaries'] : array() as $property ) {
				if ( empty( $property['property'] ) ) {
					continue;
				}
				$out[] = array(
					'id'      => (string) $property['property'],
					'name'    => isset( $property['displayName'] ) ? (string) $property['displayName'] : '',
					'account' => isset( $account['displayName'] ) ? (string) $account['displayName'] : '',
				);
			}
		}
		return $out;
	}

	/**
	 * Key events (conversions) marked in the property.
	 *
	 * @return string[]|WP_Error Event names.
	 */
	public static function key_events() {
		$ready = self::ready( true );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$data = Google::api( '/v1beta/' . Google::ga_property() . '/keyEvents?pageSize=200', null, 'analyticsadmin' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['keyEvents'] ) ? (array) $data['keyEvents'] : array() as $event ) {
			if ( ! empty( $event['eventName'] ) ) {
				$out[] = sanitize_text_field( (string) $event['eventName'] );
			}
		}
		return $out;
	}

	/**
	 * Run one Data API report on the saved property.
	 *
	 * @param array $body runReport body (dateRanges, dimensions, metrics, limit, offset, dimensionFilter).
	 * @return array|WP_Error rows (list of [dimension values..., metric values...]), row_count, quota (tokens remaining today, or -1).
	 */
	public static function report( array $body ) {
		$ready = self::ready( true );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$quota = Quota::take( 'ga4' );
		if ( is_wp_error( $quota ) ) {
			return $quota;
		}
		$body['returnPropertyQuota'] = true;
		$data                        = Google::api( '/v1beta/' . Google::ga_property() . ':runReport', $body, 'analyticsdata' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$rows = array();
		foreach ( isset( $data['rows'] ) ? (array) $data['rows'] : array() as $row ) {
			$values = array();
			foreach ( isset( $row['dimensionValues'] ) ? (array) $row['dimensionValues'] : array() as $value ) {
				$values[] = isset( $value['value'] ) ? (string) $value['value'] : '';
			}
			foreach ( isset( $row['metricValues'] ) ? (array) $row['metricValues'] : array() as $value ) {
				$values[] = isset( $value['value'] ) ? (float) $value['value'] : 0.0;
			}
			$rows[] = $values;
		}
		$tokens = isset( $data['propertyQuota']['tokensPerDay']['remaining'] ) ? (int) $data['propertyQuota']['tokensPerDay']['remaining'] : -1;
		return array(
			'rows'      => $rows,
			'row_count' => isset( $data['rowCount'] ) ? (int) $data['rowCount'] : count( $rows ),
			'quota'     => $tokens,
		);
	}

	/**
	 * Check the connection, scope and (optionally) the property.
	 *
	 * @param bool $needs_property Whether a property must be chosen.
	 * @return true|WP_Error
	 */
	private static function ready( $needs_property ) {
		$status = self::status();
		if ( 'connected' === $status['status'] || ( ! $needs_property && 'no_property' === $status['status'] ) ) {
			return true;
		}
		return new WP_Error( 'qcld_wpbot_core_ga_' . $status['status'], $status['message'], array( 'status' => 400 ) );
	}
}
