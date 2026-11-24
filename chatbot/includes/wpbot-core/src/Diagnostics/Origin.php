<?php
/**
 * Where an error came from (owner, 19 November 2026: "log all errors, but clearly mark which
 * come from WPBot and which from outside WPBot").
 *
 * - "wpbot": a file inside a WPBot folder (Error_Capture::paths()).
 * - "outside": anything else, with the part of the site it belongs to: a plugin, a must-use
 *   plugin, the theme, WordPress itself, or other code (wp-config.php, drop-ins).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Diagnostics;

defined( 'ABSPATH' ) || exit;

/**
 * Error origin.
 */
final class Origin {

	/**
	 * From WPBot.
	 */
	const WPBOT = 'wpbot';

	/**
	 * From outside WPBot.
	 */
	const OUTSIDE = 'outside';

	/**
	 * Component (and slug) of WordPress's own files.
	 */
	const WORDPRESS = 'wordpress'; // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- A slug, not the name.

	/**
	 * Origins a stored error can have ('' = recorded before WPBot Core told them apart).
	 */
	const ALL = array( self::WPBOT, self::OUTSIDE );

	/**
	 * Plugin names by folder, read once per request (labels only).
	 *
	 * @var array<string,string>|null
	 */
	private static $plugin_names = null;

	/**
	 * Folders as configured and by real path, by folder (see forms()).
	 *
	 * @var array<string,string[]>
	 */
	private static $forms = array();

	/**
	 * Where a file belongs.
	 *
	 * @param string $file Absolute path ('' when only WordPress core was on the stack).
	 * @return array{origin:string,component:string,slug:string}
	 *         component: wpbot, plugin, mu-plugin, theme, WordPress core (see the class
	 *         constant) or other;
	 *         slug: the WPBot part ("chatbot", "core"), plugin or theme folder, or file name.
	 */
	public static function of_file( $file ) {
		$file = self::normalise( $file );
		if ( '' === $file ) {
			return self::outside( self::WORDPRESS, self::WORDPRESS );
		}
		if ( Error_Capture::is_own( $file ) ) {
			return array(
				'origin'    => self::WPBOT,
				'component' => 'wpbot',
				'slug'      => Error_Log::module_of( Scrub::path( $file ) ),
			);
		}
		$file = self::unlink_plugin( $file );
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$slug = self::first_part( $file, WPMU_PLUGIN_DIR );
			if ( '' !== $slug ) {
				return self::outside( 'mu-plugin', $slug );
			}
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$slug = self::first_part( $file, WP_PLUGIN_DIR );
			if ( '' !== $slug ) {
				return self::outside( 'plugin', $slug );
			}
		}
		foreach ( self::theme_roots() as $root ) {
			$slug = self::first_part( $file, $root );
			if ( '' !== $slug ) {
				return self::outside( 'theme', $slug );
			}
		}
		if ( defined( 'ABSPATH' ) && defined( 'WPINC' ) ) {
			foreach ( array( ABSPATH . WPINC, ABSPATH . 'wp-admin' ) as $core ) {
				foreach ( self::forms( $core ) as $path ) {
					if ( 0 === strpos( $file, $path . '/' ) ) {
						return self::outside( self::WORDPRESS, self::WORDPRESS );
					}
				}
			}
			// WordPress's own entry files (index.php, wp-cron.php, wp-login.php…), not wp-config.php.
			$name = basename( $file );
			if ( in_array( dirname( $file ), self::forms( ABSPATH ), true ) && 'wp-config.php' !== $name && ( 'index.php' === $name || 'xmlrpc.php' === $name || 0 === strpos( $name, 'wp-' ) ) ) {
				return self::outside( self::WORDPRESS, self::WORDPRESS );
			}
		}
		return self::outside( 'other', substr( sanitize_key( basename( $file, '.php' ) ), 0, 40 ) );
	}

	/**
	 * Words for a stored error's origin: "WPBot", "Outside WPBot", or "" when not known.
	 *
	 * @param array $item Error log item (origin).
	 * @return string
	 */
	public static function label( array $item ) {
		$origin = isset( $item['origin'] ) ? (string) $item['origin'] : '';
		if ( self::WPBOT === $origin ) {
			return __( 'WPBot', 'chatbot' );
		}
		return self::OUTSIDE === $origin ? __( 'Outside WPBot', 'chatbot' ) : '';
	}

	/**
	 * The part of the site an outside error came from, in words: "Jetpack (plugin)",
	 * "Foodie Blocks (theme)", "WordPress", "object-cache (other code)".
	 *
	 * @param array $item Error log item (origin, module, detail.component).
	 * @return string '' for WPBot's own errors and errors of unknown origin.
	 */
	public static function part( array $item ) {
		if ( ! isset( $item['origin'] ) || self::OUTSIDE !== $item['origin'] ) {
			return '';
		}
		$slug      = isset( $item['module'] ) ? (string) $item['module'] : '';
		$component = isset( $item['detail']['component'] ) ? (string) $item['detail']['component'] : '';
		switch ( $component ) {
			case 'plugin':
				/* translators: %s: plugin name. */
				return sprintf( __( '%s (plugin)', 'chatbot' ), self::plugin_name( $slug ) );
			case 'mu-plugin':
				/* translators: %s: must-use plugin file or folder. */
				return sprintf( __( '%s (must-use plugin)', 'chatbot' ), $slug );
			case 'theme':
				/* translators: %s: theme name. */
				return sprintf( __( '%s (theme)', 'chatbot' ), self::theme_name( $slug ) );
			case self::WORDPRESS:
				return __( 'WordPress', 'chatbot' );
			default:
				/* translators: %s: file name. */
				return '' !== $slug ? sprintf( __( '%s (other code)', 'chatbot' ), $slug ) : __( 'Other code', 'chatbot' );
		}
	}

	/**
	 * An outside origin.
	 *
	 * @param string $component Component.
	 * @param string $slug      Slug.
	 * @return array{origin:string,component:string,slug:string}
	 */
	private static function outside( $component, $slug ) {
		return array(
			'origin'    => self::OUTSIDE,
			'component' => $component,
			'slug'      => $slug,
		);
	}

	/**
	 * The first folder (or file, without .php) of a path below a root, '' when not below it.
	 *
	 * @param string $file Normalised absolute path.
	 * @param string $root Root folder.
	 * @return string
	 */
	private static function first_part( $file, $root ) {
		$found = '';
		foreach ( self::forms( $root ) as $path ) {
			if ( 0 === strpos( $file, $path . '/' ) ) {
				$found = $path;
				break;
			}
		}
		if ( '' === $found ) {
			return '';
		}
		$rest  = substr( $file, strlen( $found ) + 1 );
		$first = explode( '/', $rest, 2 );
		$name  = 1 === count( $first ) ? basename( $first[0], '.php' ) : $first[0];
		return substr( sanitize_key( $name ), 0, 40 );
	}

	/**
	 * A folder as configured and by its real path (PHP reports real paths; on some hosts ABSPATH,
	 * WP_CONTENT_DIR or the plugin folder go through a symlink, often only under WP-CLI).
	 *
	 * @param string $dir Folder.
	 * @return string[] Normalised, without duplicates or empty values.
	 */
	private static function forms( $dir ) {
		$dir = (string) $dir;
		if ( ! isset( self::$forms[ $dir ] ) ) {
			$forms = array( self::normalise( $dir ) );
			$real  = '' !== $dir ? realpath( $dir ) : false;
			if ( false !== $real ) {
				$forms[] = self::normalise( $real );
			}
			self::$forms[ $dir ] = array_values( array_unique( array_filter( $forms ) ) );
		}
		return self::$forms[ $dir ];
	}

	/**
	 * A symlinked plugin reports its real path; WordPress keeps the link (wp_register_plugin_realpath()).
	 *
	 * @param string $file Normalised absolute path.
	 * @return string
	 */
	private static function unlink_plugin( $file ) {
		global $wp_plugin_paths;
		foreach ( (array) $wp_plugin_paths as $link => $real ) {
			$real = self::normalise( $real );
			if ( '' !== $real && 0 === strpos( $file, $real . '/' ) ) {
				return self::normalise( $link ) . substr( $file, strlen( $real ) );
			}
		}
		return $file;
	}

	/**
	 * Theme folders.
	 *
	 * @return string[]
	 */
	private static function theme_roots() {
		global $wp_theme_directories;
		$roots = (array) $wp_theme_directories;
		if ( ! $roots && defined( 'WP_CONTENT_DIR' ) ) {
			$roots = array( WP_CONTENT_DIR . '/themes' );
		}
		return array_filter( array_map( array( __CLASS__, 'normalise' ), $roots ) );
	}

	/**
	 * A plugin's name from its folder (or single file).
	 *
	 * @param string $slug Folder.
	 * @return string
	 */
	private static function plugin_name( $slug ) {
		if ( null === self::$plugin_names ) {
			self::$plugin_names = array();
			if ( ! function_exists( 'get_plugins' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( function_exists( 'get_plugins' ) ) {
				foreach ( (array) get_plugins() as $file => $data ) {
					$key = sanitize_key( false !== strpos( $file, '/' ) ? dirname( $file ) : basename( $file, '.php' ) );
					if ( ! isset( self::$plugin_names[ $key ] ) && ! empty( $data['Name'] ) ) {
						self::$plugin_names[ $key ] = (string) $data['Name'];
					}
				}
			}
		}
		return isset( self::$plugin_names[ $slug ] ) ? self::$plugin_names[ $slug ] : $slug;
	}

	/**
	 * A theme's name from its folder.
	 *
	 * @param string $slug Folder.
	 * @return string
	 */
	private static function theme_name( $slug ) {
		if ( function_exists( 'wp_get_theme' ) ) {
			$theme = wp_get_theme( $slug );
			if ( $theme->exists() ) {
				return (string) $theme->get( 'Name' );
			}
		}
		return $slug;
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
