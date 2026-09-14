<?php
/**
 * Contact Form 7 Triggers
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CF7_Triggers
 */
class CF7_Triggers extends Trigger {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'cf7';
		$this->group = __( 'Forms', 'wpbot-automator' );
	}

	/**
	 * Register the triggers
	 */
	public function register() {
		//error_log( 'WPbot Automator - CF7_Triggers::register() called' );
		add_action( 'wpcf7_mail_sent', array( $this, 'handle_cf7' ), 10, 1 );
	}

	/**
	 * Handle Contact Form 7.
	 */
	public function handle_cf7( $contact_form ) {
		//error_log( 'WPbot Automator - handle_cf7 called for form ID: ' . $contact_form->id() );
		$submission = \WPCF7_Submission::get_instance();
		if ( $submission ) {
			$posted_data = $submission->get_posted_data();
			$data        = array(
				'form_id'   => $contact_form->id(),
				'form_name' => $contact_form->title(),
				'fields'    => $posted_data,
			);

			// Expose every CF7 field as a direct top-level token.
			// "your-name" → {your_name} AND {name} (strips leading "your_" prefix).
			// "your-email" → {your_email} AND {email}.
			foreach ( $posted_data as $key => $val ) {
				if ( ! is_scalar( $val ) || '' === $val ) {
					continue;
				}
				$safe = preg_replace( '/[^a-z0-9]+/', '_', strtolower( $key ) );
				$safe = trim( $safe, '_' );
				if ( ! $safe ) {
					continue;
				}
				$data[ $safe ] = $val;

				// Also register without the "your_" prefix so {name} works for "your-name".
				if ( 0 === strpos( $safe, 'your_' ) ) {
					$short = substr( $safe, 5 );
					if ( $short && ! isset( $data[ $short ] ) ) {
						$data[ $short ] = $val;
					}
				}
			}

			// Semantic aliases for common field types.
			foreach ( $posted_data as $key => $val ) {
				if ( ! is_scalar( $val ) ) {
					continue;
				}
				$key_lower = strtolower( $key );
				if ( false !== strpos( $key_lower, 'email' ) && is_email( $val ) && ! isset( $data['email'] ) ) {
					$data['email'] = $val;
				}
				if ( false !== strpos( $key_lower, 'name' ) ) {
					if ( false !== strpos( $key_lower, 'first' ) && ! isset( $data['first_name'] ) ) {
						$data['first_name'] = $val;
					} elseif ( false !== strpos( $key_lower, 'last' ) && ! isset( $data['last_name'] ) ) {
						$data['last_name'] = $val;
					} elseif ( ! isset( $data['full_name'] ) ) {
						$data['full_name'] = $val;
					}
				}
			}

			// Ensure {name} always resolves — alias from full_name if not already set.
			if ( ! isset( $data['name'] ) && isset( $data['full_name'] ) ) {
				$data['name'] = $data['full_name'];
			}

			//error_log( 'WPbot Automator - CF7 Trigger Firing with mapped data: ' . wp_json_encode( $data ) );
			$this->run( array(
				'sub_id' => 'cf7_submit',
				'data'   => $data,
			) );
		}
	}
}
