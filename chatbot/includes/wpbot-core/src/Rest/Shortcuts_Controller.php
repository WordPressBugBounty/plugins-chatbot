<?php
/**
 * REST: the current user's WPBot Shortcuts (the "Add shortcut to the Admin Bar" button).
 *
 * GET    wpbot-core/v1/shortcuts               The user's shortcuts.
 * POST   wpbot-core/v1/shortcuts  url, title, group   Add one.
 * DELETE wpbot-core/v1/shortcuts  url           Remove one.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\Admin\Shortcuts;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcuts controller.
 */
final class Shortcuts_Controller extends Rest_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$text = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/shortcuts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'allowed' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add_item' ),
					'permission_callback' => array( $this, 'allowed' ),
					'args'                => array(
						'url'   => array( 'required' => true ) + $text,
						'title' => $text,
						'group' => $text,
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'remove_item' ),
					'permission_callback' => array( $this, 'allowed' ),
					'args'                => array(
						'url' => array( 'required' => true ) + $text,
					),
				),
			)
		);
	}

	/**
	 * Any logged-in user with admin access keeps their own shortcuts.
	 *
	 * @return bool
	 */
	public function allowed() {
		return is_user_logged_in() && current_user_can( 'read' );
	}

	/**
	 * The user's shortcuts.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_items() {
		return rest_ensure_response( array( 'items' => $this->with_links( Shortcuts::items( get_current_user_id() ) ) ) );
	}

	/**
	 * Add a shortcut.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_item( $request ) {
		$result = Shortcuts::add( get_current_user_id(), (string) $request['url'], (string) $request['title'], (string) $request['group'] );
		if ( ! $result['ok'] ) {
			return new \WP_Error( 'qcld_wpbot_core_shortcut_' . $result['code'], 'full' === $result['code'] ? __( 'You have the most shortcuts allowed. Remove one first.', 'chatbot' ) : __( 'This page cannot be added as a shortcut.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response(
			array(
				'code'  => $result['code'],
				'items' => $this->with_links( $result['items'] ),
			)
		);
	}

	/**
	 * Remove a shortcut.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function remove_item( $request ) {
		return rest_ensure_response( array( 'items' => $this->with_links( Shortcuts::remove( get_current_user_id(), (string) $request['url'] ) ) ) );
	}

	/**
	 * Add each shortcut's full address (the script redraws the admin bar menu with them).
	 *
	 * @param array $items Shortcuts.
	 * @return array
	 */
	private function with_links( array $items ) {
		foreach ( $items as $i => $item ) {
			$items[ $i ]['href'] = admin_url( $item['url'] );
		}
		return $items;
	}
}
