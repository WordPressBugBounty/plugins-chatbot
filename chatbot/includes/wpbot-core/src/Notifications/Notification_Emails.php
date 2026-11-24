<?php
/**
 * Where WPBot's notification emails go (owner, 20 November 2026): one list of addresses on
 * AI / Settings → Notifications for every feature.
 *
 * - The list is prefilled with WordPress's administration email (Settings → General), the
 *   addresses hosts and modules add (filter qcld_wpbot_core/notification_default_emails, for
 *   example the ChatBot's email) and every administrator's email. The owner can add more.
 * - Once the owner saves the list, WPBot's emails to staff go to it: an email sent by WPBot's
 *   code (see route()) to one of the staff addresses above, or to an address of the saved list,
 *   goes to every address of the list instead. Emails to visitors and customers are untouched.
 *   Until the list is saved, each feature sends where it always did.
 * - Features may also ask for the list directly: qcld_wpbot_core_notification_emails().
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Notifications;

use QuantumCloud\WPBotCore\Diagnostics\Error_Capture;

defined( 'ABSPATH' ) || exit;

/**
 * Notification email addresses.
 */
final class Notification_Emails {

	/**
	 * Option: the saved list (absent until the owner saves it).
	 */
	const OPTION = 'qcld_wpbot_core_notification_emails';

	/**
	 * At most this many addresses.
	 */
	const MAX = 50;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'wp_mail', array( self::class, 'route' ), 20 );
	}

	/**
	 * The saved list, or null when the owner has not saved one.
	 *
	 * @return string[]|null
	 */
	public static function saved() {
		$saved = get_option( self::OPTION, null );
		if ( ! is_array( $saved ) ) {
			return null;
		}
		$list = self::clean( $saved );
		return $list ? $list : null;
	}

	/**
	 * The suggested list: WordPress's administration email, the addresses hosts and modules add,
	 * and every administrator's email.
	 *
	 * @return string[]
	 */
	public static function defaults() {
		$emails = array( (string) get_option( 'admin_email', '' ) );
		/**
		 * Staff addresses WPBot features send notifications to, such as the ChatBot's email in
		 * its General Settings. They prefill the list and their emails follow it once saved.
		 *
		 * @param string[] $emails Addresses.
		 */
		$extra = (array) apply_filters( 'qcld_wpbot_core/notification_default_emails', array() );
		foreach ( $extra as $email ) {
			$emails[] = (string) $email;
		}
		if ( function_exists( 'get_users' ) ) {
			$admins = get_users(
				array(
					'role'   => 'administrator',
					'fields' => array( 'user_email' ),
					'number' => self::MAX,
				)
			);
			foreach ( (array) $admins as $admin ) {
				$emails[] = is_object( $admin ) && isset( $admin->user_email ) ? (string) $admin->user_email : (string) $admin;
			}
		}
		return self::clean( $emails );
	}

	/**
	 * The list notifications go to: the saved list, else the suggested one.
	 *
	 * @return string[]
	 */
	public static function emails() {
		$saved = self::saved();
		$list  = null !== $saved ? $saved : self::defaults();
		/**
		 * Addresses WPBot's notification emails go to.
		 *
		 * @param string[] $list  Addresses.
		 * @param bool     $saved Whether the owner saved the list.
		 */
		return self::clean( (array) apply_filters( 'qcld_wpbot_core/notification_emails', $list, null !== $saved ) );
	}

	/**
	 * Save the list. An empty list goes back to the suggested addresses (and emails go where
	 * each feature sends them).
	 *
	 * @param string[] $emails Addresses.
	 * @return string[] What was saved (empty when the list was cleared).
	 */
	public static function save( array $emails ) {
		$list = self::clean( $emails );
		if ( ! $list ) {
			delete_option( self::OPTION );
			return array();
		}
		update_option( self::OPTION, $list, true );
		return $list;
	}

	/**
	 * Split what the owner typed into valid and invalid addresses.
	 *
	 * @param string|string[] $raw One address per field, or several separated by commas, spaces or lines.
	 * @return array{valid:string[],invalid:string[]}
	 */
	public static function parse( $raw ) {
		$parts   = is_array( $raw ) ? $raw : array( (string) $raw );
		$valid   = array();
		$invalid = array();
		foreach ( $parts as $part ) {
			foreach ( preg_split( '/[\s,;]+/', trim( (string) $part ) ) as $piece ) {
				if ( '' === $piece ) {
					continue;
				}
				$email = self::one( $piece );
				if ( '' === $email ) {
					$invalid[] = sanitize_text_field( $piece );
				} elseif ( ! in_array( $email, $valid, true ) ) {
					$valid[] = $email;
				}
			}
		}
		return array(
			'valid'   => array_slice( $valid, 0, self::MAX ),
			'invalid' => array_slice( array_values( array_unique( $invalid ) ), 0, 20 ),
		);
	}

	/**
	 * Send WPBot's staff emails to the saved list (wp_mail filter).
	 *
	 * @param array $atts to, subject, message, headers, attachments.
	 * @return array
	 */
	public static function route( $atts ) {
		if ( ! is_array( $atts ) || ! isset( $atts['to'] ) ) {
			return $atts;
		}
		$saved = self::saved();
		if ( null === $saved || ! self::sent_by_wpbot() ) {
			return $atts;
		}
		$routed = self::reroute( $atts['to'], $saved, self::defaults() );
		if ( null === $routed ) {
			return $atts;
		}
		/**
		 * Whether this WPBot email goes to the notification list.
		 *
		 * @param bool  $route Yes.
		 * @param array $atts  The email.
		 */
		if ( ! apply_filters( 'qcld_wpbot_core/route_notification', true, $atts ) ) {
			return $atts;
		}
		$atts['to'] = $routed;
		return $atts;
	}

	/**
	 * The new recipients when the email is for staff, else null.
	 *
	 * @param string|string[] $to     Recipients as wp_mail takes them.
	 * @param string[]        $emails The saved list.
	 * @param string[]        $staff  The suggested (staff) addresses.
	 * @return string[]|null
	 */
	public static function reroute( $to, array $emails, array $staff ) {
		$recipients = is_array( $to ) ? $to : explode( ',', (string) $to );
		$staff_keys = array_map( 'strtolower', array_merge( $staff, $emails ) );
		$others     = array();
		$for_staff  = false;
		foreach ( $recipients as $recipient ) {
			$recipient = trim( (string) $recipient );
			if ( '' === $recipient ) {
				continue;
			}
			// "Name <address>" keeps working.
			$address = preg_match( '/<([^>]+)>/', $recipient, $m ) ? $m[1] : $recipient;
			if ( in_array( strtolower( trim( $address ) ), $staff_keys, true ) ) {
				$for_staff = true;
			} else {
				$others[] = $recipient;
			}
		}
		if ( ! $for_staff || ! $emails ) {
			return null;
		}
		return array_values( array_unique( array_merge( $others, $emails ) ) );
	}

	/**
	 * Whether the code that called wp_mail() belongs to WPBot.
	 *
	 * @return bool
	 */
	private static function sent_by_wpbot() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Only file names are read, to tell WPBot's emails apart.
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 ) as $frame ) {
			if ( isset( $frame['function'] ) && 'wp_mail' === $frame['function'] && empty( $frame['class'] ) ) {
				return isset( $frame['file'] ) && Error_Capture::is_own( (string) $frame['file'] );
			}
		}
		return false;
	}

	/**
	 * Valid, unique, lower-case-unique addresses.
	 *
	 * @param array $emails Addresses.
	 * @return string[]
	 */
	private static function clean( array $emails ) {
		$out  = array();
		$seen = array();
		foreach ( $emails as $email ) {
			$email = self::one( (string) $email );
			if ( '' !== $email && ! isset( $seen[ strtolower( $email ) ] ) ) {
				$seen[ strtolower( $email ) ] = true;
				$out[]                        = $email;
			}
		}
		return array_slice( $out, 0, self::MAX );
	}

	/**
	 * One address, or '' when it is not valid.
	 *
	 * @param string $email Address.
	 * @return string
	 */
	private static function one( $email ) {
		$email = sanitize_email( trim( (string) $email ) );
		return '' !== $email && is_email( $email ) ? $email : '';
	}
}
