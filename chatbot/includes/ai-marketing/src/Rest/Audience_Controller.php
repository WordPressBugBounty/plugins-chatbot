<?php
/**
 * REST: contacts, lists and tags.
 *
 * Reading needs wpbot_marketing_view; changing needs wpbot_marketing_use.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Rest;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Audience\Importer;
use QuantumCloud\AiMarketing\Audience\Lists;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Audience routes.
 */
final class Audience_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$view   = Rest_Controller::can( 'wpbot_marketing_view' );
		$use    = Rest_Controller::can( 'wpbot_marketing_use' );
		$id     = '(?P<id>\d+)';
		$routes = array(
			array( '/contacts', WP_REST_Server::READABLE, 'contacts', $view ),
			array( '/contacts', WP_REST_Server::CREATABLE, 'create_contact', $use ),
			array( '/contacts/counts', WP_REST_Server::READABLE, 'contact_counts', $view ),
			array( '/contacts/bulk', WP_REST_Server::CREATABLE, 'bulk', $use ),
			array( '/contacts/import/preview', WP_REST_Server::CREATABLE, 'import_preview', $use ),
			array( '/contacts/import', WP_REST_Server::CREATABLE, 'import', $use ),
			array( '/contacts/' . $id, WP_REST_Server::READABLE, 'contact', $view ),
			array( '/contacts/' . $id, 'PATCH', 'update_contact', $use ),
			array( '/contacts/' . $id, WP_REST_Server::DELETABLE, 'delete_contact', $use ),
			array( '/lists', WP_REST_Server::READABLE, 'lists', $view ),
			array( '/lists', WP_REST_Server::CREATABLE, 'create_list', $use ),
			array( '/lists/' . $id, 'PATCH', 'update_list', $use ),
			array( '/lists/' . $id, WP_REST_Server::DELETABLE, 'delete_list', $use ),
		);
		foreach ( $routes as $route ) {
			register_rest_route(
				Rest_Controller::NS,
				$route[0],
				array(
					'methods'             => $route[1],
					'callback'            => array( __CLASS__, $route[2] ),
					'permission_callback' => $route[3],
				)
			);
		}
	}

	/**
	 * GET /contacts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function contacts( WP_REST_Request $request ) {
		return Contacts::query( (array) $request->get_query_params() );
	}

	/**
	 * GET /contacts/counts.
	 *
	 * @return array
	 */
	public static function contact_counts() {
		return Contacts::counts();
	}

	/**
	 * GET /contacts/{id}: the contact with lists, tags and consent.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function contact( WP_REST_Request $request ) {
		$row = Contacts::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'qcld_mk_not_found', __( 'Contact not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$contact               = Contacts::shape( $row, true );
		$contact['identities'] = \QuantumCloud\AiMarketing\Audience\Identities::of_contact( (int) $row['id'] );
		return $contact;
	}

	/**
	 * POST /contacts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_contact( WP_REST_Request $request ) {
		$id = Contacts::create_by_owner( self::body( $request ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return new WP_REST_Response( Contacts::shape( Contacts::get( $id ), true ), 201 );
	}

	/**
	 * PATCH /contacts/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function update_contact( WP_REST_Request $request ) {
		$row = Contacts::update( (int) $request['id'], self::body( $request ) );
		return is_wp_error( $row ) ? $row : Contacts::shape( $row, true );
	}

	/**
	 * DELETE /contacts/{id}: erase the contact (GDPR).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function delete_contact( WP_REST_Request $request ) {
		$done = Contacts::delete( (int) $request['id'] );
		return is_wp_error( $done ) ? $done : array( 'deleted' => true );
	}

	/**
	 * POST /contacts/bulk: ids, action (add_list, remove_list, unsubscribe, delete), group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function bulk( WP_REST_Request $request ) {
		$body   = self::body( $request );
		$action = isset( $body['action'] ) ? sanitize_key( (string) $body['action'] ) : '';
		if ( ! in_array( $action, array( 'add_list', 'remove_list', 'unsubscribe', 'delete' ), true ) ) {
			return new WP_Error( 'qcld_mk_action', __( 'Choose an action.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$ids = isset( $body['ids'] ) && is_array( $body['ids'] ) ? $body['ids'] : array();
		return array( 'changed' => Contacts::bulk( $ids, $action, isset( $body['group'] ) ? (int) $body['group'] : 0 ) );
	}

	/**
	 * GET /lists?type=list|tag.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function lists( WP_REST_Request $request ) {
		return Lists::all( (string) $request->get_param( 'type' ), (string) $request->get_param( 'search' ) );
	}

	/**
	 * POST /lists: type, name, description.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_list( WP_REST_Request $request ) {
		$body = self::body( $request );
		$id   = Lists::create( isset( $body['type'] ) ? (string) $body['type'] : 'list', isset( $body['name'] ) ? (string) $body['name'] : '', isset( $body['description'] ) ? (string) $body['description'] : '' );
		return is_wp_error( $id ) ? $id : new WP_REST_Response( Lists::get( $id ), 201 );
	}

	/**
	 * PATCH /lists/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function update_list( WP_REST_Request $request ) {
		return Lists::update( (int) $request['id'], self::body( $request ) );
	}

	/**
	 * DELETE /lists/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function delete_list( WP_REST_Request $request ) {
		$done = Lists::delete( (int) $request['id'] );
		return is_wp_error( $done ) ? $done : array( 'deleted' => true );
	}

	/**
	 * POST /contacts/import/preview (multipart, field "file").
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function import_preview( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_Error( 'qcld_mk_upload', __( 'Choose a CSV file.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$name = isset( $files['file']['name'] ) ? strtolower( (string) $files['file']['name'] ) : '';
		if ( ! preg_match( '/\.(csv|txt|tsv)$/', $name ) ) {
			return new WP_Error( 'qcld_mk_upload', __( 'Upload a .csv file.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return Importer::preview( $files['file'] );
	}

	/**
	 * POST /contacts/import: one batch. source csv (token, mapping), users or woocommerce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function import( WP_REST_Request $request ) {
		$body    = self::body( $request );
		$source  = isset( $body['source'] ) ? sanitize_key( (string) $body['source'] ) : 'csv';
		$options = array(
			'offset'   => isset( $body['offset'] ) ? (int) $body['offset'] : 0,
			'consent'  => ! empty( $body['consent'] ),
			'list_ids' => isset( $body['list_ids'] ) ? array_map( 'intval', (array) $body['list_ids'] ) : array(),
			'tag_ids'  => isset( $body['tag_ids'] ) ? array_map( 'intval', (array) $body['tag_ids'] ) : array(),
		);
		if ( in_array( $source, array( 'users', 'woocommerce' ), true ) ) {
			return Importer::run_source( $source, $options );
		}
		$mapping = array();
		foreach ( isset( $body['mapping'] ) ? (array) $body['mapping'] : array() as $index => $field ) {
			$field = (string) $field;
			if ( 0 === strpos( $field, 'attribute:' ) ) {
				$key   = sanitize_key( substr( $field, 10 ) );
				$field = '' !== $key ? 'attribute:' . $key : 'ignore';
			} elseif ( ! in_array( $field, Importer::FIELDS, true ) ) {
				$field = 'ignore';
			}
			$mapping[ (int) $index ] = $field;
		}
		return Importer::run( isset( $body['token'] ) ? (string) $body['token'] : '', $mapping, $options );
	}

	/**
	 * JSON body, falling back to body params.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function body( WP_REST_Request $request ) {
		$json = $request->get_json_params();
		return is_array( $json ) ? $json : (array) $request->get_body_params();
	}
}
