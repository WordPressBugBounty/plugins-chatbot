<?php
/**
 * Automations REST routes (API-CONTRACT #28–33).
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Domain\AutomationEngine;
use QuantumCloud\WPBot\CRM\Domain\AutomationRepository;
use QuantumCloud\WPBot\CRM\Domain\RunRepository;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * /automations[/{id}] and PUT /automations/{id}/steps (body key `sequences`).
 */
class AutomationsController extends RestController {

	/**
	 * UI action aliases → stored action_name.
	 *
	 * @var array<string,string>
	 */
	const ALIASES = array(
		'add_list'    => 'add_to_list',
		'remove_list' => 'remove_from_list',
	);

	/**
	 * Automations repository.
	 *
	 * @var AutomationRepository
	 */
	protected $automations;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->automations = new AutomationRepository();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$admin = array( $this, 'admin_permission' );
		register_rest_route(
			self::NS,
			'/automations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/automations/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $admin,
					'args'                => $this->id_arg(),
				),
				array(
					'methods'             => 'PUT, PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $admin,
					'args'                => $this->id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'destroy' ),
					'permission_callback' => $admin,
					'args'                => $this->id_arg(),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/automations/(?P<id>\d+)/steps',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'replace_steps' ),
				'permission_callback' => $admin,
				'args'                => $this->id_arg(),
			)
		);
	}

	/**
	 * List + overview.
	 *
	 * @return \WP_REST_Response
	 */
	public function index() {
		$items = array();
		foreach ( $this->automations->all() as $automation ) {
			$items[] = array(
				'id'              => (int) $automation['id'],
				'title'           => $automation['name'],
				'name'            => $automation['name'],
				'status'          => $automation['status'],
				'trigger_name'    => $automation['trigger_type'],
				'enrolled_count'  => (int) $automation['enrolled'],
				'completed_count' => (int) $automation['completed'],
				'steps_count'     => count( AutomationEngine::steps( $automation ) ),
				'created_at'      => Dates::iso( $automation['created_at'] ),
			);
		}
		$response = $this->items_response( $items, count( $items ) );
		$data     = $response->get_data();

		$data['overview'] = $this->automations->overview();
		$response->set_data( $data );
		return $response;
	}

	/**
	 * One automation with its steps (`sequences`) in execution order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function show( $request ) {
		$automation = $this->automations->find( (int) $request['id'] );
		return $automation ? $this->shape( $automation ) : $this->not_found();
	}

	/**
	 * Create: title (required), trigger_name, status. Returns the automation with `id`.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( $request ) {
		$data = $this->input( $request, true );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$data['steps']          = array();
		$data['trigger_config'] = array();
		$id                     = $this->automations->insert( $data );
		if ( ! $id ) {
			return $this->error( 'wpbot_crm_db_error', __( 'The automation could not be saved. Please try again.', 'chatbot' ), 500 );
		}
		$response = new \WP_REST_Response( $this->shape( $this->automations->find( $id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Update title / trigger_name / status (only the keys sent).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function update( $request ) {
		$id = (int) $request['id'];
		if ( ! $this->automations->find( $id ) ) {
			return $this->not_found();
		}
		$data = $this->input( $request, false );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( $data ) {
			$this->automations->update( $id, $data );
		}
		return $this->shape( $this->automations->find( $id ) );
	}

	/**
	 * Delete an automation and its runs.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function destroy( $request ) {
		$id = (int) $request['id'];
		if ( ! $this->automations->find( $id ) ) {
			return $this->not_found();
		}
		$this->automations->delete( $id );
		return array(
			'success' => true,
			'deleted' => true,
			'id'      => $id,
		);
	}

	/**
	 * Full replace of the steps: order = array order; sent ids are kept, missing ids get max+1.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function replace_steps( $request ) {
		$id         = (int) $request['id'];
		$automation = $this->automations->find( $id );
		if ( ! $automation ) {
			return $this->not_found();
		}
		$raw = $request->get_param( 'sequences' );
		if ( null === $raw ) {
			$raw = $request->get_param( 'steps' );
		}
		if ( null !== $raw && ! is_array( $raw ) ) {
			return $this->error( 'wpbot_crm_invalid_steps', __( 'The automation steps could not be read.', 'chatbot' ) );
		}

		$old   = AutomationEngine::steps( $automation );
		$steps = array();
		foreach ( (array) $raw as $position => $item ) {
			$step = $this->normalize_step( $item );
			if ( is_wp_error( $step ) ) {
				/* translators: 1: step number, 2: reason. */
				return $this->error( 'wpbot_crm_invalid_step', sprintf( __( 'Step %1$d: %2$s', 'chatbot' ), (int) $position + 1, $step->get_error_message() ) );
			}
			$steps[] = $step;
		}

		// Keep sent ids (unique), assign max+1 to the rest.
		$max = 0;
		foreach ( array_merge( $old, $steps ) as $step ) {
			$max = max( $max, (int) $step['id'] );
		}
		$used = array();
		foreach ( $steps as $i => $step ) {
			if ( $step['id'] <= 0 || isset( $used[ $step['id'] ] ) ) {
				$steps[ $i ]['id'] = ++$max;
			}
			$used[ $steps[ $i ]['id'] ] = true;
		}

		$this->automations->update( $id, array( 'steps' => $steps ) );
		AutomationEngine::remap_runs( $id, $old, $steps );
		return $this->shape( $this->automations->find( $id ) );
	}

	/**
	 * Validate and sanitise one posted step.
	 *
	 * @param mixed $item Raw step.
	 * @return array|\WP_Error
	 */
	protected function normalize_step( $item ) {
		if ( ! is_array( $item ) ) {
			return new \WP_Error( 'invalid', __( 'Each step must be an object.', 'chatbot' ) );
		}
		$action = isset( $item['action_name'] ) ? sanitize_key( (string) $item['action_name'] ) : '';
		if ( '' === $action && isset( $item['type'] ) ) {
			$action = sanitize_key( (string) $item['type'] );
		}
		$action = isset( self::ALIASES[ $action ] ) ? self::ALIASES[ $action ] : $action;
		if ( ! in_array( $action, AutomationEngine::ACTIONS, true ) ) {
			return new \WP_Error( 'invalid', __( 'Unknown step type.', 'chatbot' ) );
		}
		$delay = isset( $item['delay_value'] ) ? $item['delay_value'] : ( isset( $item['delay'] ) ? $item['delay'] : 0 );
		$unit  = isset( $item['delay_unit'] ) ? strtolower( (string) $item['delay_unit'] ) : 'minutes';

		return array(
			'id'          => isset( $item['id'] ) ? absint( $item['id'] ) : 0,
			'action_name' => $action,
			'delay_value' => is_numeric( $delay ) ? max( 0, (int) $delay ) : 0,
			'delay_unit'  => in_array( $unit, AutomationEngine::UNITS, true ) ? $unit : 'minutes',
			'settings'    => $this->sanitize_settings( isset( $item['settings'] ) && is_array( $item['settings'] ) ? $item['settings'] : array() ),
		);
	}

	/**
	 * Sanitise step settings: email bodies keep safe HTML, URLs are cleaned, the rest is text.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	protected function sanitize_settings( array $settings ) {
		$out = array();
		foreach ( $settings as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			if ( in_array( $key, array( 'body', 'email_body' ), true ) ) {
				$out[ $key ] = wp_kses_post( (string) ( is_scalar( $value ) ? $value : '' ) );
			} elseif ( 'url' === $key ) {
				$out[ $key ] = esc_url_raw( (string) ( is_scalar( $value ) ? $value : '' ), array( 'http', 'https' ) );
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = map_deep( $value, 'sanitize_text_field' );
			} elseif ( is_bool( $value ) || is_int( $value ) ) {
				$out[ $key ] = $value;
			} else {
				$out[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $out;
	}

	/**
	 * Read title / trigger_name / status from the request.
	 *
	 * @param \WP_REST_Request $request  Request.
	 * @param bool             $creating Title required and defaults applied.
	 * @return array|\WP_Error Columns to write.
	 */
	protected function input( $request, $creating ) {
		$data  = array();
		$title = $request->get_param( 'title' );
		if ( null === $title ) {
			$title = $request->get_param( 'name' );
		}
		if ( null !== $title || $creating ) {
			$title = is_scalar( $title ) ? trim( sanitize_text_field( (string) $title ) ) : '';
			if ( '' === $title ) {
				return $this->error( 'wpbot_crm_missing_title', __( 'Please give the automation a name.', 'chatbot' ) );
			}
			$data['name'] = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 190 ) : substr( $title, 0, 190 );
		}

		$trigger = $request->get_param( 'trigger_name' );
		if ( null !== $trigger || $creating ) {
			$trigger = sanitize_key( (string) $trigger );
			if ( '' !== $trigger && ! in_array( $trigger, AutomationEngine::TRIGGERS, true ) ) {
				return $this->error( 'wpbot_crm_invalid_trigger', __( 'Please choose a valid trigger for this automation.', 'chatbot' ) );
			}
			$data['trigger_type'] = $trigger;
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status || $creating ) {
			$status = sanitize_key( (string) $status );
			if ( 'active' === $status ) {
				$status = 'published';
			}
			$data['status'] = in_array( $status, AutomationRepository::STATUSES, true ) ? $status : 'draft';
		}
		return $data;
	}

	/**
	 * Automation as returned by GET /automations/{id}.
	 *
	 * @param array $automation Automation row.
	 * @return array
	 */
	protected function shape( $automation ) {
		$sequences = array();
		foreach ( AutomationEngine::steps( (array) $automation ) as $step ) {
			$step['type'] = 'action';
			$sequences[]  = $step;
		}
		$runs = ( new RunRepository() )->count_by_status( (int) $automation['id'] );
		return array(
			'id'              => (int) $automation['id'],
			'title'           => $automation['name'],
			'name'            => $automation['name'],
			'trigger_name'    => $automation['trigger_type'],
			'status'          => $automation['status'],
			'type'            => 'funnel',
			'sequences'       => $sequences,
			'enrolled_count'  => (int) $automation['enrolled'],
			'completed_count' => (int) $automation['completed'],
			'active_count'    => (int) $runs['waiting'],
			'created_at'      => Dates::iso( $automation['created_at'] ),
			'updated_at'      => Dates::iso( $automation['updated_at'] ),
		);
	}

	/**
	 * 404.
	 *
	 * @return \WP_Error
	 */
	protected function not_found() {
		return $this->error( 'wpbot_crm_not_found', __( 'Automation not found.', 'chatbot' ), 404 );
	}
}
