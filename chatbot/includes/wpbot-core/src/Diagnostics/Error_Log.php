<?php
/**
 * WPBot's error log (owner, 11 Nov 2026): every error from every part of WPBot, not only AI,
 * so a site owner can send it to WPBot support with a description of the problem.
 *
 * - One row per distinct error (a fingerprint of where and what); repeats raise its count and
 *   last-seen time instead of adding rows.
 * - Kept 30 days and at most 1,000 rows (filters qcld_wpbot_core/error_log_days and
 *   qcld_wpbot_core/error_log_rows); pruned daily.
 * - Before anything is stored, secrets are removed (Scrub): API keys, tokens, passwords,
 *   authorization headers and email addresses. Visitor messages are never logged.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Diagnostics;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Error log storage.
 */
final class Error_Log {

	/**
	 * Table name (without prefix).
	 */
	const TABLE = 'qcld_wpbot_core_errors';

	/**
	 * Schema version option.
	 */
	const DB_OPTION = 'qcld_wpbot_core_errors_db_version';

	/**
	 * Schema version.
	 */
	const DB_VERSION = '1';

	/**
	 * Settings option.
	 */
	const SETTINGS = 'qcld_wpbot_core_errors_settings';

	/**
	 * Daily prune event.
	 */
	const CRON = 'qcld_wpbot_core_errors_daily';

	/**
	 * Sources (where an error came from) and their labels.
	 */
	const SOURCES = array( 'php', 'fatal', 'http', 'ai', 'job', 'mail', 'js', 'other' );

	/**
	 * Levels.
	 */
	const LEVELS = array( 'error', 'warning', 'notice' );

	/**
	 * At most this many writes per request (a loop of warnings must not slow a page down).
	 */
	const MAX_WRITES = 25;

	/**
	 * Writes made on this request, by fingerprint.
	 *
	 * @var array<string,bool>
	 */
	private static $written = array();

	/**
	 * Guard against logging while logging.
	 *
	 * @var bool
	 */
	private static $busy = false;

	/**
	 * Hook the installer and the daily prune.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 6 );
		add_action( self::CRON, array( __CLASS__, 'prune' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
	}

	/**
	 * Schedule the daily prune.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		return Schema::table( self::TABLE );
	}

	/**
	 * Create the table when missing or older.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fingerprint char(40) NOT NULL,
			level varchar(10) NOT NULL DEFAULT 'error',
			source varchar(20) NOT NULL DEFAULT 'other',
			module varchar(40) NOT NULL DEFAULT '',
			message text NOT NULL,
			detail longtext NULL,
			hits int(10) unsigned NOT NULL DEFAULT 1,
			first_seen datetime NOT NULL,
			last_seen datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY fingerprint (fingerprint),
			KEY last_seen (last_seen)
			) {$charset};"
		);
		update_option( self::DB_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Settings: notices (also record PHP notices and deprecations), browser (record browser
	 * errors of the chat widget and WPBot's admin screens).
	 *
	 * @return array{notices:bool,browser:bool}
	 */
	public static function settings() {
		$saved = get_option( self::SETTINGS, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'notices' => ! empty( $saved['notices'] ),
			'browser' => ! array_key_exists( 'browser', $saved ) || ! empty( $saved['browser'] ),
		);
	}

	/**
	 * Save the settings.
	 *
	 * @param array $settings notices, browser.
	 * @return void
	 */
	public static function save_settings( array $settings ) {
		update_option(
			self::SETTINGS,
			array(
				'notices' => ! empty( $settings['notices'] ),
				'browser' => ! empty( $settings['browser'] ),
			),
			false
		);
	}

	/**
	 * Record an error.
	 *
	 * @param string $source  One of SOURCES.
	 * @param string $message What happened (secrets are removed).
	 * @param array  $detail  Optional: level, module, file, line, url, host, status, stack, code, context (array).
	 * @return bool Whether it was stored.
	 */
	public static function add( $source, $message, array $detail = array() ) {
		if ( self::$busy || get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			return false;
		}
		self::$busy = true;
		try {
			return self::write( $source, $message, $detail );
		} catch ( \Throwable $e ) {
			return false;
		} finally {
			self::$busy = false;
		}
	}

	/**
	 * Store one error (see add()).
	 *
	 * @param string $source  Source.
	 * @param string $message Message.
	 * @param array  $detail  Detail.
	 * @return bool
	 */
	private static function write( $source, $message, array $detail ) {
		global $wpdb;
		$source  = in_array( $source, self::SOURCES, true ) ? $source : 'other';
		$level   = isset( $detail['level'] ) && in_array( $detail['level'], self::LEVELS, true ) ? $detail['level'] : 'error';
		$module  = isset( $detail['module'] ) ? substr( sanitize_key( (string) $detail['module'] ), 0, 40 ) : '';
		$message = Scrub::text( $message, 1000 );
		if ( '' === $message ) {
			return false;
		}
		unset( $detail['level'], $detail['module'] );
		if ( isset( $detail['file'] ) ) {
			$detail['file'] = Scrub::path( (string) $detail['file'] );
		}
		if ( '' === $module && ! empty( $detail['file'] ) ) {
			$module = self::module_of( (string) $detail['file'] );
		}
		if ( ! isset( $detail['url'] ) ) {
			$detail['url'] = self::request_path();
		}
		$detail = Scrub::detail( $detail );
		$where  = isset( $detail['file'] ) ? $detail['file'] . ':' . ( isset( $detail['line'] ) ? (int) $detail['line'] : 0 ) : ( isset( $detail['host'] ) ? (string) $detail['host'] : '' );
		$print  = sha1( $source . '|' . $level . '|' . $where . '|' . preg_replace( '/\d+/', '#', $message ) );
		if ( isset( self::$written[ $print ] ) || count( self::$written ) >= self::MAX_WRITES ) {
			return false;
		}
		self::$written[ $print ] = true;
		$now                     = current_time( 'mysql', true );
		$table                   = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; one atomic insert-or-count.
		$done = $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (fingerprint, level, source, module, message, detail, hits, first_seen, last_seen) VALUES (%s, %s, %s, %s, %s, %s, 1, %s, %s) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), detail = VALUES(detail)", $print, $level, $source, $module, $message, (string) wp_json_encode( $detail ), $now, $now ) );
		return false !== $done;
	}

	/**
	 * Errors, newest first.
	 *
	 * @param array $args source ('' for all), level, page (from 1), per_page (max 200), since (GMT datetime).
	 * @return array{items:array[],total:int}
	 */
	public static function items( array $args = array() ) {
		global $wpdb;
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}
		$table  = self::table();
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['source'] ) && in_array( $args['source'], self::SOURCES, true ) ) {
			$where[]  = 'source = %s';
			$params[] = $args['source'];
		}
		if ( ! empty( $args['level'] ) && in_array( $args['level'], self::LEVELS, true ) ) {
			$where[]  = 'level = %s';
			$params[] = $args['level'];
		}
		if ( ! empty( $args['since'] ) ) {
			$where[]  = 'last_seen >= %s';
			$params[] = (string) $args['since'];
		}
		$per   = min( 200, max( 1, isset( $args['per_page'] ) ? (int) $args['per_page'] : 50 ) );
		$page  = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$sql   = implode( ' AND ', $where );
		$count = "SELECT COUNT(*) FROM {$table} WHERE {$sql}";
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count, $params ) : $count ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; values prepared.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$sql} ORDER BY last_seen DESC, id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; values prepared (the WHERE list is built above).
		$items = array();
		foreach ( (array) $rows as $row ) {
			$detail  = json_decode( (string) $row['detail'], true );
			$items[] = array(
				'id'         => (int) $row['id'],
				'level'      => (string) $row['level'],
				'source'     => (string) $row['source'],
				'module'     => (string) $row['module'],
				'message'    => (string) $row['message'],
				'detail'     => is_array( $detail ) ? $detail : array(),
				'hits'       => (int) $row['hits'],
				'first_seen' => (string) $row['first_seen'],
				'last_seen'  => (string) $row['last_seen'],
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Number of errors seen since a time.
	 *
	 * @param int $seconds Seconds back.
	 * @return int
	 */
	public static function count_recent( $seconds = WEEK_IN_SECONDS ) {
		$found = self::items(
			array(
				'since'    => gmdate( 'Y-m-d H:i:s', time() - (int) $seconds ),
				'per_page' => 1,
			)
		);
		return $found['total'];
	}

	/**
	 * Delete every error.
	 *
	 * @return void
	 */
	public static function clear() {
		global $wpdb;
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			$wpdb->query( 'DELETE FROM ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		}
	}

	/**
	 * Delete errors older than the retention and beyond the row cap.
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			return;
		}
		$table = self::table();
		/**
		 * Days an error stays in the log.
		 *
		 * @param int $days Days (default 30).
		 */
		$days = max( 1, (int) apply_filters( 'qcld_wpbot_core/error_log_days', 30 ) );
		/**
		 * Most errors the log keeps.
		 *
		 * @param int $rows Rows (default 1000).
		 */
		$rows = max( 50, (int) apply_filters( 'qcld_wpbot_core/error_log_rows', 1000 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < %s", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		$cut = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} ORDER BY last_seen DESC, id DESC LIMIT 1 OFFSET %d", $rows ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		if ( $cut ) {
			$last = (string) $wpdb->get_var( $wpdb->prepare( "SELECT last_seen FROM {$table} WHERE id = %d", $cut ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < %s OR ( last_seen = %s AND id <= %d )", $last, $last, $cut ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table.
		}
	}

	/**
	 * The WPBot part a file belongs to: the folder after includes/ or addons/ ("chatbot",
	 * "voice-addon"), else "core" for files of the host plugin itself.
	 *
	 * @param string $file Path relative to a WPBot folder, or absolute.
	 * @return string
	 */
	public static function module_of( $file ) {
		$file = str_replace( '\\', '/', (string) $file );
		if ( preg_match( '#(?:^|/)(?:includes|addons)/([a-z0-9_\-]+)/#i', $file, $m ) ) {
			return substr( sanitize_key( $m[1] ), 0, 40 );
		}
		return 'core';
	}

	/**
	 * The current request's path (no query string: it can hold tokens).
	 *
	 * @return string
	 */
	public static function request_path() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp-cli';
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return 'wp-cron';
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		// admin-ajax and admin pages: keep which action or page ran (not other parameters).
		// phpcs:disable WordPress.Security.NonceVerification -- Read-only: names the request in the log.
		if ( isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ) {
			$path .= '?action=' . sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( isset( $_GET['page'] ) && is_string( $_GET['page'] ) ) {
			$path .= '?page=' . sanitize_key( wp_unslash( $_GET['page'] ) );
		}
		// phpcs:enable
		return substr( $path, 0, 300 );
	}
}
