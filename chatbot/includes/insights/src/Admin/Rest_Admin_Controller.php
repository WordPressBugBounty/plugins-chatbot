<?php
/**
 * Admin REST endpoints under /wp-json/qcin/v1/admin/*. Every route checks
 * the host capability and the REST nonce (sent by apiFetch).
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Admin;

use QuantumCloud\Insights\AI\AI_Settings;
use QuantumCloud\Insights\AI\Insight_Service;
use QuantumCloud\Insights\AI\Provider_Client;
use QuantumCloud\Insights\AI\Providers;
use QuantumCloud\Insights\AI\Usage;
use QuantumCloud\Insights\Module;
use QuantumCloud\Insights\Settings;
use QuantumCloud\Insights\Processing\Health_Check;
use QuantumCloud\Insights\Reports\Report_Settings;
use QuantumCloud\Insights\Reports\Reports;
use QuantumCloud\Insights\Storage\Heatmap_Repository;
use QuantumCloud\Insights\Storage\Recording_Events;
use QuantumCloud\Insights\Storage\Segment;
use QuantumCloud\Insights\Storage\Signal_Types;
use QuantumCloud\Insights\Storage\Session_Repository;
use QuantumCloud\Insights\Storage\Signal_Repository;

defined( 'ABSPATH' ) || exit;

final class Rest_Admin_Controller {

	const NS      = 'qcin/v1';
	const UUID_RE = '[0-9a-f-]{36}';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$perm     = array( $this, 'can_manage' );
		$settings = array( $this, 'can_manage_settings' );

		register_rest_route(
			self::NS,
			'/admin/sessions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_sessions' ),
				'permission_callback' => $perm,
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'range'    => array(
						'type'    => 'string',
						'default' => '7',
						'enum'    => array( '1', '7', '30', '90', 'all' ),
					),
					'device'   => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array( '', 'desktop', 'tablet', 'mobile' ),
					),
					'url'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'frustration' => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array_merge( array( '', 'any' ), array_keys( Signal_Types::flags() ) ),
					),
				),
			)
		);

		$range  = array(
			'type'    => 'string',
			'default' => '7',
			'enum'    => array( '1', '7', '30', '90' ),
		);
		$device = array(
			'type'    => 'string',
			'default' => 'desktop',
			'enum'    => array( 'desktop', 'tablet', 'mobile' ),
		);

		register_rest_route(
			self::NS,
			'/admin/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => $perm,
				'args'                => array( 'range' => $range ),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/heatmaps/pages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'heatmap_pages' ),
				'permission_callback' => $perm,
				'args'                => array(
					'range'  => $range,
					'device' => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array( '', 'desktop', 'tablet', 'mobile' ),
					),
					'search' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/heatmaps',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'heatmap' ),
				'permission_callback' => $perm,
				'args'                => array(
					'url_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'range'  => $range,
					'device' => $device,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/sessions/(?P<uuid>' . self::UUID_RE . ')',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_session' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_session' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/sessions/(?P<uuid>' . self::UUID_RE . ')/favourite',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'favourite' ),
				'permission_callback' => $perm,
				'args'                => array(
					'value' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/sessions/(?P<uuid>' . self::UUID_RE . ')/pageviews/(?P<pv>' . self::UUID_RE . ')/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'events' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/admin/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => $settings,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/sessions/(?P<uuid>' . self::UUID_RE . ')/summary',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_summary' ),
					'permission_callback' => $perm,
					'args'                => array(
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/ai',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_ai' ),
					'permission_callback' => $settings,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_ai' ),
					'permission_callback' => $settings,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/ai/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_ai' ),
				'permission_callback' => $settings,
			)
		);

		register_rest_route(
			self::NS,
			'/admin/ai/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_ai' ),
				'permission_callback' => $settings,
				'args'                => array(
					'provider' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => Providers::ids(),
					),
					'key'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'model'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'url'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/admin/delete-all',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete_all' ),
				'permission_callback' => $settings,
			)
		);

		register_rest_route(
			self::NS,
			'/admin/reports',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_reports' ),
					'permission_callback' => $settings,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_reports' ),
					'permission_callback' => $settings,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/admin/reports/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_report' ),
				'permission_callback' => $settings,
				'args'                => array(
					'recipients' => array(
						'type'    => 'string',
						'default' => '',
					),
					'frequency'  => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array( '', 'weekly', 'monthly' ),
					),
				),
			)
		);
	}

	/**
	 * GET email report settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_reports() {
		return rest_ensure_response( $this->reports_payload( Report_Settings::public_view() ) );
	}

	/**
	 * POST email report settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_reports( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		$view  = Report_Settings::update( is_array( $input ) ? $input : array() );
		return is_wp_error( $view ) ? $view : rest_ensure_response( $this->reports_payload( $view ) );
	}

	/**
	 * POST send a test report now (to the typed or saved addresses).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_report( \WP_REST_Request $request ) {
		$key = 'qcin_report_test_' . get_current_user_id();
		if ( get_transient( $key ) ) {
			return new \WP_Error( 'qcin_report_wait', __( 'A test was just sent. Wait a few seconds before sending another.', 'qcin-textdomain' ), array( 'status' => 429 ) );
		}
		set_transient( $key, 1, 15 );
		$override = array();
		if ( '' !== trim( (string) $request['recipients'] ) ) {
			$override['recipients'] = (string) $request['recipients'];
		}
		if ( '' !== (string) $request['frequency'] ) {
			$override['frequency'] = (string) $request['frequency'];
		}
		$result = Reports::send( true, $override );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Report settings plus the preview links.
	 *
	 * @param array<string,mixed> $view Public view.
	 * @return array<string,mixed>
	 */
	private function reports_payload( array $view ) {
		$view['previewUrl']        = Reports::preview_url( 'weekly' );
		$view['previewUrlMonthly'] = Reports::preview_url( 'monthly' );
		$view['active']            = Reports::enabled();
		return $view;
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return Module::instance()->can( 'view' );
	}

	/**
	 * Permission check for changing settings, AI keys and deleting all data.
	 *
	 * @return bool
	 */
	public function can_manage_settings() {
		return Module::instance()->can( 'settings' );
	}

	/**
	 * GET sessions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_sessions( \WP_REST_Request $request ) {
		$range   = (string) $request['range'];
		$filters = array(
			'device'      => (string) $request['device'],
			'url'         => (string) $request['url'],
			'frustration' => (string) $request['frustration'],
			// Everything else the screen sent, for features hooked to qcin/session_query.
			// Raw values: callbacks must validate them and use placeholders.
			'extra'       => $request->get_query_params(),
			'segment'     => Segment::from_params( $request->get_query_params() ),
		);
		if ( 'all' !== $range ) {
			$filters['from'] = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) $range );
		}
		$repo   = new Session_Repository();
		$result = $repo->list_sessions( $filters, (int) $request['page'], (int) $request['per_page'] );

		return rest_ensure_response(
			array(
				'total' => $result['total'],
				'items' => array_map( array( $this, 'format_session' ), $result['rows'] ),
			)
		);
	}

	/**
	 * GET one session with pageviews.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_session( \WP_REST_Request $request ) {
		$repo    = new Session_Repository();
		$session = $repo->find_session( (string) $request['uuid'] );
		if ( ! $session ) {
			return new \WP_Error( 'qcin_not_found', __( 'Recording not found.', 'qcin-textdomain' ), array( 'status' => 404 ) );
		}
		$data              = $this->format_session( $session );
		$data['pageviews'] = array();
		foreach ( $repo->pageviews( (int) $session->id ) as $pv ) {
			$data['pageviews'][] = array(
				'id'          => $pv->uuid,
				'url'         => (string) $pv->url,
				'title'       => (string) $pv->title,
				'started_at'  => $this->iso( $pv->started_at ),
				'duration_ms' => (int) $pv->duration_ms,
				'scroll'      => (int) $pv->max_scroll_pct,
				'viewport'    => array( (int) $pv->viewport_w, (int) $pv->viewport_h ),
				'chunks'      => (int) $pv->chunk_count,
			);
		}
		$data['signals'] = ( new Signal_Repository() )->for_session( (int) $session->id );
		return rest_ensure_response( $data );
	}

	/**
	 * DELETE a session.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_session( \WP_REST_Request $request ) {
		$repo    = new Session_Repository();
		$session = $repo->find_session( (string) $request['uuid'] );
		if ( ! $session ) {
			return new \WP_Error( 'qcin_not_found', __( 'Recording not found.', 'qcin-textdomain' ), array( 'status' => 404 ) );
		}
		Module::instance()->store()->delete_session( $session->uuid );
		$repo->delete_session( (int) $session->id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * POST favourite flag (favourites are kept past retention).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function favourite( \WP_REST_Request $request ) {
		global $wpdb;
		$repo    = new Session_Repository();
		$session = $repo->find_session( (string) $request['uuid'] );
		if ( ! $session ) {
			return new \WP_Error( 'qcin_not_found', __( 'Recording not found.', 'qcin-textdomain' ), array( 'status' => 404 ) );
		}
		$value = $request['value'] ? 1 : 0;
		$wpdb->update( \QuantumCloud\Insights\Storage\Schema::table( 'sessions' ), array( 'favourite' => $value ), array( 'id' => (int) $session->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return rest_ensure_response( array( 'favourite' => (bool) $value ) );
	}

	/**
	 * GET rrweb events of one pageview, merged from its chunks.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function events( \WP_REST_Request $request ) {
		$repo     = new Session_Repository();
		$session  = $repo->find_session( (string) $request['uuid'] );
		$pageview = $repo->find_pageview( (string) $request['pv'] );
		if ( ! $session || ! $pageview || (int) $pageview->session_id !== (int) $session->id ) {
			return new \WP_Error( 'qcin_not_found', __( 'Recording not found.', 'qcin-textdomain' ), array( 'status' => 404 ) );
		}
		$events   = Recording_Events::load( $session->uuid, $pageview->uuid );
		$response = rest_ensure_response( array( 'events' => $events ) );
		$response->header( 'Cache-Control', 'private, max-age=300' );
		return $response;
	}

	/**
	 * GET settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( Settings::all() );
	}

	/**
	 * POST settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		return rest_ensure_response( Settings::update( is_array( $input ) ? $input : array() ) );
	}

	/**
	 * GET the stored AI summary of a session.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_summary( \WP_REST_Request $request ) {
		$session = ( new Session_Repository() )->find_session( (string) $request['uuid'] );
		if ( ! $session ) {
			return new \WP_Error( 'qcin_not_found', __( 'Recording not found.', 'qcin-textdomain' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'summary' => ( new Insight_Service() )->get_summary( $session ),
				'state'   => $this->ai_state(),
			)
		);
	}

	/**
	 * POST create (or refresh) the AI summary of a session.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_summary( \WP_REST_Request $request ) {
		$session = ( new Session_Repository() )->find_session( (string) $request['uuid'] );
		if ( ! $session ) {
			return new \WP_Error( 'qcin_not_found', __( 'Recording not found.', 'qcin-textdomain' ), array( 'status' => 404 ) );
		}
		$result = ( new Insight_Service() )->summarize( $session, (bool) $request['force'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'summary' => $result,
				'state'   => $this->ai_state(),
			)
		);
	}

	/**
	 * GET AI settings (keys masked), usage and import status.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_ai() {
		return rest_ensure_response( $this->ai_payload() );
	}

	/**
	 * POST AI settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_ai( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		AI_Settings::update( is_array( $input ) ? $input : array() );
		return rest_ensure_response( $this->ai_payload() );
	}

	/**
	 * POST copy keys and models again from the host's AI settings.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import_ai() {
		$done = AI_Settings::import_from_host();
		if ( null === $done ) {
			return new \WP_Error(
				'qcin_ai_nothing_to_copy',
				/* translators: %s: host plugin name, e.g. "WPBot" */
				sprintf( __( 'No API keys were found in %s AI Settings.', 'qcin-textdomain' ), AI_Settings::host_name() ),
				array( 'status' => 404 )
			);
		}
		return rest_ensure_response( $this->ai_payload() );
	}

	/**
	 * POST test a provider with the typed (or saved) key and model.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function test_ai( \WP_REST_Request $request ) {
		$provider = (string) $request['provider'];
		$saved    = AI_Settings::provider( $provider );
		$key      = trim( (string) $request['key'] );
		$model    = trim( (string) $request['model'] );
		$url      = trim( (string) $request['url'] );
		$client   = new Provider_Client( $provider, '' !== $key ? $key : $saved['key'], '' !== $model ? $model : $saved['model'], '' !== $url ? esc_url_raw( $url ) : $saved['url'] );
		return rest_ensure_response( ( new Insight_Service() )->test( $client ) );
	}

	/**
	 * AI settings, usage and import status for the Settings screen.
	 *
	 * @return array<string,mixed>
	 */
	private function ai_payload() {
		$host = Module::instance()->host();
		return array(
			'settings'  => AI_Settings::public_view(),
			'usage'     => Usage::report(),
			'canImport' => AI_Settings::can_import(),
			'hostName'  => AI_Settings::host_name(),
			'hostAiUrl' => $host ? $host->get_ai_settings_url() : '',
		);
	}

	/**
	 * Whether the summary feature can run, and why not.
	 *
	 * @return array<string,mixed>
	 */
	private function ai_state() {
		$limit = Usage::limit_reached();
		return array(
			'ready'   => AI_Settings::is_ready(),
			'feature' => AI_Settings::feature_on( 'summary' ),
			'label'   => AI_Settings::client()->get_label(),
			'paused'  => $limit ? $limit['message'] : '',
		);
	}

	/**
	 * GET status: counts, storage and (with ?health=1) storage health.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function status( \WP_REST_Request $request ) {
		$repo   = new Session_Repository();
		$module = Module::instance();
		$ai     = $module->ai();
		$health = $request->get_param( 'health' ) ? Health_Check::report( (bool) $request->get_param( 'refresh' ) ) : null;
		return rest_ensure_response(
			array(
				'last24h'    => $repo->stats( gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ),
				'last7d'     => $repo->stats( gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ),
				'bytes'      => \QuantumCloud\Insights\Processing\Scheduler::used_bytes( $repo ),
				'capBytes'   => (int) Settings::get( 'storage_cap_mb' ) * 1048576,
				'health'     => $health,
				'consentApi' => function_exists( 'wp_has_consent' ),
				'ai'         => array(
					'available' => $ai->is_available(),
					'label'     => $ai->get_label(),
				),
			)
		);
	}

	/**
	 * GET overview: KPIs, sessions per day, top pages, frustration and errors.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function overview( \WP_REST_Request $request ) {
		$days  = (int) $request['range'];
		$since = gmdate( 'Y-m-d 00:00:00', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$repo  = new Session_Repository();
		$seg   = Segment::from_params( $request->get_query_params() );

		$per_day = $repo->sessions_per_day( $since, $seg );
		$series  = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day      = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$series[] = array(
				'day'        => $day,
				'sessions'   => $per_day[ $day ]['sessions'] ?? 0,
				'frustrated' => $per_day[ $day ]['frustrated'] ?? 0,
			);
		}
		$pages = array();
		foreach ( $repo->top_pages( $since, 10, $seg ) as $row ) {
			$pages[] = array(
				'url_id'     => (int) $row->url_id,
				'url'        => (string) $row->url,
				'title'      => (string) $row->title,
				'views'      => (int) $row->views,
				'avg_time_s' => (int) round( (float) $row->avg_time_ms / 1000 ),
				'avg_scroll' => (int) round( (float) $row->avg_scroll ),
			);
		}
		return rest_ensure_response(
			array(
				'range'  => $days,
				'kpis'    => $repo->overview( $since, $seg ),
				'series'  => $series,
				'pages'   => $pages,
				'errors'  => ( new Signal_Repository() )->top_errors( $since, 5, $seg ),
				'segment' => null !== $seg,
			)
		);
	}

	/**
	 * GET pages that have heatmap data, most viewed first.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function heatmap_pages( \WP_REST_Request $request ) {
		$since = gmdate( 'Y-m-d 00:00:00', time() - ( (int) $request['range'] - 1 ) * DAY_IN_SECONDS );
		$items = array();
		$seg   = Segment::from_params( $request->get_query_params() );
		foreach ( ( new Heatmap_Repository() )->pages( $since, (string) $request['device'], (string) $request['search'], 50, $seg ) as $row ) {
			$items[] = array(
				'url_id' => (int) $row->url_id,
				'url'    => (string) $row->url,
				'title'  => (string) $row->title,
				'views'  => (int) $row->views,
			);
		}
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * GET click and scroll heatmap data for one page and device.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function heatmap( \WP_REST_Request $request ) {
		$heat = new Heatmap_Repository();
		$url  = $heat->url( (int) $request['url_id'] );
		if ( ! $url ) {
			return new \WP_Error( 'qcin_not_found', __( 'Page not found.', 'qcin-textdomain' ), array( 'status' => 404 ) );
		}
		$days     = (int) $request['range'];
		$from_day = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$device   = (string) $request['device'];
		$seg      = Segment::from_params( $request->get_query_params() );
		$seg_from = null;
		if ( $seg ) {
			// Segments use raw clicks, which are kept Heatmap_Repository::raw_days() days.
			$raw_from = gmdate( 'Y-m-d', time() - ( Heatmap_Repository::raw_days() - 1 ) * DAY_IN_SECONDS );
			$seg_from = max( $from_day, $raw_from );
			$clicks   = $heat->clicks_in_segment( (int) $url->id, $device, $seg_from . ' 00:00:00', $seg );
			$scroll   = $heat->scroll_in_segment( (int) $url->id, $device, $from_day . ' 00:00:00', $seg );
		} else {
			$clicks = $heat->clicks( (int) $url->id, $device, $from_day );
			$scroll = $heat->scroll( (int) $url->id, $device, $from_day );
		}
		$signals  = ( new Signal_Repository() )->by_element( (int) $url->id, $device, ( $seg_from ? $seg_from : $from_day ) . ' 00:00:00', $seg );
		foreach ( $clicks['elements'] as &$el ) {
			$el['rage'] = $signals[ $el['id'] ]['rage'] ?? 0;
			$el['dead'] = $signals[ $el['id'] ]['dead'] ?? 0;
		}
		unset( $el );
		$recording = $heat->latest_recording( (int) $url->id, $device );
		return rest_ensure_response(
			array(
				'url'       => array(
					'id'    => (int) $url->id,
					'url'   => (string) $url->url,
					'title' => (string) $url->title,
				),
				'device'    => $device,
				'range'     => $days,
				'clicks'    => $clicks,
				'scroll'    => $scroll,
				// With a segment, clicks start at this day (raw clicks only).
				'segment'   => $seg ? array( 'clicksFrom' => $seg_from ) : null,
				'recording' => $recording ? array(
					'session'  => (string) $recording->session,
					'pageview' => (string) $recording->pageview,
					'width'    => (int) $recording->viewport_w,
					'height'   => (int) $recording->viewport_h,
				) : null,
			)
		);
	}

	/**
	 * POST delete every recording, heatmap and statistic.
	 *
	 * @return \WP_REST_Response
	 */
	public function delete_all() {
		Module::instance()->store()->delete_all();
		( new Session_Repository() )->truncate();
		delete_option( Heatmap_Repository::WATERMARK_OPTION );
		/**
		 * After "Delete all data now": features clear their own data and remote copies.
		 */
		do_action( 'qcin/delete_all' );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Session row for the API.
	 *
	 * @param object $row Row.
	 * @return array<string,mixed>
	 */
	public function format_session( $row ) {
		$duration = (int) $row->duration_s;
		if ( 0 === $duration ) {
			$duration = max( 0, strtotime( $row->last_seen_at . ' UTC' ) - strtotime( $row->started_at . ' UTC' ) );
		}
		$data = array(
			'id'          => $row->uuid,
			'started_at'  => $this->iso( $row->started_at ),
			'duration_s'  => $duration,
			'pageviews'   => (int) $row->pageviews,
			'device'      => (string) $row->device,
			'os'          => (string) $row->os,
			'browser'     => (string) $row->browser,
			'country'     => (string) $row->country,
			'referrer'    => (string) $row->referrer_host,
			'utm_source'  => (string) $row->utm_source,
			'returning'   => (bool) $row->is_returning,
			'favourite'   => (bool) $row->favourite,
			'active'      => 0 === (int) $row->status,
			'bytes'       => (int) $row->bytes,
			'flags'       => self::flag_names( (int) $row->flags ),
			'entry_url'   => isset( $row->entry_url ) ? (string) $row->entry_url : '',
			'entry_title' => isset( $row->entry_title ) ? (string) $row->entry_title : '',
		);
		/**
		 * A session as the admin screens get it (list and player).
		 * Features may add keys, e.g. a badge flag.
		 *
		 * @param array<string,mixed> $data Session data.
		 * @param object              $row  Session row.
		 */
		$filtered = apply_filters( 'qcin/admin_session', $data, $row );
		return is_array( $filtered ) ? $filtered : $data;
	}

	/**
	 * Frustration flag bits to names.
	 *
	 * @param int $flags Bitmask.
	 * @return string[]
	 */
	private static function flag_names( $flags ) {
		$out = array();
		foreach ( \QuantumCloud\Insights\Storage\Signal_Types::flags() as $name => $bit ) {
			if ( $flags & $bit ) {
				$out[] = $name;
			}
		}
		return $out;
	}

	/**
	 * UTC MySQL datetime to ISO 8601.
	 *
	 * @param string $datetime Datetime.
	 * @return string
	 */
	private function iso( $datetime ) {
		return gmdate( 'c', (int) strtotime( $datetime . ' UTC' ) );
	}
}
