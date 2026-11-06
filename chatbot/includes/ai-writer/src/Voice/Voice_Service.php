<?php
/**
 * Creates a voice profile from the owner's samples and answers, and writes
 * a sample paragraph in a voice ("Try this voice").
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Voice;

use QuantumCloud\AiWriter\Regenerate\Options;
use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\AiWriter\Writing\Writer_AI;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Voice generation.
 */
final class Voice_Service {

	/**
	 * Build a profile from samples (post ids and/or pasted text) and answers.
	 *
	 * @param int[]    $post_ids Posts or pages the owner picked.
	 * @param string[] $texts    Pasted samples.
	 * @param array    $answers  audience, formality, point_of_view, humour, always_words, never_words.
	 * @param array    $regen    Regenerate options (CR-14), or null for a first profile.
	 * @return array|WP_Error Profile.
	 */
	public static function create_profile( array $post_ids, array $texts, array $answers, $regen = null ) {
		$samples = self::samples( $post_ids, $texts );
		if ( array() === $samples ) {
			return new WP_Error( 'qcld_wpbot_writer_no_samples', __( 'Pick at least one post or page, or paste some text you wrote.', 'chatbot' ), array( 'status' => 400 ) );
		}

		$answer_lines = array();
		foreach ( array( 'audience', 'formality', 'point_of_view', 'humour', 'always_words', 'never_words' ) as $key ) {
			if ( ! empty( $answers[ $key ] ) ) {
				$answer_lines[] = str_replace( '_', ' ', $key ) . ': ' . sanitize_text_field( (string) $answers[ $key ] );
			}
		}

		$system = "Task: voice_profile\nYou describe how a business owner writes, so other writing can match it. Describe style only, never content. Answer with one JSON object.";
		$prompt = "Owner's answers:\n" . ( array() === $answer_lines ? '(none)' : implode( "\n", $answer_lines ) )
			. "\n\nSamples of the owner's writing:\n" . implode( "\n---\n", array_map( array( Prompts::class, 'clip' ), $samples, array_fill( 0, count( $samples ), 2500 ) ) )
			. "\n\nReturn JSON with keys: summary (one sentence), sliders {formal, serious, technical, reserved} as 0-100 where 0 is formal/serious/technical/reserved and 100 is casual/playful/simple/enthusiastic, point_of_view (we, I or you), sentence_length, openings, signature_phrases (array), avoid_words (array), formatting, spelling (US or UK), reading_level, cta_style.";

		$args = array( 'max_tokens' => 900 );
		if ( is_array( $regen ) ) {
			$system       .= "\n" . Options::prompt( $regen );
			$args['cache'] = false;
		}
		$result = Writer_AI::ask_json( 'voice_profile', $system, $prompt, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$profile             = $result['data'];
		$profile['excerpts'] = self::excerpts( $samples );
		return Voices::sanitise( $profile );
	}

	/**
	 * Write one sample paragraph in a voice.
	 *
	 * @param array  $profile Profile.
	 * @param string $topic   Topic.
	 * @param array  $regen   Regenerate options (CR-14), or null.
	 * @return string|WP_Error Paragraph (safe HTML).
	 */
	public static function try_voice( array $profile, $topic, $regen = null ) {
		$topic  = '' !== trim( (string) $topic ) ? sanitize_text_field( $topic ) : get_bloginfo( 'description' );
		$system = "Task: try_voice\nWrite one paragraph of about 90 words for the business's website.\n" . Prompts::voice( Voices::sanitise( $profile ) ) . "\n" . Prompts::writing_rules() . "\n" . Prompts::language();
		if ( is_array( $regen ) ) {
			$system .= "\n" . Options::prompt( $regen );
		}
		$response = Writer_AI::ask(
			'try_voice',
			$system,
			'Topic: ' . $topic,
			array(
				'mode'       => 'creative',
				'max_tokens' => 300,
				'cache'      => false,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return \QuantumCloud\AiWriter\Output\Kses_Policy::inline( $response->text );
	}

	/**
	 * Plain-text samples from posts the user may read and pasted text.
	 *
	 * @param int[]    $post_ids Post ids.
	 * @param string[] $texts    Pasted texts.
	 * @return string[]
	 */
	private static function samples( array $post_ids, array $texts ) {
		$samples = array();
		foreach ( array_slice( array_map( 'absint', $post_ids ), 0, 5 ) as $post_id ) {
			$post = get_post( $post_id );
			if ( $post && current_user_can( 'read_post', $post_id ) ) {
				$samples[] = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
			}
		}
		foreach ( array_slice( $texts, 0, 5 ) as $text ) {
			$samples[] = trim( sanitize_textarea_field( (string) $text ) );
		}
		return array_values( array_filter( $samples ) );
	}

	/**
	 * Two or three short excerpts from the samples (the owner's own words).
	 *
	 * @param string[] $samples Samples.
	 * @return string[]
	 */
	private static function excerpts( array $samples ) {
		$excerpts = array();
		foreach ( $samples as $sample ) {
			$paragraphs = preg_split( '/\n\s*\n/', $sample );
			foreach ( (array) $paragraphs as $paragraph ) {
				$words = Prompts::word_count( $paragraph );
				if ( $words >= 30 && $words <= 120 ) {
					$excerpts[] = trim( $paragraph );
					break;
				}
			}
			if ( count( $excerpts ) >= 3 ) {
				break;
			}
		}
		if ( array() === $excerpts && isset( $samples[0] ) ) {
			$excerpts[] = Prompts::clip( $samples[0], 500 );
		}
		return $excerpts;
	}
}
