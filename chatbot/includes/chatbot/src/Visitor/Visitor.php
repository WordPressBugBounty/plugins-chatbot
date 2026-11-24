<?php
/**
 * Visitor identity (shared contract C-1, Store plan section 11.1): one visitor key per browser,
 * linked to the Site Insight visitor, the WordPress user, the store customer and their orders,
 * with a "verified customer" flag set by the one-time email code (Verification).
 *
 * The key is a random 32-character id in a first-party cookie (qcld_wpbot_vk), signed with the
 * site's salt so a browser cannot pick another visitor's key. Cookies are set only in chat
 * requests (AJAX/REST), never on cached pages. The Site Insight link is made only when the browser
 * sends Site Insight's visitor id, which Site Insight stores only after statistics consent; the
 * hash is computed the way Site Insight computes it, so the two can be joined.
 *
 * Generic: live chat conversations and the Inbox use it too, not only the store.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Visitor;

defined( 'ABSPATH' ) || exit;

/**
 * Visitor identity.
 */
final class Visitor {

	/**
	 * Cookie name.
	 */
	const COOKIE = 'qcld_wpbot_vk';

	/**
	 * Key of this request's visitor (set once found or created).
	 *
	 * @var string|null
	 */
	private static $key = null;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( Schema::class, 'maybe_install' ), 6 );
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );
	}

	/**
	 * This request's visitor key, from the signed cookie; a new one when $create (chat requests).
	 *
	 * @param bool $create Create and set the cookie when the browser has none.
	 * @return string '' when there is none and $create is false.
	 */
	public static function key( $create = false ) {
		if ( null !== self::$key ) {
			return self::$key;
		}
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		$key = self::verify_cookie( $raw );
		if ( '' === $key && $create ) {
			$key = bin2hex( random_bytes( 16 ) );
			if ( ! headers_sent() ) {
				setcookie(
					self::COOKIE,
					self::cookie_value( $key ),
					array(
						'expires'  => time() + YEAR_IN_SECONDS,
						'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
						'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
						'secure'   => is_ssl(),
						'httponly' => true,
						'samesite' => 'Lax',
					)
				);
			}
		}
		if ( '' !== $key ) {
			self::$key = $key;
		}
		return $key;
	}

	/**
	 * Forget this request's key (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$key = null;
	}

	/**
	 * The signed cookie value for a key.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function cookie_value( $key ) {
		return $key . '.' . self::sign( $key );
	}

	/**
	 * The key in a cookie value, or '' when the value is not a valid signed key.
	 *
	 * @param string $value Cookie value.
	 * @return string
	 */
	public static function verify_cookie( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^([a-f0-9]{32})\.([a-f0-9]{16})$/', $value, $parts ) ) {
			return '';
		}
		return hash_equals( self::sign( $parts[1] ), $parts[2] ) ? $parts[1] : '';
	}

	/**
	 * Signature of a key.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function sign( $key ) {
		return substr( hash_hmac( 'sha256', 'qcld_wpbot_vk|' . $key, wp_salt( 'auth' ) ), 0, 16 );
	}

	/**
	 * Site Insight's hash of its visitor id (the same as its ingest computes).
	 *
	 * @param string $vid Site Insight visitor id (localStorage qcin_v).
	 * @return string
	 */
	public static function insight_hash( $vid ) {
		$vid = preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $vid );
		return '' === $vid ? '' : hash_hmac( 'sha256', $vid, wp_salt( 'auth' ) );
	}

	/**
	 * Hash of an email address (lower case), never stored in clear here.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	public static function email_hash( $email ) {
		$email = strtolower( trim( (string) $email ) );
		return '' === $email ? '' : hash_hmac( 'sha256', 'email|' . $email, wp_salt( 'auth' ) );
	}

	/**
	 * Links this visitor to what a chat request tells about it: the logged-in user, Site Insight's
	 * visitor and session ids (sent by the widget as qcin_vid and qcin_sid).
	 *
	 * @param array $request Request fields (vid, sid).
	 * @return string The visitor key ('' when the request has none and none could be set).
	 */
	public static function identify( array $request = array() ) {
		$key = self::key( true );
		if ( '' === $key ) {
			return '';
		}
		$link = array();
		if ( is_user_logged_in() ) {
			$link['user_id'] = get_current_user_id();
		}
		if ( ! empty( $request['vid'] ) ) {
			$link['insight_visitor'] = self::insight_hash( (string) $request['vid'] );
		}
		if ( ! empty( $request['sid'] ) ) {
			$link['insight_session'] = substr( preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $request['sid'] ), 0, 64 );
		}
		self::link( $key, $link );
		return $key;
	}

	/**
	 * Chat admin-ajax requests identify their visitor (actions of the chat and the store).
	 *
	 * @return void
	 */
	public static function identify_ajax() {
		if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() || empty( $_REQUEST['action'] ) || ! is_string( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		/**
		 * Whether an admin-ajax action is a chat request whose visitor is identified.
		 *
		 * @param bool   $chat   Chat request.
		 * @param string $action Action.
		 */
		$chat = (bool) apply_filters( 'qcld_wpbot_chat/visitor_action', (bool) preg_match( '/^(qcld_wb_chatbot_|wpbo_|qcld_wpbot_|variable_add_to_cart$|wpbot_save_|qcld_stream_|qcld_openai|openai_response$|gemini_response$|qcld_gemini|claude_response$|qcld_grok|openrouter_response$|mistral_response$|ollama_response$|deepseek_response$)/', $action ), $action );
		if ( ! $chat ) {
			return;
		}
		self::identify(
			array(
				'vid' => isset( $_POST['qcin_vid'] ) && is_scalar( $_POST['qcin_vid'] ) ? sanitize_text_field( wp_unslash( $_POST['qcin_vid'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'sid' => isset( $_POST['qcin_sid'] ) && is_scalar( $_POST['qcin_sid'] ) ? sanitize_text_field( wp_unslash( $_POST['qcin_sid'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			)
		);
	}

	/**
	 * Adds or updates what a visitor is linked to. Empty values never overwrite saved ones.
	 *
	 * @param string $key  Visitor key.
	 * @param array  $data user_id, insight_visitor, insight_session, store, customer_id, email (hashed here).
	 * @return bool
	 */
	public static function link( $key, array $data ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $key ) ) {
			return false;
		}
		if ( isset( $data['email'] ) ) {
			$data['email_hash'] = self::email_hash( $data['email'] );
			unset( $data['email'] );
		}
		$allowed = array( 'user_id', 'insight_visitor', 'insight_session', 'store', 'customer_id', 'email_hash' );
		$data    = array_filter(
			array_intersect_key( $data, array_flip( $allowed ) ),
			static function ( $v ) {
				return '' !== $v && 0 !== $v && null !== $v;
			}
		);
		$now     = current_time( 'mysql', true );
		$table   = Schema::visitors_table();
		$exists  = $wpdb->get_var( $wpdb->prepare( "SELECT visitor_key FROM {$table} WHERE visitor_key = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $exists ) {
			if ( ! $data ) {
				return true;
			}
			$data['updated_at'] = $now;
			return false !== $wpdb->update( $table, $data, array( 'visitor_key' => $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return false !== $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array_merge(
				array(
					'visitor_key' => $key,
					'created_at'  => $now,
					'updated_at'  => $now,
				),
				$data
			)
		);
	}

	/**
	 * What a visitor is linked to.
	 *
	 * @param string|null $key Visitor key (null = this request's).
	 * @return array|null
	 */
	public static function get( $key = null ) {
		global $wpdb;
		$key = null === $key ? self::key() : (string) $key;
		if ( '' === $key ) {
			return null;
		}
		$table = Schema::visitors_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE visitor_key = %s", $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Marks the visitor as a verified customer for an email (after the one-time code).
	 *
	 * @param string $key   Visitor key.
	 * @param string $email Email that was verified.
	 * @return bool
	 */
	public static function mark_verified( $key, $email ) {
		global $wpdb;
		self::link( $key, array( 'email' => $email ) );
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::visitors_table(),
			array(
				'verified_hash' => self::email_hash( $email ),
				'verified_at'   => current_time( 'mysql', true ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'visitor_key' => $key )
		);
	}

	/**
	 * Whether the visitor proved they own an email (or is the logged-in user with that email).
	 * Verification lasts 30 days.
	 *
	 * @param string      $email Email.
	 * @param string|null $key   Visitor key (null = this request's).
	 * @return bool
	 */
	public static function is_verified( $email, $key = null ) {
		$email = strtolower( trim( (string) $email ) );
		if ( '' === $email ) {
			return false;
		}
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user && strtolower( (string) $user->user_email ) === $email ) {
				return true;
			}
		}
		$row = self::get( $key );
		if ( ! $row || '' === (string) $row['verified_hash'] || empty( $row['verified_at'] ) ) {
			return false;
		}
		$fresh = strtotime( $row['verified_at'] . ' UTC' ) > time() - 30 * DAY_IN_SECONDS;
		return $fresh && hash_equals( (string) $row['verified_hash'], self::email_hash( $email ) );
	}

	/**
	 * Links the visitor to the user who logs in.
	 *
	 * @param string   $login Login name.
	 * @param \WP_User $user  User.
	 * @return void
	 */
	public static function on_login( $login, $user ) {
		$key = self::key();
		if ( '' !== $key && $user && ! empty( $user->ID ) ) {
			self::link( $key, array( 'user_id' => (int) $user->ID ) );
		}
	}
}
