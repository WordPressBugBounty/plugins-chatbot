<?php
/**
 * Safety net: counts (and at the global limit refuses) AI requests that WPBot's own code makes
 * with the WordPress HTTP API without going through the meter.
 *
 * - Only requests to known AI hosts (filter qcld_wpbot_core/spend_ai_hosts).
 * - Only requests made from WPBot's own files (filter qcld_wpbot_core/spend_own_paths): other
 *   plugins are never counted or refused.
 * - Requests already counted (Provider_Client marks its own; Spend::wrap() is running) are skipped.
 * - Only the usage part of an answer is read; nothing is logged or stored about the request.
 *
 * What it cannot see: raw cURL, file_get_contents and browser-side calls. Those are listed in the
 * README and routed through the meter instead.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * HTTP API safety net.
 */
final class Net {

	/**
	 * Module id of calls counted by the net.
	 */
	const MODULE = 'unrouted';

	/**
	 * Hook the HTTP API.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'pre_http_request', array( __CLASS__, 'before' ), 10, 3 );
		add_action( 'http_api_debug', array( __CLASS__, 'after' ), 10, 5 );
	}

	/**
	 * AI hosts: host => provider id.
	 *
	 * @return array<string,string>
	 */
	public static function hosts() {
		$hosts = array(
			'api.openai.com'                    => 'openai',
			'api.anthropic.com'                 => 'claude',
			'generativelanguage.googleapis.com' => 'gemini',
			'openrouter.ai'                     => 'openrouter',
			'api.x.ai'                          => 'grok',
			'api.mistral.ai'                    => 'mistral',
			'api.deepseek.com'                  => 'deepseek',
			'api.groq.com'                      => 'groq',
			'api.voyageai.com'                  => 'voyage',
		);

		/**
		 * Filter the AI hosts the spending safety net watches (host => provider id).
		 *
		 * @param array $hosts Hosts.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/spend_ai_hosts', $hosts );
	}

	/**
	 * Folders whose code counts as WPBot's own (absolute paths).
	 *
	 * @return string[]
	 */
	public static function own_paths() {
		$core  = dirname( __DIR__, 2 );
		$paths = array( $core );
		// A bundled copy lives in <host plugin>/includes/wpbot-core: the whole host is ours.
		if ( 'includes' === basename( dirname( $core ) ) ) {
			$paths[] = dirname( $core, 2 );
		}

		/**
		 * Filter the folders whose AI requests the safety net counts (absolute paths).
		 *
		 * @param string[] $paths Paths.
		 */
		$paths = (array) apply_filters( 'qcld_wpbot_core/spend_own_paths', $paths );
		return array_values( array_filter( array_map( array( __CLASS__, 'normalise' ), $paths ) ) );
	}

	/**
	 * Before a request: refuse WPBot's own unrouted AI requests at the global limit, or when the
	 * feature the request belongs to is paused.
	 *
	 * @param false|array|WP_Error $pre  Earlier short-circuit.
	 * @param array                $args Request args.
	 * @param string               $url  URL.
	 * @return false|array|WP_Error
	 */
	public static function before( $pre, $args, $url ) {
		if ( false !== $pre || ! self::watched( (array) $args, (string) $url ) ) {
			return $pre;
		}
		$file = self::origin();
		if ( '' === $file || ! self::is_own( $file ) ) {
			return $pre;
		}
		return self::check( $file );
	}

	/**
	 * After a request: record the usage of WPBot's own unrouted AI request.
	 *
	 * @param array|WP_Error $response    Response.
	 * @param string         $context     Context ('response').
	 * @param string         $class_name  Transport class.
	 * @param array          $parsed_args Request args.
	 * @param string         $url         URL.
	 * @return void
	 */
	public static function after( $response, $context, $class_name, $parsed_args, $url ) {
		if ( 'response' !== $context || ! self::watched( (array) $parsed_args, (string) $url ) ) {
			return;
		}
		$file = self::origin();
		if ( '' === $file || ! self::is_own( $file ) ) {
			return;
		}
		self::record_response( $response, (array) $parsed_args, (string) $url, $file );
	}

	/**
	 * Whether a request is one the net looks at: an AI host, not already counted.
	 *
	 * @param array  $args Request args.
	 * @param string $url  URL.
	 * @return bool
	 */
	public static function watched( array $args, $url ) {
		if ( ( isset( $args['qcld_wpbot_spend'] ) && 'metered' === $args['qcld_wpbot_spend'] ) || Spend::is_metering() ) {
			return false;
		}
		return '' !== self::provider_of( $url );
	}

	/**
	 * Refuse at the global limit or when the request's group is paused (pure apart from Spend).
	 *
	 * @param string $file Calling file.
	 * @return false|WP_Error False lets the request run.
	 */
	public static function check( $file ) {
		$allowed = Spend::allow( self::MODULE, self::feature_of( $file ), 0.0, self::group_of_file( $file ) );
		if ( is_wp_error( $allowed ) ) {
			$data = (array) $allowed->get_error_data();
			// A group limit is enforced by the routed calls; the net enforces the global one and pauses.
			if ( 'global' === $data['scope'] || 'paused' === $data['reason'] ) {
				return $allowed;
			}
		}
		return false;
	}

	/**
	 * Record the usage of one answer.
	 *
	 * @param array|WP_Error $response Response.
	 * @param array          $args     Request args.
	 * @param string         $url      URL.
	 * @param string         $file     Calling file.
	 * @return bool Whether a row was written.
	 */
	public static function record_response( $response, array $args, $url, $file ) {
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return false;
		}
		$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		if ( $code < 200 || $code >= 300 ) {
			return false;
		}
		$provider = self::provider_of( $url );
		$sent     = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null;
		$sent     = is_array( $sent ) ? $sent : array();
		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );

		$usage = Usage_Reader::from_body( json_decode( isset( $response['body'] ) ? (string) $response['body'] : '', true ) );
		if ( null === $usage && false !== strpos( $path, '/audio/speech' ) && isset( $sent['input'] ) ) {
			// Speech answers are audio: count the characters sent.
			$usage               = Usage_Reader::blank();
			$usage['characters'] = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $sent['input'] ) : strlen( (string) $sent['input'] );
		}
		if ( null === $usage ) {
			return false;
		}
		if ( false !== strpos( $path, '/embeddings' ) || false !== strpos( $path, 'embedContent' ) ) {
			$usage['kind'] = 'embedding';
		}

		$model = '' !== $usage['model'] ? $usage['model'] : ( isset( $sent['model'] ) && is_string( $sent['model'] ) ? $sent['model'] : '' );
		if ( '' === $model && preg_match( '#/models/([^/:]+)#', $path, $match ) ) {
			$model = rawurldecode( $match[1] );
		}
		Spend::record(
			self::MODULE,
			self::feature_of( $file ),
			$usage,
			array(
				'provider' => $provider,
				'model'    => $model,
				'source'   => 'net',
				'group'    => self::group_of_file( $file ),
			)
		);
		return true;
	}

	/**
	 * Provider id of a URL ('' when it is not an AI host).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function provider_of( $url ) {
		$host  = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		$hosts = self::hosts();
		return isset( $hosts[ $host ] ) ? sanitize_key( (string) $hosts[ $host ] ) : '';
	}

	/**
	 * Feature id of a calling file: "net_" plus up to three folders (for example net_addons_voice-addon),
	 * which the group map turns into a spending group.
	 *
	 * @param string $file Absolute file path.
	 * @return string
	 */
	public static function feature_of( $file ) {
		$relative = self::relative( $file );
		$parts    = array_values( array_filter( explode( '/', $relative ) ) );
		$folder   = count( $parts ) > 1 ? implode( '_', array_slice( $parts, 0, min( 3, count( $parts ) - 1 ) ) ) : 'main';
		return substr( 'net_' . sanitize_key( str_replace( '.', '-', $folder ) ), 0, 60 );
	}

	/**
	 * Spending group of a calling file (filter qcld_wpbot_core/spend_path_groups).
	 *
	 * @param string $file Absolute file path.
	 * @return string
	 */
	public static function group_of_file( $file ) {
		$relative = strtolower( self::relative( $file ) );
		$map      = array(
			'qcld-bot-rag'         => 'knowledge_base',
			'knowledge'            => 'knowledge_base',
			'chat-session'         => 'chat_sessions',
			'chat-history'         => 'chat_sessions',
			'conversational-forms' => 'forms_tickets',
			'support-ticket'       => 'forms_tickets',
			'voice'                => 'voice',
			'ai-studio'            => 'images',
			'ai-tryon'             => 'images',
			'woo-insights'         => 'images',
			'ai-writer'            => 'writer',
			'seo'                  => 'seo',
			'social'               => 'social',
			'insights'             => 'insights',
			'ai-marketing'         => 'insights',
			'automator'            => 'automator',
			'integration/'         => 'chatbot',
			'messenger'            => 'chatbot',
			'whatsapp'             => 'chatbot',
			'telegram'             => 'chatbot',
			'chatbot'              => 'chatbot',
			'qc-rating'            => 'other',
		);

		/**
		 * Filter how calling files map to spending groups (path fragment => group id; first match wins).
		 *
		 * @param array $map Map.
		 */
		$map = (array) apply_filters( 'qcld_wpbot_core/spend_path_groups', $map );
		foreach ( $map as $fragment => $group ) {
			if ( false !== strpos( $relative, strtolower( (string) $fragment ) ) ) {
				return Features::exists( (string) $group ) ? (string) $group : Features::OTHER;
			}
		}
		// Files at the host's top level (functions.php) answer the chatbot.
		return false === strpos( $relative, '/' ) ? 'chatbot' : Features::OTHER;
	}

	/**
	 * Whether a file is inside WPBot's own folders.
	 *
	 * @param string $file Absolute path.
	 * @return bool
	 */
	public static function is_own( $file ) {
		$file = self::normalise( $file );
		foreach ( self::own_paths() as $path ) {
			if ( 0 === strpos( $file, $path . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The file that started the HTTP request: the first caller outside WordPress core and this file.
	 *
	 * @return string '' when unknown.
	 */
	public static function origin() {
		$skip = array( self::normalise( __FILE__ ) );
		if ( defined( 'WPINC' ) ) {
			$skip[] = self::normalise( ABSPATH . WPINC );
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Only file names are read, to tell WPBot's requests from other plugins'.
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 ) as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}
			$file = self::normalise( $frame['file'] );
			foreach ( $skip as $prefix ) {
				if ( 0 === strpos( $file, $prefix ) ) {
					continue 2;
				}
			}
			return $file;
		}
		return '';
	}

	/**
	 * Path of a file inside its own folder ("addons/voice-addon/api.php").
	 *
	 * @param string $file Absolute path.
	 * @return string
	 */
	private static function relative( $file ) {
		$file = self::normalise( $file );
		$best = '';
		foreach ( self::own_paths() as $path ) {
			if ( 0 === strpos( $file, $path . '/' ) && strlen( $path ) > strlen( $best ) ) {
				$best = $path;
			}
		}
		return '' === $best ? basename( $file ) : substr( $file, strlen( $best ) + 1 );
	}

	/**
	 * Forward slashes, no trailing slash.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function normalise( $path ) {
		return rtrim( str_replace( '\\', '/', (string) $path ), '/' );
	}
}
