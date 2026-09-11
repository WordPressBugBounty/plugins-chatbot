<?php
/**
 * WPBot Triggers
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPBot_Triggers
 */
class WPBot_Triggers extends Trigger {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'wpbot';
		$this->group = __( 'WPBot', 'wpbot-automator' );
	}

	/**
	 * Get available sub-triggers
	 */
	public static function get_sub_triggers() {
		return array(
			'wpbot_chat_session_saved' => array(
				'title' => __( 'WPBot Chat Session Saved', 'wpbot-automator' ),
				'hook'  => 'wpbot_chat_session_saved_automator',
			),
			'conversationalforms_submit' => array(
				'title' => __( 'Conversational Forms Submitted', 'wpbot-automator' ),
				'hook'  => 'qccf_value_saved',
			)
		);
	}

	/**
	 * Register the triggers
	 */
	public function register() {
		// Register the delayed cron trigger
		add_action( 'wpbot_automator_delayed_trigger', array( $this, 'fire_delayed_trigger' ), 10, 1 );

		$triggers = self::get_sub_triggers();

		foreach ( $triggers as $id => $config ) {
			add_action( $config['hook'], function( ...$args ) use ( $id ) {
				$this->handle_trigger( $id, $args );
			}, 10, 10 );
		}
	}

	/**
	 * Handle the trigger execution
	 * 
	 * @param string $sub_id Sub-trigger ID.
	 * @param array $args Arguments from the hook.
	 */
	public function handle_trigger( $sub_id, $args ) {
		$data = array();

		switch ( $sub_id ) {
			case 'wpbot_chat_session_saved':
				if ( isset( $args[0] ) && is_array( $args[0] ) ) {
					$data = $args[0];
				}
				break;

			case 'conversationalforms_submit':
				if ( isset( $args[0], $args[1] ) ) {
					$all_answers     = $args[0];
					$submittedFormId = $args[1];
					
					$data = array(
						'form_id'   => $submittedFormId,
						'form_name' => 'Conversational Form ' . $submittedFormId,
						'fields'    => $all_answers,
					);

					$form = array();
					if ( class_exists( 'Qcformbuilder_Forms_Forms' ) ) {
						$form = \Qcformbuilder_Forms_Forms::get_form( $submittedFormId );
						if ( ! empty( $form['name'] ) ) {
							$data['form_name'] = $form['name'];
						}
					}

					if ( is_array( $all_answers ) ) {
						foreach ( $all_answers as $answer ) {
							if ( is_object( $answer ) && isset( $answer->slug ) && isset( $answer->value ) ) {
								$slug = $answer->slug;
								$val  = stripslashes( $answer->value );

								// Map by slug
								$safe_slug = preg_replace( '/[^a-z0-9]+/', '_', strtolower( $slug ) );
								$safe_slug = trim( $safe_slug, '_' );
								if ( $safe_slug && ! isset( $data[ $safe_slug ] ) ) {
									$data[ $safe_slug ] = $val;
								}

								// Aggressive mapping for email
								if ( ! isset( $data['email'] ) ) {
									if ( is_email( trim( $val ) ) ) {
										$data['email'] = trim( $val );
									} elseif ( strpos( $safe_slug, 'email' ) !== false ) {
										$data['email'] = $val;
									}
								}

								// Aggressive mapping for name
								if ( ! isset( $data['name'] ) ) {
									if ( strpos( $safe_slug, 'name' ) !== false || strpos( $safe_slug, 'first' ) !== false ) {
										$data['name'] = $val;
									}
								}

								// Map by label & heuristics
								if ( ! empty( $form['fields'] ) ) {
									foreach ( $form['fields'] as $field ) {
										if ( isset( $field['slug'] ) && $field['slug'] === $slug ) {
											if ( ! empty( $field['label'] ) ) {
												$safe_label = preg_replace( '/[^a-z0-9]+/', '_', strtolower( $field['label'] ) );
												$safe_label = trim( $safe_label, '_' );
												if ( $safe_label && ! isset( $data[ $safe_label ] ) ) {
													$data[ $safe_label ] = $val;
												}

												$label_lower = strtolower( $field['label'] );
												if ( strpos( $label_lower, 'email' ) !== false && ! isset( $data['email'] ) ) {
													$data['email'] = $val;
												} elseif ( ( strpos( $label_lower, 'name' ) !== false || strpos( $label_lower, 'first' ) !== false ) && ! isset( $data['name'] ) ) {
													$data['name'] = $val;
												}
											}

											if ( isset( $field['type'] ) ) {
												if ( $field['type'] === 'email' ) {
													$data['email'] = $val;
												} elseif ( in_array( $field['type'], array( 'name', 'first_name', 'text' ), true ) ) {
													// Only set if we haven't already deduced a name
													if ( $field['type'] === 'name' && ! isset( $data['name'] ) ) {
														$data['name'] = $val;
													}
												}
											}
											break;
										}
									}
								}
							}
						}
					}
				}
				break;
		}

		$this->run( array(
			'sub_id' => $sub_id,
			'data'   => $data,
		) );
	}

	/**
	 * Fire the delayed trigger by fetching the latest data from the database
	 * 
	 * @param string $session_id The chat session ID.
	 */
	public function fire_delayed_trigger( $session_id ) {
		if ( empty( $session_id ) ) {
			return;
		}

		global $wpdb;
		$tableuser         = $wpdb->prefix . 'wpbot_user';
		$tableconversation = $wpdb->prefix . 'wpbot_conversation';

		$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tableuser} WHERE session_id = %s", $session_id ) );
		
		if ( $user ) {
			$conv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tableconversation} WHERE user_id = %d", $user->id ) );
			
			$clean_conversation = '';
			if ( $conv && ! empty( $conv->conversation ) ) {
				$decoded = html_entity_decode( wp_unslash( $conv->conversation ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				// Add spaces/newlines around elements to prevent text merging
				$decoded = str_replace( array( '</li>', '</div>', '<br>', '<br/>', '</p>' ), "\n", $decoded );
				$clean_conversation = trim( wp_strip_all_tags( $decoded ) );
			}
			
			do_action( 'wpbot_chat_session_saved_automator', array(
				'session_id'   => $session_id,
				'name'         => $user->name,
				'email'        => $user->email,
				'phone'        => $user->phone,
				'conversation' => $clean_conversation,
				'source_url'   => '', // Retained empty or could be parsed from session
				'user_agent'   => $conv ? $conv->environment_info : '',
			) );
		}
	}
}
