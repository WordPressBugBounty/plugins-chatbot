<?php
/**
 * Optional combine (off by default, "Advanced").
 *
 * Groups neighbouring WPBot files of one print pass (head or footer) into one
 * bundle: scripts with the same loading strategy, styles with the same media.
 * A file with an "after" inline script or style, @import, conditional or RTL
 * data stays separate and ends a group; a script with a "before" inline script
 * can only start one. Each member handle becomes an empty alias of the bundle
 * (it is marked done), so add-ons that depend on it still load after it.
 * Localised data and "before" scripts of the members print before the bundle,
 * in order. A bundle is the concatenation of the members' cached copies and is
 * built after the response, like every other cached file; until it exists the
 * members print separately.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Bundles.
 */
class Combine {

	const PREFIX = 'wpbot-ao-bundle-';

	/**
	 * Bundles kept in the state (current and recent ones).
	 */
	const MAX_BUNDLES = 40;

	/**
	 * The module.
	 *
	 * @var Module
	 */
	private $module;

	/**
	 * Front end.
	 *
	 * @var Frontend
	 */
	private $frontend;

	/**
	 * Bundles printed on this page: bundle handle => [type, member handles, member src URLs].
	 *
	 * @var array<string,array{0: string, 1: string[], 2: string[]}>
	 */
	private $printed = array();

	/**
	 * Bundles to build after this response: key => [type, members].
	 *
	 * @var array<string,array{0: string, 1: array<string,string>}>
	 */
	private $queue = array();

	/**
	 * Constructor.
	 *
	 * @param Module   $module   The module.
	 * @param Frontend $frontend Front end.
	 */
	public function __construct( Module $module, Frontend $frontend ) {
		$this->module   = $module;
		$this->frontend = $frontend;
	}

	/**
	 * Hooks (page views, only when the setting is on).
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'print_scripts_array', array( $this, 'scripts' ), 30 );
		add_filter( 'print_styles_array', array( $this, 'styles' ), 30 );
	}

	/**
	 * Server-side hooks needed whether or not this request combines: the
	 * cron builder and healing bundles when a member is repaired.
	 *
	 * @param Module $module The module.
	 * @return void
	 */
	public static function register_always( Module $module ) {
		add_action(
			'wpbot_ao_build_bundle',
			static function ( $type, $key ) use ( $module ) {
				self::build( $module, (string) $type, (string) $key );
			},
			10,
			2
		);
		add_action(
			'qcld_wpbot_ao_repaired',
			static function ( $handle, $type ) use ( $module ) {
				self::heal( $module, (string) $handle, (string) $type );
			},
			10,
			2
		);
	}

	/**
	 * The bundle a handle was printed in on this page, or ''.
	 *
	 * @param string $handle Member handle.
	 * @return string
	 */
	public function bundle_of( $handle ) {
		foreach ( $this->printed as $bundle => $info ) {
			if ( in_array( $handle, $info[1], true ) ) {
				return $bundle;
			}
		}
		return '';
	}

	/**
	 * Bundles printed on this page.
	 *
	 * @return array<string,array{0: string, 1: string[], 2: string[]}>
	 */
	public function printed() {
		return $this->printed;
	}

	/**
	 * Combine scripts about to print.
	 *
	 * @param string[] $to_do Handles.
	 * @return string[]
	 */
	public function scripts( $to_do ) {
		$scripts = wp_scripts();
		if ( ! is_array( $to_do ) || ! $this->frontend->on() || ! $this->module->settings()->get( 'minify_js' ) || ! method_exists( $scripts, 'get_inline_script_data' ) ) {
			return $to_do;
		}
		$groups = array();
		$group  = array();
		$sig    = null;
		$out    = array();
		// The head pass resolves footer scripts too and only defers them: combine only what prints now.
		$pass = doing_action( 'wp_print_footer_scripts' ) || did_action( 'wp_print_footer_scripts' ) ? 1 : 0;
		foreach ( $to_do as $handle ) {
			$ok = ( isset( $scripts->groups[ $handle ] ) ? (int) $scripts->groups[ $handle ] : 0 ) === $pass ? $this->combinable_script( $scripts, $handle ) : false;
			if ( false !== $ok ) {
				$before = '' !== (string) $scripts->get_inline_script_data( $handle, 'before' );
				if ( array() !== $group && ( $ok !== $sig || $before ) ) {
					$out[] = $group;
					$group = array();
				}
				$group[] = $handle;
				$sig     = $ok;
				continue;
			}
			if ( array() !== $group ) {
				$out[] = $group;
				$group = array();
			}
			$out[] = $handle;
		}
		if ( array() !== $group ) {
			$out[] = $group;
		}
		foreach ( $out as $item ) {
			if ( ! is_array( $item ) ) {
				$groups[] = $item;
				continue;
			}
			$bundle = count( $item ) > 1 ? $this->script_bundle( $scripts, $item ) : '';
			if ( '' === $bundle ) {
				foreach ( $item as $handle ) {
					$groups[] = $handle;
				}
			} else {
				$groups[] = $bundle;
			}
		}
		return $groups;
	}

	/**
	 * Strategy signature of a combinable script ("defer" or "") or false.
	 *
	 * @param \WP_Scripts $scripts Scripts.
	 * @param string      $handle  Handle.
	 * @return string|false
	 */
	private function combinable_script( $scripts, $handle ) {
		if ( 0 === strpos( $handle, self::PREFIX ) || ! isset( $scripts->registered[ $handle ] ) || ! $this->module->registry()->has( 'script', $handle ) ) {
			return false;
		}
		$item = $scripts->registered[ $handle ];
		if ( ! $item->src || ! empty( $item->extra['conditional'] ) || ! empty( $item->textdomain ) || '' !== (string) $scripts->get_inline_script_data( $handle, 'after' ) || 'module' === ( $item->extra['type'] ?? '' ) ) {
			return false;
		}
		if ( '' === $this->module->processor()->cached_url( 'script', $handle, $this->src( $scripts, $handle ) ) ) {
			return false;
		}
		$strategy = $this->eligible_strategy( $scripts, $handle );
		return false === $strategy ? false : $strategy;
	}

	/**
	 * WordPress's own loading decision for a script ("defer", "async" or "").
	 * The method is private in WP_Scripts (6.3+); without it nothing combines.
	 *
	 * @param \WP_Scripts $scripts Scripts.
	 * @param string      $handle  Handle.
	 * @return string|false False when it cannot be known.
	 */
	private function eligible_strategy( $scripts, $handle ) {
		if ( ! method_exists( $scripts, 'get_eligible_loading_strategy' ) ) {
			return '' === (string) $scripts->get_data( $handle, 'strategy' ) ? '' : false;
		}
		try {
			$method = new \ReflectionMethod( $scripts, 'get_eligible_loading_strategy' );
			$method->setAccessible( true );
			$strategy = (string) $method->invoke( $scripts, $handle );
		} catch ( \ReflectionException $e ) {
			return false;
		}
		return 'async' === $strategy ? false : $strategy;
	}

	/**
	 * Source URL of a registered script or style, as WordPress would ask for it.
	 *
	 * @param \WP_Dependencies $deps   Scripts or styles.
	 * @param string           $handle Handle.
	 * @return string
	 */
	private function src( $deps, $handle ) {
		$src = (string) $deps->registered[ $handle ]->src;
		if ( ! preg_match( '|^(https?:)?//|', $src ) && ! ( $deps->content_url && 0 === strpos( $src, $deps->content_url ) ) ) {
			$src = $deps->base_url . $src;
		}
		return $src;
	}

	/**
	 * Register the bundle for a group of scripts, or '' to print them separately.
	 *
	 * @param \WP_Scripts $scripts Scripts.
	 * @param string[]    $group   Member handles.
	 * @return string Bundle handle.
	 */
	private function script_bundle( $scripts, array $group ) {
		$members = $this->members( 'script', $group, $scripts );
		$key     = self::key( $members );
		$file    = Storage::file_name( 'script', 'bundle', $key );
		if ( ! $this->module->storage()->exists( 'script', $file ) ) {
			$this->queue_build( 'script', $key, $members );
			return '';
		}
		$handle = self::PREFIX . $key;
		$data   = array();
		$before = array();
		foreach ( $group as $member ) {
			$extra = (string) $scripts->get_data( $member, 'data' );
			if ( '' !== $extra ) {
				$data[] = $extra;
			}
			$inline = (string) $scripts->get_inline_script_data( $member, 'before' );
			if ( '' !== $inline ) {
				$before[] = $inline;
			}
		}
		wp_register_script( $handle, $this->module->storage()->file_url( 'script', $file ), array(), null, (bool) $scripts->get_data( $group[0], 'group' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The file name carries a content hash.
		if ( array() !== $data ) {
			$scripts->add_data( $handle, 'data', implode( "\n", $data ) );
		}
		if ( array() !== $before ) {
			wp_add_inline_script( $handle, implode( "\n", $before ), 'before' );
		}
		$strategy = (string) $scripts->get_data( $group[0], 'strategy' );
		if ( '' !== $strategy && '' !== (string) $this->eligible_strategy( $scripts, $group[0] ) ) {
			$scripts->add_data( $handle, 'strategy', $strategy );
		}
		// all_deps() has already assigned groups (head 0 / footer 1); the bundle prints in its first member's.
		$scripts->groups[ $handle ] = isset( $scripts->groups[ $group[0] ] ) ? $scripts->groups[ $group[0] ] : 0;
		$this->alias( $scripts, $group );
		$this->printed[ $handle ] = array( 'script', $group, array_map( array( $this, 'original_script' ), $group ) );
		return $handle;
	}

	/**
	 * Original URL of a member script (for the guard's fallback).
	 *
	 * @param string $handle Handle.
	 * @return string
	 */
	private function original_script( $handle ) {
		$scripts = wp_scripts();
		$ver     = $scripts->registered[ $handle ]->ver;
		$src     = $this->src( $scripts, $handle );
		return null === $ver ? $src : add_query_arg( 'ver', $ver ? $ver : $scripts->default_version, $src );
	}

	/**
	 * Combine styles about to print (after non-blocking styles moved them).
	 *
	 * @param string[] $to_do Handles.
	 * @return string[]
	 */
	public function styles( $to_do ) {
		if ( ! is_array( $to_do ) || ! $this->frontend->on() || ! $this->module->settings()->get( 'minify_css' ) ) {
			return $to_do;
		}
		$styles = wp_styles();
		$out    = array();
		$group  = array();
		$media  = null;
		$flush  = function () use ( &$group, &$out, $styles ) {
			$bundle = count( $group ) > 1 ? $this->style_bundle( $styles, $group ) : '';
			if ( '' === $bundle ) {
				foreach ( $group as $handle ) {
					$out[] = $handle;
				}
			} else {
				$out[] = $bundle;
			}
			$group = array();
		};
		foreach ( $to_do as $handle ) {
			$ok = $this->combinable_style( $styles, $handle );
			if ( false !== $ok ) {
				if ( array() !== $group && $ok !== $media ) {
					$flush();
				}
				$group[] = $handle;
				$media   = $ok;
				continue;
			}
			$flush();
			$out[] = $handle;
		}
		$flush();
		return $out;
	}

	/**
	 * Media of a combinable style, or false.
	 *
	 * @param \WP_Styles $styles Styles.
	 * @param string     $handle Handle.
	 * @return string|false
	 */
	private function combinable_style( $styles, $handle ) {
		if ( 0 === strpos( $handle, self::PREFIX ) || ! isset( $styles->registered[ $handle ] ) || ! $this->module->registry()->has( 'style', $handle ) ) {
			return false;
		}
		$item = $styles->registered[ $handle ];
		if ( ! $item->src || ! empty( $item->extra['conditional'] ) || ! empty( $item->extra['rtl'] ) || ! empty( $item->extra['alt'] ) || ! empty( $item->extra['after'] ) ) {
			return false;
		}
		$cached = $this->module->processor()->cached_url( 'style', $handle, $this->src( $styles, $handle ) );
		if ( '' === $cached ) {
			return false;
		}
		$entry = $this->module->state()->file( 'style', $handle );
		$path  = $entry ? $this->module->storage()->file_path( 'style', (string) $entry['file'] ) : '';
		if ( '' === $path || ! is_file( $path ) || false !== stripos( (string) file_get_contents( $path ), '@import' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Our own cached file.
			return false;
		}
		return $item->args ? (string) $item->args : 'all';
	}

	/**
	 * Register the bundle for a group of styles, or '' to print them separately.
	 *
	 * @param \WP_Styles $styles Styles.
	 * @param string[]   $group  Member handles.
	 * @return string Bundle handle.
	 */
	private function style_bundle( $styles, array $group ) {
		$members = $this->members( 'style', $group, $styles );
		$key     = self::key( $members );
		$file    = Storage::file_name( 'style', 'bundle', $key );
		if ( ! $this->module->storage()->exists( 'style', $file ) ) {
			$this->queue_build( 'style', $key, $members );
			return '';
		}
		$handle = self::PREFIX . $key;
		$media  = $styles->registered[ $group[0] ]->args ? $styles->registered[ $group[0] ]->args : 'all';
		wp_register_style( $handle, $this->module->storage()->file_url( 'style', $file ), array(), null, $media ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The file name carries a content hash.
		$originals = array();
		foreach ( $group as $member ) {
			$ver         = $styles->registered[ $member ]->ver;
			$originals[] = (string) $styles->_css_href( $styles->registered[ $member ]->src, null === $ver ? '' : ( $ver ? $ver : $styles->default_version ), $member . '-ao-original' );
		}
		$styles->groups[ $handle ] = isset( $styles->groups[ $group[0] ] ) ? $styles->groups[ $group[0] ] : 0;
		$this->alias( $styles, $group );
		$this->printed[ $handle ] = array( 'style', $group, $originals );
		return $handle;
	}

	/**
	 * Mark members done: each becomes an empty alias of the bundle.
	 *
	 * @param \WP_Dependencies $deps  Scripts or styles.
	 * @param string[]         $group Member handles.
	 * @return void
	 */
	private function alias( $deps, array $group ) {
		foreach ( $group as $member ) {
			$deps->done[] = $member;
		}
	}

	/**
	 * Members of a group with their current cached file: handle => file name.
	 *
	 * @param string           $type  "script" or "style".
	 * @param string[]         $group Handles.
	 * @param \WP_Dependencies $deps  Scripts or styles.
	 * @return array<string,string>
	 */
	private function members( $type, array $group, $deps ) {
		$members = array();
		foreach ( $group as $handle ) {
			$url                = $this->module->processor()->cached_url( $type, $handle, $this->src( $deps, $handle ) );
			$members[ $handle ] = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		}
		return $members;
	}

	/**
	 * Bundle key: the members and their file versions.
	 *
	 * @param array<string,string> $members Handle => file.
	 * @return string
	 */
	public static function key( array $members ) {
		return substr( md5( wp_json_encode( $members ) ), 0, 12 );
	}

	/**
	 * Queue a bundle build for after the response (or a one-off cron event).
	 *
	 * @param string               $type    "script" or "style".
	 * @param string               $key     Bundle key.
	 * @param array<string,string> $members Handle => file.
	 * @return void
	 */
	private function queue_build( $type, $key, array $members ) {
		$state = $this->module->state();
		$state->refresh();
		$bundles = (array) $state->get( 'bundles' );
		if ( ! isset( $bundles[ $key ] ) ) {
			$bundles[ $key ] = array(
				't'       => $type,
				'members' => $members,
				'at'      => time(),
			);
			if ( count( $bundles ) > self::MAX_BUNDLES ) {
				$bundles = array_slice( $bundles, -self::MAX_BUNDLES, null, true );
			}
			$state->set( 'bundles', $bundles );
		}
		if ( array() === $this->queue ) {
			add_action( 'shutdown', array( $this, 'run_queue' ), PHP_INT_MAX - 1 );
		}
		$this->queue[ $key ] = $type;
	}

	/**
	 * After the response: build queued bundles (FPM/LiteSpeed) or schedule them.
	 *
	 * @return void
	 */
	public function run_queue() {
		$finished = function_exists( 'fastcgi_finish_request' ) || function_exists( 'litespeed_finish_request' );
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
		foreach ( $this->queue as $key => $type ) {
			if ( $finished ) {
				self::build( $this->module, $type, $key );
			} elseif ( ! wp_next_scheduled( 'wpbot_ao_build_bundle', array( $type, $key ) ) ) {
				wp_schedule_single_event( time(), 'wpbot_ao_build_bundle', array( $type, $key ) );
			}
		}
		$this->queue = array();
	}

	/**
	 * Build one bundle: the members' cached files, in order, in one file.
	 *
	 * @param Module $module The module.
	 * @param string $type   "script" or "style".
	 * @param string $key    Bundle key (must be in the state).
	 * @return bool
	 */
	public static function build( Module $module, $type, $key ) {
		$module->state()->refresh();
		$bundles = (array) $module->state()->get( 'bundles' );
		if ( ! isset( $bundles[ $key ]['members'] ) || $type !== $bundles[ $key ]['t'] ) {
			return false;
		}
		$parts = array();
		foreach ( (array) $bundles[ $key ]['members'] as $handle => $file ) {
			$file  = Storage::file_name( $type, (string) $handle, (string) preg_replace( '/^.*-([a-f0-9]{12})\.min\.(js|css)$/', '$1', (string) $file ) );
			$path  = $module->storage()->file_path( $type, $file );
			$entry = $module->state()->file( $type, (string) $handle );
			if ( '' === $file || ! $module->registry()->has( $type, (string) $handle, $entry ? (string) $entry['path'] : '' ) || ! is_file( $path ) ) {
				return false;
			}
			$part = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Our own cached file.
			if ( 'style' === $type && 0 === strpos( ltrim( $part ), '@import' ) ) {
				// A repaired stylesheet imports its original, which browsers ignore inside a bundle:
				// remove the bundle instead, so pages that still use it fall back to the originals.
				$bundle = $module->storage()->file_path( $type, Storage::file_name( $type, 'bundle', $key ) );
				if ( is_file( $bundle ) ) {
					wp_delete_file( $bundle );
				}
				return false;
			}
			$parts[] = $part;
		}
		return $module->storage()->write( $type, Storage::file_name( $type, 'bundle', $key ), implode( 'script' === $type ? "\n;\n" : "\n", $parts ) );
	}

	/**
	 * A member was repaired: rebuild every bundle that contains it, in place.
	 *
	 * @param Module $module The module.
	 * @param string $handle Member handle.
	 * @param string $type   "script" or "style".
	 * @return void
	 */
	public static function heal( Module $module, $handle, $type ) {
		foreach ( (array) $module->state()->get( 'bundles' ) as $key => $bundle ) {
			if ( isset( $bundle['members'][ $handle ] ) && $type === $bundle['t'] ) {
				self::build( $module, $type, (string) $key );
			}
		}
	}
}
