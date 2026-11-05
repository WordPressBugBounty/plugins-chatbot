<?php
/**
 * Link exchange records for exchanges the owner agreed by hand with real
 * partners: both sides recorded and checked (their link to us, our link to
 * them). No marketplace or partner search, by design. Guardrails warn when
 * exchanges become a large share of the site's links.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Storage\Schema;
use QuantumCloud\SeoAiSearch\Technical\Scan;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Exchange records.
 */
final class Exchanges {

	/**
	 * Statuses.
	 */
	const STATUSES = array( 'proposed', 'live', 'broken', 'ended' );

	/**
	 * Add or update a record.
	 *
	 * @param array $data Fields (id to update).
	 * @return int|WP_Error Id.
	 */
	public static function save( array $data ) {
		global $wpdb;
		$id      = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$current = $id ? self::get( $id ) : null;
		if ( $id && ! $current ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This exchange was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$row = self::clean( array_merge( $current ? $current : array(), $data ) );
		if ( '' === $row['partner'] || '' === $row['their_url'] ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'Enter the partner\'s name and the address of their page that links to you.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$table = Schema::table( Schema::EXCHANGES );
		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$wpdb->update( $table, $row, array( 'id' => $id ) );
		} else {
			$row['created_at'] = current_time( 'mysql', true );
			$row['checks']     = '[]';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$wpdb->insert( $table, $row );
			$id = (int) $wpdb->insert_id;
		}
		// Their link also goes into the link monitor.
		Link_Store::add(
			array(
				'source_url' => $row['their_url'],
				'target_url' => $row['their_target'],
				'anchor'     => $row['their_anchor'],
				'rel'        => $row['their_rel'],
				'origin'     => 'exchange',
			)
		);
		return $id;
	}

	/**
	 * One record.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::EXCHANGES );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * All records with the guardrail warnings.
	 *
	 * @return array items, warnings, counts.
	 */
	public static function all() {
		global $wpdb;
		$table = Schema::table( Schema::EXCHANGES );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows   = array_map( array( __CLASS__, 'shape' ), (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 500", ARRAY_A ) );
		$active = 0;
		$recent = 0;
		$counts = array_fill_keys( self::STATUSES, 0 );
		foreach ( $rows as $row ) {
			++$counts[ $row['status'] ];
			if ( in_array( $row['status'], array( 'proposed', 'live' ), true ) ) {
				++$active;
			}
			if ( strtotime( $row['created_at'] ) > time() - 30 * DAY_IN_SECONDS ) {
				++$recent;
			}
		}
		$links    = Link_Store::query( array( 'limit' => 1 ) );
		$warnings = array();
		foreach ( Link_Guard::exchanges( $active, max( $links['total'], $links['sites'] ), $recent ) as $warning ) {
			$warnings[] = 'large_share' === $warning
				? __( 'Exchanges are a large share of your links. Google treats excessive link exchanges as a link scheme; most links should be earned.', 'chatbot' )
				: __( 'Many exchanges were added in the last 30 days. Keep exchanges to real relationships.', 'chatbot' );
		}
		return array(
			'items'    => $rows,
			'warnings' => $warnings,
			'counts'   => $counts,
		);
	}

	/**
	 * Check both sides now.
	 *
	 * @param int $id Id.
	 * @return array|WP_Error The record after the check.
	 */
	public static function check( $id ) {
		global $wpdb;
		$item = self::get( $id );
		if ( ! $item ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This exchange was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$theirs = Link_Checker::check( $item['their_url'], $item['their_target'], array( 'rel' => $item['their_rel'] ), true );
		$ours   = self::check_ours( $item );
		$status = self::status_after( $item['status'], $theirs['status'], $ours['status'] );
		$update = array();
		if ( '' === $item['their_rel'] && 'live' === $theirs['status'] && '' !== $theirs['rel'] ) {
			// Remember the link type seen first, so a later change shows.
			$update['their_rel'] = $theirs['rel'];
		}
		$entry  = array(
			'at'     => current_time( 'mysql', true ),
			'theirs' => array_intersect_key( $theirs, array_flip( array( 'status', 'rel', 'anchor', 'http_code', 'message' ) ) ),
			'ours'   => $ours,
		);
		$checks = array_slice( array_merge( array( $entry ), $item['checks'] ), 0, 30 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->update(
			Schema::table( Schema::EXCHANGES ),
			array_merge(
				$update,
				array(
					'status'       => $status,
					'checks'       => wp_json_encode( $checks ),
					'last_checked' => $entry['at'],
				)
			),
			array( 'id' => (int) $id )
		);
		$after = self::get( $id );
		/**
		 * An exchange was checked (Pro raises alerts when a partner's link is removed or changed).
		 *
		 * @param array  $after  Record after the check.
		 * @param string $before Earlier status.
		 */
		do_action( 'qcld_wpbot_seo/exchange_checked', $after, $item['status'] );
		return $after;
	}

	/**
	 * Status after a check (pure): both sides live makes it live; a proposed
	 * exchange stays proposed until their link appears; otherwise broken.
	 * Ended records stay ended; a side that could not be checked (blocked,
	 * error, unknown) does not change the status.
	 *
	 * @param string $status Current status.
	 * @param string $theirs Their side: live, changed, lost, blocked, error.
	 * @param string $ours   Our side: live, changed, lost, unknown.
	 * @return string
	 */
	public static function status_after( $status, $theirs, $ours ) {
		if ( 'ended' === $status || in_array( $theirs, array( 'blocked', 'error' ), true ) ) {
			return $status;
		}
		if ( 'live' === $theirs && in_array( $ours, array( 'live', 'unknown' ), true ) ) {
			return 'live';
		}
		if ( 'proposed' === $status && 'live' !== $theirs ) {
			return 'proposed';
		}
		return 'broken';
	}

	/**
	 * Is our link to the partner still on our page?
	 *
	 * @param array $item Record.
	 * @return array status (live|lost|changed|unknown), rel, message.
	 */
	public static function check_ours( array $item ) {
		if ( '' === $item['our_target'] ) {
			return array(
				'status'  => 'unknown',
				'rel'     => '',
				'message' => __( 'Add the partner page you link to.', 'chatbot' ),
			);
		}
		$html = '';
		if ( $item['our_post_id'] && get_post( $item['our_post_id'] ) ) {
			$html = (string) apply_filters( 'the_content', get_post_field( 'post_content', $item['our_post_id'] ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.
		} elseif ( '' !== $item['our_url'] ) {
			$page = Scan::fetch( $item['our_url'], 8 );
			$html = is_wp_error( $page ) ? '' : (string) $page['body'];
		}
		$found = Link_Finder::find( $html, $item['our_target'], '' !== $item['our_url'] ? $item['our_url'] : home_url( '/' ), true );
		if ( null === $found ) {
			return array(
				'status'  => 'lost',
				'rel'     => '',
				'message' => __( 'Your page no longer links to the partner.', 'chatbot' ),
			);
		}
		$expected = '' !== $item['our_rel'] ? $item['our_rel'] : 'follow';
		return array(
			'status'  => $found['rel'] === $expected ? 'live' : 'changed',
			'rel'     => $found['rel'],
			'message' => $found['rel'] === $expected ? '' : __( 'Your link\'s rel differs from the record.', 'chatbot' ),
		);
	}

	/**
	 * Delete a record.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return (bool) $wpdb->delete( Schema::table( Schema::EXCHANGES ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Warnings for one record (paid without sponsored).
	 *
	 * @param array $item Record.
	 * @return string[]
	 */
	public static function item_warnings( array $item ) {
		$out = array();
		if ( $item['paid'] && 'sponsored' !== $item['our_rel'] ) {
			$out[] = __( 'Something was paid or given in return, so your link to them should be marked sponsored.', 'chatbot' );
		}
		if ( $item['paid'] && ! in_array( $item['their_rel'], array( 'sponsored', 'nofollow' ), true ) ) {
			$out[] = __( 'Their link to you should be marked sponsored too, because something was paid or given in return.', 'chatbot' );
		}
		return $out;
	}

	/**
	 * Clean fields.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	private static function clean( array $data ) {
		$rel  = static function ( $value ) {
			return in_array( $value, array( 'follow', 'nofollow', 'sponsored', 'ugc' ), true ) ? $value : '';
		};
		$url  = static function ( $key ) use ( $data ) {
			return esc_url_raw( isset( $data[ $key ] ) ? trim( (string) $data[ $key ] ) : '', array( 'http', 'https' ) );
		};
		$date = static function ( $key ) use ( $data ) {
			return isset( $data[ $key ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $data[ $key ] ) ? (string) $data[ $key ] : null;
		};
		$post = isset( $data['our_post_id'] ) ? absint( $data['our_post_id'] ) : 0;
		$our  = $url( 'our_url' );
		if ( $post && 'publish' === get_post_status( $post ) ) {
			$our = (string) get_permalink( $post );
		}
		$their_target = $url( 'their_target' );
		return array(
			'partner'      => sanitize_text_field( isset( $data['partner'] ) ? (string) $data['partner'] : '' ),
			'contact'      => sanitize_text_field( isset( $data['contact'] ) ? (string) $data['contact'] : '' ),
			'their_url'    => $url( 'their_url' ),
			'their_target' => '' !== $their_target ? $their_target : home_url( '/' ),
			'their_anchor' => sanitize_text_field( isset( $data['their_anchor'] ) ? (string) $data['their_anchor'] : '' ),
			'their_rel'    => $rel( isset( $data['their_rel'] ) ? $data['their_rel'] : '' ),
			'our_post_id'  => $post,
			'our_url'      => $our,
			'our_target'   => $url( 'our_target' ),
			'our_anchor'   => sanitize_text_field( isset( $data['our_anchor'] ) ? (string) $data['our_anchor'] : '' ),
			'our_rel'      => $rel( isset( $data['our_rel'] ) ? $data['our_rel'] : '' ),
			'paid'         => empty( $data['paid'] ) ? 0 : 1,
			'agreed_on'    => $date( 'agreed_on' ),
			'ends_on'      => $date( 'ends_on' ),
			'agreement'    => sanitize_textarea_field( isset( $data['agreement'] ) ? (string) $data['agreement'] : '' ),
			'notes'        => sanitize_textarea_field( isset( $data['notes'] ) ? (string) $data['notes'] : '' ),
			'status'       => isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) ? $data['status'] : 'proposed',
		);
	}

	/**
	 * Row for the app.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$row['id']          = (int) $row['id'];
		$row['our_post_id'] = (int) $row['our_post_id'];
		$row['paid']        = (bool) $row['paid'];
		$checks             = json_decode( (string) $row['checks'], true );
		$row['checks']      = is_array( $checks ) ? $checks : array();
		foreach ( array( 'agreed_on', 'ends_on', 'last_checked' ) as $key ) {
			$row[ $key ] = (string) $row[ $key ];
		}
		$row['warnings'] = self::item_warnings( $row );
		return $row;
	}
}
