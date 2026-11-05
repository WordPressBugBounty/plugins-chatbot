<?php
/**
 * A feature the module or an extension (the Pro module) registers.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Features;

use QuantumCloud\SeoAiSearch\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Feature contract.
 */
interface Feature {

	/**
	 * Unique id.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Add the feature's hooks.
	 *
	 * @param Module $module The running module.
	 * @return void
	 */
	public function register( Module $module );
}
