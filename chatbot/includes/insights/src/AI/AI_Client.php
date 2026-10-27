<?php
/**
 * AI client contract. Hosts implement it on top of their own AI settings,
 * so Insights never stores provider keys of its own.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

defined( 'ABSPATH' ) || exit;

interface AI_Client {

	/**
	 * Whether a provider, key and model are configured.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Label for the UI, e.g. "OpenAI · gpt-4o-mini".
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Run one completion.
	 *
	 * @param AI_Request $request Request.
	 * @return AI_Response
	 */
	public function complete( AI_Request $request );
}
