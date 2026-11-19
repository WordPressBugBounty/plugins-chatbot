<?php
/**
 * Knowledge base entry point: hooks and the read and write API modules use.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

use QuantumCloud\WPBotCore\Jobs\Job_Store;
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
	 * Empty the knowledge base: every item of every source and its pieces. Waiting
	 * knowledge base jobs (embedding, sync, migration) are cancelled, and the migration
	 * bookkeeping is reset (migrated_to and migrated_count back to 0), so rows the old
	 * knowledge screens add to rag_documents later are moved again. Hosts that empty
	 * rag_documents should do so as well: rows left there are moved back at the next catch-up.
	 *
	 * @return int Items removed.
	 */
	public static function remove_all() {
		foreach ( Job_Store::recent( 'kb', 0, 50 ) as $job ) {
			if ( in_array( (string) $job['status'], array( 'queued', 'running', 'paused' ), true ) ) {
				Job_Store::cancel( (int) $job['id'] );
			}
		}
		$removed = Kb_Store::remove_all();
		$state   = get_option( Kb_Embedder::STATE, array() );
		$state   = is_array( $state ) ? $state : array();
		unset( $state['job'] );
		if ( empty( $state['migrated'] ) ) {
			// A cancelled migration counts as done: what the old screens add next is caught up.
			$state['migrated'] = current_time( 'mysql', true );
		}
		$state['migrated_to']    = 0;
		$state['migrated_count'] = 0;
		update_option( Kb_Embedder::STATE, $state, false );
		/**
		 * The knowledge base was emptied.
		 *
		 * @param int $removed Items removed.
		 */
		do_action( 'qcld_wpbot_core/kb_emptied', $removed );
		return $removed;
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
				'moved'    => Kb_Migration::moved( is_array( $state ) ? $state : array() ),
			),
			'job'       => is_array( $state ) && ! empty( $state['job'] ) ? (int) $state['job'] : 0,
		);
	}
}
