<?php
/**
 * Catches errors for the error log, and marks each one as WPBot's or as coming from outside
 * WPBot (owner, 19 November 2026: log all errors, but say clearly where each came from):
 *
 * - PHP warnings and errors (notices and deprecations only when the owner turns them on).
 *   WordPress and any earlier error handler still get every error as before.
 * - Fatal errors (crashes), at the end of the request.
 * - Outside requests that fail (no answer, or HTTP 400 and above): AI providers, Meta, Telegram,
 *   Google and so on, with the service's own message.
 * - Emails WordPress could not send.
 *
 * WPBot's own errors are always recorded. Errors from other plugins, the theme and WordPress are
 * recorded with origin "outside" and the part of the site they came from (Origin), unless the
 * owner turned the "outside" setting off or the filter qcld_wpbot_core/error_log_outside says no.
 *
 * Errors inside @-silenced calls are left out: the code chose to ignore them.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Diagnostics;

use QuantumCloud\WPBotCore\Spend\Net;

defined( 'ABSPATH' ) || exit;

/**
 * Error capture.
 */
final class Error_Capture {

	/**
	 * The error handler that was active before ours.
	 *
	 * @var callable|null
	 */
	private static $previous = null;

	/**
	 * Whether the handlers are installed.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Whether outside errors are recorded on this request (null until asked).
	 *
	 * @var bool|null
	 */
	private static $outside = null;

	/**
	 * Install the handlers.
	 *
	 * @return void
	 */
	public static function hook() {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		// "Record errors" off: no error handler, shutdown function or request and email hooks at
		// all, so a failed request costs nothing (no backtrace) and set_error_handler() keeps the
		// previous handler. Runs at plugins_loaded priority 1; enabled() reads the option directly.
		if ( ! Error_Log::enabled() ) {
			return;
		}
		/**
		 * Whether WPBot records PHP errors from its own files (on by default).
		 *
		 * @param bool $on On.
		 */
		if ( apply_filters( 'qcld_wpbot_core/error_log_php', true ) ) {
			self::$previous = set_error_handler( array( __CLASS__, 'php' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Records WPBot's own errors for the owner's error log, then passes every error on.
			register_shutdown_function( array( __CLASS__, 'shutdown' ) );
		}
		add_action( 'http_api_debug', array( __CLASS__, 'http' ), 20, 5 );
		add_action( 'wp_mail_failed', array( __CLASS__, 'mail' ) );
	}

	/**
	 * Folders whose code counts as WPBot's (absolute paths, forward slashes).
	 *
	 * @return string[]
	 */
	public static function paths() {
		/**
		 * Folders whose errors the error log records. Each WPBot plugin adds its own folder.
		 *
		 * @param string[] $paths Absolute paths.
		 */
		$paths = (array) apply_filters( 'qcld_wpbot_core/error_paths', Net::own_paths() );
		$clean = array();
		foreach ( $paths as $path ) {
			$path = rtrim( str_replace( '\\', '/', (string) $path ), '/' );
			if ( '' !== $path ) {
				$clean[ $path ] = $path;
			}
		}
		return array_values( $clean );
	}

	/**
	 * Whether a file belongs to WPBot.
	 *
	 * @param string $file Absolute path.
	 * @return bool
	 */
	public static function is_own( $file ) {
		$file = str_replace( '\\', '/', (string) $file );
		if ( '' === $file ) {
			return false;
		}
		foreach ( self::paths() as $path ) {
			if ( 0 === strpos( $file, $path . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether errors from outside WPBot are recorded (setting "outside", on by default).
	 *
	 * @return bool
	 */
	public static function outside_on() {
		if ( null === self::$outside ) {
			/**
			 * Whether the error log records errors from other plugins, the theme and WordPress.
			 *
			 * @param bool $on The owner's setting (on by default).
			 */
			self::$outside = (bool) apply_filters( 'qcld_wpbot_core/error_log_outside', Error_Log::settings()['outside'] );
		}
		return self::$outside;
	}

	/**
	 * Forget the cached "outside" answer (after the setting changed, and in tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$outside = null;
	}

	/**
	 * Where a file belongs, or null when the error is not recorded (outside, and outside errors off).
	 *
	 * @param string $file Absolute path ('' = only WordPress core on the stack).
	 * @return array{origin:string,component:string,slug:string}|null
	 */
	private static function origin_of( $file ) {
		$origin = Origin::of_file( $file );
		if ( Origin::OUTSIDE === $origin['origin'] && ! self::outside_on() ) {
			return null;
		}
		return $origin;
	}

	/**
	 * Detail keys for an origin.
	 *
	 * @param array $origin From Origin::of_file().
	 * @return array
	 */
	private static function origin_detail( array $origin ) {
		$detail = array(
			'origin' => $origin['origin'],
			'module' => $origin['slug'],
		);
		if ( Origin::OUTSIDE === $origin['origin'] ) {
			$detail['component'] = $origin['component'];
		}
		return $detail;
	}

	/**
	 * PHP error handler: record the error with its origin, then pass the error on.
	 *
	 * @param int    $number  Error level.
	 * @param string $message Message.
	 * @param string $file    File.
	 * @param int    $line    Line.
	 * @return bool
	 */
	public static function php( $number, $message, $file = '', $line = 0 ) {
		if ( error_reporting() & $number ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting, PluginCheck.CodeAnalysis.PHPErrorReporting.DirectErrorReportingCall -- Read only: @-silenced errors are skipped.
			$level  = self::level( $number );
			$wanted = ( 'notice' !== $level || Error_Log::settings()['notices'] ) && '' !== (string) $file;
			// A warning raised inside WordPress (for example esc_html() given an array) names WordPress's
			// file; when WPBot's code made the call, record WPBot's file and line instead, so the log says
			// where to fix it (owner, 20 November 2026).
			$caller = $wanted && self::in_wordpress( (string) $file ) ? self::wpbot_caller() : null;
			$origin = $wanted ? ( $caller ? Origin::of_file( $caller['file'] ) : self::origin_of( $file ) ) : null;
			if ( $origin ) {
				$detail = array(
					'level' => $level,
					'file'  => $caller ? $caller['file'] : $file,
					'line'  => $caller ? (int) $caller['line'] : (int) $line,
					'type'  => self::type_name( $number ),
				);
				if ( $caller ) {
					$detail['raised_in'] = Scrub::path( (string) $file ) . ':' . (int) $line;
				}
				Error_Log::add( 'php', $message, array_merge( self::origin_detail( $origin ), $detail ) );
			}
		}
		if ( self::$previous ) {
			return (bool) call_user_func( self::$previous, $number, $message, $file, $line );
		}
		return false;
	}

	/**
	 * Whether a file is part of WordPress itself (wp-includes, wp-admin).
	 *
	 * @param string $file Absolute path.
	 * @return bool
	 */
	private static function in_wordpress( $file ) {
		$file = str_replace( '\\', '/', $file );
		$root = defined( 'ABSPATH' ) ? rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) : '';
		return '' !== $root && ( 0 === strpos( $file, $root . '/wp-includes/' ) || 0 === strpos( $file, $root . '/wp-admin/' ) );
	}

	/**
	 * The WPBot file and line that called into WordPress, or null when the nearest caller is not WPBot.
	 *
	 * @return array{file:string,line:int}|null
	 */
	private static function wpbot_caller() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Only file names and lines, to name the WPBot code that called WordPress.
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 ) as $frame ) {
			if ( ! isset( $frame['file'] ) || __FILE__ === $frame['file'] || self::in_wordpress( (string) $frame['file'] ) ) {
				continue;
			}
			// The nearest code outside WordPress made the call: WPBot's, or another plugin's (then
			// the warning stays "outside").
			return self::is_own( (string) $frame['file'] ) ? array(
				'file' => (string) $frame['file'],
				'line' => isset( $frame['line'] ) ? (int) $frame['line'] : 0,
			) : null;
		}
		return null;
	}

	/**
	 * End of the request: record a fatal error (a crash) with its origin.
	 *
	 * @return void
	 */
	public static function shutdown() {
		$error = error_get_last();
		if ( ! is_array( $error ) || ! in_array( (int) $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			return;
		}
		$file    = (string) $error['file'];
		$message = (string) $error['message'];
		if ( self::is_own( $file ) ) {
			$origin = Origin::of_file( $file );
		} elseif ( '' !== self::module_on_trace( $message ) ) {
			// An uncaught exception reports the file where it was thrown (often WordPress); its
			// trace names WPBot's code, so the crash is WPBot's.
			$origin = array(
				'origin'    => Origin::WPBOT,
				'component' => 'wpbot',
				'slug'      => self::module_on_trace( $message ),
			);
		} else {
			$origin = self::origin_of( $file );
		}
		if ( ! $origin ) {
			return;
		}
		$first = strtok( $message, "\n" );
		Error_Log::add(
			'fatal',
			false !== $first ? $first : $message,
			array_merge(
				self::origin_detail( $origin ),
				array(
					'level' => 'error',
					'file'  => $error['file'],
					'line'  => (int) $error['line'],
					'type'  => self::type_name( (int) $error['type'] ),
					'stack' => $message,
				)
			)
		);
	}

	/**
	 * The WPBot part named first on an uncaught exception's trace.
	 *
	 * @param string $message Fatal error message with its trace.
	 * @return string '' when the trace names no WPBot file.
	 */
	private static function module_on_trace( $message ) {
		$message = str_replace( '\\', '/', $message );
		foreach ( self::paths() as $path ) {
			$at = strpos( $message, $path . '/' );
			if ( false !== $at ) {
				$file = strtok( substr( $message, $at ), "( :\n" );
				return Error_Log::module_of( Scrub::path( false !== $file ? $file : $path . '/' ) );
			}
		}
		return '';
	}

	/**
	 * A finished outside request that failed: record it, with the file that started it.
	 *
	 * @param array|\WP_Error $response   Response.
	 * @param string          $context    Context ("response").
	 * @param string          $class_name Transport class.
	 * @param array           $args       Request args.
	 * @param string          $url        URL.
	 * @return void
	 */
	public static function http( $response, $context, $class_name, $args, $url ) {
		if ( 'response' !== $context ) {
			return;
		}
		$failed = is_wp_error( $response );
		$status = $failed ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( ! $failed && $status < 400 ) {
			return;
		}
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( '' === $host || false !== strpos( $host, 'api.wordpress.org' ) || ! empty( $args['qcld_wpbot_quiet'] ) ) {
			return;
		}
		$file   = Net::origin( array( __FILE__ ) );
		$origin = self::origin_of( $file );
		if ( ! $origin ) {
			return;
		}
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( $failed ) {
			$message = sprintf( 'No answer from %1$s: %2$s', $host, $response->get_error_message() );
		} else {
			$said    = self::service_message( (string) wp_remote_retrieve_body( $response ) );
			$message = sprintf( 'HTTP %1$d from %2$s%3$s', $status, $host, '' !== $said ? ': ' . $said : '' );
		}
		Error_Log::add(
			'http',
			$message,
			array_merge(
				self::origin_detail( $origin ),
				array(
					'level'  => ( $failed || $status >= 500 || in_array( $status, array( 401, 403, 429 ), true ) ) ? 'error' : 'warning',
					'file'   => $file,
					'host'   => $host,
					'path'   => $path,
					'status' => $status,
					'method' => isset( $args['method'] ) ? (string) $args['method'] : 'GET',
				)
			)
		);
	}

	/**
	 * WordPress could not send an email: record it, with the file that sent it.
	 *
	 * @param \WP_Error $error Error.
	 * @return void
	 */
	public static function mail( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}
		$file   = Net::origin( array( __FILE__ ) );
		$origin = self::origin_of( $file );
		if ( ! $origin ) {
			return;
		}
		$data    = (array) $error->get_error_data();
		$subject = isset( $data['subject'] ) ? (string) $data['subject'] : '';
		Error_Log::add(
			'mail',
			sprintf( 'Email not sent: %s', $error->get_error_message() ),
			array_merge(
				self::origin_detail( $origin ),
				array(
					'level'   => 'error',
					'file'    => $file,
					'subject' => $subject,
				)
			)
		);
	}

	/**
	 * The service's own error message from a JSON answer (error.message, message, error, detail).
	 *
	 * @param string $body Body.
	 * @return string
	 */
	public static function service_message( $body ) {
		$json = json_decode( $body, true );
		if ( is_array( $json ) ) {
			foreach ( array( array( 'error', 'message' ), array( 'message' ), array( 'error_description' ), array( 'error' ), array( 'detail' ), array( 'description' ) ) as $keys ) {
				$value = $json;
				foreach ( $keys as $key ) {
					$value = is_array( $value ) && isset( $value[ $key ] ) ? $value[ $key ] : null;
				}
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					return Scrub::text( $value, 300 );
				}
			}
		}
		// A whole HTML page (a site's own 404 or error page) says nothing about the request,
		// and its title and menus would read as the error: log the status alone.
		if ( 1 === preg_match( '/^\s*(<\?xml[^>]*>\s*)?(<!doctype\s+html|<html[\s>])/i', $body ) ) {
			return '';
		}
		$text = trim( wp_strip_all_tags( $body ) );
		return '' === $text ? '' : Scrub::text( $text, 200 );
	}

	/**
	 * Log level of a PHP error.
	 *
	 * @param int $number Error level.
	 * @return string
	 */
	private static function level( $number ) {
		if ( in_array( $number, array( E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, 2048 ), true ) ) { // 2048: E_STRICT (deprecated constant in PHP 8.4).
			return 'notice';
		}
		if ( in_array( $number, array( E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING ), true ) ) {
			return 'warning';
		}
		return 'error';
	}

	/**
	 * PHP's name of an error level.
	 *
	 * @param int $number Error level.
	 * @return string
	 */
	private static function type_name( $number ) {
		$names = array(
			E_ERROR             => 'E_ERROR',
			E_WARNING           => 'E_WARNING',
			E_PARSE             => 'E_PARSE',
			E_NOTICE            => 'E_NOTICE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_CORE_WARNING      => 'E_CORE_WARNING',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_USER_WARNING      => 'E_USER_WARNING',
			E_USER_NOTICE       => 'E_USER_NOTICE',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED        => 'E_DEPRECATED',
			E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		);
		return isset( $names[ $number ] ) ? $names[ $number ] : 'E_' . (int) $number;
	}
}
