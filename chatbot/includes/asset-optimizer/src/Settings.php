<?php
/**
 * Settings of the asset cache: defaults, sanitizing and kill switches.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the `wpbot_asset_cache_settings` option.
 */
class Settings {

	const OPTION = 'wpbot_asset_cache_settings';

	/**
	 * Longest "files to leave untouched" list we keep.
	 */
	const MAX_SKIP_LINES = 100;

	/**
	 * Cached option value for this request.
	 *
	 * @var array<string,mixed>|null
	 */
	private $values = null;

	/**
	 * Default values. The Pro module adds its own keys through the filter.
	 *
	 * @return array<string,mixed>
	 */
	public function defaults() {
		$defaults = array(
			'enabled'          => true,
			'minify_css'       => true,
			'minify_js'        => true,
			'defer_js'         => true,
			'nonblocking_css'  => true,
			'combine'          => false,
			'skip'             => array(),
			'purge_page_cache' => false,
		);

		/**
		 * Filter the default settings (the Pro module adds its keys here).
		 *
		 * @param array<string,mixed> $defaults Defaults.
		 */
		return (array) apply_filters( 'qcld_wpbot_ao_default_settings', $defaults );
	}

	/**
	 * Create the option on the module's first run.
	 *
	 * New installs start with the cache on. Sites updating from a version
	 * without the module start with it off and see an invitation.
	 *
	 * @param bool $existing_install Whether the host was installed before.
	 * @return bool True when the option was created now.
	 */
	public function initialise( $existing_install ) {
		if ( false !== get_option( self::OPTION, false ) ) {
			return false;
		}
		$values            = $this->defaults();
		$values['enabled'] = ! $existing_install;
		add_option( self::OPTION, $values, '', true );
		$this->values = null;
		return true;
	}

	/**
	 * All settings, merged over the defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function all() {
		if ( null === $this->values ) {
			$stored       = get_option( self::OPTION, array() );
			$this->values = array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
		}
		return $this->values;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed Null when unknown.
	 */
	public function get( $key ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Replace the settings with sanitized values.
	 *
	 * @param array<string,mixed> $input Raw input (for example from the settings form).
	 * @return array<string,mixed> What was saved.
	 */
	public function save( array $input ) {
		$clean = $this->sanitize( $input );
		update_option( self::OPTION, $clean, true );
		$this->values = null;
		return $this->all();
	}

	/**
	 * Change only the given keys, keeping the others.
	 *
	 * @param array<string,mixed> $changes Keys to change.
	 * @return array<string,mixed>
	 */
	public function update( array $changes ) {
		return $this->save( array_merge( $this->all(), $changes ) );
	}

	/**
	 * Sanitize raw settings. Unknown keys are dropped; missing booleans are off.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize( array $input ) {
		$clean = array();
		foreach ( array( 'enabled', 'minify_css', 'minify_js', 'defer_js', 'nonblocking_css', 'combine', 'purge_page_cache' ) as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] ) && 'off' !== $input[ $key ];
		}
		$clean['skip'] = self::sanitize_skip_list( isset( $input['skip'] ) ? $input['skip'] : array() );

		/**
		 * Sanitize extra keys (the Pro module adds its own here).
		 *
		 * @param array<string,mixed> $clean Sanitized core keys.
		 * @param array<string,mixed> $input Raw input.
		 */
		return (array) apply_filters( 'qcld_wpbot_ao_sanitize_settings', $clean, $input );
	}

	/**
	 * Sanitize the "files to leave untouched" list: one handle or file name
	 * per line, letters, digits, dot, dash, underscore and slash only.
	 *
	 * @param string|string[] $value Textarea text or a list.
	 * @return string[]
	 */
	public static function sanitize_skip_list( $value ) {
		$lines = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
		$out   = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || strlen( $line ) > 200 || ! preg_match( '#^[A-Za-z0-9._/\-]+$#', $line ) ) {
				continue;
			}
			$out[ $line ] = $line;
			if ( count( $out ) >= self::MAX_SKIP_LINES ) {
				break;
			}
		}
		return array_values( $out );
	}

	/**
	 * Whether the master switch is on.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) $this->get( 'enabled' );
	}

	/**
	 * Why the cache is switched off for everyone right now, or '' when it is not.
	 * The admin preview (?wpbot_ao=off) is checked per request by the front end.
	 *
	 * @return string One of '', 'setting', 'constant', 'script_debug'.
	 */
	public function kill_switch() {
		if ( defined( 'WPBOT_ASSET_CACHE_DISABLE' ) && WPBOT_ASSET_CACHE_DISABLE ) {
			return 'constant';
		}
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return 'script_debug';
		}
		if ( ! $this->is_enabled() ) {
			return 'setting';
		}
		return '';
	}

	/**
	 * Short signature of the settings that change cached output (part of every cache key).
	 *
	 * @return string
	 */
	public function output_signature() {
		$all = $this->all();
		return (string) ( (int) $all['minify_css'] ) . ( (int) $all['minify_js'] );
	}
}
