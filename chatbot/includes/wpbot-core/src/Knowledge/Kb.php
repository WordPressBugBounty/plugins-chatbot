<?php
/**
 * Knowledge base entry point: hooks and the read and write API modules use.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

use QuantumCloud\WPBotCore\Storage\Kb_Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge base facade.
 */
final class Kb {

	/**
	 * Hook everything.
	 *
	 * @return void
	 */
	public static function hook() {
		Kb_Schema::hook();
		Kb_Sources::hook();
		add_filter( 'qcld_wpbot_core/job_handlers', array( Kb_Embedder::class, 'handlers' ) );
		add_filter( 'qcld_wpbot_core/job_handlers', array( Kb_Sources::class, 'handlers' ) );
		add_filter( 'qcld_wpbot_core/job_handlers', array( Kb_Migration::class, 'handlers' ) );
	}

	/**
	 * Search.
	 *
	 * @param string $query Question.
	 * @param array  $args  See Kb_Search::search().
	 * @return array[]
	 */
	public static function search( $query, array $args = array() ) {
		return Kb_Search::search( $query, $args );
	}

	/**
	 * State for settings screens: counts, waiting work and its cost, migration.
	 *
	 * @return array
	 */
	public static function status() {
		$profile = Kb_Embedder::profile();
		$state   = get_option( Kb_Embedder::STATE, array() );
		return array(
			'embedding' => $profile ? array(
				'provider'   => $profile['provider'],
				'model'      => $profile['model'],
				'dimensions' => $profile['dimensions'],
			) : null,
			'counts'    => Kb_Store::stats( Kb_Embedder::model_id() ),
			'estimate'  => Kb_Embedder::estimate(),
			'migration' => array(
				'needed'   => Kb_Migration::needed(),
				'migrated' => is_array( $state ) && ! empty( $state['migrated'] ) ? (string) $state['migrated'] : '',
			),
			'job'       => is_array( $state ) && ! empty( $state['job'] ) ? (int) $state['job'] : 0,
		);
	}
}
