<?php
/**
 * Catches WPBot's errors for the error log:
 *
 * - PHP warnings and errors raised in WPBot's own files (notices and deprecations only when the
 *   owner turns them on). Other plugins' errors are never recorded, and WordPress and any earlier
 *   error handler still get every error as before.
 * - Fatal errors (crashes) in WPBot's files, at the end of the request.
 * - Outside requests started by WPBot code that fail (no answer, or HTTP 400 and above): AI
 *   providers, Meta, Telegram, Google and so on, with the service's own message.
 * - Emails WPBot tried to send that WordPress could not send.
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
	 * Install the handlers.
	 *
	 * @return void
	 */
	public static function hook() {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
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
	 * PHP error handler: record WPBot's own errors, then pass the error on.
	 *
	 * @param int    $number  Error level.
	 * @param string $message Message.
	 * @param string $file    File.
	 * @param int    $line    Line.
	 * @return bool
	 */
	public static function php( $number, $message, $file = '', $line = 0 ) {
		if ( ( error_reporting() & $number ) && self::is_own( $file ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting, PluginCheck.CodeAnalysis.PHPErrorReporting.DirectErrorReportingCall -- Read only: @-silenced errors are skipped.
			$level = self::level( $number );
			if ( 'notice' !== $level || Error_Log::settings()['notices'] ) {
				Error_Log::add(
					'php',
					$message,
					array(
						'level' => $level,
						'file'  => $file,
						'line'  => (int) $line,
						'type'  => self::type_name( $number ),
					)
				);
			}
		}
		if ( self::$previous ) {
			return (bool) call_user_func( self::$previous, $number, $message, $file, $line );
		}
		return false;
	}

	/**
	 * End of the request: record a fatal error in WPBot's files.
	 *
	 * @return void
	 */
	public static function shutdown() {
		$error = error_get_last();
		if ( ! is_array( $error ) || ! in_array( (int) $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			return;
		}
		$own = self::is_own( $error['file'] );
		// An uncaught exception reports the file where it was thrown; its trace can name WPBot.
		if ( ! $own ) {
			foreach ( self::paths() as $path ) {
				if ( false !== strpos( str_replace( '\\', '/', (string) $error['message'] ), $path . '/' ) ) {
					$own = true;
					break;
				}
			}
		}
		if ( ! $own ) {
			return;
		}
		$message = (string) $error['message'];
		$first   = strtok( $message, "\n" );
		Error_Log::add(
			'fatal',
			false !== $first ? $first : $message,
			array(
				'level' => 'error',
				'file'  => $error['file'],
				'line'  => (int) $error['line'],
				'type'  => self::type_name( (int) $error['type'] ),
				'stack' => $message,
			)
		);
	}

	/**
	 * A finished outside request: record it when WPBot started it and it failed.
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
		$origin = Net::origin();
		if ( ! self::is_own( $origin ) ) {
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
			array(
				'level'  => ( $failed || $status >= 500 || in_array( $status, array( 401, 403, 429 ), true ) ) ? 'error' : 'warning',
				'file'   => $origin,
				'host'   => $host,
				'path'   => $path,
				'status' => $status,
				'method' => isset( $args['method'] ) ? (string) $args['method'] : 'GET',
			)
		);
	}

	/**
	 * WordPress could not send an email: record it when WPBot sent it.
	 *
	 * @param \WP_Error $error Error.
	 * @return void
	 */
	public static function mail( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}
		$origin = Net::origin();
		if ( ! self::is_own( $origin ) ) {
			return;
		}
		$data    = (array) $error->get_error_data();
		$subject = isset( $data['subject'] ) ? (string) $data['subject'] : '';
		Error_Log::add(
			'mail',
			sprintf( 'Email not sent: %s', $error->get_error_message() ),
			array(
				'level'   => 'error',
				'file'    => $origin,
				'subject' => $subject,
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
