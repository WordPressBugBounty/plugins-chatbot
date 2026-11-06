<?php
/**
 * Shared REST error helper.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Errors the screens can show.
 */
final class Rest_Errors {

	/**
	 * Give AI and service errors an HTTP status (502 when they have none).
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	public static function with_status( WP_Error $error ) {
		$data = (array) $error->get_error_data();
		if ( empty( $data['status'] ) ) {
			$data['status'] = 502;
		}
		return new WP_Error( $error->get_error_code(), $error->get_error_message(), $data );
	}
}
