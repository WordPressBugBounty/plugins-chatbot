<?php
/**
 * The support report: one text file with the owner's description of the problem, the site's
 * setup (WordPress, PHP, WPBot and module versions, active plugins, which WPBot features are on;
 * never keys) and the error log. The owner downloads it, or sends it to WPBot support
 * (help@wpbot.pro) after ticking the consent box. The owner's email address goes with it only
 * when they tick "Should we contact you after we fix the issue?" (owner, 12 Nov 2026).
 *
 * Admin actions (admin-post.php, manage_options, nonces): download, send, clear the log, save
 * the error log settings.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Diagnostics;

use QuantumCloud\WPBotCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Support report.
 */
final class Support_Report {

	/**
	 * Nonce action of the forms.
	 */
	const NONCE = 'qcld_wpbot_core_error_log';

	/**
	 * Where reports are sent.
	 */
	const EMAIL = 'help@wpbot.pro';

	/**
	 * Hook the admin actions.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_post_qcld_wpbot_core_support_download', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_qcld_wpbot_core_support_send', array( __CLASS__, 'send' ) );
		add_action( 'admin_post_qcld_wpbot_core_errors_clear', array( __CLASS__, 'clear' ) );
		add_action( 'admin_post_qcld_wpbot_core_errors_settings', array( __CLASS__, 'save_settings' ) );
	}

	/**
	 * The support address.
	 *
	 * @return string
	 */
	public static function email() {
		/**
		 * Where support reports are sent.
		 *
		 * @param string $email Default help@wpbot.pro.
		 */
		$email = (string) apply_filters( 'qcld_wpbot_core/support_email', self::EMAIL );
		return is_email( $email ) ? $email : self::EMAIL;
	}

	/**
	 * Build the report.
	 *
	 * @param string $description The owner's description of the problem.
	 * @return string Plain text.
	 */
	public static function build( $description = '' ) {
		$lines   = array();
		$lines[] = 'WPBot support report';
		$lines[] = str_repeat( '=', 20 );
		$lines[] = 'Created: ' . gmdate( 'Y-m-d H:i' ) . ' UTC';
		$lines[] = 'Site: ' . home_url();
		$lines[] = '';
		$lines[] = 'What happened (from the site owner)';
		$lines[] = str_repeat( '-', 35 );
		$lines[] = '' !== trim( (string) $description ) ? Scrub::text( $description, 5000 ) : '(no description)';
		$lines[] = '';
		foreach ( self::sections() as $title => $rows ) {
			$lines[] = $title;
			$lines[] = str_repeat( '-', strlen( $title ) );
			foreach ( (array) $rows as $label => $value ) {
				$lines[] = is_int( $label ) ? Scrub::text( (string) $value, 500 ) : $label . ': ' . Scrub::text( (string) $value, 500 );
			}
			$lines[] = '';
		}
		$errors  = Error_Log::items( array( 'per_page' => 200 ) );
		$title   = sprintf( 'Error log (%d, newest first%s)', $errors['total'], $errors['total'] > 200 ? ', 200 shown' : '' );
		$lines[] = $title;
		$lines[] = str_repeat( '-', strlen( $title ) );
		if ( ! $errors['items'] ) {
			$lines[] = '(empty)';
		}
		foreach ( $errors['items'] as $item ) {
			$where   = isset( $item['detail']['file'] ) && '' !== $item['detail']['file'] ? ' at ' . $item['detail']['file'] . ( ! empty( $item['detail']['line'] ) ? ':' . (int) $item['detail']['line'] : '' ) : '';
			$lines[] = sprintf( '[%1$s UTC] %2$s %3$s/%4$s x%5$d: %6$s%7$s', $item['last_seen'], strtoupper( $item['level'] ), $item['source'], '' !== $item['module'] ? $item['module'] : '-', $item['hits'], $item['message'], $where );
			if ( ! empty( $item['detail']['url'] ) ) {
				$lines[] = '    request: ' . $item['detail']['url'];
			}
			if ( ! empty( $item['detail']['stack'] ) && in_array( $item['source'], array( 'fatal', 'js' ), true ) ) {
				foreach ( array_slice( explode( "\n", (string) $item['detail']['stack'] ), 0, 8 ) as $frame ) {
					$lines[] = '    ' . trim( $frame );
				}
			}
			$lines[] = '    first seen: ' . $item['first_seen'] . ' UTC';
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Report sections: title => label => value (or a list of lines).
	 *
	 * @return array<string,array>
	 */
	public static function sections() {
		global $wpdb, $wp_version;
		$theme   = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$host    = Core::instance()->host();
		$plugins = array();
		if ( ! function_exists( 'get_plugins' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( function_exists( 'get_plugins' ) ) {
			$all = get_plugins();
			foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
				if ( isset( $all[ $file ] ) ) {
					$plugins[] = $all[ $file ]['Name'] . ' ' . $all[ $file ]['Version'] . ' (' . $file . ')';
				}
			}
		}
		$sections = array(
			'WordPress and server' => array(
				'WordPress'          => (string) $wp_version,
				'PHP'                => PHP_VERSION,
				'Database'           => method_exists( $wpdb, 'db_version' ) ? (string) $wpdb->db_version() : '',
				'Web server'         => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
				'Memory limit'       => (string) ini_get( 'memory_limit' ) . ( defined( 'WP_MEMORY_LIMIT' ) ? ' (WP_MEMORY_LIMIT ' . WP_MEMORY_LIMIT . ')' : '' ),
				'Max execution time' => (string) ini_get( 'max_execution_time' ),
				'Multisite'          => is_multisite() ? 'yes' : 'no',
				'Language'           => get_locale(),
				'Time zone'          => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '',
				'WP_DEBUG'           => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'on' : 'off',
				'WP-Cron'            => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'disabled (DISABLE_WP_CRON)' : 'on',
				'Theme'              => $theme ? $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) : '',
			),
			'WPBot'                => array(
				'Plugin'     => $host ? $host->get_name() . ' (' . $host->get_id() . ')' : '',
				'WPBot Core' => defined( 'QCLD_WPBOT_CORE_VERSION' ) ? QCLD_WPBOT_CORE_VERSION : '',
			),
			'Active plugins'       => $plugins,
		);
		/**
		 * Sections of the support report: title => array of label => value, or a list of lines.
		 * Modules add their settings here. Never add keys, tokens or visitor data.
		 *
		 * @param array<string,array> $sections Sections.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/support_report', $sections );
	}

	/**
	 * Download the report as a text file.
	 *
	 * @return void
	 */
	public static function download() {
		self::check();
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check() verified the nonce.
		$report      = self::build( $description );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . self::filename() . '"' );
		echo $report; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A plain-text download, scrubbed.
		exit;
	}

	/**
	 * Send the report to WPBot support.
	 *
	 * @return void
	 */
	public static function send() {
		self::check();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check() verified the nonce.
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$consent     = ! empty( $_POST['consent'] );
		$contact     = ! empty( $_POST['contact'] );
		$email       = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
		// phpcs:enable
		if ( '' === trim( $description ) || ! $consent ) {
			self::back( '' === trim( $description ) ? 'need_description' : 'need_consent' );
		}
		if ( $contact && ! is_email( $email ) ) {
			self::back( 'need_email' );
		}
		$user   = wp_get_current_user();
		$report = self::build( $description );
		$file   = trailingslashit( get_temp_dir() ) . wp_unique_filename( get_temp_dir(), self::filename() );
		$saved  = false !== file_put_contents( $file, $report ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A temporary attachment, deleted right after sending.
		$site   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$first  = function_exists( 'mb_substr' ) ? mb_substr( $description, 0, 60 ) : substr( $description, 0, 60 );
		$mail   = self::message( $description, $report, $saved, $contact ? $email : '', (string) $user->display_name );
		$sent   = wp_mail(
			self::email(),
			sprintf( 'WPBot support report: %1$s - %2$s', $site, $first ),
			$mail['body'],
			$mail['headers'],
			$saved ? array( $file ) : array()
		);
		if ( $saved ) {
			wp_delete_file( $file );
		}
		self::back( $sent ? ( $contact ? 'sent' : 'sent_no_contact' ) : 'not_sent' );
	}

	/**
	 * The support email's body and headers. The owner's email address goes with the report only when
	 * they asked to be contacted ($contact not empty): in the body and as Reply-To.
	 *
	 * @param string $description What happened.
	 * @param string $report      The report text.
	 * @param bool   $attached    Whether the report is attached (else it is added to the body).
	 * @param string $contact     The owner's email address, or '' when they asked not to be contacted.
	 * @param string $name        The owner's display name.
	 * @return array{body:string,headers:string[]}
	 */
	public static function message( $description, $report, $attached, $contact = '', $name = '' ) {
		$name = trim( str_replace( array( "\r", "\n", '<', '>' ), '', (string) $name ) );
		$from = '' !== $contact ? 'Contact: ' . ( '' !== $name ? $name . ' ' : '' ) . '<' . $contact . ">\n" : "Contact: not requested (the owner asked not to be contacted)\n";
		$body = "A WPBot site owner sent a support report.\n\nSite: " . home_url() . "\n" . $from . "\nWhat happened:\n" . $description . "\n\nThe full report is attached" . ( $attached ? '' : ' below' ) . ".\n" . ( $attached ? '' : "\n" . $report );
		return array(
			'body'    => $body,
			'headers' => '' !== $contact ? array( 'Reply-To: ' . ( '' !== $name ? $name . ' ' : '' ) . '<' . $contact . '>' ) : array(),
		);
	}

	/**
	 * Clear the error log.
	 *
	 * @return void
	 */
	public static function clear() {
		self::check();
		Error_Log::clear();
		self::back( 'cleared' );
	}

	/**
	 * Save the error log settings.
	 *
	 * @return void
	 */
	public static function save_settings() {
		self::check();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check() verified the nonce.
		Error_Log::save_settings(
			array(
				'notices' => ! empty( $_POST['notices'] ),
				'browser' => ! empty( $_POST['browser'] ),
			)
		);
		// phpcs:enable
		self::back( 'saved' );
	}

	/**
	 * File name of a report.
	 *
	 * @return string
	 */
	public static function filename() {
		return 'wpbot-support-report-' . sanitize_title( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '-' . gmdate( 'Ymd-His' ) . '.txt';
	}

	/**
	 * Rights and nonce.
	 *
	 * @return void
	 */
	private static function check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'chatbot' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );
	}

	/**
	 * Back to the screen the form was on, with a result.
	 *
	 * @param string $result Result code.
	 * @return void
	 */
	private static function back( $result ) {
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check() verified the nonce.
		$back = wp_validate_redirect( $back, admin_url() );
		wp_safe_redirect( add_query_arg( 'qcld_error_log', rawurlencode( $result ), $back ) );
		exit;
	}
}
