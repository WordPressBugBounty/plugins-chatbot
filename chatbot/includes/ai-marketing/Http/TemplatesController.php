<?php
/**
 * REST: email templates.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Domain\CampaignService;
use QuantumCloud\WPBot\CRM\Domain\TemplateRepository;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Contract #24–27. Create is Pro-only; editing a built-in template saves a custom copy;
 * free users cannot edit or delete Pro templates.
 */
class TemplatesController extends RestController {

	const CATEGORIES = array( 'General', 'Marketing', 'Transactional', 'Notification', 'Welcome', 'Newsletter', 'Promotional' );

	/**
	 * Repository.
	 *
	 * @var TemplateRepository
	 */
	private $templates;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->templates = new TemplateRepository();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$admin = array( $this, 'admin_permission' );
		register_rest_route(
			self::NS,
			'/templates',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/templates/(?P<id>\d+)',
			array(
				'args' => $this->id_arg(),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $admin,
				),
			)
		);
	}

	/**
	 * GET /templates → {items,total,pages}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$rows = $this->templates->all(
			array(
				'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'category' => sanitize_text_field( (string) $request->get_param( 'category' ) ),
			)
		);
		return $this->items_response( array_map( array( $this, 'format' ), $rows ), count( $rows ) );
	}

	/**
	 * GET /templates/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$row = $this->templates->find( (int) $request['id'] );
		return $row ? rest_ensure_response( $this->format( $row ) ) : $this->not_found();
	}

	/**
	 * POST /templates.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$data = $this->fields( $this->body( $request ) );
		if ( empty( $data['name'] ) ) {
			return $this->error( 'wpbot_crm_template_invalid', __( 'Give the template a name.', 'chatbot' ) );
		}
		$data['is_builtin'] = false;
		$id                 = $this->templates->insert( $data );
		if ( ! $id ) {
			return $this->error( 'wpbot_crm_template_save_failed', __( 'The template could not be saved.', 'chatbot' ), 500 );
		}
		$response = rest_ensure_response( $this->format( $this->templates->find( $id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * PUT /templates/{id}. Built-in templates are never modified: a custom copy is saved instead.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$row = $this->templates->find( (int) $request['id'] );
		if ( ! $row ) {
			return $this->not_found();
		}
		$data = $this->fields( $this->body( $request ) );
		if ( array_key_exists( 'name', $data ) && '' === $data['name'] ) {
			return $this->error( 'wpbot_crm_template_invalid', __( 'Give the template a name.', 'chatbot' ) );
		}
		if ( $row['is_builtin'] ) {
			$copy = array_merge(
				array(
					'name'         => $row['name'],
					'category'     => $row['category'],
					'subject'      => $row['subject'],
					'content_html' => $row['content_html'],
					'design'       => $row['design'],
				),
				$data,
				array( 'is_builtin' => false )
			);
			$id = $this->templates->insert( $copy );
			if ( ! $id ) {
				return $this->error( 'wpbot_crm_template_save_failed', __( 'The template could not be saved.', 'chatbot' ), 500 );
			}
			return rest_ensure_response( $this->format( $this->templates->find( $id ) ) );
		}
		$this->templates->update( (int) $row['id'], $data );
		return rest_ensure_response( $this->format( $this->templates->find( (int) $row['id'] ) ) );
	}

	/**
	 * DELETE /templates/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$row = $this->templates->find( (int) $request['id'] );
		if ( ! $row ) {
			return $this->not_found();
		}
		$this->templates->delete( (int) $row['id'] );
		return rest_ensure_response(
			array(
				'success' => true,
				'deleted' => true,
				'id'      => (int) $row['id'],
			)
		);
	}

	/**
	 * API shape with aliases (title+name, subject+description, date string).
	 *
	 * @param array $row Template row.
	 * @return array
	 */
	public function format( array $row ) {
		$date = Dates::mysql( $row['created_at'] );
		return array(
			'id'          => (int) $row['id'],
			'title'       => (string) $row['name'],
			'name'        => (string) $row['name'],
			'category'    => (string) $row['category'],
			'subject'     => (string) $row['subject'],
			'description' => (string) $row['subject'],
			'content'     => (string) $row['content_html'],
			'is_builtin'  => (bool) $row['is_builtin'],
			'type'        => $row['is_builtin'] ? 'system' : 'custom',
			'date'        => $date,
			'created_at'  => $date,
			'updated_at'  => Dates::mysql( $row['updated_at'] ),
		);
	}

	/**
	 * Request → columns (name|title, description|subject, content, category).
	 *
	 * @param array $input Body.
	 * @return array
	 */
	private function fields( array $input ) {
		$data = array();
		foreach ( array( 'name', 'title' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) {
				$data['name'] = trim( sanitize_text_field( (string) $input[ $key ] ) );
				break;
			}
		}
		foreach ( array( 'description', 'subject' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) {
				$data['subject'] = sanitize_text_field( (string) $input[ $key ] );
				break;
			}
		}
		foreach ( array( 'content', 'content_html' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) {
				$data['content_html'] = CampaignService::kses( (string) $input[ $key ] );
				break;
			}
		}
		if ( isset( $input['category'] ) && is_scalar( $input['category'] ) ) {
			$data['category'] = $this->category( (string) $input['category'] );
		} elseif ( ! empty( $data['name'] ) ) {
			$data['category'] = 'Marketing';
		}
		return $data;
	}

	/**
	 * Canonical capitalised category (unknown → Marketing).
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	private function category( $value ) {
		foreach ( self::CATEGORIES as $category ) {
			if ( strtolower( $category ) === strtolower( trim( $value ) ) ) {
				return $category;
			}
		}
		return 'Marketing';
	}

	/**
	 * JSON body or params.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private function body( $request ) {
		$json = $request->get_json_params();
		return is_array( $json ) && ! empty( $json ) ? $json : (array) $request->get_params();
	}

	/**
	 * 404.
	 *
	 * @return \WP_Error
	 */
	private function not_found() {
		return $this->error( 'wpbot_crm_template_not_found', __( 'Template not found.', 'chatbot' ), 404 );
	}
}
