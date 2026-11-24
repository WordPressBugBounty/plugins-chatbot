<?php
/**
 * AI / Settings → Notifications: the addresses every WPBot feature sends its notification
 * emails to (owner, 20 November 2026).
 *
 * Added through the chatbot module's qcld_wpbot_chat/ai_settings_sections filter (wpbot-chatbot
 * 0.39.0 and later); with an older chatbot module the section does not show.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * AI / Settings section.
 */
final class Settings_Section {

	/**
	 * Section id.
	 */
	const ID = 'notifications';

	/**
	 * Posted field (an array, one address per field).
	 */
	const FIELD = 'notification_emails';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_chat/ai_settings_sections', array( self::class, 'section' ) );
		add_action( 'qcld_wpbot_chat/ai_settings_section', array( self::class, 'render' ) );
		add_action( 'qcld_wpbot_chat/ai_settings_save', array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * Add the section.
	 *
	 * @param array $sections id => [ title, dashicon, lead ].
	 * @return array
	 */
	public static function section( $sections ) {
		$sections             = is_array( $sections ) ? $sections : array();
		$sections[ self::ID ] = array(
			__( 'Notifications', 'chatbot' ),
			'dashicons-email-alt',
			__( 'Where WPBot sends its emails to you: new leads and messages, live chat, support tickets, reports and alerts.', 'chatbot' ),
		);
		return $sections;
	}

	/**
	 * Print the section's fields (inside the AI Settings form).
	 *
	 * @param string $section Section id.
	 * @return void
	 */
	public static function render( $section ) {
		if ( self::ID !== $section ) {
			return;
		}
		$saved   = Notification_Emails::saved();
		$list    = null !== $saved ? $saved : Notification_Emails::defaults();
		$invalid = self::take_invalid();
		echo '<div class="qcwp-card qcwp-notify-emails"><h2>' . esc_html__( 'Notification emails', 'chatbot' ) . '</h2>';
		echo '<p class="qcwp-help">' . esc_html__( 'Every WPBot feature sends its emails for you to these addresses: new leads and messages from the chatbot, live chat, conversational forms, support tickets, reports and alerts. Emails to your visitors and customers are not changed.', 'chatbot' ) . '</p>';
		if ( null === $saved ) {
			echo '<p class="qcwp-notify-emails-note">' . esc_html__( 'Suggested: the administration email from Settings → General, the ChatBot\'s email and every administrator. Until you save, each feature sends to the address it always used.', 'chatbot' ) . '</p>';
		}
		if ( $invalid ) {
			/* translators: %s: the addresses that were not valid, comma separated. */
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( __( 'Not saved, not a valid email address: %s', 'chatbot' ), implode( ', ', $invalid ) ) ) . '</p></div>';
		}
		echo '<ul class="qcwp-notify-emails-list" data-max="' . esc_attr( (string) Notification_Emails::MAX ) . '">';
		foreach ( $list as $email ) {
			self::row( $email );
		}
		// One empty field to add an address (more with the button below).
		self::row( '' );
		echo '</ul>';
		echo '<p><button type="button" class="qcwp-btn is-small qcwp-notify-emails-add">' . esc_html__( '+ Add an email address', 'chatbot' ) . '</button></p>';
		echo '<p class="qcwp-help">' . esc_html__( 'Remove an address with ×. Remove them all and save to go back to the suggested addresses.', 'chatbot' ) . '</p>';
		echo '</div>';
		self::script();
	}

	/**
	 * Save the section (the page has checked the user and the nonce).
	 *
	 * @param string $section Section id.
	 * @param array  $post    Posted values (unslashed).
	 * @return void
	 */
	public static function save( $section, $post ) {
		if ( self::ID !== $section || ! is_array( $post ) ) {
			return;
		}
		$raw    = isset( $post[ self::FIELD ] ) ? (array) $post[ self::FIELD ] : array();
		$parsed = Notification_Emails::parse( array_map( 'strval', $raw ) );
		Notification_Emails::save( $parsed['valid'] );
		if ( $parsed['invalid'] ) {
			set_transient( self::invalid_key(), $parsed['invalid'], 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * One address field.
	 *
	 * @param string $email Address.
	 * @return void
	 */
	private static function row( $email ) {
		echo '<li class="qcwp-notify-emails-row"><input type="email" name="' . esc_attr( self::FIELD ) . '[]" value="' . esc_attr( $email ) . '" class="regular-text" autocomplete="email" aria-label="' . esc_attr__( 'Email address', 'chatbot' ) . '" placeholder="' . esc_attr__( 'name@example.com', 'chatbot' ) . '">';
		echo '<button type="button" class="qcwp-notify-emails-remove" aria-label="' . esc_attr__( 'Remove this address', 'chatbot' ) . '">&times;</button></li>';
	}

	/**
	 * The addresses the last save could not use, once.
	 *
	 * @return string[]
	 */
	private static function take_invalid() {
		$invalid = get_transient( self::invalid_key() );
		if ( false === $invalid ) {
			return array();
		}
		delete_transient( self::invalid_key() );
		return array_map( 'strval', (array) $invalid );
	}

	/**
	 * Transient key for the current user.
	 *
	 * @return string
	 */
	private static function invalid_key() {
		return 'qcld_wpbot_core_notify_invalid_' . get_current_user_id();
	}

	/**
	 * Add and remove fields, and the field's layout.
	 *
	 * @return void
	 */
	private static function script() {
		echo '<style>.qcwp-notify-emails-list{margin:12px 0 4px;padding:0;list-style:none}.qcwp-notify-emails-row{display:flex;gap:8px;align-items:center;margin:0 0 8px}.qcwp-notify-emails-row input{flex:1 1 auto;max-width:420px}.qcwp-notify-emails-remove{width:32px;height:32px;border:1px solid currentColor;border-radius:8px;background:transparent;color:inherit;opacity:.7;cursor:pointer;font-size:18px;line-height:1}.qcwp-notify-emails-remove:hover,.qcwp-notify-emails-remove:focus{opacity:1}.qcwp-notify-emails-note{margin:8px 0 0;font-size:13px;opacity:.85}</style>';
		echo '<script>(function(){var c=document.querySelector(".qcwp-notify-emails");if(!c){return;}var l=c.querySelector(".qcwp-notify-emails-list");function row(){var r=l.querySelector(".qcwp-notify-emails-row").cloneNode(true);r.querySelector("input").value="";return r;}c.addEventListener("click",function(e){if(e.target.closest(".qcwp-notify-emails-add")){if(l.children.length>=+l.getAttribute("data-max")){return;}var r=row();l.appendChild(r);r.querySelector("input").focus();}var x=e.target.closest(".qcwp-notify-emails-remove");if(x){var li=x.closest("li");if(l.children.length>1){li.remove();}else{li.querySelector("input").value="";}}});})();</script>';
	}
}
