<?php
/**
 * One caching or optimisation plugin: how to tell it to leave WPBot's
 * files alone, and how to purge its page cache.
 *
 * Every integration only adds filters the plugin calls itself and calls its
 * public purge functions when they exist, so it fails silently when the
 * plugin renames or drops a hook. Hook names were checked against each
 * plugin's current code (see the per-class notes).
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Base class.
 */
abstract class Integration {

	/**
	 * What to exclude.
	 *
	 * @var Exclusions
	 */
	protected $x;

	/**
	 * Constructor.
	 *
	 * @param Exclusions $exclusions What to exclude.
	 */
	public function __construct( Exclusions $exclusions ) {
		$this->x = $exclusions;
	}

	/**
	 * Slug (matches Compat::known()).
	 *
	 * @return string
	 */
	abstract public function slug();

	/**
	 * Whether the plugin is active.
	 *
	 * @return bool
	 */
	abstract public function active();

	/**
	 * Add the exclusion filters. Integrations without optimisation features do nothing.
	 *
	 * @return void
	 */
	public function exclude() {
	}

	/**
	 * Whether WPBot excludes itself from this plugin automatically.
	 *
	 * @return bool
	 */
	public function automatic() {
		return false;
	}

	/**
	 * Whether the plugin has a page cache WPBot can purge.
	 *
	 * @return bool
	 */
	public function can_purge() {
		return false;
	}

	/**
	 * Purge the plugin's page cache.
	 *
	 * @return bool Whether a purge was requested.
	 */
	public function purge() {
		return false;
	}

	/**
	 * Merge values into a filtered list.
	 *
	 * @param mixed    $current Current list.
	 * @param string[] $items   Items to add.
	 * @return array
	 */
	protected static function add( $current, array $items ) {
		return array_values( array_unique( array_merge( is_array( $current ) ? $current : array(), $items ) ) );
	}
}
