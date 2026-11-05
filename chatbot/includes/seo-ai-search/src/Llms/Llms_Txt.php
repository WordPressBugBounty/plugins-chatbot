<?php
/**
 * The llms.txt file under Technical SEO (round 2, R4, Free). WordPress serves
 * /llms.txt live from the database: no file is written to the server, so a
 * deleted page can never stay listed. A cached copy is rebuilt when content
 * changes (publish, edit, unpublish, trash, delete, permalinks, the Topic
 * Map, the settings); until then the next request rebuilds it on the spot.
 * Each rebuild asks the common caching plugins to drop their copy.
 *
 * Off until the owner turns it on. Conflicts are reported, never "fixed":
 * a real llms.txt file on the server, or another plugin generating one.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Llms;

use QuantumCloud\SeoAiSearch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Serving and upkeep of llms.txt.
 */
final class Llms_Txt {

	const CACHE   = 'qcld_wpbot_seo_llms_cache';
	const CHANGED = 'qcld_wpbot_seo_llms_changed';
	const REBUILD = 'qcld_wpbot_seo_llms_rebuild';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'parse_request', array( __CLASS__, 'serve' ), 0 );
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'changed_post' ) );
		}
		add_action( 'transition_post_status', array( __CLASS__, 'transition' ), 10, 3 );
		add_action( 'update_option_permalink_structure', array( __CLASS__, 'dirty' ) );
		add_action( 'update_option_blogname', array( __CLASS__, 'dirty' ) );
		add_action( 'update_option_blogdescription', array( __CLASS__, 'dirty' ) );
		add_action( 'update_option_page_on_front', array( __CLASS__, 'dirty' ) );
		add_action( 'wp_update_nav_menu', array( __CLASS__, 'dirty' ) );
		add_action( 'qcld_wpbot_core/topic_map_changed', array( __CLASS__, 'dirty' ) );
		add_action( 'update_option_' . Settings::OPTION, array( __CLASS__, 'dirty' ) );
		add_action( self::REBUILD, array( __CLASS__, 'rebuild' ) );
		Llms_Check::hook();
	}

	/**
	 * Is llms.txt turned on?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) Settings::get( 'llms_enabled' );
	}

	/**
	 * Public address.
	 *
	 * @return string
	 */
	public static function url() {
		return home_url( '/llms.txt' );
	}

	/**
	 * Answer /llms.txt (and let extensions answer their own paths, such as Markdown copies).
	 *
	 * @param \WP $wp Request.
	 * @return void
	 */
	public static function serve( $wp ) {
		$path = trim( (string) $wp->request, '/' );
		/**
		 * Other files served from the content (Pro: Markdown copies). Return true when the request was answered.
		 *
		 * @param bool   $done Answered.
		 * @param string $path Request path.
		 */
		if ( 'llms.txt' !== $path ) {
			if ( apply_filters( 'qcld_wpbot_seo/llms_serve', false, $path ) ) {
				exit;
			}
			return;
		}
		if ( ! self::enabled() ) {
			return;
		}
		$cache = self::current();
		$etag  = '"' . $cache['hash'] . '"';
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		// Short browser and proxy cache; the ETag lets caches check cheaply.
		header( 'Cache-Control: public, max-age=300' );
		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', strtotime( $cache['built_at'] ) ) . ' GMT' );
		$match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) ) : '';
		if ( $match === $etag ) {
			status_header( 304 );
			exit;
		}
		status_header( 200 );
		echo $cache['text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text file built from cleaned titles, URLs and descriptions.
		exit;
	}

	/**
	 * The cached file, rebuilt first when content changed since.
	 *
	 * @return array text, hash, built_at, built_ts, count, left_out.
	 */
	public static function current() {
		$cache   = get_option( self::CACHE );
		$changed = (float) get_option( self::CHANGED, 0 );
		if ( ! is_array( $cache ) || ! isset( $cache['text'], $cache['built_ts'] ) || $changed > (float) $cache['built_ts'] ) {
			$cache = self::rebuild();
		}
		return $cache;
	}

	/**
	 * Rebuild now and tell the caching plugins.
	 *
	 * @return array The cache.
	 */
	public static function rebuild() {
		$built = Llms_Builder::build();
		$old   = get_option( self::CACHE );
		$cache = array(
			'text'     => $built['text'],
			'hash'     => md5( $built['text'] ),
			'built_at' => gmdate( 'c' ),
			'built_ts' => microtime( true ),
			'count'    => $built['count'],
			'ids'      => $built['ids'],
			'left_out' => array_slice( $built['left_out'], 0, 200, true ),
		);
		update_option( self::CACHE, $cache, false );
		if ( ! is_array( $old ) || ! isset( $old['hash'] ) || $old['hash'] !== $cache['hash'] ) {
			self::purge();
			/**
			 * The llms.txt file changed (the live check runs shortly after).
			 *
			 * @param array $cache The new cache.
			 */
			do_action( 'qcld_wpbot_seo/llms_txt_rebuilt', $cache );
		}
		return $cache;
	}

	/**
	 * Mark the cache out of date and rebuild within a minute (several changes rebuild once; a request before that rebuilds at once).
	 *
	 * @return void
	 */
	public static function dirty() {
		if ( ! self::enabled() ) {
			return;
		}
		// A time stamp of its own: always written, so a change is never lost to a cached copy of the file.
		update_option( self::CHANGED, microtime( true ), false );
		if ( ! wp_next_scheduled( self::REBUILD ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::REBUILD );
		}
	}

	/**
	 * A post was saved, deleted, trashed or restored.
	 *
	 * @param int $post_id Post.
	 * @return void
	 */
	public static function changed_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$type = get_post_type( $post_id );
		if ( false === $type || in_array( $type, Llms_Rules::types(), true ) || 'nav_menu_item' === $type ) {
			self::dirty();
		}
	}

	/**
	 * A post changed status (published, unpublished, made private).
	 *
	 * @param string   $new_status New.
	 * @param string   $old_status Old.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function transition( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
			self::changed_post( $post->ID );
		}
	}

	/**
	 * Ask the common caching plugins to drop their copy of the file.
	 *
	 * @return string[] Plugins asked.
	 */
	public static function purge() {
		$url   = self::url();
		$asked = array();
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( array( $url ) );
			$asked[] = 'WP Rocket';
		}
		if ( has_action( 'litespeed_purge_url' ) ) {
			do_action( 'litespeed_purge_url', $url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own purge action.
			$asked[] = 'LiteSpeed Cache';
		}
		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			wpsc_delete_url_cache( $url );
			$asked[] = 'WP Super Cache';
		}
		if ( function_exists( 'w3tc_flush_url' ) ) {
			w3tc_flush_url( $url );
			$asked[] = 'W3 Total Cache';
		}
		/**
		 * Other caches can drop their copy here.
		 *
		 * @param string $url llms.txt address.
		 */
		do_action( 'qcld_wpbot_seo/llms_txt_purge', $url );
		return $asked;
	}

	/**
	 * Things that stop WPBot's llms.txt from being the one visitors get.
	 *
	 * @return array[] id, message.
	 */
	public static function conflicts() {
		$out  = array();
		$file = trailingslashit( ABSPATH ) . 'llms.txt';
		if ( file_exists( $file ) ) {
			$out[] = array(
				'id'      => 'file',
				'message' => __( 'A real llms.txt file is saved on your server, so your web server sends it instead of this one. WPBot never deletes it: remove or rename it yourself (or with your host) to use this one.', 'chatbot' ),
			);
		}
		$yoast = get_option( 'wpseo' );
		if ( is_array( $yoast ) && ! empty( $yoast['enable_llms_txt'] ) ) {
			$out[] = array(
				'id'      => 'yoast',
				'message' => __( 'Yoast SEO also generates llms.txt. Two generators fight over one address: turn one of them off.', 'chatbot' ),
			);
		}
		if ( in_array( 'llms-txt', (array) get_option( 'rank_math_modules', array() ), true ) ) {
			$out[] = array(
				'id'      => 'rankmath',
				'message' => __( 'Rank Math also generates llms.txt. Two generators fight over one address: turn one of them off.', 'chatbot' ),
			);
		}
		if ( '' !== (string) wp_parse_url( home_url(), PHP_URL_PATH ) && '/' !== (string) wp_parse_url( home_url(), PHP_URL_PATH ) ) {
			$out[] = array(
				'id'      => 'subfolder',
				'message' => __( 'Your site lives in a folder, so the file is at the folder\'s address, not the root of the domain. Agents look at the root first.', 'chatbot' ),
			);
		}
		return $out;
	}
}
