<?php
/**
 * Gives knowledge chunks their vectors, in the background, in small batches.
 *
 * Which embedding model to use comes from the host through the
 * qcld_wpbot_core/kb_embedding filter (provider, model, key, url). Without one the
 * knowledge base still works with keyword search only. Work runs as WPBot Core
 * jobs (one step = one batch), so a large site indexes on shared hosting
 * without long requests.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

use QuantumCloud\WPBotCore\AI\Embeddings;
use QuantumCloud\WPBotCore\AI\Provider_Client;
use QuantumCloud\WPBotCore\Jobs\Cron_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use QuantumCloud\WPBotCore\Spend\Spend;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Embedding jobs.
 */
final class Kb_Embedder {

	/**
	 * Job step kind.
	 */
	const STEP = 'core_kb_embed';

	/**
	 * Chunks per step.
	 */
	const BATCH = 32;

	/**
	 * Most steps in one job (the next job picks up the rest).
	 */
	const MAX_STEPS = 400;

	/**
	 * State option (current job id).
	 */
	const STATE = 'qcld_wpbot_core_kb_state';

	/**
	 * The embedding profile, or null when the site has none.
	 *
	 * @return array{provider: string, model: string, key: string, url: string, dimensions: int}|null
	 */
	public static function profile() {
		/**
		 * Which embedding model the knowledge base uses.
		 *
		 * Return array( 'provider' => Embeddings id, 'model' => model id, 'key' => API key,
		 * 'url' => base URL for Ollama ) or null for keyword search only.
		 *
		 * @param array|null $profile Profile.
		 */
		$profile = apply_filters( 'qcld_wpbot_core/kb_embedding', null );
		if ( ! is_array( $profile ) || empty( $profile['provider'] ) || empty( $profile['model'] ) || ! Embeddings::get( (string) $profile['provider'] ) ) {
			return null;
		}
		// A retired model (text-embedding-004) becomes its replacement. The stored vectors then carry an
		// older model id, so they are embedded again, and keyword search answers until then.
		$model = Embeddings::current_model( (string) $profile['provider'], (string) $profile['model'] );
		return array(
			'provider'   => (string) $profile['provider'],
			'model'      => $model,
			'key'        => isset( $profile['key'] ) ? (string) $profile['key'] : '',
			'url'        => isset( $profile['url'] ) ? (string) $profile['url'] : '',
			'dimensions' => Embeddings::dimensions( (string) $profile['provider'], $model ),
		);
	}

	/**
	 * Model id stored with vectors ("provider/model"), or '' without a profile.
	 *
	 * @return string
	 */
	public static function model_id() {
		$profile = self::profile();
		return $profile ? $profile['provider'] . '/' . $profile['model'] : '';
	}

	/**
	 * What embedding everything still waiting would cost.
	 *
	 * @return array{chunks: int, tokens: int, usd: float, model: string}
	 */
	public static function estimate() {
		$profile = self::profile();
		$model   = self::model_id();
		$pending = Kb_Store::pending( $model );
		$tokens  = (int) ceil( $pending['chars'] / 4 );
		$price   = 0.0;
		if ( $profile ) {
			$definition = Embeddings::get( $profile['provider'] );
			$price      = isset( $definition['models'][ $profile['model'] ]['price'] ) ? (float) $definition['models'][ $profile['model'] ]['price'] : 0.0;
		}
		return array(
			'chunks' => $pending['chunks'],
			'tokens' => $tokens,
			'usd'    => round( $tokens / 1000000 * $price, 4 ),
			'model'  => $model,
		);
	}

	/**
	 * Start an embedding job when chunks are waiting and none is running.
	 *
	 * @return int Job id (0 when nothing to do).
	 */
	public static function schedule() {
		$profile = self::profile();
		if ( ! $profile ) {
			return 0;
		}
		$state = get_option( self::STATE, array() );
		if ( ! empty( $state['job'] ) ) {
			$job = Job_Store::get( (int) $state['job'] );
			if ( $job && in_array( $job['status'], array( 'queued', 'running', 'paused' ), true ) ) {
				return (int) $state['job'];
			}
		}
		$pending = Kb_Store::pending( self::model_id() );
		if ( 0 === $pending['chunks'] ) {
			return 0;
		}
		$steps = array();
		$count = min( self::MAX_STEPS, (int) ceil( $pending['chunks'] / self::BATCH ) );
		for ( $i = 0; $i < $count; $i++ ) {
			$steps[] = array(
				'kind'  => self::STEP,
				'label' => __( 'Prepare knowledge for search', 'chatbot' ),
				'input' => array(),
			);
		}
		$job = Job_Store::create( 'kb', 'kb_embed', array( 'title' => __( 'Update the knowledge base', 'chatbot' ) ), $steps );
		if ( $job ) {
			update_option(
				self::STATE,
				array_merge(
					is_array( $state ) ? $state : array(),
					array( 'job' => (int) $job )
				),
				false
			);
			Cron_Runner::kick();
		}
		return (int) $job;
	}

	/**
	 * Register the step handler.
	 *
	 * @param array $handlers Handlers.
	 * @return array
	 */
	public static function handlers( $handlers ) {
		$handlers[ self::STEP ] = array( __CLASS__, 'step' );
		return $handlers;
	}

	/**
	 * One batch. When more pieces are waiting than the job's remaining steps can
	 * cover (items were added while it ran), the job gets more steps.
	 *
	 * @param array $step Step (job_id).
	 * @return array|WP_Error Output (embedded count, tokens).
	 */
	public static function step( $step = array() ) {
		$profile = self::profile();
		if ( ! $profile ) {
			return array( 'skipped' => 'no_embedding_model' );
		}
		$model = self::model_id();
		$rows  = Kb_Store::to_embed( $model, self::BATCH );
		if ( array() === $rows ) {
			return array( 'embedded' => 0 );
		}
		$texts = array();
		foreach ( $rows as $row ) {
			// The title helps short pieces; the embedding limit is far above a piece.
			$texts[] = ( '' !== $row['title'] ? $row['title'] . "\n" : '' ) . $row['content'];
		}
		$allowed = Spend::allow( 'knowledge_base', 'embed' );
		if ( is_wp_error( $allowed ) ) {
			return $allowed; // The job waits for the spending limit to reset or be raised.
		}
		$result = Provider_Client::embed( $profile['provider'], $profile['model'], $profile['key'], $texts, $profile['url'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::record( 'embed', $profile, $result, $texts, ! empty( $step['job_id'] ) ? (int) $step['job_id'] : 0 );
		$vectors = array();
		foreach ( $rows as $i => $row ) {
			$vectors[ (int) $row['id'] ] = $result['vectors'][ $i ];
		}
		Kb_Store::save_vectors( $vectors, $model );
		if ( ! empty( $step['job_id'] ) ) {
			self::top_up( (int) $step['job_id'], $model );
		}
		return array(
			'embedded' => count( $vectors ),
			'tokens'   => (int) $result['tokens'],
		);
	}

	/**
	 * Add steps to a running job so it covers every waiting piece.
	 *
	 * @param int    $job_id Job id.
	 * @param string $model  Model id.
	 * @return void
	 */
	private static function top_up( $job_id, $model ) {
		$left = 0;
		foreach ( Job_Store::steps( $job_id ) as $row ) {
			if ( 'pending' === $row['status'] ) {
				++$left;
			}
		}
		$needed = (int) ceil( Kb_Store::pending( $model )['chunks'] / self::BATCH ) - $left;
		if ( $needed <= 0 ) {
			return;
		}
		$steps = array();
		$count = min( $needed, self::MAX_STEPS );
		for ( $i = 0; $i < $count; $i++ ) {
			$steps[] = array(
				'kind'  => self::STEP,
				'label' => __( 'Prepare knowledge for search', 'chatbot' ),
				'input' => array(),
			);
		}
		Job_Store::add_steps( $job_id, $steps );
	}

	/**
	 * Vector of a question (cached for a day, so repeated questions cost nothing).
	 *
	 * @param string $query Question.
	 * @return float[]|null Null without a profile or on error.
	 */
	public static function query_vector( $query ) {
		$profile = self::profile();
		if ( ! $profile ) {
			return null;
		}
		$key    = 'qcld_wpbot_core_kbq_' . md5( self::model_id() . '|' . strtolower( trim( (string) $query ) ) );
		$cached = get_transient( $key );
		if ( is_string( $cached ) && '' !== $cached ) {
			$vector = Vector_Codec::decode( $cached );
			if ( array() !== $vector ) {
				return $vector;
			}
		}
		if ( is_wp_error( Spend::allow( 'knowledge_base', 'query' ) ) ) {
			return null; // Over the limit: search falls back to keywords.
		}
		$result = Provider_Client::embed( $profile['provider'], $profile['model'], $profile['key'], array( (string) $query ), $profile['url'] );
		if ( is_wp_error( $result ) || empty( $result['vectors'][0] ) ) {
			return null;
		}
		self::record( 'query', $profile, $result, array( (string) $query ), 0 );
		set_transient( $key, Vector_Codec::encode( $result['vectors'][0] ), DAY_IN_SECONDS );
		return $result['vectors'][0];
	}

	/**
	 * Record an embedding call in the spending log. Gemini does not report tokens; they are
	 * estimated from the text length (about four characters per token).
	 *
	 * @param string   $feature embed or query.
	 * @param array    $profile Embedding profile.
	 * @param array    $result  Provider_Client::embed() result.
	 * @param string[] $texts   Texts sent.
	 * @param int      $job_id  Job id.
	 * @return void
	 */
	private static function record( $feature, array $profile, array $result, array $texts, $job_id ) {
		$tokens = (int) $result['tokens'];
		if ( $tokens <= 0 ) {
			$tokens = (int) ceil( strlen( implode( ' ', $texts ) ) / 4 );
		}
		Spend::record(
			'knowledge_base',
			$feature,
			array(
				'kind'      => 'embedding',
				'tokens_in' => $tokens,
			),
			array(
				'provider' => (string) $profile['provider'],
				'model'    => (string) $profile['model'],
				'job_id'   => (int) $job_id,
			)
		);
	}
}
