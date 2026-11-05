<?php
/**
 * Get cited by AI, milestone F: the posted log. After posting (by hand,
 * from their own account) the owner clicks "I posted it" and pastes the
 * link; optionally the page of their site it links to and a note. The
 * place is marked posted, and the post is recorded as a change in Results
 * & Reports (kind "offsite") on the linked page, so its clicks before and
 * after show there like any other change. Pro adds automatic checks.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Cited;

use QuantumCloud\SeoAiSearch\Results\Change_Log;
use QuantumCloud\SeoAiSearch\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Posted log.
 */
final class Posts {

	/**
	 * Log a post.
	 *
	 * @param array $args place_id (optional), url (required), page_url (optional, own site), note.
	 * @return array|WP_Error The post.
	 */
	public static function log( array $args ) {
		global $wpdb;
		$url = isset( $args['url'] ) ? esc_url_raw( trim( (string) $args['url'] ), array( 'http', 'https' ) ) : '';
		// Format only: the link is stored, never fetched here (no DNS lookup needed).
		$parts = '' !== $url ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || false === strpos( $parts['host'], '.' ) || ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'Paste the link to your post (starting with https://).', 'chatbot' ), array( 'status' => 400 ) );
		}
		$host = strtolower( preg_replace( '/^(www\.|m\.|old\.)/', '', (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
		$own  = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		if ( $host === $own ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'That link is on your own site. Paste the link to your post on the other site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$place    = ! empty( $args['place_id'] ) ? Places::get( absint( $args['place_id'] ) ) : null;
		$platform = $place ? $place['platform'] : self::platform_of( $host );
		// The post should be on the place's platform (a reply in that thread, or a post on that site).
		$defs = Places::platforms();
		if ( $place && isset( $defs[ $platform ] ) && array() !== $defs[ $platform ]['domains'] && self::platform_of( $host ) !== $platform ) {
			/* translators: %s: platform. */
			return new WP_Error( 'qcld_wpbot_seo_invalid', sprintf( __( 'That link is not on %s. Paste the link to your post there.', 'chatbot' ), $defs[ $platform ]['label'] ), array( 'status' => 400 ) );
		}
		$page_id = 0;
		if ( ! empty( $args['page_url'] ) ) {
			$page_id = url_to_postid( esc_url_raw( (string) $args['page_url'] ) );
			if ( ! $page_id ) {
				return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'The linked page was not found on your site. Leave it empty or paste the page\'s address.', 'chatbot' ), array( 'status' => 400 ) );
			}
		}
		$hash  = md5( strtolower( untrailingslashit( $url ) ) );
		$table = Schema::table( Schema::POSTS );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE url_hash = %s", $hash ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
			return new WP_Error( 'qcld_wpbot_seo_duplicate', __( 'This post is already in your log.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$note = substr( sanitize_textarea_field( isset( $args['note'] ) ? (string) $args['note'] : '' ), 0, 500 );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$table,
			array(
				'place_id'  => $place ? (int) $place['id'] : 0,
				'platform'  => sanitize_key( $platform ),
				'url'       => substr( $url, 0, 500 ),
				'url_hash'  => $hash,
				'search'    => $place ? substr( $place['search'], 0, 190 ) : '',
				'focus'     => Focus::text(),
				'note'      => $note,
				'status'    => 'posted',
				'checks'    => wp_json_encode( array( 'page_id' => $page_id ) ),
				'posted_at' => current_time( 'mysql', true ),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( $place ) {
			Places::set_status( (int) $place['id'], 'posted' );
		}
		if ( $page_id ) {
			Change_Log::record(
				array(
					'post_id' => $page_id,
					'kind'    => 'offsite',
					'field'   => sanitize_key( $platform ),
					'before'  => '',
					'after'   => $url,
					'source'  => 'get_cited_by_ai',
				)
			);
		}
		/**
		 * The owner logged a post on another site (Get cited by AI).
		 *
		 * @param int   $id   Post log id.
		 * @param array $post The post.
		 */
		do_action( 'qcld_wpbot_seo/cited_posted', $id, self::get( $id ) );
		return self::get( $id );
	}

	/**
	 * One post.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::POSTS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Posts, newest first.
	 *
	 * @param int $limit Rows.
	 * @return array[]
	 */
	public static function query( $limit = 100 ) {
		global $wpdb;
		$table = Schema::table( Schema::POSTS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY posted_at DESC, id DESC LIMIT %d", max( 1, min( 500, (int) $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	/**
	 * Remove a post from the log (the place goes back to drafted or new).
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function remove( $id ) {
		global $wpdb;
		$post = self::get( $id );
		if ( ! $post ) {
			return false;
		}
		$wpdb->delete( Schema::table( Schema::POSTS ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $post['place_id'] ) {
			$place = Places::get( $post['place_id'] );
			if ( $place && 'posted' === $place['status'] ) {
				Places::set_status( $post['place_id'], $place['draft'] ? 'drafted' : 'new' );
			}
		}
		return true;
	}

	/**
	 * Save check results (Pro).
	 *
	 * @param int    $id     Id.
	 * @param array  $checks Checks (merged).
	 * @param string $status posted|removed.
	 * @return void
	 */
	public static function save_checks( $id, array $checks, $status = '' ) {
		global $wpdb;
		$post = self::get( $id );
		if ( ! $post ) {
			return;
		}
		$row = array(
			'checks'     => wp_json_encode( array_merge( $post['checks'], $checks ) ),
			'checked_at' => current_time( 'mysql', true ),
		);
		if ( in_array( $status, array( 'posted', 'removed' ), true ) ) {
			$row['status'] = $status;
		}
		$wpdb->update( Schema::table( Schema::POSTS ), $row, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Platform of a host ('' when unknown).
	 *
	 * @param string $host Host.
	 * @return string
	 */
	private static function platform_of( $host ) {
		foreach ( Places::platforms() as $id => $def ) {
			foreach ( $def['domains'] as $domain ) {
				if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
					return $id;
				}
			}
		}
		return 'forum';
	}

	/**
	 * Row for the screen.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$checks  = json_decode( (string) $row['checks'], true );
		$checks  = is_array( $checks ) ? $checks : array();
		$page_id = isset( $checks['page_id'] ) ? (int) $checks['page_id'] : 0;
		$defs    = Places::platforms();
		return array(
			'id'             => (int) $row['id'],
			'place_id'       => (int) $row['place_id'],
			'platform'       => $row['platform'],
			'platform_label' => isset( $defs[ $row['platform'] ] ) ? $defs[ $row['platform'] ]['label'] : $row['platform'],
			'url'            => $row['url'],
			'search'         => $row['search'],
			'note'           => (string) $row['note'],
			'status'         => $row['status'],
			'checks'         => $checks,
			'page'           => $page_id ? array(
				'id'    => $page_id,
				'title' => wp_strip_all_tags( get_the_title( $page_id ) ),
				'url'   => (string) get_permalink( $page_id ),
			) : null,
			'posted_at'      => $row['posted_at'],
			'checked_at'     => (string) $row['checked_at'],
		);
	}
}
