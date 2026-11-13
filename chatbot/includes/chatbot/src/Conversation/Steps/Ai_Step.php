<?php
/**
 * AI answer from the provider that is switched on, with the knowledge base,
 * page context, AI Actions forms and the guest rate limit.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation\Steps;

use QuantumCloud\Chatbot\Actions\Actions;
use QuantumCloud\Chatbot\Ai\Ai_Client;
use QuantumCloud\Chatbot\Ai\Safe_Answer;
use QuantumCloud\Chatbot\Ai\Page_Context;
use QuantumCloud\Chatbot\Ai\Provider_Settings;
use QuantumCloud\Chatbot\Conversation\Context;
use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Conversation\Reply;
use QuantumCloud\Chatbot\Conversation\Step;
use QuantumCloud\Chatbot\Leads\Ai_Forms;
use QuantumCloud\Chatbot\RateLimit\Limiter;

defined( 'ABSPATH' ) || exit;

/**
 * AI step.
 */
final class Ai_Step implements Step {

	/**
	 * Step id.
	 *
	 * @return string
	 */
	public function id() {
		return 'ai';
	}

	/**
	 * On when a provider is switched on.
	 *
	 * @return bool
	 */
	public function enabled() {
		return '' !== Provider_Settings::answering_provider();
	}

	/**
	 * Ask the AI.
	 *
	 * @param Message $message Message.
	 * @param Context $context Context.
	 * @return Reply|null Null on errors, so site search or the fallback can answer.
	 */
	public function handle( Message $message, Context $context ) {
		if ( '' === $message->text ) {
			return null;
		}
		$provider = Provider_Settings::answering_provider();
		$settings = Provider_Settings::get( $provider );

		// Channel webhooks all come from the platform's servers: limit per sender there, not per IP.
		$who = 'web' === $message->channel ? null : $message->channel . ':' . $message->session_id;
		// A widget question was already counted by the AJAX guard in this request: count it once.
		if ( ! ( null === $who && Limiter::counted( 'ai' ) ) && ! Limiter::hit( 'ai', $who ) ) {
			return Reply::make(
				'ai',
				array(
					'text' => self::limit_text(),
					'data' => array( 'limited' => true ),
					'meta' => array( 'provider' => $provider ),
				)
			);
		}

		// AI Actions as native tools when the model supports them; otherwise the delimiter path.
		$actions    = array_values(
			array_filter(
				Actions::available( $message ),
				static function ( $action ) use ( $settings ) {
					// Form Actions follow the AI Forms switch; Pro's lookups (store, REST) do not.
					return empty( $action['form'] ) || ! empty( $settings['ai_forms'] );
				}
			)
		);
		$outcomes   = array();
		$tools      = $actions ? array(
			'tools'    => Actions::tools( $actions ),
			'run_tool' => static function ( $name, $arguments ) use ( $message, &$outcomes ) {
				$outcome    = Actions::run( (string) $name, (array) $arguments, $message );
				$outcomes[] = $outcome;
				return $outcome['result'];
			},
		) : array();
		$tool_mode  = $tools && Ai_Client::tools_ready( $provider, (string) $settings['model'], $tools );
		$args       = array_merge(
			array(
				'provider'     => $provider,
				'history'      => $message->history,
				'context'      => $this->context( $message, $context, $settings, $tool_mode ? $actions : null ),
				'feature'      => 'web' === $message->channel ? 'chat' : 'chat_' . $message->channel,
				// The answer cache leaves the name out of its key and never saves an answer that mentions it.
				'visitor_name' => $message->name,
			),
			$tool_mode ? $tools : array()
		);
		$on_text    = $context->on_text;
		$flush      = null;
		$safe_flush = null;
		if ( null !== $on_text ) {
			list( $on_text, $safe_flush ) = Safe_Answer::stream_guard( $on_text );
		}
		if ( null !== $on_text && ! empty( $settings['ai_forms'] ) && ! $tool_mode ) {
			list( $on_text, $flush ) = Ai_Forms::stream_guard( $on_text );
		}
		$reply = Ai_Client::reply( $message->text, $args, $on_text );
		if ( null !== $flush ) {
			$flush();
		}
		if ( null !== $safe_flush ) {
			$safe_flush();
		}
		if ( \QuantumCloud\Chatbot\Spend\Fallback::is_limit( $reply ) ) {
			// AI spending limit: no error for the visitor; the next steps answer without AI.
			\QuantumCloud\Chatbot\Spend\Fallback::mark( $context );
			return null;
		}
		if ( is_wp_error( $reply ) ) {
			$context->notes['ai_error'] = $reply->get_error_code();
			/**
			 * The AI provider failed (wrong key, no credit, unknown model, provider down). Visitors get
			 * the friendly message; hosts keep the real error for the site admin.
			 *
			 * @param \WP_Error $error   The provider's error.
			 * @param Message   $message The visitor's message.
			 */
			do_action( 'qcld_wpbot_chat/ai_failed', $reply, $message );
			return null;
		}

		$form = ! empty( $settings['ai_forms'] ) && ! $tool_mode ? Ai_Forms::extract( $reply['text'] ) : array(
			'text' => $reply['text'],
			'form' => null,
		);
		// Safe-answer mode: the AI marks replies it is not sure about.
		$safe         = Safe_Answer::strip( $form['text'] );
		$form['text'] = $safe['text'];
		$grounded     = ! empty( $context->notes['knowledge'] ) && ! $safe['not_sure'];
		$data         = array(
			'grounded'   => $grounded,
			'confidence' => $safe['not_sure'] ? 'low' : ( $grounded ? 'high' : 'medium' ),
		);
		if ( $safe['not_sure'] ) {
			$data['not_sure']   = true;
			$data['next_steps'] = array_map(
				static function ( $button ) {
					return array(
						'label' => $button['label'],
						'type'  => 'handoff',
					);
				},
				Safe_Answer::handoff()
			);
			Fallback_Step::log( $message->text );
			// Questions Not Answered (knowledge gaps).
			/**
			 * The bot was not sure about a question (a knowledge gap).
			 *
			 * @param string  $question Question.
			 * @param string  $reason   not_sure (from here), no_answer (fallback), or thumbs_down / handoff from hosts.
			 * @param Message $message  Message.
			 */
			do_action( 'qcld_wpbot_chat/knowledge_gap', $message->text, 'not_sure', $message );
		}//end if
		if ( null !== $form['form'] ) {
			$data['form_entry'] = Ai_Forms::save( $form['form'], $message->session_id );
			$data['form_title'] = $form['form']['form_title'];
		}
		foreach ( $outcomes as $outcome ) {
			if ( ! empty( $outcome['cards'] ) ) {
				$data['cards'] = array_slice( array_merge( isset( $data['cards'] ) ? $data['cards'] : array(), (array) $outcome['cards'] ), 0, 6 );
			}
			if ( ! empty( $outcome['ok'] ) && ! empty( $outcome['entry'] ) ) {
				$data['form_entry'] = (int) $outcome['entry'];
				$data['form_title'] = (string) $outcome['title'];
			}
		}
		if ( $outcomes ) {
			$context->notes['tools'] = array_map(
				static function ( $outcome ) {
					return array(
						'action' => $outcome['action'],
						'ok'     => (bool) $outcome['ok'],
					);
				},
				$outcomes
			);
		}
		if ( ! empty( $context->notes['knowledge_sources'] ) ) {
			$data['sources'] = $context->notes['knowledge_sources'];
		}
		if ( ! empty( $context->notes['citations'] ) && self::show_citations( $message, null !== $form['form'] ) ) {
			$data['citations'] = $context->notes['citations'];
		}
		$left = Limiter::left( 'ai', $who );
		if ( null !== $left ) {
			$data['left'] = $left;
		}
		return Reply::make(
			'ai',
			array(
				'text' => $form['text'],
				'data' => $data,
				'meta' => array(
					'provider'    => $reply['provider'],
					'model'       => $reply['model'],
					'tokens_in'   => $reply['tokens_in'],
					'tokens_out'  => $reply['tokens_out'],
					'truncated'   => $reply['truncated'],
					'cost'        => isset( $reply['cost'] ) ? $reply['cost'] : 0.0,
					'price_known' => ! empty( $reply['price_known'] ),
				),
			)
		);
	}

	/**
	 * Whether source links go under this reply. Not under an AI Action's questions or its "sent"
	 * note (chatbot features round 6): the visitor is filling in a form, and the links came from
	 * the knowledge search on their answer, not from anything the reply says.
	 *
	 * @param Message $message    Message (extra ai_action: the form being filled in).
	 * @param bool    $form_saved The reply saved an AI form.
	 * @return bool
	 */
	public static function show_citations( Message $message, $form_saved = false ) {
		return Knowledge_Step::citations_enabled() && '' === trim( $message->extra( 'ai_action' ) ) && ! $form_saved;
	}

	/**
	 * Extra system text: visitor name, page, knowledge base, forms.
	 *
	 * @param Message      $message  Message.
	 * @param Context      $context  Context.
	 * @param array        $settings Provider settings.
	 * @param array[]|null $actions Actions offered as tools (tool path), or null for the delimiter path.
	 * @return string
	 */
	public function context( Message $message, Context $context, array $settings, $actions = null ) {
		$parts = array();
		if ( '' !== $message->name ) {
			$parts[] = "The user's name is: " . $message->name . '. Do not ask for their name.';
		}
		if ( ! empty( $settings['page_context'] ) && ( '' !== $message->page_title || '' !== $message->page_url ) ) {
			// Built on the server from the published post (size-limited); never from browser text.
			$parts[] = Page_Context::text( $message->page_url, $message->page_title, \QuantumCloud\Chatbot\Knowledge\Visitor_Labels::for_message( $message ) );
		}
		if ( ! empty( $context->notes['knowledge'] ) ) {
			$parts[] = "Answer from this VERIFIED KNOWLEDGE from the website. Do not invent facts; if it does not cover the question, say you don't have that information.\n\n" . $context->notes['knowledge'];
		}
		if ( is_array( $actions ) ) {
			$parts[] = Actions::instructions( $actions, $message->extra( 'ai_action' ) );
		} elseif ( ! empty( $settings['ai_forms'] ) ) {
			$forms = Ai_Forms::instructions( $message->extra( 'ai_action' ) );
			if ( '' !== $forms ) {
				$parts[] = $forms;
			}
		}
		$parts[] = Safe_Answer::rule();
		/**
		 * Extra system text sent to the AI with a visitor's question.
		 *
		 * @param string[] $parts   Parts.
		 * @param Message  $message Message.
		 */
		$parts = (array) apply_filters( 'qcld_wpbot_chat/ai_context', $parts, $message );
		return implode( "\n\n", array_filter( array_map( 'strval', $parts ), 'strlen' ) );
	}

	/**
	 * Text shown when a visitor reached the daily AI limit.
	 *
	 * @return string
	 */
	public static function limit_text() {
		/**
		 * Text shown when a visitor reached the daily AI reply limit.
		 *
		 * @param string $text Text.
		 */
		return (string) apply_filters( 'qcld_wpbot_chat/rate_limit_text', __( 'You have reached the limit of AI answers for today. Please try again tomorrow.', 'chatbot' ) );
	}
}
