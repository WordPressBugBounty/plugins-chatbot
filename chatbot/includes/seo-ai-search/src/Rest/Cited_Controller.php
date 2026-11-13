<?php
/**
 * Get cited by AI routes. Milestone A: the shared Site Context ("What we
 * know about your site"): read it (the free scan runs by itself the first
 * time), scan again, build the AI summary (cost shown first), edit it,
 * switch layers, and fill the chatbot's knowledge base.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Cited\Drafts;
use QuantumCloud\SeoAiSearch\Cited\Focus;
use QuantumCloud\SeoAiSearch\Cited\Places;
use QuantumCloud\SeoAiSearch\Cited\Posts;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\SeoAiSearch\Research\Research;
use QuantumCloud\WPBotCore\Context\Context;
use QuantumCloud\WPBotCore\Context\Knowledge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Get cited by AI controller.
 */
final class Cited_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/cited/context'           => array( WP_REST_Server::READABLE, 'context', 'can_use' ),
			'/cited/context/scan'      => array( WP_REST_Server::CREATABLE, 'scan', 'can_use' ),
			'/cited/context/summary'   => array( WP_REST_Server::CREATABLE, 'summary', 'can_use' ),
			'/cited/context/edit'      => array( WP_REST_Server::CREATABLE, 'edit', 'can_manage' ),
			'/cited/context/settings'  => array( WP_REST_Server::CREATABLE, 'settings', 'can_manage' ),
			'/cited/context/knowledge' => array( WP_REST_Server::CREATABLE, 'knowledge', 'can_manage' ),
			'/cited/focus'             => array( WP_REST_Server::READABLE, 'focus', 'can_use' ),
			'/cited/focus/save'        => array( WP_REST_Server::CREATABLE, 'focus_save', 'can_manage' ),
			'/cited/places'            => array( WP_REST_Server::READABLE, 'places', 'can_use' ),
			'/cited/places/estimate'   => array( WP_REST_Server::CREATABLE, 'places_estimate', 'can_use' ),
			'/cited/places/find'       => array( WP_REST_Server::CREATABLE, 'places_find', 'can_use' ),
			'/cited/places/status'     => array( WP_REST_Server::CREATABLE, 'place_status', 'can_use' ),
			'/cited/settings'          => array( WP_REST_Server::CREATABLE, 'cited_settings', 'can_manage' ),
			'/cited/drafts/estimate'   => array( WP_REST_Server::CREATABLE, 'draft_estimate', 'can_use' ),
			'/cited/drafts/create'     => array( WP_REST_Server::CREATABLE, 'draft_create', 'can_use' ),
			'/cited/posts'             => array( WP_REST_Server::READABLE, 'posts', 'can_use' ),
			'/cited/posts/log'         => array( WP_REST_Server::CREATABLE, 'post_log', 'can_use' ),
			'/cited/posts/delete'      => array( WP_REST_Server::CREATABLE, 'post_delete', 'can_manage' ),
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
	 * Is the shared Site Context available (WPBot Core with Context)?
	 *
	 * @return true|WP_Error
	 */
	private function available() {
		return class_exists( Context::class ) ? true : new WP_Error( 'qcld_wpbot_seo_no_context', sprintf( /* translators: %s: product name, for example WPBot. */ __( 'Update %s to use Get cited by AI.', 'chatbot' ), \QuantumCloud\SeoAiSearch\Module::product() ), array( 'status' => 400 ) );
	}

	/**
	 * The context, scanned once by itself on the first visit (free, no AI).
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function context() {
		$ok = $this->available();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$state = Context::get();
		if ( '' === $state['scanned_at'] && $state['settings']['site'] ) {
			$fresh = Context::refresh_facts();
			$state = is_wp_error( $fresh ) ? $state : $fresh;
		}
		return $this->respond( $this->view( $state ) );
	}

	/**
	 * Read the free layers again.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function scan() {
		$ok = $this->available();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$state = Context::refresh_facts();
		return $this->respond( is_wp_error( $state ) ? $state : $this->view( $state ) );
	}

	/**
	 * Build the AI summary (the screen showed the cost first).
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function summary() {
		$ok = $this->available();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$ready = Research::ai_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$state = Context::summarize( Module::ID );
		return $this->respond( is_wp_error( $state ) ? $state : $this->view( $state ) );
	}

	/**
	 * Save the owner's edits to the summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function edit( WP_REST_Request $request ) {
		$ok = $this->available();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$summary = $request->get_param( 'summary' );
		if ( ! is_array( $summary ) || null === Context::clean_summary( $summary ) ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'Describe the business or list what it offers.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return $this->respond( $this->view( Context::save_summary( $summary ) ) );
	}

	/**
	 * Switch layers on or off.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function settings( WP_REST_Request $request ) {
		$ok = $this->available();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$changes = array();
		foreach ( array_keys( Context::DEFAULTS ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$changes[ $key ] = rest_sanitize_boolean( $request->get_param( $key ) );
			}
		}
		Context::save_settings( $changes );
		// A layer just switched on is read at once (free; the knowledge base may use the chatbot's search).
		$state = array_filter( $changes ) ? Context::refresh_facts() : Context::get();
		return $this->respond( is_wp_error( $state ) ? $state : $this->view( $state ) );
	}

	/**
	 * Add the site's main pages and products to the chatbot's knowledge base (a job).
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function knowledge() {
		$ok = $this->available();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$state = Context::get();
		if ( '' === $state['scanned_at'] ) {
			Context::refresh_facts();
		}
		$ids = function_exists( 'qcld_wpbot_core_context_post_ids' ) ? qcld_wpbot_core_context_post_ids() : array();
		$job = Knowledge::build( Module::ID, $ids );
		return $this->respond( is_wp_error( $job ) ? $job : array( 'job_id' => (int) $job ) );
	}

	/**
	 * The saved focus with suggestions from the Site Context.
	 *
	 * @return \WP_REST_Response
	 */
	public function focus() {
		return rest_ensure_response( $this->focus_view() );
	}

	/**
	 * Save the focus and topics.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function focus_save( WP_REST_Request $request ) {
		$focus = (string) $request->get_param( 'focus' );
		if ( strlen( $focus ) > Focus::MAX * 4 ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'Keep your focus under 500 characters.', 'chatbot' ), array( 'status' => 400 ) );
		}
		Focus::save( $focus, (array) $request->get_param( 'topics' ) );
		return rest_ensure_response( $this->focus_view() );
	}

	/**
	 * Places, the searches to pick from, platforms and YouTube state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function places( WP_REST_Request $request ) {
		$platform = sanitize_key( (string) $request->get_param( 'platform' ) );
		$defs     = Places::platforms();
		$list     = array();
		foreach ( $defs as $id => $def ) {
			$list[] = array(
				'id'    => $id,
				'label' => $def['label'],
				'on'    => in_array( $id, Places::enabled_platforms(), true ),
			);
		}
		$youtube = class_exists( '\QuantumCloud\WPBotCore\Connections\YouTube' ) ? \QuantumCloud\WPBotCore\Connections\YouTube::status()['status'] : 'unavailable';
		return rest_ensure_response(
			array(
				'places'     => array_map( array( $this, 'with_writer' ), Places::query( array( 'platform' => $platform ) ) ),
				'disclosure' => \QuantumCloud\SeoAiSearch\Cited\Rules::disclosure(),
				'searches'   => Places::suggested_searches(),
				'platforms'  => $list,
				'web_search' => Places::web_search(),
				'youtube'    => $youtube,
				'can_edit'   => $this->can_manage(),
				'max'        => Places::SEARCHES,
			)
		);
	}

	/**
	 * Cost of finding places for some searches.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function places_estimate( WP_REST_Request $request ) {
		return rest_ensure_response( Places::estimate( count( (array) $request->get_param( 'searches' ) ) ) );
	}

	/**
	 * Start finding places (a job; the screen showed the cost first).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function places_find( WP_REST_Request $request ) {
		$ready = Research::ai_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$job = Places::find( (array) $request->get_param( 'searches' ) );
		return $this->respond( is_wp_error( $job ) ? $job : array( 'job_id' => (int) $job ) );
	}

	/**
	 * Dismiss a place or bring it back.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function place_status( WP_REST_Request $request ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! in_array( $status, array( 'new', 'dismissed' ), true ) || ! Places::get( absint( $request->get_param( 'id' ) ) ) ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'That place was not found.', 'chatbot' ), array( 'status' => 400 ) );
		}
		Places::set_status( absint( $request->get_param( 'id' ) ), $status );
		return rest_ensure_response( Places::get( absint( $request->get_param( 'id' ) ) ) );
	}

	/**
	 * Get cited by AI settings: platforms and YouTube.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function cited_settings( WP_REST_Request $request ) {
		$changes = array();
		if ( null !== $request->get_param( 'platforms' ) ) {
			$changes['cited_platforms'] = array_values( array_intersect( array_map( 'sanitize_key', (array) $request->get_param( 'platforms' ) ), array_keys( Places::platforms() ) ) );
		}
		if ( null !== $request->get_param( 'youtube' ) ) {
			$changes['cited_youtube'] = rest_sanitize_boolean( $request->get_param( 'youtube' ) );
		}
		if ( array() !== $changes ) {
			Settings::update( $changes );
		}
		return $this->places( $request );
	}

	/**
	 * Cost of one draft.
	 *
	 * @return \WP_REST_Response
	 */
	public function draft_estimate() {
		return rest_ensure_response( array( 'cost' => Drafts::estimate() ) );
	}

	/**
	 * Write a draft for a place (the screen showed the cost first).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function draft_create( WP_REST_Request $request ) {
		$ready = Research::ai_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$id    = absint( $request->get_param( 'id' ) );
		$draft = Drafts::create(
			$id,
			array(
				'tip'     => (string) $request->get_param( 'tip' ),
				'example' => (string) $request->get_param( 'example' ),
				'steer'   => (string) $request->get_param( 'steer' ),
			)
		);
		if ( is_wp_error( $draft ) ) {
			return $this->respond( $draft );
		}
		return rest_ensure_response( $this->with_writer( Places::get( $id ) ) );
	}

	/**
	 * The posted log.
	 *
	 * @return \WP_REST_Response
	 */
	public function posts() {
		return rest_ensure_response(
			array(
				'posts'    => Posts::query(),
				'can_edit' => $this->can_manage(),
			)
		);
	}

	/**
	 * "I posted it".
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function post_log( WP_REST_Request $request ) {
		return $this->respond(
			Posts::log(
				array(
					'place_id' => absint( $request->get_param( 'place_id' ) ),
					'url'      => (string) $request->get_param( 'url' ),
					'page_url' => (string) $request->get_param( 'page_url' ),
					'note'     => (string) $request->get_param( 'note' ),
				)
			)
		);
	}

	/**
	 * Remove a post from the log.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function post_delete( WP_REST_Request $request ) {
		if ( ! Posts::remove( absint( $request->get_param( 'id' ) ) ) ) {
			return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'That post was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return $this->posts();
	}

	/**
	 * Add the AI Writer link to an article place.
	 *
	 * @param array $place Place.
	 * @return array
	 */
	public function with_writer( $place ) {
		$place['writer_url'] = Drafts::writer_url( $place );
		return $place;
	}

	/**
	 * Focus for the screen.
	 *
	 * @return array
	 */
	private function focus_view() {
		$saved = Focus::get();
		return array(
			'focus'       => $saved['focus'],
			'topics'      => $saved['topics'],
			'updated_at'  => $saved['updated_at'],
			'suggestions' => Focus::suggestions(),
			'offers'      => Focus::topics(),
			'can_edit'    => $this->can_manage(),
		);
	}

	/**
	 * The context for the screen, with the summary's estimated cost.
	 *
	 * @param array $state Context::get().
	 * @return array
	 */
	private function view( array $state ) {
		$state['estimate'] = Context::estimate( Module::ID );
		$state['can_edit'] = $this->can_manage();
		return $state;
	}
}
