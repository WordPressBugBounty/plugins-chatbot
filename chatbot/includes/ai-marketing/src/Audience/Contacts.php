<?php
/**
 * Contacts: find, list, create, update, delete (GDPR erasure) and capture.
 *
 * Every write goes through Rules, so consent is respected wherever a contact
 * comes from: an opt-out is never undone by an import, a merge or the owner.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Audience;

use QuantumCloud\AiMarketing\Settings;
use QuantumCloud\AiMarketing\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Contacts.
 */
final class Contacts {

	/**
	 * Columns the owner may sort by.
	 *
	 * @var string[]
	 */
	const ORDERBY = array( 'created_at', 'email', 'first_name', 'last_activity_at', 'status' );

	/**
	 * One contact by id.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		return self::row( 'id', (int) $id );
	}

	/**
	 * Email addresses of contacts (id => email), only those with one of the
	 * statuses; for audiences built elsewhere (ad audiences, exports).
	 *
	 * @param int[]    $ids      Contact ids.
	 * @param string[] $statuses Statuses to keep.
	 * @return array<int,string>
	 */
	public static function emails( array $ids, array $statuses ) {
		global $wpdb;
		$table    = Schema::table( 'contacts' );
		$statuses = array_values( array_intersect( $statuses, array( 'subscribed', 'pending', 'lead', 'prospect', 'unsubscribed', 'bounced', 'complained' ) ) );
		$out      = array();
		if ( ! $statuses ) {
			return $out;
		}
		foreach ( array_chunk( array_values( array_unique( array_map( 'intval', $ids ) ) ), 1000 ) as $chunk ) {
			$in_ids  = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$in_stat = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT id, email FROM `{$table}` WHERE id IN ({$in_ids}) AND status IN ({$in_stat}) AND email <> ''", array_merge( $chunk, $statuses ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- Own table; placeholders built above.
			foreach ( (array) $rows as $row ) {
				$out[ (int) $row['id'] ] = (string) $row['email'];
			}
		}
		return $out;
	}

	/**
	 * One contact by email.
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public static function by_email( $email ) {
		$email = Rules::email( $email );
		return '' === $email ? null : self::row( 'email', $email );
	}

	/**
	 * One contact by token.
	 *
	 * @param string $token Token.
	 * @return array|null
	 */
	public static function by_token( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
		if ( '' === $token ) {
			return null;
		}
		$contact = self::row( 'token', $token );
		if ( ! $contact ) {
			$merged  = Identities::find( 'token', $token );
			$contact = $merged ? self::get( $merged ) : null;
		}
		return $contact;
	}

	/**
	 * A page of contacts.
	 *
	 * @param array $args search, status, list_id, tag_id, orderby, order, page, per_page.
	 * @return array{items:array[],total:int,pages:int,page:int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		$contacts = Schema::table( 'contacts' );
		$members  = Schema::table( 'memberships' );
		$paging   = Rules::paging( $args );
		$where    = array( '1=1' );
		$params   = array();

		$search = isset( $args['search'] ) ? trim( sanitize_text_field( (string) $args['search'] ) ) : '';
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR c.company LIKE %s OR c.phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( in_array( $status, Rules::STATUSES, true ) ) {
			$where[]  = 'c.status = %s';
			$params[] = $status;
		}
		foreach ( array( 'list_id', 'tag_id' ) as $key ) {
			$group = isset( $args[ $key ] ) ? (int) $args[ $key ] : 0;
			if ( $group > 0 ) {
				$where[]  = "EXISTS (SELECT 1 FROM `{$members}` m WHERE m.contact_id = c.id AND m.list_id = %d)";
				$params[] = $group;
			}
		}
		$orderby = isset( $args['orderby'] ) && in_array( $args['orderby'], self::ORDERBY, true ) ? $args['orderby'] : 'created_at';
		$order   = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$sql     = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM `{$contacts}` c WHERE {$sql}", $params ) : "SELECT COUNT(*) FROM `{$contacts}` c WHERE {$sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- Own tables; placeholders are in $sql, column names from an allow-list.
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT c.* FROM `{$contacts}` c WHERE {$sql} ORDER BY c.{$orderby} {$order}, c.id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $paging['per_page'], $paging['offset'] ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- Own tables; placeholders are in $sql, orderby/order from allow-lists.

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = self::shape( $row, true );
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $paging['per_page'] ),
			'page'  => $paging['page'],
		);
	}

	/**
	 * Count by status (for the dashboard and filters).
	 *
	 * @return array<string,int>
	 */
	public static function counts() {
		global $wpdb;
		$contacts = Schema::table( 'contacts' );
		$out      = array_fill_keys( Rules::STATUSES, 0 );
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM `{$contacts}` GROUP BY status", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			$out[ (string) $row['status'] ] = (int) $row['n'];
		}
		$out['all'] = array_sum( $out );
		return $out;
	}

	/**
	 * Add a contact by hand (the owner vouches for consent, so no double opt-in email).
	 *
	 * @param array $data Fields, plus status, list_ids, tag_ids.
	 * @return int|WP_Error Contact id.
	 */
	public static function create_by_owner( array $data ) {
		$clean = Rules::clean( $data );
		if ( empty( $clean['email'] ) ) {
			return new WP_Error( 'qcld_mk_email', __( 'Enter a valid email address.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( self::by_email( $clean['email'] ) ) {
			return new WP_Error( 'qcld_mk_exists', __( 'A contact with this email already exists.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$status = isset( $clean['status'] ) ? $clean['status'] : 'subscribed';
		$id     = self::insert( $clean, $status, isset( $clean['source'] ) ? $clean['source'] : 'admin' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::record_consent( $id, 'subscribed' === $status ? 'marketing' : 'contact', 'admin', __( 'Added by the site owner.', 'chatbot' ) );
		self::assign( $id, $data );
		return $id;
	}

	/**
	 * Update a contact. list_ids / tag_ids, when sent, are the complete wanted sets.
	 *
	 * @param int   $id   Id.
	 * @param array $data Fields.
	 * @return array|WP_Error The contact.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$contact = self::get( $id );
		if ( ! $contact ) {
			return self::not_found();
		}
		$clean   = Rules::clean( $data );
		$changes = array();
		foreach ( array( 'first_name', 'last_name', 'phone', 'company', 'website', 'language' ) as $key ) {
			if ( array_key_exists( $key, $clean ) ) {
				$changes[ $key ] = $clean[ $key ];
			}
		}
		if ( array_key_exists( 'email', $data ) ) {
			if ( '' === $clean['email'] ) {
				return new WP_Error( 'qcld_mk_email', __( 'Enter a valid email address.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$other = self::by_email( $clean['email'] );
			if ( $other && (int) $other['id'] !== (int) $id ) {
				return new WP_Error( 'qcld_mk_exists', __( 'A contact with this email already exists.', 'chatbot' ), array( 'status' => 409 ) );
			}
			$changes['email'] = $clean['email'];
		}
		if ( isset( $clean['status'] ) && $clean['status'] !== $contact['status'] ) {
			if ( ! Rules::owner_may_set( $contact['status'], $clean['status'] ) ) {
				return new WP_Error( 'qcld_mk_opted_out', __( 'This person unsubscribed or complained, so they cannot be subscribed again by hand. They can sign up again themselves.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$changes['status'] = $clean['status'];
			if ( 'unsubscribed' === $clean['status'] ) {
				$changes['unsubscribed_at'] = current_time( 'mysql', true );
			}
		}
		if ( isset( $clean['attributes'] ) ) {
			$changes['attributes'] = wp_json_encode( array_merge( (array) $contact['attributes'], $clean['attributes'] ) );
		}
		if ( $changes ) {
			$changes['updated_at'] = current_time( 'mysql', true );
			$wpdb->update( Schema::table( 'contacts' ), $changes, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			if ( isset( $changes['status'] ) ) {
				self::status_changed( self::get( $id ), (string) $contact['status'], 'owner' );
			}
		}
		self::assign( $id, $data );
		return self::get( $id );
	}

	/**
	 * Delete a contact and everything about them (GDPR erasure). Sent-email
	 * statistics stay as anonymous counts.
	 *
	 * @param int $id Id.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$contact = self::get( $id );
		if ( ! $contact ) {
			return self::not_found();
		}
		foreach ( array( 'memberships', 'identities', 'consents', 'events', 'journey_runs' ) as $table ) {
			$wpdb->delete( Schema::table( $table ), array( 'contact_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own tables.
		}
		$wpdb->update( Schema::table( 'messages' ), array( 'contact_id' => 0 ), array( 'contact_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table; keeps campaign counts.
		$wpdb->delete( Schema::table( 'contacts' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		/**
		 * A contact was erased.
		 *
		 * @param array $contact The contact before erasure.
		 */
		do_action( 'qcld_wpbot_marketing/contact_deleted', $contact );
		return true;
	}

	/**
	 * Merge two records of the same person: everything moves to $keep_id and
	 * $drop_id is removed. An opt-out on either record wins.
	 *
	 * @param int $keep_id Contact that stays.
	 * @param int $drop_id Contact merged into it.
	 * @return array|WP_Error The merged contact.
	 */
	public static function merge( $keep_id, $drop_id ) {
		global $wpdb;
		$keep = self::get( $keep_id );
		$drop = self::get( $drop_id );
		if ( ! $keep || ! $drop || (int) $keep_id === (int) $drop_id ) {
			return self::not_found();
		}
		$keep_id = (int) $keep_id;
		$drop_id = (int) $drop_id;
		// Rows that would clash (same list, same campaign …) stay on the dropped id and are cleaned below.
		foreach ( array( 'memberships', 'identities', 'journey_runs', 'messages' ) as $name ) {
			$table = Schema::table( $name );
			$wpdb->query( $wpdb->prepare( "UPDATE IGNORE `{$table}` SET contact_id = %d WHERE contact_id = %d", $keep_id, $drop_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
		}
		foreach ( array( 'consents', 'events' ) as $name ) {
			$wpdb->update( Schema::table( $name ), array( 'contact_id' => $keep_id ), array( 'contact_id' => $drop_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own tables.
		}
		foreach ( array( 'memberships', 'identities', 'journey_runs' ) as $name ) {
			$wpdb->delete( Schema::table( $name ), array( 'contact_id' => $drop_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own tables.
		}
		$wpdb->update( Schema::table( 'messages' ), array( 'contact_id' => 0 ), array( 'contact_id' => $drop_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table; duplicate sends stay as anonymous counts.
		// Links in emails already sent to the merged record keep working.
		Identities::link( $keep_id, 'token', (string) $drop['token'] );
		$map = Schema::table( 'legacy_map' );
		$wpdb->query( $wpdb->prepare( "UPDATE `{$map}` SET new_id = %d WHERE kind = 'contact' AND new_id = %d", $keep_id, $drop_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$changes = array( 'updated_at' => current_time( 'mysql', true ) );
		foreach ( array( 'first_name', 'last_name', 'phone', 'company', 'website', 'language' ) as $key ) {
			if ( '' === (string) $keep[ $key ] && '' !== (string) $drop[ $key ] ) {
				$changes[ $key ] = $drop[ $key ];
			}
		}
		$status = Rules::merge_statuses( (string) $keep['status'], (string) $drop['status'] );
		if ( $status !== $keep['status'] ) {
			$changes['status'] = $status;
			if ( in_array( $status, Rules::OPT_OUTS, true ) && empty( $keep['unsubscribed_at'] ) ) {
				$changes['unsubscribed_at'] = current_time( 'mysql', true );
			}
		}
		$changes['attributes'] = wp_json_encode( array_merge( (array) $drop['attributes'], (array) $keep['attributes'] ) );
		$wpdb->delete( Schema::table( 'contacts' ), array( 'id' => $drop_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( '' === (string) $keep['email'] && '' !== (string) $drop['email'] ) {
			$changes['email'] = $drop['email'];
		}
		$wpdb->update( Schema::table( 'contacts' ), $changes, array( 'id' => $keep_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( isset( $changes['status'] ) ) {
			self::status_changed( self::get( $keep_id ), (string) $keep['status'], 'merge' );
		}
		/**
		 * Two contacts were merged.
		 *
		 * @param int   $keep_id The contact that stays.
		 * @param array $drop    The contact merged into it, as it was.
		 */
		do_action( 'qcld_wpbot_marketing/contacts_merged', $keep_id, $drop );
		return self::get( $keep_id );
	}

	/**
	 * What a contact did lately (for scores and lifecycle stages).
	 *
	 * @param int $id   Contact id.
	 * @param int $days Days back.
	 * @return array{sent:int,opened:int,clicked:int,chats:int,last_open:string,last_click:string}
	 */
	public static function activity_counts( $id, $days = 90 ) {
		global $wpdb;
		$events   = Schema::table( 'events' );
		$messages = Schema::table( 'messages' );
		$since    = gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS );
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( "SELECT type, COUNT(*) AS n, MAX(created_at) AS last FROM `{$events}` WHERE contact_id = %d AND created_at >= %s AND type IN ('open','click','chat_lead') GROUP BY type", (int) $id, $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$out      = array(
			'sent'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$messages}` WHERE contact_id = %d AND status IN ('sent','unknown') AND sent_at >= %s", (int) $id, $since ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			'opened'     => 0,
			'clicked'    => 0,
			'chats'      => 0,
			'last_open'  => '',
			'last_click' => '',
		);
		foreach ( $rows as $row ) {
			if ( 'open' === $row['type'] ) {
				$out['opened']    = (int) $row['n'];
				$out['last_open'] = (string) $row['last'];
			} elseif ( 'click' === $row['type'] ) {
				$out['clicked']    = (int) $row['n'];
				$out['last_click'] = (string) $row['last'];
			} else {
				$out['chats'] = (int) $row['n'];
			}
		}
		return $out;
	}

	/**
	 * Set the lifecycle stage (worked out by the Master module).
	 *
	 * @param int    $id    Contact id.
	 * @param string $stage Stage key.
	 * @return bool Whether it changed.
	 */
	public static function set_lifecycle( $id, $stage ) {
		global $wpdb;
		$stage = substr( sanitize_key( (string) $stage ), 0, 20 );
		return (bool) $wpdb->query( $wpdb->prepare( 'UPDATE `' . Schema::table( 'contacts' ) . '` SET lifecycle = %s WHERE id = %d AND lifecycle <> %s', $stage, (int) $id, $stage ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Subscribers who were emailed in the last $days days but opened and
	 * clicked nothing in that time (for the sunset policy), oldest first.
	 *
	 * @param int $days     Days.
	 * @param int $after_id Continue after this contact id.
	 * @param int $limit    Max ids.
	 * @return int[]
	 */
	public static function inactive( $days, $after_id = 0, $limit = 200 ) {
		global $wpdb;
		$contacts = Schema::table( 'contacts' );
		$events   = Schema::table( 'events' );
		$messages = Schema::table( 'messages' );
		$since    = gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT c.id FROM `{$contacts}` c WHERE c.status = 'subscribed' AND c.id > %d AND c.created_at < %s AND EXISTS ( SELECT 1 FROM `{$messages}` m WHERE m.contact_id = c.id AND m.status = 'sent' AND m.sent_at >= %s ) AND NOT EXISTS ( SELECT 1 FROM `{$events}` e WHERE e.contact_id = c.id AND e.type IN ('open','click','chat_lead') AND e.created_at >= %s ) ORDER BY c.id ASC LIMIT %d", (int) $after_id, $since, $since, $since, max( 1, (int) $limit ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
	}

	/**
	 * Other contacts that are probably the same person: the same phone number,
	 * or the same Gmail address written with dots or a +tag.
	 *
	 * @param int $id Contact id.
	 * @return int[]
	 */
	public static function duplicates_of( $id ) {
		global $wpdb;
		$contact = self::get( $id );
		if ( ! $contact ) {
			return array();
		}
		$table = Schema::table( 'contacts' );
		$ids   = array();
		if ( '' !== (string) $contact['phone'] ) {
			$ids = array_merge( $ids, (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE phone = %s AND id <> %d LIMIT 20", (string) $contact['phone'], (int) $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		$canonical = Rules::canonical_gmail( (string) $contact['email'] );
		if ( '' !== $canonical ) {
			$ids = array_merge( $ids, (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id <> %d AND SUBSTRING_INDEX(email, '@', -1) IN ('gmail.com','googlemail.com') AND REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(email, '@', 1), '+', 1), '.', '') = %s LIMIT 20", (int) $id, substr( $canonical, 0, strpos( $canonical, '@' ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Add or update a contact from a capture (form, chatbot, import, sync).
	 *
	 * @param array  $data       email (or phone), first_name, last_name, phone, language, attributes ...
	 * @param string $consent    marketing, contact or none.
	 * @param string $source     Source key, e.g. chatbot, form, import.
	 * @param array  $groups     List/tag ids => reason.
	 * @param bool   $own_opt_in The person opted in themselves just now.
	 * @param array  $options    skip_double_opt_in (bool): people who already opted in elsewhere (backfill of chatbot subscribers).
	 * @return array{id:int,created:bool,status:string}|WP_Error
	 */
	public static function capture( array $data, $consent, $source, array $groups = array(), $own_opt_in = false, array $options = array() ) {
		global $wpdb;
		$clean   = Rules::clean( $data );
		$consent = in_array( $consent, Rules::CONSENTS, true ) ? $consent : 'contact';
		$doi     = empty( $options['skip_double_opt_in'] ) && (bool) Settings::get( 'double_opt_in' );
		$email   = isset( $clean['email'] ) ? $clean['email'] : '';
		$phone   = isset( $clean['phone'] ) ? $clean['phone'] : '';
		if ( '' === $email && '' === $phone ) {
			return new WP_Error( 'qcld_mk_email', __( 'An email address or phone number is needed.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( Suppression::blocked( $email ) ) {
			return new WP_Error( 'qcld_mk_suppressed', __( 'This address is on the suppression list.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$existing = '' !== $email ? self::by_email( $email ) : self::row( 'phone', $phone );
		$created  = false;
		if ( $existing ) {
			$id      = (int) $existing['id'];
			$status  = Rules::next_status( (string) $existing['status'], $consent, $doi, (bool) $own_opt_in );
			$changes = array();
			foreach ( array( 'first_name', 'last_name', 'phone', 'company', 'website', 'language' ) as $key ) {
				if ( ! empty( $clean[ $key ] ) && '' === (string) $existing[ $key ] ) {
					$changes[ $key ] = $clean[ $key ];
				}
			}
			if ( '' !== $email && null === $existing['email'] ) {
				$changes['email'] = $email;
			}
			if ( $status !== $existing['status'] ) {
				$changes['status'] = $status;
			}
			if ( $changes ) {
				$changes['updated_at'] = current_time( 'mysql', true );
				$wpdb->update( Schema::table( 'contacts' ), $changes, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				if ( isset( $changes['status'] ) ) {
					self::status_changed( self::get( $id ), (string) $existing['status'], $source );
				}
			}
		} else {
			$status = Rules::initial_status( $consent, $doi );
			$id     = self::insert( $clean, $status, $source );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$created = true;
		}
		self::record_consent( $id, $consent, $source, isset( $data['consent_text'] ) ? (string) $data['consent_text'] : '' );
		foreach ( $groups as $group_id => $reason ) {
			Lists::add_member( (int) $group_id, $id, (string) $reason, sanitize_key( (string) $source ) );
		}
		$contact = self::get( $id );
		if ( 'pending' === $contact['status'] && ( $created || 'pending' !== ( $existing ? $existing['status'] : '' ) ) ) {
			/**
			 * A contact needs a double opt-in confirmation email.
			 *
			 * @param array $contact Contact.
			 */
			do_action( 'qcld_wpbot_marketing/send_confirmation', $contact );
		}
		return array(
			'id'      => $id,
			'created' => $created,
			'status'  => (string) $contact['status'],
		);
	}

	/**
	 * Set a status that the system decides (bounced, complained, unsubscribed).
	 * Unlike update(), it bypasses the owner rules; it never subscribes anyone.
	 *
	 * @param int    $id     Contact id.
	 * @param string $status bounced, complained or unsubscribed.
	 * @param string $by     Who: bounce, complaint, provider ...
	 * @return bool Whether the status changed.
	 */
	public static function mark( $id, $status, $by ) {
		global $wpdb;
		$contact = self::get( $id );
		if ( ! $contact || ! in_array( $status, Rules::OPT_OUTS, true ) || $status === $contact['status'] || 'complained' === $contact['status'] ) {
			return false;
		}
		$changes = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( empty( $contact['unsubscribed_at'] ) ) {
			$changes['unsubscribed_at'] = current_time( 'mysql', true );
		}
		$wpdb->update( Schema::table( 'contacts' ), $changes, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		self::status_changed( self::get( $id ), (string) $contact['status'], sanitize_key( (string) $by ) );
		return true;
	}

	/**
	 * Apply a bulk action to contacts.
	 *
	 * @param int[]  $ids    Contact ids.
	 * @param string $action add_list, remove_list, unsubscribe, delete.
	 * @param int    $group  List or tag id for list actions.
	 * @return int Contacts changed.
	 */
	public static function bulk( array $ids, $action, $group = 0 ) {
		$done = 0;
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			if ( $id <= 0 ) {
				continue;
			}
			switch ( $action ) {
				case 'add_list':
					$done += Lists::add_member( (int) $group, $id, __( 'Added by the owner', 'chatbot' ), 'owner' ) ? 1 : 0;
					break;
				case 'remove_list':
					$done += Lists::remove_member( (int) $group, $id ) ? 1 : 0;
					break;
				case 'unsubscribe':
					$done += is_wp_error( self::update( $id, array( 'status' => 'unsubscribed' ) ) ) ? 0 : 1;
					break;
				case 'delete':
					$done += true === self::delete( $id ) ? 1 : 0;
					break;
			}
		}
		return $done;
	}

	/**
	 * A contact as returned to the app.
	 *
	 * @param array $row        Row.
	 * @param bool  $with_lists Include lists and tags.
	 * @return array
	 */
	public static function shape( array $row, $with_lists = false ) {
		$attributes = is_string( $row['attributes'] ) ? json_decode( $row['attributes'], true ) : $row['attributes'];
		$out        = array(
			'id'               => (int) $row['id'],
			'email'            => (string) $row['email'],
			'phone'            => (string) $row['phone'],
			'first_name'       => (string) $row['first_name'],
			'last_name'        => (string) $row['last_name'],
			'company'          => (string) $row['company'],
			'website'          => (string) $row['website'],
			'language'         => (string) $row['language'],
			'status'           => (string) $row['status'],
			'lifecycle'        => (string) $row['lifecycle'],
			'source'           => (string) $row['source'],
			'attributes'       => is_array( $attributes ) ? $attributes : array(),
			'token'            => (string) $row['token'],
			'mailable'         => Rules::mailable( (string) $row['status'] ),
			'confirmed_at'     => (string) $row['confirmed_at'],
			'unsubscribed_at'  => (string) $row['unsubscribed_at'],
			'last_activity_at' => (string) $row['last_activity_at'],
			'created_at'       => (string) $row['created_at'],
		);
		if ( $with_lists ) {
			$out['lists'] = array();
			$out['tags']  = array();
			foreach ( Lists::of_contact( (int) $row['id'] ) as $group ) {
				$out[ 'tag' === $group['type'] ? 'tags' : 'lists' ][] = array(
					'id'     => (int) $group['id'],
					'name'   => (string) $group['name'],
					'reason' => (string) $group['reason'],
				);
			}
		}
		return $out;
	}

	/* ── Internals. ── */

	/**
	 * Insert a contact row.
	 *
	 * @param array  $clean  Clean fields.
	 * @param string $status Status.
	 * @param string $source Source.
	 * @return int|WP_Error
	 */
	private static function insert( array $clean, $status, $source ) {
		global $wpdb;
		$now   = current_time( 'mysql', true );
		$saved = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'contacts' ),
			array(
				'email'        => ! empty( $clean['email'] ) ? $clean['email'] : null,
				'phone'        => isset( $clean['phone'] ) ? $clean['phone'] : '',
				'first_name'   => isset( $clean['first_name'] ) ? $clean['first_name'] : '',
				'last_name'    => isset( $clean['last_name'] ) ? $clean['last_name'] : '',
				'company'      => isset( $clean['company'] ) ? $clean['company'] : '',
				'website'      => isset( $clean['website'] ) ? $clean['website'] : '',
				'language'     => isset( $clean['language'] ) ? $clean['language'] : '',
				'status'       => $status,
				'source'       => sanitize_key( (string) $source ),
				'attributes'   => isset( $clean['attributes'] ) ? wp_json_encode( $clean['attributes'] ) : null,
				'token'        => wp_generate_password( 32, false, false ),
				'confirmed_at' => 'subscribed' === $status ? $now : null,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);
		if ( ! $saved ) {
			return new WP_Error( 'qcld_mk_db', __( 'The contact could not be saved. Please try again.', 'chatbot' ), array( 'status' => 500 ) );
		}
		$id = (int) $wpdb->insert_id;
		/**
		 * A contact was created.
		 *
		 * @param array  $contact Contact.
		 * @param string $source  Source.
		 */
		do_action( 'qcld_wpbot_marketing/contact_created', self::get( $id ), (string) $source );
		return $id;
	}

	/**
	 * Record what the person agreed to.
	 *
	 * @param int    $contact_id Contact id.
	 * @param string $consent    marketing, contact or none.
	 * @param string $source     Source.
	 * @param string $wording    Text the person saw.
	 * @return void
	 */
	private static function record_consent( $contact_id, $consent, $source, $wording ) {
		global $wpdb;
		if ( 'none' === $consent ) {
			return;
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'consents' ),
			array(
				'contact_id' => (int) $contact_id,
				'channel'    => 'email',
				'kind'       => $consent,
				'source'     => sanitize_key( (string) $source ),
				'wording'    => sanitize_textarea_field( (string) $wording ),
				'ip_hash'    => '' !== $ip ? hash( 'sha256', $ip . wp_salt( 'nonce' ) ) : '',
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Apply list_ids / tag_ids when the request sent them.
	 *
	 * @param int   $id   Contact id.
	 * @param array $data Request data.
	 * @return void
	 */
	private static function assign( $id, array $data ) {
		if ( isset( $data['list_ids'] ) && is_array( $data['list_ids'] ) ) {
			Lists::set_for_contact( $id, 'list', $data['list_ids'] );
		}
		if ( isset( $data['tag_ids'] ) && is_array( $data['tag_ids'] ) ) {
			Lists::set_for_contact( $id, 'tag', $data['tag_ids'] );
		}
	}

	/**
	 * Fire the status-change hook.
	 *
	 * @param array|null $contact Contact after the change.
	 * @param string     $old     Old status.
	 * @param string     $by      Who changed it.
	 * @return void
	 */
	private static function status_changed( $contact, $old, $by ) {
		if ( ! $contact ) {
			return;
		}
		/**
		 * A contact's status changed.
		 *
		 * @param array  $contact Contact (after).
		 * @param string $old     Old status.
		 * @param string $by      owner, chatbot, link ...
		 */
		do_action( 'qcld_wpbot_marketing/contact_status_changed', $contact, $old, $by );
	}

	/**
	 * One row by a column.
	 *
	 * @param string     $column id, email, token or phone.
	 * @param int|string $value  Value.
	 * @return array|null
	 */
	private static function row( $column, $value ) {
		global $wpdb;
		if ( ! in_array( $column, array( 'id', 'email', 'token', 'phone' ), true ) || '' === (string) $value ) {
			return null;
		}
		$contacts = Schema::table( 'contacts' );
		$format   = 'id' === $column ? '%d' : '%s';
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$contacts}` WHERE {$column} = {$format} ORDER BY id ASC LIMIT 1", $value ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- Own table; column and placeholder from an allow-list.
		if ( ! $row ) {
			return null;
		}
		$row['attributes'] = is_string( $row['attributes'] ) ? json_decode( $row['attributes'], true ) : array();
		return $row;
	}

	/**
	 * Not-found error.
	 *
	 * @return WP_Error
	 */
	private static function not_found() {
		return new WP_Error( 'qcld_mk_not_found', __( 'Contact not found.', 'chatbot' ), array( 'status' => 404 ) );
	}
}
