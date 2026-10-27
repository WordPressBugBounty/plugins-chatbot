<?php
/**
 * Lists and tags REST routes (API-CONTRACT #16–23).
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Domain\GroupRepository;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * /lists[/{id}] and /tags[/{id}] — one table, `type` = list|tag.
 */
class GroupsController extends RestController {

	/**
	 * Route base => group type.
	 *
	 * @var array<string,string>
	 */
	const BASES = array(
		'lists' => 'list',
		'tags'  => 'tag',
	);

	/**
	 * Groups repository.
	 *
	 * @var GroupRepository
	 */
	protected $groups;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->groups = new GroupRepository();
	}

	/**
	 * Register both route families.
	 */
	public function register_routes() {
		foreach ( self::BASES as $base => $type ) {
			register_rest_route(
				self::NS,
				'/' . $base,
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => $this->bind( 'index', $type ),
						'permission_callback' => array( $this, 'admin_permission' ),
					),
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => $this->bind( 'create', $type ),
						'permission_callback' => array( $this, 'admin_permission' ),
					),
				)
			);
			register_rest_route(
				self::NS,
				'/' . $base . '/(?P<id>\d+)',
				array(
					array(
						'methods'             => 'PUT, PATCH',
						'callback'            => $this->bind( 'update', $type ),
						'permission_callback' => array( $this, 'admin_permission' ),
						'args'                => $this->id_arg(),
					),
					array(
						'methods'             => \WP_REST_Server::DELETABLE,
						'callback'            => $this->bind( 'destroy', $type ),
						'permission_callback' => array( $this, 'admin_permission' ),
						'args'                => $this->id_arg(),
					),
				)
			);
		}
	}

	/**
	 * Callback that passes the group type to a handler.
	 *
	 * @param string $method Handler name.
	 * @param string $type   list|tag.
	 * @return callable
	 */
	protected function bind( $method, $type ) {
		$controller = $this;
		return static function ( $request ) use ( $controller, $method, $type ) {
			return $controller->$method( $request, $type );
		};
	}

	/**
	 * All groups of a type with member counts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $type    list|tag.
	 * @return \WP_REST_Response
	 */
	public function index( $request, $type ) {
		$rows  = $this->groups->all_with_counts( $type, sanitize_text_field( (string) $request->get_param( 'search' ) ) );
		$items = array_map( array( $this, 'shape' ), $rows );
		return $this->items_response( $items, count( $items ) );
	}

	/**
	 * Create a group from `title` (or `name`) and `description`.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $type    list|tag.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( $request, $type ) {
		$title = $this->title( $request );
		if ( '' === $title ) {
			return $this->error( 'wpbot_crm_missing_title', 'tag' === $type ? __( 'Please enter a tag name.', 'chatbot' ) : __( 'Please enter a list name.', 'chatbot' ) );
		}
		$id = $this->groups->create( $type, $title, $this->description( $request ) );
		if ( ! $id ) {
			return $this->error( 'wpbot_crm_db_error', __( 'It could not be saved. Please try again.', 'chatbot' ), 500 );
		}
		$response = new \WP_REST_Response( $this->shape( $this->groups->find( $id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Rename / re-describe a group. The slug stays stable so shortcodes keep working.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $type    list|tag.
	 * @return array|\WP_Error
	 */
	public function update( $request, $type ) {
		$id    = (int) $request['id'];
		$group = $this->groups->find_typed( $id, $type );
		if ( ! $group ) {
			return $this->not_found( $type );
		}
		$data = array();
		if ( null !== $request->get_param( 'title' ) || null !== $request->get_param( 'name' ) ) {
			$title = $this->title( $request );
			if ( '' === $title ) {
				return $this->error( 'wpbot_crm_missing_title', __( 'The name cannot be empty.', 'chatbot' ) );
			}
			$data['name'] = $title;
		}
		if ( null !== $request->get_param( 'description' ) ) {
			$data['description'] = $this->description( $request );
		}
		if ( $data ) {
			$this->groups->update( $id, $data );
		}
		return $this->shape( $this->groups->find( $id ) );
	}

	/**
	 * Delete a group and its memberships (contacts are kept).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $type    list|tag.
	 * @return array|\WP_Error
	 */
	public function destroy( $request, $type ) {
		$id = (int) $request['id'];
		if ( ! $this->groups->find_typed( $id, $type ) ) {
			return $this->not_found( $type );
		}
		$this->groups->delete( $id );
		return array(
			'success' => true,
			'deleted' => true,
			'id'      => $id,
		);
	}

	/**
	 * Group as returned to the UI.
	 *
	 * @param array $group Group row (optionally with subscribers_count).
	 * @return array
	 */
	public function shape( $group ) {
		$group = (array) $group;
		return array(
			'id'                => (int) $group['id'],
			'title'             => (string) $group['name'],
			'name'              => (string) $group['name'],
			'slug'              => (string) $group['slug'],
			'type'              => (string) $group['type'],
			'description'       => (string) $group['description'],
			'subscribers_count' => isset( $group['subscribers_count'] ) ? (int) $group['subscribers_count'] : $this->groups->member_count( (int) $group['id'] ),
			'created_at'        => Dates::iso( $group['created_at'] ),
		);
	}

	/**
	 * Sanitised title from `title`, else `name`.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	protected function title( $request ) {
		$title = $request->get_param( 'title' );
		if ( null === $title || '' === trim( (string) $title ) ) {
			$title = $request->get_param( 'name' );
		}
		$title = is_scalar( $title ) ? trim( sanitize_text_field( (string) $title ) ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 190 ) : substr( $title, 0, 190 );
	}

	/**
	 * Sanitised description.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	protected function description( $request ) {
		$value = $request->get_param( 'description' );
		return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
	}

	/**
	 * 404 error for a type.
	 *
	 * @param string $type list|tag.
	 * @return \WP_Error
	 */
	protected function not_found( $type ) {
		return $this->error( 'wpbot_crm_not_found', 'tag' === $type ? __( 'Tag not found.', 'chatbot' ) : __( 'List not found.', 'chatbot' ), 404 );
	}
}
