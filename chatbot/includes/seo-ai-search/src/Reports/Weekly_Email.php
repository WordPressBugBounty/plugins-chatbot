<?php
/**
 * Weekly email summary: search numbers for the week, biggest movers, new
 * opportunities, new critical issues, and results that came in. Sent only
 * when there is news. Free hosts add a short upgrade footer the owner can
 * hide; other modules and the Pro module add sections through
 * qcld_wpbot_seo/report_sections.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Reports;

use QuantumCloud\SeoAiSearch\Api\Dashboard_Data;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Opportunities\Opportunities;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Weekly email.
 */
final class Weekly_Email {

	const CRON  = 'qcld_wpbot_seo_weekly_email';
	const STATE = 'qcld_wpbot_seo_email_state';

	/**
	 * Hook the weekly event (scheduled on the owner's day, 8:00 site time).
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( self::CRON, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 31 );
	}

	/**
	 * Keep one weekly event on the chosen day.
	 *
	 * @return void
	 */
	public static function schedule() {
		$next = wp_next_scheduled( self::CRON );
		if ( ! Settings::get( 'email_enabled' ) ) {
			if ( $next ) {
				wp_clear_scheduled_hook( self::CRON );
			}
			return;
		}
		$day = (string) Settings::get( 'email_day' );
		if ( $next && strtolower( wp_date( 'l', $next ) ) === $day ) {
			return;
		}
		wp_clear_scheduled_hook( self::CRON );
		$when = new \DateTime( 'next ' . $day . ' 08:00', wp_timezone() );
		wp_schedule_event( $when->getTimestamp(), 'weekly', self::CRON );
	}

	/**
	 * Weekly run: send when there is news.
	 *
	 * @return bool Sent.
	 */
	public static function run() {
		if ( ! Settings::get( 'email_enabled' ) ) {
			return false;
		}
		// At most one summary a week, even if the event runs again.
		$state = self::state();
		if ( ! empty( $state['sent'] ) && strtotime( $state['sent'] ) > time() - 6 * DAY_IN_SECONDS ) {
			return false;
		}
		$facts = self::facts();
		if ( ! $facts['news'] ) {
			self::save_state( array( 'skipped' => gmdate( 'c' ) ) );
			return false;
		}
		$sent = self::send( self::recipients(), $facts );
		if ( $sent ) {
			self::save_state(
				array(
					'sent'          => gmdate( 'c' ),
					'opportunities' => $facts['opportunity_ids'],
					'critical'      => $facts['critical'],
					'results'       => $facts['result_ids'],
				)
			);
		}
		return $sent;
	}

	/**
	 * Send a test email to one address, news or not.
	 *
	 * @param string $to Address.
	 * @return bool
	 */
	public static function test( $to ) {
		return self::send( array( $to ), self::facts(), true );
	}

	/**
	 * What happened this week.
	 *
	 * @return array totals, trend, movers, opportunities (new), critical, results (new), sections, news.
	 */
	public static function facts() {
		global $wpdb;
		$state = self::state();
		$dash  = Dashboard_Data::get( array( 'days' => 7 ) );
		$opps  = Opportunities::query( array( 'limit' => 50 ) )['items'];
		$seen  = isset( $state['opportunities'] ) ? (array) $state['opportunities'] : array();
		$new   = array_values(
			array_filter(
				$opps,
				static function ( $opp ) use ( $seen ) {
					return ! in_array( $opp['id'], $seen, true );
				}
			)
		);
		$table = Schema::table( Schema::CHANGES );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows    = (array) $wpdb->get_results( "SELECT id, post_id, kind, field, results FROM {$table} WHERE results IS NOT NULL AND undone_at IS NULL ORDER BY id DESC LIMIT 200", ARRAY_A );
		$done    = isset( $state['results'] ) ? (array) $state['results'] : array();
		$results = array();
		foreach ( $rows as $row ) {
			$data = json_decode( (string) $row['results'], true );
			$keys = is_array( $data ) ? array_keys( $data ) : array();
			$ref  = (int) $row['id'] . ':' . implode( ',', $keys );
			if ( array() === $keys || in_array( $ref, $done, true ) ) {
				continue;
			}
			// Day checkpoints (14, 28, 90) carry click changes; "onpage" carries the on-page score before and after.
			$days = array_values( array_filter( $keys, 'is_numeric' ) );
			$last = array() !== $days ? end( $days ) : null;
			if ( null !== $last && isset( $data[ $last ]['change']['clicks'] ) ) {
				$results[] = array(
					'ref'     => $ref,
					'post_id' => (int) $row['post_id'],
					'title'   => get_the_title( (int) $row['post_id'] ),
					'field'   => (string) $row['field'],
					'days'    => (int) $last,
					'change'  => $data[ $last ]['change'],
				);
			} elseif ( isset( $data['onpage']['before'], $data['onpage']['after'] ) ) {
				$results[] = array(
					'ref'     => $ref,
					'post_id' => (int) $row['post_id'],
					'title'   => get_the_title( (int) $row['post_id'] ),
					'field'   => (string) $row['field'],
					'score'   => array( (int) $data['onpage']['before'], (int) $data['onpage']['after'] ),
				);
			}
		}
		$critical = (int) $dash['critical_issues'];
		$trend    = isset( $dash['totals']['trend']['clicks'] ) ? $dash['totals']['trend']['clicks'] : null;
		$facts    = array(
			'period'          => $dash['period'],
			'totals'          => $dash['totals'],
			'movers'          => $dash['movers'],
			'opportunities'   => array_slice( $new, 0, 5 ),
			'opportunity_ids' => array_column( $opps, 'id' ),
			'critical'        => $critical,
			'critical_new'    => $critical > ( isset( $state['critical'] ) ? (int) $state['critical'] : 0 ),
			'results'         => array_slice( $results, 0, 5 ),
			'result_ids'      => array_merge( $done, array_column( $results, 'ref' ) ),
			'health'          => $dash['health_score'],
		);
		/**
		 * Extra sections for the weekly email and reports.
		 *
		 * @param array $sections Each [ title, rows[[label, value, url?]], note ].
		 * @param array $facts    Facts.
		 */
		$facts['sections'] = (array) apply_filters( 'qcld_wpbot_seo/report_sections', array(), $facts );
		/**
		 * Change in weekly clicks that counts as news on its own (0.1 = 10%).
		 *
		 * @param float $threshold Threshold.
		 */
		$threshold     = (float) apply_filters( 'qcld_wpbot_seo/email_trend_threshold', 0.1 );
		$facts['news'] = array() !== $new || $facts['critical_new'] || array() !== $results || array() !== $facts['sections'] || ( null !== $trend && abs( $trend ) >= $threshold );
		return $facts;
	}

	/**
	 * Build and send the email.
	 *
	 * @param string[] $to    Recipients.
	 * @param array    $facts Facts.
	 * @param bool     $test  Test email.
	 * @return bool
	 */
	private static function send( array $to, array $facts, $test = false ) {
		$to = array_values( array_filter( $to, 'is_email' ) );
		if ( array() === $to ) {
			return false;
		}
		/* translators: %s: site name. */
		$subject = sprintf( __( 'Your week in search: %s', 'chatbot' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		if ( $test ) {
			$subject = '[' . __( 'Test', 'chatbot' ) . '] ' . $subject;
		}
		return (bool) wp_mail( $to, $subject, self::html( $facts ), array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Email body (inline styles; escaped).
	 *
	 * @param array $facts Facts.
	 * @return string
	 */
	public static function html( array $facts ) {
		$t    = $facts['totals'];
		$link = admin_url( 'admin.php?page=wpbot-seo-ai-search' );
		$pct  = static function ( $value ) {
			return null === $value ? '' : ( $value >= 0 ? '+' : '' ) . round( 100 * $value ) . '%';
		};
		$out  = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#1e1b2e;max-width:620px">';
		/* translators: 1: first day, 2: last day. */
		$out .= '<h2 style="margin:0 0 4px">' . esc_html( sprintf( __( 'Search, %1$s to %2$s', 'chatbot' ), $facts['period']['start'], $facts['period']['end'] ) ) . '</h2>';
		$out .= '<p>' . esc_html(
			sprintf(
				/* translators: 1: clicks, 2: change, 3: impressions, 4: average position. */
				__( '%1$s clicks (%2$s), %3$s impressions, average position %4$s.', 'chatbot' ),
				number_format_i18n( $t['clicks'] ),
				$pct( $t['trend']['clicks'] ),
				number_format_i18n( $t['impressions'] ),
				number_format_i18n( $t['position'], 1 )
			)
		) . '</p>';
		if ( null !== $facts['health'] ) {
			/* translators: 1: health score, 2: critical issues. */
			$out .= '<p>' . esc_html( sprintf( __( 'Site health %1$d/100, %2$d critical issues.', 'chatbot' ), (int) $facts['health'], (int) $facts['critical'] ) ) . '</p>';
		}
		$sections = array();
		if ( array() !== $facts['opportunities'] ) {
			$rows = array();
			foreach ( $facts['opportunities'] as $opp ) {
				$rows[] = array( $opp['label'], '' !== $opp['title'] ? $opp['title'] : $opp['query'], $link . '&tab=opportunities' );
			}
			$sections[] = array(
				'title' => __( 'New opportunities', 'chatbot' ),
				'rows'  => $rows,
			);
		}
		if ( array() !== $facts['results'] ) {
			$rows = array();
			foreach ( $facts['results'] as $result ) {
				if ( isset( $result['score'] ) ) {
					/* translators: 1: score before, 2: score after. */
					$rows[] = array( $result['title'], sprintf( __( 'On-page score %1$d → %2$d', 'chatbot' ), $result['score'][0], $result['score'][1] ), $link . '&tab=results' );
					continue;
				}
				/* translators: 1: clicks change, 2: days. */
				$rows[] = array( $result['title'], sprintf( __( '%1$s clicks after %2$d days', 'chatbot' ), ( $result['change']['clicks'] >= 0 ? '+' : '' ) . $result['change']['clicks'], $result['days'] ), $link . '&tab=results' );
			}
			$sections[] = array(
				'title' => __( 'Results of your changes', 'chatbot' ),
				'rows'  => $rows,
			);
		}
		if ( array() !== $facts['movers'] ) {
			$rows = array();
			foreach ( $facts['movers'] as $move ) {
				$rows[] = array( '' !== $move['title'] ? $move['title'] : $move['url'], ( $move['change'] >= 0 ? '+' : '' ) . $move['change'] . ' ' . __( 'clicks', 'chatbot' ), $move['url'] );
			}
			$sections[] = array(
				'title' => __( 'Biggest movers', 'chatbot' ),
				'rows'  => $rows,
			);
		}
		foreach ( array_merge( $sections, $facts['sections'] ) as $section ) {
			$out .= '<h3 style="margin:18px 0 6px">' . esc_html( (string) $section['title'] ) . '</h3><table style="border-collapse:collapse;width:100%">';
			foreach ( (array) $section['rows'] as $row ) {
				$label = isset( $row[2] ) && '' !== $row[2] ? '<a href="' . esc_url( $row[2] ) . '">' . esc_html( (string) $row[0] ) . '</a>' : esc_html( (string) $row[0] );
				$out  .= '<tr><td style="padding:4px 8px 4px 0;border-bottom:1px solid #eee">' . $label . '</td><td style="padding:4px 0;border-bottom:1px solid #eee;text-align:right">' . esc_html( (string) $row[1] ) . '</td></tr>';
			}
			$out .= '</table>';
			if ( ! empty( $section['note'] ) ) {
				$out .= '<p style="color:#646178;font-size:13px">' . esc_html( (string) $section['note'] ) . '</p>';
			}
		}
		$out   .= '<p style="margin-top:20px"><a href="' . esc_url( $link ) . '">' . esc_html__( 'Open SEO & AI Search', 'chatbot' ) . '</a></p>';
		$footer = self::footer();
		if ( '' !== $footer ) {
			$out .= '<p style="color:#646178;font-size:12px;border-top:1px solid #eee;padding-top:8px">' . wp_kses( $footer, array( 'a' => array( 'href' => array() ) ) ) . '</p>';
		}
		return $out . '</div>';
	}

	/**
	 * Footer from the host (Free hosts: a short upgrade line), unless hidden.
	 *
	 * @return string
	 */
	public static function footer() {
		if ( Settings::get( 'hide_email_footer' ) ) {
			return '';
		}
		$host = Module::instance()->host();
		$text = $host && method_exists( $host, 'get_email_footer' ) ? (string) $host->get_email_footer() : '';
		/**
		 * Email footer text (HTML links allowed).
		 *
		 * @param string $text Footer.
		 */
		return (string) apply_filters( 'qcld_wpbot_seo/email_footer', $text );
	}

	/**
	 * Recipients (setting, else the site admin).
	 *
	 * @return string[]
	 */
	public static function recipients() {
		$to = (array) Settings::get( 'email_recipients' );
		return array() !== $to ? $to : array( (string) get_option( 'admin_email' ) );
	}

	/**
	 * Stored state.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Merge and save state.
	 *
	 * @param array $state State.
	 * @return void
	 */
	private static function save_state( array $state ) {
		update_option( self::STATE, array_merge( self::state(), $state ), false );
	}
}
