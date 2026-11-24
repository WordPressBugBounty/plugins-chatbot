<?php
/**
 * WordPress privacy tools (Tools › Export / Erase Personal Data): AI Marketing
 * contacts, their consent records and their timeline.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Privacy;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Audience\Lists;
use QuantumCloud\AiMarketing\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy exporter and eraser.
 */
final class Privacy {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hook() {
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
		$exporters['qcld-wpbot-marketing'] = array(
			'exporter_friendly_name' => __( 'WPBot AI Marketing', 'chatbot' ),
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
		$erasers['qcld-wpbot-marketing'] = array(
			'eraser_friendly_name' => __( 'WPBot AI Marketing', 'chatbot' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export a person's data.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export( $email ) {
		global $wpdb;
		$contact = Contacts::by_email( (string) $email );
		if ( ! $contact ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$data = array();
		foreach ( array( 'email', 'first_name', 'last_name', 'phone', 'company', 'website', 'status', 'source', 'created_at', 'confirmed_at', 'unsubscribed_at' ) as $key ) {
			$data[] = array(
				'name'  => $key,
				'value' => (string) $contact[ $key ],
			);
		}
		$groups   = wp_list_pluck( Lists::of_contact( (int) $contact['id'] ), 'name' );
		$data[]   = array(
			'name'  => __( 'Lists and tags', 'chatbot' ),
			'value' => implode( ', ', $groups ),
		);
		$items    = array(
			array(
				'group_id'    => 'qcld-wpbot-marketing',
				'group_label' => __( 'Email marketing', 'chatbot' ),
				'item_id'     => 'contact-' . (int) $contact['id'],
				'data'        => $data,
			),
		);
		$consents = Schema::table( 'consents' );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$consents}` WHERE contact_id = %d", (int) $contact['id'] ), ARRAY_A ) as $consent ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			$items[] = array(
				'group_id'    => 'qcld-wpbot-marketing-consent',
				'group_label' => __( 'Email marketing consent', 'chatbot' ),
				'item_id'     => 'consent-' . (int) $consent['id'],
				'data'        => array(
					array(
						'name'  => __( 'Agreed to', 'chatbot' ),
						'value' => (string) $consent['kind'],
					),
					array(
						'name'  => __( 'Where', 'chatbot' ),
						'value' => (string) $consent['source'],
					),
					array(
						'name'  => __( 'Wording', 'chatbot' ),
						'value' => (string) $consent['wording'],
					),
					array(
						'name'  => __( 'When', 'chatbot' ),
						'value' => (string) $consent['created_at'],
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase a person's data.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase( $email ) {
		$contact = Contacts::by_email( (string) $email );
		$removed = $contact && true === Contacts::delete( (int) $contact['id'] );
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
