<?php
/**
 * The llms.txt file (round 2, R4): /llms (the file, what was left out and
 * why, the live check, conflicts; Use level), /llms/settings (turn on, summary,
 * notes, most links, post types, pinned and kept-out pages; settings level),
 * /llms/check (check the live file) and /llms/rebuild (Use level).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Llms\Llms_Check;
use QuantumCloud\SeoAiSearch\Llms\Llms_Txt;
use QuantumCloud\SeoAiSearch\Settings;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes for llms.txt.
 */
final class Llms_Controller extends Rest_Base {

	const KEYS = array( 'llms_enabled', 'llms_summary', 'llms_details', 'llms_max', 'llms_types', 'llms_pinned', 'llms_excluded' );

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/llms'          => array( WP_REST_Server::READABLE, 'view', 'can_use' ),
			'/llms/settings' => array( WP_REST_Server::CREATABLE, 'settings', 'can_manage' ),
			'/llms/check'    => array( WP_REST_Server::CREATABLE, 'check', 'can_use' ),
			'/llms/rebuild'  => array( WP_REST_Server::CREATABLE, 'rebuild', 'can_use' ),
		);
		foreach ( $routes as $route => $def ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => array( $this, $def[2] ),
				)
			);
		}
	}

	/**
	 * Everything the tab shows.
	 *
	 * @return \WP_REST_Response
	 */
	public function view() {
		$settings = array();
		foreach ( self::KEYS as $key ) {
			$settings[ $key ] = Settings::get( $key );
		}
		$out = array(
			'enabled'  => Llms_Txt::enabled(),
			'url'      => Llms_Txt::url(),
			'settings' => $settings,
			'types'    => array(),
			'check'    => Llms_Check::state(),
			'conflict' => Llms_Txt::conflicts(),
			'can_save' => $this->can_manage(),
		);
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$out['types'][] = array(
					'id'    => $type->name,
					'label' => $type->labels->name,
				);
			}
		}
		if ( Llms_Txt::enabled() ) {
			$cache           = Llms_Txt::current();
			$out['text']     = $cache['text'];
			$out['count']    = (int) $cache['count'];
			$out['built_at'] = (string) $cache['built_at'];
			$out['left_out'] = array();
			foreach ( array_slice( (array) $cache['left_out'], 0, 100, true ) as $post_id => $why ) {
				$title             = get_the_title( (int) $post_id );
				$out['left_out'][] = array(
					'post_id' => (int) $post_id,
					'title'   => '' !== $title ? $title : '#' . (int) $post_id,
					'why'     => (string) $why,
				);
			}
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Save the settings (and rebuild).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function settings( WP_REST_Request $request ) {
		$changes = array();
		foreach ( self::KEYS as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$changes[ $key ] = $request->get_param( $key );
			}
		}
		Settings::update( $changes );
		if ( Llms_Txt::enabled() ) {
			Llms_Txt::rebuild();
			Llms_Check::soon();
		}
		return $this->view();
	}

	/**
	 * Check the live file now.
	 *
	 * @return \WP_REST_Response
	 */
	public function check() {
		return rest_ensure_response( Llms_Check::run() );
	}

	/**
	 * Rebuild now.
	 *
	 * @return \WP_REST_Response
	 */
	public function rebuild() {
		Llms_Txt::rebuild();
		return $this->view();
	}
}
