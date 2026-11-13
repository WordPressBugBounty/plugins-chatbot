<?php
/**
 * Personal data exporter and eraser for chat messages (Tools → Export/Erase
 * Personal Data). Sessions are found through the legacy wpbot_user rows that
 * carry the visitor's email.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Sessions;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy tools.
 */
final class Privacy {

	/**
	 * Hook.
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
		$exporters['qcld-wpbot-chat'] = array(
			'exporter_friendly_name' => __( 'WPBot chat messages', 'chatbot' ),
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
		$erasers['qcld-wpbot-chat'] = array(
			'eraser_friendly_name' => __( 'WPBot chat messages', 'chatbot' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Session ids of an email address.
	 *
	 * @param string $email Email.
	 * @return string[]
	 */
	public static function sessions( $email ) {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( '' === $email ) {
			return array();
		}
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT session_id FROM %i WHERE email = %s', $wpdb->prefix . 'wpbot_user', $email ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_array( $ids ) ? array_values( array_filter( $ids, 'strlen' ) ) : array();
	}

	/**
	 * Export messages.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress passes the page; one page holds all.
		$items = array();
		foreach ( self::sessions( $email ) as $session_id ) {
			foreach ( Session_Store::messages( $session_id ) as $row ) {
				$items[] = array(
					'group_id'    => 'qcld-wpbot-chat',
					'group_label' => __( 'Chat messages', 'chatbot' ),
					'item_id'     => 'qcld-wpbot-chat-' . $row['id'],
					'data'        => array(
						array(
							'name'  => __( 'Session', 'chatbot' ),
							'value' => $session_id,
						),
						array(
							'name'  => __( 'From', 'chatbot' ),
							'value' => $row['role'],
						),
						array(
							'name'  => __( 'Message', 'chatbot' ),
							'value' => wp_strip_all_tags( $row['text'] ),
						),
						array(
							'name'  => __( 'Date', 'chatbot' ),
							'value' => $row['created'],
						),
					),
				);
			}//end foreach
		}//end foreach
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase messages.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress passes the page; one page holds all.
		$removed = Session_Store::delete( self::sessions( $email ) );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
