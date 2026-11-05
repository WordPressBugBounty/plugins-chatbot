<?php
/**
 * Registry of features; the first feature with an id wins.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Features;

use QuantumCloud\SeoAiSearch\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Holds and registers features.
 */
final class Feature_Registry {

	/**
	 * Features by id.
	 *
	 * @var Feature[]
	 */
	private $features = array();

	/**
	 * Whether register_all() has run.
	 *
	 * @var Module|null
	 */
	private $module = null;

	/**
	 * Add a feature. Added after registration, it registers at once.
	 *
	 * @param Feature $feature Feature.
	 * @return bool Whether it was added.
	 */
	public function add( Feature $feature ) {
		$id = sanitize_key( $feature->id() );
		if ( '' === $id || isset( $this->features[ $id ] ) ) {
			return false;
		}
		$this->features[ $id ] = $feature;
		if ( null !== $this->module ) {
			$feature->register( $this->module );
		}
		return true;
	}

	/**
	 * Whether a feature is registered.
	 *
	 * @param string $id Feature id.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->features[ sanitize_key( $id ) ] );
	}

	/**
	 * Registered ids.
	 *
	 * @return string[]
	 */
	public function ids() {
		return array_keys( $this->features );
	}

	/**
	 * Register every feature once.
	 *
	 * @param Module $module Module.
	 * @return void
	 */
	public function register_all( Module $module ) {
		if ( null !== $this->module ) {
			return;
		}
		$this->module = $module;
		foreach ( $this->features as $feature ) {
			$feature->register( $module );
		}
	}
}
