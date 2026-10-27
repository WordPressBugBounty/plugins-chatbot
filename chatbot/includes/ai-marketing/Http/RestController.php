<?php
/**
 * Base class for CRM REST controllers.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Domain\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Namespace, permissions and response helpers shared by all controllers.
 */
abstract class RestController {

	const NS = 'wpbot-crm/v1';

	/**
	 * Register the controller's routes (called on rest_api_init).
	 */
	abstract public function register_routes();

	/**
	 * Permission callback for admin routes.
	 *
	 * @return bool|\WP_Error
	 */
	public function admin_permission() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new \WP_Error( 'wpbot_crm_forbidden', __( 'You do not have permission to manage email marketing.', 'chatbot' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * `{items, total, pages}` response with X-WP-Total / X-WP-TotalPages headers.
	 *
	 * @param array $items    Items.
	 * @param int   $total    Total matching rows.
	 * @param int   $per_page Page size (0 = everything on one page).
	 * @return \WP_REST_Response
	 */
	protected function items_response( array $items, $total, $per_page = 0 ) {
		$total    = (int) $total;
		$per_page = (int) $per_page;
		$pages    = $per_page > 0 ? (int) ceil( $total / $per_page ) : ( $total > 0 ? 1 : 0 );
		$response = new \WP_REST_Response(
			array(
				'items' => array_values( $items ),
				'total' => $total,
				'pages' => $pages,
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * Build a WP_Error with an HTTP status.
	 *
	 * @param string $code    Error code (wpbot_crm_*).
	 * @param string $message User-presentable sentence.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	protected function error( $code, $message, $status = 400 ) {
		return new \WP_Error( $code, $message, array( 'status' => (int) $status ) );
	}

	/**
	 * Unique positive ints from an array, a CSV string or a single value.
	 *
	 * @param mixed $value Raw value.
	 * @return int[]
	 */
	protected function int_list( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		} elseif ( ! is_array( $value ) ) {
			$value = null === $value ? array() : array( $value );
		}
		$value = array_filter( $value, 'is_scalar' );
		return Repository::ids( $value );
	}

	/**
	 * Loose boolean: true, 1, '1', 'true', 'yes', 'on'.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	protected function bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return is_numeric( $value ) && 0 !== (int) $value;
	}

	/**
	 * page/per_page from a request (per_page 0 = all, capped at 500).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param int              $default_per_page Default page size.
	 * @return array{0:int,1:int} [ page, per_page ]
	 */
	protected function pagination( $request, $default_per_page = 0 ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = null === $request->get_param( 'per_page' ) ? (int) $default_per_page : (int) $request->get_param( 'per_page' );
		return array( $page, min( 500, max( 0, $per_page ) ) );
	}

	/**
	 * Route args for an `(?P<id>\d+)` segment.
	 *
	 * @return array
	 */
	protected function id_arg() {
		return array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
	}
}
