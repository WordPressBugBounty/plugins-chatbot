<?php
/**
 * Public email links: open pixel, click redirect, unsubscribe and confirm.
 *
 * Two formats are served:
 * - new links, ?wpbot_mk=o|c|u|v, signed with this module's secret;
 * - old links from emails the previous AI Marketing sent, ?wpbot_crm=o|c|u|v,
 *   signed with the old secret (read-only). They keep working after the
 *   update: message ids are translated through the migration map, and an
 *   unsubscribe is honoured even before the copy reached that contact.
 *
 * Signature: first 20 hex characters of HMAC-SHA256("{type}|{message_id}|{payload}").
 * Click destinations are only redirected to when the signature is valid, so
 * there is no open redirect. Unsubscribe and confirm links carry the
 * contact's random 32-character token.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Sending;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Migration\From_Crm;
use QuantumCloud\AiMarketing\Migration\Legacy_Tables;
use QuantumCloud\AiMarketing\Module;
use QuantumCloud\AiMarketing\Settings;
use QuantumCloud\AiMarketing\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Email links.
 */
final class Links {

	const VAR_NEW    = 'wpbot_mk';
	const VAR_LEGACY = 'wpbot_crm';

	/**
	 * 1x1 transparent GIF.
	 */
	const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

	/**
	 * Register the request handler.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 0 );
	}

	/* ── Building links. ── */

	/**
	 * Signed open-pixel URL.
	 *
	 * @param int $message_id Message id.
	 * @return string
	 */
	public static function open_url( $message_id ) {
		return add_query_arg(
			array(
				self::VAR_NEW => 'o',
				'm'           => (int) $message_id,
				's'           => self::sign( 'o', $message_id, '', Schema::secret() ),
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
				self::VAR_NEW => 'c',
				'm'           => (int) $message_id,
				'u'           => $payload,
				's'           => self::sign( 'c', $message_id, $payload, Schema::secret() ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Unsubscribe URL for a contact token.
	 *
	 * @param string $token Contact token.
	 * @return string
	 */
	public static function unsubscribe_url( $token ) {
		return add_query_arg(
			array(
				self::VAR_NEW => 'u',
				't'           => rawurlencode( (string) $token ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Double opt-in confirmation URL for a contact token.
	 *
	 * @param string $token Contact token.
	 * @return string
	 */
	public static function confirm_url( $token ) {
		return add_query_arg(
			array(
				self::VAR_NEW => 'v',
				't'           => rawurlencode( (string) $token ),
			),
			home_url( '/' )
		);
	}

	/* ── Signatures (pure; unit tested). ── */

	/**
	 * Signature.
	 *
	 * @param string $type       o|c.
	 * @param int    $message_id Message id.
	 * @param string $payload    Payload.
	 * @param string $secret     Secret.
	 * @return string
	 */
	public static function sign( $type, $message_id, $payload, $secret ) {
		return substr( hash_hmac( 'sha256', $type . '|' . (int) $message_id . '|' . $payload, (string) $secret ), 0, 20 );
	}

	/**
	 * Constant-time signature check. An empty secret never verifies.
	 *
	 * @param string $type       o|c.
	 * @param int    $message_id Message id.
	 * @param string $payload    Payload.
	 * @param string $sig        Signature from the URL.
	 * @param string $secret     Secret.
	 * @return bool
	 */
	public static function verify( $type, $message_id, $payload, $sig, $secret ) {
		return '' !== (string) $secret && is_string( $sig ) && '' !== $sig && hash_equals( self::sign( $type, $message_id, $payload, $secret ), $sig );
	}

	/**
	 * URL-safe base64 without padding.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function b64_encode( $value ) {
		return rtrim( strtr( base64_encode( (string) $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL payload.
	}

	/**
	 * Decode URL-safe base64 ('' when invalid).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function b64_decode( $value ) {
		$decoded = base64_decode( strtr( (string) $value, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- URL payload.
		return is_string( $decoded ) ? $decoded : '';
	}

	/**
	 * A click destination we redirect to: http(s) only.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function safe_destination( $url ) {
		return 1 === preg_match( '#^https?://[^\s]+$#i', (string) $url );
	}

	/* ── Handling requests. ── */

	/**
	 * Dispatch a link request (template_redirect, before the theme).
	 *
	 * @return void
	 */
	public static function handle() {
		$legacy = isset( $_GET[ self::VAR_LEGACY ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed or token-bound public links.
		$var    = $legacy ? self::VAR_LEGACY : self::VAR_NEW;
		$action = self::param( $var );
		if ( '' === $action ) {
			return;
		}
		switch ( sanitize_key( $action ) ) {
			case 'o':
				self::handle_open( $legacy );
				break;
			case 'c':
				self::handle_click( $legacy );
				break;
			case 'u':
				self::handle_unsubscribe( $legacy );
				break;
			case 'v':
				self::handle_confirm( $legacy );
				break;
			default:
				return;
		}
		exit;
	}

	/**
	 * Open pixel.
	 *
	 * @param bool $legacy Old link.
	 * @return void
	 */
	private static function handle_open( $legacy ) {
		$message_id = self::message_id( $legacy );
		$sig_id     = (int) self::param( 'm' );
		if ( self::verify( 'o', $sig_id, '', self::param( 's' ), self::secret( $legacy ) ) && $message_id && ! self::is_head() && Settings::get( 'track_opens' ) ) {
			self::record( $message_id, 'open', array() );
		}
		nocache_headers();
		header( 'Content-Type: image/gif' );
		echo base64_decode( self::PIXEL ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- 1x1 GIF.
	}

	/**
	 * Click redirect.
	 *
	 * @param bool $legacy Old link.
	 * @return void
	 */
	private static function handle_click( $legacy ) {
		$sig_id  = (int) self::param( 'm' );
		$payload = self::param( 'u' );
		$url     = self::b64_decode( $payload );
		if ( ! self::verify( 'c', $sig_id, $payload, self::param( 's' ), self::secret( $legacy ) ) || ! self::safe_destination( $url ) ) {
			self::page( __( 'Invalid link', 'chatbot' ), __( 'This link is invalid or has expired.', 'chatbot' ), 400 );
			return;
		}
		$message_id = self::message_id( $legacy );
		if ( $message_id && ! self::is_head() && Settings::get( 'track_clicks' ) ) {
			self::record( $message_id, 'click', array( 'url' => $url ) );
		}
		nocache_headers();
		wp_redirect( $url, 302, 'WPBot' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External destination, signature verified above.
	}

	/**
	 * Unsubscribe: GET shows a button, POST (the button or RFC 8058 one-click) applies it.
	 *
	 * @param bool $legacy Old link.
	 * @return void
	 */
	private static function handle_unsubscribe( $legacy ) {
		$token   = preg_replace( '/[^A-Za-z0-9]/', '', self::param( 't' ) );
		$contact = '' === $token ? null : self::contact_for_token( $token, $legacy );
		if ( ! $contact ) {
			self::page( __( 'Invalid link', 'chatbot' ), __( 'This unsubscribe link is invalid or has expired.', 'chatbot' ), 404 );
			return;
		}
		if ( ! self::is_post() ) {
			$action = esc_url( add_query_arg( array() ) );
			$button = '<form method="post" action="' . $action . '"><button type="submit" style="padding:10px 18px;font-size:15px;cursor:pointer;">' . esc_html__( 'Unsubscribe', 'chatbot' ) . '</button></form>';
			self::page( __( 'Unsubscribe', 'chatbot' ), esc_html__( 'Click the button to stop receiving these emails.', 'chatbot' ) . $button, 200, true );
			return;
		}
		self::unsubscribe( $contact, $legacy ? 'legacy_link' : 'link' );
		self::page( __( 'Unsubscribed', 'chatbot' ), __( 'You have been unsubscribed and will not receive these emails again.', 'chatbot' ) );
	}

	/**
	 * Double opt-in confirmation.
	 *
	 * @param bool $legacy Old link.
	 * @return void
	 */
	private static function handle_confirm( $legacy ) {
		global $wpdb;
		$token   = preg_replace( '/[^A-Za-z0-9]/', '', self::param( 't' ) );
		$contact = '' === $token ? null : self::contact_for_token( $token, $legacy );
		if ( ! $contact ) {
			self::page( __( 'Invalid link', 'chatbot' ), __( 'This confirmation link is invalid or has expired.', 'chatbot' ), 404 );
			return;
		}
		if ( 'pending' === $contact['status'] ) {
			$now = current_time( 'mysql', true );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				Schema::table( 'contacts' ),
				array(
					'status'       => 'subscribed',
					'confirmed_at' => $now,
					'updated_at'   => $now,
				),
				array( 'id' => (int) $contact['id'] )
			);
			$contact['status'] = 'subscribed';
			self::event( (int) $contact['id'], null, 'confirm', array() );
			/**
			 * A contact confirmed their subscription (double opt-in).
			 *
			 * @param array $contact Contact row.
			 */
			do_action( 'qcld_wpbot_marketing/contact_confirmed', $contact );
		}
		self::page( __( 'Subscription confirmed', 'chatbot' ), __( 'Thank you. Your subscription is confirmed.', 'chatbot' ) );
	}

	/**
	 * Mark a contact unsubscribed everywhere: this module, the event log, and the chatbot's User Data.
	 *
	 * @param array  $contact Contact row.
	 * @param string $source  Where it came from.
	 * @return void
	 */
	public static function unsubscribe( array $contact, $source ) {
		global $wpdb;
		if ( 'unsubscribed' !== $contact['status'] ) {
			$now = current_time( 'mysql', true );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				Schema::table( 'contacts' ),
				array(
					'status'          => 'unsubscribed',
					'unsubscribed_at' => $now,
					'updated_at'      => $now,
				),
				array( 'id' => (int) $contact['id'] )
			);
			self::event( (int) $contact['id'], null, 'unsubscribe', array( 'source' => $source ) );
		}
		$host = Module::instance()->host();
		$lead = $host ? $host->get_lead_source() : null;
		if ( $lead && ! empty( $contact['email'] ) ) {
			$lead->mark_unsubscribed( (string) $contact['email'] );
		}
		/**
		 * A contact unsubscribed.
		 *
		 * @param array  $contact Contact row (before the change).
		 * @param string $source  link, legacy_link, chat ...
		 */
		do_action( 'qcld_wpbot_marketing/contact_unsubscribed', $contact, $source );
	}

	/* ── Lookups. ── */

	/**
	 * Contact for a token. For old links whose contact was not copied yet, the
	 * old contact is read (never changed) and added here, so the request is honoured.
	 *
	 * @param string $token  Token.
	 * @param bool   $legacy Old link.
	 * @return array|null
	 */
	private static function contact_for_token( $token, $legacy ) {
		global $wpdb;
		$table   = Schema::table( 'contacts' );
		$contact = Contacts::by_token( $token );
		if ( $contact || ! $legacy || ! Legacy_Tables::exists( 'contacts' ) ) {
			return $contact ? $contact : null;
		}
		$old_table = Legacy_Tables::table( 'contacts' );
		$old       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$old_table}` WHERE token = %s", $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Old table, read only.
		if ( ! $old ) {
			return null;
		}
		From_Crm::copy( 'contact', (int) $old['id'] - 1 );
		$new_id = From_Crm::mapped( 'contact', (int) $old['id'] );
		return $new_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $new_id ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * New message id for the link (old ids go through the migration map).
	 *
	 * @param bool $legacy Old link.
	 * @return int
	 */
	private static function message_id( $legacy ) {
		$id = (int) self::param( 'm' );
		if ( $id <= 0 ) {
			return 0;
		}
		return $legacy ? From_Crm::mapped( 'message', $id ) : $id;
	}

	/**
	 * Secret for a link format.
	 *
	 * @param bool $legacy Old link.
	 * @return string
	 */
	private static function secret( $legacy ) {
		return $legacy ? Legacy_Tables::secret() : Schema::secret();
	}

	/**
	 * Count an open or click on a message and log it.
	 *
	 * @param int    $message_id Message id.
	 * @param string $type       open|click.
	 * @param array  $data       Event data.
	 * @return void
	 */
	private static function record( $message_id, $type, array $data ) {
		global $wpdb;
		$table   = Schema::table( 'messages' );
		$message = $wpdb->get_row( $wpdb->prepare( "SELECT id, contact_id, campaign_id, journey_id, step_key, sent_at FROM `{$table}` WHERE id = %d", (int) $message_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( ! $message ) {
			return;
		}
		$context = array(
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 300 ) : '',
			'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'sent_at'    => (string) $message['sent_at'],
		);
		/**
		 * Whether an open or click came from a person (the Master module filters out
		 * privacy proxies and security scanners, so "engaged" means real people).
		 *
		 * @param bool   $real    Real.
		 * @param string $type    open|click.
		 * @param array  $context user_agent, ip, sent_at (UTC).
		 * @param array  $message Message row.
		 */
		if ( ! apply_filters( 'qcld_wpbot_marketing/real_engagement', true, $type, $context, $message ) ) {
			/** This action fires instead of email_engagement for machine opens and clicks. */
			do_action( 'qcld_wpbot_marketing/machine_engagement', (int) $message_id, (int) $message['contact_id'], $type, $message );
			return;
		}
		$now = current_time( 'mysql', true );
		if ( 'open' === $type ) {
			$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET opens = opens + 1, first_open_at = COALESCE(first_open_at, %s) WHERE id = %d", $now, (int) $message_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET clicks = clicks + 1, first_click_at = COALESCE(first_click_at, %s), opens = GREATEST(opens, 1), first_open_at = COALESCE(first_open_at, %s) WHERE id = %d", $now, $now, (int) $message_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		self::event( (int) $message['contact_id'], (int) $message_id, $type, $data );
		/**
		 * An email was opened or a link clicked.
		 *
		 * @param int    $message_id Message id.
		 * @param int    $contact_id Contact id.
		 * @param string $type       open|click.
		 * @param array  $data       url for clicks.
		 * @param array  $message    Message row: campaign_id, journey_id, step_key, sent_at.
		 */
		do_action( 'qcld_wpbot_marketing/email_engagement', (int) $message_id, (int) $message['contact_id'], $type, $data, $message );
	}

	/**
	 * Add a timeline event.
	 *
	 * @param int      $contact_id Contact id.
	 * @param int|null $message_id Message id.
	 * @param string   $type       Type.
	 * @param array    $data       Data.
	 * @return void
	 */
	public static function event( $contact_id, $message_id, $type, array $data ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'events' ),
			array(
				'contact_id' => (int) $contact_id,
				'message_id' => $message_id ? (int) $message_id : null,
				'type'       => substr( (string) $type, 0, 30 ),
				'data'       => wp_json_encode( $data ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	/* ── Request helpers. ── */

	/**
	 * GET parameter.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function param( $key ) {
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed or token-bound public links.
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
	 * Whether this is a POST request.
	 *
	 * @return bool
	 */
	private static function is_post() {
		return isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
	}

	/**
	 * Print a small standalone page.
	 *
	 * @param string $title  Title.
	 * @param string $body   Body (plain text unless $html).
	 * @param int    $status HTTP status.
	 * @param bool   $html   Body is already escaped HTML.
	 * @return void
	 */
	private static function page( $title, $body, $status = 200, $html = false ) {
		nocache_headers();
		status_header( (int) $status );
		header( 'Content-Type: text/html; charset=utf-8' );
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $title . ' – ' . $site ) . '</title></head>';
		echo '<body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:520px;margin:12vh auto;padding:0 20px;color:#1d2327;line-height:1.5;">';
		echo '<h1 style="font-size:22px;">' . esc_html( $title ) . '</h1><div>' . ( $html ? $body : esc_html( $body ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $body is escaped by the caller when $html.
		echo '<p style="margin-top:32px;color:#646970;font-size:13px;">' . esc_html( $site ) . '</p></body></html>';
	}
}
