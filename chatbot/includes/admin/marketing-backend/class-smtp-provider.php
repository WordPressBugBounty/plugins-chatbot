<?php
/**
 * class-smtp-provider.php
 *
 * Partly based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: includes/class-smtp-provider.php
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

class SmtpProvider {
	const PASSWORD_MASK = '********';
	const SITE_MAIL_ERROR_LOG_LIMIT = 50;
	const LIMIT_WAIT_NOTE = 'Waiting for SMTP capacity';

	public static function get_connections_for_api() {
		return array(
			array(
				'id' => 'default',
				'provider' => 'wp_mail',
				'title' => 'Default WordPress Mail (wp_mail)',
				'has_password' => false,
			)
		);
	}

	public static function get_detected_smtp_plugins() {
		return array();
	}

	public static function is_site_mail_enabled() {
		return false;
	}

	public static function get_site_mail_error_logs() {
		return array();
	}

	public static function clear_site_mail_error_logs() {
		return true;
	}

	public static function update_site_mail_enabled( $enabled ) {
		return true;
	}

	public static function get_connections() {
		return self::get_connections_for_api();
	}

	public static function save_connection( $data ) {
		throw new \Exception( 'SMTP connections are only available in the Pro version.' );
	}

	public static function delete_connection( $id ) {
		return true;
	}

	public static function test_connection_by_id( $id, $to ) {
		return array( 'success' => false, 'message' => 'Not implemented' );
	}

	public static function move_connection( $id, $direction ) {
		return self::get_connections_for_api();
	}

	public static function send_with_fallback( $to, $subject, $body, $headers ) {
		return wp_mail( $to, $subject, $body, $headers );
	}
}
