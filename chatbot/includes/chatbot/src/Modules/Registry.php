<?php
/**
 * Module registry: the list behind Module Manager.
 *
 * Features register themselves (id, name, description, default, path) instead of
 * being hard-coded in the host. Each feature keeps the path it has always had in
 * the stored state ("folder/file.php" relative to the host's add-on folder), so a
 * site's on/off choices carry over unchanged.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of switchable features.
 */
final class Registry {

	/**
	 * Registered features, keyed by id, in registration order.
	 *
	 * @var array<string,array>
	 */
	private $modules = array();

	/**
	 * Stored paths of features that no longer exist; dropped from the state.
	 *
	 * @var string[]
	 */
	private $retired = array();

	/**
	 * State storage.
	 *
	 * @var State_Store
	 */
	private $store;

	/**
	 * Base folder of the stored paths.
	 *
	 * @var string
	 */
	private $base_dir = '';

	/**
	 * Whether the register action already ran.
	 *
	 * @var bool
	 */
	private $collected = false;

	/**
	 * Constructor.
	 *
	 * @param State_Store $store State storage.
	 */
	public function __construct( State_Store $store ) {
		$this->store = $store;
	}

	/**
	 * Set the folder the stored paths are relative to (the host's add-on folder).
	 *
	 * @param string $dir Absolute folder.
	 * @return void
	 */
	public function set_base_dir( $dir ) {
		$this->base_dir = rtrim( (string) $dir, '/\\' );
	}

	/**
	 * Register a feature.
	 *
	 * Arguments: `id` (unique, lowercase letters, digits and dashes), `name` (shown in
	 * Module Manager), `description` (optional), `default` (on for new sites, default
	 * false), `path` (stored path, "folder/file.php" under the base folder) and `order`
	 * (optional sort position, lower first, default 100).
	 *
	 * @param array $args Feature arguments, see above.
	 * @return bool False when the arguments are invalid or the id is taken.
	 */
	public function register( array $args ) {
		$id   = isset( $args['id'] ) ? (string) $args['id'] : '';
		$path = isset( $args['path'] ) ? (string) $args['path'] : '';
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $id ) || isset( $this->modules[ $id ] ) || ! self::is_valid_path( $path ) ) {
			return false;
		}
		$this->modules[ $id ] = array(
			'id'          => $id,
			'name'        => isset( $args['name'] ) ? (string) $args['name'] : $id,
			'description' => isset( $args['description'] ) ? (string) $args['description'] : '',
			'default'     => ! empty( $args['default'] ),
			'path'        => $path,
			'order'       => isset( $args['order'] ) ? (int) $args['order'] : 100,
		);
		return true;
	}

	/**
	 * Mark a stored path as retired: it is removed from the stored state and never loaded.
	 *
	 * @param string $path Stored path.
	 * @return void
	 */
	public function retire( $path ) {
		$this->retired[] = (string) $path;
	}

	/**
	 * Let Pro and add-ons register (once), then return every feature, sorted.
	 *
	 * @return array<string,array>
	 */
	public function all() {
		$this->collect();
		$modules = $this->modules;
		uasort(
			$modules,
			static function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);
		return $modules;
	}

	/**
	 * One feature by id.
	 *
	 * @param string $id Feature id.
	 * @return array|null
	 */
	public function get( $id ) {
		$all = $this->all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * One feature by its stored path.
	 *
	 * @param string $path Stored path.
	 * @return array|null
	 */
	public function get_by_path( $path ) {
		foreach ( $this->all() as $module ) {
			if ( $module['path'] === $path ) {
				return $module;
			}
		}
		return null;
	}

	/**
	 * Whether a feature is on: the stored choice when there is one, else its default.
	 *
	 * @param string $id Feature id.
	 * @return bool
	 */
	public function is_active( $id ) {
		$module = $this->get( $id );
		if ( null === $module ) {
			return false;
		}
		$state = $this->state();
		return isset( $state['active'][ $module['path'] ] );
	}

	/**
	 * Default state of every registered feature, in the legacy format.
	 *
	 * @return array
	 */
	public function defaults() {
		$state = array(
			'active'   => array(),
			'inactive' => array(),
		);
		foreach ( $this->all() as $module ) {
			$state[ $module['default'] ? 'active' : 'inactive' ][ $module['path'] ] = $module['name'];
		}
		return $state;
	}

	/**
	 * Current state in the legacy format: stored choices kept, features the site has
	 * never seen added with their default, retired paths removed. Saved when it changed.
	 *
	 * @return array
	 */
	public function state() {
		$stored = $this->store->get();
		if ( null === $stored ) {
			$state = $this->defaults();
			$this->store->set( $state );
			return $state;
		}

		$state = array(
			'active'   => isset( $stored['active'] ) && is_array( $stored['active'] ) ? $stored['active'] : array(),
			'inactive' => isset( $stored['inactive'] ) && is_array( $stored['inactive'] ) ? $stored['inactive'] : array(),
		);
		foreach ( $this->retired as $path ) {
			unset( $state['active'][ $path ], $state['inactive'][ $path ] );
		}
		foreach ( $this->all() as $module ) {
			$path = $module['path'];
			if ( ! isset( $state['active'][ $path ] ) && ! isset( $state['inactive'][ $path ] ) ) {
				$state[ $module['default'] ? 'active' : 'inactive' ][ $path ] = $module['name'];
			}
		}
		if ( $state !== $stored ) {
			$this->store->set( $state );
		}
		return $state;
	}

	/**
	 * Switch features on or off by stored path (the form Module Manager posts).
	 * Unknown paths are ignored.
	 *
	 * @param string[] $paths  Stored paths.
	 * @param bool     $active On or off.
	 * @return array New state.
	 */
	public function set_active( array $paths, $active ) {
		$state = $this->state();
		$from  = $active ? 'inactive' : 'active';
		$to    = $active ? 'active' : 'inactive';
		foreach ( $paths as $path ) {
			$path = (string) $path;
			if ( ! $this->is_known_path( $path ) ) {
				continue;
			}
			$name = isset( $state[ $from ][ $path ] ) ? $state[ $from ][ $path ] : ( isset( $state[ $to ][ $path ] ) ? $state[ $to ][ $path ] : '' );
			unset( $state[ $from ][ $path ] );
			$state[ $to ][ $path ] = $name;
		}
		$this->store->set( $state );
		return $state;
	}

	/**
	 * Put every feature back to its default (Module Manager's reset button).
	 *
	 * @return array New state.
	 */
	public function reset() {
		$state = $this->defaults();
		$this->store->set( $state );
		return $state;
	}

	/**
	 * Whether a stored path belongs to a registered feature, or is already in the
	 * stored state (an add-on that registers later) and is not retired.
	 *
	 * @param string $path Stored path.
	 * @return bool
	 */
	public function is_known_path( $path ) {
		if ( ! self::is_valid_path( $path ) || in_array( $path, $this->retired, true ) ) {
			return false;
		}
		if ( null !== $this->get_by_path( $path ) ) {
			return true;
		}
		$stored = $this->store->get();
		return is_array( $stored ) && ( isset( $stored['active'][ $path ] ) || isset( $stored['inactive'][ $path ] ) );
	}

	/**
	 * Absolute files of the active features that exist under the base folder, in
	 * stored order (the order WPBot Pro has always loaded them in).
	 *
	 * @return string[]
	 */
	public function active_files() {
		if ( '' === $this->base_dir ) {
			return array();
		}
		$files = array();
		foreach ( array_keys( $this->state()['active'] ) as $path ) {
			$path = (string) $path;
			if ( ! self::is_valid_path( $path ) || in_array( $path, $this->retired, true ) ) {
				continue;
			}
			$file = $this->base_dir . '/' . $path;
			if ( is_file( $file ) ) {
				$files[] = $file;
			}
		}
		return $files;
	}

	/**
	 * A stored path is "folder/file.php" inside the base folder, never a path outside it.
	 *
	 * @param string $path Stored path.
	 * @return bool
	 */
	public static function is_valid_path( $path ) {
		return is_string( $path ) && 1 === preg_match( '#^[A-Za-z0-9_-]+/[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*\.php$#', $path );
	}

	/**
	 * Run the register action once.
	 *
	 * @return void
	 */
	private function collect() {
		if ( $this->collected ) {
			return;
		}
		$this->collected = true;
		/**
		 * Register switchable features (Module Manager entries).
		 *
		 * @param Registry $registry The registry; call register() and retire().
		 */
		do_action( 'qcld_wpbot_chat/register_modules', $this );
	}
}
