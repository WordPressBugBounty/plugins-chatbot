<?php
/**
 * Signed tracking links and their front-end handlers.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Mail;

use QuantumCloud\WPBot\CRM\Domain\ContactRepository;
use QuantumCloud\WPBot\CRM\Domain\ContactService;
use QuantumCloud\WPBot\CRM\Domain\EventRepository;
use QuantumCloud\WPBot\CRM\Domain\MessageRepository;
use QuantumCloud\WPBot\CRM\Domain\Schema;
use QuantumCloud\WPBot\CRM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Handles `?wpbot_crm=o|c|u|v` requests: open pixel, click redirect, unsubscribe page and
 * double opt-in confirmation. Open/click links carry an HMAC signature; unsubscribe and
 * confirm links are bound to the contact's random token.
 */
final class Tracking {

	const QUERY_VAR = 'wpbot_crm';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 1 );
		add_action( 'wpbot_crm/send_confirmation', array( Mailer::class, 'send_confirmation' ) );
	}

	/**
	 * Register the query var.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Unsubscribe page URL for a contact.
	 *
	 * @param array $contact Contact row.
	 * @return string
	 */
	public static function unsubscribe_url( array $contact ) {
		return self::public_url( 'u', self::token( $contact ) );
	}

	/**
	 * Double opt-in confirmation URL for a contact.
	 *
	 * @param array $contact Contact row.
	 * @return string
	 */
	public static function confirm_url( array $contact ) {
		return self::public_url( 'v', self::token( $contact ) );
	}

	/**
	 * Signed open-pixel URL.
	 *
	 * @param int $message_id Message id.
	 * @return string
	 */
	public static function open_url( $message_id ) {
		return add_query_arg(
			array(
				self::QUERY_VAR => 'o',
				'm'             => (int) $message_id,
				's'             => self::sign( 'o', $message_id, '' ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Signed click-redirect URL.
	 *
	 * @param int    $message_id Message id.
	 * @param string $url        Destination.
	 * @return string
	 */
	public static function click_url( $message_id, $url ) {
		$payload = self::b64_encode( (string) $url );
		return add_query_arg(
			array(
				self::QUERY_VAR => 'c',
				'm'             => (int) $message_id,
				'u'             => $payload,
				's'             => self::sign( 'c', $message_id, $payload ),
			),
			home_url( '/' )
		);
	}

	/**
	 * HMAC signature: first 20 hex chars of sha256("{type}|{message_id}|{payload}").
	 *
	 * @param string $type       o|c.
	 * @param int    $message_id Message id.
	 * @param string $payload    Payload.
	 * @return string
	 */
	public static function sign( $type, $message_id, $payload ) {
		return substr( hash_hmac( 'sha256', $type . '|' . (int) $message_id . '|' . $payload, Schema::secret() ), 0, 20 );
	}

	/**
	 * Constant-time signature check.
	 *
	 * @param string $type       o|c.
	 * @param int    $message_id Message id.
	 * @param string $payload    Payload.
	 * @param string $sig        Signature from the URL.
	 * @return bool
	 */
	public static function verify( $type, $message_id, $payload, $sig ) {
		return is_string( $sig ) && '' !== $sig && hash_equals( self::sign( $type, $message_id, $payload ), $sig );
	}

	/**
	 * Dispatch a tracking request (template_redirect).
	 */
	public static function handle() {
		$action = get_query_var( self::QUERY_VAR );
		if ( '' === $action || null === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public signed link.
			$action = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';
		}
		switch ( (string) $action ) {
			case 'o':
				self::handle_open();
				break;
			case 'c':
				self::handle_click();
				break;
			case 'u':
				self::handle_unsubscribe();
				break;
			case 'v':
				self::handle_confirm();
				break;
			default:
				return;
		}
		exit;
	}

	/**
	 * Open pixel.
	 */
	private static function handle_open() {
		$message_id = self::param_int( 'm' );
		if ( self::verify( 'o', $message_id, '', self::param( 's' ) ) && ! self::is_head() && Settings::bool( 'track_opens' ) ) {
			$messages = new MessageRepository();
			$message  = $messages->find( $message_id );
			$fresh    = $message && ! empty( $message['sent_at'] ) && ( time() - strtotime( $message['sent_at'] . ' UTC' ) ) < 2;
			if ( $message && ! $fresh && true === $messages->record_open( $message_id ) ) {
				$contact = self::contact_for( $message );
				( new EventRepository() )->log( 'open', (int) $message['contact_id'], array(), $message_id );
				if ( $contact ) {
					/**
					 * Fires on the first open of a message.
					 *
					 * @param array $message Message row.
					 * @param array $contact Contact row.
					 */
					do_action( 'wpbot_crm/email_opened', $messages->find( $message_id ), $contact );
				}
			}
		}
		nocache_headers();
		header( 'Content-Type: image/gif' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- 1x1 transparent GIF.
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
	}

	/**
	 * Click redirect. Redirects only when the signature is valid (no open redirect).
	 */
	private static function handle_click() {
		$message_id = self::param_int( 'm' );
		$payload    = self::param( 'u' );
		if ( ! self::verify( 'c', $message_id, $payload, self::param( 's' ) ) ) {
			self::page( __( 'Invalid link', 'chatbot' ), __( 'This link is invalid or has expired.', 'chatbot' ), 400 );
			return;
		}
		$url = self::b64_decode( $payload );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			self::page( __( 'Invalid link', 'chatbot' ), __( 'This link is invalid or has expired.', 'chatbot' ), 400 );
			return;
		}
		if ( ! self::is_head() && Settings::bool( 'track_clicks' ) ) {
			$messages = new MessageRepository();
			$message  = $messages->find( $message_id );
			if ( $message && null !== $messages->record_click( $message_id ) ) {
				( new EventRepository() )->log( 'click', (int) $message['contact_id'], array( 'url' => $url ), $message_id );
				$contact = self::contact_for( $message );
				if ( $contact ) {
					/**
					 * Fires on every tracked click.
					 *
					 * @param array  $message Message row.
					 * @param array  $contact Contact row.
					 * @param string $url     Destination URL.
					 */
					do_action( 'wpbot_crm/link_clicked', $messages->find( $message_id ), $contact, $url );
				}
			}
		}
		nocache_headers();
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external destination, HMAC-verified above.
		wp_redirect( $url, 302, 'WPBot' );
	}

	/**
	 * Unsubscribe: GET shows a confirmation button, POST (button or RFC 8058 one-click) applies it.
	 */
	private static function handle_unsubscribe() {
		$contact = self::contact_by_token();
		if ( ! $contact ) {
			self::page( __( 'Link not valid', 'chatbot' ), __( 'We could not find a subscription for this link.', 'chatbot' ), 404 );
			return;
		}
		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
		if ( 'unsubscribed' === $contact['status'] ) {
			self::page( __( 'Unsubscribed', 'chatbot' ), __( 'You are unsubscribed and will not receive further marketing emails from us.', 'chatbot' ) );
			return;
		}
		if ( ! $is_post ) {
			$form  = '<form method="post" action="' . esc_url( self::current_url() ) . '">';
			$form .= '<input type="hidden" name="wpbot_crm_unsubscribe" value="1" />';
			$form .= '<button type="submit" class="button button-primary" style="padding:8px 20px;cursor:pointer;">' . esc_html__( 'Yes, unsubscribe me', 'chatbot' ) . '</button></form>';
			/* translators: %s: email address. */
			$text = '<p>' . esc_html( sprintf( __( 'Unsubscribe %s from our emails?', 'chatbot' ), $contact['email'] ) ) . '</p>' . $form;
			self::page( __( 'Unsubscribe', 'chatbot' ), $text, 200, true );
			return;
		}
		$context    = array( 'source' => 'link' );
		$message_id = self::param_int( 'm' );
		if ( $message_id > 0 ) {
			$message = ( new MessageRepository() )->find( $message_id );
			if ( $message && (int) $message['contact_id'] === (int) $contact['id'] ) {
				$context['message_id'] = $message_id;
			}
		}
		( new ContactService() )->change_status( (int) $contact['id'], 'unsubscribed', $context );
		self::page( __( 'Unsubscribed', 'chatbot' ), __( 'You have been unsubscribed. You will not receive further marketing emails from us.', 'chatbot' ) );
	}

	/**
	 * Double opt-in confirmation.
	 */
	private static function handle_confirm() {
		$contact = self::contact_by_token();
		if ( ! $contact ) {
			self::page( __( 'Link not valid', 'chatbot' ), __( 'We could not find a subscription for this link.', 'chatbot' ), 404 );
			return;
		}
		if ( 'pending' === $contact['status'] ) {
			$updated = ( new ContactService() )->change_status( (int) $contact['id'], 'subscribed', array( 'source' => 'double_opt_in' ) );
			if ( is_array( $updated ) ) {
				/**
				 * Fires when a pending contact confirms their subscription.
				 *
				 * @param array $contact Updated contact row.
				 */
				do_action( 'wpbot_crm/contact_confirmed', $updated );
			}
		} elseif ( 'unsubscribed' === $contact['status'] ) {
			self::page( __( 'Unsubscribed', 'chatbot' ), __( 'This address has unsubscribed, so the confirmation link no longer applies.', 'chatbot' ) );
			return;
		}
		self::page( __( 'Subscription confirmed', 'chatbot' ), __( 'Thank you! Your subscription is confirmed.', 'chatbot' ) );
	}

	/**
	 * Minimal themed page via wp_die (does not exit by itself; handle() exits).
	 *
	 * @param string $title  Title.
	 * @param string $body   Text or trusted HTML.
	 * @param int    $status HTTP status.
	 * @param bool   $html   Whether $body is already HTML.
	 */
	private static function page( $title, $body, $status = 200, $html = false ) {
		nocache_headers();
		$content = '<h1>' . esc_html( $title ) . '</h1>' . ( $html ? $body : '<p>' . esc_html( $body ) . '</p>' );
		wp_die(
			$content, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_html( $title ),
			array(
				'response'  => (int) $status,
				'back_link' => false,
				'exit'      => false,
			)
		);
	}

	/**
	 * Contact for the `t` token parameter.
	 *
	 * @return array|null
	 */
	private static function contact_by_token() {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', self::param( 't' ) );
		return '' === $token ? null : ( new ContactRepository() )->find_by_token( $token );
	}

	/**
	 * Contact of a message (and bump last activity).
	 *
	 * @param array $message Message row.
	 * @return array|null
	 */
	private static function contact_for( array $message ) {
		$contacts = new ContactRepository();
		$contacts->touch_activity( (int) $message['contact_id'] );
		return $contacts->find( (int) $message['contact_id'] );
	}

	/**
	 * Contact token, generated when missing.
	 *
	 * @param array $contact Contact row.
	 * @return string
	 */
	private static function token( array $contact ) {
		$token = isset( $contact['token'] ) ? (string) $contact['token'] : '';
		if ( '' === $token && ! empty( $contact['id'] ) ) {
			$token = ( new ContactService() )->generate_token();
			( new ContactRepository() )->update( (int) $contact['id'], array( 'token' => $token ) );
		}
		return $token;
	}

	/**
	 * Token-bound public URL.
	 *
	 * @param string $type  u|v.
	 * @param string $token Contact token.
	 * @return string
	 */
	private static function public_url( $type, $token ) {
		return add_query_arg(
			array(
				self::QUERY_VAR => $type,
				't'             => rawurlencode( $token ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Current request URL (for the unsubscribe form action).
	 *
	 * @return string
	 */
	private static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return home_url( $uri );
	}

	/**
	 * Raw string query parameter.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function param( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed/token-bound public links.
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/**
	 * Integer query parameter.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	private static function param_int( $key ) {
		return absint( self::param( $key ) );
	}

	/**
	 * Whether this is a HEAD request (link scanners).
	 *
	 * @return bool
	 */
	private static function is_head() {
		return isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
	}

	/**
	 * URL-safe base64 without padding.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function b64_encode( $value ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL payload.
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode URL-safe base64.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function b64_decode( $value ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- URL payload.
		$decoded = base64_decode( strtr( (string) $value, '-_', '+/' ), true );
		return false === $decoded ? '' : $decoded;
	}
}
