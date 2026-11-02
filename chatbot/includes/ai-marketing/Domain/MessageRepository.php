<?php
/**
 * Messages (send queue + per-recipient delivery stats).
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Jobs\Scheduler;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * SQL for wpbot_crm_messages. Status: queued|sent|failed|skipped.
 */
class MessageRepository extends Repository {

	/**
	 * Table suffix.
	 *
	 * @var string
	 */
	protected $table_name = 'messages';

	/**
	 * Writable columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'campaign_id', 'automation_id', 'step_key', 'contact_id', 'status', 'error', 'queued_at', 'sent_at', 'first_open_at', 'opens', 'first_click_at', 'clicks' );

	/**
	 * Integer columns.
	 *
	 * @var string[]
	 */
	protected $ints = array( 'campaign_id', 'automation_id', 'contact_id', 'opens', 'clicks' );

	/**
	 * No created/updated columns.
	 *
	 * @var string[]
	 */
	protected $timestamps = array();

	/**
	 * Insert one message (e.g. an automation email). queued_at defaults to now.
	 *
	 * @param array $data Columns.
	 * @return int
	 */
	public function insert( array $data ) {
		$data = wp_parse_args(
			$data,
			array(
				'status'    => 'queued',
				'queued_at' => Dates::now(),
			)
		);
		$id = parent::insert( $data );
		if ( $id && 'queued' === $data['status'] ) {
			Scheduler::wake();
		}
		return $id;
	}

	/**
	 * Queue many messages in one statement (duplicates per campaign+contact are ignored).
	 *
	 * @param array[] $rows Each: contact_id (required), campaign_id, automation_id, step_key.
	 * @return int Rows inserted.
	 */
	public function enqueue( array $rows ) {
		global $wpdb;
		$now    = Dates::now();
		$values = array();
		foreach ( $rows as $row ) {
			$contact_id = isset( $row['contact_id'] ) ? (int) $row['contact_id'] : 0;
			if ( $contact_id <= 0 ) {
				continue;
			}
			$campaign   = empty( $row['campaign_id'] ) ? 'NULL' : (string) (int) $row['campaign_id'];
			$automation = empty( $row['automation_id'] ) ? 'NULL' : (string) (int) $row['automation_id'];
			$step       = ( isset( $row['step_key'] ) && '' !== (string) $row['step_key'] ) ? $wpdb->prepare( '%s', substr( (string) $row['step_key'], 0, 40 ) ) : 'NULL';
			$values[]   = $wpdb->prepare( "({$campaign}, {$automation}, {$step}, %d, 'queued', %s)", $contact_id, $now ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ints/prepared fragments.
		}
		if ( empty( $values ) ) {
			return 0;
		}
		$table    = $this->table();
		$inserted = 0;
		foreach ( array_chunk( $values, 500 ) as $chunk ) {
			$wpdb->query( "INSERT IGNORE INTO {$table} (campaign_id, automation_id, step_key, contact_id, status, queued_at) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			$inserted += (int) $wpdb->rows_affected;
		}
		if ( $inserted > 0 ) {
			Scheduler::wake();
		}
		return $inserted;
	}

	/**
	 * Next queued messages to send, oldest first. Campaign messages are only returned while
	 * their campaign is `sending`; automation messages always.
	 *
	 * Does not change status: callers must hold the Jobs\Scheduler lock (single runner)
	 * and mark each message sent/failed/skipped.
	 *
	 * @param int $limit       Max rows.
	 * @param int $campaign_id Restrict to one campaign (0 = any).
	 * @return array[]
	 */
	public function claim( $limit, $campaign_id = 0 ) {
		global $wpdb;
		$table     = $this->table();
		$campaigns = Schema::table( 'campaigns' );
		$sql       = "SELECT m.* FROM {$table} m LEFT JOIN {$campaigns} c ON c.id = m.campaign_id
			WHERE m.status = 'queued' AND ( m.campaign_id IS NULL OR c.status = 'sending' )";
		$params    = array();
		if ( (int) $campaign_id > 0 ) {
			$sql     .= ' AND m.campaign_id = %d';
			$params[] = (int) $campaign_id;
		}
		$sql     .= ' ORDER BY m.queued_at ASC, m.id ASC LIMIT %d';
		$params[] = max( 1, (int) $limit );
		return $this->hydrate_all( $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Mark a message sent.
	 *
	 * @param int $id Message id.
	 * @return bool
	 */
	public function mark_sent( $id ) {
		return $this->update(
			$id,
			array(
				'status'  => 'sent',
				'sent_at' => Dates::now(),
				'error'   => null,
			)
		);
	}

	/**
	 * Mark a message failed.
	 *
	 * @param int    $id    Message id.
	 * @param string $error Error text.
	 * @return bool
	 */
	public function mark_failed( $id, $error = '' ) {
		return $this->update(
			$id,
			array(
				'status' => 'failed',
				'error'  => substr( (string) $error, 0, 2000 ),
			)
		);
	}

	/**
	 * Mark a message skipped (e.g. contact unsubscribed meanwhile).
	 *
	 * @param int    $id     Message id.
	 * @param string $reason Reason.
	 * @return bool
	 */
	public function mark_skipped( $id, $reason = '' ) {
		return $this->update(
			$id,
			array(
				'status' => 'skipped',
				'error'  => substr( (string) $reason, 0, 2000 ),
			)
		);
	}

	/**
	 * Count messages per status for one campaign, plus opened/clicked recipients.
	 *
	 * @param int $campaign_id Campaign id.
	 * @return array{queued:int,sent:int,failed:int,skipped:int,total:int,opened:int,clicked:int,opens:int,clicks:int}
	 */
	public function counts_for_campaign( $campaign_id ) {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total,
					SUM(status = 'queued') AS queued, SUM(status = 'sent') AS sent,
					SUM(status = 'failed') AS failed, SUM(status = 'skipped') AS skipped,
					SUM(first_open_at IS NOT NULL) AS opened, SUM(first_click_at IS NOT NULL) AS clicked,
					SUM(opens) AS opens, SUM(clicks) AS clicks
				FROM {$table} WHERE campaign_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $campaign_id
			),
			ARRAY_A
		);
		$out = array();
		foreach ( array( 'queued', 'sent', 'failed', 'skipped', 'total', 'opened', 'clicked', 'opens', 'clicks' ) as $key ) {
			$out[ $key ] = isset( $row[ $key ] ) ? (int) $row[ $key ] : 0;
		}
		return $out;
	}

	/**
	 * Sent/opened/clicked recipient counts for many campaigns in one query.
	 *
	 * @param int[] $campaign_ids Campaign ids.
	 * @return array<int,array{sent:int,opened:int,clicked:int,queued:int}>
	 */
	public function stats_for_campaigns( array $campaign_ids ) {
		global $wpdb;
		$ids = self::ids( $campaign_ids );
		$out = array();
		foreach ( $ids as $id ) {
			$out[ $id ] = array(
				'sent'    => 0,
				'opened'  => 0,
				'clicked' => 0,
				'queued'  => 0,
			);
		}
		if ( empty( $ids ) ) {
			return $out;
		}
		$table = $this->table();
		$in    = implode( ',', $ids );
		$rows  = $wpdb->get_results(
			"SELECT campaign_id, SUM(status = 'sent') AS sent, SUM(first_open_at IS NOT NULL) AS opened,
				SUM(first_click_at IS NOT NULL) AS clicked, SUM(status = 'queued') AS queued
			FROM {$table} WHERE campaign_id IN ({$in}) GROUP BY campaign_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ints.
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['campaign_id'] ] = array(
				'sent'    => (int) $row['sent'],
				'opened'  => (int) $row['opened'],
				'clicked' => (int) $row['clicked'],
				'queued'  => (int) $row['queued'],
			);
		}
		return $out;
	}

	/**
	 * Queued messages left for a campaign.
	 *
	 * @param int $campaign_id Campaign id.
	 * @return int
	 */
	public function remaining_for_campaign( $campaign_id ) {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d AND status = 'queued'", (int) $campaign_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Messages sent since a UTC datetime.
	 *
	 * @param string $since_utc      MySQL datetime (UTC).
	 * @param bool   $campaigns_only Count only campaign messages (not automations).
	 * @return int
	 */
	public function count_sent_since( $since_utc, $campaigns_only = true ) {
		global $wpdb;
		$table = $this->table();
		$extra = $campaigns_only ? ' AND campaign_id IS NOT NULL' : '';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'sent' AND sent_at >= %s{$extra}", (string) $since_utc ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Messages sent in [from, to).
	 *
	 * @param string $from_utc From (UTC).
	 * @param string $to_utc   To (UTC).
	 * @return int
	 */
	public function count_sent_between( $from_utc, $to_utc ) {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'sent' AND sent_at >= %s AND sent_at < %s", (string) $from_utc, (string) $to_utc ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Register an open.
	 *
	 * @param int $id Message id.
	 * @return bool|null True on first open, false on a repeat, null when the message does not exist.
	 */
	public function record_open( $id ) {
		return $this->record_interaction( $id, 'opens', 'first_open_at' );
	}

	/**
	 * Register a click.
	 *
	 * @param int $id Message id.
	 * @return bool|null True on first click, false on a repeat, null when the message does not exist.
	 */
	public function record_click( $id ) {
		return $this->record_interaction( $id, 'clicks', 'first_click_at' );
	}

	/**
	 * Delete all messages of a campaign.
	 *
	 * @param int $campaign_id Campaign id.
	 * @return int Rows deleted.
	 */
	public function delete_for_campaign( $campaign_id ) {
		global $wpdb;
		return (int) $wpdb->delete( $this->table(), array( 'campaign_id' => (int) $campaign_id ), array( '%d' ) );
	}

	/**
	 * Increment a counter and set its first-* timestamp once.
	 *
	 * @param int    $id      Message id.
	 * @param string $counter opens|clicks.
	 * @param string $first   first_open_at|first_click_at.
	 * @return bool|null
	 */
	private function record_interaction( $id, $counter, $first ) {
		global $wpdb;
		$message = $this->find( $id );
		if ( ! $message ) {
			return null;
		}
		$table = $this->table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET {$counter} = {$counter} + 1, {$first} = COALESCE({$first}, %s) WHERE id = %d", Dates::now(), (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- columns are internal constants.
		return empty( $message[ $first ] );
	}
}
