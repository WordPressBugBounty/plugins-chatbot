<?php
/**
 * AI check of saved answers (AI Settings > Answer cache). The owner starts it; nothing runs on its own.
 *
 * Each check sends the question, the saved answer and the knowledge base text found for the question
 * today to the provider that answers the chatbot, and asks for a verdict: correct, wrong or unsure.
 * Wrong and unsure answers are flagged, so the cache stops serving them at once; the next visitor
 * gets a fresh answer. The check is recorded as AI use under the feature "cache_review".
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Answer review.
 */
final class Answer_Review {

	/**
	 * Most checks in one "Check all" run.
	 */
	const BATCH = 50;

	/**
	 * Rough tokens of one check (instructions, question, answer, knowledge in; verdict out).
	 */
	const TOKENS_IN  = 1500;
	const TOKENS_OUT = 80;

	/**
	 * Check one saved answer and store the verdict.
	 *
	 * @param int $id Answer id.
	 * @return array|WP_Error { verdict, reason, status }
	 */
	public static function check( $id ) {
		$row = Answer_Cache::row( (int) $id );
		if ( null === $row ) {
			return new WP_Error( 'qcld_wpbot_chat_cache_missing', __( 'This saved answer no longer exists.', 'chatbot' ) );
		}
		$verdict = self::ask( (string) $row['question'], (string) $row['answer'], self::knowledge( (string) $row['question'] ) );
		if ( is_wp_error( $verdict ) ) {
			return $verdict;
		}
		$status = 'correct' === $verdict['verdict'] ? Answer_Cache::CORRECT : Answer_Cache::FLAGGED;
		$labels = self::labels();
		Answer_Cache::set_status( (int) $id, $status, $labels[ $verdict['verdict'] ] . ': ' . $verdict['reason'] );
		$verdict['status'] = $status;
		return $verdict;
	}

	/**
	 * The knowledge base text found for a question today ('' when there is none).
	 *
	 * @param string $question Question.
	 * @return string
	 */
	public static function knowledge( $question ) {
		if ( ! function_exists( 'qcld_wpbot_core_kb_search' ) || ! class_exists( '\QuantumCloud\WPBotCore\Knowledge\Kb_Search' ) ) {
			return '';
		}
		$results = qcld_wpbot_core_kb_search( (string) $question, array() );
		return is_array( $results ) && $results ? (string) \QuantumCloud\WPBotCore\Knowledge\Kb_Search::context( $results ) : '';
	}

	/**
	 * Ask the AI for a verdict.
	 *
	 * @param string $question  Question.
	 * @param string $answer    Saved answer.
	 * @param string $knowledge Knowledge base text.
	 * @return array|WP_Error { verdict: correct|wrong|unsure, reason }
	 */
	public static function ask( $question, $answer, $knowledge ) {
		$prompt = self::prompt( $question, $answer, $knowledge );
		$args   = array(
			'raw'        => true,
			'json'       => true,
			'context'    => self::instructions(),
			'feature'    => 'cache_review',
			'max_tokens' => 300,
		);
		/**
		 * Answer the review without calling the AI (tests): return the AI's text, or null to call it.
		 *
		 * @param string|null $text   Default null.
		 * @param string      $prompt What would be sent.
		 */
		$text = apply_filters( 'qcld_wpbot_chat/pre_answer_review', null, $prompt );
		if ( null === $text ) {
			$reply = Ai_Client::reply( $prompt, $args );
			if ( is_wp_error( $reply ) ) {
				return $reply;
			}
			$text = (string) $reply['text'];
		}
		return self::parse( (string) $text );
	}

	/**
	 * The reviewer's instructions.
	 *
	 * @return string
	 */
	public static function instructions() {
		return "You check answers a website's chatbot gave to visitors. Compare the ANSWER with the QUESTION and the WEBSITE KNOWLEDGE.\n"
			. "Reply with one JSON object only: {\"verdict\": \"correct\" | \"wrong\" | \"unsure\", \"reason\": \"one short sentence\"}.\n"
			. "- correct: the answer is accurate, answers the question, and does not contradict the website knowledge. A correct general-knowledge answer is correct.\n"
			. "- wrong: it contradicts the website knowledge, invents business facts (prices, policies, opening hours, contact details, dates, products) that the knowledge does not contain, or does not answer the question.\n"
			. "- unsure: the answer states business facts the website knowledge does not cover, so they cannot be verified.\n"
			. 'Write the reason in the language of the question.';
	}

	/**
	 * The text sent for one check.
	 *
	 * @param string $question  Question.
	 * @param string $answer    Answer.
	 * @param string $knowledge Knowledge.
	 * @return string
	 */
	public static function prompt( $question, $answer, $knowledge ) {
		return "QUESTION:\n" . trim( (string) $question )
			. "\n\nANSWER:\n" . trim( wp_strip_all_tags( (string) $answer ) )
			. "\n\nWEBSITE KNOWLEDGE:\n" . ( '' !== trim( (string) $knowledge ) ? trim( (string) $knowledge ) : '(Nothing was found in the knowledge base for this question.)' );
	}

	/**
	 * Read the verdict from the AI's text.
	 *
	 * @param string $text AI text.
	 * @return array|WP_Error
	 */
	public static function parse( $text ) {
		$text = trim( (string) preg_replace( '/^```(?:json)?|```$/m', '', trim( (string) $text ) ) );
		$data = json_decode( $text, true );
		if ( ! is_array( $data ) && preg_match( '/\{.*\}/s', $text, $m ) ) {
			$data = json_decode( $m[0], true );
		}
		$verdict = is_array( $data ) && isset( $data['verdict'] ) ? strtolower( trim( (string) $data['verdict'] ) ) : '';
		if ( ! in_array( $verdict, array( 'correct', 'wrong', 'unsure' ), true ) ) {
			return new WP_Error( 'qcld_wpbot_chat_review_unreadable', __( 'The AI\'s verdict could not be read. Try again.', 'chatbot' ) );
		}
		$reason = isset( $data['reason'] ) ? trim( wp_strip_all_tags( (string) $data['reason'] ) ) : '';
		return array(
			'verdict' => $verdict,
			'reason'  => function_exists( 'mb_substr' ) ? mb_substr( $reason, 0, 300, 'UTF-8' ) : substr( $reason, 0, 300 ),
		);
	}

	/**
	 * Verdict labels.
	 *
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			'correct' => __( 'AI check: correct', 'chatbot' ),
			'wrong'   => __( 'AI check: wrong', 'chatbot' ),
			'unsure'  => __( 'AI check: unsure', 'chatbot' ),
		);
	}

	/**
	 * Estimated cost of checking a number of answers with the provider that answers (USD; 0 when unknown).
	 *
	 * @param int $count Answers.
	 * @return array{provider:string,model:string,cost:float,known:bool}
	 */
	public static function estimate( $count ) {
		$provider = Provider_Settings::answering_provider();
		$settings = '' !== $provider ? Provider_Settings::get( $provider ) : null;
		$model    = is_array( $settings ) ? (string) $settings['model'] : '';
		$usage    = '\QuantumCloud\WPBotCore\AI\Usage';
		$cost     = '' !== $model && class_exists( $usage ) && method_exists( $usage, 'estimate' ) ? (float) $usage::estimate( $provider, $model, self::TOKENS_IN, self::TOKENS_OUT ) : 0.0;
		return array(
			'provider' => $provider,
			'model'    => $model,
			'cost'     => max( 0, (int) $count ) * $cost,
			'known'    => $cost > 0,
		);
	}
}
