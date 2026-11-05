<?php
/**
 * The cache folder: location, writability, atomic writes and cleanup.
 *
 * Files live in uploads/wpbot-cache/<package>/js|css/. Only .js, .css,
 * index.php and .htaccess are ever created, and file names are built only
 * from the handle registry and a hash.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * File access for the cache folder.
 */
class Storage {

	/**
	 * Package slug ("pro" or "free").
	 *
	 * @var string
	 */
	private $package;

	/**
	 * Resolved base path and URL.
	 *
	 * @var array{path: string, url: string}|null
	 */
	private $base = null;

	/**
	 * Direct filesystem access.
	 *
	 * @var \WP_Filesystem_Direct|null
	 */
	private $fs = null;

	/**
	 * Constructor.
	 *
	 * @param string $package Package slug.
	 */
	public function __construct( $package ) {
		$this->package = sanitize_key( $package );
	}

	/**
	 * Folder path and URL of this package's cache.
	 *
	 * @return array{path: string, url: string}
	 */
	public function base() {
		if ( null === $this->base ) {
			$uploads = wp_upload_dir( null, false );
			$base    = array(
				'path' => trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . 'wpbot-cache/' . $this->package,
				'url'  => trailingslashit( set_url_scheme( $uploads['baseurl'] ) ) . 'wpbot-cache/' . $this->package,
			);

			/**
			 * Move the cache folder.
			 *
			 * @param array{path: string, url: string} $base    Folder path and URL (no trailing slash).
			 * @param string                           $package Package slug.
			 */
			$filtered = apply_filters( 'wpbot_asset_cache_dir', $base, $this->package );
			if ( is_array( $filtered ) && ! empty( $filtered['path'] ) && ! empty( $filtered['url'] ) ) {
				$base = array(
					'path' => untrailingslashit( wp_normalize_path( (string) $filtered['path'] ) ),
					'url'  => untrailingslashit( (string) $filtered['url'] ),
				);
			}
			$this->base = $base;
		}
		return $this->base;
	}

	/**
	 * Folder path of the cache.
	 *
	 * @return string
	 */
	public function path() {
		$base = $this->base();
		return $base['path'];
	}

	/**
	 * The filesystem.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	private function fs() {
		if ( null === $this->fs ) {
			if ( ! class_exists( '\WP_Filesystem_Direct', false ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			}
			$this->fs = new \WP_Filesystem_Direct( null );
		}
		return $this->fs;
	}

	/**
	 * File permissions WordPress uses.
	 *
	 * @return int
	 */
	private function file_mode() {
		return defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;
	}

	/**
	 * Create the folder structure, index.php and .htaccess when missing.
	 *
	 * @return bool Whether the folder is ready for writing.
	 */
	public function ensure() {
		$path = $this->path();
		foreach ( array( dirname( $path ), $path, $path . '/js', $path . '/css' ) as $dir ) {
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				return false;
			}
			if ( ! file_exists( $dir . '/index.php' ) ) {
				$this->fs()->put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n", $this->file_mode() );
			}
		}
		if ( ! file_exists( $path . '/.htaccess' ) ) {
			$this->fs()->put_contents( $path . '/.htaccess', $this->htaccess(), $this->file_mode() );
		}
		return $this->is_writable();
	}

	/**
	 * Apache/LiteSpeed rules: long browser caching for the versioned files.
	 * The 30-day lifetime matches the cleanup age; repair in place rewrites a
	 * file at the same URL, so files are not marked immutable.
	 *
	 * @return string
	 */
	public function htaccess() {
		return implode(
			"\n",
			array(
				'# WPBot asset cache: cached copies of WPBot files. Names change whenever a file changes.',
				'Options -Indexes',
				'<IfModule mod_headers.c>',
				'	<FilesMatch "\.(js|css)$">',
				'		Header set Cache-Control "public, max-age=2592000"',
				'	</FilesMatch>',
				'</IfModule>',
				'<IfModule mod_expires.c>',
				'	ExpiresActive On',
				'	ExpiresByType text/css "access plus 30 days"',
				'	ExpiresByType application/javascript "access plus 30 days"',
				'	ExpiresByType text/javascript "access plus 30 days"',
				'</IfModule>',
				'',
			)
		);
	}

	/**
	 * Whether files can be written: the folder exists (or its nearest
	 * existing parent does) and is writable.
	 *
	 * @return bool
	 */
	public function is_writable() {
		$dir = $this->path();
		while ( ! is_dir( $dir ) ) {
			$parent = dirname( $dir );
			if ( $parent === $dir ) {
				return false;
			}
			$dir = $parent;
		}
		if ( ! wp_is_writable( $dir ) ) {
			return false;
		}
		$path = $this->path();
		foreach ( array( '/js', '/css' ) as $sub ) {
			if ( is_dir( $path . $sub ) && ! wp_is_writable( $path . $sub ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Valid cache file name for a handle and key: handle-key.min.js|css.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $handle Registry handle.
	 * @param string $key    Cache key (hex).
	 * @return string Empty when the parts are not valid.
	 */
	public static function file_name( $type, $handle, $key ) {
		$handle = strtolower( (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $handle ) );
		if ( '' === $handle || ! preg_match( '/^[a-f0-9]{8,32}$/', (string) $key ) ) {
			return '';
		}
		return substr( $handle, 0, 80 ) . '-' . $key . ( 'script' === $type ? '.min.js' : '.min.css' );
	}

	/**
	 * Sub-folder for a type.
	 *
	 * @param string $type "script" or "style".
	 * @return string
	 */
	private static function sub( $type ) {
		return 'script' === $type ? 'js' : 'css';
	}

	/**
	 * Absolute path of a cache file.
	 *
	 * @param string $type "script" or "style".
	 * @param string $file File name from file_name().
	 * @return string
	 */
	public function file_path( $type, $file ) {
		return $this->path() . '/' . self::sub( $type ) . '/' . basename( $file );
	}

	/**
	 * Public URL of a cache file.
	 *
	 * @param string $type "script" or "style".
	 * @param string $file File name from file_name().
	 * @return string
	 */
	public function file_url( $type, $file ) {
		$base = $this->base();
		return $base['url'] . '/' . self::sub( $type ) . '/' . rawurlencode( basename( $file ) );
	}

	/**
	 * Whether a cache file exists.
	 *
	 * @param string $type "script" or "style".
	 * @param string $file File name.
	 * @return bool
	 */
	public function exists( $type, $file ) {
		return '' !== $file && is_file( $this->file_path( $type, $file ) );
	}

	/**
	 * Write a cache file atomically: a temporary file in the same folder,
	 * then a rename, so a visitor never gets half a file.
	 *
	 * @param string $type     "script" or "style".
	 * @param string $file     File name from file_name().
	 * @param string $contents File contents.
	 * @return bool
	 */
	public function write( $type, $file, $contents ) {
		if ( '' === $file || ! preg_match( '/\.min\.(js|css)$/', $file ) || ! $this->ensure() ) {
			return false;
		}
		$target = $this->file_path( $type, $file );
		$temp   = $target . '.' . wp_generate_password( 8, false ) . '.tmp';
		if ( ! $this->fs()->put_contents( $temp, $contents, $this->file_mode() ) ) {
			$this->fs()->delete( $temp );
			return false;
		}
		if ( ! $this->fs()->move( $temp, $target, true ) ) {
			$this->fs()->delete( $temp );
			return false;
		}
		return true;
	}

	/**
	 * Every cache file: name => [type, path, size, mtime].
	 *
	 * @return array<string,array{type: string, path: string, size: int, mtime: int}>
	 */
	public function files() {
		$out = array();
		foreach ( array( 'script', 'style' ) as $type ) {
			$dir = $this->path() . '/' . self::sub( $type );
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			foreach ( (array) glob( $dir . '/*.min.' . self::sub( $type ) ) as $path ) {
				if ( ! is_string( $path ) || ! is_file( $path ) ) {
					continue;
				}
				$out[ basename( $path ) ] = array(
					'type'  => $type,
					'path'  => $path,
					'size'  => (int) filesize( $path ),
					'mtime' => (int) filemtime( $path ),
				);
			}
		}
		return $out;
	}

	/**
	 * Number and total size of cache files.
	 *
	 * @return array{count: int, bytes: int}
	 */
	public function stats() {
		$files = $this->files();
		return array(
			'count' => count( $files ),
			'bytes' => (int) array_sum( wp_list_pluck( $files, 'size' ) ),
		);
	}

	/**
	 * Delete every cache file of this package (Clear). The folder, its
	 * index.php and .htaccess stay.
	 *
	 * @return int Files deleted.
	 */
	public function clear() {
		$deleted = 0;
		foreach ( $this->files() as $info ) {
			if ( $this->fs()->delete( $info['path'] ) ) {
				++$deleted;
			}
		}
		foreach ( array( 'js', 'css' ) as $sub ) {
			foreach ( (array) glob( $this->path() . '/' . $sub . '/*.tmp' ) as $temp ) {
				if ( is_string( $temp ) ) {
					$this->fs()->delete( $temp );
				}
			}
		}
		return $deleted;
	}

	/**
	 * Daily cleanup: delete files older than $max_age that are neither the
	 * current nor the previous version of any file, and stale temporary files.
	 *
	 * @param string[] $keep_keys Keys in use.
	 * @param int      $max_age   Age in seconds (default 30 days).
	 * @return int Files deleted.
	 */
	public function cleanup( array $keep_keys, $max_age = 2592000 ) {
		$deleted = 0;
		$cutoff  = time() - (int) $max_age;
		foreach ( $this->files() as $name => $info ) {
			$key = preg_match( '/-([a-f0-9]{8,32})\.min\.(js|css)$/', $name, $m ) ? $m[1] : '';
			if ( in_array( $key, $keep_keys, true ) || $info['mtime'] > $cutoff ) {
				continue;
			}
			if ( $this->fs()->delete( $info['path'] ) ) {
				++$deleted;
			}
		}
		foreach ( array( 'js', 'css' ) as $sub ) {
			foreach ( (array) glob( $this->path() . '/' . $sub . '/*.tmp' ) as $temp ) {
				if ( is_string( $temp ) && filemtime( $temp ) < time() - HOUR_IN_SECONDS ) {
					$this->fs()->delete( $temp );
				}
			}
		}
		return $deleted;
	}

	/**
	 * Remove this package's whole cache folder (uninstall). Never touches
	 * the other package's subfolder.
	 *
	 * @return void
	 */
	public function destroy() {
		$path = $this->path();
		$own  = 'wpbot-cache/' . $this->package;
		if ( is_dir( $path ) && substr( $path, -strlen( $own ) ) === $own ) {
			$this->fs()->rmdir( $path, true );
		}
		$parent = dirname( $path );
		if ( is_dir( $parent ) && 'wpbot-cache' === basename( $parent ) ) {
			$left = array_diff( (array) scandir( $parent ), array( '.', '..', 'index.php' ) );
			if ( array() === $left ) {
				$this->fs()->rmdir( $parent, true );
			}
		}
	}
}
