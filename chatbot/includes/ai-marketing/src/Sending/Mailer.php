<?php
/**
 * Builds and sends one marketing email: merge tags, tracked links, open
 * pixel, a footer with the unsubscribe link and the company address, and the
 * List-Unsubscribe headers (RFC 8058 one-click).
 *
 * Sending goes through wp_mail() unless a provider (the Pro module: SMTP,
 * Amazon SES, Brevo …) handles it through the qcld_wpbot_marketing/send
 * filter. This module never changes how the rest of the site sends mail.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Sending;

use QuantumCloud\AiMarketing\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Mailer.
 */
final class Mailer {

	/**
	 * The provider's id of the last email sent (for bounce and complaint reports).
	 *
	 * @var string
	 */
	private static $last_id = '';

	/**
	 * The provider's id of the last email sent ('' when the provider gave none).
	 *
	 * @return string
	 */
	public static function last_id() {
		return self::$last_id;
	}

	/**
	 * Build the email (pure apart from the URL helpers; unit tested through the parts).
	 *
	 * @param array $email      subject, preview_text, content_html, from_name, from_email, reply_to.
	 * @param array $contact    Contact.
	 * @param int   $message_id Message id (0 for test sends: no tracking).
	 * @return array{to:string,subject:string,html:string,headers:string[]}
	 */
	public static function build( array $email, array $contact, $message_id = 0 ) {
		/**
		 * The email for one contact (the Master module translates it into the contact's language).
		 *
		 * @param array $email      subject, preview_text, content_html, campaign_id or journey_id and step_key, extra ...
		 * @param array $contact    Contact.
		 * @param int   $message_id Message id (0 for tests).
		 */
		$filtered = apply_filters( 'qcld_wpbot_marketing/email_for_contact', $email, $contact, (int) $message_id );
		$email    = is_array( $filtered ) ? array_merge( $email, $filtered ) : $email;
		$settings = Settings::all();
		$unsub    = Links::unsubscribe_url( (string) $contact['token'] );
		$site     = array(
			'site_name'       => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'        => home_url( '/' ),
			'unsubscribe_url' => $unsub,
		);
		/**
		 * The email body for one contact, before merge tags (the Master module fills per-contact blocks such as {top_story}).
		 *
		 * @param string $html    Body HTML.
		 * @param array  $contact Contact.
		 * @param array  $email   Email: subject, content_html, campaign_id ...
		 */
		$content = (string) apply_filters( 'qcld_wpbot_marketing/email_html', (string) $email['content_html'], $contact, $email );
		$subject = Personalizer::apply( (string) $email['subject'], $contact, $site, false );
		$body    = Personalizer::apply( $content, $contact, $site, true );
		if ( $message_id && ! empty( $settings['track_clicks'] ) ) {
			$body = self::track_links( $body, (int) $message_id );
		}
		$preview = isset( $email['preview_text'] ) ? trim( Personalizer::apply( (string) $email['preview_text'], $contact, $site, false ) ) : '';
		$html    = self::wrap( $body, $preview, $unsub, (string) $settings['company_address'], $site['site_name'] );
		if ( $message_id && ! empty( $settings['track_opens'] ) ) {
			$html = str_replace( '</body>', '<img src="' . esc_url( Links::open_url( (int) $message_id ) ) . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;"></body>', $html );
		}
		$from_name  = '' !== trim( (string) ( isset( $email['from_name'] ) ? $email['from_name'] : '' ) ) ? (string) $email['from_name'] : (string) $settings['from_name'];
		$from_email = is_email( isset( $email['from_email'] ) ? $email['from_email'] : '' ) ? (string) $email['from_email'] : (string) $settings['from_email'];
		$reply_to   = is_email( isset( $email['reply_to'] ) ? $email['reply_to'] : '' ) ? (string) $email['reply_to'] : (string) $settings['reply_to'];
		$headers    = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . self::header_name( $from_name ) . ' <' . $from_email . '>',
			'List-Unsubscribe: <' . $unsub . '>',
			'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
		);
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		return array(
			'to'      => (string) $contact['email'],
			'subject' => $subject,
			'html'    => $html,
			'headers' => $headers,
		);
	}

	/**
	 * Send a built email.
	 *
	 * @param array $built   From build().
	 * @param array $context message_id, campaign_id, journey_id, kind (campaign, journey, confirmation, test).
	 * @return true|WP_Error
	 */
	public static function send( array $built, array $context = array() ) {
		/**
		 * Lets a provider send the email instead of wp_mail(). Return true, array( 'id' => provider
		 * message id ), or a WP_Error to take over.
		 *
		 * @param null|true|array|WP_Error $result  Null to let wp_mail() send.
		 * @param array              $built   to, subject, html, headers.
		 * @param array              $context message_id, campaign_id, journey_id, kind.
		 */
		self::$last_id = '';
		$result        = apply_filters( 'qcld_wpbot_marketing/send', null, $built, $context );
		if ( is_array( $result ) ) {
			self::$last_id = isset( $result['id'] ) ? substr( (string) $result['id'], 0, 190 ) : '';
			return true;
		}
		if ( null !== $result ) {
			return true === $result ? true : ( is_wp_error( $result ) ? $result : new WP_Error( 'qcld_mk_send', __( 'The sending provider did not accept the email.', 'chatbot' ) ) );
		}
		$error = null;
		$catch = static function ( $wp_error ) use ( &$error ) {
			$error = $wp_error;
		};
		add_action( 'wp_mail_failed', $catch );
		$sent = wp_mail( $built['to'], $built['subject'], $built['html'], $built['headers'] );
		remove_action( 'wp_mail_failed', $catch );
		if ( $sent ) {
			return true;
		}
		return $error instanceof WP_Error ? $error : new WP_Error( 'qcld_mk_send', __( 'WordPress could not send the email.', 'chatbot' ) );
	}

	/**
	 * Replace http(s) links with signed click-tracking links (pure; unit tested).
	 * Unsubscribe links, mailto:, tel: and anchors are left alone.
	 *
	 * @param string $html       HTML.
	 * @param int    $message_id Message id.
	 * @return string
	 */
	public static function track_links( $html, $message_id ) {
		return (string) preg_replace_callback(
			'/(<a\b[^>]*\bhref=)(["\'])(https?:\/\/[^"\']+)\2/i',
			static function ( $m ) use ( $message_id ) {
				$url = html_entity_decode( $m[3], ENT_QUOTES );
				if ( false !== strpos( $url, 'wpbot_mk=u' ) || false !== strpos( $url, 'wpbot_crm=u' ) ) {
					return $m[0];
				}
				return $m[1] . $m[2] . esc_url( Links::click_url( $message_id, $url ) ) . $m[2];
			},
			(string) $html
		);
	}

	/**
	 * Wrap the body in a simple, email-safe layout with the footer.
	 *
	 * @param string $body    Body HTML.
	 * @param string $preview Preview text (hidden preheader).
	 * @param string $unsub   Unsubscribe URL.
	 * @param string $address Company address.
	 * @param string $site    Site name.
	 * @return string
	 */
	public static function wrap( $body, $preview, $unsub, $address, $site ) {
		$footer = '<p style="margin:24px 0 0;color:#6b7280;font-size:12px;line-height:1.5;text-align:center;">'
			. ( '' !== trim( $address ) ? nl2br( esc_html( $address ) ) . '<br>' : esc_html( $site ) . '<br>' )
			/* translators: %s: unsubscribe link. */
			. sprintf( esc_html__( 'Don\'t want these emails? %s', 'chatbot' ), '<a href="' . esc_url( $unsub ) . '" style="color:#6b7280;">' . esc_html__( 'Unsubscribe', 'chatbot' ) . '</a>' )
			. '</p>';
		$pre = '' !== $preview ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . esc_html( $preview ) . '</div>' : '';
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#f4f4f5;">'
			. $pre
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;"><tr><td align="center" style="padding:24px 12px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;"><tr><td style="padding:28px 32px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">'
			. $body
			. '</td></tr></table>'
			. $footer
			. '</td></tr></table></body></html>';
	}

	/**
	 * A From name safe for a header.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function header_name( $name ) {
		$name = trim( preg_replace( '/[\r\n"<>]+/', ' ', (string) $name ) );
		return '"' . $name . '"';
	}
}
