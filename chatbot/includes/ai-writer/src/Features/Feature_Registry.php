<?php
/**
 * Holds the features registered by extensions.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Features;

use QuantumCloud\AiWriter\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Feature registry.
 */
final class Feature_Registry {

	/**
	 * Features by id.
	 *
	 * @var Feature[]
	 */
	private $features = array();

	/**
	 * Whether register_all() ran.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Add a feature. The first feature with an id wins.
	 *
	 * @param Feature $feature Feature.
	 * @return void
	 */
	public function add( Feature $feature ) {
		$id = sanitize_key( $feature->id() );
		if ( '' === $id || isset( $this->features[ $id ] ) ) {
			return;
		}
		$this->features[ $id ] = $feature;
		if ( $this->registered ) {
			$feature->register( Module::instance() );
		}
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
	 * Registered feature ids.
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
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;
		foreach ( $this->features as $feature ) {
			$feature->register( $module );
		}
	}
}
