<?php
/**
 * A feature that plugs into AI Writer through its hooks.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Features;

use QuantumCloud\AiWriter\Module;

defined( 'ABSPATH' ) || exit;

interface Feature {

	/**
	 * Stable feature id, for example "chat_ideas".
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Hook the feature into WordPress and AI Writer.
	 *
	 * @param Module $module Module.
	 * @return void
	 */
	public function register( Module $module );
}
