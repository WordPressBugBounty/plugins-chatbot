<?php
/**
 * Contacts REST routes (API-CONTRACT #12–15).
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Domain\ContactExporter;
use QuantumCloud\WPBot\CRM\Domain\ContactImporter;
use QuantumCloud\WPBot\CRM\Domain\ContactRepository;
use QuantumCloud\WPBot\CRM\Domain\ContactService;
use QuantumCloud\WPBot\CRM\Domain\GroupRepository;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /contacts, PUT /contacts/{id}, POST /contacts/import, GET /contacts/export.
 */
class ContactsController extends RestController {

	/**
	 * Fields a client may write.
	 *
	 * @var string[]
	 */
	const FIELDS = array( 'email', 'first_name', 'last_name', 'phone', 'company', 'website', 'status' );

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/contacts',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/contacts/(?P<id>\d+)',
			array(
				'methods'             => 'PUT, PATCH',
				'callback'            => array( $this, 'update' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => $this->id_arg(),
			)
		);
		register_rest_route(
			self::NS,
			'/contacts/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);
		register_rest_route(
			self::NS,
			'/contacts/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);
	}

	/**
	 * GET /contacts/export → { filename, csv }. The admin screen turns `csv` into a download.
	 *
	 * @return \WP_REST_Response
	 */
	public function export() {
		return rest_ensure_response(
			array(
				'filename' => 'contacts-' . gmdate( 'Y-m-d' ) . '.csv',
				'csv'      => ContactExporter::csv(),
			)
		);
	}

	/**
	 * List contacts: page, per_page (default 100), search, status, list_id, tag_id.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( $request ) {
		list( $page, $per_page ) = $this->pagination( $request, 100 );

		$status = (string) $request->get_param( 'status' );
		if ( '' !== $status ) {
			$status = ContactService::normalize_status( $status, 'none' );
		}

		$result = ( new ContactRepository() )->search(
			array(
				'page'     => $page,
				'per_page' => $per_page,
				'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'status'   => $status,
				'list_id'  => absint( $request->get_param( 'list_id' ) ),
				'tag_id'   => absint( $request->get_param( 'tag_id' ) ),
				'orderby'  => sanitize_key( (string) $request->get_param( 'orderby' ) ),
				'order'    => (string) $request->get_param( 'order' ),
			)
		);

		return $this->items_response( $this->shape_many( $result['items'] ), $result['total'], $per_page );
	}

	/**
	 * Create a contact. Duplicate email → 409.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( $request ) {
		$data = $this->input( $request );
		if ( isset( $data['status'] ) ) {
			$data['status'] = ContactService::normalize_status( $data['status'], 'subscribed' );
		}

		$service = new ContactService();
		$id      = $service->create( $data, 'manual' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$response = new \WP_REST_Response( $this->shape( $service->find( $id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Update a contact. `list_ids` / `tag_ids`, when sent, are the complete desired sets.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function update( $request ) {
		$data = $this->input( $request );
		if ( isset( $data['status'] ) && '' === trim( (string) $data['status'] ) ) {
			unset( $data['status'] );
		}
		$contact = ( new ContactService() )->update( (int) $request['id'], $data );
		if ( is_wp_error( $contact ) ) {
			return $contact;
		}
		return $this->shape( $contact );
	}

	/**
	 * Import contacts from a CSV upload, WordPress users or WooCommerce customers.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function import( $request ) {
		$files  = $request->get_file_params();
		$source = sanitize_key( (string) $request->get_param( 'source' ) );
		if ( '' === $source ) {
			$source = empty( $files['file'] ) ? '' : 'csv';
		}

		$options = array(
			'status'          => (string) $request->get_param( 'status' ),
			'update_existing' => $this->bool( $request->get_param( 'update_existing' ) ),
			'skip_test'       => null === $request->get_param( 'skip_test' ) ? true : $this->bool( $request->get_param( 'skip_test' ) ),
			'skip_disposable' => $this->bool( $request->get_param( 'skip_disposable' ) ),
			'skip_role'       => $this->bool( $request->get_param( 'skip_role' ) ),
			'list_ids'        => $this->int_list( $request->get_param( 'list_ids' ) ),
			'tag_ids'         => $this->int_list( $request->get_param( 'tag_ids' ) ),
		);

		$importer = new ContactImporter();
		switch ( $source ) {
			case 'csv':
				$path = $this->uploaded_path( $files );
				if ( is_wp_error( $path ) ) {
					return $path;
				}
				return $importer->from_csv( $path, $options );
			case 'wp_users':
				return $importer->from_wp_users( $options );
			case 'woocommerce':
				return $importer->from_woocommerce( $options );
		}
		return $this->error( 'wpbot_crm_invalid_source', __( 'Choose a CSV file, WordPress users or WooCommerce customers to import.', 'chatbot' ) );
	}

	/**
	 * Validate the uploaded `file` and return its temporary path.
	 *
	 * @param array $files File params.
	 * @return string|\WP_Error
	 */
	protected function uploaded_path( array $files ) {
		$file = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : array();
		if ( empty( $file['tmp_name'] ) || ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) ) {
			return $this->error( 'wpbot_crm_no_file', __( 'Please choose a CSV file to upload.', 'chatbot' ) );
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return $this->error( 'wpbot_crm_bad_upload', __( 'The file upload could not be verified. Please try again.', 'chatbot' ) );
		}
		$ext = strtolower( pathinfo( isset( $file['name'] ) ? (string) $file['name'] : '', PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'txt', 'tsv' ), true ) ) {
			return $this->error( 'wpbot_crm_bad_file_type', __( 'Please upload a .csv file.', 'chatbot' ) );
		}
		return (string) $file['tmp_name'];
	}

	/**
	 * Writable fields present in the request (plus list_ids / tag_ids when sent).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	protected function input( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || empty( $params ) ) {
			$params = $request->get_body_params();
		}
		$params = is_array( $params ) ? $params : array();

		$data = array();
		foreach ( self::FIELDS as $field ) {
			if ( array_key_exists( $field, $params ) && is_scalar( $params[ $field ] ) ) {
				$data[ $field ] = (string) $params[ $field ];
			}
		}
		foreach ( array( 'list_ids', 'tag_ids' ) as $key ) {
			if ( array_key_exists( $key, $params ) ) {
				$data[ $key ] = $this->int_list( $params[ $key ] );
			}
		}
		return $data;
	}

	/**
	 * Shape many contacts, loading their lists/tags in one query.
	 *
	 * @param array[] $contacts Contact rows.
	 * @return array[]
	 */
	protected function shape_many( array $contacts ) {
		$groups = ( new GroupRepository() )->groups_for_contacts( wp_list_pluck( $contacts, 'id' ) );
		$out    = array();
		foreach ( $contacts as $contact ) {
			$out[] = $this->shape( $contact, isset( $groups[ $contact['id'] ] ) ? $groups[ $contact['id'] ] : null );
		}
		return $out;
	}

	/**
	 * Contact as returned to the UI.
	 *
	 * @param array|null $contact Contact row.
	 * @param array|null $groups  ['list'=>[], 'tag'=>[]] (loaded when null).
	 * @return array
	 */
	protected function shape( $contact, $groups = null ) {
		if ( ! is_array( $contact ) ) {
			return array();
		}
		if ( null === $groups ) {
			$all    = ( new GroupRepository() )->groups_for_contacts( array( $contact['id'] ) );
			$groups = isset( $all[ $contact['id'] ] ) ? $all[ $contact['id'] ] : array();
		}
		$map = static function ( $group ) {
			return array(
				'id'    => (int) $group['id'],
				'title' => $group['name'],
				'name'  => $group['name'],
			);
		};
		$lists = isset( $groups['list'] ) ? array_map( $map, $groups['list'] ) : array();
		$tags  = isset( $groups['tag'] ) ? array_map( $map, $groups['tag'] ) : array();

		return array(
			'id'               => (int) $contact['id'],
			'email'            => $contact['email'],
			'first_name'       => $contact['first_name'],
			'last_name'        => $contact['last_name'],
			'phone'            => $contact['phone'],
			'company'          => $contact['company'],
			'website'          => $contact['website'],
			'status'           => $contact['status'],
			'source'           => $contact['source'],
			'lists'            => $lists,
			'tags'             => $tags,
			'list_ids'         => wp_list_pluck( $lists, 'id' ),
			'tag_ids'          => wp_list_pluck( $tags, 'id' ),
			'created_at'       => Dates::iso( $contact['created_at'] ),
			'updated_at'       => Dates::iso( $contact['updated_at'] ),
			'last_activity_at' => Dates::iso( $contact['last_activity_at'] ),
		);
	}
}
