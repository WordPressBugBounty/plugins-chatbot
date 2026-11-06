<?php
/**
 * CR-16 F3: REST routes for original research. The owner uploads a CSV,
 * picks a CSV from the chatbot knowledge, or (WPBot Pro) a dataset built
 * from activity; AI Writer computes the findings and keeps them for the
 * owner to approve. Nothing is stored in the Media Library.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Formats\Research_Data;
use QuantumCloud\AiWriter\Module;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Research routes.
 */
final class Research_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns = Settings_Controller::NAMESPACE_V1;
		register_rest_route(
			$ns,
			'/write/research/sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'sources' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			$ns,
			'/write/research/file',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'file' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			$ns,
			'/write/research/source',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'source' ),
				'permission_callback' => array( $this, 'can_read_site_data' ),
				'args'                => array(
					'type' => array(
						'type'     => 'string',
						'enum'     => array( 'knowledge', 'pro' ),
						'required' => true,
					),
					'id'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/write/research/(?P<id>[a-f0-9]{16})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
	}

	/**
	 * Writers may analyse their own uploads.
	 *
	 * @return bool
	 */
	public function can_use() {
		return current_user_can( Module::cap( 'use' ) );
	}

	/**
	 * Research from the site's own records (knowledge, chats, orders,
	 * Search Console) needs the Settings level too.
	 *
	 * @return bool
	 */
	public function can_read_site_data() {
		return current_user_can( Module::cap( 'use' ) ) && current_user_can( Module::cap( 'settings' ) );
	}

	/**
	 * GET where research can come from.
	 *
	 * @return array knowledge (files), pro (sources), min, site_data (bool).
	 */
	public function sources() {
		$site = $this->can_read_site_data();
		/**
		 * Research sources built from activity (WPBot Pro: chats, orders and
		 * reviews, Search Console). Each: id, label, help, available, reason.
		 * Their tables come from the qcld_wpbot_writer/research_dataset filter.
		 *
		 * @param array[] $sources Sources.
		 */
		$pro = $site ? (array) apply_filters( 'qcld_wpbot_writer/research_sources', array() ) : array();
		return array(
			'knowledge' => $site ? Research_Data::knowledge_files() : array(),
			'pro'       => array_values( $pro ),
			'min'       => Research_Data::min_group(),
			'site_data' => $site,
		);
	}

	/**
	 * POST an uploaded CSV: read, analyse, delete.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function file( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_research_nofile', __( 'Choose a CSV file.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$file = $files['file'];
		if ( ! empty( $file['size'] ) && (int) $file['size'] > Research_Data::MAX_BYTES ) {
			return new WP_Error( 'qcld_wpbot_writer_research_big', __( 'This file is larger than 5 MB.', 'chatbot' ), array( 'status' => 413 ) );
		}
		$name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'tsv', 'txt' ), true ) ) {
			return new WP_Error( 'qcld_wpbot_writer_research_type', __( 'Use a CSV file (comma or tab separated, first row = column names). Save a spreadsheet as CSV first.', 'chatbot' ), array( 'status' => 415 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$moved = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array( $ext => 'tsv' === $ext ? 'text/tab-separated-values' : ( 'csv' === $ext ? 'text/csv' : 'text/plain' ) ),
			)
		);
		if ( ! is_array( $moved ) || ! empty( $moved['error'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_research_upload', is_array( $moved ) && ! empty( $moved['error'] ) ? (string) $moved['error'] : __( 'The file could not be uploaded.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$text = (string) file_get_contents( $moved['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload, read once.
		wp_delete_file( $moved['file'] );
		$table = Research_Data::parse_csv( $text, $name );
		return is_wp_error( $table ) ? $table : $this->analysed( $table, 'upload' );
	}

	/**
	 * POST a knowledge CSV or a Pro dataset.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function source( WP_REST_Request $request ) {
		$id = (string) $request['id'];
		if ( 'knowledge' === $request['type'] ) {
			$table = Research_Data::knowledge_table( $id );
			return is_wp_error( $table ) ? $table : $this->analysed( $table, 'knowledge' );
		}
		/**
		 * The table of a research source another module added (WPBot Pro).
		 * Rows must already be anonymised (no names, emails or order numbers).
		 *
		 * @param array|WP_Error|null $table label, columns, rows, weight (column, optional), unit (optional); null when unknown.
		 * @param string              $id    Source id.
		 */
		$table = apply_filters( 'qcld_wpbot_writer/research_dataset', null, $id );
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		if ( ! is_array( $table ) ) {
			return new WP_Error( 'qcld_wpbot_writer_research_source', __( 'This research source is not available.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$clean = Research_Data::table( isset( $table['label'] ) ? $table['label'] : $id, isset( $table['columns'] ) ? (array) $table['columns'] : array(), isset( $table['rows'] ) ? (array) $table['rows'] : array() );
		// Optional: a weight column (each row counts that many times) and the unit counted.
		$clean['weight'] = isset( $table['weight'] ) ? sanitize_text_field( (string) $table['weight'] ) : '';
		$clean['unit']   = isset( $table['unit'] ) ? sanitize_text_field( (string) $table['unit'] ) : '';
		return $this->analysed( $clean, $id );
	}

	/**
	 * GET an analysis this user ran (to show it again).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function get( WP_REST_Request $request ) {
		$result = Research_Data::stored( (string) $request['id'] );
		return null === $result ? new WP_Error( 'qcld_wpbot_writer_research_gone', __( 'This analysis has expired. Run it again.', 'chatbot' ), array( 'status' => 404 ) ) : $result;
	}

	/**
	 * Analyse, keep for approval, and return the result with its chart.
	 *
	 * @param array  $table  Table.
	 * @param string $source Source id.
	 * @return array|WP_Error
	 */
	private function analysed( array $table, $source ) {
		$result = Research_Data::analyse( $table, $source );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Research_Data::store( $result );
		return $result;
	}
}
