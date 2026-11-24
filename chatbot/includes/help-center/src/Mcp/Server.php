<?php
/**
 * The Help Center's built-in MCP endpoint (HC3-A, owner 20 November 2026): Claude Code, Claude
 * Desktop and other MCP clients connect to `/wp-json/wpbot-help/v1/mcp` with a WordPress
 * Application Password (HTTP Basic), with nothing else installed.
 *
 * MCP over "Streamable HTTP", JSON responses only: initialize, ping, tools/list, tools/call;
 * resources/list and prompts/list answer with empty lists; notifications get 202. Every tool
 * runs as the signed-in WordPress user (Tools checks the capability of each).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * MCP server.
 */
final class Server {

	/**
	 * REST namespace and route.
	 */
	const NAMESPACE_ = 'wpbot-help/v1';

	/**
	 * Route.
	 */
	const ROUTE = '/mcp';

	/**
	 * Protocol versions this server speaks (newest first).
	 */
	const VERSIONS = array( '2025-06-18', '2025-03-26', '2024-11-05' );

	/**
	 * Hook in (only while the Help Center is on).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * The endpoint address.
	 *
	 * @return string
	 */
	public static function url() {
		return rest_url( self::NAMESPACE_ . self::ROUTE );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::NAMESPACE_,
			self::ROUTE,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest' ),
					'permission_callback' => array( self::class, 'can_connect' ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'no_stream' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Signed in (Application Password) and allowed to work on articles.
	 *
	 * @return bool|\WP_Error
	 */
	public static function can_connect() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_not_logged_in', 'Connect with a WordPress Application Password (Help Center → AI & GitHub).', array( 'status' => 401 ) );
		}
		return current_user_can( 'edit_wpbot_articles' ) ? true : new \WP_Error( 'rest_forbidden', 'This WordPress account may not work on help articles.', array( 'status' => 403 ) );
	}

	/**
	 * GET: this server does not open event streams.
	 *
	 * @return \WP_REST_Response
	 */
	public static function no_stream() {
		$response = new \WP_REST_Response( array( 'message' => 'Use POST.' ), 405 );
		$response->header( 'Allow', 'POST' );
		return $response;
	}

	/**
	 * POST: one JSON-RPC message or a batch.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function rest( $request ) {
		$body = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $body ) ) {
			return new \WP_REST_Response( self::error( null, -32700, 'Parse error' ), 400 );
		}
		$batch   = isset( $body[0] ) && array_keys( $body ) === range( 0, count( $body ) - 1 );
		$replies = array();
		foreach ( $batch ? $body : array( $body ) as $message ) {
			$reply = self::handle( is_array( $message ) ? $message : array() );
			if ( null !== $reply ) {
				$replies[] = $reply;
			}
		}
		if ( ! $replies ) {
			return new \WP_REST_Response( null, 202 );
		}
		return new \WP_REST_Response( $batch ? $replies : $replies[0], 200 );
	}

	/**
	 * Handle one JSON-RPC message.
	 *
	 * @param array $message Message.
	 * @return array|null Reply, or null for a notification.
	 */
	public static function handle( array $message ) {
		$id     = array_key_exists( 'id', $message ) ? $message['id'] : null;
		$method = isset( $message['method'] ) ? (string) $message['method'] : '';
		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();
		if ( ! array_key_exists( 'id', $message ) ) {
			return null;
			// Notification (for example notifications/initialized).
		}
		if ( ! isset( $message['jsonrpc'] ) || '2.0' !== $message['jsonrpc'] || '' === $method ) {
			return self::error( $id, -32600, 'Invalid request' );
		}
		switch ( $method ) {
			case 'initialize':
				$asked = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
				return self::result(
					$id,
					array(
						'protocolVersion' => in_array( $asked, self::VERSIONS, true ) ? $asked : self::VERSIONS[0],
						'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
						'serverInfo'      => array(
							'name'    => 'wpbot-help-center',
							'title'   => 'WPBot Help Center',
							'version' => defined( 'QCLD_WPBOT_HELP_CENTER_VERSION' ) ? QCLD_WPBOT_HELP_CENTER_VERSION : '0',
						),
						'instructions'    => self::instructions(),
					)
				);
			case 'ping':
				return self::result( $id, (object) array() );
			case 'tools/list':
				return self::result( $id, array( 'tools' => self::tools() ) );
			case 'tools/call':
				return self::call( $id, $params );
			case 'resources/list':
				return self::result( $id, array( 'resources' => array() ) );
			case 'prompts/list':
				return self::result( $id, array( 'prompts' => array() ) );
			default:
				return self::error( $id, -32601, 'Method not found: ' . $method );
		}//end switch
	}

	/**
	 * How to use the tools (sent at initialize).
	 *
	 * @return string
	 */
	public static function instructions() {
		return 'You are building the help center of this WordPress site. 1) Call helpcenter_get_structure first. 2) Create knowledge bases, then categories (parents before children), then articles. 3) Use stable slugs: running again updates instead of duplicating. 4) Write articles in Markdown for customers: short paragraphs, numbered steps, no internal jargon; do not repeat the title as a heading. 5) Put the GitHub repository, path and commit in "source". 6) Never ask to delete; use helpcenter_propose_unpublish. 7) Finish by telling the owner what you created and that drafts are under Help Center → All Articles.';
	}

	/**
	 * Tools the user may use, in MCP form.
	 *
	 * @return array[]
	 */
	public static function tools() {
		$out = array();
		foreach ( Tools::definitions() as $name => $def ) {
			if ( ! Tools::allowed( $name ) ) {
				continue;
			}
			$out[] = array(
				'name'        => $name,
				'description' => $def['description'],
				'inputSchema' => $def['input'],
				'annotations' => array(
					'readOnlyHint'    => ! $def['write'],
					'destructiveHint' => false,
					'idempotentHint'  => true,
				),
			);
		}
		return $out;
	}

	/**
	 * The tools/call method.
	 *
	 * @param mixed $id     Request id.
	 * @param array $params name, arguments.
	 * @return array
	 */
	private static function call( $id, array $params ) {
		$name = isset( $params['name'] ) ? (string) $params['name'] : '';
		if ( ! isset( Tools::definitions()[ $name ] ) ) {
			return self::error( $id, -32602, 'Unknown tool: ' . $name );
		}
		$args   = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
		$result = Tools::call( $name, $args );
		if ( is_wp_error( $result ) ) {
			return self::result(
				$id,
				array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => $result->get_error_message(),
						),
					),
					'isError' => true,
				)
			);
		}
		return self::result(
			$id,
			array(
				'content'           => array(
					array(
						'type' => 'text',
						'text' => (string) wp_json_encode( $result ),
					),
				),
				'structuredContent' => $result,
				'isError'           => false,
			)
		);
	}

	/**
	 * A result.
	 *
	 * @param mixed $id     Id.
	 * @param mixed $result Result.
	 * @return array
	 */
	public static function result( $id, $result ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * An error.
	 *
	 * @param mixed  $id      Id.
	 * @param int    $code    Code.
	 * @param string $message Message.
	 * @return array
	 */
	public static function error( $id, $code, $message ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => (int) $code,
				'message' => (string) $message,
			),
		);
	}
}
