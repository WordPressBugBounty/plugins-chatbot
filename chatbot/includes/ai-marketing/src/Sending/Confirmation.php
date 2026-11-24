<?php
/**
 * Double opt-in confirmation email, sent right away when a contact becomes
 * "pending". Not counted as marketing; no tracking.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Sending;

defined( 'ABSPATH' ) || exit;

/**
 * Confirmation email.
 */
final class Confirmation {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_marketing/send_confirmation', array( __CLASS__, 'send' ) );
	}

	/**
	 * Send the confirmation email.
	 *
	 * @param array $contact Contact.
	 * @return void
	 */
	public static function send( $contact ) {
		if ( ! is_array( $contact ) || empty( $contact['email'] ) || empty( $contact['token'] ) ) {
			return;
		}
		$site  = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$link  = Links::confirm_url( (string) $contact['token'] );
		$email = array(
			/* translators: %s: site name. */
			'subject'      => sprintf( __( 'Please confirm your subscription to %s', 'chatbot' ), $site ),
			'preview_text' => __( 'One click and you are in.', 'chatbot' ),
			'content_html' => '<p>' . esc_html__( 'Hi {first_name|there},', 'chatbot' ) . '</p><p>' . esc_html__( 'Please confirm that you want to receive our emails.', 'chatbot' ) . '</p><p><a href="' . esc_url( $link ) . '" style="background:#5b4e96;color:#fff;padding:12px 22px;border-radius:6px;text-decoration:none;display:inline-block;">' . esc_html__( 'Yes, subscribe me', 'chatbot' ) . '</a></p><p style="color:#6b7280;font-size:13px;">' . esc_html__( 'If you did not ask for this, ignore this email and you will not hear from us.', 'chatbot' ) . '</p>',
		);
		Mailer::send( Mailer::build( $email, $contact, 0 ), array( 'kind' => 'confirmation' ) );
	}
}
