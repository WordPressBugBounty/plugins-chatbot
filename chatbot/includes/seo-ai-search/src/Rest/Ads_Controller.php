<?php
/**
 * Paid searches from Google Ads (S13): /ads, /ads/pull, /ads/vault.
 *
 * View reads; Use starts an import and saves paid searches to the Vault.
 * Nothing here ever writes to Google Ads.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Ads\Ads_Data;
use QuantumCloud\SeoAiSearch\Ads\Ga_Ads_Pull;
use QuantumCloud\SeoAiSearch\Ads\Paid_Searches;
use QuantumCloud\WPBotCore\Connections\Analytics;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Paid search routes.
 */
final class Ads_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/ads'       => array( WP_REST_Server::READABLE, 'view', 'can_view' ),
			'/ads/pull'  => array( WP_REST_Server::CREATABLE, 'pull', 'can_use' ),
			'/ads/vault' => array( WP_REST_Server::CREATABLE, 'vault', 'can_use' ),
		);
		foreach ( $routes as $route => $def ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => array( $this, $def[2] ),
				)
			);
		}
	}

	/**
	 * Connections and paid searches.
	 *
	 * @return \WP_REST_Response
	 */
	public function view() {
		$api = class_exists( '\QuantumCloud\WPBotCore\Connections\Google_Ads' ) ? \QuantumCloud\WPBotCore\Connections\Google_Ads::status() : null;
		if ( $api ) {
			unset( $api['token_hint'] );
		}
		return rest_ensure_response(
			array(
				'analytics' => Analytics::status()['status'],
				'api'       => $api ? $api['status'] : 'unavailable',
				'state'     => Ga_Ads_Pull::state(),
				'source'    => Ads_Data::has_data() ? Ads_Data::label( Ads_Data::source() ) : '',
				'searches'  => self::with_volumes( array_slice( Paid_Searches::insights(), 0, 300 ) ),
			)
		);
	}

	/**
	 * Monthly searches for each paid search (cached keyword data; no cost), so
	 * the screen can compare the searches available organically with the ones paid for.
	 *
	 * @param array $rows Paid searches.
	 * @return array
	 */
	private static function with_volumes( array $rows ) {
		$data = function_exists( 'qcld_wpbot_core_keyword_data' ) && array() !== $rows ? qcld_wpbot_core_keyword_data( array_column( $rows, 'query' ) ) : array();
		foreach ( $rows as $index => $row ) {
			$norm                            = \QuantumCloud\WPBotCore\Map\Keyword_Map::normalise( (string) $row['query'] );
			$rows[ $index ]['volume']        = isset( $data[ $norm ]['volume'] ) ? $data[ $norm ]['volume'] : null;
			$rows[ $index ]['volume_source'] = isset( $data[ $norm ]['provider'] ) ? \QuantumCloud\SeoAiSearch\Research\Research_View::provider_label( (string) $data[ $norm ]['provider'] ) : '';
		}
		return $rows;
	}

	/**
	 * Import now (through Analytics).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function pull() {
		$job = Ga_Ads_Pull::start();
		return $this->respond( is_wp_error( $job ) ? $job : array( 'job_id' => $job ) );
	}

	/**
	 * Save paid searches to the Vault (source Google Ads).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function vault( WP_REST_Request $request ) {
		$saved = 0;
		foreach ( array_slice( (array) $request->get_param( 'queries' ), 0, 300 ) as $query ) {
			if ( function_exists( 'qcld_wpbot_core_vault_add' ) && ! is_wp_error(
				qcld_wpbot_core_vault_add(
					array(
						'keyword' => sanitize_text_field( (string) $query ),
						'source'  => 'google_ads',
						'module'  => 'seo',
					)
				)
			) ) {
				++$saved;
			}
		}
		return rest_ensure_response( array( 'saved' => $saved ) );
	}
}
