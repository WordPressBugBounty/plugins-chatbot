<?php
/**
 * Knowledge base: finds the site content that answers the question and leaves
 * it for the AI step. Never answers by itself.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Conversation\Steps;

use QuantumCloud\Chatbot\Ai\Provider_Settings;
use QuantumCloud\Chatbot\Conversation\Context;
use QuantumCloud\Chatbot\Conversation\Message;
use QuantumCloud\Chatbot\Conversation\Step;
use QuantumCloud\Chatbot\Knowledge\Kb_Bridge;
use QuantumCloud\Chatbot\Knowledge\Visitor_Labels;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge step.
 */
final class Knowledge_Step implements Step {

	/**
	 * Option: show source links under knowledge-based answers ('1' on, '0' off; default on).
	 */
	const CITATIONS_OPTION = 'qcld_wpbot_citations';

	/**
	 * Whether replies carry citations (one setting for every provider; on by default).
	 *
	 * @return bool
	 */
	public static function citations_enabled() {
		$value = get_option( self::CITATIONS_OPTION, '1' );
		/**
		 * Whether knowledge-based replies carry citations.
		 *
		 * @param bool $enabled Enabled.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/citations_enabled', '0' !== (string) $value );
	}

	/**
	 * Citations for knowledge base results: one per source (title, url, source_type, score),
	 * best score first, only results with a URL.
	 *
	 * @param array[] $results Results of qcld_wpbot_core_kb_search().
	 * @return array[]
	 */
	public static function citations( array $results ) {
		$citations = array();
		foreach ( $results as $result ) {
			$url = isset( $result['url'] ) ? (string) $result['url'] : '';
			if ( '' === $url || isset( $citations[ $url ] ) ) {
				continue;
			}
			$anchor            = isset( $result['anchor'] ) ? (string) $result['anchor'] : '';
			$citations[ $url ] = array(
				'title'       => isset( $result['title'] ) && '' !== (string) $result['title'] ? (string) $result['title'] : $url,
				// The section that answered (heading-aware chunks): link straight to it.
				'url'         => '' !== $anchor && false === strpos( $url, '#' ) ? $url . '#' . rawurlencode( $anchor ) : $url,
				'source_type' => isset( $result['source_type'] ) ? (string) $result['source_type'] : '',
				'score'       => isset( $result['score'] ) ? round( (float) $result['score'], 4 ) : 0.0,
			);
			if ( ! empty( $result['heading_path'] ) ) {
				$citations[ $url ]['section'] = (string) $result['heading_path'];
			}
		}
		$citations = array_values( $citations );
		usort(
			$citations,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);
		/**
		 * Citations shown with a knowledge-based reply.
		 *
		 * @param array[] $citations Citations.
		 * @param array[] $results   Knowledge base results.
		 */
		return (array) apply_filters( 'qcld_wpbot_chat/citations', $citations, $results );
	}

	/**
	 * Step id.
	 *
	 * @return string
	 */
	public function id() {
		return 'knowledge';
	}

	/**
	 * On when the knowledge base runs and the answering provider uses it.
	 *
	 * @return bool
	 */
	public function enabled() {
		$provider = Provider_Settings::answering_provider();
		if ( '' === $provider || ! Kb_Bridge::active() || ! function_exists( 'qcld_wpbot_core_kb_search' ) ) {
			return false;
		}
		$settings = Provider_Settings::get( $provider );
		return ! empty( $settings['knowledge_base'] );
	}

	/**
	 * Store the context for the AI step.
	 *
	 * @param Message $message Message.
	 * @param Context $context Context.
	 * @return null
	 */
	public function handle( Message $message, Context $context ) {
		$results = qcld_wpbot_core_kb_search(
			$message->text,
			array(
				'language' => $message->language,
				'labels'   => Visitor_Labels::for_message( $message ),
			)
		);
		if ( is_array( $results ) && $results ) {
			$context->notes['knowledge']         = \QuantumCloud\WPBotCore\Knowledge\Kb_Search::context( $results );
			$context->notes['knowledge_sources'] = array_values(
				array_filter(
					array_map(
						static function ( $result ) {
							return '' !== $result['url'] ? array(
								'title' => $result['title'],
								'url'   => $result['url'],
							) : null;
						},
						$results
					)
				)
			);
			$context->notes['knowledge_results'] = $results;
			$context->notes['citations']         = self::citations( $results );
		}
		return null;
	}
}
