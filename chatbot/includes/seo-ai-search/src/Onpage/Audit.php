<?php
/**
 * Audits pages with Checks and stores the result (post meta), on publish
 * or update (in the background, after the response) and on demand.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Onpage;

use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\WPBotCore\Jobs\Job_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Page audits.
 */
final class Audit {

	/**
	 * Post meta with the latest audit.
	 */
	const META = '_qcld_wpbot_seo_audit';

	/**
	 * Posts waiting for an audit at the end of this request.
	 *
	 * @var int[]
	 */
	private static $queue = array();

	/**
	 * Hook the audit on publish.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_save' ), 30, 2 );
		add_filter( 'qcld_wpbot_seo/page_data', array( __CLASS__, 'page_data' ), 10, 2 );
	}

	/**
	 * Job step handlers.
	 *
	 * @return array
	 */
	public static function handlers() {
		return array( 'seo_audit_batch' => array( __CLASS__, 'step_batch' ) );
	}

	/**
	 * Post types the module audits.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = array_values( array_filter( (array) Settings::get( 'automation_types' ), 'post_type_exists' ) );
		return array() === $types ? array( 'post', 'page' ) : $types;
	}

	/**
	 * After a published post is saved: audit it once the response is sent.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function on_save( $post_id, $post ) {
		if ( ! Settings::get( 'audit_on_publish' ) || ! $post instanceof \WP_Post || 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}
		if ( array() === self::$queue ) {
			add_action( 'shutdown', array( __CLASS__, 'run_queue' ) );
		}
		self::$queue[ (int) $post_id ] = (int) $post_id;
	}

	/**
	 * Audit the queued posts at shutdown (no AI calls, a few queries each).
	 *
	 * @return void
	 */
	public static function run_queue() {
		if ( function_exists( 'fastcgi_finish_request' ) && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			fastcgi_finish_request();
		}
		foreach ( self::$queue as $post_id ) {
			self::audit( $post_id );
		}
		self::$queue = array();
	}

	/**
	 * Audit one post and store the result.
	 *
	 * @param int    $post_id Post id.
	 * @param string $trigger publish, manual, scan, scheduled or fix (Page Audit history).
	 * @param string $keyword Target keyword for this audit ('' = the page's main search).
	 * @return array|null Audit (score, issues, words, checks, checked_at).
	 */
	public static function audit( $post_id, $trigger = 'publish', $keyword = '' ) {
		$page = self::page( $post_id );
		if ( null === $page ) {
			return null;
		}
		if ( '' !== trim( (string) $keyword ) ) {
			$page['main_query'] = sanitize_text_field( (string) $keyword );
		}
		$result               = Checks::run( $page );
		$result['checked_at'] = gmdate( 'c' );
		$result['main_query'] = $page['main_query'];
		update_post_meta( $post_id, self::META, wp_slash( wp_json_encode( $result ) ) );
		Audit_History::record( (int) $post_id, (string) $page['main_query'], (int) $result['score'], $result['checks'], $trigger );
		return $result;
	}

	/**
	 * Stored audit, or null.
	 *
	 * @param int $post_id Post id.
	 * @return array|null
	 */
	public static function stored( $post_id ) {
		$raw  = get_post_meta( $post_id, self::META, true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Data Checks need for one post.
	 *
	 * @param int $post_id Post id.
	 * @return array|null
	 */
	public static function page( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$seo         = Seo_Bridge::read( $post_id );
		$title       = '' !== $seo['title'] ? $seo['title'] : $post->post_title;
		$description = (string) $seo['description'];
		return array(
			'post_id'               => (int) $post_id,
			'title'                 => self::resolve_vars( $title, $post ),
			'seo_title'             => $seo['title'],
			'description'           => self::resolve_vars( $description, $post ),
			'content'               => (string) $post->post_content,
			'main_query'            => '' !== $seo['focus_keyword'] ? (string) $seo['focus_keyword'] : self::target( $post_id ),
			'inbound'               => self::inbound( $post ),
			'duplicate_title'       => self::duplicates( $post_id, 'title', $title, $post->post_title ),
			'duplicate_description' => '' !== $description ? self::duplicates( $post_id, 'description', $description, '' ) : 0,
			'host'                  => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'slug'                  => (string) $post->post_name,
		);
	}

	/**
	 * The page's target keyword when no SEO plugin focus keyword is set: the
	 * owner's own (manual or locked) main search in the shared keyword map,
	 * else the search that brings the page the most clicks.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function target( $post_id ) {
		if ( class_exists( '\\QuantumCloud\\WPBotCore\\Map\\Keyword_Map' ) ) {
			$main = \QuantumCloud\WPBotCore\Map\Keyword_Map::main_query( $post_id );
			if ( $main && ( 'manual' === $main['source'] || ! empty( $main['locked'] ) ) ) {
				return (string) $main['query'];
			}
		}
		return self::main_query( $post_id );
	}

	/**
	 * Search that brings the page the most clicks (then impressions), last 28 days.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function main_query( $post_id ) {
		global $wpdb;
		$url_id = Data_Store::url_for_post( $post_id );
		if ( ! $url_id ) {
			return '';
		}
		$period  = Period::last( 28 );
		$daily   = \QuantumCloud\SeoAiSearch\Storage\Schema::table( \QuantumCloud\SeoAiSearch\Storage\Schema::GSC_DAILY );
		$queries = \QuantumCloud\SeoAiSearch\Storage\Schema::table( \QuantumCloud\SeoAiSearch\Storage\Schema::QUERIES );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names from constants.
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT q.query FROM {$daily} d JOIN {$queries} q ON q.id = d.query_id WHERE d.url_id = %d AND d.query_id > 0 AND d.day BETWEEN %s AND %s GROUP BY d.query_id ORDER BY SUM(d.clicks) DESC, SUM(d.impressions) DESC LIMIT 1", $url_id, $period['start'], $period['end'] ) );
	}

	/**
	 * Published posts that link to this one (by permalink path).
	 *
	 * @param \WP_Post $post Post.
	 * @return int
	 */
	public static function inbound( \WP_Post $post ) {
		global $wpdb;
		$path = (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
		$id   = '?p=' . $post->ID;
		if ( '' === $path || '/' === $path ) {
			return -1;
		}
		$types = self::post_types();
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders built for the post types; values passed as one array.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND ID <> %d AND post_type IN ({$in}) AND (post_content LIKE %s OR post_content LIKE %s)",
				array_merge( array( $post->ID ), $types, array( '%href="%' . $wpdb->esc_like( untrailingslashit( $path ) ) . '%', '%' . $wpdb->esc_like( $id ) . '"%' ) )
			)
		);
		// phpcs:enable
	}

	/**
	 * Other published pages with the same title or description.
	 *
	 * @param int    $post_id    Post id.
	 * @param string $field      title|description.
	 * @param string $value      Value.
	 * @param string $post_title Post title (title fallback).
	 * @return int
	 */
	public static function duplicates( $post_id, $field, $value, $post_title ) {
		global $wpdb;
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		$plugin = Seo_Plugins::active();
		$keys   = array(
			'title'       => isset( Seo_Bridge::KEYS[ $plugin ]['title'] ) ? Seo_Bridge::KEYS[ $plugin ]['title'] : Seo_Bridge::OWN_TITLE,
			'description' => isset( Seo_Bridge::KEYS[ $plugin ]['description'] ) ? Seo_Bridge::KEYS[ $plugin ]['description'] : Seo_Bridge::OWN_DESCRIPTION,
		);
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value = %s AND pm.post_id <> %d AND p.post_status = 'publish'", $keys[ $field ], $value, $post_id ) );
		if ( 'title' === $field && $value === $post_title ) {
			$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title = %s AND ID <> %d AND post_status = 'publish' AND post_type IN ('post','page')", $value, $post_id ) );
		}
		// phpcs:enable
		return $count;
	}

	/**
	 * Start a site-wide audit in the job queue (20 posts per step).
	 *
	 * @param string $trigger scan or scheduled (Page Audit history).
	 * @return int|\WP_Error Job id.
	 */
	public static function start_scan( $trigger = 'scan' ) {
		$ids   = get_posts(
			array(
				'post_type'      => self::post_types(),
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 5000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Ids only; audited 20 per job step.
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$steps = array();
		foreach ( array_chunk( $ids, 20 ) as $index => $chunk ) {
			$steps[] = array(
				'kind'  => 'seo_audit_batch',
				/* translators: %d: batch number. */
				'label' => sprintf( __( 'Check pages, batch %d', 'chatbot' ), $index + 1 ),
				'input' => array(
					'ids'     => $chunk,
					'trigger' => 'scheduled' === $trigger ? 'scheduled' : 'scan',
				),
			);
		}
		if ( array() === $steps ) {
			return new \WP_Error( 'qcld_wpbot_seo_nothing', __( 'There are no published pages to check.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return (int) Job_Store::create( Module::ID, 'audit', array( 'title' => __( 'Check every page', 'chatbot' ) ), $steps );
	}

	/**
	 * Job step: audit a batch.
	 *
	 * @param array $step Step.
	 * @return array
	 */
	public static function step_batch( array $step ) {
		$done = 0;
		foreach ( (array) $step['input']['ids'] as $post_id ) {
			if ( self::audit( (int) $post_id, isset( $step['input']['trigger'] ) ? (string) $step['input']['trigger'] : 'scan' ) ) {
				++$done;
			}
		}
		return array( 'audited' => $done );
	}

	/**
	 * Fill audit fields of qcld_wpbot_seo_get_page_data().
	 *
	 * @param array $data    Page data.
	 * @param int   $post_id Post id.
	 * @return array
	 */
	public static function page_data( $data, $post_id ) {
		$audit = self::stored( $post_id );
		if ( $audit ) {
			$data['audit_score'] = (int) $audit['score'];
			$data['open_issues'] = count( $audit['issues'] );
			if ( '' === $data['main_query'] && ! empty( $audit['main_query'] ) ) {
				$data['main_query'] = (string) $audit['main_query'];
			}
		}
		return $data;
	}

	/**
	 * Replace common SEO plugin variables with real values for length checks.
	 *
	 * @param string   $text Text with %%title%% or %title% style variables.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function resolve_vars( $text, \WP_Post $post ) {
		$map = array(
			'%%title%%'     => $post->post_title,
			'%title%'       => $post->post_title,
			'#post_title'   => $post->post_title,
			'%%sitename%%'  => get_bloginfo( 'name' ),
			'%sitename%'    => get_bloginfo( 'name' ),
			'#site_title'   => get_bloginfo( 'name' ),
			'%%sep%%'       => '-',
			'%sep%'         => '-',
			'#separator_sa' => '-',
		);
		return trim( str_replace( array_keys( $map ), array_values( $map ), (string) $text ) );
	}
}
