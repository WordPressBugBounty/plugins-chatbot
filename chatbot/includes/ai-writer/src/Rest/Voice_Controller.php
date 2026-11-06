<?php
/**
 * REST routes for the site voice: read, create from samples, try, save.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Voice\Voice_Service;
use QuantumCloud\AiWriter\Voice\Voices;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/voices/*
 */
final class Voice_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns     = Settings_Controller::NAMESPACE_V1;
		$object = static function ( $value ) {
			return is_array( $value );
		};
		register_rest_route(
			$ns,
			'/voices/site',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_site' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_site' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'profile' => array(
							'type'              => 'object',
							'required'          => true,
							'validate_callback' => $object,
						),
						'name'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/voices/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'post_ids'   => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array( 'type' => 'integer' ),
					),
					'texts'      => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array( 'type' => 'string' ),
					),
					'answers'    => array(
						'type'              => 'object',
						'default'           => array(),
						'validate_callback' => $object,
					),
					'regenerate' => array(
						'type'    => 'object',
						'default' => array(),
					),
					'again'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/voices/try',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'try_voice' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'profile'    => array(
						'type'              => 'object',
						'required'          => true,
						'validate_callback' => $object,
					),
					'topic'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'regenerate' => array(
						'type'    => 'object',
						'default' => array(),
					),
					'again'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * View level.
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Module::cap( 'view' ) );
	}

	/**
	 * Settings level (the site voice affects everyone's writing).
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( Module::cap( 'settings' ) );
	}

	/**
	 * GET the site voice.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_site() {
		return rest_ensure_response( Voices::site_voice() );
	}

	/**
	 * POST save the site voice.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_site( WP_REST_Request $request ) {
		$name = '' !== (string) $request['name'] ? (string) $request['name'] : __( 'Site voice', 'chatbot' );
		return rest_ensure_response( Voices::save( Voices::SITE_VOICE, $name, (array) $request['profile'] ) );
	}

	/**
	 * POST build a profile from samples (not saved yet).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate( WP_REST_Request $request ) {
		$profile = Voice_Service::create_profile( (array) $request['post_ids'], (array) $request['texts'], (array) $request['answers'], self::regen( $request ) );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		return rest_ensure_response( array( 'profile' => $profile ) );
	}

	/**
	 * POST a sample paragraph in a voice.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function try_voice( WP_REST_Request $request ) {
		$paragraph = Voice_Service::try_voice( (array) $request['profile'], (string) $request['topic'], self::regen( $request ) );
		if ( is_wp_error( $paragraph ) ) {
			return $paragraph;
		}
		return rest_ensure_response( array( 'paragraph' => $paragraph ) );
	}

	/**
	 * Regenerate options of a request (CR-14), or null for a first run.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|null
	 */
	private static function regen( WP_REST_Request $request ) {
		return $request['again'] ? \QuantumCloud\AiWriter\Regenerate\Options::sanitise( (array) $request['regenerate'] ) : null;
	}
}
