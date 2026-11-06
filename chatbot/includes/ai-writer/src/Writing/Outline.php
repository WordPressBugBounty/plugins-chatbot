<?php
/**
 * Outline data: sanitising and word budgets.
 *
 * Shape: titles[3], title, angle, sections[{heading, purpose, words,
 * image{kind, query, prompt, alt}|null}], faq[], summary (bool),
 * featured_image{query, prompt, alt}, missing_facts[].
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

defined( 'ABSPATH' ) || exit;

/**
 * Outline helpers.
 */
final class Outline {

	/**
	 * Clean an outline and scale section word budgets to the target.
	 *
	 * @param array $raw    Outline from the AI or the screen.
	 * @param int   $target Target words for the whole piece.
	 * @param bool  $none   "No specific technique" for this piece (round 3).
	 * @return array
	 */
	public static function sanitise( array $raw, $target, $none = false ) {
		$titles = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', isset( $raw['titles'] ) ? (array) $raw['titles'] : array() ) ) ) ), 0, 3 );
		$title  = isset( $raw['title'] ) && '' !== trim( (string) $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : ( isset( $titles[0] ) ? $titles[0] : '' );

		$sections = array();
		$raw_list = array_values(
			array_filter(
				array_slice( isset( $raw['sections'] ) ? (array) $raw['sections'] : array(), 0, 15 ),
				static function ( $section ) {
					return is_array( $section ) && ! empty( $section['heading'] );
				}
			)
		);
		foreach ( $raw_list as $i => $section ) {
			// Round 3: each section's job and its one technique (labels come from the library).
			$plan       = \QuantumCloud\AiWriter\Persuasion\Choices::section( $section, $i, count( $raw_list ), $none );
			$sections[] = array(
				'heading'   => substr( sanitize_text_field( $section['heading'] ), 0, 200 ),
				'purpose'   => substr( sanitize_text_field( isset( $section['purpose'] ) ? $section['purpose'] : '' ), 0, 300 ),
				'words'     => max( 60, absint( isset( $section['words'] ) ? $section['words'] : 0 ) ),
				'image'     => self::image( isset( $section['image'] ) ? $section['image'] : null ),
				'job'       => $plan['job'],
				'technique' => $plan['technique'],
			);
		}

		$faq = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', isset( $raw['faq'] ) ? (array) $raw['faq'] : array() ) ) ) ), 0, 10 );

		// Scale budgets: sections get the target minus room for FAQ and summary.
		$reserve   = ( array() !== $faq ? 60 * count( $faq ) : 0 ) + ( ! empty( $raw['summary'] ) ? 60 : 0 );
		$available = max( 100 * max( 1, count( $sections ) ), (int) $target - $reserve );
		$sum       = array_sum( wp_list_pluck( $sections, 'words' ) );
		if ( $sum > 0 ) {
			foreach ( $sections as &$section ) {
				$section['words'] = max( 60, (int) round( $section['words'] * $available / $sum / 10 ) * 10 );
			}
			unset( $section );
		}

		return array(
			'titles'         => $titles,
			'title'          => $title,
			'angle'          => substr( sanitize_text_field( isset( $raw['angle'] ) ? $raw['angle'] : '' ), 0, 400 ),
			'sections'       => $sections,
			'faq'            => $faq,
			'summary'        => ! empty( $raw['summary'] ),
			'featured_image' => self::image( isset( $raw['featured_image'] ) ? $raw['featured_image'] : null ),
			'missing_facts'  => array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', isset( $raw['missing_facts'] ) ? (array) $raw['missing_facts'] : array() ) ) ) ), 0, 8 ),
		);
	}

	/**
	 * Clean an image spot.
	 *
	 * @param mixed $image Raw spot.
	 * @return array|null
	 */
	private static function image( $image ) {
		if ( ! is_array( $image ) || ( empty( $image['query'] ) && empty( $image['prompt'] ) ) ) {
			return null;
		}
		$kinds = array( 'photo', 'illustration', 'diagram', 'screenshot' );
		return array(
			'kind'   => isset( $image['kind'] ) && in_array( $image['kind'], $kinds, true ) ? $image['kind'] : 'photo',
			'query'  => substr( sanitize_text_field( isset( $image['query'] ) ? $image['query'] : '' ), 0, 120 ),
			'prompt' => substr( sanitize_text_field( isset( $image['prompt'] ) ? $image['prompt'] : '' ), 0, 600 ),
			'alt'    => substr( sanitize_text_field( isset( $image['alt'] ) ? $image['alt'] : '' ), 0, 200 ),
		);
	}
}
