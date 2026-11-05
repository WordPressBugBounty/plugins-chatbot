<?php
/**
 * Outreach: one pipeline for link requests, guest posts and
 * collaborations, each with its own status steps, notes, follow-up dates
 * and history. Not a mass-mail tool: nothing is sent without the owner's
 * click, and sending is a Pro feature (copy or open in the owner's email
 * in Free).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\SeoAiSearch\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Outreach records.
 */
final class Outreach {

	/**
	 * Types and their status steps (in order).
	 */
	const TYPES = array(
		'link_request'  => array( 'to_contact', 'contacted', 'replied', 'won', 'declined' ),
		'guest_post'    => array( 'idea', 'pitched', 'accepted', 'submitted', 'published', 'declined' ),
		'collaboration' => array( 'suggested', 'contacted', 'talking', 'agreed', 'live', 'declined' ),
	);

	/**
	 * The status that means a link was earned, per type.
	 */
	const WON = array(
		'link_request'  => 'won',
		'guest_post'    => 'published',
		'collaboration' => 'live',
	);

	/**
	 * Create a contact.
	 *
	 * @param array $data type, prospect_id and fields.
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;
		$type = isset( $data['type'] ) && isset( self::TYPES[ $data['type'] ] ) ? $data['type'] : '';
		if ( '' === $type ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'Choose a type: link request, guest post or collaboration.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$row = self::clean( $data );
		if ( '' === $row['name'] && '' === $row['website'] ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'Enter a name or a website.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->insert(
			Schema::table( Schema::OUTREACH ),
			array_merge(
				$row,
				array(
					'type'        => $type,
					'status'      => self::TYPES[ $type ][0],
					'prospect_id' => isset( $data['prospect_id'] ) ? absint( $data['prospect_id'] ) : 0,
					'owner_id'    => get_current_user_id(),
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			)
		);
		$id = (int) $wpdb->insert_id;
		self::log( $id, 'created', '', self::TYPES[ $type ][0] );
		return $id;
	}

	/**
	 * Update fields and/or move to another status of the same type.
	 *
	 * @param int   $id   Id.
	 * @param array $data Fields; status.
	 * @return array|WP_Error The record with warnings.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$current = self::get( $id );
		if ( ! $current ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This contact was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$row    = self::clean( array_merge( $current, $data ) );
		$status = isset( $data['status'] ) ? (string) $data['status'] : $current['status'];
		if ( ! in_array( $status, self::TYPES[ $current['type'] ], true ) ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'That status does not belong to this type of outreach.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$won = self::WON[ $current['type'] ] === $status;
		if ( $won && '' === $row['link_url'] ) {
			return new WP_Error( 'qcld_wpbot_seo_link_needed', __( 'Add the address of the page that now links to you, so the link can be checked.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$row['status']     = $status;
		$row['updated_at'] = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->update( Schema::table( Schema::OUTREACH ), $row, array( 'id' => (int) $id ) );
		if ( $status !== $current['status'] ) {
			self::log( $id, 'status', $current['status'], $status );
		}
		if ( isset( $data['note'] ) && '' !== trim( (string) $data['note'] ) ) {
			self::log( $id, 'note', '', '', sanitize_textarea_field( (string) $data['note'] ) );
		}
		if ( $won && ( $status !== $current['status'] || $row['link_url'] !== $current['link_url'] ) ) {
			self::won( (int) $id, $row, isset( $data['reciprocal_url'] ) ? (string) $data['reciprocal_url'] : '' );
		}
		return self::get( $id );
	}

	/**
	 * A won link moves into the monitor and is checked; a reciprocal link
	 * from a collaboration also goes into exchange records.
	 *
	 * @param int    $id         Outreach id.
	 * @param array  $row        Cleaned record.
	 * @param string $reciprocal Page of this site linking back to the partner.
	 * @return void
	 */
	private static function won( $id, array $row, $reciprocal ) {
		$link_id = Link_Store::add(
			array(
				'source_url'  => $row['link_url'],
				'target_url'  => '' !== $row['target_url'] ? $row['target_url'] : home_url( '/' ),
				'anchor'      => $row['anchor'],
				'origin'      => 'outreach',
				'outreach_id' => $id,
			)
		);
		if ( is_wp_error( $link_id ) ) {
			self::log( $id, 'note', '', '', $link_id->get_error_message() );
			return;
		}
		self::log( $id, 'link', '', '', $row['link_url'] );
		Link_Checker::start( array( $link_id ) );
		if ( '' !== trim( $reciprocal ) ) {
			Exchanges::save(
				array(
					'partner'      => $row['name'],
					'their_url'    => $row['link_url'],
					'their_target' => '' !== $row['target_url'] ? $row['target_url'] : home_url( '/' ),
					'their_anchor' => $row['anchor'],
					'our_url'      => $reciprocal,
					'our_target'   => $row['website'],
					'status'       => 'live',
					'notes'        => __( 'Reciprocal link from a collaboration.', 'chatbot' ),
				)
			);
		}
	}

	/**
	 * Record history.
	 *
	 * @param int    $id     Outreach id.
	 * @param string $kind   created, status, note, draft, copied, opened, sent, link, reminder.
	 * @param string $from   From status.
	 * @param string $to     To status.
	 * @param string $detail Detail.
	 * @return void
	 */
	public static function log( $id, $kind, $from = '', $to = '', $detail = '' ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->insert(
			Schema::table( Schema::STEPS ),
			array(
				'outreach_id' => (int) $id,
				'kind'        => sanitize_key( $kind ),
				'from_status' => $from,
				'to_status'   => $to,
				'detail'      => (string) $detail,
				'user_id'     => get_current_user_id(),
				'created_at'  => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * One contact with its history.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::OUTREACH );
		$steps = Schema::table( Schema::STEPS );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names from constants.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$history = (array) $wpdb->get_results( $wpdb->prepare( "SELECT kind, from_status, to_status, detail, created_at FROM {$steps} WHERE outreach_id = %d ORDER BY id DESC LIMIT 100", (int) $id ), ARRAY_A );
		// phpcs:enable
		$out             = self::shape( $row );
		$out['history']  = $history;
		$out['warnings'] = self::warnings( $out );
		return $out;
	}

	/**
	 * Contacts by type and status.
	 *
	 * @param string $type   Type or ''.
	 * @param string $status Status or ''.
	 * @return array items, due.
	 */
	public static function query( $type = '', $status = '' ) {
		global $wpdb;
		$table = Schema::table( Schema::OUTREACH );
		$where = '1=1';
		if ( '' !== $type && isset( self::TYPES[ $type ] ) ) {
			$where .= $wpdb->prepare( ' AND type = %s', $type );
		}
		if ( '' !== $status ) {
			$where .= $wpdb->prepare( ' AND status = %s', sanitize_key( $status ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant; $where built with prepare().
		$rows = (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY (follow_up IS NULL), follow_up ASC, updated_at DESC LIMIT 500", ARRAY_A );
		return array(
			'items' => array_map( array( __CLASS__, 'shape' ), $rows ),
			'due'   => self::due_count(),
		);
	}

	/**
	 * Contacts with a follow-up due today or earlier (open ones only).
	 *
	 * @return array
	 */
	public static function due() {
		global $wpdb;
		$table  = Schema::table( Schema::OUTREACH );
		$closed = array( 'won', 'declined', 'published', 'live' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE follow_up IS NOT NULL AND follow_up <= %s AND status NOT IN (%s, %s, %s, %s) ORDER BY follow_up ASC LIMIT 200", wp_date( 'Y-m-d' ), $closed[0], $closed[1], $closed[2], $closed[3] ), ARRAY_A );
		return array_map( array( __CLASS__, 'shape' ), $rows );
	}

	/**
	 * Number of follow-ups due (menu badge).
	 *
	 * @return int
	 */
	public static function due_count() {
		return count( self::due() );
	}

	/**
	 * Record a message sent to a contact (the Pro module sends; Free never does).
	 *
	 * @param int    $id      Outreach id.
	 * @param string $subject Subject sent.
	 * @return void
	 */
	public static function mark_sent( $id, $subject ) {
		global $wpdb;
		$table = Schema::table( Schema::OUTREACH );
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET sent_count = sent_count + 1, last_sent = %s, updated_at = %s WHERE id = %d", $now, $now, (int) $id ) );
		self::log( $id, 'sent', '', '', sanitize_text_field( (string) $subject ) );
	}

	/**
	 * Messages sent since a time (for the daily limit).
	 *
	 * @param string $since UTC datetime.
	 * @return int
	 */
	public static function sent_since( $since ) {
		global $wpdb;
		$table = Schema::table( Schema::STEPS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE kind = 'sent' AND created_at >= %s", $since ) );
	}

	/**
	 * Links earned and links lost since a time (weekly report).
	 *
	 * @param string $since UTC datetime.
	 * @return array earned (list of name, type, link_url).
	 */
	public static function earned_since( $since ) {
		global $wpdb;
		$table = Schema::table( Schema::OUTREACH );
		$steps = Schema::table( Schema::STEPS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names from constants.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT o.id, o.name, o.type, o.link_url FROM {$steps} s JOIN {$table} o ON o.id = s.outreach_id WHERE s.kind = 'status' AND s.created_at >= %s AND ((o.type = 'link_request' AND s.to_status = 'won') OR (o.type = 'guest_post' AND s.to_status = 'published') OR (o.type = 'collaboration' AND s.to_status = 'live'))", $since ), ARRAY_A );
	}

	/**
	 * Delete a contact and its history.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Own tables.
		$wpdb->delete( Schema::table( Schema::STEPS ), array( 'outreach_id' => (int) $id ), array( '%d' ) );
		return (bool) $wpdb->delete( Schema::table( Schema::OUTREACH ), array( 'id' => (int) $id ), array( '%d' ) );
		// phpcs:enable
	}

	/**
	 * Guest-post and anchor warnings for a contact.
	 *
	 * @param array $item Contact.
	 * @return string[] Messages.
	 */
	public static function warnings( array $item ) {
		$out = array();
		if ( '' !== $item['anchor'] ) {
			$title   = $item['target_post_id'] ? get_the_title( $item['target_post_id'] ) : '';
			$queries = array();
			if ( $item['target_post_id'] ) {
				$queries[] = Audit::main_query( $item['target_post_id'] );
			}
			/**
			 * Searches whose exact wording should not be used as anchors.
			 *
			 * @param string[] $queries Queries.
			 * @param array    $item    Contact.
			 */
			$queries = (array) apply_filters( 'qcld_wpbot_seo/anchor_queries', array_filter( $queries ), $item );
			if ( Link_Guard::anchor_over_optimised( $item['anchor'], get_bloginfo( 'name' ), $title, $queries ) ) {
				$out[] = __( 'The anchor text looks keyword-rich. Use your brand name or the page title instead; keyword-rich anchors in guest posts and requests can count as link spam.', 'chatbot' );
			}
		}
		if ( 'guest_post' === $item['type'] ) {
			$out[] = __( 'Guest post rules: one or two links at most, using your brand name or the page title; follow the publication\'s link policy; if anything is paid or exchanged, the link must be marked sponsored.', 'chatbot' );
			foreach ( Link_Guard::pacing( self::published_guest_posts(), time() ) as $warning ) {
				$out[] = 'many_recent' === $warning
					? __( 'Many guest posts went out in the last 30 days. Slow down: a sudden burst of guest posts can look like a link scheme.', 'chatbot' )
					: __( 'Most of your guest-post links use the same anchor text. Vary it, using your brand name or page titles.', 'chatbot' );
			}
		}
		return $out;
	}

	/**
	 * Published guest posts (date, anchor) for pacing checks.
	 *
	 * @return array
	 */
	private static function published_guest_posts() {
		global $wpdb;
		$table = Schema::table( Schema::OUTREACH );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = (array) $wpdb->get_results( "SELECT updated_at AS date, anchor FROM {$table} WHERE type = 'guest_post' AND status = 'published' ORDER BY updated_at DESC LIMIT 100", ARRAY_A );
		return $rows;
	}

	/**
	 * Clean fields.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	private static function clean( array $data ) {
		$website = isset( $data['website'] ) ? trim( (string) $data['website'] ) : '';
		if ( '' !== $website && ! preg_match( '#^https?://#i', $website ) ) {
			$website = 'https://' . $website;
		}
		$website = esc_url_raw( $website, array( 'http', 'https' ) );
		$post_id = isset( $data['target_post_id'] ) ? absint( $data['target_post_id'] ) : 0;
		$target  = esc_url_raw( isset( $data['target_url'] ) ? trim( (string) $data['target_url'] ) : '', array( 'http', 'https' ) );
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			$target = (string) get_permalink( $post_id );
		}
		$follow = isset( $data['follow_up'] ) ? (string) $data['follow_up'] : '';
		$draft  = isset( $data['draft'] ) ? $data['draft'] : '';
		if ( is_array( $draft ) ) {
			$draft = wp_json_encode( self::clean_draft( $draft ) );
		}
		return array(
			'name'           => sanitize_text_field( isset( $data['name'] ) ? (string) $data['name'] : '' ),
			'website'        => $website,
			'host'           => Link_Finder::host( $website ),
			'contact_name'   => sanitize_text_field( isset( $data['contact_name'] ) ? (string) $data['contact_name'] : '' ),
			'contact_email'  => sanitize_email( isset( $data['contact_email'] ) ? (string) $data['contact_email'] : '' ),
			'target_post_id' => $post_id,
			'target_url'     => $target,
			'link_url'       => esc_url_raw( isset( $data['link_url'] ) ? trim( (string) $data['link_url'] ) : '', array( 'http', 'https' ) ),
			'anchor'         => sanitize_text_field( isset( $data['anchor'] ) ? (string) $data['anchor'] : '' ),
			'follow_up'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $follow ) ? $follow : null,
			'notes'          => sanitize_textarea_field( isset( $data['notes'] ) ? (string) $data['notes'] : '' ),
			'draft'          => is_string( $draft ) ? $draft : '',
		);
	}

	/**
	 * Clean a draft (subject, body, question, answer, ideas).
	 *
	 * @param array $draft Draft.
	 * @return array
	 */
	public static function clean_draft( array $draft ) {
		$ideas = array();
		foreach ( isset( $draft['ideas'] ) ? (array) $draft['ideas'] : array() as $idea ) {
			if ( is_array( $idea ) && ! empty( $idea['title'] ) ) {
				$ideas[] = array(
					'title' => sanitize_text_field( (string) $idea['title'] ),
					'angle' => sanitize_textarea_field( isset( $idea['angle'] ) ? (string) $idea['angle'] : '' ),
					'link'  => sanitize_textarea_field( isset( $idea['link'] ) ? (string) $idea['link'] : '' ),
				);
			}
		}
		return array(
			'subject'  => sanitize_text_field( isset( $draft['subject'] ) ? (string) $draft['subject'] : '' ),
			'body'     => sanitize_textarea_field( isset( $draft['body'] ) ? (string) $draft['body'] : '' ),
			'question' => sanitize_text_field( isset( $draft['question'] ) ? (string) $draft['question'] : '' ),
			'answer'   => sanitize_textarea_field( isset( $draft['answer'] ) ? (string) $draft['answer'] : '' ),
			'ideas'    => array_slice( $ideas, 0, 3 ),
			'chosen'   => isset( $draft['chosen'] ) ? (int) $draft['chosen'] : -1,
		);
	}

	/**
	 * Row for the app.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function shape( array $row ) {
		foreach ( array( 'id', 'prospect_id', 'target_post_id', 'sent_count', 'owner_id' ) as $key ) {
			$row[ $key ] = (int) $row[ $key ];
		}
		$draft            = json_decode( (string) $row['draft'], true );
		$row['draft']     = is_array( $draft ) ? $draft : null;
		$row['steps']     = self::TYPES[ $row['type'] ];
		$row['won']       = self::WON[ $row['type'] ] === $row['status'];
		$row['due']       = ! empty( $row['follow_up'] ) && $row['follow_up'] <= wp_date( 'Y-m-d' ) && ! in_array( $row['status'], array( 'won', 'declined', 'published', 'live' ), true );
		$row['follow_up'] = (string) $row['follow_up'];
		return $row;
	}
}
