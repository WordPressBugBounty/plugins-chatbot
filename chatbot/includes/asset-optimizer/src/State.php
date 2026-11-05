<?php
/**
 * Runtime state of the asset cache, kept in `wpbot_asset_cache_state`.
 *
 * Holds the module version, the current file map (used by Rebuild now and
 * the status panel), repaired and skipped files, the last error and the
 * update invitation flag.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Small wrapper around one autoloaded option.
 */
class State {

	const OPTION = 'wpbot_asset_cache_state';

	/**
	 * Most entries kept in the repaired and skipped lists.
	 */
	const MAX_LOG = 50;

	/**
	 * Current value.
	 *
	 * @var array<string,mixed>|null
	 */
	private $data = null;

	/**
	 * Empty state.
	 *
	 * @return array<string,mixed>
	 */
	private function blank() {
		return array(
			'version'    => '',
			'files'      => array(),
			'repaired'   => array(),
			'skipped'    => array(),
			'last_error' => '',
			'invite'     => false,
			'bundles'    => array(),
		);
	}

	/**
	 * The whole state.
	 *
	 * @return array<string,mixed>
	 */
	public function all() {
		if ( null === $this->data ) {
			$stored     = get_option( self::OPTION, array() );
			$this->data = array_merge( $this->blank(), is_array( $stored ) ? $stored : array() );
		}
		return $this->data;
	}

	/**
	 * One key.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( $key ) {
		$all = $this->all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Set one key and save.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( $key, $value ) {
		// Re-read first and change only this key, so values other requests saved meanwhile are kept.
		$this->refresh();
		$this->all();
		$this->data[ $key ] = $value;
		update_option( self::OPTION, $this->data, true );
	}

	/**
	 * Re-read the option (another request may have changed it). Used on
	 * write, build and report paths only, never on a plain page view.
	 *
	 * @return void
	 */
	public function refresh() {
		$this->data = null;
		if ( ! wp_using_ext_object_cache() ) {
			// Without a persistent object cache, options are cached for the whole request:
			// a long build after the response would otherwise read and write back an old copy.
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( self::OPTION, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}
	}

	/**
	 * Map key for a handle.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function file_id( $type, $handle ) {
		return $type . ':' . $handle;
	}

	/**
	 * The file map entry of a handle, or null.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $handle Handle.
	 * @return array<string,mixed>|null
	 */
	public function file( $type, $handle ) {
		$files = (array) $this->get( 'files' );
		$id    = self::file_id( $type, $handle );
		return isset( $files[ $id ] ) && is_array( $files[ $id ] ) ? $files[ $id ] : null;
	}

	/**
	 * Record a newly built file. The previous key is kept because cached
	 * pages may still point at it.
	 *
	 * @param string              $type   "script" or "style".
	 * @param string              $handle Handle.
	 * @param array<string,mixed> $entry  path (relative to the host folder), key, file, original, size, built.
	 * @return void
	 */
	public function record_file( $type, $handle, array $entry ) {
		$this->refresh();
		$files = (array) $this->get( 'files' );
		$id    = self::file_id( $type, $handle );
		$old   = isset( $files[ $id ] ) && is_array( $files[ $id ] ) ? $files[ $id ] : array();

		if ( ! empty( $old['key'] ) && $old['key'] !== $entry['key'] ) {
			$entry['previous'] = $old['key'];
		} elseif ( ! empty( $old['previous'] ) ) {
			$entry['previous'] = $old['previous'];
		}
		$files[ $id ] = $entry;
		$this->set( 'files', $files );
	}

	/**
	 * Keys still in use (current and previous of every file), for the cleanup.
	 *
	 * @return string[]
	 */
	public function keys_in_use() {
		$keys = array();
		foreach ( (array) $this->get( 'files' ) as $entry ) {
			foreach ( array( 'key', 'previous' ) as $field ) {
				if ( ! empty( $entry[ $field ] ) ) {
					$keys[] = (string) $entry[ $field ];
				}
			}
		}
		foreach ( array_keys( (array) $this->get( 'bundles' ) ) as $key ) {
			$keys[] = (string) $key;
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Add one entry to a capped log list ("repaired" or "skipped").
	 *
	 * @param string              $name  List name.
	 * @param string              $id    File id (type:handle).
	 * @param array<string,mixed> $entry Details.
	 * @return void
	 */
	public function log( $name, $id, array $entry ) {
		$this->refresh();
		$items        = (array) $this->get( $name );
		$entry['at']  = time();
		$items[ $id ] = $entry;
		if ( count( $items ) > self::MAX_LOG ) {
			$items = array_slice( $items, -self::MAX_LOG, null, true );
		}
		$this->set( $name, $items );
	}

	/**
	 * Forget everything about cached files (after Clear).
	 *
	 * @return void
	 */
	public function reset_files() {
		$this->refresh();
		$this->all();
		$this->data['files']      = array();
		$this->data['skipped']    = array();
		$this->data['repaired']   = array();
		$this->data['bundles']    = array();
		$this->data['last_error'] = '';
		update_option( self::OPTION, $this->data, true );
	}
}
