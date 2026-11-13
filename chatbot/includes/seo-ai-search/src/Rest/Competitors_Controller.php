<?php
/**
 * Shared competitor list (round 2, R2): /competitors (read, at Use level),
 * /competitors/suggest (AI suggestions, at Use level: costs AI credits, run
 * on a click or once automatically for a site with none), /competitors/save
 * (replace the list, settings level) and /competitors/add (add ticked
 * suggestions, settings level).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Research\Competitor_List;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Competitor routes.
 */
final class Competitors_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/competitors'         => array( WP_REST_Server::READABLE, 'view', 'can_use' ),
			'/competitors/suggest' => array( WP_REST_Server::CREATABLE, 'suggest', 'can_use' ),
			'/competitors/save'    => array( WP_REST_Server::CREATABLE, 'save', 'can_manage' ),
			'/competitors/add'     => array( WP_REST_Server::CREATABLE, 'add', 'can_manage' ),
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
	 * The shared list.
	 *
	 * @return \WP_REST_Response
	 */
	public function view() {
		return rest_ensure_response(
			array(
				'available' => Competitor_List::available(),
				'list'      => Competitor_List::all(),
				'can_save'  => $this->can_manage(),
				'auto_done' => (bool) get_option( Competitor_List::AUTO ),
				'prefs'     => Competitor_List::prefs(),
			)
		);
	}

	/**
	 * AI suggestions (verified; nothing saved).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function suggest( WP_REST_Request $request ) {
		return $this->respond(
			Competitor_List::suggest(
				rest_sanitize_boolean( $request['auto'] ),
				sanitize_text_field( (string) $request['focus'] ),
				sanitize_key( (string) $request['kind'] )
			)
		);
	}

	/**
	 * Replace the list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		if ( ! Competitor_List::available() ) {
			return new WP_Error( 'qcld_wpbot_seo_no_competitors', sprintf( /* translators: %s: product name, for example WPBot. */ __( 'Update %s to use the shared competitor list.', 'chatbot' ), \QuantumCloud\SeoAiSearch\Module::product() ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'list' => Competitor_List::save( $this->items( $request['items'] ) ) ) );
	}

	/**
	 * Add ticked suggestions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function add( WP_REST_Request $request ) {
		if ( ! Competitor_List::available() ) {
			return new WP_Error( 'qcld_wpbot_seo_no_competitors', sprintf( /* translators: %s: product name, for example WPBot. */ __( 'Update %s to use the shared competitor list.', 'chatbot' ), \QuantumCloud\SeoAiSearch\Module::product() ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'list' => Competitor_List::add( $this->items( $request['items'] ) ) ) );
	}

	/**
	 * Items from a request: strings, a text block (one per line or comma separated) or arrays (domain, reason, source).
	 *
	 * @param mixed $raw Raw items.
	 * @return array
	 */
	private function items( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\s,]+/', $raw );
		}
		$items = array();
		foreach ( array_slice( (array) $raw, 0, 50 ) as $item ) {
			if ( is_array( $item ) ) {
				$items[] = array(
					'domain' => isset( $item['domain'] ) ? sanitize_text_field( (string) $item['domain'] ) : '',
					'reason' => isset( $item['reason'] ) ? sanitize_text_field( (string) $item['reason'] ) : '',
					'source' => isset( $item['source'] ) ? sanitize_key( (string) $item['source'] ) : 'owner',
				);
			} elseif ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
				$items[] = sanitize_text_field( (string) $item );
			}
		}
		return $items;
	}
}
