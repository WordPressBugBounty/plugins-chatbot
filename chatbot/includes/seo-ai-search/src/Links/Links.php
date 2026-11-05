<?php
/**
 * Links area wiring: menu badge for follow-ups due, and the privacy
 * exporter and eraser for outreach contacts (the only personal data the
 * Links area keeps, entered by the owner).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks.
 */
final class Links {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_seo/menu_items', array( __CLASS__, 'badge' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	/**
	 * Follow-ups due as a badge on Links.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function badge( $items ) {
		$due = Outreach::due_count();
		if ( $due ) {
			foreach ( $items as $index => $item ) {
				if ( isset( $item['id'] ) && 'links' === $item['id'] ) {
					$items[ $index ]['badge'] = $due;
				}
			}
		}
		return $items;
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function exporters( $exporters ) {
		$exporters['qcld-wpbot-seo-outreach'] = array(
			'exporter_friendly_name' => __( 'SEO & AI Search outreach contacts', 'chatbot' ),
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
		$erasers['qcld-wpbot-seo-outreach'] = array(
			'eraser_friendly_name' => __( 'SEO & AI Search outreach contacts', 'chatbot' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export outreach contacts with this email address.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export( $email ) {
		$data = array();
		foreach ( self::rows( $email ) as $row ) {
			$data[] = array(
				'group_id'    => 'qcld-wpbot-seo-outreach',
				'group_label' => __( 'Outreach contacts', 'chatbot' ),
				'item_id'     => 'outreach-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Name', 'chatbot' ),
						'value' => $row['contact_name'],
					),
					array(
						'name'  => __( 'Email', 'chatbot' ),
						'value' => $row['contact_email'],
					),
					array(
						'name'  => __( 'Website', 'chatbot' ),
						'value' => $row['website'],
					),
					array(
						'name'  => __( 'Status', 'chatbot' ),
						'value' => $row['status'],
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Remove the contact's name and email from outreach records.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase( $email ) {
		global $wpdb;
		$removed = 0;
		foreach ( self::rows( $email ) as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$wpdb->update(
				Schema::table( Schema::OUTREACH ),
				array(
					'contact_name'  => '',
					'contact_email' => '',
				),
				array( 'id' => (int) $row['id'] )
			);
			++$removed;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Outreach rows for an email address.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	private static function rows( $email ) {
		global $wpdb;
		$email = sanitize_email( (string) $email );
		if ( '' === $email ) {
			return array();
		}
		$table = Schema::table( Schema::OUTREACH );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, contact_name, contact_email, website, status FROM {$table} WHERE contact_email = %s", $email ), ARRAY_A );
	}
}
