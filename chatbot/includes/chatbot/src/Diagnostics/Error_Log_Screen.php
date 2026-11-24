<?php
/**
 * AI Settings > Error log (owner, 11 Nov 2026): every error WPBot recorded (WPBot Core's
 * error log: PHP, crashes, outside services, emails, background jobs, browser errors), a support
 * report to download or send to help@wpbot.pro with a description, and the log's settings.
 * The Help page shows a card that leads here.
 *
 * Errors from outside WPBot (other plugins, the theme, WordPress) are listed too, marked
 * "Outside WPBot" with the part of the site they came from (owner, 19 Nov 2026), when WPBot Core
 * tells them apart (Diagnostics\Origin).
 *
 * Needs WPBot Core's error log (Diagnostics\Error_Log); with an older WPBot Core the section
 * is not shown.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Diagnostics;

use QuantumCloud\Chatbot\Admin\Ai_Settings_Page;
use QuantumCloud\Chatbot\Ai\Provider_Settings;
use QuantumCloud\Chatbot\Knowledge\Faq;
use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Error log screen.
 */
final class Error_Log_Screen {

	/**
	 * Errors per page.
	 */
	const PER_PAGE = 25;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'help_card' ), 1 );
		// WPBot Core's admin design keeps notices for its bell: the card is page content, so it waits for the page top.
		add_action( 'qcld_wpbot_core/design_page_top', array( __CLASS__, 'help_card' ), 5 );
		add_filter( 'qcld_wpbot_core/support_report', array( __CLASS__, 'report_section' ) );
	}

	/**
	 * Whether WPBot Core has the error log.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( '\QuantumCloud\WPBotCore\Diagnostics\Error_Log' ) && class_exists( '\QuantumCloud\WPBotCore\Diagnostics\Support_Report' );
	}

	/**
	 * Whether WPBot Core marks where each error came from (WPBot or outside WPBot).
	 *
	 * @return bool
	 */
	public static function has_origins() {
		return class_exists( '\QuantumCloud\WPBotCore\Diagnostics\Origin' ) && method_exists( '\QuantumCloud\WPBotCore\Diagnostics\Error_Log', 'origin_counts' );
	}

	/**
	 * Labels of the origin filter.
	 *
	 * @return array<string,string>
	 */
	public static function origins() {
		return array(
			'wpbot'   => __( 'From WPBot', 'chatbot' ),
			'outside' => __( 'From outside WPBot', 'chatbot' ),
			'unknown' => __( 'Origin not known', 'chatbot' ),
		);
	}

	/**
	 * Labels of the error sources.
	 *
	 * @return array<string,string>
	 */
	public static function sources() {
		return array(
			'php'   => __( 'PHP', 'chatbot' ),
			'fatal' => __( 'Crash', 'chatbot' ),
			'http'  => __( 'Request to a service', 'chatbot' ),
			'ai'    => __( 'AI', 'chatbot' ),
			'job'   => __( 'Background job', 'chatbot' ),
			'mail'  => __( 'Email', 'chatbot' ),
			'js'    => __( 'Browser', 'chatbot' ),
			'other' => __( 'Other', 'chatbot' ),
		);
	}

	/**
	 * The section.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! self::available() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$log      = '\QuantumCloud\WPBotCore\Diagnostics\Error_Log';
		$report   = '\QuantumCloud\WPBotCore\Diagnostics\Support_Report';
		$back     = Ai_Settings_Page::url( 'errors' );
		$settings = $log::settings();
		// "Record errors" needs a WPBot Core with the switch; an older Core always records.
		$switch    = method_exists( $log, 'enabled' );
		$recording = ! $switch || $log::enabled();
		self::result_notice();

		// Send to support.
		$user = wp_get_current_user();
		echo '<div class="qcwp-card qcwp-errlog-send"><h2>' . esc_html__( 'Having a problem? Send it to WPBot support', 'chatbot' ) . '</h2>';
		echo '<p class="qcwp-help">' . esc_html__( 'Describe what happened. The report adds your WordPress and WPBot setup and the error log below. API keys, passwords and email addresses are removed first.', 'chatbot' ) . '</p>';
		if ( ! $recording ) {
			echo '<p class="qcwp-help">' . esc_html__( 'The error log is off, so the report has no recent errors.', 'chatbot' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( $report::NONCE );
		echo '<input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
		echo '<p><label for="qcwp-errlog-description"><strong>' . esc_html__( 'What happened?', 'chatbot' ) . '</strong></label><br>';
		echo '<textarea id="qcwp-errlog-description" name="description" rows="5" class="large-text" placeholder="' . esc_attr__( 'For example: since yesterday the chatbot answers "Sorry, something went wrong" to every question.', 'chatbot' ) . '"></textarea></p>';
		/* translators: %s: support email address. */
		echo '<label class="qcwp-check"><input type="checkbox" name="consent" value="1"> <span>' . esc_html( sprintf( __( 'Send this report to WPBot support (%s), so they can reply.', 'chatbot' ), $report::email() ) ) . '</span></label>';
		// Contact (owner, 12 Nov 2026): optional and unticked; the email address goes with the report only when ticked.
		$contact = '' !== (string) $user->user_email ? (string) $user->user_email : (string) get_option( 'admin_email' );
		echo '<div class="qcwp-errlog-contact"><label class="qcwp-check"><input type="checkbox" name="contact" value="1" aria-controls="qcwp-errlog-contact-email"> <span>' . esc_html__( 'Should we contact you after we fix the issue?', 'chatbot' ) . '</span></label>';
		echo '<p><label for="qcwp-errlog-contact-email">' . esc_html__( 'Your email address', 'chatbot' ) . '</label><br><input type="email" id="qcwp-errlog-contact-email" name="contact_email" class="regular-text" autocomplete="email" value="' . esc_attr( $contact ) . '"></p></div>';
		echo '<p class="qcwp-actions"><button type="submit" name="action" value="qcld_wpbot_core_support_send" class="qcwp-btn is-primary">' . esc_html__( 'Send to WPBot support', 'chatbot' ) . '</button> ';
		echo '<button type="submit" name="action" value="qcld_wpbot_core_support_download" class="qcwp-btn">' . esc_html__( 'Download report', 'chatbot' ) . '</button></p>';
		echo '<p class="qcwp-help">' . esc_html__( 'Download the report to read it first, or to attach it to an email yourself.', 'chatbot' ) . '</p>';
		echo '</form></div>';

		self::settings_card( $settings, $switch, $recording, $back );

		// The log.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filters and paging only.
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		$origin = isset( $_GET['origin'] ) ? sanitize_key( wp_unslash( $_GET['origin'] ) ) : '';
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$origins = self::has_origins();
		$source  = isset( self::sources()[ $source ] ) ? $source : '';
		$origin  = $origins && isset( self::origins()[ $origin ] ) ? $origin : '';
		$found   = $log::items(
			array(
				'source'   => $source,
				'origin'   => $origin,
				'page'     => $page,
				'per_page' => self::PER_PAGE,
			)
		);
		echo '<div class="qcwp-card qcwp-errlog"><div class="qcwp-card-head"><div><h2>' . esc_html__( 'Error log', 'chatbot' ) . '</h2>';
		if ( $origins && '' === $source && '' === $origin ) {
			$counts = $log::origin_counts();
			/* translators: 1: number of distinct errors, 2: how many came from WPBot, 3: how many came from outside WPBot. */
			$summary = sprintf( _n( '%1$d error from the last 30 days: %2$d from WPBot, %3$d from outside WPBot.', '%1$d errors from the last 30 days: %2$d from WPBot, %3$d from outside WPBot.', $found['total'], 'chatbot' ), $found['total'], $counts['wpbot'], $counts['outside'] );
			if ( $counts['unknown'] ) {
				/* translators: %d: number of errors recorded before WPBot told them apart. */
				$summary .= ' ' . sprintf( _n( '%d recorded before WPBot could tell.', '%d recorded before WPBot could tell.', $counts['unknown'], 'chatbot' ), $counts['unknown'] );
			}
			echo '<p class="qcwp-help">' . esc_html( $summary . ' ' . __( 'Repeats are counted, not listed again.', 'chatbot' ) ) . '</p>';
		} else {
			/* translators: %d: number of distinct errors. */
			echo '<p class="qcwp-help">' . esc_html( sprintf( _n( '%d error from the last 30 days. Repeats are counted, not listed again.', '%d errors from the last 30 days. Repeats are counted, not listed again.', $found['total'], 'chatbot' ), $found['total'] ) ) . '</p>';
		}
		if ( $origins ) {
			echo '<p class="qcwp-help">' . esc_html__( 'Errors marked "Outside WPBot" came from another plugin, the theme or WordPress. WPBot only recorded them, to help you and support find problems.', 'chatbot' ) . '</p>';
		}
		echo '</div>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="qcwp-errlog-filter"><input type="hidden" name="page" value="' . esc_attr( Ai_Settings_Page::SLUG ) . '"><input type="hidden" name="section" value="errors">';
		if ( $origins ) {
			echo '<label for="qcwp-errlog-origin" class="screen-reader-text">' . esc_html__( 'Show errors that came from', 'chatbot' ) . '</label><select id="qcwp-errlog-origin" name="origin"><option value="">' . esc_html__( 'From anywhere', 'chatbot' ) . '</option>';
			foreach ( self::origins() as $id => $label ) {
				echo '<option value="' . esc_attr( $id ) . '"' . selected( $origin, $id, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select> ';
		}
		echo '<label for="qcwp-errlog-source" class="screen-reader-text">' . esc_html__( 'Show errors of this kind', 'chatbot' ) . '</label><select id="qcwp-errlog-source" name="source"><option value="">' . esc_html__( 'Every kind', 'chatbot' ) . '</option>';
		foreach ( self::sources() as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $source, $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <button type="submit" class="qcwp-btn is-small">' . esc_html__( 'Show', 'chatbot' ) . '</button></form></div>';
		if ( ! $recording ) {
			echo '<p class="qcwp-errlog-off">' . esc_html__( 'The error log is off. Nothing new is being recorded.', 'chatbot' ) . '</p>';
		}
		if ( ! $found['items'] ) {
			if ( '' !== $source || '' !== $origin ) {
				$empty = __( 'No errors of this kind.', 'chatbot' );
			} elseif ( $recording ) {
				$empty = __( 'No errors recorded. WPBot is running without problems.', 'chatbot' );
			} else {
				$empty = __( 'No errors in the log.', 'chatbot' );
				// Off: an empty log says nothing about problems.
			}
			echo '<p class="qcwp-errlog-empty">' . esc_html( $empty ) . '</p>';
		} else {
			echo '<div class="qcwp-table-wrap"><table class="qcwp-table qcwp-errlog-table"><thead><tr><th scope="col">' . esc_html__( 'Last seen', 'chatbot' ) . '</th><th scope="col">' . esc_html__( 'Level', 'chatbot' ) . '</th><th scope="col">' . esc_html( $origins ? __( 'From', 'chatbot' ) : __( 'Where', 'chatbot' ) ) . '</th><th scope="col">' . esc_html__( 'What happened', 'chatbot' ) . '</th><th scope="col">' . esc_html__( 'Times', 'chatbot' ) . '</th></tr></thead><tbody>';
			foreach ( $found['items'] as $item ) {
				self::row( $item, $origins );
			}
			echo '</tbody></table></div>';
			self::pagination( $found['total'], $page, $source, $origin );
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="qcwp-errlog-clear">';
		wp_nonce_field( $report::NONCE );
		echo '<input type="hidden" name="action" value="qcld_wpbot_core_errors_clear"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
		echo '<button type="submit" class="qcwp-btn is-small is-danger"' . ( $found['total'] ? '' : ' disabled' ) . ' onclick="return window.confirm(' . esc_attr( wp_json_encode( __( 'Delete every error in the log?', 'chatbot' ) ) ) . ');">' . esc_html__( 'Clear the log', 'chatbot' ) . '</button></form>';
		echo '</div>';
	}

	/**
	 * The settings card. It comes before the log (owner, 19 November 2026): after a long log it was
	 * far down the page and easy to miss.
	 *
	 * @param array  $settings   Saved Error_Log settings.
	 * @param bool   $has_switch Whether WPBot Core has the "Record errors" switch.
	 * @param bool   $recording  Whether errors are recorded.
	 * @param string $back       Address to come back to after saving.
	 * @return void
	 */
	private static function settings_card( array $settings, $has_switch, $recording, $back ) {
		$report  = '\QuantumCloud\WPBotCore\Diagnostics\Support_Report';
		$origins = self::has_origins();
		echo '<div class="qcwp-card qcwp-errlog-settings"><h2>' . esc_html__( 'Error log settings', 'chatbot' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( $report::NONCE );
		echo '<input type="hidden" name="action" value="qcld_wpbot_core_errors_settings"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
		// The *_sent markers tell the handlers these switches are on the form (an unticked box is not
		// posted); a form without them keeps the saved values.
		echo '<input type="hidden" name="alert_sent" value="1">';
		if ( $has_switch ) {
			echo '<input type="hidden" name="record_sent" value="1">';
			echo '<label class="qcwp-check"><input type="checkbox" name="record" value="1" id="qcwp-errlog-record"' . checked( $settings['record'], true, false ) . '> <span>' . esc_html__( 'Record errors', 'chatbot' ) . '<small>' . esc_html__( 'Turn off to stop WPBot recording anything in this log. The chatbot keeps working. Support can\'t see recent errors while this is off.', 'chatbot' ) . '</small></span></label>';
		}
		echo '<label class="qcwp-check qcwp-errlog-dep"><input type="checkbox" name="alert" value="1"' . checked( Error_Alert::enabled(), true, false ) . disabled( $recording, false, false ) . '> <span>' . esc_html__( 'Show an alert when WPBot records errors', 'chatbot' ) . '<small>' . esc_html__( 'Red count bubbles on the WPBot menus and a notice on WPBot\'s screens.', 'chatbot' ) . '</small></span></label>';
		if ( $origins && isset( $settings['outside'] ) ) {
			echo '<input type="hidden" name="outside_sent" value="1">';
			echo '<label class="qcwp-check qcwp-errlog-dep"><input type="checkbox" name="outside" value="1"' . checked( $settings['outside'], true, false ) . disabled( $recording, false, false ) . '> <span>' . esc_html__( 'Errors from outside WPBot too: other plugins, the theme and WordPress', 'chatbot' ) . '<small>' . esc_html__( 'On by default. They are marked "Outside WPBot" and never count for WPBot\'s error alert. They help WPBot support see what else is going wrong on the site.', 'chatbot' ) . '</small></span></label>';
		}
		echo '<label class="qcwp-check qcwp-errlog-dep"><input type="checkbox" name="browser" value="1"' . checked( $settings['browser'], true, false ) . disabled( $recording, false, false ) . '> <span>' . esc_html__( 'Browser errors of the chat widget and WPBot\'s admin screens', 'chatbot' ) . '<small>' . esc_html__( 'Only errors of WPBot\'s own scripts; nothing a visitor typed. At most 100 a site per hour.', 'chatbot' ) . '</small></span></label>';
		echo '<label class="qcwp-check qcwp-errlog-dep"><input type="checkbox" name="notices" value="1"' . checked( $settings['notices'], true, false ) . disabled( $recording, false, false ) . '> <span>' . esc_html__( 'Minor PHP notices and deprecation messages too', 'chatbot' ) . '<small>' . esc_html__( 'Off by default: they rarely break anything. Turn on when WPBot support asks.', 'chatbot' ) . '</small></span></label>';
		echo '<p class="qcwp-help">' . esc_html__( 'When recording is on, these are always recorded: PHP warnings and errors and crashes in WPBot\'s files, failed requests to AI providers and other services, emails WPBot could not send, and failed background jobs. Errors are kept 30 days.', 'chatbot' ) . '</p>';
		echo '<p><button type="submit" class="qcwp-btn">' . esc_html__( 'Save', 'chatbot' ) . '</button></p></form></div>';
		if ( $has_switch ) {
			// Grey out the other options while "Record errors" is unticked. Without JavaScript the
			// server still ignores them while recording is off.
			echo '<script>(function(){var r=document.getElementById("qcwp-errlog-record");if(!r){return;}var c=r.closest("form");function s(){c.querySelectorAll(".qcwp-errlog-dep input").forEach(function(i){i.disabled=!r.checked;});}r.addEventListener("change",s);s();})();</script>';
		}
	}

	/**
	 * One error row.
	 *
	 * @param array $item    Error.
	 * @param bool  $origins Whether WPBot Core marks where errors came from.
	 * @return void
	 */
	private static function row( array $item, $origins = false ) {
		$sources = self::sources();
		$detail  = (array) $item['detail'];
		$where   = isset( $sources[ $item['source'] ] ) ? $sources[ $item['source'] ] : $item['source'];
		$from    = isset( $item['origin'] ) ? (string) $item['origin'] : '';
		$pill    = 'error' === $item['level'] ? 'is-bad' : ( 'warning' === $item['level'] ? 'is-warn' : '' );
		$levels  = array(
			'error'   => __( 'Error', 'chatbot' ),
			'warning' => __( 'Warning', 'chatbot' ),
			'notice'  => __( 'Notice', 'chatbot' ),
		);
		$time    = strtotime( $item['last_seen'] . ' UTC' );
		echo '<tr><td><time datetime="' . esc_attr( gmdate( 'c', $time ) ) . '">' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) ) . '</time></td>';
		echo '<td><span class="qcwp-pill ' . esc_attr( $pill ) . '">' . esc_html( isset( $levels[ $item['level'] ] ) ? $levels[ $item['level'] ] : $item['level'] ) . '</span></td>';
		if ( $origins ) {
			$origin_class = '\QuantumCloud\WPBotCore\Diagnostics\Origin';
			$part         = $origin_class::part( $item );
			if ( 'wpbot' === $from ) {
				$pill  = '<span class="qcwp-pill is-wpbot">' . esc_html__( 'WPBot', 'chatbot' ) . '</span>';
				$small = ( '' !== $item['module'] ? $item['module'] . ' · ' : '' ) . $where;
			} elseif ( 'outside' === $from ) {
				$pill  = '<span class="qcwp-pill is-outside">' . esc_html__( 'Outside WPBot', 'chatbot' ) . '</span>';
				$small = $part . ' · ' . $where;
			} else {
				$pill  = '<span class="qcwp-pill">' . esc_html__( 'Not known', 'chatbot' ) . '</span>';
				$small = $where;
			}
			echo '<td>' . $pill . '<br><small>' . esc_html( $small ) . '</small></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $pill is built from escaped parts above.
		} else {
			echo '<td>' . esc_html( $where ) . ( '' !== $item['module'] ? '<br><small>' . esc_html( $item['module'] ) . '</small>' : '' ) . '</td>';
		}
		echo '<td><div class="qcwp-errlog-message">' . esc_html( $item['message'] ) . '</div>';
		if ( $origins && 'outside' === $from ) {
			/* translators: %s: the part of the site the error came from, such as "Jetpack (plugin)". */
			echo '<p class="qcwp-errlog-note">' . esc_html( sprintf( __( 'Not a WPBot error: it came from %s. WPBot only recorded it.', 'chatbot' ), $part ) ) . '</p>';
		} elseif ( $origins && 'wpbot' !== $from ) {
			echo '<p class="qcwp-errlog-note">' . esc_html__( 'Recorded before WPBot could tell where errors came from: it may not be a WPBot error.', 'chatbot' ) . '</p>';
		}
		$facts = array();
		if ( ! empty( $detail['file'] ) ) {
			$facts[ __( 'File', 'chatbot' ) ] = $detail['file'] . ( ! empty( $detail['line'] ) ? ':' . (int) $detail['line'] : '' );
		}
		if ( ! empty( $detail['url'] ) ) {
			$facts[ __( 'Request', 'chatbot' ) ] = $detail['url'];
		}
		if ( ! empty( $detail['host'] ) ) {
			$facts[ __( 'Service', 'chatbot' ) ] = $detail['host'] . ( ! empty( $detail['path'] ) ? $detail['path'] : '' );
		}
		$facts[ __( 'First seen', 'chatbot' ) ] = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['first_seen'] . ' UTC' ) );
		echo '<details class="qcwp-errlog-details"><summary>' . esc_html__( 'Details', 'chatbot' ) . '</summary><dl>';
		foreach ( $facts as $label => $value ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd><code>' . esc_html( (string) $value ) . '</code></dd>';
		}
		echo '</dl>';
		if ( ! empty( $detail['stack'] ) ) {
			echo '<pre class="qcwp-errlog-stack">' . esc_html( (string) $detail['stack'] ) . '</pre>';
		}
		echo '</details></td><td data-label="' . esc_attr__( 'Times', 'chatbot' ) . '">' . esc_html( number_format_i18n( (int) $item['hits'] ) ) . '</td></tr>';
	}

	/**
	 * Page links.
	 *
	 * @param int    $total  Errors.
	 * @param int    $page   Page.
	 * @param string $source Source filter.
	 * @param string $origin Origin filter.
	 * @return void
	 */
	private static function pagination( $total, $page, $source, $origin = '' ) {
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages < 2 ) {
			return;
		}
		echo '<nav class="qcwp-errlog-pages" aria-label="' . esc_attr__( 'Error log pages', 'chatbot' ) . '">';
		for ( $i = 1; $i <= $pages; $i++ ) {
			$args = array( 'paged' => $i );
			if ( '' !== $source ) {
				$args['source'] = $source;
			}
			if ( '' !== $origin ) {
				$args['origin'] = $origin;
			}
			if ( $i === $page ) {
				echo '<span class="qcwp-btn is-small is-primary" aria-current="page">' . esc_html( (string) $i ) . '</span> ';
			} else {
				echo '<a class="qcwp-btn is-small" href="' . esc_url( Ai_Settings_Page::url( 'errors', $args ) ) . '">' . esc_html( (string) $i ) . '</a> ';
			}
		}
		echo '</nav>';
	}

	/**
	 * The result of the last action (sent, saved, cleared…).
	 *
	 * @return void
	 */
	private static function result_notice() {
		$result   = isset( $_GET['qcld_error_log'] ) ? sanitize_key( wp_unslash( $_GET['qcld_error_log'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Notice only.
		$messages = array(
			'sent'             => array( '', __( 'Sent. WPBot support will reply to your email address.', 'chatbot' ) ),
			'sent_no_contact'  => array( '', __( 'Sent. You asked not to be contacted, so WPBot support will not reply.', 'chatbot' ) ),
			'need_email'       => array( 'is-warn', __( 'Enter a valid email address, or untick "Should we contact you after we fix the issue?".', 'chatbot' ) ),
			'not_sent'         => array( 'is-warn', __( 'The email could not be sent from this site. Download the report and email it to WPBot support yourself.', 'chatbot' ) ),
			'need_description' => array( 'is-warn', __( 'Describe what happened first, then send.', 'chatbot' ) ),
			'need_consent'     => array( 'is-warn', __( 'Tick the box to agree that the report is sent to WPBot support.', 'chatbot' ) ),
			'cleared'          => array( '', __( 'The error log is empty.', 'chatbot' ) ),
			'saved'            => array( '', __( 'Saved.', 'chatbot' ) ),
		);
		if ( isset( $messages[ $result ] ) ) {
			echo '<div class="qcwp-notice ' . esc_attr( $messages[ $result ][0] ) . '" role="status">' . esc_html( $messages[ $result ][1] ) . '</div>';
		}
	}

	/**
	 * A card at the top of the Help page that leads to the error log.
	 *
	 * @return void
	 */
	public static function help_card() {
		$design = class_exists( '\\QuantumCloud\\WPBotCore\\Admin\\Design' ) && \QuantumCloud\WPBotCore\Admin\Design::active();
		// Printed once: at the design's page top, or in the notices without it.
		if ( doing_action( 'qcld_wpbot_core/design_page_top' ) !== $design ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the page slug.
		/**
		 * Admin pages (slugs) that show the error log card: the hosts' Help pages.
		 *
		 * @param string[] $pages Page slugs.
		 */
		$pages = (array) apply_filters( 'qcld_wpbot_chat/error_log_help_pages', array( 'wpbot_license_page', 'wpbot_help_page' ) );
		if ( ! in_array( $page, $pages, true ) || ! self::available() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$log    = '\QuantumCloud\WPBotCore\Diagnostics\Error_Log';
		$recent = $log::count_recent( WEEK_IN_SECONDS );
		echo '<div class="notice qcwp-errlog-help" role="region" aria-labelledby="qcwp-errlog-help-title">';
		echo '<span class="dashicons dashicons-sos" aria-hidden="true"></span><div>';
		echo '<h2 id="qcwp-errlog-help-title">' . esc_html__( 'Something not working? Send us your error log', 'chatbot' ) . '</h2>';
		echo '<p>' . esc_html__( 'WPBot records its errors. Describe the problem and send the error log to WPBot support in one click, or download it.', 'chatbot' );
		if ( $recent > 0 ) {
			/* translators: %d: number of errors. */
			echo ' <strong>' . esc_html( sprintf( _n( '%d error in the last 7 days.', '%d errors in the last 7 days.', $recent, 'chatbot' ), $recent ) ) . '</strong>';
		}
		echo '</p></div><a class="button button-primary button-hero" href="' . esc_url( Ai_Settings_Page::url( 'errors' ) ) . '">' . esc_html__( 'Open the error log', 'chatbot' ) . '</a></div>';
		echo '<style>.qcwp-errlog-help{display:flex;gap:16px;align-items:center;padding:18px 20px!important;margin:16px 0!important;border-left:4px solid #5b4e96!important;background:#fff;border-radius:12px}.qcwp-errlog-help>.dashicons{font-size:36px;width:36px;height:36px;color:#5b4e96}.qcwp-errlog-help>div{flex:1}.qcwp-errlog-help h2{margin:0 0 4px;font-size:16px}.qcwp-errlog-help p{margin:0}@media (max-width:782px){.qcwp-errlog-help{flex-wrap:wrap}}</style>';
	}

	/**
	 * The chatbot's part of the support report (no keys).
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	public static function report_section( $sections ) {
		$answering = Provider_Settings::answering_provider();
		$settings  = '' !== $answering ? Provider_Settings::get( $answering ) : null;
		$on        = array();
		foreach ( array( 'openai', 'gemini', 'claude', 'grok', 'deepseek', 'mistral', 'openrouter', 'ollama' ) as $provider ) {
			$one = Provider_Settings::get( $provider, false );
			if ( $one && ! empty( $one['enabled'] ) ) {
				$on[] = $provider . ( '' !== trim( (string) ( isset( $one['key'] ) ? $one['key'] : '' ) ) || 'ollama' === $provider ? '' : ' (no key)' );
			}
		}
		$sections['ChatBot'] = array(
			'Answering AI provider'  => '' !== $answering ? $answering : 'none',
			'Model'                  => $settings && isset( $settings['model'] ) ? (string) $settings['model'] : '',
			'AI providers on'        => $on ? implode( ', ', $on ) : 'none',
			'Knowledge base answers' => $settings && ! empty( $settings['knowledge_base'] ) ? 'on' : 'off',
			'Site search'            => '' === (string) Options::get( 'disable_sitesearch', '' ) ? 'on' : 'off',
			'Simple Text Responses'  => Options::on( 'qc_bot_enable_str' ) ? 'on' : 'off',
			'FAQ questions'          => (string) count( Faq::entries() ),
			'Chatbot module'         => defined( 'QCLD_WPBOT_CHAT_VERSION' ) ? QCLD_WPBOT_CHAT_VERSION : '',
		);
		return $sections;
	}
}
