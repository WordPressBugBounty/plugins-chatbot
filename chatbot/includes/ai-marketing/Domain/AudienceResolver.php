<?php
/**
 * Turns campaign audience settings into sendable contact ids.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Audience = sendable contacts (subscribed|customer) matching send_to all|lists|tags,
 * minus exclude_lists/exclude_tags. EXISTS sub-queries keep results de-duplicated.
 */
class AudienceResolver {

	const SENDABLE = array( 'subscribed', 'customer' );

	/**
	 * Normalise settings: send_to (all|lists|tags), lists, tags, exclude_lists, exclude_tags (int[]).
	 * Accepts list_ids/tag_ids aliases.
	 *
	 * @param array $settings Raw campaign settings.
	 * @return array
	 */
	public static function normalize( array $settings ) {
		$send_to = isset( $settings['send_to'] ) ? (string) $settings['send_to'] : '';
		if ( ! in_array( $send_to, array( 'all', 'lists', 'tags' ), true ) ) {
			$send_to = 'all';
		}
		$lists = isset( $settings['lists'] ) ? $settings['lists'] : ( isset( $settings['list_ids'] ) ? $settings['list_ids'] : array() );
		$tags  = isset( $settings['tags'] ) ? $settings['tags'] : ( isset( $settings['tag_ids'] ) ? $settings['tag_ids'] : array() );
		return array(
			'send_to'       => $send_to,
			'lists'         => Repository::ids( $lists ),
			'tags'          => Repository::ids( $tags ),
			'exclude_lists' => Repository::ids( isset( $settings['exclude_lists'] ) ? $settings['exclude_lists'] : array() ),
			'exclude_tags'  => Repository::ids( isset( $settings['exclude_tags'] ) ? $settings['exclude_tags'] : array() ),
		);
	}

	/**
	 * WHERE clause (on alias c = contacts) selecting the audience. Contains only ints/constants.
	 *
	 * @param array $settings Campaign settings.
	 * @return string
	 */
	public function where( array $settings ) {
		$s       = self::normalize( $settings );
		$members = Schema::table( 'group_members' );
		$where   = array( "c.status IN ('" . implode( "','", self::SENDABLE ) . "')" );

		if ( 'lists' === $s['send_to'] || 'tags' === $s['send_to'] ) {
			$ids = 'lists' === $s['send_to'] ? $s['lists'] : $s['tags'];
			if ( empty( $ids ) ) {
				return '1=0';
			}
			$where[] = "EXISTS (SELECT 1 FROM {$members} gm WHERE gm.contact_id = c.id AND gm.group_id IN (" . implode( ',', $ids ) . '))';
		}
		$exclude = array_merge( $s['exclude_lists'], $s['exclude_tags'] );
		if ( ! empty( $exclude ) ) {
			$where[] = "NOT EXISTS (SELECT 1 FROM {$members} gx WHERE gx.contact_id = c.id AND gx.group_id IN (" . implode( ',', $exclude ) . '))';
		}
		return implode( ' AND ', $where );
	}

	/**
	 * Number of contacts in the audience.
	 *
	 * @param array $settings Campaign settings.
	 * @return int
	 */
	public function count( array $settings ) {
		global $wpdb;
		$contacts = Schema::table( 'contacts' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$contacts} c WHERE " . $this->where( $settings ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- ints only.
	}

	/**
	 * Audience contact ids (ascending).
	 *
	 * @param array $settings Campaign settings.
	 * @param int   $limit    Max ids (0 = all).
	 * @return int[]
	 */
	public function ids( array $settings, $limit = 0 ) {
		global $wpdb;
		$contacts = Schema::table( 'contacts' );
		$sql      = "SELECT c.id FROM {$contacts} c WHERE " . $this->where( $settings ) . ' ORDER BY c.id ASC';
		if ( (int) $limit > 0 ) {
			$sql .= sprintf( ' LIMIT %d', (int) $limit );
		}
		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Queue one `messages` row per audience contact with a single INSERT IGNORE … SELECT.
	 * Re-running is safe (UNIQUE campaign_id+contact_id).
	 *
	 * @param int   $campaign_id Campaign id.
	 * @param array $settings    Campaign settings.
	 * @return int Rows inserted.
	 */
	public function enqueue_for_campaign( $campaign_id, array $settings ) {
		global $wpdb;
		$contacts = Schema::table( 'contacts' );
		$messages = Schema::table( 'messages' );
		$sql      = $wpdb->prepare(
			"INSERT IGNORE INTO {$messages} (campaign_id, contact_id, status, queued_at) SELECT %d, c.id, 'queued', %s FROM {$contacts} c WHERE " . $this->where( $settings ) . ' ORDER BY c.id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			(int) $campaign_id,
			Dates::now()
		);
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->rows_affected;
	}
}
