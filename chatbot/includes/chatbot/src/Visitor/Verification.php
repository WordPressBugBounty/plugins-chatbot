<?php
/**
 * One-time email codes (C-1): a visitor proves they own an email before the chat shows order
 * details, downloads, licences or opens a refund. A 6-digit code is emailed, kept only as a hash
 * for 10 minutes, with 5 tries; sending is limited to 5 codes an hour per visitor.
 *
 * The answer never says whether an email belongs to a customer, so emails can't be probed.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Visitor;

use QuantumCloud\Chatbot\RateLimit\Limiter;

defined( 'ABSPATH' ) || exit;

/**
 * Email verification codes.
 */
final class Verification {

	/**
	 * Seconds a code is valid.
	 */
	const TTL = 600;

	/**
	 * Tries per code.
	 */
	const TRIES = 5;

	/**
	 * Sends a code to an email for this visitor.
	 *
	 * @param string $email   Email.
	 * @param string $purpose What it is for, shown in the email ("check your order").
	 * @return array{ok: bool, message: string}
	 */
	public static function send( $email, $purpose = '' ) {
		$email = sanitize_email( (string) $email );
		$key   = Visitor::key( true );
		if ( ! is_email( $email ) || '' === $key ) {
			return self::answer( false, __( 'Please enter a valid email address.', 'chatbot' ) );
		}
		if ( ! Limiter::hit( 'verify', 'v:' . $key ) ) {
			return self::answer( false, __( 'Too many codes were requested. Please try again later.', 'chatbot' ) );
		}
		$code = (string) random_int( 100000, 999999 );
		set_transient(
			self::transient( $key, $email ),
			array(
				'hash'  => wp_hash_password( $code ),
				'tries' => 0,
			),
			self::TTL
		);
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject = sprintf(
			/* translators: %s: site name. */
			__( 'Your verification code for %s', 'chatbot' ),
			$site
		);
		$body = sprintf(
			/* translators: 1: code, 2: what it is for. */
			__( "Your code is %1\$s\n\nType it in the chat to %2\$s. It is valid for 10 minutes.\n\nIf you did not ask for it, you can ignore this email.", 'chatbot' ),
			$code,
			'' !== $purpose ? $purpose : __( 'continue', 'chatbot' )
		);
		/**
		 * Whether to send the verification email (tests and other channels turn it off).
		 *
		 * @param bool   $send  Send.
		 * @param string $email Email.
		 * @param string $code  Code.
		 */
		if ( apply_filters( 'qcld_wpbot_chat/verification_send', true, $email, $code ) ) {
			wp_mail( $email, $subject, $body );
		}
		// Same answer whether or not the email belongs to a customer.
		return self::answer( true, __( 'If this email is ours, a code is on its way. Please type it here.', 'chatbot' ) );
	}

	/**
	 * Checks a code; on success the visitor is marked verified for the email.
	 *
	 * @param string $email Email.
	 * @param string $code  Code.
	 * @return array{ok: bool, message: string}
	 */
	public static function check( $email, $code ) {
		$email = sanitize_email( (string) $email );
		$key   = Visitor::key();
		$code  = preg_replace( '/\D/', '', (string) $code );
		if ( '' === $key || ! is_email( $email ) || 6 !== strlen( $code ) ) {
			return self::answer( false, __( 'That code is not right. Please check it and try again.', 'chatbot' ) );
		}
		$name  = self::transient( $key, $email );
		$saved = get_transient( $name );
		if ( ! is_array( $saved ) || (int) $saved['tries'] >= self::TRIES ) {
			delete_transient( $name );
			return self::answer( false, __( 'This code has expired. Please ask for a new one.', 'chatbot' ) );
		}
		if ( ! wp_check_password( $code, (string) $saved['hash'] ) ) {
			++$saved['tries'];
			set_transient( $name, $saved, self::TTL );
			return self::answer( false, __( 'That code is not right. Please check it and try again.', 'chatbot' ) );
		}
		delete_transient( $name );
		Visitor::mark_verified( $key, $email );
		return self::answer( true, __( 'Thanks, you are verified.', 'chatbot' ) );
	}

	/**
	 * Transient name for a visitor and email.
	 *
	 * @param string $key   Visitor key.
	 * @param string $email Email.
	 * @return string
	 */
	private static function transient( $key, $email ) {
		return 'qcld_wpbot_vc_' . substr( hash( 'sha256', $key . '|' . strtolower( $email ) ), 0, 32 );
	}

	/**
	 * Answer.
	 *
	 * @param bool   $ok      Ok.
	 * @param string $message Message.
	 * @return array{ok: bool, message: string}
	 */
	private static function answer( $ok, $message ) {
		return array(
			'ok'      => (bool) $ok,
			'message' => (string) $message,
		);
	}
}
