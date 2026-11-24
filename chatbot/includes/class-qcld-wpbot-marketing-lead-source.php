<?php
/**
 * The chatbot's saved subscribers (User Data, table {prefix}wpbot_subscription)
 * as an AI Marketing lead source. Same file in WPBot Pro and WPBot Free.
 *
 * Read only, except mark_unsubscribed(): the row is kept (owner, 19 Nov
 * 2026: current entries are never deleted) and the address is remembered in
 * the option qcld_wpbot_chat_unsubscribed, which the chatbot checks before
 * saying "you are already subscribed".
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Marketing_Lead_Source', false ) ) {

	/**
	 * Chatbot lead source.
	 */
	class QCLD_WPBot_Marketing_Lead_Source implements \QuantumCloud\AiMarketing\Host\Lead_Source {

		const UNSUBSCRIBED_OPTION = 'qcld_wpbot_chat_unsubscribed';

		/**
		 * Table name.
		 *
		 * @return string
		 */
		private function table() {
			global $wpdb;
			return $wpdb->prefix . 'wpbot_subscription';
		}

		/**
		 * Whether the table exists.
		 *
		 * @return bool
		 */
		private function exists() {
			global $wpdb;
			return $this->table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check.
		}

		/**
		 * Number of saved leads.
		 *
		 * @return int
		 */
		public function count() {
			global $wpdb;
			if ( ! $this->exists() ) {
				return 0;
			}
			return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $this->table() . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Fixed table name.
		}

		/**
		 * One page of leads, oldest first.
		 *
		 * @param int $after_id Last id of the previous page.
		 * @param int $limit    Page size.
		 * @return array[]
		 */
		public function page( $after_id, $limit ) {
			global $wpdb;
			if ( ! $this->exists() ) {
				return array();
			}
			$rows         = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, email, phone, url, date FROM `' . $this->table() . '` WHERE id > %d ORDER BY id ASC LIMIT %d', (int) $after_id, max( 1, min( 500, (int) $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Fixed table name.
			$unsubscribed = get_option( self::UNSUBSCRIBED_OPTION, array() );
			$unsubscribed = is_array( $unsubscribed ) ? array_flip( $unsubscribed ) : array();
			foreach ( $rows as $i => $row ) {
				$rows[ $i ]['id'] = (int) $row['id'];
				foreach ( array( 'name', 'email', 'phone', 'url', 'date' ) as $key ) {
					$rows[ $i ][ $key ] = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
				}
				$rows[ $i ]['unsubscribed'] = isset( $unsubscribed[ strtolower( $rows[ $i ]['email'] ) ] );
			}
			return $rows;
		}

		/**
		 * Remember that this address unsubscribed (the row itself is kept).
		 *
		 * @param string $email Email address.
		 * @return void
		 */
		public function mark_unsubscribed( $email ) {
			$email = strtolower( sanitize_email( (string) $email ) );
			if ( '' === $email ) {
				return;
			}
			$list = get_option( self::UNSUBSCRIBED_OPTION, array() );
			$list = is_array( $list ) ? $list : array();
			if ( ! in_array( $email, $list, true ) ) {
				$list[] = $email;
				update_option( self::UNSUBSCRIBED_OPTION, array_slice( $list, -20000 ), false );
			}
		}
	}
}
