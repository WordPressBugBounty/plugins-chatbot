<?php
/**
 * Site Context, layer 3: the chatbot's knowledge base, through the host
 * adapter (get_knowledge_search / get_knowledge_writer). Reading asks the
 * knowledge base a few fixed questions about the business. Building adds
 * the site's most important pages and products to it with the host's own
 * indexer, one post per job step. Hosts without a knowledge base return
 * null for both and the feature is simply not offered.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Context;

use QuantumCloud\WPBotCore\Core;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use QuantumCloud\WPBotCore\Privacy\Redactor;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Chatbot knowledge base.
 */
final class Knowledge {

	const STEP = 'core_context_knowledge_add';
	const MAX  = 4000;

	/**
	 * Can the knowledge base be read?
	 *
	 * @return bool
	 */
	public static function readable() {
		return is_callable( self::search() );
	}

	/**
	 * Can pages be added to the knowledge base?
	 *
	 * @return bool
	 */
	public static function writable() {
		return is_callable( self::writer() );
	}

	/**
	 * Ask the knowledge base about the business.
	 *
	 * @param string $name Business name.
	 * @return array{text: string, answers: int}|WP_Error
	 */
	public static function read( $name ) {
		$search = self::search();
		if ( ! is_callable( $search ) ) {
			return new WP_Error( 'qcld_wpbot_core_no_knowledge', __( 'This site has no chatbot knowledge base to read.', 'chatbot' ) );
		}
		$queries = array(
			trim( $name . ' about us' ),
			'products and services we offer',
			'prices and plans',
			'opening hours, location and contact',
			'frequently asked questions',
			'shipping, returns and support',
		);
		$parts   = array();
		$answers = 0;
		foreach ( $queries as $query ) {
			$result = call_user_func( $search, $query, 3 );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$text = trim( wp_strip_all_tags( (string) $result ) );
			if ( '' !== $text ) {
				++$answers;
				$parts[] = Site_Scan::cut( $text, 900 );
			}
		}
		$text = Redactor::redact( implode( "\n\n", array_unique( $parts ) ) );
		return array(
			'text'    => Site_Scan::cut( $text, self::MAX ),
			'answers' => $answers,
		);
	}

	/**
	 * Start a job that adds the given posts to the knowledge base.
	 *
	 * @param string $module   Module starting it.
	 * @param int[]  $post_ids Posts.
	 * @return int|WP_Error Job id.
	 */
	public static function build( $module, array $post_ids ) {
		if ( ! self::writable() ) {
			return new WP_Error( 'qcld_wpbot_core_no_knowledge', __( 'This site has no chatbot knowledge base to build.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$steps = array();
		foreach ( array_slice( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ), 0, 60 ) as $id ) {
			$steps[] = array(
				'kind'  => self::STEP,
				/* translators: %s: page title. */
				'label' => sprintf( __( 'Add "%s" to the chatbot knowledge base', 'chatbot' ), wp_strip_all_tags( get_the_title( $id ) ) ),
				'input' => array( 'post_id' => $id ),
			);
		}
		if ( array() === $steps ) {
			return new WP_Error( 'qcld_wpbot_core_nothing', __( 'There are no published pages to add.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$job = Job_Store::create( $module, 'context_knowledge', array( 'title' => __( 'Build the chatbot knowledge base', 'chatbot' ) ), $steps );
		return $job ? $job : new WP_Error( 'qcld_wpbot_core_job', __( 'The job could not be created.', 'chatbot' ), array( 'status' => 500 ) );
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
	 * One post into the knowledge base.
	 *
	 * @param array $step Step.
	 * @return array|WP_Error
	 */
	public static function step( $step ) {
		$input = isset( $step['input'] ) ? (array) $step['input'] : array();
		$id    = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( ! $id || 'publish' !== get_post_status( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return array( 'skipped' => true );
		}
		$writer = self::writer();
		if ( ! is_callable( $writer ) ) {
			return new WP_Error( 'qcld_wpbot_core_no_knowledge', __( 'The chatbot knowledge base is no longer available.', 'chatbot' ) );
		}
		$result = call_user_func( $writer, $id );
		return is_wp_error( $result ) ? $result : array( 'added' => $id );
	}

	/**
	 * Host search callable.
	 *
	 * @return callable|null
	 */
	private static function search() {
		$host = Core::instance()->host();
		return $host ? $host->get_knowledge_search() : null;
	}

	/**
	 * Host writer callable.
	 *
	 * @return callable|null
	 */
	private static function writer() {
		$host = Core::instance()->host();
		return $host ? $host->get_knowledge_writer() : null;
	}
}
