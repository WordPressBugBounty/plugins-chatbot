<?php
/**
 * Free keyword data (round 3, B): gives WPBot Core the owner's Bing
 * Webmaster Tools key, through this module's Bing client, so research,
 * the Vault and Get cited by AI have Bing search numbers and trends when
 * no paid provider or Keyword Planner is set. The key stays in this
 * module's encrypted secrets; WPBot Core only gets a callable. Also turns
 * on WPBot Core's YouTube search when the owner chose it (setting
 * cited_youtube), which adds the read-only YouTube permission to Google.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Keywords;

use QuantumCloud\SeoAiSearch\Links\Bing_Client;
use QuantumCloud\SeoAiSearch\Secrets;

defined( 'ABSPATH' ) || exit;

/**
 * Free keyword data.
 */
final class Free_Data {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/bing_webmaster_call', array( __CLASS__, 'call' ) );
		// YouTube search (read-only) once the owner turns it on in Get cited by AI.
		add_filter(
			'qcld_wpbot_core/youtube_enabled',
			static function ( $on ) {
				return $on || (bool) \QuantumCloud\SeoAiSearch\Settings::get( 'cited_youtube' );
			}
		);
	}

	/**
	 * The Bing call, when the owner saved a Bing key.
	 *
	 * @param callable|null $call Current.
	 * @return callable|null
	 */
	public static function call( $call ) {
		if ( is_callable( $call ) || '' === Secrets::get( 'bing' ) ) {
			return $call;
		}
		return array( Bing_Client::class, 'call' );
	}
}
