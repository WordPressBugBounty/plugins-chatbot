<?php
/**
 * Automation runtime: triggers, enrollment and step execution.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Enrolls contacts into published automations when a trigger fires and advances
 * `automation_runs` (ARCHITECTURE §11.6). One run per contact per automation.
 *
 * A run waiting at `step_index` i executes step i at `next_run_at`; afterwards step i+1 is
 * scheduled at now + its delay. `end`, or running past the last step, completes the run.
 */
final class AutomationEngine {

	const TRIGGERS = array( 'subscriber_created', 'subscriber_status_change', 'tag_added', 'tag_removed', 'list_added', 'list_removed', 'form_submitted', 'link_clicked', 'email_opened', 'woo_order_completed', 'user_registered' );
	const ACTIONS  = array( 'send_email', 'wait', 'add_tag', 'remove_tag', 'add_to_list', 'remove_from_list', 'update_contact', 'webhook', 'end' );
	const UNITS    = array( 'minutes', 'hours', 'days' );

	/**
	 * Contact fields an `update_contact` step may change.
	 *
	 * @var string[]
	 */
	const UPDATABLE = array( 'first_name', 'last_name', 'phone', 'company', 'website', 'status' );

	/**
	 * Zero-delay steps executed back to back in one pass, at most.
	 */
	const MAX_CHAIN = 20;

	/**
	 * Whether init() already ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Subscribe to foundation, mail, WordPress and WooCommerce hooks.
	 */
	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'wpbot_crm/contact_created', array( __CLASS__, 'on_contact_created' ), 20, 1 );
		add_action( 'wpbot_crm/contact_status_changed', array( __CLASS__, 'on_status_changed' ), 20, 3 );
		add_action( 'wpbot_crm/group_added', array( __CLASS__, 'on_group_added' ), 20, 2 );
		add_action( 'wpbot_crm/group_removed', array( __CLASS__, 'on_group_removed' ), 20, 2 );
		add_action( 'wpbot_crm/email_opened', array( __CLASS__, 'on_email_opened' ), 20, 2 );
		add_action( 'wpbot_crm/link_clicked', array( __CLASS__, 'on_link_clicked' ), 20, 3 );
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_woo_completed' ), 20, 1 );
	}

	/**
	 * Fire a trigger for a contact: enroll it into every matching published automation.
	 *
	 * @param string $trigger_name One of TRIGGERS.
	 * @param int    $contact_id   Contact id.
	 * @param array  $context      Trigger data (group_id, status, source, url, message_id, order_id…).
	 */
	public static function trigger( string $trigger_name, int $contact_id, array $context = array() ): void {
		if ( $contact_id <= 0 || ! in_array( $trigger_name, self::TRIGGERS, true ) ) {
			return;
		}
		foreach ( ( new AutomationRepository() )->published_for_trigger( $trigger_name ) as $automation ) {
			self::enroll( $automation, $contact_id, $context );
		}
	}

	/**
	 * Enroll a contact into one automation (no-op when already enrolled, no steps, or filtered out).
	 *
	 * @param array $automation Automation row.
	 * @param int   $contact_id Contact id.
	 * @param array $context    Trigger data, matched against `trigger_config`.
	 * @return int Run id, 0 when not enrolled.
	 */
	public static function enroll( array $automation, $contact_id, array $context = array() ) {
		$steps = self::steps( $automation );
		if ( empty( $steps ) || ! self::matches( (array) $automation['trigger_config'], $context ) ) {
			return 0;
		}
		$run_id = ( new RunRepository() )->enroll( (int) $automation['id'], (int) $contact_id, self::due_at( $steps[0] ) );
		if ( $run_id ) {
			( new AutomationRepository() )->increment( (int) $automation['id'], 'enrolled' );
			do_action( 'wpbot_crm/automation_enrolled', (int) $automation['id'], (int) $contact_id, $run_id );
		}
		return $run_id;
	}

	/**
	 * Execute due runs (called by the cron tick under its lock).
	 *
	 * @param int $limit Max runs to handle.
	 * @return int Runs handled.
	 */
	public static function run_due( int $limit = 100 ): int {
		$runs        = new RunRepository();
		$automations = new AutomationRepository();
		$contacts    = new ContactRepository();
		$cache       = array();
		$handled     = 0;

		foreach ( $runs->due( Dates::now(), max( 1, $limit ) ) as $run ) {
			++$handled;
			$aid = (int) $run['automation_id'];
			if ( ! array_key_exists( $aid, $cache ) ) {
				$cache[ $aid ] = $automations->find( $aid );
			}
			$automation = $cache[ $aid ];
			$contact    = $contacts->find( (int) $run['contact_id'] );
			if ( ! $automation || ! $contact ) {
				$runs->finish( (int) $run['id'], 'exited' );
				continue;
			}
			if ( 'published' !== $automation['status'] ) {
				// Paused (draft): look again in an hour.
				$runs->advance( (int) $run['id'], (int) $run['step_index'], Dates::add( '', 1, 'hours' ) );
				continue;
			}
			self::advance_run( $run, $automation, $contact );
		}
		return $handled;
	}

	/**
	 * Run the current step (and following zero-delay steps), then reschedule or finish.
	 *
	 * @param array $run        Run row.
	 * @param array $automation Automation row.
	 * @param array $contact    Contact row.
	 */
	private static function advance_run( array $run, array $automation, array $contact ) {
		$runs  = new RunRepository();
		$steps = self::steps( $automation );
		$index = (int) $run['step_index'];

		for ( $chain = 0; $chain < self::MAX_CHAIN; $chain++ ) {
			if ( ! isset( $steps[ $index ] ) ) {
				self::complete( $run, $automation );
				return;
			}
			$outcome = self::execute( $steps[ $index ], $contact, $automation );
			if ( 'retry' === $outcome ) {
				$runs->advance( (int) $run['id'], $index, Dates::add( '', 5, 'minutes' ) );
				return;
			}
			if ( 'end' === $outcome || ! isset( $steps[ $index + 1 ] ) ) {
				self::complete( $run, $automation );
				return;
			}
			++$index;
			if ( (int) $steps[ $index ]['delay_value'] > 0 ) {
				break;
			}
			$fresh   = ( new ContactRepository() )->find( (int) $contact['id'] );
			$contact = $fresh ? $fresh : $contact;
		}
		$runs->advance( (int) $run['id'], $index, self::due_at( $steps[ $index ] ) );
	}

	/**
	 * Mark a run done and count the completion.
	 *
	 * @param array $run        Run row.
	 * @param array $automation Automation row.
	 */
	private static function complete( array $run, array $automation ) {
		( new RunRepository() )->finish( (int) $run['id'], 'done' );
		( new AutomationRepository() )->increment( (int) $automation['id'], 'completed' );
		do_action( 'wpbot_crm/automation_completed', (int) $automation['id'], (int) $run['contact_id'] );
	}

	/**
	 * Execute one step.
	 *
	 * @param array $step       Normalised step.
	 * @param array $contact    Contact row.
	 * @param array $automation Automation row.
	 * @return string next|retry|end
	 */
	private static function execute( array $step, array $contact, array $automation ) {
		$settings = (array) $step['settings'];
		switch ( $step['action_name'] ) {
			case 'end':
				return 'end';
			case 'send_email':
				return self::send_email( $step, $contact, $automation );
			case 'add_tag':
			case 'add_to_list':
			case 'remove_tag':
			case 'remove_from_list':
				self::change_group( $step['action_name'], $settings, (int) $contact['id'] );
				return 'next';
			case 'update_contact':
				self::update_contact( $settings, (int) $contact['id'] );
				return 'next';
			case 'webhook':
				self::webhook( $settings, $contact, $automation, $step );
				return 'next';
		}
		return 'next'; // wait: the delay already did the waiting.
	}

	/**
	 * send_email step. Non-sendable contacts and empty emails are skipped; a missing Mailer retries later.
	 *
	 * @param array $step       Step.
	 * @param array $contact    Contact.
	 * @param array $automation Automation.
	 * @return string next|retry
	 */
	private static function send_email( array $step, array $contact, array $automation ) {
		$settings = (array) $step['settings'];
		$subject  = self::first_string( $settings, array( 'subject', 'email_subject' ) );
		$body     = self::first_string( $settings, array( 'body', 'email_body' ) );
		if ( '' === trim( $subject ) || '' === trim( $body ) || ! in_array( $contact['status'], array( 'subscribed', 'customer' ), true ) ) {
			return 'next';
		}
		$mailer = '\\QuantumCloud\\WPBot\\CRM\\Mail\\Mailer';
		if ( ! class_exists( $mailer ) || ! method_exists( $mailer, 'send_to_contact' ) ) {
			return 'retry';
		}
		if ( wp_strip_all_tags( $body ) === $body ) {
			$body = wpautop( $body );
		}
		call_user_func(
			array( $mailer, 'send_to_contact' ),
			$contact,
			$subject,
			$body,
			array(
				'automation_id' => (int) $automation['id'],
				'step_key'      => 'step-' . (int) $step['id'],
			)
		);
		return 'next';
	}

	/**
	 * Tag/list steps. Target from settings.tag_id|list_id (id) or tag|tag_name|list|list_name (name).
	 *
	 * @param string $action     add_tag|remove_tag|add_to_list|remove_from_list.
	 * @param array  $settings   Step settings.
	 * @param int    $contact_id Contact id.
	 */
	private static function change_group( $action, array $settings, $contact_id ) {
		$type   = false !== strpos( $action, 'tag' ) ? 'tag' : 'list';
		$adding = 0 === strpos( $action, 'add' );
		$groups = new GroupRepository();

		$group_id = isset( $settings[ $type . '_id' ] ) ? absint( $settings[ $type . '_id' ] ) : 0;
		if ( $group_id && ! $groups->find_typed( $group_id, $type ) ) {
			$group_id = 0;
		}
		if ( ! $group_id ) {
			$name = self::first_string( $settings, array( $type, $type . '_name' ) );
			if ( '' === trim( $name ) ) {
				return;
			}
			if ( $adding ) {
				$group_id = $groups->ensure( $type, $name );
			} else {
				$group    = $groups->find_by_slug( $type, GroupRepository::slugify( $name ) );
				$group_id = $group ? (int) $group['id'] : 0;
			}
		}
		if ( ! $group_id ) {
			return;
		}
		$service = new ContactService();
		if ( $adding ) {
			$service->add_to_group( $contact_id, $group_id );
		} else {
			$service->remove_from_group( $contact_id, $group_id );
		}
	}

	/**
	 * update_contact step: settings.field (whitelisted) = settings.value.
	 *
	 * @param array $settings   Step settings.
	 * @param int   $contact_id Contact id.
	 */
	private static function update_contact( array $settings, $contact_id ) {
		$field = isset( $settings['field'] ) ? sanitize_key( (string) $settings['field'] ) : '';
		if ( ! in_array( $field, self::UPDATABLE, true ) || ! isset( $settings['value'] ) || ! is_scalar( $settings['value'] ) ) {
			return;
		}
		( new ContactService() )->update( $contact_id, array( $field => (string) $settings['value'] ) );
	}

	/**
	 * webhook step: POST the contact as JSON to settings.url.
	 *
	 * @param array $settings   Step settings.
	 * @param array $contact    Contact.
	 * @param array $automation Automation.
	 * @param array $step       Step.
	 */
	private static function webhook( array $settings, array $contact, array $automation, array $step ) {
		$url = isset( $settings['url'] ) ? esc_url_raw( (string) $settings['url'], array( 'http', 'https' ) ) : '';
		if ( '' === $url ) {
			return;
		}
		unset( $contact['token'] );
		wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'event'      => 'automation_step',
						'automation' => array(
							'id'    => (int) $automation['id'],
							'title' => $automation['name'],
						),
						'step_id'    => (int) $step['id'],
						'contact'    => $contact,
					)
				),
			)
		);
	}

	/**
	 * Normalised steps of an automation.
	 *
	 * @param array $automation Automation row.
	 * @return array[] {id, action_name, delay_value, delay_unit, settings}
	 */
	public static function steps( array $automation ) {
		$out = array();
		foreach ( (array) $automation['steps'] as $step ) {
			if ( is_array( $step ) && isset( $step['action_name'] ) && in_array( $step['action_name'], self::ACTIONS, true ) ) {
				$out[] = array(
					'id'          => isset( $step['id'] ) ? (int) $step['id'] : 0,
					'action_name' => $step['action_name'],
					'delay_value' => isset( $step['delay_value'] ) ? max( 0, (int) $step['delay_value'] ) : 0,
					'delay_unit'  => isset( $step['delay_unit'] ) && in_array( $step['delay_unit'], self::UNITS, true ) ? $step['delay_unit'] : 'minutes',
					'settings'    => isset( $step['settings'] ) && is_array( $step['settings'] ) ? $step['settings'] : array(),
				);
			}
		}
		return $out;
	}

	/**
	 * Re-point waiting runs after the steps were replaced, following step ids.
	 * A run whose step was deleted moves to the next surviving step; none left → done.
	 *
	 * @param int     $automation_id Automation id.
	 * @param array[] $old_steps     Previous normalised steps.
	 * @param array[] $new_steps     New normalised steps.
	 */
	public static function remap_runs( $automation_id, array $old_steps, array $new_steps ) {
		global $wpdb;
		$position = array();
		foreach ( $new_steps as $i => $step ) {
			$position[ (int) $step['id'] ] = $i;
		}
		$table   = Schema::table( 'automation_runs' );
		$waiting = $wpdb->get_results( $wpdb->prepare( "SELECT id, contact_id, step_index FROM {$table} WHERE automation_id = %d AND status = 'waiting'", (int) $automation_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$runs    = new RunRepository();
		foreach ( (array) $waiting as $run ) {
			$target = null;
			for ( $i = (int) $run['step_index']; $i < count( $old_steps ); $i++ ) {
				if ( isset( $position[ (int) $old_steps[ $i ]['id'] ] ) ) {
					$target = $position[ (int) $old_steps[ $i ]['id'] ];
					break;
				}
			}
			if ( null === $target ) {
				self::complete( $run, array( 'id' => (int) $automation_id ) );
			} elseif ( $target !== (int) $run['step_index'] ) {
				$runs->update( (int) $run['id'], array( 'step_index' => $target ) );
			}
		}
	}

	/**
	 * Whether trigger data satisfies an automation's optional trigger_config.
	 * Each config key (e.g. group_ids, status, source, url) narrows the trigger; empty = any.
	 *
	 * @param array $config  trigger_config.
	 * @param array $context Trigger data.
	 * @return bool
	 */
	public static function matches( array $config, array $context ) {
		foreach ( $config as $key => $expected ) {
			$expected = array_filter( array_map( 'strval', (array) $expected ), 'strlen' );
			if ( empty( $expected ) ) {
				continue;
			}
			$ctx_key = preg_replace( '/_ids$/', '_id', (string) $key );
			if ( ! isset( $context[ $ctx_key ] ) || ! is_scalar( $context[ $ctx_key ] ) ) {
				return false;
			}
			$actual = (string) $context[ $ctx_key ];
			if ( 'url' === $ctx_key ) {
				$hit = false;
				foreach ( $expected as $needle ) {
					$hit = $hit || false !== strpos( $actual, $needle );
				}
				if ( ! $hit ) {
					return false;
				}
			} elseif ( ! in_array( $actual, $expected, true ) ) {
				return false;
			}
		}
		return true;
	}

	/** Hook: contact created. @param array $contact Contact. */
	public static function on_contact_created( $contact ) {
		if ( is_array( $contact ) && ! empty( $contact['id'] ) ) {
			self::trigger( 'subscriber_created', (int) $contact['id'], array( 'source' => (string) $contact['source'] ) );
		}
	}

	/** Hook: status changed. @param array $contact Contact. @param string $old Old. @param string $new New. */
	public static function on_status_changed( $contact, $old, $new ) {
		if ( is_array( $contact ) && ! empty( $contact['id'] ) ) {
			$context = array(
				'status'     => (string) $new,
				'old_status' => (string) $old,
			);
			self::trigger( 'subscriber_status_change', (int) $contact['id'], $context );
		}
	}

	/** Hook: group added. @param int $contact_id Contact. @param array $group Group. */
	public static function on_group_added( $contact_id, $group ) {
		self::group_trigger( 'added', (int) $contact_id, $group );
	}

	/** Hook: group removed. @param int $contact_id Contact. @param array $group Group. */
	public static function on_group_removed( $contact_id, $group ) {
		self::group_trigger( 'removed', (int) $contact_id, $group );
	}

	/** Hook: email opened. @param array $message Message. @param array $contact Contact. */
	public static function on_email_opened( $message, $contact = array() ) {
		self::message_trigger( 'email_opened', $message, $contact );
	}

	/** Hook: link clicked. @param array $message Message. @param array $contact Contact. @param string $url URL. */
	public static function on_link_clicked( $message, $contact = array(), $url = '' ) {
		self::message_trigger( 'link_clicked', $message, $contact, array( 'url' => (string) $url ) );
	}

	/**
	 * Hook: WordPress user registered. Creates the contact only when an automation listens.
	 *
	 * @param int $user_id User id.
	 */
	public static function on_user_register( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( $user && ! empty( $user->user_email ) ) {
			$data = array(
				'email'      => $user->user_email,
				'first_name' => (string) get_user_meta( $user->ID, 'first_name', true ),
				'last_name'  => (string) get_user_meta( $user->ID, 'last_name', true ),
			);
			self::external_trigger( 'user_registered', $data, 'wordpress', array( 'user_id' => (int) $user_id ) );
		}
	}

	/**
	 * Hook: WooCommerce order completed. Creates the contact only when an automation listens.
	 *
	 * @param int $order_id Order id.
	 */
	public static function on_woo_completed( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_billing_email' ) || '' === (string) $order->get_billing_email() ) {
			return;
		}
		$data           = \QuantumCloud\WPBot\CRM\Integrations\WooCommerce::order_fields( $order );
		$data['status'] = 'customer';
		self::external_trigger( 'woo_order_completed', $data, 'woocommerce', array( 'order_id' => (int) $order_id ) );
	}

	/**
	 * Fire a trigger for an email that may not be a contact yet.
	 *
	 * @param string $trigger Trigger.
	 * @param array  $data    Contact data (email required).
	 * @param string $source  Source for a new contact.
	 * @param array  $context Trigger data.
	 */
	private static function external_trigger( $trigger, array $data, $source, array $context ) {
		$service = new ContactService();
		$contact = $service->find_by_email( (string) $data['email'] );
		if ( ! $contact ) {
			if ( ! ( new AutomationRepository() )->published_for_trigger( $trigger ) ) {
				return;
			}
			$id = $service->upsert( $data, $source );
			if ( is_wp_error( $id ) ) {
				return;
			}
			$contact = array( 'id' => (int) $id );
		}
		self::trigger( $trigger, (int) $contact['id'], $context );
	}

	/**
	 * Map a group hook to list_/tag_ trigger.
	 *
	 * @param string $what       added|removed.
	 * @param int    $contact_id Contact id.
	 * @param array  $group      Group row.
	 */
	private static function group_trigger( $what, $contact_id, $group ) {
		if ( is_array( $group ) && ! empty( $group['id'] ) ) {
			$type = 'tag' === ( isset( $group['type'] ) ? $group['type'] : '' ) ? 'tag' : 'list';
			self::trigger( $type . '_' . $what, $contact_id, array( 'group_id' => (int) $group['id'] ) );
		}
	}

	/**
	 * Trigger from a tracked message.
	 *
	 * @param string $trigger Trigger.
	 * @param mixed  $message Message row.
	 * @param mixed  $contact Contact row.
	 * @param array  $extra   Extra context.
	 */
	private static function message_trigger( $trigger, $message, $contact, array $extra = array() ) {
		$message    = is_array( $message ) ? $message : array();
		$contact_id = is_array( $contact ) && ! empty( $contact['id'] ) ? (int) $contact['id'] : ( isset( $message['contact_id'] ) ? (int) $message['contact_id'] : 0 );
		$context    = array_merge(
			array(
				'message_id'  => isset( $message['id'] ) ? (int) $message['id'] : 0,
				'campaign_id' => isset( $message['campaign_id'] ) ? (int) $message['campaign_id'] : 0,
			),
			$extra
		);
		self::trigger( $trigger, $contact_id, $context );
	}

	/**
	 * UTC time a step becomes due (now + its delay).
	 *
	 * @param array $step Step.
	 * @return string
	 */
	private static function due_at( array $step ) {
		return Dates::add( '', (int) $step['delay_value'], (string) $step['delay_unit'] );
	}

	/**
	 * First non-empty string among keys.
	 *
	 * @param array    $settings Settings.
	 * @param string[] $keys     Keys.
	 * @return string
	 */
	private static function first_string( array $settings, array $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $settings[ $key ] ) && is_scalar( $settings[ $key ] ) && '' !== trim( (string) $settings[ $key ] ) ) {
				return (string) $settings[ $key ];
			}
		}
		return '';
	}
}
