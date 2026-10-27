<?php
/**
 * Holds features added by the core module, by WPBot Pro, or by other hosts.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Features;

use QuantumCloud\Insights\Module;

defined( 'ABSPATH' ) || exit;

final class Feature_Registry {

	/**
	 * Features by id.
	 *
	 * @var array<string,Feature>
	 */
	private $features = array();

	/**
	 * Whether register() has run on the features.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Add a feature. Adding the same id twice keeps the first.
	 *
	 * @param Feature $feature Feature.
	 */
	public function add( Feature $feature ) {
		$id = $feature->id();
		if ( isset( $this->features[ $id ] ) ) {
			return;
		}
		$this->features[ $id ] = $feature;
		if ( $this->registered ) {
			$feature->register( Module::instance() );
		}
	}

	/**
	 * Whether a feature id is present.
	 *
	 * @param string $id Feature id.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->features[ $id ] );
	}

	/**
	 * All feature ids.
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
