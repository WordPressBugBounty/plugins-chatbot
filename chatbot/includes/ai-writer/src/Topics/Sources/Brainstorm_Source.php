<?php
/**
 * Topic source: AI brainstorm from what the site is about (name, tagline,
 * main topics, categories) with the owner's AI key. Existing post titles
 * are sent so the ideas do not repeat what the site already has.
 *
 * Works with no other source connected, so Topic Finder is never empty.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics\Sources;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Topics\Topic_Finder;
use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\AiWriter\Writing\Writer_AI;
use QuantumCloud\WPBotCore\AI\Keys;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * AI brainstorm source.
 */
final class Brainstorm_Source {

	/**
	 * Connection state.
	 *
	 * @return array
	 */
	public static function status() {
		if ( array() === (array) Keys::call_order( Module::ID ) ) {
			return array(
				'status'  => 'not_connected',
				'message' => __( 'Add an AI key to get ideas from your site profile.', 'chatbot' ),
				'fix'     => array(
					'label' => __( 'Add a key', 'chatbot' ),
					'tab'   => 'settings',
				),
			);
		}
		return array(
			'status'  => 'connected',
			'message' => '',
		);
	}

	/**
	 * Ideas from the AI.
	 *
	 * @param array $context Context: keywords, job_id.
	 * @return array[]|WP_Error
	 */
	public static function ideas( array $context ) {
		if ( array() === (array) Keys::call_order( Module::ID ) ) {
			return array();
		}
		$titles = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 40,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$have   = array_map(
			static function ( $id ) {
				return html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
			},
			$titles
		);
		$prompt = "Task: topic_ideas\n"
			. \QuantumCloud\AiWriter\Writing\Prompts::business() . "\n"
			. 'Main topics: ' . implode( ', ', isset( $context['keywords'] ) ? (array) $context['keywords'] : Topic_Finder::keywords() ) . "\n"
			. ( array() !== $have ? 'Already published (do not repeat): ' . Prompts::clip( implode( ' | ', $have ), 2500 ) . "\n" : '' )
			. "Suggest 10 article topics real customers of this business would search for or ask about. Prefer concrete questions and problems over generic topics. For each, give a one-line reason (who it helps and why now).\n"
			. 'Answer as JSON: {"ideas":[{"title":"...","reason":"...","relevance":0-100}]}. ' . Prompts::language();
		$result = Writer_AI::ask_json(
			'topic_ideas',
			'You plan useful content for a small business website. You never invent facts about the business.',
			$prompt,
			array(
				'mode'       => 'creative',
				'max_tokens' => 1500,
				'job_id'     => isset( $context['job_id'] ) ? (int) $context['job_id'] : 0,
				'cache'      => false,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$ideas = array();
		foreach ( isset( $result['data']['ideas'] ) ? array_slice( (array) $result['data']['ideas'], 0, 15 ) : array() as $idea ) {
			$title = is_array( $idea ) && isset( $idea['title'] ) ? trim( sanitize_text_field( (string) $idea['title'] ) ) : '';
			if ( '' === $title ) {
				continue;
			}
			$relevance = isset( $idea['relevance'] ) ? min( 100, absint( $idea['relevance'] ) ) : 50;
			$ideas[]   = array(
				'title'      => $title,
				'ref'        => md5( strtolower( $title ) ),
				'reason'     => isset( $idea['reason'] ) ? sanitize_text_field( (string) $idea['reason'] ) : '',
				'trend'      => 'steady',
				'relevance'  => $relevance,
				'rank_score' => $relevance / 5,
			);
		}
		$ideas['_usage'] = Writer_AI::usage( $result['response'] );
		return $ideas;
	}
}
