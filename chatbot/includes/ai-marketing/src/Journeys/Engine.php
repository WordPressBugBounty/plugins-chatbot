<?php
/**
 * Journeys (automations), Essentials: a trigger with an optional filter, then
 * steps run in order: wait, send email, add or remove a tag or list, end.
 *
 * - Step format is the same as the old module's automations (action_name,
 *   delay_value, delay_unit, settings), so copied automations work again
 *   once the owner switches them on.
 * - Triggers: subscribed (new subscriber or a confirmed double opt-in, so
 *   the welcome email never misses double opt-in people), list_added,
 *   tag_added, chatbot_lead, form_submitted. trigger_config filters by
 *   list_id, tag_id or channel (audit H2: the filter is stored and applied).
 * - Each person runs a journey once (run_key ''), in claimed batches, so two
 *   runners never run the same step twice. Emails go through the queue.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Journeys;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Audience\Lists;
use QuantumCloud\AiMarketing\Audience\Rules;
use QuantumCloud\AiMarketing\Sending\Queue;
use QuantumCloud\AiMarketing\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Journey engine.
 */
final class Engine {

	const TRIGGERS = array( 'subscribed', 'list_added', 'tag_added', 'chatbot_lead', 'form_submitted' );
	const ACTIONS  = array( 'wait', 'send_email', 'add_tag', 'remove_tag', 'add_to_list', 'remove_from_list', 'end' );
	const UNITS    = array( 'minutes', 'hours', 'days' );
	const BATCH    = 50;

	/**
	 * Every trigger: the core ones plus those the Pro module adds.
	 *
	 * @return string[]
	 */
	public static function triggers() {
		/**
		 * Journey triggers. The Pro module adds its own (order completed, cart abandoned, chat ...)
		 * and starts them with Engine::trigger().
		 *
		 * @param string[] $triggers Trigger ids.
		 */
		return array_values( array_unique( array_map( 'sanitize_key', (array) apply_filters( 'qcld_wpbot_marketing/journey_triggers', self::TRIGGERS ) ) ) );
	}

	/**
	 * Every step type: the core ones plus those the Pro module adds.
	 *
	 * @return string[]
	 */
	public static function actions() {
		/**
		 * Journey step types. A step type added here runs through the
		 * qcld_wpbot_marketing/journey_step filter.
		 *
		 * @param string[] $actions Step types.
		 */
		return array_values( array_unique( array_map( 'sanitize_key', (array) apply_filters( 'qcld_wpbot_marketing/journey_actions', self::ACTIONS ) ) ) );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_marketing/contact_created', array( __CLASS__, 'on_created' ), 20, 1 );
		add_action( 'qcld_wpbot_marketing/contact_confirmed', array( __CLASS__, 'on_subscribed' ), 20, 1 );
		add_action( 'qcld_wpbot_marketing/contact_status_changed', array( __CLASS__, 'on_status' ), 20, 2 );
		add_action( 'qcld_wpbot_marketing/list_added', array( __CLASS__, 'on_list_added' ), 20, 2 );
		add_action( 'qcld_wpbot_marketing/chatbot_lead', array( __CLASS__, 'on_chatbot_lead' ), 20, 2 );
		add_action( 'qcld_wpbot_marketing/form_submitted', array( __CLASS__, 'on_form' ), 20, 2 );
		add_filter( 'qcld_wpbot_marketing/journey_email', array( __CLASS__, 'email' ), 10, 2 );
		add_action( Queue::HOOK, array( __CLASS__, 'run_due' ), 5 );
	}

	/* ── Triggers. ── */

	/**
	 * New contact: "subscribed" only when they are subscribed now.
	 *
	 * @param array $contact Contact.
	 * @return void
	 */
	public static function on_created( $contact ) {
		if ( is_array( $contact ) && 'subscribed' === $contact['status'] ) {
			self::trigger( 'subscribed', (int) $contact['id'], array() );
		}
	}

	/**
	 * Confirmed double opt-in.
	 *
	 * @param array $contact Contact.
	 * @return void
	 */
	public static function on_subscribed( $contact ) {
		if ( is_array( $contact ) ) {
			self::trigger( 'subscribed', (int) $contact['id'], array() );
		}
	}

	/**
	 * Status became subscribed (for example a lead who opted in).
	 *
	 * @param array  $contact Contact after.
	 * @param string $old     Old status.
	 * @return void
	 */
	public static function on_status( $contact, $old ) {
		if ( is_array( $contact ) && 'subscribed' === $contact['status'] && 'subscribed' !== $old ) {
			self::trigger( 'subscribed', (int) $contact['id'], array() );
		}
	}

	/**
	 * Added to a list or tag.
	 *
	 * @param int $contact_id Contact.
	 * @param int $list_id    List or tag.
	 * @return void
	 */
	public static function on_list_added( $contact_id, $list_id ) {
		$list = Lists::get( (int) $list_id );
		if ( $list ) {
			self::trigger( 'tag' === $list['type'] ? 'tag_added' : 'list_added', (int) $contact_id, array( 'tag' === $list['type'] ? 'tag_id' : 'list_id' => (int) $list_id ) );
		}
	}

	/**
	 * A chatbot lead.
	 *
	 * @param int   $contact_id Contact.
	 * @param array $lead       Normalized lead.
	 * @return void
	 */
	public static function on_chatbot_lead( $contact_id, $lead ) {
		self::trigger( 'chatbot_lead', (int) $contact_id, array( 'channel' => isset( $lead['channel'] ) ? (string) $lead['channel'] : '' ) );
	}

	/**
	 * A sign-up or form submission.
	 *
	 * @param int   $contact_id Contact.
	 * @param int[] $groups     Lists of the form.
	 * @return void
	 */
	public static function on_form( $contact_id, $groups ) {
		self::trigger( 'form_submitted', (int) $contact_id, array( 'list_ids' => array_map( 'intval', (array) $groups ) ) );
	}

	/**
	 * Whether a journey's filter accepts an event (pure; unit tested).
	 *
	 * @param array $config Journey trigger_config: list_id, tag_id, channel.
	 * @param array $event  list_id, tag_id, channel, list_ids.
	 * @return bool
	 */
	public static function matches( array $config, array $event ) {
		foreach ( array( 'list_id', 'tag_id' ) as $key ) {
			if ( ! empty( $config[ $key ] ) ) {
				$have = isset( $event[ $key ] ) ? array( (int) $event[ $key ] ) : ( isset( $event['list_ids'] ) ? (array) $event['list_ids'] : array() );
				if ( ! in_array( (int) $config[ $key ], array_map( 'intval', $have ), true ) ) {
					return false;
				}
			}
		}
		if ( ! empty( $config['channel'] ) && ( ! isset( $event['channel'] ) || $config['channel'] !== $event['channel'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * The run key of an entry (pure; unit tested): the event's own key (a cart, an
	 * order), else a period number when the journey allows re-entry, else '' (once).
	 *
	 * @param array $settings Journey settings: reentry_days.
	 * @param array $event    Event: run_key.
	 * @param int   $now      Unix time.
	 * @return string
	 */
	public static function run_key( array $settings, array $event, $now ) {
		if ( ! empty( $event['run_key'] ) ) {
			return substr( preg_replace( '/[^A-Za-z0-9_\-:.]/', '', (string) $event['run_key'] ), 0, 64 );
		}
		$days = isset( $settings['reentry_days'] ) ? (int) $settings['reentry_days'] : 0;
		return $days > 0 ? 'p' . (int) floor( (int) $now / ( $days * DAY_IN_SECONDS ) ) : '';
	}

	/**
	 * Enroll a contact in every active journey with this trigger whose filter matches.
	 *
	 * @param string $trigger    Trigger.
	 * @param int    $contact_id Contact.
	 * @param array  $event      Event data for filters.
	 * @return int Journeys entered.
	 */
	public static function trigger( $trigger, $contact_id, array $event ) {
		global $wpdb;
		$journeys = Schema::table( 'journeys' );
		$runs     = Schema::table( 'journey_runs' );
		$legacy   = 'subscribed' === $trigger ? 'subscriber_created' : $trigger; // Old trigger name of copied automations.
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, trigger_config, settings FROM `{$journeys}` WHERE status = 'active' AND trigger_type IN (%s, %s)", $trigger, $legacy ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$entered  = 0;
		$now      = current_time( 'mysql', true );
		foreach ( $rows as $row ) {
			$config   = json_decode( (string) $row['trigger_config'], true );
			$config   = is_array( $config ) ? $config : array();
			$settings = json_decode( (string) $row['settings'], true );
			$settings = is_array( $settings ) ? $settings : array();
			/**
			 * Whether a journey's trigger filter accepts an event (the Pro module checks its own filters).
			 *
			 * @param bool   $matches Core result.
			 * @param array  $config  Trigger config.
			 * @param array  $event   Event.
			 * @param string $trigger Trigger.
			 */
			if ( ! apply_filters( 'qcld_wpbot_marketing/journey_matches', self::matches( $config, $event ), $config, $event, $trigger ) ) {
				continue;
			}
			$key   = self::run_key( $settings, $event, time() );
			$added = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$runs}` (journey_id, contact_id, run_key, step_index, status, next_run_at, entered_at, updated_at) VALUES (%d, %d, %s, 0, 'waiting', %s, %s, %s)", (int) $row['id'], (int) $contact_id, $key, $now, $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			if ( $added ) {
				$wpdb->query( $wpdb->prepare( "UPDATE `{$journeys}` SET enrolled = enrolled + 1 WHERE id = %d", (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
				++$entered;
			}
		}
		if ( $entered ) {
			Queue::ensure_scheduled();
			self::run_due();
		}
		return $entered;
	}

	/* ── Running. ── */

	/**
	 * Run due steps in claimed batches.
	 *
	 * @return int Runs advanced.
	 */
	public static function run_due() {
		global $wpdb;
		if ( Queue::automation_paused() ) {
			return 0;
		}
		$runs     = Schema::table( 'journey_runs' );
		$journeys = Schema::table( 'journeys' );
		$token    = wp_generate_password( 32, false, false );
		$now      = current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare( "UPDATE `{$runs}` SET claim = %s WHERE status = 'waiting' AND claim IS NULL AND next_run_at <= %s AND journey_id IN ( SELECT id FROM `{$journeys}` WHERE status = 'active' ) ORDER BY id ASC LIMIT %d", $token, $now, self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables; atomic claim.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$runs}` WHERE claim = %s", $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		foreach ( $rows as $run ) {
			self::advance( $run );
		}
		return count( $rows );
	}

	/**
	 * Run a contact's steps until a wait or the end.
	 *
	 * @param array $run Run row (claimed).
	 * @return void
	 */
	private static function advance( array $run ) {
		global $wpdb;
		$runs    = Schema::table( 'journey_runs' );
		$journey = self::get( (int) $run['journey_id'] );
		$contact = Contacts::get( (int) $run['contact_id'] );
		$index   = (int) $run['step_index'];
		$status  = 'waiting';
		$next    = null;
		if ( ! $journey || ! $contact ) {
			$status = 'done';
		} else {
			$steps = $journey['steps'];
			for ( $guard = 0; $guard < 25; $guard++ ) {
				if ( ! isset( $steps[ $index ] ) ) {
					$status = 'done';
					break;
				}
				/**
				 * Whether a contact leaves a journey now (goal reached, exit condition).
				 *
				 * @param bool  $exit    Leave.
				 * @param array $journey Journey.
				 * @param array $contact Contact.
				 * @param array $run     Run.
				 */
				if ( apply_filters( 'qcld_wpbot_marketing/journey_exit', false, $journey, $contact, $run ) ) {
					$status = 'exited';
					break;
				}
				$step = $steps[ $index ];
				++$index;
				if ( ! in_array( $step['action_name'], self::ACTIONS, true ) ) {
					/**
					 * Run a step type the core does not know (the Pro module's branch, wait-until, webhook ...).
					 * Return null to skip the step, or an array: goto (step id to continue at, 0 for the end),
					 * wait_until (Unix time to continue at), end (bool).
					 *
					 * @param null|array $result  Result.
					 * @param array      $step    Step.
					 * @param array      $journey Journey.
					 * @param array      $contact Contact.
					 * @param array      $run     Run (entered_at ...).
					 */
					$result = apply_filters( 'qcld_wpbot_marketing/journey_step', null, $step, $journey, $contact, $run );
					if ( ! is_array( $result ) ) {
						continue;
					}
					if ( ! empty( $result['end'] ) ) {
						$status = 'done';
						break;
					}
					if ( isset( $result['goto'] ) ) {
						$target = self::index_of( $steps, (int) $result['goto'] );
						if ( null === $target ) {
							$status = 'done';
							break;
						}
						$index = $target;
					}
					if ( ! empty( $result['wait_until'] ) ) {
						$next = gmdate( 'Y-m-d H:i:s', max( time(), (int) $result['wait_until'] ) );
						break;
					}
					continue;
				}
				if ( 'wait' === $step['action_name'] ) {
					$next = gmdate( 'Y-m-d H:i:s', time() + self::seconds( $step ) );
					break;
				}
				if ( 'end' === $step['action_name'] ) {
					$status = 'done';
					break;
				}
				if ( 'send_email' === $step['action_name'] ) {
					$allowed = Queue::may_send(
						$contact,
						array(
							'kind'       => 'journey',
							'journey_id' => (int) $journey['id'],
							'step_key'   => (string) $step['id'],
							'entered_at' => isset( $run['entered_at'] ) ? (string) $run['entered_at'] : '',
						)
					);
					if ( is_wp_error( $allowed ) ) {
						$data = $allowed->get_error_data();
						if ( is_array( $data ) && ! empty( $data['retry_at'] ) ) {
							// A guardrail (e.g. the weekly cap) holds this email: try the same step later.
							--$index;
							$next = gmdate( 'Y-m-d H:i:s', (int) $data['retry_at'] );
							break;
						}
						continue;
					}
				}
				self::act( $step, $journey, $contact );
			}
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$runs,
			array(
				'step_index'  => $index,
				'status'      => $status,
				'next_run_at' => $next ? $next : current_time( 'mysql', true ),
				'claim'       => null,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array(
				'id'    => (int) $run['id'],
				'claim' => (string) $run['claim'],
			)
		);
		if ( in_array( $status, array( 'done', 'exited' ), true ) && $journey ) {
			$journeys = Schema::table( 'journeys' );
			$wpdb->query( $wpdb->prepare( "UPDATE `{$journeys}` SET completed = completed + 1 WHERE id = %d", (int) $journey['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
	}

	/**
	 * Position of a step by its id (pure).
	 *
	 * @param array[] $steps Steps.
	 * @param int     $id    Step id (0: none).
	 * @return int|null
	 */
	public static function index_of( array $steps, $id ) {
		foreach ( array_values( $steps ) as $index => $step ) {
			if ( (int) $id > 0 && (int) $step['id'] === (int) $id ) {
				return $index;
			}
		}
		return null;
	}

	/**
	 * Do one step.
	 *
	 * @param array $step    Step.
	 * @param array $journey Journey.
	 * @param array $contact Contact.
	 * @return void
	 */
	private static function act( array $step, array $journey, array $contact ) {
		$settings = isset( $step['settings'] ) && is_array( $step['settings'] ) ? $step['settings'] : array();
		/* translators: %s: journey name. */
		$reason = sprintf( __( 'Journey "%s"', 'chatbot' ), $journey['name'] );
		switch ( $step['action_name'] ) {
			case 'send_email':
				if ( Rules::mailable( (string) $contact['status'] ) ) {
					Queue::enqueue_journey( (int) $journey['id'], (string) $step['id'], (int) $contact['id'] );
				}
				break;
			case 'add_tag':
			case 'add_to_list':
				if ( ! empty( $settings['list_id'] ) ) {
					Lists::add_member( (int) $settings['list_id'], (int) $contact['id'], $reason, 'journey' );
				}
				break;
			case 'remove_tag':
			case 'remove_from_list':
				if ( ! empty( $settings['list_id'] ) ) {
					Lists::remove_member( (int) $settings['list_id'], (int) $contact['id'] );
				}
				break;
		}
	}

	/**
	 * The email of a journey step, for the queue (filter qcld_wpbot_marketing/journey_email).
	 *
	 * @param mixed $email Email so far.
	 * @param array $row   Message row.
	 * @return array|null
	 */
	public static function email( $email, $row ) {
		if ( is_array( $email ) || empty( $row['journey_id'] ) ) {
			return $email;
		}
		$journey = self::get( (int) $row['journey_id'] );
		if ( ! $journey ) {
			return null;
		}
		foreach ( $journey['steps'] as $step ) {
			if ( (string) $step['id'] === (string) $row['step_key'] && 'send_email' === $step['action_name'] ) {
				$settings = (array) $step['settings'];
				return array(
					'subject'      => isset( $settings['subject'] ) ? (string) $settings['subject'] : ( isset( $settings['email_subject'] ) ? (string) $settings['email_subject'] : '' ),
					'preview_text' => isset( $settings['preview_text'] ) ? (string) $settings['preview_text'] : '',
					'content_html' => isset( $settings['body'] ) ? (string) $settings['body'] : ( isset( $settings['email_body'] ) ? (string) $settings['email_body'] : '' ),
					'journey_id'   => (int) $journey['id'],
					'step_key'     => (string) $step['id'],
					'extra'        => $settings,
				);
			}
		}
		return null;
	}

	/* ── Journeys (CRUD). ── */

	/**
	 * All journeys.
	 *
	 * @return array[]
	 */
	public static function all() {
		global $wpdb;
		$table = Schema::table( 'journeys' );
		return array_map( array( __CLASS__, 'shape' ), (array) $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id DESC", ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * One journey.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( 'journeys' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Create or update.
	 *
	 * @param int   $id   Id (0 to create).
	 * @param array $data name, trigger_type, trigger_config, steps, status.
	 * @return array|WP_Error
	 */
	public static function save( $id, array $data ) {
		global $wpdb;
		$table = Schema::table( 'journeys' );
		$row   = array( 'updated_at' => current_time( 'mysql', true ) );
		if ( isset( $data['name'] ) ) {
			$row['name'] = substr( sanitize_text_field( (string) $data['name'] ), 0, 190 );
		}
		if ( isset( $data['trigger_type'] ) ) {
			if ( ! in_array( $data['trigger_type'], self::triggers(), true ) ) {
				return new WP_Error( 'qcld_mk_trigger', __( 'Choose what starts the journey.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$row['trigger_type'] = $data['trigger_type'];
		}
		if ( isset( $data['trigger_config'] ) && is_array( $data['trigger_config'] ) ) {
			$row['trigger_config'] = wp_json_encode( self::clean_config( $data['trigger_config'] ) );
		}
		if ( isset( $data['steps'] ) && is_array( $data['steps'] ) ) {
			$row['steps'] = wp_json_encode( self::clean_steps( $data['steps'] ) );
		}
		if ( isset( $data['status'] ) && in_array( $data['status'], array( 'draft', 'active', 'paused' ), true ) ) {
			$row['status'] = $data['status'];
		}
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$current         = $id ? self::get( $id ) : null;
			$row['settings'] = wp_json_encode( array_merge( $current ? $current['settings'] : array(), self::clean_settings( $data['settings'] ) ) );
		}
		if ( isset( $data['origin'] ) ) {
			$row['origin'] = substr( sanitize_key( (string) $data['origin'] ), 0, 20 );
		}
		if ( $id ) {
			if ( ! self::get( $id ) ) {
				return new WP_Error( 'qcld_mk_not_found', __( 'Journey not found.', 'chatbot' ), array( 'status' => 404 ) );
			}
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		} else {
			$row = array_merge(
				array(
					'name'         => __( 'New journey', 'chatbot' ),
					'trigger_type' => 'subscribed',
					'status'       => 'draft',
					'steps'        => '[]',
				),
				$row,
				array( 'created_at' => current_time( 'mysql', true ) )
			);
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$id = (int) $wpdb->insert_id;
		}
		return self::get( $id );
	}

	/**
	 * Delete a journey and its runs.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( Schema::table( 'journey_runs' ), array( 'journey_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return (bool) $wpdb->delete( Schema::table( 'journeys' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Clean a trigger filter (pure).
	 *
	 * @param array $config list_id, tag_id, channel.
	 * @return array
	 */
	public static function clean_config( array $config ) {
		$out = array(
			'list_id' => isset( $config['list_id'] ) ? (int) $config['list_id'] : 0,
			'tag_id'  => isset( $config['tag_id'] ) ? (int) $config['tag_id'] : 0,
			'channel' => isset( $config['channel'] ) ? sanitize_key( (string) $config['channel'] ) : '',
		);
		// Filters of triggers added by the Pro module (scalar values only).
		foreach ( $config as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' !== $key && ! isset( $out[ $key ] ) && is_scalar( $value ) ) {
				$out[ $key ] = is_numeric( $value ) ? $value + 0 : sanitize_text_field( (string) $value );
			}
		}
		return array_filter( $out );
	}

	/**
	 * Clean journey settings (pure; unit tested): reentry_days, goal and other scalars.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function clean_settings( array $settings ) {
		$out = array();
		foreach ( $settings as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || ! is_scalar( $value ) || 'migrated_status' === $key ) {
				continue;
			}
			$out[ $key ] = 'reentry_days' === $key ? max( 0, min( 3650, (int) $value ) ) : ( is_bool( $value ) ? $value : sanitize_text_field( (string) $value ) );
		}
		return $out;
	}

	/**
	 * Clean steps; keep ids, give new steps the next id (pure).
	 *
	 * @param array $steps Steps.
	 * @return array[]
	 */
	public static function clean_steps( array $steps ) {
		$out = array();
		$max = 0;
		foreach ( $steps as $step ) {
			$max = max( $max, isset( $step['id'] ) ? (int) $step['id'] : 0 );
		}
		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) || ! isset( $step['action_name'] ) || ! in_array( $step['action_name'], self::actions(), true ) ) {
				continue;
			}
			$settings = isset( $step['settings'] ) && is_array( $step['settings'] ) ? $step['settings'] : array();
			$clean    = array();
			foreach ( $settings as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( in_array( $key, array( 'body', 'email_body' ), true ) ) {
					$clean[ $key ] = wp_kses_post( (string) $value );
				} elseif ( in_array( $key, array( 'list_id', 'then_step', 'else_step', 'hour' ), true ) ) {
					$clean[ $key ] = (int) $value;
				} elseif ( is_scalar( $value ) ) {
					$clean[ $key ] = sanitize_text_field( (string) $value );
				}
			}
			$unit  = isset( $step['delay_unit'] ) && in_array( $step['delay_unit'], self::UNITS, true ) ? $step['delay_unit'] : 'days';
			$out[] = array(
				'id'          => ! empty( $step['id'] ) ? (int) $step['id'] : ++$max,
				'action_name' => $step['action_name'],
				'delay_value' => isset( $step['delay_value'] ) ? max( 0, (int) $step['delay_value'] ) : 0,
				'delay_unit'  => $unit,
				'settings'    => $clean,
			);
		}
		return $out;
	}

	/**
	 * Seconds of a wait step (pure).
	 *
	 * @param array $step Step.
	 * @return int
	 */
	public static function seconds( array $step ) {
		$value = isset( $step['delay_value'] ) ? max( 0, (int) $step['delay_value'] ) : 0;
		$unit  = isset( $step['delay_unit'] ) ? $step['delay_unit'] : 'days';
		return $value * ( 'minutes' === $unit ? MINUTE_IN_SECONDS : ( 'hours' === $unit ? HOUR_IN_SECONDS : DAY_IN_SECONDS ) );
	}

	/**
	 * Journey as returned to the app.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$config   = json_decode( (string) $row['trigger_config'], true );
		$steps    = json_decode( (string) $row['steps'], true );
		$settings = json_decode( (string) $row['settings'], true );
		$trigger  = (string) $row['trigger_type'];
		if ( 'subscriber_created' === $trigger ) {
			$trigger = 'subscribed'; // Old name, from automations copied from the old module.
		}
		return array(
			'id'             => (int) $row['id'],
			'name'           => (string) $row['name'],
			'status'         => (string) $row['status'],
			'trigger_type'   => $trigger,
			'trigger_config' => is_array( $config ) ? $config : array(),
			'steps'          => self::clean_steps( is_array( $steps ) ? $steps : array() ),
			'origin'         => (string) $row['origin'],
			'migrated'       => is_array( $settings ) && isset( $settings['migrated_status'] ),
			'settings'       => is_array( $settings ) ? $settings : array(),
			'enrolled'       => (int) $row['enrolled'],
			'completed'      => (int) $row['completed'],
		);
	}
}
