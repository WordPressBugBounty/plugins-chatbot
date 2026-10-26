<?php
/**
 * class-email-validator.php
 *
 * Partly based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: includes/class-email-validator.php
 * Copyright (C) Noor Alam (wpthemespace.com). Licensed GPLv2 or later.
 *
 * Modified by QuantumCloud for WPBot, 2026: Substantially rewritten,
 * namespace, prefixes and text domain renamed, integrated with WPBot.
 *
 */
namespace WPBot\EmailMarketing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailValidator {

	public static function validate( $email, $context = 'import', $validation = [] ) {
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', 'Invalid email address.' );
		}
		
		if ( ! empty( $validation['skip_test'] ) && preg_match( '/(test|fake|localhost|example\.com)/i', $email ) ) {
			return new \WP_Error( 'test_email', 'Test email addresses are skipped.' );
		}

		if ( ! empty( $validation['skip_role'] ) && preg_match( '/^(info|admin|support|contact|sales|marketing|webmaster|billing)@/i', $email ) ) {
			return new \WP_Error( 'role_email', 'Role-based email addresses are skipped.' );
		}

		if ( ! empty( $validation['skip_disposable'] ) && self::is_disposable( $email ) ) {
			return new \WP_Error( 'disposable_email', 'Disposable email addresses are skipped.' );
		}

		return true;
	}

	public static function normalize_duplicate_email_key( $email ) {
		$email = strtolower( trim( $email ) );
		// Simple normalization (e.g. gmail aliases)
		if ( str_contains( $email, '@gmail.com' ) ) {
			$parts = explode( '@', $email );
			$name = explode( '+', $parts[0] )[0];
			$name = str_replace( '.', '', $name );
			$email = $name . '@gmail.com';
		}
		return $email;
	}

	public static function is_suspicious_import_identity( $string ) {
		return preg_match( '/(spam|test|fake|dummy|bot|12345|asdf)/i', $string ) === 1;
	}

	public static function has_mailable_domain( $domain ) {
		if ( empty( $domain ) ) {
			return false;
		}
		// Use checkdnsrr to verify MX records if function is available.
		if ( function_exists( 'checkdnsrr' ) ) {
			return checkdnsrr( $domain, 'MX' ) || checkdnsrr( $domain, 'A' );
		}
		return true;
	}

	private static function is_disposable( $email ) {
		$domain = substr( strrchr( $email, "@" ), 1 );
		$disposable = ['mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'yopmail.com'];
		return in_array( $domain, $disposable, true );
	}
}
