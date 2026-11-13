<?php
/**
 * Email reports: a daily job (about 6:00 site time) sends the weekly or
 * monthly report when it is due, plus "Send a test now" and a preview for
 * the Settings screen.
 *
 * A feature that sends its own, fuller report (for example a Pro version)
 * turns this one off with the filter qcin/email_reports; it can read the
 * same settings (Report_Settings::all()) so nothing has to be set up twice.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Reports;

use QuantumCloud\Insights\Admin\Admin_Controller;
use QuantumCloud\Insights\Module;
use QuantumCloud\Insights\Processing\Health_Check;

defined( 'ABSPATH' ) || exit;

final class Reports {

	const HOOK = 'qcin_email_report';
	const HOUR = 6;
	const LOCK = 'qcin_lock_email_report';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( 'admin_post_qcin_report_preview', array( $this, 'preview' ) );
		add_filter( 'qcin/health_jobs', array( $this, 'health_job' ) );
	}

	/**
	 * Whether the core report is in use (a feature may replace it).
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Whether the core sends email reports. A feature that sends its own
		 * report returns false; the core then hides its Settings section too.
		 *
		 * @param bool $on On.
		 */
		return (bool) apply_filters( 'qcin/email_reports', true );
	}

	/**
	 * Schedule the daily job at the next 6:00 site time.
	 */
	public function ensure_scheduled() {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$tz   = wp_timezone();
		$next = ( new \DateTimeImmutable( 'now', $tz ) )->setTime( self::HOUR, 0 );
		if ( $next->getTimestamp() <= time() ) {
			$next = $next->modify( '+1 day' );
		}
		wp_schedule_event( $next->getTimestamp(), 'daily', self::HOOK );
	}

	/**
	 * Show the job in the health report while reports are on.
	 *
	 * @param string[] $jobs Jobs.
	 * @return string[]
	 */
	public function health_job( $jobs ) {
		if ( self::enabled() && Report_Settings::all()['enabled'] ) {
			$jobs   = (array) $jobs;
			$jobs[] = self::HOOK;
		}
		return $jobs;
	}

	/**
	 * Daily job: send the report when it is due.
	 */
	public function run() {
		Health_Check::record_run( self::HOOK );
		$now = time();
		Report_Settings::start( $now );
		if ( ! self::enabled() || ! Report_Settings::due( $now ) ) {
			return;
		}
		if ( get_transient( self::LOCK ) ) {
			return;
		}
		set_transient( self::LOCK, 1, 10 * MINUTE_IN_SECONDS );
		$result = self::send( false );
		delete_transient( self::LOCK );
		if ( ! is_wp_error( $result ) ) {
			// Sent or skipped (no visits). A failed send is tried again on the next run.
			Report_Settings::mark_checked( $now );
		}
		Report_Settings::record_result( is_wp_error( $result ) ? array( 'ok' => false, 'error' => $result->get_error_message() ) : $result );
	}

	/**
	 * Build and send a report.
	 *
	 * @param bool                $test     A test from the Settings screen: sent even with no visits, does not count as the period's report.
	 * @param array<string,mixed> $override recipients and frequency to use instead of the saved ones (tests).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function send( $test, array $override = array() ) {
		$s    = array_merge( Report_Settings::all(), array_intersect_key( $override, array_flip( array( 'recipients', 'frequency' ) ) ) );
		$list = Report_Settings::parse_list( (string) $s['recipients'] );
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		$facts = ( new Report_Builder() )->facts( Report_Builder::period( (string) $s['frequency'], time() ) );
		if ( '' !== $facts['db_error'] ) {
			return new \WP_Error(
				'qcin_report_db',
				/* translators: %s: database error message */
				sprintf( __( 'The visit data could not be read, so no report was sent. Database error: %s', 'chatbot' ), $facts['db_error'] ),
				array( 'status' => 500 )
			);
		}
		if ( ! $test && ! $facts['totals']['sessions'] ) {
			return array(
				'ok'      => true,
				'skipped' => true,
				'reason'  => 'no_visits',
			);
		}
		$sent = ( new Report_Mailer( $facts ) )->send( $list );
		if ( is_wp_error( $sent ) ) {
			return $sent;
		}
		if ( ! $test ) {
			update_option( Report_Settings::LAST_OPTION, time(), false );
		}
		/**
		 * After an email report was sent.
		 *
		 * @param array<string,mixed> $sent  to, subject.
		 * @param array<string,mixed> $facts Report facts.
		 * @param bool                $test  Whether it was a test.
		 */
		do_action( 'qcin/report_sent', $sent, $facts, (bool) $test );
		return array_merge( $sent, array( 'ok' => true, 'test' => (bool) $test ) );
	}

	/**
	 * Report name, credit line and optional upgrade line, from the host.
	 *
	 * The host adapter may add get_report_branding() returning any of:
	 * report_name (e.g. "WPBot Site Insight", used in the subject and heading),
	 * credit_text, credit_url, credit_label, promo_text, promo_url.
	 *
	 * @return array{report_name:string,credit_text:string,credit_url:string,credit_label:string,promo_text:string,promo_url:string}
	 */
	public static function branding() {
		$host = Module::instance()->host();
		$raw  = $host && method_exists( $host, 'get_report_branding' ) ? (array) $host->get_report_branding() : array();
		/**
		 * Report name, credit and upgrade line in the email report.
		 *
		 * @param array<string,string> $raw report_name, credit_text, credit_url, credit_label, promo_text, promo_url.
		 */
		$raw = (array) apply_filters( 'qcin/report_branding', $raw );
		$url = static function ( $u ) {
			$u = esc_url_raw( (string) $u );
			return preg_match( '#^https?://#i', $u ) ? $u : '';
		};
		$credit = trim( wp_strip_all_tags( (string) ( $raw['credit_text'] ?? '' ) ) );
		if ( '' === $credit ) {
			/* translators: %s: menu title, e.g. "Site Insight" */
			$credit = sprintf( __( 'Sent by %s', 'chatbot' ), Admin_Controller::title() );
		}
		$name = trim( wp_strip_all_tags( (string) ( $raw['report_name'] ?? '' ) ) );
		return array(
			// Default: the menu title, e.g. "Site Insight".
			'report_name'  => '' !== $name ? $name : Admin_Controller::title(),
			'credit_text'  => $credit,
			'credit_url'   => $url( $raw['credit_url'] ?? '' ),
			'credit_label' => trim( wp_strip_all_tags( (string) ( $raw['credit_label'] ?? '' ) ) ),
			'promo_text'   => trim( wp_strip_all_tags( (string) ( $raw['promo_text'] ?? '' ) ) ),
			'promo_url'    => $url( $raw['promo_url'] ?? '' ),
		);
	}

	/**
	 * Preview URL for the Settings screen (opens in a new tab).
	 *
	 * @param string $frequency weekly|monthly.
	 * @return string
	 */
	public static function preview_url( $frequency = '' ) {
		return add_query_arg(
			array(
				'action'    => 'qcin_report_preview',
				'frequency' => 'monthly' === $frequency ? 'monthly' : 'weekly',
				'_wpnonce'  => wp_create_nonce( 'qcin_report_preview' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Print the report as it would be sent now.
	 */
	public function preview() {
		if ( ! Module::instance()->can( 'settings' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to see this page.', 'chatbot' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'qcin_report_preview' );
		$frequency = isset( $_GET['frequency'] ) && 'monthly' === $_GET['frequency'] ? 'monthly' : 'weekly'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$facts     = ( new Report_Builder() )->facts( Report_Builder::period( $frequency, time() ) );
		if ( '' !== $facts['db_error'] ) {
			/* translators: %s: database error message */
			wp_die( esc_html( sprintf( __( 'The visit data could not be read. Database error: %s', 'chatbot' ), $facts['db_error'] ) ), '', array( 'response' => 500 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );
		echo ( new Report_Mailer( $facts ) )->html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		exit;
	}

	/**
	 * Remove the job (uninstall).
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
