<?php
/**
 * Starts the content model (HC1-03). Called only while the Help Center is on.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Content model entry point.
 */
final class Content_Model {

	/**
	 * Hook everything in.
	 *
	 * @return void
	 */
	public static function init() {
		Taxonomies::init();
		Post_Type::init();
		Meta::init();
		Caps::init();
	}
}
