<?php
/**
 * Link building results: links earned per month by outreach type, guest
 * posts published, collaborations live, referral visits per linking site
 * and partner (Site Insight), and the linked pages' Search Console numbers
 * 28 days before and after each won link.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Report data.
 */
final class Links_Report {

	/**
	 * Report.
	 *
	 * @return array
	 */
	public static function build() {
		global $wpdb;
		$outreach = Schema::table( Schema::OUTREACH );
		$steps    = Schema::table( Schema::STEPS );
		$since    = gmdate( 'Y-m-01 00:00:00', strtotime( '-11 months' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names from constants.
		$rows      = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(s.created_at, '%%Y-%%m') AS month, o.type, COUNT(DISTINCT o.id) AS n FROM {$steps} s JOIN {$outreach} o ON o.id = s.outreach_id
				 WHERE s.kind = 'status' AND ((o.type = 'link_request' AND s.to_status = 'won') OR (o.type = 'guest_post' AND s.to_status = 'published') OR (o.type = 'collaboration' AND s.to_status = 'live')) AND s.created_at >= %s
				 GROUP BY month, o.type ORDER BY month",
				$since
			),
			ARRAY_A
		);
		$by_status = (array) $wpdb->get_results( "SELECT type, status, COUNT(*) AS n FROM {$outreach} GROUP BY type, status", ARRAY_A );
		$won       = (array) $wpdb->get_results( "SELECT id, type, name, host, link_url, target_url, target_post_id, updated_at FROM {$outreach} WHERE (type = 'link_request' AND status = 'won') OR (type = 'guest_post' AND status = 'published') OR (type = 'collaboration' AND status = 'live') ORDER BY updated_at DESC LIMIT 50", ARRAY_A );
		// phpcs:enable
		$months = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$month            = gmdate( 'Y-m', strtotime( "first day of -{$i} months" ) );
			$months[ $month ] = array(
				'month'         => $month,
				'link_request'  => 0,
				'guest_post'    => 0,
				'collaboration' => 0,
			);
		}
		foreach ( $rows as $row ) {
			if ( isset( $months[ $row['month'] ] ) ) {
				$months[ $row['month'] ][ $row['type'] ] = (int) $row['n'];
			}
		}
		$pipeline = array();
		foreach ( $by_status as $row ) {
			$pipeline[ $row['type'] ][ $row['status'] ] = (int) $row['n'];
		}
		$referrals = array();
		foreach ( Referrals::sources( 90 ) as $ref ) {
			$referrals[ $ref['host'] ] = $ref['sessions'];
		}
		$earned = array();
		foreach ( $won as $row ) {
			$earned[] = array(
				'id'       => (int) $row['id'],
				'type'     => $row['type'],
				'name'     => $row['name'],
				'link_url' => $row['link_url'],
				'date'     => substr( $row['updated_at'], 0, 10 ),
				'visits'   => isset( $referrals[ Link_Finder::host( $row['link_url'] ) ] ) ? $referrals[ Link_Finder::host( $row['link_url'] ) ] : null,
				'search'   => self::before_after( (int) $row['target_post_id'], $row['target_url'], substr( $row['updated_at'], 0, 10 ) ),
			);
		}
		$exchanges = Exchanges::all();
		$partners  = array();
		foreach ( $exchanges['items'] as $item ) {
			$host       = Link_Finder::host( $item['their_url'] );
			$partners[] = array(
				'partner' => $item['partner'],
				'status'  => $item['status'],
				'visits'  => isset( $referrals[ $host ] ) ? $referrals[ $host ] : null,
			);
		}
		$links = Link_Store::query( array( 'limit' => 1 ) );
		return array(
			'months'      => array_values( $months ),
			'pipeline'    => $pipeline,
			'earned'      => $earned,
			'guest_posts' => isset( $pipeline['guest_post']['published'] ) ? $pipeline['guest_post']['published'] : 0,
			'collabs'     => isset( $pipeline['collaboration']['live'] ) ? $pipeline['collaboration']['live'] : 0,
			'links'       => $links['counts'],
			'sites'       => $links['sites'],
			'exchanges'   => array(
				'counts'   => $exchanges['counts'],
				'partners' => $partners,
			),
			'insights'    => Referrals::available(),
		);
	}

	/**
	 * The linked page's clicks and position 28 days before and after a date.
	 *
	 * @param int    $post_id Post.
	 * @param string $url     Page URL.
	 * @param string $day     Date (Y-m-d).
	 * @return array|null before, after (clicks, position).
	 */
	public static function before_after( $post_id, $url, $day ) {
		$url_id = $post_id ? Data_Store::url_for_post( $post_id ) : 0;
		if ( ! $url_id && '' !== $url ) {
			$post_id = url_to_postid( $url );
			$url_id  = $post_id ? Data_Store::url_for_post( $post_id ) : 0;
		}
		$last = Data_Store::coverage()['last'];
		if ( ! $url_id || '' === $last ) {
			return null;
		}
		$before = Data_Store::totals( gmdate( 'Y-m-d', strtotime( $day . ' -28 days' ) ), gmdate( 'Y-m-d', strtotime( $day . ' -1 day' ) ), $url_id, 0 );
		$end    = min( $last, gmdate( 'Y-m-d', strtotime( $day . ' +27 days' ) ) );
		if ( $end < $day ) {
			return array(
				'before' => self::pick( $before ),
				'after'  => null,
			);
		}
		return array(
			'before' => self::pick( $before ),
			'after'  => self::pick( Data_Store::totals( $day, $end, $url_id, 0 ) ),
		);
	}

	/**
	 * Clicks and position.
	 *
	 * @param array $totals Totals.
	 * @return array
	 */
	private static function pick( array $totals ) {
		return array(
			'clicks'   => (int) $totals['clicks'],
			'position' => isset( $totals['position'] ) ? (float) $totals['position'] : 0.0,
		);
	}
}
