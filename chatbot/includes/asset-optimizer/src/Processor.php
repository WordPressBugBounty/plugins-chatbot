<?php
/**
 * Builds cached files: cache keys, locks, minification and validation.
 *
 * A visitor never waits for a build. A cache miss serves the original file
 * and queues the build; the queue runs after the response is sent
 * (fastcgi_finish_request() when available, otherwise a one-off cron event),
 * under a 60-second lock, once per file version.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Turns registered source files into cached copies.
 */
class Processor {

	/**
	 * One-off cron event that builds a queued file.
	 */
	const CRON_BUILD = 'wpbot_ao_build';

	/**
	 * Most files built after one response.
	 */
	const MAX_BUILDS_PER_REQUEST = 6;

	/**
	 * The module.
	 *
	 * @var Module
	 */
	private $module;

	/**
	 * Files to build after this response: id => [type, handle, relative path].
	 *
	 * @var array<string,array{0: string, 1: string, 2: string}>
	 */
	private $queue = array();

	/**
	 * Lookups done in this request: type:handle:src => url|''.
	 *
	 * @var array<string,string>
	 */
	private $memo = array();

	/**
	 * Constructor.
	 *
	 * @param Module $module The module.
	 */
	public function __construct( Module $module ) {
		$this->module = $module;
	}

	/**
	 * Hooks: the cron fallback builder.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::CRON_BUILD, array( $this, 'build_from_cron' ), 10, 3 );
	}

	/**
	 * Version of the minifiers. Part of every cache key, so a minifier change
	 * rebuilds every cached file.
	 *
	 * @return string
	 */
	public static function minifier_version() {
		return 'css3-js1';
	}

	/**
	 * Cache key of a source file: its path, modified time, size, the minifier
	 * version, the settings that change the output and the URL base.
	 *
	 * @param string $path Absolute source path.
	 * @return string 12 hex characters, or '' when the file cannot be read.
	 */
	public function key( $path ) {
		$mtime = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A vanished file simply has no key.
		$size  = @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Same.
		if ( false === $mtime || false === $size ) {
			return '';
		}
		$relative = substr( wp_normalize_path( $path ), strlen( $this->module->registry()->root_dir() ) );
		// The URL base cached stylesheets' url()s are rewritten against (changes when uploads move to a CDN).
		$base = $this->source_url( '' );
		return substr( md5( $relative . '|' . $mtime . '|' . $size . '|' . self::minifier_version() . '|' . $this->module->settings()->output_signature() . '|' . $base ), 0, 12 );
	}

	/**
	 * Whether a type is minified with the current settings.
	 *
	 * @param string $type "script" or "style".
	 * @return bool
	 */
	public function minifies( $type ) {
		$settings = $this->module->settings();
		return 'style' === $type ? (bool) $settings->get( 'minify_css' ) : ( (bool) $settings->get( 'minify_js' ) && $this->supports( 'script' ) );
	}

	/**
	 * Which types this version can build.
	 *
	 * @param string $type "script" or "style".
	 * @return bool
	 */
	public function supports( $type ) {
		return 'style' === $type || 'script' === $type;
	}

	/**
	 * URL of the cached copy of a registered file, or '' to keep the original
	 * (not registered, skipped, outside the plugin folder, not built yet, or
	 * failed its checks for this version). A miss queues the build.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $handle Handle.
	 * @param string $src    Source URL as registered.
	 * @return string
	 */
	public function cached_url( $type, $handle, $src ) {
		$memo = $type . ':' . $handle . ':' . $src;
		if ( isset( $this->memo[ $memo ] ) ) {
			return $this->memo[ $memo ];
		}
		$this->memo[ $memo ] = '';

		$registry = $this->module->registry();
		if ( ! $this->minifies( $type ) || ! $registry->has( $type, $handle ) || $registry->is_skipped( $type, $handle, $src ) ) {
			return '';
		}
		$path = $registry->resolve( $src, $type );
		if ( '' === $path ) {
			return '';
		}
		$key  = $this->key( $path );
		$file = Storage::file_name( $type, $handle, $key );
		if ( '' === $file || $this->is_blocked( $type, $handle, $key ) ) {
			return '';
		}
		$storage = $this->module->storage();
		if ( $storage->exists( $type, $file ) ) {
			$this->memo[ $memo ] = $storage->file_url( $type, $file );
			return $this->memo[ $memo ];
		}
		$this->enqueue_build( $type, $handle, substr( $path, strlen( $registry->root_dir() ) + 1 ) );
		return '';
	}

	/**
	 * Whether this version of a file failed its checks or was repaired, so the
	 * original is served until the source changes.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $handle Handle.
	 * @param string $key    Current key.
	 * @return bool
	 */
	private function is_blocked( $type, $handle, $key ) {
		$id = State::file_id( $type, $handle );
		foreach ( array( 'skipped', 'repaired' ) as $list ) {
			$items = (array) $this->module->state()->get( $list );
			if ( ( isset( $items[ $id ]['key'] ) && $items[ $id ]['key'] === $key ) || ( isset( $items[ $id ]['keys'] ) && in_array( $key, (array) $items[ $id ]['keys'], true ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Queue a build for after the response.
	 *
	 * @param string $type     "script" or "style".
	 * @param string $handle   Handle.
	 * @param string $relative Path relative to the host folder.
	 * @return void
	 */
	private function enqueue_build( $type, $handle, $relative ) {
		if ( array() === $this->queue ) {
			add_action( 'shutdown', array( $this, 'run_queue' ), PHP_INT_MAX );
		}
		$this->queue[ State::file_id( $type, $handle ) ] = array( $type, $handle, $relative );
	}

	/**
	 * After the response: build the queued files. With PHP-FPM (or LiteSpeed)
	 * the response is finished first so the visitor does not wait; otherwise
	 * each build is handed to a one-off cron event.
	 *
	 * @return void
	 */
	public function run_queue() {
		$queue       = array_slice( $this->queue, 0, self::MAX_BUILDS_PER_REQUEST, true );
		$this->queue = array();
		if ( array() === $queue || ! $this->module->storage()->is_writable() ) {
			return;
		}

		$finished = false;
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			$finished = fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			$finished = litespeed_finish_request();
		}

		foreach ( $queue as $item ) {
			if ( $finished ) {
				$this->build( $item[0], $item[1], $item[2] );
			} elseif ( ! wp_next_scheduled( self::CRON_BUILD, $item ) ) {
				wp_schedule_single_event( time(), self::CRON_BUILD, $item );
			}
		}
	}

	/**
	 * Cron fallback.
	 *
	 * @param string $type     "script" or "style".
	 * @param string $handle   Handle.
	 * @param string $relative Path relative to the host folder.
	 * @return void
	 */
	public function build_from_cron( $type, $handle, $relative ) {
		$this->build( (string) $type, (string) $handle, (string) $relative );
	}

	/**
	 * Build one file now: lock, read, minify, check, write atomically, record.
	 *
	 * @param string $type     "script" or "style".
	 * @param string $handle   Registered handle.
	 * @param string $relative Source path relative to the host folder.
	 * @return string '' on success, otherwise why the original is kept.
	 */
	public function build( $type, $handle, $relative ) {
		$registry = $this->module->registry();
		if ( ! in_array( $type, array( 'script', 'style' ), true ) || ! $this->supports( $type ) || ! $registry->has( $type, $handle, (string) $relative ) ) {
			return 'not a registered handle';
		}
		$root = $registry->root_dir();
		$path = realpath( $root . '/' . ltrim( (string) $relative, '/' ) );
		$path = false === $path ? '' : wp_normalize_path( $path );
		if ( '' === $root || '' === $path || 0 !== strpos( $path, $root . '/' ) || ! is_file( $path ) ) {
			return 'source outside the plugin folder';
		}
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ( 'style' === $type && 'css' !== $extension ) || ( 'script' === $type && 'js' !== $extension ) ) {
			return 'wrong file type';
		}

		$key  = $this->key( $path );
		$file = Storage::file_name( $type, $handle, $key );
		if ( '' === $file ) {
			return 'no cache key';
		}
		$storage = $this->module->storage();
		if ( $storage->exists( $type, $file ) ) {
			return '';
		}

		$lock = 'wpbot_ao_lock_' . $key;
		if ( get_transient( $lock ) ) {
			return 'locked';
		}
		set_transient( $lock, time(), 60 );

		$reason = $this->make( $type, $handle, $path, $key, $file, substr( $path, strlen( $root ) + 1 ) );

		delete_transient( $lock );
		return $reason;
	}

	/**
	 * Minify, check and write one file.
	 *
	 * @param string $type     "script" or "style".
	 * @param string $handle   Handle.
	 * @param string $path     Absolute source path.
	 * @param string $key      Cache key.
	 * @param string $file     Cache file name.
	 * @param string $relative Source path relative to the host folder.
	 * @return string '' on success, otherwise the reason.
	 */
	private function make( $type, $handle, $path, $key, $file, $relative ) {
		$source = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file inside the plugin folder.
		if ( false === $source ) {
			return $this->fail( $type, $handle, $key, __( 'the file could not be read', 'chatbot' ) );
		}

		// Protocol-relative, so a copy built over http works on https pages (and behind a CDN).
		if ( 'script' === $type && Js_Minifier::looks_minified( $source, basename( $path ) ) ) {
			// Already minified: copied as it is (with the marker below).
			$output = $source;
		} else {
			$base   = $this->source_url( ltrim( dirname( $relative ), './' ) );
			$output = $this->minify( $type, $source, $base );
			$reason = 'style' === $type ? Css_Minifier::check( $source, (string) $output ) : ( new Js_Minifier() )->check( $source, $output );
			if ( '' !== $reason ) {
				return $this->fail( $type, $handle, $key, $reason );
			}
		}

		/**
		 * Adjust a minified file before it is written.
		 *
		 * @param string $output Minified code.
		 * @param string $handle Handle.
		 * @param string $type   "script" or "style".
		 */
		$output = (string) apply_filters( 'wpbot_asset_cache_minified', $output, $handle, $type );
		if ( 'script' === $type ) {
			$output .= self::marker( $handle );
		}

		if ( ! $this->module->storage()->write( $type, $file, $output ) ) {
			$this->module->state()->set( 'last_error', __( 'A cached file could not be written; the original is served.', 'chatbot' ) );
			return 'write failed';
		}

		$this->module->state()->record_file(
			$type,
			$handle,
			array(
				'path'     => $relative,
				'key'      => $key,
				'file'     => $file,
				'original' => strlen( $source ),
				'size'     => strlen( $output ),
				'built'    => time(),
			)
		);
		return '';
	}

	/**
	 * URL of a path inside the host folder, as cached files should refer to it.
	 *
	 * Root-relative ("/wp-content/plugins/…") when the cache folder is on the
	 * same host as the plugin, so a copy works under every host name the site
	 * answers to, over http and https, and after a move from staging.
	 * Protocol-relative ("//host/…") when uploads are served from another host
	 * (for example a CDN).
	 *
	 * @param string $relative Path relative to the host folder.
	 * @return string
	 */
	public function source_url( $relative ) {
		$root       = trailingslashit( $this->module->host()->get_root_url() ) . ltrim( (string) $relative, '/' );
		$cache_host = wp_parse_url( $this->module->storage()->base()['url'], PHP_URL_HOST );
		$root_host  = wp_parse_url( $root, PHP_URL_HOST );
		if ( null === $root_host || strtolower( (string) $cache_host ) === strtolower( (string) $root_host ) ) {
			return (string) wp_parse_url( $root, PHP_URL_PATH );
		}
		return (string) preg_replace( '#^https?:#i', '', $root );
	}

	/**
	 * Run the minifier for a type.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $source Source code.
	 * @param string $base   URL of the source file's folder.
	 * @return string
	 */
	protected function minify( $type, $source, $base ) {
		return 'style' === $type ? ( new Css_Minifier() )->minify( $source, $base ) : ( new Js_Minifier() )->minify( $source );
	}

	/**
	 * Line appended to every cached script: when the browser guard finds it
	 * missing, the file failed to load or run.
	 *
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function marker( $handle ) {
		return "\n;(window.wpbotAoRan=window.wpbotAoRan||{})[" . wp_json_encode( (string) $handle ) . "]=1;\n";
	}

	/**
	 * Keep the original for this version of a file and say why.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $handle Handle.
	 * @param string $key    Key.
	 * @param string $reason Reason.
	 * @return string The reason.
	 */
	private function fail( $type, $handle, $key, $reason ) {
		$this->module->state()->log(
			'skipped',
			State::file_id( $type, $handle ),
			array(
				'key'    => $key,
				'reason' => $reason,
			)
		);
		return $reason;
	}

	/**
	 * Build every file in the current file map again now (Rebuild now). The
	 * cache was cleared first, so this writes fresh copies.
	 *
	 * @return array{built: int, failed: int}
	 */
	public function rebuild_all() {
		$this->module->state()->refresh();
		$files  = (array) $this->module->state()->get( 'files' );
		$result = array(
			'built'  => 0,
			'failed' => 0,
		);
		if ( array() === $files ) {
			return $result;
		}
		$this->module->clear();
		foreach ( $files as $id => $entry ) {
			list( $type, $handle ) = array_pad( explode( ':', (string) $id, 2 ), 2, '' );
			if ( empty( $entry['path'] ) ) {
				continue;
			}
			if ( '' === $this->build( $type, $handle, (string) $entry['path'] ) ) {
				++$result['built'];
			} else {
				++$result['failed'];
			}
		}
		return $result;
	}
}
