<?php
/**
 * Quick write: three variations of a short piece in one AI call, checked
 * against the network's limits and the AI-pattern rules, saved as private
 * Content Library items.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Quick;

use QuantumCloud\AiWriter\Persuasion\Choices;
use QuantumCloud\AiWriter\Persuasion\Context;
use QuantumCloud\AiWriter\Persuasion\Persuasion;

use QuantumCloud\AiWriter\Checks\Ai_Pattern_Check;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\AiWriter\Voice\Voices;
use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\AiWriter\Writing\Writer_AI;
use QuantumCloud\WPBotCore\Library\Items;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Quick write service.
 */
final class Quick_Write {

	const ITEM_TYPE = 'quick_write';

	/**
	 * Write three variations.
	 *
	 * @param string $template_id Template id.
	 * @param array  $fields      Field key => value.
	 * @param int    $source_post Optional post to start from.
	 * @return array|WP_Error variations: [{text, chars, over, flags}].
	 */
	public static function generate( $template_id, array $fields, $source_post = 0 ) {
		$templates = Templates::all();
		if ( ! isset( $templates[ $template_id ] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_template', __( 'Choose a template.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$template = $templates[ $template_id ];

		$input = '';
		foreach ( (array) $template['fields'] as $field ) {
			$key   = isset( $field['key'] ) ? (string) $field['key'] : '';
			$value = isset( $fields[ $key ] ) ? Prompts::cut( sanitize_textarea_field( (string) $fields[ $key ] ), 3000 ) : '';
			if ( '' !== $value ) {
				$input .= $field['label'] . ' ' . $value . "\n";
			}
		}
		$source_post = absint( $source_post );
		if ( $source_post && current_user_can( 'read_post', $source_post ) ) {
			$post   = get_post( $source_post );
			$input .= "\nStart from this " . ( $post ? $post->post_type : 'post' ) . ":\nTitle: " . get_the_title( $source_post ) . "\nURL: " . get_permalink( $source_post ) . "\n" . Prompts::clip( wp_strip_all_tags( excerpt_remove_blocks( (string) ( $post ? $post->post_content : '' ) ) ), 4000 ) . "\n";
		}
		if ( '' === trim( $input ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_input', __( 'Fill in at least one field, or start from a post.', 'chatbot' ), array( 'status' => 400 ) );
		}

		/**
		 * The voice of a Quick write piece (Pro: a channel variant).
		 *
		 * @param array|null $voice       Site voice or null.
		 * @param string     $template_id Template id.
		 * @param array      $fields      Fields.
		 */
		$voice = apply_filters( 'qcld_wpbot_writer/quick_voice', Voices::site_voice(), $template_id, $fields );
		$limit = (int) $template['limit'];
		// Round 3: how this piece persuades (detected per piece, or the owner's choice).
		$choices    = Choices::resolve( isset( $fields['persuasion'] ) && is_array( $fields['persuasion'] ) ? $fields['persuasion'] : array(), $template_id );
		$persuasion = Context::prompt( $choices, Prompts::cut( $input, 300 ) );
		$system     = "Task: quick_write\nWrite a " . $template['label'] . ' for a small business. Write three clearly different variations (different hooks and angles), each complete on its own.' .
			"\nRules for this format: " . $template['rules'] .
			( $limit ? "\nHard limit: " . $limit . ' characters' . ( ! empty( $template['split'] ) ? ' per post' : '' ) . ', including spaces and hashtags.' : '' ) .
			"\nUse emoji only if the voice profile mentions them.\n" . Prompts::business() . "\n" . Prompts::language() . "\n" . Prompts::voice( $voice ? $voice['profile'] : array() ) . "\n" . Prompts::writing_rules() .
			( '' !== $persuasion ? "\n" . $persuasion . ( empty( $choices['none'] ) ? "\nEach variation may use a different hook, but one technique per variation." : '' ) : '' ) .
			"\nAnswer with JSON: {\"variations\": [\"...\", \"...\", \"...\"]}. Use \\n for line breaks.";

		// CR-14: "Write 3 more" and Regenerate with changes.
		$regen = isset( $fields['regenerate'] ) && is_array( $fields['regenerate'] ) ? \QuantumCloud\AiWriter\Regenerate\Options::sanitise( $fields['regenerate'] ) : null;
		if ( null !== $regen ) {
			$system .= "\n" . \QuantumCloud\AiWriter\Regenerate\Options::prompt( $regen );
		}
		$result = Writer_AI::ask_json(
			'quick_' . $template_id,
			$system,
			$input,
			array(
				'mode'       => 'creative',
				'max_tokens' => 2500,
				'cache'      => false,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$variations = array();
		foreach ( isset( $result['data']['variations'] ) ? (array) $result['data']['variations'] : array() as $text ) {
			if ( ! is_scalar( $text ) ) {
				continue;
			}
			$text = trim( sanitize_textarea_field( str_replace( '\n', "\n", (string) $text ) ) );
			if ( '' === $text ) {
				continue;
			}
			$fitted       = self::fit( $text, $limit, ! empty( $template['split'] ) );
			$check        = Ai_Pattern_Check::analyse( preg_split( '/\n{2,}/', $fitted['text'] ), array(), (array) Settings::get( 'cliches' ) );
			$variations[] = array(
				'text'    => $fitted['text'],
				'chars'   => self::length( $fitted['text'] ),
				'trimmed' => $fitted['trimmed'],
				'flags'   => array_slice( $check['flags'], 0, 5 ),
				// Round 3: invented proof, fake urgency, engagement bait and the rest (can't be hidden).
				'honesty' => Persuasion::available() ? qcld_wpbot_core_honesty_check( $fitted['text'], Context::honesty( $input ) ) : array(),
			);
		}
		if ( array() === $variations ) {
			return new WP_Error( 'qcld_wpbot_writer_empty_answer', __( 'The AI returned nothing. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$out = array(
			'variations' => array_slice( $variations, 0, 3 ),
			'limit'      => $limit,
			'persuasion' => Choices::labels( $choices ),
		);
		// Version history per template (not tied to a post: kept for the user for a week).
		$out['versions'] = \QuantumCloud\AiWriter\Regenerate\History::add( 0, 'quick:' . $template_id, $out, null !== $regen ? $regen : array() );
		return $out;
	}

	/**
	 * Keep a piece within its limit: cut at the last full sentence (or word)
	 * that fits. Thread posts (blank-line separated) are fitted one by one.
	 *
	 * @param string $text  Text.
	 * @param int    $limit Characters (0 = none).
	 * @param bool   $split Limit applies per blank-line-separated post.
	 * @return array{text: string, trimmed: bool}
	 */
	public static function fit( $text, $limit, $split = false ) {
		if ( $limit <= 0 ) {
			return array(
				'text'    => $text,
				'trimmed' => false,
			);
		}
		$parts   = $split ? preg_split( '/\n\s*\n/', $text ) : array( $text );
		$trimmed = false;
		foreach ( $parts as $index => $part ) {
			if ( self::length( $part ) <= $limit ) {
				continue;
			}
			$trimmed = true;
			$cut     = Prompts::cut( $part, $limit );
			$stop    = max( (int) strrpos( $cut, '. ' ), (int) strrpos( $cut, '! ' ), (int) strrpos( $cut, '? ' ), (int) strrpos( $cut, "\n" ) );
			if ( $stop > strlen( $cut ) / 2 ) {
				$cut = substr( $cut, 0, $stop + 1 );
			} else {
				$space = strrpos( $cut, ' ' );
				$cut   = false !== $space ? substr( $cut, 0, $space ) : $cut;
			}
			$parts[ $index ] = rtrim( $cut );
		}
		return array(
			'text'    => implode( "\n\n", array_map( 'trim', $parts ) ),
			'trimmed' => $trimmed,
		);
	}

	/**
	 * Save a piece to the Content Library (private, with revisions).
	 *
	 * @param string $template_id Template id.
	 * @param string $text        Text.
	 * @param int    $source_post Post it was made from.
	 * @return int|WP_Error Item id.
	 */
	public static function save( $template_id, $text, $source_post = 0 ) {
		$templates = Templates::all();
		if ( ! isset( $templates[ $template_id ] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_template', __( 'Choose a template.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$text = trim( sanitize_textarea_field( (string) $text ) );
		if ( '' === $text ) {
			return new WP_Error( 'qcld_wpbot_writer_empty', __( 'There is nothing to save.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$first = strtok( $text, "\n" );
		return Items::create(
			array(
				'module'      => Module::ID,
				'type'        => self::ITEM_TYPE,
				'title'       => $templates[ $template_id ]['label'] . ': ' . Prompts::clip( false !== $first ? $first : $text, 60 ),
				'content'     => $text,
				'data'        => array( 'template' => $template_id ),
				'source_post' => absint( $source_post ),
			)
		);
	}

	/**
	 * The current user's recent Quick write items.
	 *
	 * @return array[]
	 */
	public static function recent() {
		return Items::query(
			array(
				'module' => Module::ID,
				'type'   => self::ITEM_TYPE,
				'author' => get_current_user_id(),
				'limit'  => 20,
			)
		);
	}

	/**
	 * Characters as people count them.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}
}
