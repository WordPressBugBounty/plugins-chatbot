<?php
/**
 * WPForms capture (Essentials): every WPForms submission with an email
 * address becomes a contact in the "WPForms" list.
 *
 * Consent: a submission counts as a subscription only when the form had a
 * ticked checkbox whose label talks about emails, newsletters, marketing or
 * subscribing. Otherwise the person is kept as a lead (in the list, never
 * emailed until they opt in). On by default while WPForms is active.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Capture;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Audience\Lists;
use QuantumCloud\AiMarketing\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * WPForms capture.
 */
final class WPForms {

	const SYSTEM_KEY = 'wpforms';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'wpforms_process_complete', array( __CLASS__, 'on_submit' ), 10, 4 );
	}

	/**
	 * Whether WPForms is active.
	 *
	 * @return bool
	 */
	public static function active() {
		return function_exists( 'wpforms' );
	}

	/**
	 * A WPForms submission.
	 *
	 * @param array $fields    Processed fields (each: name, value, type).
	 * @param array $entry     Raw entry.
	 * @param array $form_data Form settings.
	 * @param int   $entry_id  Entry id.
	 * @return void
	 */
	public static function on_submit( $fields, $entry, $form_data, $entry_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WPForms hook signature.
		if ( ! Settings::get( 'capture_wpforms' ) || ! is_array( $fields ) ) {
			return;
		}
		$found = self::extract( $fields );
		if ( '' === $found['email'] ) {
			return;
		}
		$title   = isset( $form_data['settings']['form_title'] ) ? (string) $form_data['settings']['form_title'] : '';
		$list_id = Lists::ensure_system( self::SYSTEM_KEY, __( 'WPForms', 'chatbot' ) );
		/* translators: %s: form title. */
		$reason = sprintf( __( 'Filled in the form "%s"', 'chatbot' ), $title );
		$result = Contacts::capture(
			array(
				'email'        => $found['email'],
				'first_name'   => $found['first_name'],
				'last_name'    => $found['last_name'],
				'phone'        => $found['phone'],
				'consent_text' => $found['consent_text'],
			),
			$found['consent'] ? 'marketing' : 'contact',
			'wpforms',
			array( $list_id => $reason ),
			$found['consent']
		);
		if ( ! is_wp_error( $result ) ) {
			/** This action is documented in src/Capture/Signup_Form.php */
			do_action( 'qcld_wpbot_marketing/form_submitted', (int) $result['id'], array( $list_id ), '' );
		}
	}

	/**
	 * Find the person and their consent in WPForms fields (pure; unit tested).
	 *
	 * @param array $fields Fields: each name, value, type.
	 * @return array{email:string,first_name:string,last_name:string,phone:string,consent:bool,consent_text:string}
	 */
	public static function extract( array $fields ) {
		$out = array(
			'email'        => '',
			'first_name'   => '',
			'last_name'    => '',
			'phone'        => '',
			'consent'      => false,
			'consent_text' => '',
		);
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
			$label = strtolower( isset( $field['name'] ) ? (string) $field['name'] : '' );
			$value = isset( $field['value'] ) ? $field['value'] : '';
			$value = is_array( $value ) ? implode( ', ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) ) : trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			if ( '' === $out['email'] && ( 'email' === $type || is_email( $value ) ) && is_email( $value ) ) {
				$out['email'] = strtolower( $value );
			} elseif ( 'name' === $type || false !== strpos( $label, 'name' ) ) {
				if ( isset( $field['first'] ) && '' !== (string) $field['first'] ) {
					$out['first_name'] = (string) $field['first'];
					$out['last_name']  = isset( $field['last'] ) ? (string) $field['last'] : '';
				} elseif ( false !== strpos( $label, 'last' ) ) {
					$out['last_name'] = $value;
				} elseif ( '' === $out['first_name'] ) {
					$parts             = preg_split( '/\s+/', $value, 2 );
					$out['first_name'] = (string) $parts[0];
					$out['last_name']  = isset( $parts[1] ) ? (string) $parts[1] : $out['last_name'];
				}
			} elseif ( 'phone' === $type || preg_match( '/phone|tel|mobile|whatsapp/', $label ) ) {
				$out['phone'] = $value;
			} elseif ( in_array( $type, array( 'checkbox', 'gdpr-checkbox' ), true ) && preg_match( '/newsletter|subscribe|marketing|e-?mails?|updates|offers|news/i', $label . ' ' . $value ) ) {
				$out['consent']      = true;
				$out['consent_text'] = isset( $field['name'] ) ? (string) $field['name'] : $value;
			}
		}
		return $out;
	}
}
