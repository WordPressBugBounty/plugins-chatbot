<?php
/**
 * Which handles the cache may process, and where their files are.
 *
 * Only WPBot's own files are processed: handles come from the host adapter
 * (and the Pro module, through `wpbot_asset_cache_handles`), and a file is
 * read only when its resolved real path sits inside the host plugin folder.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

use QuantumCloud\AssetOptimizer\Host\Host_Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * Handle registry and path resolution.
 */
class Registry {

	/**
	 * Handle of the module's browser guard. Never served from the cache: the
	 * safety net must work when the cache folder has just been emptied.
	 */
	const GUARD = 'wpbot-ao-guard';

	/**
	 * Handle of the module's cached-page freshness script (never cached either).
	 */
	const FRESH = 'wpbot-ao-fresh';

	/**
	 * Host adapter.
	 *
	 * @var Host_Adapter
	 */
	private $host;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Handles per type, once filtered.
	 *
	 * @var array{script: string[], style: string[]}|null
	 */
	private $handles = null;

	/**
	 * Constructor.
	 *
	 * @param Host_Adapter $host     Host adapter.
	 * @param Settings     $settings Settings.
	 */
	public function __construct( Host_Adapter $host, Settings $settings ) {
		$this->host     = $host;
		$this->settings = $settings;
	}

	/**
	 * All registered handles.
	 *
	 * @return array{script: string[], style: string[]}
	 */
	public function all() {
		if ( null === $this->handles ) {
			$handles = $this->host->get_handles();

			/**
			 * Add or remove handles the cache may process. Only files inside the
			 * host plugin's folder are ever processed, whatever is added here.
			 *
			 * @param array{script: string[], style: string[]} $handles Handles per type.
			 */
			$handles = apply_filters( 'wpbot_asset_cache_handles', $handles );

			$clean = array(
				'script' => array(),
				'style'  => array(),
			);
			foreach ( array_keys( $clean ) as $type ) {
				$list = isset( $handles[ $type ] ) ? (array) $handles[ $type ] : array();
				foreach ( $list as $handle ) {
					if ( is_string( $handle ) && '' !== $handle ) {
						$clean[ $type ][ $handle ] = $handle;
					}
				}
				$clean[ $type ] = array_values( $clean[ $type ] );
			}
			$this->handles = $clean;
		}
		return $this->handles;
	}

	/**
	 * Forget the filtered list (after a filter was added late).
	 *
	 * @return void
	 */
	public function reset() {
		$this->handles = null;
	}

	/**
	 * Whether a handle is registered for a type.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $handle Handle.
	 * @param string $path   Source path relative to the host folder, when known (passed to the filter).
	 * @return bool
	 */
	public function has( $type, $handle, $path = '' ) {
		$all = $this->all();
		if ( isset( $all[ $type ] ) && in_array( (string) $handle, $all[ $type ], true ) ) {
			return true;
		}

		/**
		 * Allow a handle that is not in the list, by rule (for example handles
		 * an add-on builds at run time). The file must still sit inside the
		 * host plugin folder to be processed.
		 *
		 * @param bool   $allowed Whether to allow it.
		 * @param string $handle  Handle.
		 * @param string $type    "script" or "style".
		 * @param string $path    Source path relative to the host folder, when known ('' on page views,
		 *                        where the registered URL is available instead).
		 */
		return (bool) apply_filters( 'wpbot_asset_cache_handle_allowed', false, (string) $handle, $type, (string) $path );
	}

	/**
	 * Whether a registered file must be left untouched: listed by handle or
	 * file name in "Files to leave untouched", or skipped through the filter.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $handle Handle.
	 * @param string $src    Source URL or path.
	 * @return bool
	 */
	public function is_skipped( $type, $handle, $src = '' ) {
		$skip     = false;
		$file     = '' !== $src ? basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ) : '';
		$relative = '' !== $src ? $this->relative_path( $src ) : '';
		foreach ( (array) $this->settings->get( 'skip' ) as $line ) {
			if ( $line === $handle || ( '' !== $file && $line === $file ) || ( '' !== $relative && $line === $relative ) ) {
				$skip = true;
				break;
			}
		}

		/**
		 * Leave one file untouched.
		 *
		 * @param bool   $skip   Whether to skip it.
		 * @param string $handle Handle.
		 * @param string $type   "script" or "style".
		 */
		return (bool) apply_filters( 'wpbot_asset_cache_skip', $skip, $handle, $type );
	}

	/**
	 * Real path of the host plugin folder.
	 *
	 * @return string Empty when unknown.
	 */
	public function root_dir() {
		$root = realpath( $this->host->get_root_dir() );
		return false === $root ? '' : wp_normalize_path( $root );
	}

	/**
	 * Local file behind a source URL, only when it is a .js or .css file
	 * inside the host plugin folder. Remote URLs and anything outside are refused.
	 *
	 * @param string $src  Source URL as registered.
	 * @param string $type "script" or "style".
	 * @return string Absolute normalised path, or '' when it may not be processed.
	 */
	public function resolve( $src, $type ) {
		$relative = $this->relative_path( $src );
		if ( '' === $relative ) {
			return '';
		}
		$root = $this->root_dir();
		if ( '' === $root ) {
			return '';
		}
		$real = realpath( $root . '/' . $relative );
		if ( false === $real ) {
			return '';
		}
		$real = wp_normalize_path( $real );
		if ( 0 !== strpos( $real, $root . '/' ) || ! is_file( $real ) || ! is_readable( $real ) ) {
			return '';
		}
		$extension = strtolower( pathinfo( $real, PATHINFO_EXTENSION ) );
		if ( ( 'script' === $type && 'js' !== $extension ) || ( 'style' === $type && 'css' !== $extension ) ) {
			return '';
		}
		return $real;
	}

	/**
	 * Path of a source URL relative to the host plugin folder.
	 *
	 * @param string $src Source URL.
	 * @return string Relative path without query, or '' when the URL is not in the folder.
	 */
	public function relative_path( $src ) {
		$src = (string) $src;
		if ( '' === $src ) {
			return '';
		}
		if ( 0 === strpos( $src, '//' ) ) {
			$src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
		}
		$path = (string) wp_parse_url( $src, PHP_URL_PATH );
		$root = (string) wp_parse_url( untrailingslashit( $this->host->get_root_url() ), PHP_URL_PATH );
		$host = wp_parse_url( $src, PHP_URL_HOST );
		$home = wp_parse_url( $this->host->get_root_url(), PHP_URL_HOST );
		if ( '' === $path || '' === $root || ( null !== $host && strtolower( (string) $host ) !== strtolower( (string) $home ) ) ) {
			return '';
		}
		$root = trailingslashit( $root );
		if ( 0 !== strpos( $path, $root ) ) {
			return '';
		}
		$relative = rawurldecode( substr( $path, strlen( $root ) ) );
		if ( '' === $relative || false !== strpos( $relative, '..' ) || false !== strpos( $relative, "\0" ) ) {
			return '';
		}
		return $relative;
	}
}
