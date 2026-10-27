<?php
/**
 * Email Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Email_Actions
 */
class Email_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'mail';
		$this->group = __( 'Mail', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 * 
	 * @param array $action_data Configuration for this action from the workflow.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return bool|array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'send_email':
				return $this->send_email( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	/**
	 * Send an email
	 */
	private function send_email( $config, $trigger_data ) {
		global $wpdb;
		$config_values = isset( $config['config'] ) ? $config['config'] : array();
		
		// Raw values; tokens are parsed once below, after the template is chosen.
		$to          = isset( $config_values['to_email'] ) ? (string) $config_values['to_email'] : '';
		$subject     = isset( $config_values['subject'] ) ? (string) $config_values['subject'] : '';
		$body        = isset( $config_values['body'] ) ? (string) $config_values['body'] : '';
		$template_id = isset( $config_values['template_id'] ) ? absint( $config_values['template_id'] ) : 0;
		$from_email  = isset( $config_values['from_email'] ) ? $this->parse_tokens( $config_values['from_email'], $trigger_data ) : get_option( 'admin_email' );
		$from_name   = isset( $config_values['from_name'] ) ? $this->parse_tokens( $config_values['from_name'], $trigger_data ) : get_option( 'blogname' );
		$cc          = isset( $config_values['cc_email'] ) ? (string) $config_values['cc_email'] : '';
		$bcc         = isset( $config_values['bcc_email'] ) ? (string) $config_values['bcc_email'] : '';
		$reply_to    = isset( $config_values['reply_to'] ) ? $this->parse_tokens( $config_values['reply_to'], $trigger_data ) : '';
		$is_html     = isset( $config_values['is_html'] ) ? (bool) $config_values['is_html'] : true;

		$from_email = sanitize_email( $from_email );
		if ( ! is_email( $from_email ) ) {
			$from_email = get_option( 'admin_email' );
		}
		$reply_to = sanitize_email( $reply_to );
		if ( ! is_email( $reply_to ) ) {
			$reply_to = '';
		}

		// If a template is selected, use it instead of the manual body.
		if ( ! empty( $template_id ) ) {
			$table = \WPbot_Automator\Core\Database::get_email_templates_table();
			$template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $template_id ), ARRAY_A );
			if ( $template && ! empty( $template['body_html'] ) ) {
				$body = $template['body_html'];
				$is_html = true; // Templates are always HTML.
			}
		}

		if ( empty( $to ) || empty( $subject ) || empty( $body ) ) {
			return array(
				'success' => false,
				'message' => 'To, Subject, and Body (or Template) are required.',
			);
		}

		// Parse tokens once. In HTML mode, visitor/AI values are escaped so they can't inject markup.
		$to      = $this->parse_tokens( $to, $trigger_data );
		$subject = wp_strip_all_tags( $this->parse_tokens( $subject, $trigger_data ) );
		$body    = $this->parse_tokens( $body, $trigger_data, $is_html ? 'esc_html' : false );
		$cc      = $this->parse_tokens( $cc, $trigger_data );
		$bcc     = $this->parse_tokens( $bcc, $trigger_data );

		$to_list = $this->sanitize_email_list( $to );
		if ( empty( $to_list ) ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Invalid or missing "To" email address: "%s".', esc_html( $to ) ),
			);
		}
		$to = 1 === count( $to_list ) ? reset( $to_list ) : $to_list;

		$cc_list = $this->sanitize_email_list( $cc );
		$bcc_list = $this->sanitize_email_list( $bcc );

		$headers = array();
		
		// Set Content-Type
		if ( $is_html ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		} else {
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		}

		// Set From
		$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

		// Set Reply-To
		if ( ! empty( $reply_to ) && $reply_to !== $from_email ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		// Set CC
		if ( ! empty( $cc_list ) ) {
			$headers[] = 'Cc: ' . implode( ', ', $cc_list );
		}

		// Set BCC
		if ( ! empty( $bcc_list ) ) {
			$headers[] = 'Bcc: ' . implode( ', ', $bcc_list );
		}

		// Convert headers to string for better compatibility with logging plugins.
		$headers_str = implode( "\r\n", $headers );

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( ! $sent ) {
			//error_log( sprintf( 'WPbot Automator - send_email failed. to=%s from=%s reply_to=%s headers=%s', is_array( $to ) ? implode( ',', $to ) : $to, $from_email, $reply_to, wp_json_encode( $headers ) ) );
		}

		if ( $sent ) {
			return array(
				'success' => true,
				'message' => 'Email sent successfully.',
			);
		}

		return array(
			'success' => false,
			'message' => 'Failed to send email.',
		);
	}

	/**
	 * Sanitize a list of email addresses.
	 *
	 * @param string|array $emails Emails string or array.
	 * @return array
	 */
	private function sanitize_email_list( $emails ) {
		if ( is_array( $emails ) ) {
			$emails = implode( ',', $emails );
		}

		if ( ! is_string( $emails ) ) {
			return array();
		}

		$parts = preg_split( '/[;,\s]+/', $emails, -1, PREG_SPLIT_NO_EMPTY );
		$result = array();
		foreach ( $parts as $email ) {
			$email = sanitize_email( $email );
			if ( is_email( $email ) ) {
				$result[] = $email;
			}
		}

		return array_unique( $result );
	}
}
