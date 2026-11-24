<?php
/**
 * Campaigns: write, choose who gets it, test, send now or schedule, pause,
 * resume and cancel.
 *
 * Statuses: draft → scheduled → sending → sent; paused and cancelled.
 * Audience: send_to all | lists | tags, plus exclude_lists / exclude_tags;
 * only subscribed contacts with an email that is not suppressed.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Content;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Audience\Rules;
use QuantumCloud\AiMarketing\Sending\Mailer;
use QuantumCloud\AiMarketing\Sending\Queue;
use QuantumCloud\AiMarketing\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Campaigns.
 */
final class Campaigns {

	const STATUSES = array( 'draft', 'scheduled', 'sending', 'paused', 'sent', 'cancelled' );
	const EDITABLE = array( 'draft', 'scheduled', 'paused' );
	const FIELDS   = array( 'name', 'subject', 'preview_text', 'from_name', 'from_email', 'reply_to', 'content_html' );

	/**
	 * All campaigns, newest first, with counts.
	 *
	 * @param string $status Optional status filter.
	 * @return array[]
	 */
	public static function all( $status = '' ) {
		global $wpdb;
		$table = Schema::table( 'campaigns' );
		$rows  = in_array( $status, self::STATUSES, true )
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE status = %s ORDER BY id DESC LIMIT 200", $status ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			: $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 200", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	/**
	 * One campaign.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( 'campaigns' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Create or update a campaign (only while it can still be edited).
	 *
	 * @param int   $id   Id (0 to create).
	 * @param array $data Fields, audience, scheduled_at.
	 * @return array|WP_Error
	 */
	public static function save( $id, array $data ) {
		global $wpdb;
		$table = Schema::table( 'campaigns' );
		$now   = current_time( 'mysql', true );
		if ( $id ) {
			$current = self::get( $id );
			if ( ! $current ) {
				return self::not_found();
			}
			if ( ! in_array( $current['status'], self::EDITABLE, true ) ) {
				return new WP_Error( 'qcld_mk_locked', __( 'This campaign is sending or sent and can no longer be changed. Duplicate it instead.', 'chatbot' ), array( 'status' => 400 ) );
			}
		}
		$row = array( 'updated_at' => $now );
		foreach ( self::FIELDS as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$row[ $field ] = 'content_html' === $field ? wp_kses_post( (string) $data[ $field ] ) : sanitize_text_field( (string) $data[ $field ] );
			}
		}
		foreach ( array( 'from_email', 'reply_to' ) as $field ) {
			if ( isset( $row[ $field ] ) && '' !== $row[ $field ] && ! is_email( $row[ $field ] ) ) {
				return new WP_Error( 'qcld_mk_email', __( 'Check the email addresses.', 'chatbot' ), array( 'status' => 400 ) );
			}
		}
		if ( isset( $data['audience'] ) && is_array( $data['audience'] ) ) {
			$row['audience'] = wp_json_encode( self::clean_audience( $data['audience'] ) );
		}
		if ( isset( $data['extra'] ) && is_array( $data['extra'] ) ) {
			$stored = $id ? json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT settings FROM `{$table}` WHERE id = %d", (int) $id ) ), true ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			$stored = is_array( $stored ) ? $stored : array();
			/**
			 * Extra campaign settings other modules keep (the Master module's subject test, send time).
			 * Unknown keys are dropped unless a module cleans and returns them.
			 *
			 * @param array $clean Cleaned extra settings.
			 * @param array $input Submitted extra settings.
			 */
			$stored['extra'] = (array) apply_filters( 'qcld_wpbot_marketing/campaign_extra', array(), $data['extra'] );
			$row['settings'] = wp_json_encode( $stored );
		}
		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		} else {
			$row['status']     = 'draft';
			$row['created_at'] = $now;
			if ( empty( $row['name'] ) ) {
				$row['name'] = ! empty( $row['subject'] ) ? $row['subject'] : __( 'New campaign', 'chatbot' );
			}
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$id = (int) $wpdb->insert_id;
		}
		return self::get( $id );
	}

	/**
	 * Duplicate as a new draft.
	 *
	 * @param int $id Id.
	 * @return array|WP_Error
	 */
	public static function duplicate( $id ) {
		$campaign = self::get( $id );
		if ( ! $campaign ) {
			return self::not_found();
		}
		$data             = array_intersect_key( $campaign, array_flip( self::FIELDS ) );
		$data['audience'] = $campaign['audience'];
		/* translators: %s: campaign name. */
		$data['name'] = sprintf( __( '%s (copy)', 'chatbot' ), $campaign['name'] );
		return self::save( 0, $data );
	}

	/**
	 * Delete a draft or finished campaign (not while sending).
	 *
	 * @param int $id Id.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$campaign = self::get( $id );
		if ( ! $campaign ) {
			return self::not_found();
		}
		if ( 'sending' === $campaign['status'] ) {
			return new WP_Error( 'qcld_mk_sending', __( 'Pause or cancel the campaign before deleting it.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$wpdb->delete( Schema::table( 'campaigns' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . Schema::table( 'messages' ) . "` WHERE campaign_id = %d AND status IN ('queued')", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return true;
	}

	/**
	 * Check a campaign is ready to send.
	 *
	 * @param array $campaign Campaign.
	 * @return true|WP_Error
	 */
	public static function ready( array $campaign ) {
		if ( '' === trim( $campaign['subject'] ) ) {
			return new WP_Error( 'qcld_mk_subject', __( 'Add a subject line.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( '' === trim( wp_strip_all_tags( $campaign['content_html'] ) ) ) {
			return new WP_Error( 'qcld_mk_body', __( 'The email is empty.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 0 === self::count_recipients( $campaign['audience'] ) ) {
			return new WP_Error( 'qcld_mk_nobody', __( 'Nobody would receive this campaign. Check who it goes to.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Send now, or schedule when a future time is given.
	 *
	 * @param int    $id           Id.
	 * @param string $scheduled_at UTC 'Y-m-d H:i:s' or ''.
	 * @return array|WP_Error
	 */
	public static function start( $id, $scheduled_at = '' ) {
		global $wpdb;
		$campaign = self::get( $id );
		if ( ! $campaign ) {
			return self::not_found();
		}
		if ( ! in_array( $campaign['status'], array( 'draft', 'scheduled' ), true ) ) {
			return new WP_Error( 'qcld_mk_state', __( 'This campaign was already sent or is sending.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$ready = self::ready( $campaign );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$table = Schema::table( 'campaigns' );
		$when  = '' !== $scheduled_at ? strtotime( $scheduled_at . ' UTC' ) : false;
		if ( $when && $when > time() + 60 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				$table,
				array(
					'status'       => 'scheduled',
					'scheduled_at' => gmdate( 'Y-m-d H:i:s', $when ),
				),
				array( 'id' => (int) $id )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Queue::ensure_scheduled();
			return self::get( $id );
		}
		$ids = self::recipient_ids( $campaign['audience'] );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$table,
			array(
				'status'     => 'sending',
				'started_at' => current_time( 'mysql', true ),
				'recipients' => count( $ids ),
			),
			array( 'id' => (int) $id )
		);
		Queue::enqueue_campaign( (int) $id, $ids );
		return self::get( $id );
	}

	/**
	 * Start scheduled campaigns that are due (cron).
	 *
	 * @return void
	 */
	public static function start_due() {
		global $wpdb;
		$table = Schema::table( 'campaigns' );
		$ids   = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= %s", current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		foreach ( $ids as $id ) {
			self::start( (int) $id );
		}
	}

	/**
	 * Pause, resume or cancel.
	 *
	 * @param int    $id     Id.
	 * @param string $action pause, resume, cancel.
	 * @return array|WP_Error
	 */
	public static function control( $id, $action ) {
		global $wpdb;
		$campaign = self::get( $id );
		if ( ! $campaign ) {
			return self::not_found();
		}
		$table = Schema::table( 'campaigns' );
		$map   = array(
			'pause'  => array( array( 'sending', 'scheduled' ), 'paused' ),
			'resume' => array( array( 'paused' ), 'sending' ),
			'cancel' => array( array( 'sending', 'scheduled', 'paused' ), 'cancelled' ),
		);
		if ( ! isset( $map[ $action ] ) || ! in_array( $campaign['status'], $map[ $action ][0], true ) ) {
			return new WP_Error( 'qcld_mk_state', __( 'That is not possible for this campaign right now.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$status = $map[ $action ][1];
		if ( 'resume' === $action && ! empty( $campaign['scheduled_at'] ) && 0 === (int) $campaign['recipients'] ) {
			$status = 'scheduled';
		}
		$wpdb->update( $table, array( 'status' => $status ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( 'cancel' === $action ) {
			$messages = Schema::table( 'messages' );
			$wpdb->query( $wpdb->prepare( "UPDATE `{$messages}` SET status = 'skipped', error = %s WHERE campaign_id = %d AND status = 'queued'", __( 'Campaign cancelled.', 'chatbot' ), (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		Queue::ensure_scheduled();
		return self::get( $id );
	}

	/**
	 * Mark sending campaigns with nothing left in the queue as sent.
	 *
	 * @return void
	 */
	public static function finish_done() {
		global $wpdb;
		$campaigns = Schema::table( 'campaigns' );
		$messages  = Schema::table( 'messages' );
		$wpdb->query( $wpdb->prepare( "UPDATE `{$campaigns}` c SET c.status = 'sent', c.finished_at = %s WHERE c.status = 'sending' AND NOT EXISTS ( SELECT 1 FROM `{$messages}` m WHERE m.campaign_id = c.id AND m.status IN ('queued','sending') )", current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
	}

	/**
	 * Send a test to one address (no tracking, nothing counted).
	 *
	 * @param int    $id    Campaign id.
	 * @param string $email Address.
	 * @return true|WP_Error
	 */
	public static function test( $id, $email ) {
		$campaign = self::get( $id );
		if ( ! $campaign ) {
			return self::not_found();
		}
		$email = Rules::email( $email );
		if ( '' === $email ) {
			return new WP_Error( 'qcld_mk_email', __( 'Enter a valid email address for the test.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$person           = Contacts::by_email( $email );
		$sample           = $person ? $person : array(
			'email'      => $email,
			'first_name' => '',
			'last_name'  => '',
			'company'    => '',
			'attributes' => array(),
			'token'      => 'test',
		);
		$built            = Mailer::build( self::email_for( $id ), $sample, 0 );
		$built['to']      = $email;
		$built['subject'] = '[' . __( 'Test', 'chatbot' ) . '] ' . $built['subject'];
		return Mailer::send( $built, array( 'kind' => 'test' ) );
	}

	/**
	 * The email of a campaign, for the Mailer.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function email_for( $id ) {
		$campaign = self::get( $id );
		return $campaign ? array_merge(
			array_intersect_key( $campaign, array_flip( self::FIELDS ) ),
			array(
				'campaign_id' => (int) $campaign['id'],
				'extra'       => $campaign['extra'],
			)
		) : null;
	}

	/* ── Audience. ── */

	/**
	 * Clean audience settings (pure; unit tested).
	 *
	 * @param array $audience send_to, lists, tags, exclude_lists, exclude_tags.
	 * @return array
	 */
	public static function clean_audience( array $audience ) {
		$ids = static function ( $value ) {
			return array_values( array_unique( array_filter( array_map( 'intval', is_array( $value ) ? $value : array() ) ) ) );
		};
		$to  = isset( $audience['send_to'] ) && in_array( $audience['send_to'], array( 'all', 'lists', 'tags' ), true ) ? $audience['send_to'] : 'all';
		return array(
			'send_to'       => $to,
			'lists'         => $ids( isset( $audience['lists'] ) ? $audience['lists'] : array() ),
			'tags'          => $ids( isset( $audience['tags'] ) ? $audience['tags'] : array() ),
			'exclude_lists' => $ids( isset( $audience['exclude_lists'] ) ? $audience['exclude_lists'] : array() ),
			'exclude_tags'  => $ids( isset( $audience['exclude_tags'] ) ? $audience['exclude_tags'] : array() ),
		);
	}

	/**
	 * Split list ids into member lists and segments (rules).
	 *
	 * @param int[] $ids List ids.
	 * @return array{0:int[],1:array[]}
	 */
	private static function split_segments( array $ids ) {
		$static   = array();
		$segments = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$list = $id > 0 ? \QuantumCloud\AiMarketing\Audience\Lists::get( $id ) : null;
			if ( $list && 'segment' === $list['kind'] ) {
				$segments[] = $list['rules'];
			} elseif ( $id > 0 ) {
				$static[] = $id;
			}
		}
		return array( $static, $segments );
	}

	/**
	 * WHERE clause selecting the audience (only ints and constants inside).
	 *
	 * @param array $audience Clean audience.
	 * @return string
	 */
	private static function where( array $audience ) {
		$members  = Schema::table( 'memberships' );
		$suppress = Schema::table( 'suppression' );
		$where    = array( "c.status = 'subscribed'", 'c.email IS NOT NULL', "NOT EXISTS ( SELECT 1 FROM `{$suppress}` s WHERE s.value = c.email OR ( s.kind = 'domain' AND s.value = SUBSTRING_INDEX( c.email, '@', -1 ) ) )" );
		$include  = 'lists' === $audience['send_to'] ? $audience['lists'] : ( 'tags' === $audience['send_to'] ? $audience['tags'] : array() );
		if ( 'all' !== $audience['send_to'] ) {
			list( $static, $segments ) = self::split_segments( $include );
			$any                       = array();
			if ( $static ) {
				$any[] = "EXISTS ( SELECT 1 FROM `{$members}` m WHERE m.contact_id = c.id AND m.list_id IN (" . implode( ',', $static ) . ') )';
			}
			foreach ( $segments as $rules ) {
				$any[] = \QuantumCloud\AiMarketing\Audience\Segments::sql( $rules );
			}
			$where[] = $any ? '( ' . implode( ' OR ', $any ) . ' )' : '1 = 0';
		}
		/**
		 * Lists and tags left out of every campaign (the Pro module's "Resting" contacts).
		 *
		 * @param int[] $ids List or tag ids.
		 */
		$always                    = array_map( 'intval', (array) apply_filters( 'qcld_wpbot_marketing/excluded_list_ids', array() ) );
		$exclude                   = array_filter( array_merge( $audience['exclude_lists'], $audience['exclude_tags'], $always ) );
		list( $static, $segments ) = self::split_segments( $exclude );
		if ( $static ) {
			$where[] = "NOT EXISTS ( SELECT 1 FROM `{$members}` x WHERE x.contact_id = c.id AND x.list_id IN (" . implode( ',', $static ) . ') )';
		}
		foreach ( $segments as $rules ) {
			$where[] = 'NOT ' . \QuantumCloud\AiMarketing\Audience\Segments::sql( $rules );
		}
		return implode( ' AND ', $where );
	}

	/**
	 * How many people would get it now.
	 *
	 * @param array $audience Audience.
	 * @return int
	 */
	public static function count_recipients( array $audience ) {
		global $wpdb;
		$contacts = Schema::table( 'contacts' );
		$where    = self::where( self::clean_audience( $audience ) );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$contacts}` c WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables; only ints and constants.
	}

	/**
	 * Contact ids that would get it now.
	 *
	 * @param array $audience Audience.
	 * @return int[]
	 */
	public static function recipient_ids( array $audience ) {
		global $wpdb;
		$contacts = Schema::table( 'contacts' );
		$where    = self::where( self::clean_audience( $audience ) );
		return array_map( 'intval', (array) $wpdb->get_col( "SELECT c.id FROM `{$contacts}` c WHERE {$where} ORDER BY c.id" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables; only ints and constants.
	}

	/**
	 * Delivery numbers of a campaign.
	 *
	 * @param int $id Id.
	 * @return array
	 */
	public static function stats( $id ) {
		global $wpdb;
		$messages = Schema::table( 'messages' );
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM(status='queued') AS queued, SUM(status='sent') AS sent, SUM(status='failed') AS failed, SUM(status='skipped') AS skipped, SUM(status='unknown') AS unknown, SUM(opens>0) AS opened, SUM(clicks>0) AS clicked FROM `{$messages}` WHERE campaign_id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return array_map( 'intval', (array) $row );
	}

	/**
	 * A campaign as returned to the app.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$settings = json_decode( (string) $row['settings'], true );
		$audience = json_decode( (string) $row['audience'], true );
		if ( ! is_array( $audience ) || ! isset( $audience['send_to'] ) ) {
			$audience = json_decode( (string) $row['settings'], true ); // Campaigns copied from the old module kept their audience in settings.
		}
		return array(
			'id'           => (int) $row['id'],
			'name'         => (string) $row['name'],
			'subject'      => (string) $row['subject'],
			'preview_text' => (string) $row['preview_text'],
			'from_name'    => (string) $row['from_name'],
			'from_email'   => (string) $row['from_email'],
			'reply_to'     => (string) $row['reply_to'],
			'content_html' => (string) $row['content_html'],
			'audience'     => self::clean_audience( is_array( $audience ) ? $audience : array() ),
			'status'       => (string) $row['status'],
			'origin'       => (string) $row['origin'],
			'scheduled_at' => (string) $row['scheduled_at'],
			'started_at'   => (string) $row['started_at'],
			'finished_at'  => (string) $row['finished_at'],
			'recipients'   => (int) $row['recipients'],
			'sent'         => (int) $row['sent'],
			'failed'       => (int) $row['failed'],
			'created_at'   => (string) $row['created_at'],
			'extra'        => is_array( $settings ) && isset( $settings['extra'] ) && is_array( $settings['extra'] ) ? $settings['extra'] : array(),
		);
	}

	/**
	 * Not-found error.
	 *
	 * @return WP_Error
	 */
	private static function not_found() {
		return new WP_Error( 'qcld_mk_not_found', __( 'Campaign not found.', 'chatbot' ), array( 'status' => 404 ) );
	}
}
