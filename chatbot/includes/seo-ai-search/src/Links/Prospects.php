<?php
/**
 * Prospects: sites worth contacting. Added by hand (Free) or found by AI
 * with web search (Pro). Only what the owner enters or a published contact
 * page is kept; no personal contact details are scraped.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Prospect records.
 */
final class Prospects {

	/**
	 * Kinds and the outreach type each starts.
	 */
	const KINDS = array(
		'mention'     => 'link_request',
		'resource'    => 'link_request',
		'broken'      => 'link_request',
		'publication' => 'guest_post',
		'partner'     => 'collaboration',
		'other'       => 'link_request',
	);

	/**
	 * Add a prospect.
	 *
	 * @param array $data kind, name, website, page_url, contact_url, location, topic, reason, flags, source.
	 * @return int|WP_Error
	 */
	public static function add( array $data ) {
		global $wpdb;
		$row = self::clean( $data );
		if ( '' === $row['host'] ) {
			return new WP_Error( 'qcld_wpbot_seo_bad_url', __( 'Enter the prospect\'s website address.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( Link_Finder::host( home_url() ) === $row['host'] ) {
			return new WP_Error( 'qcld_wpbot_seo_own_site', __( 'That is your own site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( in_array( $row['host'], self::competitor_hosts(), true ) ) {
			return new WP_Error( 'qcld_wpbot_seo_competitor', __( 'This site is on your competitor list, so it is left out of prospects.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$table = Schema::table( Schema::PROSPECTS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE host = %s AND kind = %s AND page_url = %s', $table, $row['host'], $row['kind'], $row['page_url'] ) );
		if ( $existing ) {
			return $existing;
		}
		$row['created_at'] = current_time( 'mysql', true );
		$row['status']     = 'new';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a prospect (fields or status: new, started, dismissed).
	 *
	 * @param int   $id   Id.
	 * @param array $data Fields.
	 * @return array|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$current = self::get( $id );
		if ( ! $current ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This prospect was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$row = self::clean( array_merge( $current, $data ) );
		if ( isset( $data['status'] ) && in_array( $data['status'], array( 'new', 'started', 'dismissed' ), true ) ) {
			$row['status'] = $data['status'];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->update( Schema::table( Schema::PROSPECTS ), $row, array( 'id' => (int) $id ) );
		return self::get( $id );
	}

	/**
	 * One prospect.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::PROSPECTS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, (int) $id ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Prospects by kind and status.
	 *
	 * @param string $kind   Kind or ''.
	 * @param string $status Status or '' (all but dismissed).
	 * @return array
	 */
	public static function query( $kind = '', $status = '' ) {
		global $wpdb;
		$table = Schema::table( Schema::PROSPECTS );
		$args  = array( $table );
		$where = "status <> 'dismissed'";
		if ( '' !== $status ) {
			$where  = 'status = %s';
			$args[] = $status;
		}
		if ( '' !== $kind && isset( self::KINDS[ $kind ] ) ) {
			$where .= ' AND kind = %s';
			$args[] = $kind;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where holds only fixed placeholder fragments; values are passed to prepare().
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$where} ORDER BY id DESC LIMIT 500", $args ), ARRAY_A );
		return array_map( array( __CLASS__, 'shape' ), $rows );
	}

	/**
	 * Delete a prospect.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return (bool) $wpdb->delete( Schema::table( Schema::PROSPECTS ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Start outreach for a prospect with the matching type.
	 *
	 * @param int $id Prospect id.
	 * @return int|WP_Error Outreach id.
	 */
	public static function start_outreach( $id ) {
		$prospect = self::get( $id );
		if ( ! $prospect ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This prospect was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$outreach = Outreach::create(
			array(
				'prospect_id' => (int) $id,
				'type'        => self::KINDS[ $prospect['kind'] ],
				'name'        => $prospect['name'],
				'website'     => $prospect['website'],
				'notes'       => $prospect['reason'],
			)
		);
		if ( ! is_wp_error( $outreach ) ) {
			self::update( $id, array( 'status' => 'started' ) );
		}
		return $outreach;
	}

	/**
	 * Hosts of direct competitors (shared competitor list, from AI Writer's
	 * Competitor Watch when it is available).
	 *
	 * @return string[]
	 */
	public static function competitor_hosts() {
		/**
		 * Competitor hosts to leave out of prospects.
		 *
		 * @param string[] $hosts Hosts.
		 */
		$hosts = (array) apply_filters( 'qcld_wpbot_seo/competitor_hosts', array() );
		$out   = array();
		foreach ( $hosts as $host ) {
			$host = trim( (string) $host );
			$host = Link_Finder::host( preg_match( '#^https?://#i', $host ) ? $host : 'https://' . $host );
			if ( '' !== $host ) {
				$out[] = $host;
			}
		}
		// The shared list, Pro's list and AI Writer's can name the same site: once each.
		return array_values( array_unique( $out ) );
	}

	/**
	 * Pages of this site worth linking to (guides, data, tools), best first.
	 *
	 * @param int $limit Pages.
	 * @return array List of post_id, title, url, words, reason, links.
	 */
	public static function linkable( $limit = 20 ) {
		$posts  = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded scan of own pages for linkable content.
				'orderby'        => 'modified',
			)
		);
		$counts = Bing_Client::saved()['pages'] + Csv_Import::summary()['targets'];
		$out    = array();
		foreach ( $posts as $post ) {
			$words = str_word_count( wp_strip_all_tags( (string) $post->post_content ) );
			$url   = (string) get_permalink( $post );
			$kind  = preg_match( '/\b(guide|how to|how-to|statistics|stats|data|study|research|survey|report|checklist|template|tool|calculator|glossary|faq|resources?)\b/i', $post->post_title, $m ) ? strtolower( $m[1] ) : '';
			$links = isset( $counts[ $url ] ) ? (int) $counts[ $url ] : 0;
			$score = min( 4, $words / 600 ) + ( '' !== $kind ? 3 : 0 ) + min( 3, $links / 5 );
			if ( $score < 2.5 ) {
				continue;
			}
			$reason = array();
			if ( '' !== $kind ) {
				/* translators: %s: kind of page, such as guide or checklist. */
				$reason[] = sprintf( __( 'a %s other sites cite', 'chatbot' ), $kind );
			}
			if ( $words >= 1200 ) {
				/* translators: %s: number of words. */
				$reason[] = sprintf( __( 'in-depth (%s words)', 'chatbot' ), number_format_i18n( $words ) );
			}
			if ( $links > 0 ) {
				/* translators: %d: links. */
				$reason[] = sprintf( _n( 'already has %d link', 'already has %d links', $links, 'chatbot' ), $links );
			}
			$out[] = array(
				'post_id' => (int) $post->ID,
				'title'   => get_the_title( $post ),
				'url'     => $url,
				'words'   => $words,
				'links'   => $links,
				'score'   => round( $score, 1 ),
				'reason'  => implode( ', ', $reason ),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);
		return array_slice( $out, 0, $limit );
	}

	/**
	 * Clean fields.
	 *
	 * @param array $data Data.
	 * @return array Row.
	 */
	private static function clean( array $data ) {
		$website = isset( $data['website'] ) ? trim( (string) $data['website'] ) : '';
		if ( '' !== $website && ! preg_match( '#^https?://#i', $website ) ) {
			$website = 'https://' . $website;
		}
		$website = esc_url_raw( $website, array( 'http', 'https' ) );
		$page    = esc_url_raw( isset( $data['page_url'] ) ? trim( (string) $data['page_url'] ) : '', array( 'http', 'https' ) );
		if ( '' === $website && '' !== $page ) {
			$website = 'https://' . Link_Finder::host( $page ) . '/';
		}
		$kind  = isset( $data['kind'] ) && isset( self::KINDS[ $data['kind'] ] ) ? $data['kind'] : 'other';
		$flags = isset( $data['flags'] ) ? (array) ( is_string( $data['flags'] ) ? json_decode( $data['flags'], true ) : $data['flags'] ) : array();
		return array(
			'kind'        => $kind,
			'name'        => self::cut( sanitize_text_field( isset( $data['name'] ) ? (string) $data['name'] : '' ), 190 ),
			'website'     => $website,
			'host'        => Link_Finder::host( $website ),
			'page_url'    => $page,
			'contact_url' => esc_url_raw( isset( $data['contact_url'] ) ? trim( (string) $data['contact_url'] ) : '', array( 'http', 'https' ) ),
			'location'    => self::cut( sanitize_text_field( isset( $data['location'] ) ? (string) $data['location'] : '' ), 190 ),
			'topic'       => self::cut( sanitize_text_field( isset( $data['topic'] ) ? (string) $data['topic'] : '' ), 190 ),
			'reason'      => sanitize_textarea_field( isset( $data['reason'] ) ? (string) $data['reason'] : '' ),
			'flags'       => wp_json_encode( array_values( array_filter( array_map( 'sanitize_key', $flags ) ) ) ),
			'source'      => isset( $data['source'] ) && in_array( $data['source'], array( 'manual', 'ai', 'data' ), true ) ? $data['source'] : 'manual',
		);
	}

	/**
	 * Row for the app.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$row['id']    = (int) $row['id'];
		$flags        = json_decode( (string) $row['flags'], true );
		$row['flags'] = is_array( $flags ) ? $flags : array();
		$row['type']  = self::KINDS[ isset( self::KINDS[ $row['kind'] ] ) ? $row['kind'] : 'other' ];
		return $row;
	}

	/**
	 * Cut text.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function cut( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}
}
