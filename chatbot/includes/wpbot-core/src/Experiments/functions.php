<?php
/**
 * Public API of the Experiments service (round 3, P4). Modules create and
 * render tests through these functions; they never query the tables.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

use QuantumCloud\WPBotCore\Experiments\Experiments;
use QuantumCloud\WPBotCore\Experiments\Front;
use QuantumCloud\WPBotCore\Experiments\Learning;

if ( ! function_exists( 'qcld_wpbot_core_experiment_create' ) ) {
	/**
	 * Create a test (draft). See Experiments::create() for the arguments.
	 *
	 * @param array $args Arguments.
	 * @return int|WP_Error Experiment id.
	 */
	function qcld_wpbot_core_experiment_create( array $args ) {
		return Experiments::create( $args );
	}

	/**
	 * Start a test (one running test per page).
	 *
	 * @param int $id Experiment.
	 * @return array|WP_Error View.
	 */
	function qcld_wpbot_core_experiment_start( $id ) {
		return Experiments::start( $id );
	}

	/**
	 * A test with counts, assessment, reliability and duration.
	 *
	 * @param int $id Experiment.
	 * @return array|null
	 */
	function qcld_wpbot_core_experiment( $id ) {
		return Experiments::view( $id );
	}

	/**
	 * Tests matching filters (module, status, object_type, object_id, kind, legacy).
	 *
	 * @param array $args Filters.
	 * @return array[]
	 */
	function qcld_wpbot_core_experiments( array $args = array() ) {
		return Experiments::query( $args );
	}

	/**
	 * Stop a test without a winner.
	 *
	 * @param int $id Experiment.
	 * @return array|WP_Error
	 */
	function qcld_wpbot_core_experiment_stop( $id ) {
		return Experiments::stop( $id );
	}

	/**
	 * Apply a winner (fires qcld_wpbot_core/experiment_apply for the owning module).
	 *
	 * @param int $id         Experiment.
	 * @param int $variant_id Variant.
	 * @return array|WP_Error
	 */
	function qcld_wpbot_core_experiment_apply( $id, $variant_id ) {
		return Experiments::apply( $id, $variant_id, 'owner' );
	}

	/**
	 * Count events for a variant.
	 *
	 * @param int    $id         Experiment.
	 * @param int    $variant_id Variant.
	 * @param string $metric     Metric or exposure (views, impressions, sent…).
	 * @param int    $n          How many.
	 * @return bool
	 */
	function qcld_wpbot_core_experiment_record( $id, $variant_id, $metric, $n = 1 ) {
		return Experiments::record( $id, $variant_id, $metric, $n );
	}

	/**
	 * Cache-safe markup of a running split test's variants.
	 *
	 * @param int    $id   Experiment.
	 * @param array  $html Variant id => safe HTML.
	 * @param string $tag  span|div.
	 * @return string '' when the test is not running.
	 */
	function qcld_wpbot_core_experiment_markup( $id, array $html, $tag = 'span' ) {
		return Front::markup( $id, $html, $tag );
	}

	/**
	 * Running split tests of a module, keyed by experiment id (k kind, o object, v variants, c control).
	 *
	 * @param string $module Module.
	 * @return array
	 */
	function qcld_wpbot_core_experiments_running( $module ) {
		return Experiments::running_for( $module );
	}

	/**
	 * A tracked link for one version of a time or channel test (counts link_clicks).
	 *
	 * @param string $url        Link.
	 * @param int    $id         Experiment.
	 * @param int    $variant_id Variant.
	 * @return string
	 */
	function qcld_wpbot_core_experiment_link( $url, $id, $variant_id ) {
		return Front::link( $url, $id, $variant_id );
	}

	/**
	 * Duration estimate before starting.
	 *
	 * @param float  $daily    Daily visitors that will see the test.
	 * @param int    $variants Variants.
	 * @param string $metric   Metric.
	 * @param int    $max_days Maximum run time.
	 * @param int    $min      Minimum per variant.
	 * @return array per_variant, days, fits, message.
	 */
	function qcld_wpbot_core_experiment_estimate( $daily, $variants, $metric, $max_days = 30, $min = 100 ) {
		return Experiments::estimate( $daily, $variants, $metric, $max_days, $min );
	}

	/**
	 * Per-technique results (module, kind, stage, technique filters).
	 *
	 * @param array $args Filters.
	 * @return array[]
	 */
	function qcld_wpbot_core_technique_results( array $args = array() ) {
		return Learning::query( $args );
	}

	/**
	 * Techniques that have won more than they lost on this site, best first.
	 *
	 * @param string $module Module.
	 * @param string $kind   Kind of copy.
	 * @param string $stage  Reader stage.
	 * @return string[]
	 */
	function qcld_wpbot_core_technique_winners( $module, $kind, $stage = '' ) {
		return Learning::winners( $module, $kind, $stage );
	}
}
