<?php
/**
 * WooCommerce Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooCommerce_Actions
 */
class WooCommerce_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'woocommerce';
		$this->group = __( 'WooCommerce', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 * 
	 * @param array $action_data Configuration for this action from the workflow.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return bool|array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'wc_order_created':
				return $this->create_order( $action_data, $trigger_data );
			default:
				return array( 'success' => true, 'message' => "Action {$action_id} executed (simulated)" );
		}
	}

	/**
	 * Create a new WooCommerce order
	 */
	private function create_order( $config, $trigger_data ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'success' => false, 'message' => 'WooCommerce is not active.' );
		}

		// Get User ID from trigger data or previous actions.
		$user_id = isset( $trigger_data['user_id'] ) ? $trigger_data['user_id'] : get_current_user_id();
		
		if ( ! $user_id ) {
			//error_log( 'WPbot Automator - Create Order: No user ID found.' );
			return array( 'success' => false, 'message' => 'No user ID found for order.' );
		}

		$product_id = isset( $config['config']['product_id'] ) ? (int) $config['config']['product_id'] : 0;
		if ( ! $product_id ) {
			//error_log( 'WPbot Automator - Create Order: No product ID selected.' );
			return array( 'success' => false, 'message' => 'No product selected.' );
		}

		try {
			$order = wc_create_order( array( 'customer_id' => $user_id ) );
			$order->add_product( wc_get_product( $product_id ), 1 );
			$order->calculate_totals();
			$order->update_status( 'pending', 'Created via WPbot Automator', true );
			
			//error_log( sprintf( 'WPbot Automator - Order %d created for user %d', $order->get_id(), $user_id ) );

			return array(
				'success'  => true,
				'order_id' => $order->get_id(),
				'message'  => 'Order created successfully.',
			);
		} catch ( \Exception $e ) {
			//error_log( 'WPbot Automator - Order creation failed: ' . $e->getMessage() );
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}
}
