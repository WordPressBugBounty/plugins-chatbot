<?php
/**
 * Runs a visitor message through the steps until one answers.
 *
 * Default order (the legacy widget's order, now on the server):
 * Simple Text Responses, DialogFlow, site search (when it has priority),
 * knowledge base, AI, site search (otherwise), fallback.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation;

use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Conversation pipeline.
 */
final class Pipeline {

	/**
	 * Steps in order.
	 *
	 * @var Step[]
	 */
	private $steps;

	/**
	 * Constructor.
	 *
	 * @param Step[]|null $steps Steps; null for the default steps (filterable).
	 */
	public function __construct( $steps = null ) {
		$this->steps = null === $steps ? self::default_steps() : array_values( $steps );
	}

	/**
	 * Default steps.
	 *
	 * @return Step[]
	 */
	public static function default_steps() {
		$search      = new Steps\Search_Step();
		$search_last = '1' === (string) Options::get( 'qc_site_search_priority', '' );
		$steps       = array( new Steps\Str_Step(), new Steps\DialogFlow_Step() );
		if ( ! $search_last ) {
			$steps[] = $search;
		}
		$steps[] = new Steps\Knowledge_Step();
		$steps[] = new Steps\Ai_Step();
		if ( $search_last ) {
			$steps[] = $search;
		}
		$steps[] = new Steps\Fallback_Step();

		/**
		 * Conversation steps, in order. Pro and add-ons add or replace steps here.
		 *
		 * @param Step[] $steps Steps.
		 */
		$filtered = apply_filters( 'qcld_wpbot_chat/pipeline_steps', $steps );
		return array_values(
			array_filter(
				is_array( $filtered ) ? $filtered : $steps,
				static function ( $step ) {
					return $step instanceof Step;
				}
			)
		);
	}

	/**
	 * Steps.
	 *
	 * @return Step[]
	 */
	public function steps() {
		return $this->steps;
	}

	/**
	 * Whether replies carry the debug view (site administrators only; filter
	 * qcld_wpbot_chat/debug_allowed).
	 *
	 * @return bool
	 */
	public static function debug_allowed() {
		$allowed = function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
		/**
		 * Whether this reply carries the admin debug view.
		 *
		 * @param bool $allowed Allowed.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/debug_allowed', $allowed );
	}

	/**
	 * The admin debug view of a reply: steps, knowledge base matches with scores, model,
	 * tokens and cost. Never contains keys or the system prompt.
	 *
	 * @param Reply   $reply   Reply.
	 * @param Context $context Context.
	 * @return array
	 */
	public static function debug( Reply $reply, Context $context ) {
		$sources = array();
		foreach ( isset( $context->notes['knowledge_results'] ) ? (array) $context->notes['knowledge_results'] : array() as $result ) {
			$sources[] = array(
				'title'       => isset( $result['title'] ) ? (string) $result['title'] : '',
				'url'         => isset( $result['url'] ) ? (string) $result['url'] : '',
				'source_type' => isset( $result['source_type'] ) ? (string) $result['source_type'] : '',
				'score'       => isset( $result['score'] ) ? round( (float) $result['score'], 4 ) : 0.0,
			);
		}
		$meta = $reply->meta;
		return array(
			'answered_by' => $reply->source,
			'steps'       => $context->trace,
			'sources'     => $sources,
			'provider'    => isset( $meta['provider'] ) ? (string) $meta['provider'] : '',
			'model'       => isset( $meta['model'] ) ? (string) $meta['model'] : '',
			'tokens_in'   => isset( $meta['tokens_in'] ) ? (int) $meta['tokens_in'] : 0,
			'tokens_out'  => isset( $meta['tokens_out'] ) ? (int) $meta['tokens_out'] : 0,
			'cost'        => isset( $meta['cost'] ) ? (float) $meta['cost'] : 0.0,
			'price_known' => ! empty( $meta['price_known'] ),
			'ai_error'    => isset( $context->notes['ai_error'] ) ? (string) $context->notes['ai_error'] : '',
			'tools'       => isset( $context->notes['tools'] ) ? (array) $context->notes['tools'] : array(),
		);
	}

	/**
	 * Answer a message.
	 *
	 * @param Message      $message Visitor message.
	 * @param Context|null $context Context.
	 * @return Reply Always a reply; the fallback step answers when nothing else does.
	 */
	public function run( Message $message, $context = null ) {
		$context = $context instanceof Context ? $context : new Context();

		/**
		 * A visitor message arrived.
		 *
		 * @param Message $message Message.
		 */
		do_action( 'qcld_wpbot_chat/message_received', $message );

		$reply = null;
		foreach ( $this->steps as $step ) {
			if ( $context->only && ! in_array( $step->id(), $context->only, true ) ) {
				continue;
			}
			if ( ! $step->enabled() ) {
				$context->trace[ $step->id() ] = 'off';
				continue;
			}
			$reply                         = $step->handle( $message, $context );
			$context->trace[ $step->id() ] = $reply instanceof Reply ? 'answered' : 'passed';
			if ( $reply instanceof Reply ) {
				break;
			}
		}
		if ( ! $reply instanceof Reply ) {
			$reply = Reply::make( 'none' );
		}
		// Stored with the message for the session detail; shown in the reply to administrators only.
		$context->notes['debug'] = self::debug( $reply, $context );
		if ( self::debug_allowed() ) {
			$reply->data['debug'] = $context->notes['debug'];
		}

		/**
		 * The bot answered.
		 *
		 * @param Reply   $reply   Reply.
		 * @param Message $message Message.
		 */
		do_action( 'qcld_wpbot_chat/message_sent', $reply, $message );
		return $reply;
	}
}
