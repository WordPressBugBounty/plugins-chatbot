<?php
/**
 * REST routes for CR-15 imports (Brand & Business → Business Profile):
 * fill the Persuasion Kit and Customer words from the site's pages, web
 * pages and uploaded documents. The cost comes first; nothing is saved
 * until the owner ticks items and applies them.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Business\Importer;
use QuantumCloud\AiWriter\Module;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Import routes.
 */
final class Import_Controller {

	/**
	 * Largest upload (10 MB).
	 */
	const MAX_UPLOAD = 10485760;

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		if ( ! Importer::available() ) {
			return;
		}
		$ns     = Settings_Controller::NAMESPACE_V1;
		$target = array(
			'type'     => 'string',
			'enum'     => Importer::TARGETS,
			'required' => true,
		);
		register_rest_route(
			$ns,
			'/business/import/estimate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'estimate' ),
				'permission_callback' => array( $this, 'can_import' ),
				'args'                => array(
					'sources' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 1,
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/business/import',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'waiting' ),
					'permission_callback' => array( $this, 'can_import' ),
					'args'                => array( 'target' => $target ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'source' ),
					'permission_callback' => array( $this, 'can_import' ),
					'args'                => array(
						'target' => $target,
						'source' => array(
							'type'       => 'object',
							'required'   => true,
							'properties' => array(
								'type' => array(
									'type' => 'string',
									'enum' => array( 'post', 'url' ),
								),
								'id'   => array( 'type' => 'integer' ),
								'url'  => array(
									'type'      => 'string',
									'maxLength' => 2000,
								),
							),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear' ),
					'permission_callback' => array( $this, 'can_import' ),
					'args'                => array( 'target' => $target ),
				),
			)
		);
		register_rest_route(
			$ns,
			'/business/import/file',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'file' ),
				'permission_callback' => array( $this, 'can_import' ),
				'args'                => array( 'target' => $target ),
			)
		);
		register_rest_route(
			$ns,
			'/business/import/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply' ),
				'permission_callback' => array( $this, 'can_import' ),
				'args'                => array(
					'target'   => $target,
					'ids'      => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
						'maxItems' => 200,
					),
					'quotable' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
				),
			)
		);
	}

	/**
	 * The Persuasion Kit and Customer words are edited at AI Writer's
	 * settings level, as on Business Profile; reading with AI needs Use too.
	 *
	 * @return bool
	 */
	public function can_import() {
		return current_user_can( Module::cap( 'settings' ) ) && current_user_can( Module::cap( 'use' ) );
	}

	/**
	 * POST the cost of reading N sources.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function estimate( WP_REST_Request $request ) {
		return Importer::estimate( (int) $request['sources'] );
	}

	/**
	 * GET proposals still waiting.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function waiting( WP_REST_Request $request ) {
		return array( 'items' => Importer::stored( (string) $request['target'] ) );
	}

	/**
	 * DELETE the waiting proposals.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function clear( WP_REST_Request $request ) {
		Importer::clear( (string) $request['target'] );
		return array( 'items' => array() );
	}

	/**
	 * POST read one page or address and propose items.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function source( WP_REST_Request $request ) {
		$read = Importer::read_source( (array) $request['source'] );
		if ( is_wp_error( $read ) ) {
			return $read;
		}
		return Importer::propose( (string) $request['target'], $read );
	}

	/**
	 * POST an uploaded document: read it, delete it, propose items.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function file( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_import_nofile', __( 'Choose a file to upload.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$file = $files['file'];
		if ( ! empty( $file['size'] ) && (int) $file['size'] > self::MAX_UPLOAD ) {
			return new WP_Error( 'qcld_wpbot_writer_import_big', __( 'The file is larger than 10 MB.', 'chatbot' ), array( 'status' => 413 ) );
		}
		$name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, Importer::types(), true ) ) {
			return new WP_Error( 'qcld_wpbot_writer_import_type', __( 'This kind of file can\'t be imported. Use Word (.docx), PDF, text, Markdown or CSV.', 'chatbot' ), array( 'status' => 415 ) );
		}
		$mimes = array(
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'pdf'  => 'application/pdf',
			'txt'  => 'text/plain',
			'md'   => 'text/plain',
			'csv'  => 'text/csv',
		);
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$moved = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array( $ext => $mimes[ $ext ] ),
			)
		);
		if ( ! is_array( $moved ) || ! empty( $moved['error'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_import_upload', is_array( $moved ) && ! empty( $moved['error'] ) ? (string) $moved['error'] : __( 'The file could not be uploaded.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$read = Importer::read_file( $moved['file'], $name );
		if ( is_wp_error( $read ) ) {
			return $read;
		}
		return Importer::propose( (string) $request['target'], $read );
	}

	/**
	 * POST save the ticked proposals.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function apply( WP_REST_Request $request ) {
		return Importer::apply( (string) $request['target'], (array) $request['ids'], (array) $request['quotable'] );
	}
}
