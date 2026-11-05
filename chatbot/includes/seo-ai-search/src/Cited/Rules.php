<?php
/**
 * Get cited by AI: the built-in rules. They always win over the owner's
 * focus and "Steer this draft" text, and extensions can add rules but never
 * remove these (filter qcld_wpbot_seo/cited_rules only appends).
 *
 * - The owner's own voice: no posing as a customer, a bystander or several people.
 * - No made-up facts, experiences, quotes, reviews or numbers.
 * - Disclosure when the owner's own product or business is mentioned.
 * - No near-identical text across places.
 * - The community's rules come first.
 *
 * Also the platform writing norms (Reddit and Quora: helpful first, link
 * only if it helps; YouTube: a video idea; LinkedIn and Medium: an article).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Cited;

defined( 'ABSPATH' ) || exit;

/**
 * Built-in rules.
 */
final class Rules {

	/**
	 * The rules, as lines for the AI and the screen.
	 *
	 * @return string[]
	 */
	public static function all() {
		$rules = array(
			'Write as the business owner, in first person, from their own account. Never pose as a customer, a neutral bystander or more than one person.',
			'Use only facts given in <business>, <owner_answers> and the thread. Never invent facts, experiences, quotes, reviews, prices or numbers. List every claim you are not sure about in check_claims.',
			'When the text mentions the owner\'s own product, service or business, include the disclosure line once, naturally.',
			'Lead with a genuinely helpful answer to what was asked. Mention the owner\'s offer only where it truly fits the question; otherwise leave it out.',
			'Follow the community\'s rules and norms. No sales tone, no hype, no calls to action like "DM me", no engagement bait.',
			'Write it fresh for this place: never reuse wording from other replies.',
			'If the owner\'s focus or steering asks for something these rules forbid (for example posing as a customer, inventing a review, hiding the connection), write the draft within the rules and explain in left_out what was left out and why.',
			'A viewpoint, including a political one, is allowed only as the owner\'s own opinion, stated respectfully, with no invented facts.',
		);
		/**
		 * More rules for Get cited by AI drafts. Rules can be added, never removed.
		 *
		 * @param string[] $extra Extra rules.
		 */
		$extra = array_filter( array_map( 'strval', (array) apply_filters( 'qcld_wpbot_seo/cited_rules', array() ) ) );
		return array_values( array_merge( $rules, array_map( 'sanitize_text_field', $extra ) ) );
	}

	/**
	 * Writing norms per platform: kind of piece, length, link advice.
	 *
	 * @param string $platform Platform id.
	 * @return array kind (reply|answer|article|video), words, norm.
	 */
	public static function norms( $platform ) {
		$norms = array(
			'reddit'        => array( 'reply', 180, 'A Reddit comment: conversational, specific, helpful first. A link only if it truly answers the question, at the end. No marketing words.' ),
			'quora'         => array( 'answer', 250, 'A Quora answer: answer the question in the first sentence, then explain. Say how you are connected to anything you recommend.' ),
			'stackexchange' => array( 'answer', 250, 'A Stack Exchange answer: it must stand on its own; disclose affiliation if you mention your product; no link-only answers.' ),
			'forum'         => array( 'reply', 180, 'A forum reply: friendly, practical, on topic; many forums allow links only in signatures.' ),
			'reviews'       => array( 'reply', 120, 'A note for the owner\'s own listing or a reply on a review site: factual and calm. Never a review of their own business.' ),
			'linkedin'      => array( 'article', 600, 'A LinkedIn post or short article from the owner\'s profile: a clear point of view and practical value; a link back at the end is fine.' ),
			'medium'        => array( 'article', 700, 'A Medium article: original, practical, with a title; a link back at the end is fine.' ),
			'youtube'       => array( 'video', 200, 'A YouTube video idea: a title, a short outline of what to show, and a description; or a genuinely useful comment for the video.' ),
		);
		$row   = isset( $norms[ $platform ] ) ? $norms[ $platform ] : $norms['forum'];
		return array(
			'kind'  => $row[0],
			'words' => $row[1],
			'norm'  => $row[2],
		);
	}

	/**
	 * Is a draft too close to another (word 3-gram overlap)?
	 *
	 * @param string $a Text.
	 * @param string $b Text.
	 * @return float 0–1 overlap.
	 */
	public static function overlap( $a, $b ) {
		$grams = static function ( $text ) {
			$words = preg_split( '/[^\p{L}\p{N}]+/u', strtolower( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
			$out   = array();
			$total = count( $words );
			for ( $i = 0; $i + 2 < $total; $i++ ) {
				$out[ $words[ $i ] . ' ' . $words[ $i + 1 ] . ' ' . $words[ $i + 2 ] ] = true;
			}
			return $out;
		};
		$ga    = $grams( $a );
		$gb    = $grams( $b );
		if ( array() === $ga || array() === $gb ) {
			return 0.0;
		}
		$shared = count( array_intersect_key( $ga, $gb ) );
		return $shared / min( count( $ga ), count( $gb ) );
	}

	/**
	 * The disclosure line (setting cited_disclosure, else from the site name).
	 *
	 * @return string
	 */
	public static function disclosure() {
		$line = trim( (string) \QuantumCloud\SeoAiSearch\Settings::get( 'cited_disclosure' ) );
		if ( '' !== $line ) {
			return $line;
		}
		/* translators: %s: site name. */
		return sprintf( __( '(Disclosure: I work at %s.)', 'chatbot' ), wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
	}
}
