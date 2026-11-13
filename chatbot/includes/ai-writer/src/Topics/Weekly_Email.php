<?php
/**
 * Weekly topics email (off by default): the new ideas Topic Finder found
 * since the last email, with a link back. Nothing new, no email.
 *
 * The footer line about more sources is filterable
 * (qcld_wpbot_writer/weekly_email_footer) and the owner can hide it.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics;

use QuantumCloud\AiWriter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Weekly email.
 */
final class Weekly_Email {

	const CRON      = 'qcld_wpbot_writer_weekly_email';
	const LAST_SENT = 'qcld_wpbot_writer_email_sent';

	/**
	 * Hook the schedule.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( self::CRON, array( __CLASS__, 'scheduled' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Keep the weekly event while the email is on.
	 *
	 * @return void
	 */
	public static function schedule() {
		$next = wp_next_scheduled( self::CRON );
		if ( Settings::get( 'weekly_email' ) ) {
			if ( ! $next ) {
				// A day after the weekly refresh, so the list is fresh.
				wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON );
			}
		} elseif ( $next ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * Cron handler.
	 *
	 * @return void
	 */
	public static function scheduled() {
		if ( Settings::get( 'weekly_email' ) ) {
			self::send();
		}
	}

	/**
	 * Recipients: the setting, else the site admin email.
	 *
	 * @return string[]
	 */
	public static function recipients() {
		$list = (array) Settings::get( 'email_recipients' );
		return array() !== $list ? $list : array( (string) get_option( 'admin_email' ) );
	}

	/**
	 * Send the email when there is something new.
	 *
	 * @param string[] $to      Recipients (default: the setting).
	 * @param bool     $preview Send even with nothing new (a test), and do not move the "last sent" mark.
	 * @return array{sent: bool, count: int, reason: string}
	 */
	public static function send( array $to = array(), $preview = false ) {
		$since = (string) get_option( self::LAST_SENT, '' );
		$since = '' !== $since ? $since : gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$ideas = Ideas::new_since( $preview ? gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) : $since, 15 );
		if ( array() === $ideas && ! $preview ) {
			return array(
				'sent'   => false,
				'count'  => 0,
				'reason' => 'nothing_new',
			);
		}
		$to      = array() !== $to ? $to : self::recipients();
		$subject = sprintf(
			/* translators: 1: number of ideas, 2: site name. */
			_n( '%1$d new topic idea for %2$s', '%1$d new topic ideas for %2$s', count( $ideas ), 'chatbot' ),
			count( $ideas ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$sent = wp_mail( $to, $subject, self::body( $ideas ), array( 'Content-Type: text/html; charset=UTF-8' ) );
		if ( $sent && ! $preview ) {
			update_option( self::LAST_SENT, gmdate( 'Y-m-d H:i:s' ), false );
		}
		return array(
			'sent'   => (bool) $sent,
			'count'  => count( $ideas ),
			'reason' => $sent ? '' : 'mail_failed',
		);
	}

	/**
	 * Email HTML. Every value is escaped; links point to this site.
	 *
	 * @param array[] $ideas Ideas.
	 * @return string
	 */
	public static function body( array $ideas ) {
		$sources = array();
		foreach ( Topic_Finder::sources() as $id => $source ) {
			$sources[ $id ] = $source['label'];
		}
		$link  = \QuantumCloud\AiWriter\Module::page_url( array( 'tab' => 'topics' ) );
		$html  = '<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;font-size:15px;line-height:1.5;color:#1e1e1e;max-width:640px">';
		$html .= '<h2 style="font-size:20px">' . esc_html__( 'New topic ideas this week', 'chatbot' ) . '</h2>';
		if ( array() === $ideas ) {
			$html .= '<p>' . esc_html__( 'Nothing new this week. This is a test email.', 'chatbot' ) . '</p>';
		}
		$html .= '<ul style="padding-left:18px">';
		foreach ( $ideas as $idea ) {
			$label = isset( $sources[ $idea['source'] ] ) ? $sources[ $idea['source'] ] : $idea['source'];
			$html .= '<li style="margin-bottom:12px"><strong>' . esc_html( $idea['title'] ) . '</strong>'
				. ' <span style="color:#757575">(' . esc_html( $label ) . ')</span>'
				. ( '' !== $idea['reason'] ? '<br>' . esc_html( $idea['reason'] ) : '' )
				. '</li>';
		}
		$html  .= '</ul>';
		$html  .= '<p><a href="' . esc_url( $link ) . '" style="background:#2271b1;color:#fff;padding:8px 14px;border-radius:4px;text-decoration:none">' . esc_html__( 'Open Topic Finder', 'chatbot' ) . '</a></p>';
		$html  .= '<p style="color:#757575;font-size:13px">' . esc_html__( 'You get this email because the weekly topics email is on in AI Writer > Settings > Topic Finder. Turn it off there.', 'chatbot' ) . '</p>';
		$footer = self::footer();
		if ( '' !== $footer ) {
			$html .= '<p style="color:#757575;font-size:13px;border-top:1px solid #ddd;padding-top:8px">' . esc_html( $footer ) . '</p>';
		}
		return $html . '</div>';
	}

	/**
	 * The footer line ('' when hidden or removed by an extension).
	 *
	 * @return string
	 */
	public static function footer() {
		if ( Settings::get( 'hide_email_footer' ) ) {
			return '';
		}

		$pro    = \QuantumCloud\AiWriter\Module::pro_product();
		$footer = \QuantumCloud\AiWriter\Module::has_chatbot()
			/* translators: %s: paid edition name, for example WPBot Pro. */
			? sprintf( __( 'More topic sources (visitor questions from your chats, YouTube, Wikipedia) and one combined ranking are available in %s.', 'chatbot' ), $pro )
			/* translators: %s: paid edition name, for example WPBot Pro. */
			: sprintf( __( 'More topic sources (YouTube, Wikipedia) and one combined ranking are available in %s.', 'chatbot' ), $pro );

		/**
		 * Footer line of the weekly topics email. Return '' to remove it.
		 *
		 * @param string $footer Text.
		 */
		return (string) apply_filters( 'qcld_wpbot_writer/weekly_email_footer', $footer );
	}
}
