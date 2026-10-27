<?php
/**
 * Contact business rules.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Support\Dates;
use QuantumCloud\WPBot\CRM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates/updates contacts, manages statuses and group membership, fires public hooks.
 *
 * Hooks fired:
 * - do_action( 'wpbot_crm/contact_created', array $contact )
 * - do_action( 'wpbot_crm/contact_updated', array $contact, array $old_contact )
 * - do_action( 'wpbot_crm/contact_status_changed', array $contact, string $old_status, string $new_status )
 * - do_action( 'wpbot_crm/group_added', int $contact_id, array $group )   // $group = groups row (id, type list|tag, name, slug, …)
 * - do_action( 'wpbot_crm/group_removed', int $contact_id, array $group )
 * - do_action( 'wpbot_crm/send_confirmation', array $contact )            // double opt-in email (Mail layer hooks it)
 */
class ContactService {

	const STATUSES = array( 'subscribed', 'unsubscribed', 'pending', 'bounced', 'complained', 'customer' );

	/**
	 * Contacts repository.
	 *
	 * @var ContactRepository
	 */
	protected $contacts;

	/**
	 * Groups repository.
	 *
	 * @var GroupRepository
	 */
	protected $groups;

	/**
	 * Events repository.
	 *
	 * @var EventRepository
	 */
	protected $events;

	/**
	 * Constructor.
	 *
	 * @param ContactRepository|null $contacts Contacts repository.
	 * @param GroupRepository|null   $groups   Groups repository.
	 * @param EventRepository|null   $events   Events repository.
	 */
	public function __construct( ContactRepository $contacts = null, GroupRepository $groups = null, EventRepository $events = null ) {
		$this->contacts = $contacts ? $contacts : new ContactRepository();
		$this->groups   = $groups ? $groups : new GroupRepository();
		$this->events   = $events ? $events : new EventRepository();
	}

	/**
	 * Normalise a status; `unconfirmed` → `pending`. Unknown values → $fallback.
	 *
	 * @param string $status   Raw status.
	 * @param string $fallback Value for unknown input ('' lets callers detect invalid input).
	 * @return string
	 */
	public static function normalize_status( $status, $fallback = '' ) {
		$status = strtolower( trim( (string) $status ) );
		if ( 'unconfirmed' === $status ) {
			$status = 'pending';
		}
		return in_array( $status, self::STATUSES, true ) ? $status : $fallback;
	}

	/**
	 * Random 32-char hex token, unique among contacts.
	 *
	 * @return string
	 */
	public function generate_token() {
		do {
			try {
				$token = bin2hex( random_bytes( 16 ) );
			} catch ( \Exception $e ) {
				$token = md5( wp_generate_password( 32, true, true ) . microtime() );
			}
		} while ( $this->contacts->find_by_token( $token ) );
		return $token;
	}

	/**
	 * A contact by id.
	 *
	 * @param int $id Contact id.
	 * @return array|null
	 */
	public function find( $id ) {
		return $this->contacts->find( $id );
	}

	/**
	 * A contact by email.
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public function find_by_email( $email ) {
		return $this->contacts->find_by_email( $email );
	}

	/**
	 * Create a contact (admin "Add Contact"). Rejects existing emails.
	 *
	 * @param array  $data   email (required), first_name, last_name, phone, company, website, status, attributes, list_ids, tag_ids.
	 * @param string $source Source label.
	 * @return int|\WP_Error New contact id.
	 */
	public function create( array $data, $source = 'manual' ) {
		$clean = $this->sanitize( $data );
		$error = $this->validate_email( $clean );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		if ( $this->contacts->find_by_email( $clean['email'] ) ) {
			return new \WP_Error( 'wpbot_crm_contact_exists', __( 'A contact with this email already exists.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$status = isset( $data['status'] ) ? self::normalize_status( $data['status'], 'subscribed' ) : 'subscribed';

		$group_ids = array_merge(
			$this->groups->existing_ids( isset( $data['list_ids'] ) ? (array) $data['list_ids'] : array(), 'list' ),
			$this->groups->existing_ids( isset( $data['tag_ids'] ) ? (array) $data['tag_ids'] : array(), 'tag' )
		);

		return $this->insert_contact( $clean, $status, $source, $group_ids );
	}

	/**
	 * Create or merge a contact from any capture source (chatbot, forms, imports, leads).
	 *
	 * New contacts: status from $data['status'] or `subscribed`; public sources become `pending`
	 * when double opt-in is enabled (and a confirmation is requested). Existing contacts: only
	 * empty fields are filled, the status is never changed (an unsubscribed contact stays
	 * unsubscribed), and groups are added (never removed).
	 *
	 * @param array  $data      email (required), first_name, last_name, phone, company, website, status, attributes, list_ids, tag_ids.
	 * @param string $source    Source label (e.g. chatbot, shortcode, cf7, import, leads).
	 * @param int[]  $group_ids List/tag ids to add.
	 * @return int|\WP_Error Contact id.
	 */
	public function upsert( array $data, $source, array $group_ids = array() ) {
		$clean = $this->sanitize( $data );
		$error = $this->validate_email( $clean );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		foreach ( array( 'list_ids', 'tag_ids' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$group_ids = array_merge( $group_ids, (array) $data[ $key ] );
			}
		}
		$group_ids = $this->groups->existing_ids( $group_ids );
		$source    = sanitize_key( (string) $source );

		$existing = $this->contacts->find_by_email( $clean['email'] );
		if ( ! $existing ) {
			$status = isset( $data['status'] ) ? self::normalize_status( $data['status'], 'subscribed' ) : 'subscribed';
			$doi    = Settings::bool( 'double_opt_in' ) && $this->is_public_source( $source );
			if ( $doi && 'subscribed' === $status ) {
				$status = 'pending';
			}
			$id = $this->insert_contact( $clean, $status, $source, $group_ids );
			if ( ! is_wp_error( $id ) && $doi && 'pending' === $status ) {
				$this->send_confirmation( $this->contacts->find( $id ) );
			}
			return $id;
		}

		$changes = array();
		foreach ( array( 'first_name', 'last_name', 'phone', 'company', 'website' ) as $field ) {
			if ( isset( $clean[ $field ] ) && '' !== $clean[ $field ] && '' === (string) $existing[ $field ] ) {
				$changes[ $field ] = $clean[ $field ];
			}
		}
		if ( ! empty( $clean['attributes'] ) ) {
			$merged = array_merge( $clean['attributes'], (array) $existing['attributes'] );
			if ( $merged !== $existing['attributes'] ) {
				$changes['attributes'] = $merged;
			}
		}
		$changes['last_activity_at'] = Dates::now();
		$this->contacts->update( $existing['id'], $changes );

		$added = array();
		foreach ( $group_ids as $group_id ) {
			if ( $this->groups->add_member( $group_id, $existing['id'] ) ) {
				$added[] = $group_id;
			}
		}
		if ( count( $changes ) > 1 || ! empty( $added ) ) {
			$this->fire_updated( $existing['id'], $existing );
		}
		$this->fire_groups( 'wpbot_crm/group_added', $existing['id'], $added );

		return (int) $existing['id'];
	}

	/**
	 * Update a contact (admin edit). `list_ids` / `tag_ids`, when present, are the complete
	 * desired set (replace semantics). `status` changes go through change_status().
	 *
	 * @param int   $id   Contact id.
	 * @param array $data Fields to change.
	 * @return array|\WP_Error Updated contact.
	 */
	public function update( $id, array $data ) {
		$old = $this->contacts->find( $id );
		if ( ! $old ) {
			return new \WP_Error( 'wpbot_crm_contact_not_found', __( 'Contact not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$clean = $this->sanitize( $data );

		if ( array_key_exists( 'email', $clean ) ) {
			$error = $this->validate_email( $clean );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
			$other = $this->contacts->find_by_email( $clean['email'] );
			if ( $other && (int) $other['id'] !== (int) $id ) {
				return new \WP_Error( 'wpbot_crm_contact_exists', __( 'A contact with this email already exists.', 'chatbot' ), array( 'status' => 409 ) );
			}
		}

		$new_status = null;
		if ( isset( $data['status'] ) && '' !== (string) $data['status'] ) {
			$new_status = self::normalize_status( $data['status'] );
			if ( '' === $new_status ) {
				return new \WP_Error( 'wpbot_crm_invalid_status', __( 'That contact status is not valid.', 'chatbot' ), array( 'status' => 400 ) );
			}
		}

		$fields = array_intersect_key( $clean, array_flip( array( 'email', 'first_name', 'last_name', 'phone', 'company', 'website', 'source' ) ) );
		if ( isset( $clean['attributes'] ) ) {
			$fields['attributes'] = array_merge( (array) $old['attributes'], $clean['attributes'] );
		}
		$changed = false;
		foreach ( $fields as $key => $value ) {
			if ( $value !== $old[ $key ] ) {
				$changed = true;
				break;
			}
		}
		if ( $changed ) {
			$this->contacts->update( $id, $fields );
		}

		if ( null !== $new_status && $new_status !== $old['status'] ) {
			$this->change_status( $id, $new_status );
			$changed = true;
		}

		$added   = array();
		$removed = array();
		foreach ( array(
			'list_ids' => 'list',
			'tag_ids'  => 'tag',
		) as $key => $type ) {
			if ( array_key_exists( $key, $data ) ) {
				$diff    = $this->groups->set_members_for_contact( $id, $type, Repository::ids( (array) $data[ $key ] ) );
				$added   = array_merge( $added, $diff['added'] );
				$removed = array_merge( $removed, $diff['removed'] );
			}
		}

		if ( $changed || $added || $removed ) {
			$this->fire_updated( $id, $old );
		}
		$this->fire_groups( 'wpbot_crm/group_added', $id, $added );
		$this->fire_groups( 'wpbot_crm/group_removed', $id, $removed );

		return $this->contacts->find( $id );
	}

	/**
	 * Change a contact's status. Sets confirmed_at (pending → subscribed) / unsubscribed_at,
	 * logs `unsubscribe` / `confirm` events and fires wpbot_crm/contact_status_changed.
	 *
	 * @param int    $id      Contact id.
	 * @param string $status  New status (unconfirmed accepted).
	 * @param array  $context Extra event data; `message_id` links the event to a message.
	 * @return array|\WP_Error Updated contact.
	 */
	public function change_status( $id, $status, array $context = array() ) {
		$contact = $this->contacts->find( $id );
		if ( ! $contact ) {
			return new \WP_Error( 'wpbot_crm_contact_not_found', __( 'Contact not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$new = self::normalize_status( $status );
		if ( '' === $new ) {
			return new \WP_Error( 'wpbot_crm_invalid_status', __( 'That contact status is not valid.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$old = (string) $contact['status'];
		if ( $new === $old ) {
			return $contact;
		}

		$fields = array( 'status' => $new );
		$event  = '';
		if ( 'unsubscribed' === $new ) {
			$fields['unsubscribed_at'] = Dates::now();
			$event                     = 'unsubscribe';
		} elseif ( 'pending' === $old && in_array( $new, array( 'subscribed', 'customer' ), true ) ) {
			$fields['confirmed_at'] = Dates::now();
			$event                  = 'confirm';
		}
		$this->contacts->update( $id, $fields );

		if ( '' !== $event ) {
			$message_id = isset( $context['message_id'] ) ? (int) $context['message_id'] : 0;
			unset( $context['message_id'] );
			$this->events->log( $event, $id, $context, $message_id );
		}

		$updated = $this->contacts->find( $id );
		do_action( 'wpbot_crm/contact_status_changed', $updated, $old, $new );
		return $updated;
	}

	/**
	 * Add a contact to one group (fires wpbot_crm/group_added when new).
	 *
	 * @param int $contact_id Contact id.
	 * @param int $group_id   Group id.
	 * @return bool True when newly added.
	 */
	public function add_to_group( $contact_id, $group_id ) {
		if ( ! $this->groups->find( $group_id ) || ! $this->groups->add_member( $group_id, $contact_id ) ) {
			return false;
		}
		$this->fire_groups( 'wpbot_crm/group_added', $contact_id, array( $group_id ) );
		return true;
	}

	/**
	 * Remove a contact from one group (fires wpbot_crm/group_removed when removed).
	 *
	 * @param int $contact_id Contact id.
	 * @param int $group_id   Group id.
	 * @return bool True when removed.
	 */
	public function remove_from_group( $contact_id, $group_id ) {
		$group = $this->groups->find( $group_id );
		if ( ! $group || ! $this->groups->remove_member( $group_id, $contact_id ) ) {
			return false;
		}
		do_action( 'wpbot_crm/group_removed', (int) $contact_id, $group );
		return true;
	}

	/**
	 * Ask the mail layer to send a double opt-in confirmation.
	 *
	 * @param array|null $contact Contact row.
	 */
	public function send_confirmation( $contact ) {
		if ( is_array( $contact ) ) {
			do_action( 'wpbot_crm/send_confirmation', $contact );
		}
	}

	/**
	 * Whether a source is a public sign-up (subject to double opt-in).
	 *
	 * @param string $source Source label.
	 * @return bool
	 */
	public function is_public_source( $source ) {
		$public = (array) apply_filters( 'wpbot_crm/public_sources', array( 'chatbot', 'shortcode', 'subscribe', 'form', 'cf7', 'wpforms', 'gravity', 'conversational' ) );
		return in_array( $source, $public, true );
	}

	/**
	 * Sanitise known contact fields that are present in $data.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	public function sanitize( array $data ) {
		$out = array();
		if ( isset( $data['email'] ) ) {
			$out['email'] = strtolower( trim( sanitize_email( (string) $data['email'] ) ) );
		}
		$lengths = array(
			'first_name' => 100,
			'last_name'  => 100,
			'phone'      => 50,
			'company'    => 190,
		);
		foreach ( $lengths as $field => $max ) {
			if ( isset( $data[ $field ] ) && is_scalar( $data[ $field ] ) ) {
				$out[ $field ] = self::clip( sanitize_text_field( (string) $data[ $field ] ), $max );
			}
		}
		if ( isset( $data['website'] ) && is_scalar( $data['website'] ) ) {
			$out['website'] = self::clip( esc_url_raw( (string) $data['website'] ), 255 );
		}
		if ( isset( $data['source'] ) && is_scalar( $data['source'] ) ) {
			$out['source'] = self::clip( sanitize_key( (string) $data['source'] ), 50 );
		}
		if ( isset( $data['attributes'] ) && is_array( $data['attributes'] ) ) {
			$out['attributes'] = map_deep( $data['attributes'], 'sanitize_text_field' );
		}
		return $out;
	}

	/**
	 * Insert a new contact, attach groups silently, then fire created + group_added hooks.
	 *
	 * @param array  $clean     Sanitised fields (with email).
	 * @param string $status    Status.
	 * @param string $source    Source.
	 * @param int[]  $group_ids Existing group ids.
	 * @return int|\WP_Error
	 */
	protected function insert_contact( array $clean, $status, $source, array $group_ids ) {
		$now = Dates::now();
		$row = array_merge(
			$clean,
			array(
				'status'           => $status,
				'source'           => isset( $clean['source'] ) ? $clean['source'] : self::clip( sanitize_key( (string) $source ), 50 ),
				'token'            => $this->generate_token(),
				'attributes'       => isset( $clean['attributes'] ) ? $clean['attributes'] : array(),
				'last_activity_at' => $now,
				'unsubscribed_at'  => 'unsubscribed' === $status ? $now : null,
				'confirmed_at'     => in_array( $status, array( 'subscribed', 'customer' ), true ) ? $now : null,
			)
		);
		$id = $this->contacts->insert( $row );
		if ( ! $id ) {
			return new \WP_Error( 'wpbot_crm_db_error', __( 'The contact could not be saved. Please try again.', 'chatbot' ), array( 'status' => 500 ) );
		}

		$added = array();
		foreach ( $group_ids as $group_id ) {
			if ( $this->groups->add_member( $group_id, $id ) ) {
				$added[] = (int) $group_id;
			}
		}

		$this->events->log( 'subscribe', $id, array( 'source' => $row['source'] ) );
		do_action( 'wpbot_crm/contact_created', $this->contacts->find( $id ) );
		$this->fire_groups( 'wpbot_crm/group_added', $id, $added );

		return $id;
	}

	/**
	 * Validate the email in sanitised data.
	 *
	 * @param array $clean Sanitised data.
	 * @return true|\WP_Error
	 */
	protected function validate_email( array $clean ) {
		if ( empty( $clean['email'] ) || ! is_email( $clean['email'] ) || strlen( $clean['email'] ) > 190 ) {
			return new \WP_Error( 'wpbot_crm_invalid_email', __( 'Please enter a valid email address.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Fire wpbot_crm/contact_updated with the fresh row.
	 *
	 * @param int   $id  Contact id.
	 * @param array $old Previous row.
	 */
	protected function fire_updated( $id, array $old ) {
		do_action( 'wpbot_crm/contact_updated', $this->contacts->find( $id ), $old );
	}

	/**
	 * Fire a group hook for each group id.
	 *
	 * @param string $hook       Hook name.
	 * @param int    $contact_id Contact id.
	 * @param int[]  $group_ids  Group ids.
	 */
	protected function fire_groups( $hook, $contact_id, array $group_ids ) {
		if ( empty( $group_ids ) ) {
			return;
		}
		foreach ( $this->groups->find_many( $group_ids ) as $group ) {
			do_action( $hook, (int) $contact_id, $group );
		}
	}

	/**
	 * Truncate a string to $max characters.
	 *
	 * @param string $value Value.
	 * @param int    $max   Max length.
	 * @return string
	 */
	protected static function clip( $value, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}
}
