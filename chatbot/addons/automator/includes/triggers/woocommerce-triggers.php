<?php
/**
 * WooCommerce Triggers
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooCommerce_Triggers
 */
class WooCommerce_Triggers extends Trigger {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'woocommerce';
		$this->group = __( 'WooCommerce', 'wpbot-automator' );
	}

	/**
	 * Get available sub-triggers
	 */
	public static function get_sub_triggers() {
		return array(
			'wc_order_created'       => array(
				'title' => __( 'Order Created', 'wpbot-automator' ),
				'hook'  => 'woocommerce_new_order',
			),
			'wc_order_status_change' => array(
				'title' => __( 'Order Status Change', 'wpbot-automator' ),
				'hook'  => 'woocommerce_order_status_changed',
			),
			'wc_payment_completed'   => array(
				'title' => __( 'Payment Completed', 'wpbot-automator' ),
				'hook'  => 'woocommerce_payment_complete',
			),
			'wc_product_added'       => array(
				'title' => __( 'Product Added', 'wpbot-automator' ),
				'hook'  => 'wp_insert_post', // Filtered for product post type
			),
		);
	}

	/**
	 * Register the triggers
	 */
	public function register() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$triggers = self::get_sub_triggers();

		add_action( 'woocommerce_new_order', array( $this, 'handle_new_order' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_payment_complete' ), 10, 1 );
		add_action( 'wp_insert_post', array( $this, 'handle_new_product' ), 10, 3 );
	}

	/**
	 * Handle new order
	 */
	public function handle_new_order( $order_id, $order ) {
		$product_ids = array();
		$items       = array();
		foreach ( $order->get_items() as $item ) {
			$product_ids[] = $item->get_product_id();
			$items[]       = array(
				'name'     => $item->get_name(),
				'sku'      => ( $item->get_product() ? $item->get_product()->get_sku() : '' ),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
			);
		}

		$this->run( array(
			'sub_id' => 'wc_order_created',
			'data'   => array(
				'order_id'       => $order_id,
				'total'          => $order->get_total(),
				'currency'       => $order->get_currency(),
				'status'         => $order->get_status(),
				'billing_email'  => $order->get_billing_email(),
				'billing_phone'  => $order->get_billing_phone(),
				'billing_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'payment_method' => $order->get_payment_method_title(),
				'product_ids'    => $product_ids,
				'items'          => $items,
			),
		) );
	}

	/**
	 * Handle order status change
	 */
	public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
		$product_ids = array();
		$items       = array();
		foreach ( $order->get_items() as $item ) {
			$product_ids[] = $item->get_product_id();
			$items[]       = array(
				'name'     => $item->get_name(),
				'sku'      => ( $item->get_product() ? $item->get_product()->get_sku() : '' ),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
			);
		}

		$this->run( array(
			'sub_id' => 'wc_order_status_change',
			'data'   => array(
				'order_id'       => $order_id,
				'old_status'     => $old_status,
				'new_status'     => $new_status,
				'total'          => $order->get_total(),
				'currency'       => $order->get_currency(),
				'billing_email'  => $order->get_billing_email(),
				'billing_phone'  => $order->get_billing_phone(),
				'billing_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'payment_method' => $order->get_payment_method_title(),
				'product_ids'    => $product_ids,
				'items'          => $items,
			),
		) );
	}

	/**
	 * Handle payment complete
	 */
	public function handle_payment_complete( $order_id ) {
		$order       = \wc_get_order( $order_id );
		$product_ids = array();
		$items       = array();
		if ( $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_ids[] = $item->get_product_id();
				$items[]       = array(
					'name'     => $item->get_name(),
					'sku'      => ( $item->get_product() ? $item->get_product()->get_sku() : '' ),
					'quantity' => $item->get_quantity(),
					'total'    => $item->get_total(),
				);
			}
		}

		$this->run( array(
			'sub_id' => 'wc_payment_completed',
			'data'   => array(
				'order_id'       => $order_id,
				'total'          => $order ? $order->get_total() : 0,
				'currency'       => $order ? $order->get_currency() : '',
				'billing_email'  => $order ? $order->get_billing_email() : '',
				'billing_phone'  => $order ? $order->get_billing_phone() : '',
				'billing_name'   => $order ? ( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) : '',
				'payment_method' => $order ? $order->get_payment_method_title() : '',
				'product_ids'    => $product_ids,
				'items'          => $items,
			),
		) );
	}

	/**
	 * Handle new product
	 */
	public function handle_new_product( $post_id, $post, $update ) {
		if ( $update || 'product' !== $post->post_type ) {
			return;
		}

		$this->run( array(
			'sub_id'   => 'wc_product_added',
			'data'     => array(
				'product_id' => $post_id,
				'title'      => $post->post_title,
			),
		) );
	}
}
