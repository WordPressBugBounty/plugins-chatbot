<?php
/**
 * Dashboard numbers: contacts by status and source, growth over 30 days,
 * recent campaigns with open and click rates, the ChatBot list.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Insights;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Stats.
 */
final class Stats {

	/**
	 * Overview.
	 *
	 * @return array
	 */
	public static function overview() {
		global $wpdb;
		$contacts  = Schema::table( 'contacts' );
		$messages  = Schema::table( 'messages' );
		$campaigns = Schema::table( 'campaigns' );
		$since     = gmdate( 'Y-m-d 00:00:00', time() - 29 * DAY_IN_SECONDS );
		$growth    = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) AS day, COUNT(*) AS n FROM `{$contacts}` WHERE created_at >= %s GROUP BY DATE(created_at)", $since ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			$growth[ (string) $row['day'] ] = (int) $row['n'];
		}
		$days = array();
		for ( $i = 29; $i >= 0; $i-- ) {
			$day    = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$days[] = array(
				'day' => $day,
				'n'   => isset( $growth[ $day ] ) ? $growth[ $day ] : 0,
			);
		}
		$sources = array();
		foreach ( (array) $wpdb->get_results( "SELECT source, COUNT(*) AS n FROM `{$contacts}` GROUP BY source ORDER BY n DESC LIMIT 8", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			$sources[ '' !== (string) $row['source'] ? (string) $row['source'] : 'other' ] = (int) $row['n'];
		}
		$mail   = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(status='sent') AS sent, SUM(opens>0) AS opened, SUM(clicks>0) AS clicked FROM `{$messages}` WHERE sent_at >= %s", $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$recent = (array) $wpdb->get_results( "SELECT c.id, c.name, c.status, c.finished_at, c.started_at, (SELECT COUNT(*) FROM `{$messages}` m WHERE m.campaign_id = c.id AND m.status = 'sent') AS sent, (SELECT COUNT(*) FROM `{$messages}` m WHERE m.campaign_id = c.id AND m.opens > 0) AS opened, (SELECT COUNT(*) FROM `{$messages}` m WHERE m.campaign_id = c.id AND m.clicks > 0) AS clicked FROM `{$campaigns}` c WHERE c.status IN ('sent','sending') ORDER BY c.id DESC LIMIT 5", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
		foreach ( $recent as $i => $row ) {
			foreach ( array( 'id', 'sent', 'opened', 'clicked' ) as $key ) {
				$recent[ $i ][ $key ] = (int) $row[ $key ];
			}
		}
		return array(
			'contacts' => Contacts::counts(),
			'growth'   => $days,
			'sources'  => $sources,
			'mail_30d' => array_map( 'intval', (array) $mail ),
			'recent'   => $recent,
		);
	}

	/**
	 * Numbers for a period (reports and anomaly checks).
	 *
	 * @param int $from Unix time (start).
	 * @param int $to   Unix time (end).
	 * @return array{signups:int,unsubscribes:int,sent:int,opened:int,clicked:int,orders:int,revenue:float}
	 */
	public static function period( $from, $to ) {
		global $wpdb;
		$contacts = Schema::table( 'contacts' );
		$messages = Schema::table( 'messages' );
		$events   = Schema::table( 'events' );
		$a        = gmdate( 'Y-m-d H:i:s', (int) $from );
		$b        = gmdate( 'Y-m-d H:i:s', (int) $to );
		$mail     = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS sent, SUM(opens>0) AS opened, SUM(clicks>0) AS clicked FROM `{$messages}` WHERE status IN ('sent','unknown') AND sent_at >= %s AND sent_at < %s", $a, $b ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$orders   = (array) $wpdb->get_col( $wpdb->prepare( "SELECT data FROM `{$events}` WHERE type = 'order' AND created_at >= %s AND created_at < %s", $a, $b ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$revenue  = 0.0;
		foreach ( $orders as $data ) {
			$data     = json_decode( (string) $data, true );
			$revenue += is_array( $data ) && isset( $data['total'] ) ? (float) $data['total'] : 0.0;
		}
		return array(
			'signups'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$contacts}` WHERE status = 'subscribed' AND created_at >= %s AND created_at < %s", $a, $b ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			'unsubscribes' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$contacts}` WHERE unsubscribed_at >= %s AND unsubscribed_at < %s", $a, $b ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			'sent'         => (int) ( $mail ? $mail['sent'] : 0 ),
			'opened'       => (int) ( $mail ? $mail['opened'] : 0 ),
			'clicked'      => (int) ( $mail ? $mail['clicked'] : 0 ),
			'orders'       => count( $orders ),
			'revenue'      => round( $revenue, 2 ),
		);
	}

	/**
	 * Revenue from attributed orders per campaign and journey in a period.
	 *
	 * @param int $from Unix time.
	 * @param int $to   Unix time.
	 * @return array[] kind (campaign|journey), id, name, orders, revenue.
	 */
	public static function revenue( $from, $to ) {
		global $wpdb;
		$events = Schema::table( 'events' );
		$rows   = (array) $wpdb->get_col( $wpdb->prepare( "SELECT data FROM `{$events}` WHERE type = 'order' AND created_at >= %s AND created_at < %s", gmdate( 'Y-m-d H:i:s', (int) $from ), gmdate( 'Y-m-d H:i:s', (int) $to ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$out    = array();
		foreach ( $rows as $data ) {
			$data = json_decode( (string) $data, true );
			if ( ! is_array( $data ) || ( empty( $data['campaign_id'] ) && empty( $data['journey_id'] ) ) ) {
				continue;
			}
			$kind = ! empty( $data['campaign_id'] ) ? 'campaign' : 'journey';
			$id   = (int) ( 'campaign' === $kind ? $data['campaign_id'] : $data['journey_id'] );
			$key  = $kind . ':' . $id;
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array(
					'kind'    => $kind,
					'id'      => $id,
					'name'    => isset( $data['name'] ) ? (string) $data['name'] : '',
					'orders'  => 0,
					'revenue' => 0.0,
				);
			}
			++$out[ $key ]['orders'];
			$out[ $key ]['revenue'] += isset( $data['total'] ) ? (float) $data['total'] : 0.0;
		}
		usort(
			$out,
			static function ( $x, $y ) {
				return $y['revenue'] <=> $x['revenue'];
			}
		);
		return array_values( $out );
	}

	/**
	 * The email a contact clicked last within a window (for attribution).
	 *
	 * @param int $contact_id Contact id.
	 * @param int $days       Window.
	 * @return array|null message_id, campaign_id, journey_id, clicked_at.
	 */
	public static function last_click( $contact_id, $days = 7 ) {
		global $wpdb;
		$events   = Schema::table( 'events' );
		$messages = Schema::table( 'messages' );
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT e.message_id, e.created_at, m.campaign_id, m.journey_id FROM `{$events}` e JOIN `{$messages}` m ON m.id = e.message_id WHERE e.contact_id = %d AND e.type = 'click' AND e.created_at >= %s ORDER BY e.id DESC LIMIT 1", (int) $contact_id, gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
		return $row ? array(
			'message_id'  => (int) $row['message_id'],
			'campaign_id' => (int) $row['campaign_id'],
			'journey_id'  => (int) $row['journey_id'],
			'clicked_at'  => (string) $row['created_at'],
		) : null;
	}

	/**
	 * When a contact opens and clicks: counts per site hour (0–23) over a window.
	 *
	 * @param int $contact_id Contact id.
	 * @param int $days       Window.
	 * @return array<int,int>
	 */
	public static function open_hours( $contact_id, $days = 180 ) {
		global $wpdb;
		$events = Schema::table( 'events' );
		$rows   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT HOUR(created_at) AS h, COUNT(*) AS n FROM `{$events}` WHERE contact_id = %d AND type IN ('open','click') AND created_at >= %s GROUP BY HOUR(created_at)", (int) $contact_id, gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$offset = (int) round( (float) get_option( 'gmt_offset', 0 ) );
		$out    = array_fill( 0, 24, 0 );
		foreach ( $rows as $row ) {
			$out[ ( (int) $row['h'] + $offset + 24 ) % 24 ] += (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Numbers per email step of a journey over a window: sent, opened, clicked,
	 * and unsubscribed within three days of the email.
	 *
	 * @param int $journey_id Journey id.
	 * @param int $days       Window.
	 * @return array<string,array{sent:int,opened:int,clicked:int,unsubscribed:int}>
	 */
	public static function journey_steps( $journey_id, $days = 30 ) {
		global $wpdb;
		$messages = Schema::table( 'messages' );
		$contacts = Schema::table( 'contacts' );
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( "SELECT m.step_key, COUNT(*) AS sent, SUM(m.opens > 0) AS opened, SUM(m.clicks > 0) AS clicked, SUM(c.unsubscribed_at IS NOT NULL AND c.unsubscribed_at >= m.sent_at AND c.unsubscribed_at < DATE_ADD(m.sent_at, INTERVAL 3 DAY)) AS unsubscribed FROM `{$messages}` m LEFT JOIN `{$contacts}` c ON c.id = m.contact_id WHERE m.journey_id = %d AND m.status = 'sent' AND m.sent_at >= %s GROUP BY m.step_key", (int) $journey_id, gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
		$out      = array();
		foreach ( $rows as $row ) {
			$out[ (string) $row['step_key'] ] = array(
				'sent'         => (int) $row['sent'],
				'opened'       => (int) $row['opened'],
				'clicked'      => (int) $row['clicked'],
				'unsubscribed' => (int) $row['unsubscribed'],
			);
		}
		return $out;
	}

	/**
	 * Contacts with attributed or recorded orders in a window, with their totals.
	 *
	 * @param int $days Window.
	 * @return array<int,array{orders:int,revenue:float}> contact id => totals.
	 */
	public static function buyers( $days = 365 ) {
		global $wpdb;
		$events = Schema::table( 'events' );
		$rows   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT contact_id, data FROM `{$events}` WHERE type = 'order' AND created_at >= %s LIMIT 50000", gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$out    = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['contact_id'];
			if ( ! $id ) {
				continue;
			}
			$data = json_decode( (string) $row['data'], true );
			if ( ! isset( $out[ $id ] ) ) {
				$out[ $id ] = array(
					'orders'  => 0,
					'revenue' => 0.0,
				);
			}
			++$out[ $id ]['orders'];
			$out[ $id ]['revenue'] += is_array( $data ) && isset( $data['total'] ) ? (float) $data['total'] : 0.0;
		}
		return $out;
	}

	/**
	 * The most clicked links in emails over a window.
	 *
	 * @param int $days  Window.
	 * @param int $limit Max links.
	 * @return array[] url, clicks, people.
	 */
	public static function top_links( $days = 30, $limit = 20 ) {
		global $wpdb;
		$events = Schema::table( 'events' );
		$rows   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT contact_id, data FROM `{$events}` WHERE type = 'click' AND created_at >= %s ORDER BY id DESC LIMIT 20000", gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$links  = array();
		foreach ( $rows as $row ) {
			$data = json_decode( (string) $row['data'], true );
			$url  = is_array( $data ) && isset( $data['url'] ) ? (string) $data['url'] : '';
			if ( '' === $url ) {
				continue;
			}
			if ( ! isset( $links[ $url ] ) ) {
				$links[ $url ] = array(
					'url'    => $url,
					'clicks' => 0,
					'people' => array(),
				);
			}
			++$links[ $url ]['clicks'];
			$links[ $url ]['people'][ (int) $row['contact_id'] ] = true;
		}
		foreach ( $links as $url => $link ) {
			$links[ $url ]['people'] = count( $link['people'] );
		}
		usort(
			$links,
			static function ( $x, $y ) {
				return array( $y['people'], $y['clicks'] ) <=> array( $x['people'], $x['clicks'] );
			}
		);
		return array_slice( array_values( $links ), 0, max( 1, (int) $limit ) );
	}

	/**
	 * Rate in percent with one decimal (pure; unit tested).
	 *
	 * @param int $part  Part.
	 * @param int $whole Whole.
	 * @return float
	 */
	public static function rate( $part, $whole ) {
		return $whole > 0 ? round( 100 * $part / $whole, 1 ) : 0.0;
	}
}
