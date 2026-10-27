<?php
/**
 * Outgoing marketing and system email.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Mail;

use QuantumCloud\WPBot\CRM\Domain\AudienceResolver;
use QuantumCloud\WPBot\CRM\Domain\CampaignRepository;
use QuantumCloud\WPBot\CRM\Domain\MessageRepository;
use QuantumCloud\WPBot\CRM\Domain\Personalizer;
use QuantumCloud\WPBot\CRM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Builds one personalised email per contact (footer, compliance headers, tracking) and
 * hands it to wp_mail(). Each send is recorded in the messages table.
 */
final class Mailer {

	/**
	 * Personalise, add footer/compliance, tracking (per settings), List-Unsubscribe headers, send via wp_mail.
	 *
	 * Creates (or reuses $context['message_id']) a `messages` row, marks it sent/failed and fires
	 * wpbot_crm/message_sent on success.
	 *
	 * @param array  $contact Contact row.
	 * @param string $subject Subject (merge tags allowed).
	 * @param string $html    HTML body (merge tags allowed).
	 * @param array  $context campaign_id, automation_id, step_key, message_id, from_name, from_email,
	 *                        reply_to, utm (bool), utm_campaign (slug), preview_text.
	 * @return int|\WP_Error Message id on success.
	 */
	public static function send_to_contact( array $contact, $subject, $html, array $context = array() ) {
		$messages   = new MessageRepository();
		$contact_id = isset( $contact['id'] ) ? (int) $contact['id'] : 0;
		$message_id = isset( $context['message_id'] ) ? (int) $context['message_id'] : 0;
		$campaign   = isset( $context['campaign_id'] ) ? (int) $context['campaign_id'] : 0;

		if ( $message_id <= 0 ) {
			$message_id = $messages->insert(
				array(
					'contact_id'    => $contact_id,
					'campaign_id'   => $campaign > 0 ? $campaign : null,
					'automation_id' => ! empty( $context['automation_id'] ) ? (int) $context['automation_id'] : null,
					'step_key'      => isset( $context['step_key'] ) ? substr( (string) $context['step_key'], 0, 40 ) : null,
				)
			);
			if ( $message_id <= 0 ) {
				return new \WP_Error( 'wpbot_crm_queue_failed', __( 'The email could not be recorded for sending.', 'chatbot' ), array( 'status' => 500 ) );
			}
		}

		$email = isset( $contact['email'] ) ? (string) $contact['email'] : '';
		$state = isset( $contact['status'] ) ? (string) $contact['status'] : '';
		if ( ! is_email( $email ) || ! in_array( $state, AudienceResolver::SENDABLE, true ) ) {
			$messages->mark_skipped( $message_id, 'Contact is not subscribed.' );
			return new \WP_Error( 'wpbot_crm_not_sendable', __( 'This contact cannot receive marketing email.', 'chatbot' ), array( 'status' => 400 ) );
		}

		$unsubscribe = add_query_arg( 'm', $message_id, Tracking::unsubscribe_url( $contact ) );
		$extra       = array( 'unsubscribe_url' => $unsubscribe );
		$subject     = wp_strip_all_tags( Personalizer::render( (string) $subject, $contact, $extra, false ) );
		$body        = self::build_body( (string) $html, $contact, $extra, $message_id, $context );

		$headers   = self::headers( $context );
		$headers[] = 'List-Unsubscribe: <' . esc_url_raw( $unsubscribe ) . '>';
		$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';

		$error = '';
		$ok    = self::deliver( $email, $subject, $body, $headers, $error );

		if ( ! $ok ) {
			$messages->mark_failed( $message_id, '' !== $error ? $error : 'wp_mail() returned false.' );
			if ( $campaign > 0 ) {
				( new CampaignRepository() )->increment( $campaign, 'failed' );
			}
			return new \WP_Error( 'wpbot_crm_send_failed', '' !== $error ? $error : __( 'The email could not be sent.', 'chatbot' ), array( 'status' => 500 ) );
		}

		$messages->mark_sent( $message_id );
		if ( $campaign > 0 ) {
			( new CampaignRepository() )->increment( $campaign, 'sent' );
		}

		/**
		 * Fires after a CRM email was handed to wp_mail successfully.
		 *
		 * @param array $message Messages row.
		 */
		do_action( 'wpbot_crm/message_sent', $messages->find( $message_id ) );

		return $message_id;
	}

	/**
	 * Plain transactional mail (double opt-in confirmation, tests). No tracking, no footer.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $html    HTML body.
	 * @return bool
	 */
	public static function send_system( $to, $subject, $html ) {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$error = '';
		return self::deliver( (string) $to, wp_strip_all_tags( (string) $subject ), self::document( (string) $html, '' ), self::headers( array() ), $error );
	}

	/**
	 * Double opt-in: email the confirmation link (hooked to wpbot_crm/send_confirmation).
	 *
	 * @param array $contact Contact row.
	 */
	public static function send_confirmation( $contact ) {
		if ( ! is_array( $contact ) || empty( $contact['email'] ) ) {
			return;
		}
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$url  = Tracking::confirm_url( $contact );
		$name = isset( $contact['first_name'] ) ? trim( (string) $contact['first_name'] ) : '';
		/* translators: %s: site name. */
		$subject = sprintf( __( 'Please confirm your subscription to %s', 'chatbot' ), $site );
		$hello   = '' !== $name
			/* translators: %s: first name. */
			? sprintf( __( 'Hi %s,', 'chatbot' ), esc_html( $name ) )
			: esc_html__( 'Hi there,', 'chatbot' );

		$html  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;"><tr><td align="center">';
		$html .= '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:6px;font-family:Arial,Helvetica,sans-serif;color:#222;">';
		$html .= '<tr><td style="padding:32px;font-size:15px;line-height:1.6;">';
		$html .= '<p style="margin:0 0 16px;">' . $hello . '</p>';
		/* translators: %s: site name. */
		$html .= '<p style="margin:0 0 24px;">' . esc_html( sprintf( __( 'Thanks for signing up to hear from %s. Please confirm your email address to complete your subscription.', 'chatbot' ), $site ) ) . '</p>';
		$html .= '<p style="margin:0 0 24px;"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:4px;font-weight:bold;">' . esc_html__( 'Confirm my subscription', 'chatbot' ) . '</a></p>';
		$html .= '<p style="margin:0;font-size:13px;color:#666;">' . esc_html__( 'If you did not sign up, you can ignore this email.', 'chatbot' ) . '</p>';
		$html .= '</td></tr></table></td></tr></table>';

		self::send_system( (string) $contact['email'], $subject, $html );
	}

	/**
	 * Personalise and decorate the HTML body.
	 *
	 * @param string $html       Raw body.
	 * @param array  $contact    Contact.
	 * @param array  $extra      Extra merge tags.
	 * @param int    $message_id Message id.
	 * @param array  $context    Send context.
	 * @return string
	 */
	private static function build_body( $html, array $contact, array $extra, $message_id, array $context ) {
		$settings     = Settings::all();
		$has_unsub    = (bool) preg_match( '/\{\{?\s*unsubscribe_url\s*\}?\}/i', $html );
		$body         = Personalizer::render( $html, $contact, $extra, true );
		$footer       = Personalizer::render( self::footer( $settings, $extra['unsubscribe_url'], ! $has_unsub ), $contact, $extra, true );
		$body         = self::insert_before_body_end( $body, $footer );
		$unsubscribe  = $extra['unsubscribe_url'];
		$utm_campaign = isset( $context['utm_campaign'] ) ? (string) $context['utm_campaign'] : '';

		if ( ! empty( $context['utm'] ) ) {
			$body = self::rewrite_links(
				$body,
				$unsubscribe,
				static function ( $url ) use ( $utm_campaign ) {
					if ( false !== strpos( $url, 'utm_source=' ) ) {
						return $url;
					}
					return add_query_arg(
						array(
							'utm_source'   => 'wpbot',
							'utm_medium'   => 'email',
							'utm_campaign' => '' !== $utm_campaign ? $utm_campaign : 'wpbot-crm',
						),
						$url
					);
				}
			);
		}
		if ( ! empty( $settings['track_clicks'] ) ) {
			$body = self::rewrite_links(
				$body,
				$unsubscribe,
				static function ( $url ) use ( $message_id ) {
					return Tracking::click_url( $message_id, $url );
				}
			);
		}
		if ( ! empty( $settings['track_opens'] ) ) {
			$pixel = '<img src="' . esc_url( Tracking::open_url( $message_id ) ) . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;" />';
			$body  = self::insert_before_body_end( $body, $pixel );
		}

		$preview = isset( $context['preview_text'] ) ? Personalizer::render( (string) $context['preview_text'], $contact, $extra, false ) : '';
		return self::document( $body, $preview );
	}

	/**
	 * Footer HTML: settings footer ({YEAR}), company name/address, unsubscribe link.
	 *
	 * @param array  $settings    Settings.
	 * @param string $unsubscribe Unsubscribe URL.
	 * @param bool   $with_link   Whether to add the unsubscribe link (skipped when the body has one).
	 * @return string
	 */
	private static function footer( array $settings, $unsubscribe, $with_link ) {
		$parts  = array();
		$custom = str_replace( '{YEAR}', gmdate( 'Y' ), (string) $settings['email_footer'] );
		if ( '' !== trim( $custom ) ) {
			$parts[] = $custom;
		}
		$company = array_filter( array( trim( (string) $settings['company_name'] ), trim( (string) $settings['company_address'] ) ) );
		if ( ! empty( $company ) ) {
			$parts[] = '<p style="margin:4px 0;">' . nl2br( esc_html( implode( "\n", $company ) ) ) . '</p>';
		}
		if ( $with_link ) {
			$label   = '' !== trim( (string) $settings['unsubscribe_text'] ) ? (string) $settings['unsubscribe_text'] : __( 'Unsubscribe', 'chatbot' );
			$parts[] = '<p style="margin:4px 0;"><a href="' . esc_url( $unsubscribe ) . '" style="color:#6b7280;text-decoration:underline;">' . esc_html( $label ) . '</a></p>';
		}
		if ( empty( $parts ) ) {
			return '';
		}
		return '<div class="wpbot-crm-footer" style="max-width:600px;margin:24px auto 0;padding:16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;text-align:center;">' . implode( '', $parts ) . '</div>';
	}

	/**
	 * Apply $callback to every http(s) href except the unsubscribe link, mailto:, tel: and anchors.
	 *
	 * @param string   $html        HTML.
	 * @param string   $unsubscribe Unsubscribe URL (left untouched).
	 * @param callable $callback    fn( string $url ): string.
	 * @return string
	 */
	private static function rewrite_links( $html, $unsubscribe, $callback ) {
		return preg_replace_callback(
			'/(<a\b[^>]*?\bhref\s*=\s*)(["\'])(.*?)\2/is',
			static function ( $m ) use ( $unsubscribe, $callback ) {
				$url = trim( html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' ) );
				if ( '' === $url || $url === $unsubscribe || ! preg_match( '#^https?://#i', $url ) || false !== strpos( $url, 'wpbot_crm=' ) ) {
					return $m[0];
				}
				return $m[1] . $m[2] . esc_url( call_user_func( $callback, $url ) ) . $m[2];
			},
			$html
		);
	}

	/**
	 * Insert a fragment before </body>, or append it.
	 *
	 * @param string $html     HTML.
	 * @param string $fragment Fragment.
	 * @return string
	 */
	private static function insert_before_body_end( $html, $fragment ) {
		if ( '' === $fragment ) {
			return $html;
		}
		$pos = strripos( $html, '</body>' );
		return false === $pos ? $html . $fragment : substr( $html, 0, $pos ) . $fragment . substr( $html, $pos );
	}

	/**
	 * Wrap a fragment in a full HTML document and inject the hidden preview text.
	 *
	 * @param string $html    Body or full document.
	 * @param string $preview Inbox preview text.
	 * @return string
	 */
	private static function document( $html, $preview ) {
		$hidden = '' !== trim( $preview ) ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">' . esc_html( $preview ) . '</div>' : '';
		if ( preg_match( '/<body\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			$at = $m[0][1] + strlen( $m[0][0] );
			return substr( $html, 0, $at ) . $hidden . substr( $html, $at );
		}
		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;padding:0;">' . $hidden . $html . '</body></html>';
	}

	/**
	 * Content-Type, From and Reply-To headers from the context or settings.
	 *
	 * @param array $context Send context.
	 * @return string[]
	 */
	private static function headers( array $context ) {
		$from_email = ! empty( $context['from_email'] ) ? sanitize_email( $context['from_email'] ) : Settings::string( 'from_email' );
		$from_name  = ! empty( $context['from_name'] ) ? (string) $context['from_name'] : Settings::string( 'from_name' );
		$reply_to   = ! empty( $context['reply_to'] ) ? sanitize_email( $context['reply_to'] ) : Settings::string( 'reply_to' );
		$from_name  = trim( str_replace( array( "\r", "\n", '"', '<', '>' ), '', $from_name ) );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $from_email ) ) {
			$headers[] = 'From: ' . ( '' !== $from_name ? $from_name . ' ' : '' ) . '<' . $from_email . '>';
		}
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		return $headers;
	}

	/**
	 * Call wp_mail() and capture the failure reason.
	 *
	 * @param string   $to      Recipient.
	 * @param string   $subject Subject.
	 * @param string   $body    HTML.
	 * @param string[] $headers Headers.
	 * @param string   $error   Receives the error message.
	 * @return bool
	 */
	private static function deliver( $to, $subject, $body, array $headers, &$error ) {
		$error   = '';
		$capture = static function ( $wp_error ) use ( &$error ) {
			if ( is_wp_error( $wp_error ) ) {
				$error = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $capture );
		$ok = wp_mail( $to, $subject, $body, $headers );
		remove_action( 'wp_mail_failed', $capture );
		return (bool) $ok;
	}
}
