<?php
/**
 * Live lead capture from WPForms and the chatbot.
 *
 * Add-ons can register more sources with the `wpbot_crm/capture_sources` and
 * `wpbot_crm/capture_sources_active` filters, then pass submissions to capture() or save().
 *
 * Moved from the old ThirdPartyController (QuantumCloud code). Contacts are now
 * saved through ContactService so validation, opt-in and hooks behave the same
 * as every other source.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Integrations;

use QuantumCloud\WPBot\CRM\Domain\AutomationEngine;
use QuantumCloud\WPBot\CRM\Domain\ContactService;
use QuantumCloud\WPBot\CRM\Domain\GroupRepository;
use QuantumCloud\WPBot\CRM\Support\Ai;

defined( 'ABSPATH' ) || exit;

final class FormCapture {

	const OPTION = 'wpbot_crm_capture';

	/**
	 * Source key => label. The label also names the list leads are added to ("{label} Leads").
	 *
	 * @return array<string,string>
	 */
	public static function sources() {
		$sources = array(
			'wpforms' => __( 'WPForms', 'chatbot' ),
			'chatbot' => __( 'Chatbot Sessions', 'chatbot' ),
		);

		/**
		 * Filters the capture sources (key => label). Add-ons that hook a form plugin add theirs here.
		 *
		 * @param array<string,string> $sources Registered sources.
		 */
		$filtered = apply_filters( 'wpbot_crm/capture_sources', $sources );
		return is_array( $filtered ) ? $filtered : $sources;
	}

	public static function init() {
		add_action( 'wpforms_process_complete', array( __CLASS__, 'on_wpforms' ), 10, 4 );
		// Fired by qcld-wpwbot-search.php after WPBot stores a chat subscription.
		add_action( 'qcwpbot_chatbot_lead_captured', array( __CLASS__, 'on_chatbot' ), 10, 1 );
	}

	/* ── Source status ─────────────────────────────────── */

	/**
	 * Whether each source plugin is active.
	 *
	 * @return array<string,bool>
	 */
	public static function active_sources() {
		$active = array(
			'wpforms' => function_exists( 'wpforms' ),
			'chatbot' => true, // This plugin.
		);

		/**
		 * Filters whether each registered source's plugin is active (key => bool).
		 *
		 * @param array<string,bool> $active Active state per source.
		 */
		$filtered = apply_filters( 'wpbot_crm/capture_sources_active', $active );
		$filtered = is_array( $filtered ) ? $filtered : $active;
		return array_intersect_key( $filtered, self::sources() );
	}

	public static function is_enabled( $source ) {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) && ! empty( $saved[ $source ] );
	}

	/**
	 * @return array<string,bool>
	 */
	public static function capturing() {
		$out = array();
		foreach ( array_keys( self::sources() ) as $source ) {
			$out[ $source ] = self::is_enabled( $source );
		}
		return $out;
	}

	/**
	 * Turn live capture on or off for a source.
	 *
	 * @param string    $source Source key.
	 * @param bool|null $state  Desired state; null flips the stored one.
	 * @return bool New state.
	 */
	public static function set_enabled( $source, $state = null ) {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		if ( null === $state ) {
			$state = empty( $saved[ $source ] );
		}
		$saved[ $source ] = (bool) $state;
		update_option( self::OPTION, $saved, false );
		return (bool) $state;
	}

	/* ── Live submission handlers ──────────────────────── */

	/**
	 * @param array $fields    Processed fields.
	 * @param array $entry     Raw entry.
	 * @param array $form_data Form settings.
	 * @param int   $entry_id  Entry id.
	 */
	public static function on_wpforms( $fields, $entry, $form_data, $entry_id ) {
		if ( ! self::is_enabled( 'wpforms' ) || ! is_array( $fields ) ) {
			return;
		}
		self::capture( self::wpforms_fields( $fields ), 'wpforms' );
	}

	/**
	 * @param array $lead { name, email, phone, url, user_agent }.
	 */
	public static function on_chatbot( $lead ) {
		if ( ! self::is_enabled( 'chatbot' ) || ! is_array( $lead ) ) {
			return;
		}
		$contact          = self::split_name( isset( $lead['name'] ) ? $lead['name'] : '' );
		$contact['email'] = isset( $lead['email'] ) ? $lead['email'] : '';
		$contact['phone'] = isset( $lead['phone'] ) ? $lead['phone'] : '';
		self::save( $contact, 'chatbot' );
	}

	/* ── Extraction ────────────────────────────────────── */

	/**
	 * Find the contact in arbitrary form fields and save it.
	 *
	 * @param array<string,string> $fields Label => value.
	 * @param string               $source Source key.
	 * @return int|null Contact id.
	 */
	public static function capture( array $fields, $source ) {
		if ( empty( $fields ) ) {
			return null;
		}
		$contact = self::guess( $fields );
		if ( ! is_email( $contact['email'] ) ) {
			$contact = self::extract_with_ai( $fields );
		}
		return is_email( $contact['email'] ) ? self::save( $contact, $source ) : null;
	}

	/**
	 * Rule-based extraction: first email-looking value, name and phone by label.
	 *
	 * @param array $fields Label => value.
	 * @return array{email:string,first_name:string,last_name:string,phone:string}
	 */
	public static function guess( array $fields ) {
		$out = array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'phone'      => '',
		);
		foreach ( $fields as $label => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$value = trim( (string) $value );
			$label = strtolower( (string) $label );
			if ( '' === $value ) {
				continue;
			}
			if ( '' === $out['email'] && is_email( $value ) ) {
				$out['email'] = $value;
				continue;
			}
			$is_name = false !== strpos( $label, 'name' );
			if ( $is_name && false !== strpos( $label, 'first' ) ) {
				$out['first_name'] = '' === $out['first_name'] ? $value : $out['first_name'];
			} elseif ( $is_name && ( false !== strpos( $label, 'last' ) || false !== strpos( $label, 'sur' ) ) ) {
				$out['last_name'] = '' === $out['last_name'] ? $value : $out['last_name'];
			} elseif ( $is_name && '' === $out['first_name'] ) {
				$out = array_merge( $out, self::split_name( $value ) );
			} elseif ( '' === $out['phone'] && preg_match( '/phone|tel|mobile|whatsapp/', $label ) ) {
				$out['phone'] = $value;
			}
		}
		return $out;
	}

	/**
	 * Ask the configured AI provider to pick the contact out of the fields.
	 * Used only when the rules found no email (e.g. unusual field labels).
	 *
	 * @param array $fields Label => value.
	 * @return array{email:string,first_name:string,last_name:string,phone:string}
	 */
	public static function extract_with_ai( array $fields ) {
		$empty = array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'phone'      => '',
		);
		if ( ! class_exists( Ai::class ) || ! Ai::is_configured() ) {
			return $empty;
		}
		$compact = array();
		foreach ( $fields as $label => $value ) {
			$compact[ (string) $label ] = function_exists( 'mb_substr' ) ? mb_substr( (string) $value, 0, 300 ) : substr( (string) $value, 0, 300 );
		}
		$result = Ai::json(
			'You read website form submissions and return the person who submitted them as JSON with the keys email, first_name, last_name and phone. Use empty strings for anything missing. Never invent values.',
			wp_json_encode( $compact ),
			array(
				'max_tokens'  => 200,
				'temperature' => 0,
				'timeout'     => 8,
			)
		);
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			return $empty;
		}
		foreach ( $empty as $key => $unused ) {
			$empty[ $key ] = isset( $result[ $key ] ) && is_scalar( $result[ $key ] ) ? trim( (string) $result[ $key ] ) : '';
		}
		return $empty;
	}

	/**
	 * Save a contact into the "{Label} Leads" list for its source.
	 *
	 * @param array  $contact email, first_name, last_name, phone.
	 * @param string $source  Source key.
	 * @return int|null Contact id, or null when skipped (invalid email or opted out).
	 */
	public static function save( array $contact, $source ) {
		$labels = self::sources();
		$label  = isset( $labels[ $source ] ) ? $labels[ $source ] : $source;
		/* translators: %s: form plugin name, e.g. "WPForms". */
		$list_id = ( new GroupRepository() )->ensure( 'list', sprintf( __( '%s Leads', 'chatbot' ), $label ) );

		$service  = new ContactService();
		$existing = $service->find_by_email( isset( $contact['email'] ) ? (string) $contact['email'] : '' );
		if ( $existing && 'unsubscribed' === $existing['status'] ) {
			return null; // Respect the opt-out: don't add them to a new list.
		}

		$id = $service->upsert( $contact, $source, $list_id ? array( $list_id ) : array() );
		if ( is_wp_error( $id ) ) {
			return null;
		}
		if ( class_exists( AutomationEngine::class ) ) {
			AutomationEngine::trigger( 'form_submitted', (int) $id, array( 'source' => $source ) );
		}
		return (int) $id;
	}

	/* ── Helpers ───────────────────────────────────────── */

	/**
	 * @param array $fields WPForms field arrays (name, value).
	 * @return array<string,string>
	 */
	private static function wpforms_fields( array $fields ) {
		$out = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$label         = ! empty( $field['name'] ) ? $field['name'] : ( isset( $field['id'] ) ? 'field_' . $field['id'] : 'field' );
			$out[ $label ] = self::flatten( isset( $field['value'] ) ? $field['value'] : '' );
		}
		return $out;
	}

	/**
	 * @param string $name Full name.
	 * @return array{first_name:string,last_name:string}
	 */
	private static function split_name( $name ) {
		$parts = preg_split( '/\s+/', trim( (string) $name ), 2 );
		return array(
			'first_name' => isset( $parts[0] ) ? $parts[0] : '',
			'last_name'  => isset( $parts[1] ) ? $parts[1] : '',
		);
	}

	/**
	 * @param mixed $value Field value.
	 * @return string
	 */
	public static function flatten( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) );
		}
		return sanitize_text_field( (string) $value );
	}

}
