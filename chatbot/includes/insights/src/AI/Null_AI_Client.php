<?php
/**
 * Used when the host provides no AI.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

defined( 'ABSPATH' ) || exit;

final class Null_AI_Client implements AI_Client {

	/**
	 * {@inheritDoc}
	 */
	public function is_available() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function complete( AI_Request $request ) {
		return AI_Response::error( 'ai_unavailable', 'No AI provider is configured.' );
	}
}
