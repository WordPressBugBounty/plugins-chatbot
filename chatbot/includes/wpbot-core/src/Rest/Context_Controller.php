<?php
/**
 * REST routes for WPBot > Business Context (wpbot-core/v1/context).
 *
 * Permission section "context" (WPBot > Permissions > Business Context):
 * View sees the screen, Use reads the site, builds the AI summary, edits and
 * saves it, Manage settings switches sources on or off.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\AI\AI_Readiness;
use QuantumCloud\WPBotCore\Context\Context;
use QuantumCloud\WPBotCore\Context\Context_Documents;
use QuantumCloud\WPBotCore\Context\Context_Sources;
use QuantumCloud\WPBotCore\Context\Knowledge;
use QuantumCloud\WPBotCore\Permissions\Sections;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Business Context routes.
 */
final class Context_Controller extends Rest_Controller {

	const SECTION = 'context';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/context'                                => array( WP_REST_Server::READABLE, 'view', 'view' ),
			'/context/scan'                           => array( WP_REST_Server::CREATABLE, 'scan', 'use' ),
			'/context/summarize'                      => array( WP_REST_Server::CREATABLE, 'summarize', 'use' ),
			'/context/save'                           => array( WP_REST_Server::CREATABLE, 'save', 'use' ),
			'/context/settings'                       => array( WP_REST_Server::CREATABLE, 'settings', 'settings' ),
			'/context/knowledge'                      => array( WP_REST_Server::CREATABLE, 'knowledge', 'use' ),
			'/context/docs'                           => array( WP_REST_Server::CREATABLE, 'doc_upload', 'use' ),
			'/context/docs/(?P<id>[A-Za-z0-9]{6,32})' => array( WP_REST_Server::CREATABLE, 'doc_edit', 'use' ),
			'/context/docs/(?P<id>[A-Za-z0-9]{6,32})/summarize' => array( WP_REST_Server::CREATABLE, 'doc_summarize', 'use' ),
			'/context/docs/(?P<id>[A-Za-z0-9]{6,32})/delete' => array( WP_REST_Server::CREATABLE, 'doc_delete', 'use' ),
		);
		foreach ( $routes as $route => $def ) {
			$level = $def[2];
			register_rest_route(
				self::NAMESPACE_V1,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => function () use ( $level ) {
						return $this->can( self::SECTION, $level );
					},
				)
			);
		}
	}

	/**
	 * Everything the screen shows. The free site scan runs on the first visit.
	 *
	 * @return \WP_REST_Response
	 */
	public function view() {
		$state = Context::get();
		if ( '' === $state['scanned_at'] && $state['settings']['site'] ) {
			$fresh = Context::refresh_facts();
			$state = is_wp_error( $fresh ) ? $state : $fresh;
		}
		return rest_ensure_response( self::shape( $state ) );
	}

	/**
	 * Read the sources again (free, no AI).
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function scan() {
		$state = Context::refresh_facts();
		return is_wp_error( $state ) ? $state : rest_ensure_response( self::shape( $state ) );
	}

	/**
	 * Build the AI summary (the screen showed the cost first).
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function summarize() {
		$module = Context::ai_module();
		$ready  = '' !== $module ? AI_Readiness::check( $module ) : null;
		if ( ! is_array( $ready ) || empty( $ready['ready'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_context_no_ai', is_array( $ready ) && ! empty( $ready['message'] ) ? (string) $ready['message'] : __( 'Set up AI first: add an AI key in AI Settings, or in the AI settings of AI Writer or SEO & AI Search.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$state = Context::summarize( $module );
		return is_wp_error( $state ) ? $state : rest_ensure_response( self::shape( $state ) );
	}

	/**
	 * Save the owner's summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$summary = $request->get_param( 'summary' );
		if ( ! is_array( $summary ) || null === Context::clean_summary( $summary ) ) {
			return new WP_Error( 'qcld_wpbot_core_invalid', __( 'Describe the business or list what it offers.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( self::shape( Context::save_summary( $summary ) ) );
	}

	/**
	 * Switch sources on or off, and "Keep it up to date".
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function settings( WP_REST_Request $request ) {
		$changes = array();
		foreach ( array_keys( Context::DEFAULTS ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$changes[ $key ] = rest_sanitize_boolean( $request->get_param( $key ) );
			}
		}
		$state = Context::save_settings( $changes );
		// A source switched on is read at once.
		if ( in_array( true, $changes, true ) ) {
			$fresh = Context::refresh_facts();
			$state = is_wp_error( $fresh ) ? $state : $fresh;
		}
		return rest_ensure_response( self::shape( $state ) );
	}

	/**
	 * Build the chatbot knowledge base from the pages the scan found (a job).
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function knowledge() {
		if ( '' === Context::get()['scanned_at'] ) {
			Context::refresh_facts();
		}
		$job = Knowledge::build( self::SECTION, Context::post_ids() );
		return is_wp_error( $job ) ? $job : rest_ensure_response( array( 'job_id' => (int) $job ) );
	}

	/**
	 * Upload one document: read, summarised by AI (cost shown before), file deleted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function doc_upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_doc_none', __( 'Choose a file to upload.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$module = $this->ready_module();
		if ( is_wp_error( $module ) ) {
			return $module;
		}
		$doc = Context_Documents::upload( $files['file'], $module );
		return is_wp_error( $doc ) ? $doc : rest_ensure_response( self::shape( Context::get() ) );
	}

	/**
	 * Save the owner's edit of a document summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function doc_edit( WP_REST_Request $request ) {
		$doc = Context_Documents::edit( (string) $request['id'], (string) $request->get_param( 'summary' ) );
		return is_wp_error( $doc ) ? $doc : rest_ensure_response( self::shape( Context::get() ) );
	}

	/**
	 * Summarise a text document again.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function doc_summarize( WP_REST_Request $request ) {
		$module = $this->ready_module();
		if ( is_wp_error( $module ) ) {
			return $module;
		}
		$doc = Context_Documents::resummarize( (string) $request['id'], $module );
		return is_wp_error( $doc ) ? $doc : rest_ensure_response( self::shape( Context::get() ) );
	}

	/**
	 * Delete a document.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function doc_delete( WP_REST_Request $request ) {
		if ( ! Context_Documents::delete( (string) $request['id'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_not_found', __( 'This document was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::shape( Context::get() ) );
	}

	/**
	 * The module whose AI is ready, or why there is none.
	 *
	 * @return string|WP_Error
	 */
	private function ready_module() {
		$module = Context::ai_module();
		$ready  = '' !== $module ? AI_Readiness::check( $module ) : null;
		if ( ! is_array( $ready ) || empty( $ready['ready'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_context_no_ai', is_array( $ready ) && ! empty( $ready['message'] ) ? (string) $ready['message'] : __( 'Set up AI first: add an AI key in AI Settings, or in the AI settings of AI Writer or SEO & AI Search.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return $module;
	}

	/**
	 * The state for the screen.
	 *
	 * @param array $state Context::get().
	 * @return array
	 */
	public static function shape( array $state ) {
		$module = Context::ai_module();
		$ready  = '' !== $module ? AI_Readiness::check( $module ) : null;
		$labels = Sections::all();
		return array(
			'summary'    => $state['summary'],
			'edited'     => $state['edited'],
			'summary_at' => $state['summary_at'],
			'scanned_at' => $state['scanned_at'],
			'stale'      => $state['stale'],
			'settings'   => $state['settings'],
			'saved'      => is_array( $state['summary'] ),
			'sources'    => Context_Sources::all( $state ),
			'ai'         => array(
				'module'   => $module,
				'label'    => '' !== $module && isset( $labels[ $module ]['label'] ) ? (string) $labels[ $module ]['label'] : '',
				'ready'    => is_array( $ready ) && ! empty( $ready['ready'] ),
				'message'  => is_array( $ready ) && empty( $ready['ready'] ) ? (string) $ready['message'] : '',
				'url'      => is_array( $ready ) && ! empty( $ready['url'] ) ? (string) $ready['url'] : '',
				'estimate' => '' !== $module ? Context::estimate( $module ) : 0.0,
			),
			'documents'  => array(
				'items'     => Context_Documents::all(),
				'max'       => Context_Documents::MAX_DOCS,
				'estimate'  => '' !== $module ? Context_Documents::estimate( $module ) : 0.0,
				'reads_pdf' => '' !== $module && Context_Documents::reads_pdf( $module ),
			),
			'can'        => array(
				'use'      => current_user_can( Sections::cap( self::SECTION, 'use' ) ),
				'settings' => current_user_can( Sections::cap( self::SECTION, 'settings' ) ),
			),
		);
	}
}
