<?php
/**
 * Bulk contact import (CSV, WordPress users, WooCommerce customers).
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Integrations\WooCommerce;
use QuantumCloud\WPBot\CRM\Integrations\WordPressUsers;
use QuantumCloud\WPBot\CRM\Support\Csv;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Streams rows from a source, filters them and writes them in batches.
 *
 * New contacts are written with multi-row INSERTs (contacts, memberships, `subscribe` events)
 * so large files finish quickly; per-contact hooks are NOT fired for them (one
 * `wpbot_crm/contacts_imported( int[] $ids, string $source )` per batch instead), so an import
 * never floods automations. Existing contacts go through ContactService::update() when
 * `update_existing` is on.
 */
class ContactImporter {

	const BATCH = 500;

	/**
	 * Role-based local parts (skip_role).
	 *
	 * @var string[]
	 */
	const ROLE_PREFIXES = array( 'info', 'support', 'admin', 'sales', 'contact', 'noreply', 'no-reply', 'webmaster', 'postmaster', 'hello', 'office' );

	/**
	 * Placeholder / test domains (skip_test).
	 *
	 * @var string[]
	 */
	const TEST_DOMAINS = array( 'example.com', 'example.org', 'example.net', 'test.com', 'test.org', 'test.net', 'domain.com', 'yourdomain.com', 'yoursite.com', 'yourcompany.com', 'email.test', 'localhost', 'localhost.localdomain' );

	/**
	 * Disposable inbox domains (skip_disposable). Hand-picked, intentionally short.
	 *
	 * @var string[]
	 */
	const DISPOSABLE = array(
		'mailinator.com', 'mailinator.net', 'mailinator.org', 'mailin8r.com', 'notmailinator.com', 'binkmail.com', 'bobmail.info', 'chammy.info',
		'devnullmail.com', 'letthemeatspam.com', 'reallymymail.com', 'sogetthis.com', 'spamherelots.com', 'thisisnotmyrealemail.com', 'tradermail.info',
		'veryrealemail.com', 'zippymail.info', 'suremail.info', 'safetymail.info', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
		'guerrillamail.biz', 'guerrillamail.de', 'guerrillamail.info', 'guerrillamailblock.com', 'sharklasers.com', 'grr.la', 'pokemail.net', 'spam4.me',
		'10minutemail.com', '10minutemail.net', '10minemail.com', '20minutemail.com', 'temp-mail.org', 'temp-mail.io', 'tempmail.com', 'tempmail.net',
		'tempmailo.com', 'tempail.com', 'tempr.email', 'tempinbox.com', 'temporaryemail.net', 'temporaryinbox.com', 'throwawaymail.com', 'yopmail.com',
		'yopmail.net', 'yopmail.fr', 'cool.fr.nf', 'jetable.org', 'trashmail.com', 'trashmail.net', 'trashmail.de', 'trashmail.me', 'trash-mail.com',
		'trashymail.com', 'mailmetrash.com', 'mailnesia.com', 'maildrop.cc', 'getnada.com', 'nada.email', 'dispostable.com', 'fakeinbox.com',
		'mintemail.com', 'mytemp.email', 'mohmal.com', 'emailondeck.com', 'getairmail.com', 'discard.email', 'discardmail.com', 'spambox.us',
		'spamgourmet.com', 'spamfree24.org', 'spamex.com', 'mailcatch.com', 'mailnull.com', 'mailexpire.com', 'mailforspam.com', 'mailpoof.com',
		'mailtemp.info', 'incognitomail.org', 'anonbox.net', 'burnermail.io', 'inboxkitten.com', 'harakirimail.com', 'moakt.com', 'tmpmail.org',
		'tmpmail.net', 'emailfake.com', 'crazymailing.com', 'wegwerfmail.de', 'wegwerfmail.net', 'byom.de', 'deadaddress.com', 'gishpuppy.com',
		'kasmail.com', 'nowmymail.com', 'armyspy.com', 'cuvox.de', 'dayrep.com', 'einrot.com', 'fleckens.hu', 'gustr.com', 'jourrapide.com',
		'rhyta.com', 'superrito.com', 'teleworm.us',
	);

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
	 * Contact service.
	 *
	 * @var ContactService
	 */
	protected $service;

	/**
	 * Normalised options of the current run.
	 *
	 * @var array
	 */
	protected $opts = array();

	/**
	 * Emails already handled in this run (lower-case => true).
	 *
	 * @var array<string,bool>
	 */
	protected $seen = array();

	/**
	 * Counters.
	 *
	 * @var array{imported:int,updated:int,skipped:int}
	 */
	protected $stats = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->contacts = new ContactRepository();
		$this->groups   = new GroupRepository();
		$this->service  = new ContactService( $this->contacts, $this->groups );
	}

	/**
	 * Import a CSV file (delimiter and header names detected automatically).
	 *
	 * @param string $path    Readable file path.
	 * @param array  $options status, update_existing, skip_test, skip_disposable, skip_role, list_ids, tag_ids.
	 * @return array|\WP_Error
	 */
	public function from_csv( $path, array $options = array() ) {
		if ( ! is_readable( $path ) ) {
			return new \WP_Error( 'wpbot_crm_no_file', __( 'The uploaded file could not be read.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$rows  = Csv::rows( $path );
		$first = $rows->current();
		if ( ! is_array( $first ) ) {
			return new \WP_Error( 'wpbot_crm_empty_file', __( 'The CSV file has no contacts in it.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! array_key_exists( 'email', $first ) ) {
			return new \WP_Error( 'wpbot_crm_no_email_column', __( 'The CSV file needs an "Email" column in its first row.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return $this->run( 'csv', $rows, $options );
	}

	/**
	 * Import every WordPress user.
	 *
	 * @param array $options See from_csv().
	 * @return array
	 */
	public function from_wp_users( array $options = array() ) {
		return $this->run( 'wordpress', WordPressUsers::rows( self::BATCH ), $options );
	}

	/**
	 * Import WooCommerce customers (customer accounts plus billing details of paid orders).
	 *
	 * @param array $options See from_csv().
	 * @return array|\WP_Error
	 */
	public function from_woocommerce( array $options = array() ) {
		if ( ! WooCommerce::active() ) {
			return new \WP_Error( 'wpbot_crm_woo_inactive', __( 'WooCommerce is not active, so there are no customers to import.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return $this->run( 'woocommerce', WooCommerce::customer_rows( self::BATCH ), $options );
	}

	/**
	 * Why an email would be skipped by the filters ('' = keep).
	 *
	 * @param string $email   Lower-case email.
	 * @param array  $options Normalised options (skip_test, skip_disposable, skip_role).
	 * @return string invalid|test|disposable|role|''
	 */
	public static function skip_reason( $email, array $options ) {
		if ( '' === $email || ! is_email( $email ) || strlen( $email ) > 190 ) {
			return 'invalid';
		}
		list( $local, $domain ) = explode( '@', $email, 2 );
		if ( ! empty( $options['skip_test'] ) && self::is_test( $local, $domain ) ) {
			return 'test';
		}
		if ( ! empty( $options['skip_disposable'] ) && in_array( $domain, self::DISPOSABLE, true ) ) {
			return 'disposable';
		}
		if ( ! empty( $options['skip_role'] ) && in_array( $local, self::ROLE_PREFIXES, true ) ) {
			return 'role';
		}
		return '';
	}

	/**
	 * Whether an address looks like a test/placeholder address.
	 *
	 * @param string $local  Local part.
	 * @param string $domain Domain.
	 * @return bool
	 */
	protected static function is_test( $local, $domain ) {
		if ( in_array( $domain, self::TEST_DOMAINS, true ) || preg_match( '/\.(test|example|invalid|localhost|local)$/', $domain ) ) {
			return true;
		}
		return (bool) preg_match( '/^(test(ing|er)?|fake|dummy|sample|placeholder|asdf+|qwerty|foo(bar)?|nomail|no-?email|none|null)[._-]?\d*$/', $local );
	}

	/**
	 * Run an import over any row iterator.
	 *
	 * @param string   $source  Source label stored on new contacts.
	 * @param iterable $rows    Rows keyed by contact field.
	 * @param array    $options Raw options.
	 * @return array{success:bool,imported:int,updated:int,skipped:int,message:string}
	 */
	protected function run( $source, $rows, array $options ) {
		self::extend_time();
		$this->opts = array(
			'source'          => $source,
			'status'          => ContactService::normalize_status( isset( $options['status'] ) ? $options['status'] : '', 'subscribed' ),
			'update_existing' => ! empty( $options['update_existing'] ),
			'skip_test'       => ! isset( $options['skip_test'] ) || ! empty( $options['skip_test'] ),
			'skip_disposable' => ! empty( $options['skip_disposable'] ),
			'skip_role'       => ! empty( $options['skip_role'] ),
			'group_ids'       => array_merge(
				$this->groups->existing_ids( isset( $options['list_ids'] ) ? (array) $options['list_ids'] : array(), 'list' ),
				$this->groups->existing_ids( isset( $options['tag_ids'] ) ? (array) $options['tag_ids'] : array(), 'tag' )
			),
		);
		$this->seen  = array();
		$this->stats = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
		);

		$batch = array();
		foreach ( $rows as $row ) {
			$batch[] = $row;
			if ( count( $batch ) >= self::BATCH ) {
				$this->process_batch( $batch );
				$batch = array();
			}
		}
		if ( $batch ) {
			$this->process_batch( $batch );
		}

		return array(
			'success'  => true,
			'imported' => $this->stats['imported'],
			'updated'  => $this->stats['updated'],
			'skipped'  => $this->stats['skipped'],
			/* translators: 1: imported, 2: updated, 3: skipped. */
			'message'  => sprintf( __( '%1$d contacts imported, %2$d updated, %3$d skipped.', 'chatbot' ), $this->stats['imported'], $this->stats['updated'], $this->stats['skipped'] ),
		);
	}

	/**
	 * Filter, de-duplicate and write one batch.
	 *
	 * @param array[] $batch Raw rows.
	 */
	protected function process_batch( array $batch ) {
		$valid = array();
		foreach ( $batch as $row ) {
			$row   = is_array( $row ) ? $row : array();
			$email = isset( $row['email'] ) ? strtolower( trim( sanitize_email( (string) $row['email'] ) ) ) : '';
			if ( isset( $this->seen[ $email ] ) || '' !== self::skip_reason( $email, $this->opts ) ) {
				++$this->stats['skipped'];
				continue;
			}
			$this->seen[ $email ] = true;
			$row['email']         = $email;
			$valid[ $email ]      = $row;
		}
		if ( empty( $valid ) ) {
			return;
		}

		$existing = array_flip( $this->contacts->existing_emails( array_map( 'strval', array_keys( $valid ) ) ) );
		$new      = array_diff_key( $valid, $existing );
		$old      = array_intersect_key( $valid, $existing );

		if ( $new ) {
			$this->insert_new( $new );
		}
		foreach ( $old as $email => $row ) {
			if ( ! $this->opts['update_existing'] ) {
				++$this->stats['skipped'];
				continue;
			}
			$this->update_one( (string) $email, $row );
		}
	}

	/**
	 * Bulk-insert new contacts, their memberships and `subscribe` events.
	 *
	 * @param array<string,array> $rows email => row.
	 */
	protected function insert_new( array $rows ) {
		global $wpdb;
		$now          = Dates::now();
		$values       = array();
		$placeholders = array();
		foreach ( $rows as $row ) {
			$clean     = $this->service->sanitize( $row );
			$status    = ContactService::normalize_status( isset( $row['status'] ) ? $row['status'] : '', $this->opts['status'] );
			$unsub     = 'unsubscribed' === $status;
			$confirmed = in_array( $status, array( 'subscribed', 'customer' ), true );

			$placeholders[] = '(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,' . ( $unsub ? '%s' : 'NULL' ) . ',' . ( $confirmed ? '%s' : 'NULL' ) . ',%s,%s,%s)';
			array_push(
				$values,
				$clean['email'],
				isset( $clean['first_name'] ) ? $clean['first_name'] : '',
				isset( $clean['last_name'] ) ? $clean['last_name'] : '',
				isset( $clean['phone'] ) ? $clean['phone'] : '',
				isset( $clean['company'] ) ? $clean['company'] : '',
				isset( $clean['website'] ) ? $clean['website'] : '',
				$status,
				$this->opts['source'],
				wp_json_encode( (object) $this->attributes( $row ) ),
				bin2hex( random_bytes( 16 ) )
			);
			if ( $unsub ) {
				$values[] = $now;
			}
			if ( $confirmed ) {
				$values[] = $now;
			}
			array_push( $values, $now, $now, $now );
		}

		$table = Schema::table( 'contacts' );
		$sql   = "INSERT IGNORE INTO {$table} (email, first_name, last_name, phone, company, website, status, source, attributes, token, unsubscribed_at, confirmed_at, last_activity_at, created_at, updated_at) VALUES " . implode( ',', $placeholders );
		$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- bulk write, placeholders built above.

		$emails = array_map( 'strval', array_keys( $rows ) );
		$in     = implode( ',', array_fill( 0, count( $emails ), '%s' ) );
		$ids    = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE created_at = %s AND source = %s AND email IN ({$in})", array_merge( array( $now, $this->opts['source'] ), $emails ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery

		$this->stats['imported'] += count( $ids );
		$this->stats['skipped']  += count( $rows ) - count( $ids );
		if ( empty( $ids ) ) {
			return;
		}

		$events = Schema::table( 'events' );
		$data   = wp_json_encode( array( 'source' => $this->opts['source'] ) );
		$tuples = array();
		foreach ( $ids as $id ) {
			$tuples[] = $wpdb->prepare( "(%d,'subscribe',%s,%s)", $id, $data, $now );
		}
		$wpdb->query( "INSERT INTO {$events} (contact_id, type, data, created_at) VALUES " . implode( ',', $tuples ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- every tuple prepared.

		$this->add_memberships( $ids );

		/**
		 * Fires after a batch of contacts was bulk-imported (per-contact hooks are not fired).
		 *
		 * @param int[]  $ids    New contact ids.
		 * @param string $source csv|wordpress|woocommerce.
		 */
		do_action( 'wpbot_crm/contacts_imported', $ids, $this->opts['source'] );
	}

	/**
	 * Update one existing contact (non-empty fields only; status only when the row has one).
	 *
	 * @param string $email Email.
	 * @param array  $row   Row.
	 */
	protected function update_one( $email, array $row ) {
		$contact = $this->contacts->find_by_email( $email );
		if ( ! $contact ) {
			++$this->stats['skipped'];
			return;
		}
		$data = array();
		foreach ( array( 'first_name', 'last_name', 'phone', 'company', 'website' ) as $field ) {
			if ( isset( $row[ $field ] ) && '' !== trim( (string) $row[ $field ] ) ) {
				$data[ $field ] = $row[ $field ];
			}
		}
		$status = ContactService::normalize_status( isset( $row['status'] ) ? $row['status'] : '' );
		if ( '' !== $status ) {
			$data['status'] = $status;
		}
		$attrs = $this->attributes( $row );
		if ( $attrs ) {
			$data['attributes'] = $attrs;
		}
		$result = $data ? $this->service->update( $contact['id'], $data ) : $contact;
		if ( is_wp_error( $result ) ) {
			++$this->stats['skipped'];
			return;
		}
		$this->add_memberships( array( (int) $contact['id'] ) );
		++$this->stats['updated'];
	}

	/**
	 * Add the selected lists/tags to contacts (INSERT IGNORE, 1,000 rows per query).
	 *
	 * @param int[] $contact_ids Contact ids.
	 */
	protected function add_memberships( array $contact_ids ) {
		global $wpdb;
		if ( empty( $this->opts['group_ids'] ) || empty( $contact_ids ) ) {
			return;
		}
		$now    = esc_sql( Dates::now() );
		$tuples = array();
		foreach ( $this->opts['group_ids'] as $group_id ) {
			foreach ( $contact_ids as $contact_id ) {
				$tuples[] = sprintf( "(%d,%d,'%s')", (int) $group_id, (int) $contact_id, $now );
			}
		}
		$members = Schema::table( 'group_members' );
		foreach ( array_chunk( $tuples, 1000 ) as $chunk ) {
			$wpdb->query( "INSERT IGNORE INTO {$members} (group_id, contact_id, added_at) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- ints + escaped date.
		}
	}

	/**
	 * Extra (non-contact) columns of a row as custom attributes (max 30, 500 chars each).
	 *
	 * @param array $row Row.
	 * @return array
	 */
	protected function attributes( array $row ) {
		$known = array( 'email', 'first_name', 'last_name', 'phone', 'company', 'website', 'status' );
		$out   = array();
		foreach ( $row as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || in_array( $key, $known, true ) || ! is_scalar( $value ) || '' === trim( (string) $value ) || count( $out ) >= 30 ) {
				continue;
			}
			$out[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 500 );
		}
		return $out;
	}

	/**
	 * Lift the time and memory limits for long imports where the host allows it.
	 */
	protected static function extend_time() {
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		if ( function_exists( 'set_time_limit' ) && ! in_array( 'set_time_limit', $disabled, true ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- large imports.
		}
		wp_raise_memory_limit( 'admin' );
	}
}
