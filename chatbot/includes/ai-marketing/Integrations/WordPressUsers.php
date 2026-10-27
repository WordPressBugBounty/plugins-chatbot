<?php
/**
 * WordPress users → contacts.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Integrations;

use QuantumCloud\WPBot\CRM\Domain\ContactService;
use QuantumCloud\WPBot\CRM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds new WordPress users as contacts when the `sync_wp_users` setting is on,
 * and provides the user rows for the WordPress-users import.
 */
final class WordPressUsers {

	const SETTING = 'sync_wp_users';
	const SOURCE  = 'wordpress';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'wpbot_crm/settings_defaults', array( __CLASS__, 'defaults' ) );
		Settings::reset_cache();
		add_action( 'user_register', array( __CLASS__, 'on_register' ), 10, 1 );
	}

	/**
	 * Add the sync flag to the CRM settings (off by default).
	 *
	 * @param array $defaults Defaults.
	 * @return array
	 */
	public static function defaults( $defaults ) {
		$defaults                  = (array) $defaults;
		$defaults[ self::SETTING ] = false;
		return $defaults;
	}

	/**
	 * Create/merge a contact for a newly registered user.
	 *
	 * @param int $user_id User id.
	 */
	public static function on_register( $user_id ) {
		if ( ! Settings::bool( self::SETTING ) ) {
			return;
		}
		$data = self::user_data( (int) $user_id );
		if ( $data ) {
			( new ContactService() )->upsert( $data, self::SOURCE );
		}
	}

	/**
	 * Contact fields for a user (empty array when the user has no email).
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	public static function user_data( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return array();
		}
		return array(
			'email'      => $user->user_email,
			'first_name' => (string) get_user_meta( $user->ID, 'first_name', true ),
			'last_name'  => (string) get_user_meta( $user->ID, 'last_name', true ),
		);
	}

	/**
	 * Every user as a contact row, $per_page users per query.
	 *
	 * @param int $per_page Users per query.
	 * @return \Generator<int,array>
	 */
	public static function rows( $per_page = 500 ) {
		for ( $page = 1; ; $page++ ) {
			$users = get_users(
				array(
					'number'  => max( 1, (int) $per_page ),
					'paged'   => $page,
					'orderby' => 'ID',
					'order'   => 'ASC',
					'fields'  => array( 'ID', 'user_email' ),
				)
			);
			if ( empty( $users ) ) {
				return;
			}
			update_meta_cache( 'user', wp_list_pluck( $users, 'ID' ) );
			foreach ( $users as $user ) {
				yield array(
					'email'      => $user->user_email,
					'first_name' => (string) get_user_meta( $user->ID, 'first_name', true ),
					'last_name'  => (string) get_user_meta( $user->ID, 'last_name', true ),
				);
			}
		}
	}
}
