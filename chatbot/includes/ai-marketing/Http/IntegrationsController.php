<?php
/**
 * Form Capture: which form plugins are active, and the live capture toggle per source.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Integrations\FormCapture;

defined( 'ABSPATH' ) || exit;

final class IntegrationsController extends RestController {

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/integrations/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);
		register_rest_route(
			self::NS,
			'/integrations/capture',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'capture' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'source'  => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array_keys( FormCapture::sources() ),
					),
					'enabled' => array( 'type' => 'boolean' ),
				),
			)
		);
	}

	/**
	 * Top-level booleans say whether each plugin is active; `capturing` holds the live toggles.
	 *
	 * @return \WP_REST_Response
	 */
	public function status() {
		$data              = FormCapture::active_sources();
		$data['capturing'] = FormCapture::capturing();
		return rest_ensure_response( $data );
	}

	/**
	 * The UI sends only `source`, so a request without `enabled` flips the current state.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function capture( \WP_REST_Request $request ) {
		$source = $request['source'];
		$state  = $request->has_param( 'enabled' ) ? $this->bool( $request['enabled'] ) : null;
		$state  = FormCapture::set_enabled( $source, $state );
		$labels = FormCapture::sources();

		return rest_ensure_response(
			array(
				'success'   => true,
				'source'    => $source,
				'capturing' => $state,
				'message'   => sprintf(
					/* translators: %s: form plugin name. */
					$state ? __( 'Auto lead capture enabled for %s.', 'chatbot' ) : __( 'Auto lead capture disabled for %s.', 'chatbot' ),
					$labels[ $source ]
				),
			)
		);
	}
}
