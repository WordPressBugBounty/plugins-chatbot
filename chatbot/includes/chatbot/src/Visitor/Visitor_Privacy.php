<?php
/**
 * Personal data export and erase for visitor identity and events (WordPress Tools > Export /
 * Erase Personal Data). A visitor row is found by the user with that email or the email's hash.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Visitor;

defined( 'ABSPATH' ) || exit;

/**
 * Visitor privacy tools.
 */
final class Visitor_Privacy {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function exporters( $exporters ) {
		$exporters['qcld-wpbot-chat-visitor'] = array(
			'exporter_friendly_name' => __( 'WPBot visitor links and events', 'chatbot' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function erasers( $erasers ) {
		$erasers['qcld-wpbot-chat-visitor'] = array(
			'eraser_friendly_name' => __( 'WPBot visitor links and events', 'chatbot' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Visitor keys of an email address.
	 *
	 * @param string $email Email.
	 * @return string[]
	 */
	public static function keys( $email ) {
		global $wpdb;
		$hash = Visitor::email_hash( $email );
		if ( '' === $hash ) {
			return array();
		}
		$user  = get_user_by( 'email', $email );
		$table = Schema::visitors_table();
		$keys  = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT visitor_key FROM {$table} WHERE email_hash = %s OR verified_hash = %s OR ( user_id > 0 AND user_id = %d )", $hash, $hash, $user ? (int) $user->ID : 0 ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return is_array( $keys ) ? $keys : array();
	}

	/**
	 * Export.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress passes the page; one page is enough.
		global $wpdb;
		$items = array();
		foreach ( self::keys( $email ) as $key ) {
			$row     = Visitor::get( $key );
			$table   = Schema::events_table();
			$count   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE visitor_key = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$items[] = array(
				'group_id'    => 'qcld-wpbot-chat-visitor',
				'group_label' => __( 'Chat visitor', 'chatbot' ),
				'item_id'     => 'visitor-' . $key,
				'data'        => array(
					array(
						'name'  => __( 'Visitor id', 'chatbot' ),
						'value' => $key,
					),
					array(
						'name'  => __( 'First seen', 'chatbot' ),
						'value' => $row ? (string) $row['created_at'] : '',
					),
					array(
						'name'  => __( 'Email verified', 'chatbot' ),
						'value' => $row && ! empty( $row['verified_at'] ) ? (string) $row['verified_at'] : __( 'No', 'chatbot' ),
					),
					array(
						'name'  => __( 'Recorded chat and store events', 'chatbot' ),
						'value' => (string) $count,
					),
				),
			);
		}//end foreach
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress passes the page; one page is enough.
		global $wpdb;
		$removed = 0;
		foreach ( self::keys( $email ) as $key ) {
			$removed += (int) $wpdb->delete( Schema::events_table(), array( 'visitor_key' => $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$removed += (int) $wpdb->delete( Schema::visitors_table(), array( 'visitor_key' => $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
