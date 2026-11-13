<?php
/**
 * Spending alerts: an email (and an admin notice) when the global spend passes one of the
 * owner's thresholds, and when a limit stops AI. Each alert is sent once per period.
 *
 * Checked each time a call is recorded (at most once a minute) and hourly by WP-Cron, because
 * WP-Cron only runs when the site has visits.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use QuantumCloud\WPBotCore\Host\Brand;

defined( 'ABSPATH' ) || exit;

/**
 * Alerts.
 */
final class Alerts {

	const HOURLY = 'qcld_wpbot_core_spend_hourly';

	const DAILY = 'qcld_wpbot_core_spend_daily';

	const THROTTLE = 'qcld_wpbot_core_spend_check';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_core/spend_recorded', array( __CLASS__, 'after_record' ) );
		add_action( 'qcld_wpbot_core/spend_limit_reached', array( __CLASS__, 'limit_reached' ), 10, 2 );
		add_action( self::HOURLY, array( __CLASS__, 'hourly' ) );
		add_action( self::DAILY, array( __CLASS__, 'daily' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		add_action( 'admin_post_qcld_wpbot_core_spend_dismiss', array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * Schedule the hourly and daily checks.
	 *
	 * @return void
	 */
	public static function ensure_scheduled() {
		if ( ! wp_next_scheduled( self::HOURLY ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::HOURLY );
		}
		if ( ! wp_next_scheduled( self::DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY );
		}
	}

	/**
	 * After a call was recorded: check, at most once a minute.
	 *
	 * @return void
	 */
	public static function after_record() {
		if ( false !== get_transient( self::THROTTLE ) ) {
			return;
		}
		set_transient( self::THROTTLE, 1, MINUTE_IN_SECONDS );
		self::check();
	}

	/**
	 * A limit stopped a call: send its alert now (no throttle).
	 *
	 * @param string $scope  'global' or a group id.
	 * @param string $reason limit or paused.
	 * @return void
	 */
	public static function limit_reached( $scope, $reason = 'limit' ) {
		if ( 'limit' === $reason ) {
			self::check();
		}
	}

	/**
	 * Hourly: alerts, a new period (jobs paused by a limit resume), and Pro's hourly listeners.
	 *
	 * @return void
	 */
	public static function hourly() {
		$period = Period::current();
		$state  = self::state();
		if ( ! isset( $state['period'] ) || $state['period'] !== $period['key'] ) {
			$state['period'] = $period['key'];
			// Keep only this period's sent alerts and raises.
			$state['sent']   = isset( $state['sent'][ $period['key'] ] ) ? array( $period['key'] => $state['sent'][ $period['key'] ] ) : array();
			$state['raised'] = isset( $state['raised'][ $period['key'] ] ) ? array( $period['key'] => $state['raised'][ $period['key'] ] ) : array();
			self::save_state( $state );
			Spend::resume_jobs();
		}
		self::check();

		/**
		 * Hourly spending check (WPBot Pro's spike alert listens here).
		 *
		 * @param array $status Spend::status().
		 */
		do_action( 'qcld_wpbot_core/spend_hourly', Spend::status() );
	}

	/**
	 * Daily: retention, and the weekly summary hook on the owner's weekday.
	 *
	 * @return void
	 */
	public static function daily() {
		Rollup::prune();
		$settings = Spend_Settings::all();
		$week     = wp_date( 'o-W' );
		$state    = self::state();
		if ( ! empty( $settings['weekly_summary'] ) && (int) wp_date( 'N' ) === (int) $settings['summary_weekday'] && ( ! isset( $state['weekly'] ) || $state['weekly'] !== $week ) ) {
			$state['weekly'] = $week;
			self::save_state( $state );

			/**
			 * Once a week on the owner's weekday (WPBot Pro sends its summary email here).
			 *
			 * @param array $settings Spend settings.
			 */
			do_action( 'qcld_wpbot_core/spend_weekly', $settings );
		}
	}

	/**
	 * Send the alerts that are due now.
	 *
	 * @return string[] Alerts sent (for tests): "global:80", "limit:chatbot".
	 */
	public static function check() {
		$settings = Spend_Settings::all();
		$status   = Spend::status();
		$key      = $status['period']['key'];
		$state    = self::state();
		$sent     = isset( $state['sent'][ $key ] ) ? (array) $state['sent'][ $key ] : array();
		$done     = array();

		$global = $status['global'];
		if ( $global['limit'] > 0 ) {
			$already = isset( $sent['global'] ) ? array_map( 'intval', (array) $sent['global'] ) : array();
			$due     = self::due( $global['spent'], $global['limit'], (array) $settings['thresholds'], $already );
			if ( $due ) {
				$top = max( $due );
				self::email_threshold( $top, $status, $settings );
				$sent['global']  = array_values( array_unique( array_merge( $already, $due ) ) );
				$state['notice'] = array(
					'period'  => $key,
					'percent' => $top,
					'scope'   => 'global',
				);
				$done[]          = 'global:' . $top;
			}
		}
		foreach ( $status['groups'] as $id => $entry ) {
			if ( $entry['limit'] > 0 && $entry['spent'] >= $entry['limit'] && empty( $sent[ 'limit:' . $id ] ) ) {
				self::email_group_limit( $entry, $status, $settings );
				$sent[ 'limit:' . $id ] = true;
				$done[]                 = 'limit:' . $id;
				if ( empty( $state['notice'] ) || $state['notice']['period'] !== $key ) {
					$state['notice'] = array(
						'period'  => $key,
						'percent' => 100,
						'scope'   => $id,
					);
				}
			}
		}
		if ( $done ) {
			$state['sent'][ $key ] = $sent;
			self::save_state( $state );
		}
		return $done;
	}

	/**
	 * Thresholds due now (pure).
	 *
	 * @param float $spent      Spend.
	 * @param float $limit      Limit.
	 * @param int[] $thresholds Thresholds (percent).
	 * @param int[] $sent       Already sent this period.
	 * @return int[]
	 */
	public static function due( $spent, $limit, array $thresholds, array $sent ) {
		if ( $limit <= 0 ) {
			return array();
		}
		$due = array();
		foreach ( $thresholds as $threshold ) {
			$threshold = (int) $threshold;
			if ( $spent >= $limit * $threshold / 100 && ! in_array( $threshold, $sent, true ) ) {
				$due[] = $threshold;
			}
		}
		return $due;
	}

	/**
	 * Who gets the alerts: every administrator plus the owner's extra addresses.
	 *
	 * @param array $settings Spend settings.
	 * @return string[]
	 */
	public static function recipients( array $settings ) {
		$emails = array();
		if ( function_exists( 'get_users' ) ) {
			foreach ( (array) get_users(
				array(
					'role'   => 'administrator',
					'fields' => array( 'user_email' ),
				)
			) as $user ) {
				$emails[] = strtolower( (string) $user->user_email );
			}
		}
		if ( array() === $emails ) {
			$emails[] = strtolower( (string) get_option( 'admin_email' ) );
		}
		$emails = array_merge( $emails, (array) $settings['recipients'] );

		/**
		 * Filter who receives the spending alerts.
		 *
		 * @param string[] $emails   Addresses.
		 * @param array    $settings Spend settings.
		 */
		$emails = (array) apply_filters( 'qcld_wpbot_core/spend_alert_recipients', $emails, $settings );
		return array_values( array_unique( array_filter( array_map( 'sanitize_email', $emails ) ) ) );
	}

	/**
	 * Email for a global threshold.
	 *
	 * @param int   $percent  Threshold.
	 * @param array $status   Spend::status().
	 * @param array $settings Settings.
	 * @return bool
	 */
	private static function email_threshold( $percent, array $status, array $settings ) {
		$global = $status['global'];
		$period = Period::label( $status['period']['type'] );
		/* translators: 1: percent, 2: period name (Monthly or Weekly), 3: spend, 4: limit. */
		$subject = sprintf( __( 'AI spend at %1$d%% of your %2$s limit ($%3$s of $%4$s)', 'chatbot' ), $percent, strtolower( $period ), self::money( $global['spent'] ), self::money( $global['limit'] ) );
		$intro   = $percent >= 100
			/* translators: %s: reset date. */
			? sprintf( __( 'Your AI spending limit is reached. AI features stop or fall back until the limit resets on %s, or until you raise it.', 'chatbot' ), $status['period']['end'] )
			/* translators: 1: percent, 2: reset date. */
			: sprintf( __( 'Your site has used %1$d%% of its AI spending limit. The limit resets on %2$s.', 'chatbot' ), $percent, $status['period']['end'] );
		return self::send( $subject, $intro, $status, $settings );
	}

	/**
	 * Email for a feature's own limit.
	 *
	 * @param array $entry    Group entry.
	 * @param array $status   Spend::status().
	 * @param array $settings Settings.
	 * @return bool
	 */
	private static function email_group_limit( array $entry, array $status, array $settings ) {
		/* translators: 1: feature name, 2: spend, 3: limit. */
		$subject = sprintf( __( '%1$s reached its AI spending limit ($%2$s of $%3$s)', 'chatbot' ), $entry['label'], self::money( $entry['spent'] ), self::money( $entry['limit'] ) );
		$intro   = 'chatbot' === $entry['id']
			/* translators: %s: reset date. */
			? sprintf( __( 'The chatbot now answers without AI (your saved answers, FAQ and site search, then your contact options) until the limit resets on %s, or until you raise it. Visitors see no error.', 'chatbot' ), $status['period']['end'] )
			/* translators: 1: feature name, 2: reset date. */
			: sprintf( __( '%1$s stops using AI until the limit resets on %2$s, or until you raise it. Background jobs pause and continue later.', 'chatbot' ), $entry['label'], $status['period']['end'] );
		return self::send( $subject, $intro, $status, $settings, $entry['id'] );
	}

	/**
	 * Build and send one alert (HTML and plain text).
	 *
	 * @param string $subject  Subject without prefix.
	 * @param string $intro    First paragraph.
	 * @param array  $status   Spend::status().
	 * @param array  $settings Settings.
	 * @param string $group    Group the alert is about ('' = global).
	 * @return bool
	 */
	private static function send( $subject, $intro, array $status, array $settings, $group = '' ) {
		$to = self::recipients( $settings );
		if ( array() === $to ) {
			return false;
		}
		$name    = method_exists( Brand::class, 'product' ) ? (string) call_user_func( array( Brand::class, 'product' ) ) : Brand::name();
		$prefix  = '' !== (string) $settings['prefix'] ? $settings['prefix'] . ' ' : '';
		$subject = $prefix . '[' . $name . '] ' . $subject;
		$html    = self::body( $intro, $status, $group, $name );
		$sent    = wp_mail( $to, wp_specialchars_decode( $subject, ENT_QUOTES ), $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		return (bool) $sent;
	}

	/**
	 * Email body: spend against the limit, the top features, and two buttons (pure apart from URLs).
	 *
	 * @param string $intro  First paragraph.
	 * @param array  $status Spend::status().
	 * @param string $group  Group the alert is about.
	 * @param string $name   Product name.
	 * @return string HTML.
	 */
	public static function body( $intro, array $status, $group, $name ) {
		$groups = $status['groups'];
		uasort(
			$groups,
			static function ( $a, $b ) {
				return $b['spent'] <=> $a['spent'];
			}
		);
		$rows = '';
		foreach ( array_slice( $groups, 0, 3, true ) as $entry ) {
			if ( $entry['spent'] <= 0 ) {
				continue;
			}
			$limit = $entry['limit'] > 0 ? ' / $' . self::money( $entry['limit'] ) : '';
			$rows .= '<tr><td style="padding:4px 12px 4px 0">' . esc_html( $entry['label'] ) . '</td><td style="padding:4px 0;text-align:right">$' . esc_html( self::money( $entry['spent'] ) . $limit ) . '</td></tr>';
		}
		$global = $status['global'];
		$button = 'display:inline-block;padding:10px 16px;margin:0 8px 8px 0;border-radius:6px;background:#2271b1;color:#fff;text-decoration:none';

		$html  = '<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;font-size:14px;color:#1d2327;max-width:560px">';
		$html .= '<p>' . esc_html( $intro ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Spent this period (estimated)', 'chatbot' ) . ':</strong> $' . esc_html( self::money( $global['spent'] ) ) . ( $global['limit'] > 0 ? ' / $' . esc_html( self::money( $global['limit'] ) ) : '' ) . '</p>';
		if ( '' !== $rows ) {
			$html .= '<p><strong>' . esc_html__( 'Features spending the most', 'chatbot' ) . '</strong></p><table style="border-collapse:collapse">' . $rows . '</table>';
		}
		$html .= '<p style="margin-top:20px"><a style="' . esc_attr( $button ) . '" href="' . esc_url( Spend_Urls::settings( $group ) ) . '">' . esc_html__( 'Raise the limit', 'chatbot' ) . '</a>';
		$html .= '<a style="' . esc_attr( str_replace( '#2271b1', '#50575e', $button ) ) . '" href="' . esc_url( Spend_Urls::settings( '' !== $group ? $group : 'features' ) ) . '">' . esc_html__( 'Pause a feature', 'chatbot' ) . '</a></p>';
		/* translators: %s: product name. */
		$html .= '<p style="color:#646970;font-size:12px">' . esc_html( sprintf( __( 'Costs are estimates by %s from the providers\' list prices. Your provider\'s billing page shows what you are charged. You get this email because you are an administrator of this site or were added to its spending alerts.', 'chatbot' ), $name ) ) . '</p>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * The admin notice for the highest alert this period (dismissible per period).
	 *
	 * @return void
	 */
	public static function render_notice() {
		if ( ! current_user_can( Spend::capability() ) ) {
			return;
		}
		$state = self::state();
		if ( empty( $state['notice'] ) ) {
			return;
		}
		$notice = $state['notice'];
		$period = Period::current();
		if ( $notice['period'] !== $period['key'] || ( isset( $state['dismissed'] ) && $state['dismissed'] === $period['key'] ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		/**
		 * Whether the spending notice shows on this admin screen (default: the dashboard and WPBot screens).
		 *
		 * @param bool   $show Show.
		 * @param string $id   Screen id.
		 */
		if ( ! apply_filters( 'qcld_wpbot_core/spend_notice_screen', 'dashboard' === $id || (bool) preg_match( '/wpbot|chatbot|qcld|openai/i', $id ), $id ) ) {
			return;
		}
		$labels = Features::all();
		if ( 'global' === $notice['scope'] ) {
			/* translators: %d: percent of the limit. */
			$text = $notice['percent'] >= 100 ? __( 'Your AI spending limit is reached.', 'chatbot' ) : sprintf( __( 'Your site has used %d%% of its AI spending limit.', 'chatbot' ), (int) $notice['percent'] );
		} else {
			/* translators: %s: feature name. */
			$text = sprintf( __( '%s reached its AI spending limit.', 'chatbot' ), isset( $labels[ $notice['scope'] ] ) ? $labels[ $notice['scope'] ] : $notice['scope'] );
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=qcld_wpbot_core_spend_dismiss' ), 'qcld_wpbot_core_spend_dismiss' );
		printf(
			'<div class="notice notice-%1$s"><p>%2$s <a href="%3$s">%4$s</a> &middot; <a href="%5$s">%6$s</a></p></div>',
			$notice['percent'] >= 100 ? 'error' : 'warning',
			esc_html( $text ),
			esc_url( Spend_Urls::settings( 'global' === $notice['scope'] ? '' : $notice['scope'] ) ),
			esc_html__( 'Spending and limits', 'chatbot' ),
			esc_url( $dismiss ),
			esc_html__( 'Dismiss', 'chatbot' )
		);
	}

	/**
	 * Dismiss the notice for this period.
	 *
	 * @return void
	 */
	public static function dismiss() {
		check_admin_referer( 'qcld_wpbot_core_spend_dismiss' );
		if ( ! current_user_can( Spend::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'chatbot' ), 403 );
		}
		$state              = self::state();
		$state['dismissed'] = Period::current()['key'];
		self::save_state( $state );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Saved state (alerts sent, raises, notice).
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( Spend::STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Save the state (autoload off).
	 *
	 * @param array $state State.
	 * @return void
	 */
	public static function save_state( array $state ) {
		update_option( Spend::STATE, $state, false );
	}

	/**
	 * Two decimals (pure).
	 *
	 * @param float $usd Amount.
	 * @return string
	 */
	private static function money( $usd ) {
		return number_format( (float) $usd, 2, '.', ',' );
	}
}
