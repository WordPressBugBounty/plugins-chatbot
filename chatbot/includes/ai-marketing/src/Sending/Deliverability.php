<?php
/**
 * Bounces and complaints reported by a sending provider: the contact stops
 * receiving email (status bounced or complained) and the address goes on the
 * suppression list. Three soft bounces in 30 days count as a hard bounce.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Sending;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Audience\Suppression;
use QuantumCloud\AiMarketing\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Deliverability events.
 */
final class Deliverability {

	const KINDS      = array( 'hard_bounce', 'soft_bounce', 'complaint' );
	const SOFT_LIMIT = 3;

	/**
	 * Record a bounce or complaint.
	 *
	 * @param string $kind        hard_bounce, soft_bounce or complaint.
	 * @param string $email       Recipient address ('' when only the provider id is known).
	 * @param string $provider_id The provider's message id ('' when unknown).
	 * @param string $source      Provider: ses, brevo, smtp ...
	 * @param string $detail      Provider detail (diagnostic text).
	 * @return array{contact_id:int,result:string} result: bounced, complained, soft, unknown or ignored.
	 */
	public static function record( $kind, $email, $provider_id = '', $source = '', $detail = '' ) {
		global $wpdb;
		$out = array(
			'contact_id' => 0,
			'result'     => 'ignored',
		);
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return $out;
		}
		$messages   = Schema::table( 'messages' );
		$message_id = 0;
		$contact    = null;
		if ( '' !== (string) $provider_id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, contact_id FROM `{$messages}` WHERE provider_id = %s ORDER BY id DESC LIMIT 1", (string) $provider_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			if ( $row ) {
				$message_id = (int) $row['id'];
				$contact    = Contacts::get( (int) $row['contact_id'] );
			}
		}
		if ( ! $contact && '' !== (string) $email ) {
			$contact = Contacts::by_email( (string) $email );
		}
		$address = $contact && '' !== (string) $contact['email'] ? (string) $contact['email'] : (string) $email;
		if ( ! $contact ) {
			if ( 'soft_bounce' !== $kind && '' !== $address ) {
				Suppression::add( $address, 'complaint' === $kind ? 'complaint' : 'bounce', $source );
			}
			$out['result'] = 'unknown';
			return $out;
		}
		$id                = (int) $contact['id'];
		$out['contact_id'] = $id;
		$data              = array_filter(
			array(
				'source' => sanitize_key( (string) $source ),
				'detail' => substr( sanitize_text_field( (string) $detail ), 0, 300 ),
			)
		);
		if ( 'soft_bounce' === $kind ) {
			Links::event( $id, $message_id, 'soft_bounce', $data );
			if ( self::soft_count( $id ) < self::SOFT_LIMIT ) {
				$out['result'] = 'soft';
				self::fire( $kind, $contact, $out['result'] );
				return $out;
			}
			$kind = 'hard_bounce';
		}
		$complaint = 'complaint' === $kind;
		Links::event( $id, $message_id, $complaint ? 'complaint' : 'bounce', $data );
		Contacts::mark( $id, $complaint ? 'complained' : 'bounced', $complaint ? 'complaint' : 'bounce' );
		if ( '' !== $address ) {
			Suppression::add( $address, $complaint ? 'complaint' : 'bounce', $source );
		}
		$out['result'] = $complaint ? 'complained' : 'bounced';
		self::fire( $kind, $contact, $out['result'] );
		return $out;
	}

	/**
	 * Sent, bounces and complaints over the last days, with rates in percent (two decimals).
	 *
	 * @param int $days Days.
	 * @return array{sent:int,bounces:int,complaints:int,bounce_rate:float,complaint_rate:float}
	 */
	public static function stats( $days = 7 ) {
		global $wpdb;
		$since      = gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS );
		$messages   = Schema::table( 'messages' );
		$events     = Schema::table( 'events' );
		$sent       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$messages}` WHERE status IN ('sent','unknown') AND sent_at >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$bounces    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$events}` WHERE type = 'bounce' AND created_at >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$complaints = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$events}` WHERE type = 'complaint' AND created_at >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return array(
			'sent'           => $sent,
			'bounces'        => $bounces,
			'complaints'     => $complaints,
			'bounce_rate'    => self::rate( $bounces, $sent ),
			'complaint_rate' => self::rate( $complaints, $sent ),
		);
	}

	/**
	 * Percent with two decimals (pure; unit tested).
	 *
	 * @param int $part  Part.
	 * @param int $whole Whole.
	 * @return float
	 */
	public static function rate( $part, $whole ) {
		return $whole > 0 ? round( 100 * $part / $whole, 2 ) : 0.0;
	}

	/**
	 * Soft bounces of a contact in the last 30 days.
	 *
	 * @param int $contact_id Contact id.
	 * @return int
	 */
	private static function soft_count( $contact_id ) {
		global $wpdb;
		$events = Schema::table( 'events' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$events}` WHERE contact_id = %d AND type = 'soft_bounce' AND created_at >= %s", (int) $contact_id, gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Fire the action.
	 *
	 * @param string $kind    Kind.
	 * @param array  $contact Contact.
	 * @param string $result  Result.
	 * @return void
	 */
	private static function fire( $kind, array $contact, $result ) {
		/**
		 * A bounce or complaint was recorded.
		 *
		 * @param string $kind    hard_bounce, soft_bounce or complaint.
		 * @param array  $contact Contact (before the change).
		 * @param string $result  bounced, complained or soft.
		 */
		do_action( 'qcld_wpbot_marketing/deliverability_event', $kind, $contact, $result );
	}
}
