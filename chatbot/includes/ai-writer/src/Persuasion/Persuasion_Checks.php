<?php
/**
 * Persuasion and clarity checks for the checklist (round 3, CR-13; rules,
 * no AI): answer first, one call to action, proof near the call to
 * action, risk reversal where the kit has a guarantee, and specific
 * details (stickiness). Each item: id, status (good, info, warn), label,
 * hint.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Persuasion;

defined( 'ABSPATH' ) || exit;

/**
 * Persuasion checks.
 */
final class Persuasion_Checks {

	/** Verbs that start a call to action. */
	const CTA = '/(?:^|[.!?]\s+|\n)\s*(book|buy|order|call|contact|sign up|subscribe|get started|start|shop|download|try|request|reserve|join|visit|claim|register|enquire|ask)\b/i';

	/** Words of real proof. */
	const PROOF = '/\b(review|reviews|rated|customers|clients|years|since|award|certified|guarantee|case study|testimonial|\d+(?:[.,]\d+)?\s*(?:%|\/5|stars?))\b/i';

	/** Risk reversal words. */
	const RISK = '/\b(guarantee|guaranteed|money back|refund|free returns?|returns? within|no risk|cancel any ?time|free cancellation|warranty)\b/i';

	/**
	 * Run the checks.
	 *
	 * @param string[] $texts   Block texts, in order.
	 * @param string   $type    Kind of piece ('product' for products).
	 * @param array    $choices Persuasion choices of the piece.
	 * @param array    $kit     Kit entries usable now.
	 * @return array[]
	 */
	public static function analyse( array $texts, $type = '', array $choices = array(), array $kit = array() ) {
		$texts = array_values( array_filter( array_map( 'trim', array_map( 'strval', $texts ) ) ) );
		$all   = implode( "\n", $texts );
		$items = array();
		if ( '' === $all ) {
			return $items;
		}

		// Answer first: the opening gets to the point.
		$first   = isset( $texts[0] ) ? $texts[0] : '';
		$words   = str_word_count( wp_strip_all_tags( $first ) );
		$items[] = self::item(
			'answer_first',
			$words <= 60 ? 'good' : 'info',
			$words <= 60 ? __( 'Opens with the point (answer first)', 'chatbot' ) : __( 'The opening is long: give the answer or main point first', 'chatbot' )
		);

		if ( ! empty( $choices['none'] ) ) {
			return $items;
		}

		// One clear call to action.
		preg_match_all( self::CTA, $all, $m );
		$verbs = array_unique( array_map( 'strtolower', $m[1] ) );
		if ( array() === $verbs ) {
			$items[] = self::item( 'one_cta', 'info', __( 'No call to action yet (optional for guides)', 'chatbot' ) );
		} else {
			$items[] = self::item(
				'one_cta',
				count( $verbs ) <= 2 ? 'good' : 'warn',
				count( $verbs ) <= 2 ? __( 'One clear call to action', 'chatbot' ) : __( 'Several different calls to action: keep one main next step', 'chatbot' ),
				implode( ', ', array_slice( $verbs, 0, 5 ) )
			);
			// Proof near the call to action: the last third of the piece.
			$tail    = substr( $all, (int) floor( strlen( $all ) * 2 / 3 ) );
			$has_kit = (bool) array_filter(
				$kit,
				static function ( $entry ) {
					return in_array( $entry['type'], array( 'review', 'testimonial', 'customer_count', 'case_study', 'certification', 'media_mention', 'years_in_business' ), true );
				}
			);
			$near    = (bool) preg_match( self::PROOF, $tail );
			$items[] = self::item(
				'proof_near_cta',
				$near ? 'good' : ( $has_kit ? 'warn' : 'info' ),
				$near ? __( 'Proof near the call to action', 'chatbot' ) : __( 'No proof near the call to action', 'chatbot' ),
				$near ? '' : ( $has_kit ? __( 'Add one piece of proof from your Persuasion Kit close to the button or link.', 'chatbot' ) : __( 'Add reviews or numbers to your Persuasion Kit to use them here.', 'chatbot' ) )
			);
		}

		// Risk reversal where the owner has a guarantee.
		$guarantee = (bool) array_filter(
			$kit,
			static function ( $entry ) {
				return 'guarantee' === $entry['type'];
			}
		);
		if ( $guarantee && in_array( $type, array( 'product', 'page_copy', 'landing', 'page' ), true ) ) {
			$risk    = (bool) preg_match( self::RISK, $all );
			$items[] = self::item(
				'risk_reversal',
				$risk ? 'good' : 'warn',
				$risk ? __( 'Mentions your guarantee (risk reversal)', 'chatbot' ) : __( 'Your guarantee is not mentioned', 'chatbot' ),
				$risk ? '' : __( 'Buyers worry less when they see your guarantee or return policy.', 'chatbot' )
			);
		}

		// Stickiness: specific details rather than adjectives.
		$specific = preg_match_all( '/\d/', $all );
		$items[]  = self::item(
			'specific',
			$specific >= 2 ? 'good' : 'info',
			$specific >= 2 ? __( 'Uses specific details', 'chatbot' ) : __( 'Few specific details: exact numbers and examples are remembered', 'chatbot' )
		);
		return $items;
	}

	/**
	 * One checklist item.
	 *
	 * @param string $id     Id.
	 * @param string $status good, info or warn.
	 * @param string $label  Label.
	 * @param string $hint   Hint.
	 * @return array
	 */
	private static function item( $id, $status, $label, $hint = '' ) {
		return array(
			'id'     => $id,
			'status' => $status,
			'label'  => $label,
			'hint'   => $hint,
		);
	}
}
