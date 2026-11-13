<?php
/**
 * Lead capture (email and phone subscription), feedback (like/dislike on a
 * message) and message reports. Same tables as today: wpbot_subscription and
 * wpbot_chat_report. Callers apply the rate limits.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Leads;

use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Leads, feedback and reports.
 */
final class Leads {

	/**
	 * Save a lead. An existing subscriber is never changed, except that a missing phone is filled in.
	 *
	 * @param array $lead name, email, phone, url, user_agent, language.
	 * @return array { status: success|fail, new: bool, msg: string }
	 */
	public static function subscribe( array $lead ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpbot_subscription';
		$name  = isset( $lead['name'] ) ? substr( sanitize_text_field( (string) $lead['name'] ), 0, 100 ) : '';
		$email = isset( $lead['email'] ) ? sanitize_email( (string) $lead['email'] ) : '';
		$phone = isset( $lead['phone'] ) ? substr( preg_replace( '/[^0-9+()\-. ]/', '', (string) $lead['phone'] ), 0, 40 ) : '';
		$url   = isset( $lead['url'] ) ? esc_url_raw( (string) $lead['url'] ) : '';
		$agent = isset( $lead['user_agent'] ) ? substr( sanitize_text_field( (string) $lead['user_agent'] ), 0, 500 ) : '';
		$lang  = isset( $lead['language'] ) ? (string) $lead['language'] : '';
		$email = is_email( $email ) ? $email : '';
		$fail  = array(
			'status' => 'fail',
			'new'    => false,
			'msg'    => '',
		);
		if ( '' === $email && '' === trim( $phone ) ) {
			return $fail;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$existing = '' !== $email ? $wpdb->get_row( $wpdb->prepare( 'SELECT id, phone FROM %i WHERE email = %s', $table, $email ) ) : null;
		if ( $existing ) {
			if ( '' !== trim( $phone ) && '' === (string) $existing->phone ) {
				$wpdb->update( $table, array( 'phone' => $phone ), array( 'id' => (int) $existing->id ), array( '%s' ), array( '%d' ) );
			}
			return array(
				'status' => 'success',
				'new'    => false,
				'msg'    => Options::text( 'qlcd_wp_email_already_subscribe', $lang, __( 'You have already subscribed.', 'chatbot' ) ),
			);
		}
		$row = array(
			'date'       => current_time( 'mysql' ),
			'name'       => $name,
			'email'      => $email,
			'url'        => $url,
			'user_agent' => $agent,
			'phone'      => trim( $phone ),
		);
		$ok  = $wpdb->insert( $table, $row );
		// phpcs:enable
		if ( ! $ok ) {
			return $fail;
		}

		/** This action is documented in the legacy subscription handler. */
		do_action( 'qcld_mailing_list_subscription_success', $name, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- existing public hook.
		/**
		 * A lead was captured in the chat.
		 *
		 * @param array $lead name, email, phone, url.
		 */
		do_action(
			'qcld_wpbot_chat/lead_captured',
			array(
				'name'  => $name,
				'email' => $email,
				'phone' => $phone,
				'url'   => $url,
			)
		);
		return array(
			'status' => 'success',
			'new'    => true,
			'msg'    => Options::text( 'qlcd_wp_email_subscription_success', $lang, __( 'Thank you for subscribing.', 'chatbot' ) ),
		);
	}

	/**
	 * Save feedback on a bot message.
	 *
	 * @param array $feedback message, feedback (like|dislike), conversation_id, meta_info.
	 * @return bool
	 */
	public static function feedback( array $feedback ) {
		global $wpdb;
		$value = isset( $feedback['feedback'] ) ? sanitize_key( (string) $feedback['feedback'] ) : '';
		if ( ! in_array( $value, array( 'like', 'dislike' ), true ) ) {
			return false;
		}
		return false !== $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wpbot_chat_report',
			array(
				'user_id'         => get_current_user_id(),
				'conversation_id' => isset( $feedback['conversation_id'] ) ? absint( $feedback['conversation_id'] ) : 0,
				'message'         => isset( $feedback['message'] ) ? self::clip( sanitize_textarea_field( (string) $feedback['message'] ), 5000 ) : '',
				'feedback'        => $value,
				'meta_info'       => isset( $feedback['meta_info'] ) ? self::clip( sanitize_textarea_field( (string) $feedback['meta_info'] ), 2000 ) : '',
				'created_at'      => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Save a report on a bot message and email the site admin (at most one email per 10 minutes per visitor).
	 *
	 * @param array  $report  message, report_text, email.
	 * @param string $visitor Visitor key for the email throttle.
	 * @return bool
	 */
	public static function report( array $report, $visitor ) {
		global $wpdb;
		$message = isset( $report['message'] ) ? self::clip( sanitize_textarea_field( (string) $report['message'] ), 5000 ) : '';
		$text    = isset( $report['report_text'] ) ? self::clip( sanitize_textarea_field( (string) $report['report_text'] ), 2000 ) : '';
		$email   = isset( $report['email'] ) ? sanitize_email( (string) $report['email'] ) : '';
		if ( '' === $message && '' === $text ) {
			return false;
		}
		$ok       = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wpbot_chat_report',
			array(
				'user_id'   => get_current_user_id(),
				'message'   => $message,
				'meta_info' => maybe_serialize(
					array(
						'email'       => is_email( $email ) ? $email : '',
						'report_text' => $text,
					)
				),
			),
			array( '%d', '%s', '%s' )
		);
		$throttle = 'qcld_wpbot_chat_report_mail_' . md5( (string) $visitor );
		if ( $ok && ! get_transient( $throttle ) ) {
			set_transient( $throttle, 1, 10 * MINUTE_IN_SECONDS );
			wp_mail(
				get_option( 'admin_email' ),
				__( 'New Chat Report', 'chatbot' ),
				sprintf( "Reported Message:\n%s\n\nReport Text:\n%s\n\nUser Email: %s", $message, $text, is_email( $email ) ? $email : '' )
			);
		}
		return false !== $ok;
	}

	/**
	 * Cut text.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function clip( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}
}
