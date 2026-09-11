<?php
/**
 * Form Triggers
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Form_Triggers
 */
class Form_Triggers extends Trigger {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'wpforms';
		$this->group = __( 'Forms', 'wpbot-automator' );
	}

	/**
	 * Get available sub-triggers
	 */
	public static function get_sub_triggers() {
		return array(
			'wpforms_submit'         => array(
				'title' => __( 'WPForms Submitted', 'wpbot-automator' ),
				'hook'  => 'wpforms_process_complete',
			),
			'fluentform_submit'      => array(
				'title' => __( 'Fluent Forms Submitted', 'wpbot-automator' ),
				'hook'  => 'fluentform_submission_inserted',
			),
			'gravityforms_submit'    => array(
				'title' => __( 'Gravity Forms Submitted', 'wpbot-automator' ),
				'hook'  => 'gform_after_submission',
			),
		);
	}

	/**
	 * Register the triggers
	 */
	public function register() {
	//	error_log( 'WPbot Automator - Registering Form Triggers' );
		add_action( 'wpforms_process_complete', array( $this, 'handle_wpforms' ), 10, 4 );
	// CF7 is handled by the dedicated CF7 trigger class.
	// add_action( 'wpcf7_mail_sent', array( $this, 'handle_cf7' ), 10, 1 );
	//	error_log( 'WPbot Automator - CF7 hooked' );
		error_log( 'WPbot Automator - WPForms hooked' );
		add_action( 'fluentform_submission_inserted', array( $this, 'handle_fluentform' ), 10, 3 );
	//	error_log( 'WPbot Automator - Fluent hooked' );
		add_action( 'gform_after_submission', array( $this, 'handle_gravityforms' ), 10, 2 );
	//	error_log( 'WPbot Automator - Gravity hooked' );
	}

	/**
	 * Handle WPForms.
	 */
	public function handle_wpforms( $fields, $entry, $form_data, $entry_id ) {
		$data = array(
			'form_id'   => $form_data['id'],
			'form_name' => $form_data['settings']['form_title'],
			'fields'    => $fields,
			'entry_id'  => $entry_id,
		);

		// Expose each WPForms field value as a direct {token} using the field label.
		// e.g. a field labelled "Email" → {email}, "First Name" → {first_name}.
		foreach ( $fields as $field ) {
			if ( ! isset( $field['name'], $field['value'] ) || ! is_scalar( $field['value'] ) ) {
				continue;
			}
			$safe = preg_replace( '/[^a-z0-9]+/', '_', strtolower( $field['name'] ) );
			$safe = trim( $safe, '_' );
			if ( $safe && ! isset( $data[ $safe ] ) ) {
				$data[ $safe ] = $field['value'];
			}
		}

		$this->run( array(
			'sub_id' => 'wpforms_submit',
			'data'   => $data,
		) );
	}

	/**
	 * Handle Contact Form 7.
	 */
	public function handle_cf7( $contact_form ) {
		$full_trigger_id = $this->id;
		\WPbot_Automator\Engine\Workflow_Runner::run_workflows_for_trigger( $full_trigger_id, $contact_form );
		$submission = \WPCF7_Submission::get_instance();
		if ( $submission ) {
			$this->run( array(
				'sub_id'  => 'cf7_submit',
				'data'    => array(
					'form_id'   => $contact_form->id(),
					'form_name' => $contact_form->title(),
					'fields'    => $submission->get_posted_data(),
				),
			) );
		}
	}

	/**
	 * Handle Fluent Forms.
	 */
	public function handle_fluentform( $insert_id, $raw_data, $form ) {
		$data = array(
			'form_id'   => $form->id,
			'form_name' => $form->title,
			'fields'    => $raw_data,
			'entry_id'  => $insert_id,
		);

		// FluentForms submitted data is a flat key→value array.
		if ( is_array( $raw_data ) ) {
			foreach ( $raw_data as $key => $val ) {
				if ( is_scalar( $val ) && '' !== $val ) {
					$safe = preg_replace( '/[^a-z0-9]+/', '_', strtolower( $key ) );
					$safe = trim( $safe, '_' );
					if ( $safe && ! isset( $data[ $safe ] ) ) {
						$data[ $safe ] = $val;
					}
				}
			}
		}

		$this->run( array(
			'sub_id' => 'fluentform_submit',
			'data'   => $data,
		) );
	}

	/**
	 * Handle Gravity Forms.
	 */
	public function handle_gravityforms( $entry, $form ) {
		$data = array(
			'form_id'   => $form['id'],
			'form_name' => $form['title'],
			'fields'    => $entry,
		);

		// Gravity Forms entry is a flat field_id → value array; also expose via label.
		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$field_id = $field['id'] ?? null;
				if ( ! $field_id || ! isset( $entry[ $field_id ] ) || ! is_scalar( $entry[ $field_id ] ) ) {
					continue;
				}
				$safe = preg_replace( '/[^a-z0-9]+/', '_', strtolower( $field['label'] ?? '' ) );
				$safe = trim( $safe, '_' );
				if ( $safe && ! isset( $data[ $safe ] ) ) {
					$data[ $safe ] = $entry[ $field_id ];
				}
			}
		}

		$this->run( array(
			'sub_id' => 'gravityforms_submit',
			'data'   => $data,
		) );
	}
}
