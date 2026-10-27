<?php
/**
 * WooCommerce customers → contacts.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Integrations;

use QuantumCloud\WPBot\CRM\Domain\ContactService;
use QuantumCloud\WPBot\CRM\Domain\GroupRepository;
use QuantumCloud\WPBot\CRM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * On completed orders (when `sync_woo_customers` is on) the buyer becomes a contact
 * tagged "Customer". Also provides customer rows for the WooCommerce import.
 */
final class WooCommerce {

	const SETTING = 'sync_woo_customers';
	const SOURCE  = 'woocommerce';
	const TAG     = 'Customer';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'wpbot_crm/settings_defaults', array( __CLASS__, 'defaults' ) );
		Settings::reset_cache();
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_completed' ), 10, 1 );
	}

	/**
	 * Whether WooCommerce is loaded.
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_order' );
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
	 * Add the buyer of a completed order as a `customer` contact tagged "Customer".
	 *
	 * @param int $order_id Order id.
	 */
	public static function on_completed( $order_id ) {
		if ( ! Settings::bool( self::SETTING ) || ! self::active() ) {
			return;
		}
		self::upsert_buyer( wc_get_order( $order_id ) );
	}

	/**
	 * Upsert the buyer of an order (status `customer` when new) and tag them "Customer".
	 *
	 * @param mixed $order WC_Order.
	 * @return int|\WP_Error|null Contact id, error, or null when the order has no email.
	 */
	public static function upsert_buyer( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_billing_email' ) ) {
			return null;
		}
		$data = self::order_fields( $order );
		if ( '' === $data['email'] ) {
			return null;
		}
		$data['status'] = 'customer';
		$tag_id         = ( new GroupRepository() )->ensure( 'tag', self::TAG );
		return ( new ContactService() )->upsert( $data, self::SOURCE, $tag_id ? array( $tag_id ) : array() );
	}

	/**
	 * Contact fields from an order's billing address.
	 *
	 * @param object $order WC_Order.
	 * @return array
	 */
	public static function order_fields( $order ) {
		return array(
			'email'      => (string) $order->get_billing_email(),
			'first_name' => (string) $order->get_billing_first_name(),
			'last_name'  => (string) $order->get_billing_last_name(),
			'phone'      => (string) $order->get_billing_phone(),
			'company'    => (string) $order->get_billing_company(),
		);
	}

	/**
	 * Customer accounts, then billing details of processing/completed orders (guests included).
	 *
	 * @param int $per_page Rows per query.
	 * @return \Generator<int,array>
	 */
	public static function customer_rows( $per_page = 500 ) {
		$per_page = max( 1, (int) $per_page );
		for ( $page = 1; ; $page++ ) {
			$users = get_users(
				array(
					'role'    => 'customer',
					'number'  => $per_page,
					'paged'   => $page,
					'orderby' => 'ID',
					'fields'  => array( 'ID', 'user_email' ),
				)
			);
			if ( empty( $users ) ) {
				break;
			}
			update_meta_cache( 'user', wp_list_pluck( $users, 'ID' ) );
			foreach ( $users as $user ) {
				$billing = (string) get_user_meta( $user->ID, 'billing_email', true );
				yield array(
					'email'      => '' !== $billing ? $billing : $user->user_email,
					'first_name' => (string) get_user_meta( $user->ID, 'billing_first_name', true ),
					'last_name'  => (string) get_user_meta( $user->ID, 'billing_last_name', true ),
					'phone'      => (string) get_user_meta( $user->ID, 'billing_phone', true ),
					'company'    => (string) get_user_meta( $user->ID, 'billing_company', true ),
				);
			}
		}
		for ( $page = 1; ; $page++ ) {
			$orders = wc_get_orders(
				array(
					'limit'   => min( 200, $per_page ),
					'paged'   => $page,
					'orderby' => 'ID',
					'order'   => 'ASC',
					'type'    => 'shop_order',
					'status'  => array( 'wc-processing', 'wc-completed' ),
				)
			);
			if ( empty( $orders ) ) {
				return;
			}
			foreach ( $orders as $order ) {
				if ( is_object( $order ) && method_exists( $order, 'get_billing_email' ) ) {
					yield self::order_fields( $order );
				}
			}
		}
	}
}
