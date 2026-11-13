<?php
/**
 * How each network's version is written: technique, length, link and extra
 * rules (Settings › Writing, screen 22). Defaults follow each network's habits.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Writing;

use QuantumCloud\Social\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Per-network writing rules.
 */
final class Network_Rules {

	/**
	 * Techniques the owner can pick per network.
	 *
	 * @return array<string,string> id => label.
	 */
	public static function techniques() {
		return array(
			'fits'      => __( 'What fits', 'chatbot' ),
			'curiosity' => __( 'Curiosity', 'chatbot' ),
			'insight'   => __( 'Insight-led', 'chatbot' ),
			'answer'    => __( 'Answer first', 'chatbot' ),
			'story'     => __( 'Short story', 'chatbot' ),
			'question'  => __( 'Ends with a question', 'chatbot' ),
			'offer'     => __( 'Offer first', 'chatbot' ),
		);
	}

	/**
	 * Prompt text for a technique.
	 *
	 * @param string $technique Technique id.
	 * @return string
	 */
	public static function technique_prompt( $technique ) {
		$prompts = array(
			'fits'      => 'Use whatever approach suits this network and this message best.',
			'curiosity' => 'Open with a line that makes people curious, then pay it off.',
			'insight'   => 'Lead with one concrete insight or behind-the-scenes fact from the source.',
			'answer'    => 'Answer the reader\'s question in the first line, then add detail.',
			'story'     => 'Tell it as a two- or three-sentence story with a clear point.',
			'question'  => 'End with one simple question that invites replies.',
			'offer'     => 'Lead with the offer and what the reader gets, then the details.',
		);
		return isset( $prompts[ $technique ] ) ? $prompts[ $technique ] : $prompts['fits'];
	}

	/**
	 * Default rules per network. The Master module adds its networks through
	 * the qcld_wpbot_social/writing_rules filter.
	 *
	 * @return array<string,array{label:string, technique:string, length:string, link:string, extra:string, rules:string, manual:bool}>
	 */
	public static function defaults() {
		$rules = array(
			'facebook'        => array(
				'label'     => __( 'Facebook', 'chatbot' ),
				'technique' => 'fits',
				'length'    => __( 'Short', 'chatbot' ),
				'link'      => __( 'In the post', 'chatbot' ),
				'extra'     => '',
				'rules'     => 'Friendly and conversational. The first 125 characters carry the point. 0 to 2 hashtags. Under 80 words.',
				'manual'    => false,
			),
			'instagram'       => array(
				'label'     => __( 'Instagram', 'chatbot' ),
				'technique' => 'curiosity',
				'length'    => __( 'Medium', 'chatbot' ),
				'link'      => __( '"Link in bio"', 'chatbot' ),
				'extra'     => '',
				'rules'     => 'A hook in the first line, short paragraphs, hashtags at the end. Never paste a link: say "link in bio".',
				'manual'    => false,
			),
			'threads'         => array(
				'label'     => __( 'Threads', 'chatbot' ),
				'technique' => 'question',
				'length'    => __( 'Short', 'chatbot' ),
				'link'      => __( 'At the end', 'chatbot' ),
				'extra'     => '',
				'rules'     => 'Casual, one idea, under 500 characters including the link. At most one hashtag.',
				'manual'    => false,
			),
			'telegram'        => array(
				'label'     => __( 'Telegram', 'chatbot' ),
				'technique' => 'fits',
				'length'    => __( 'Short', 'chatbot' ),
				'link'      => __( 'At the end', 'chatbot' ),
				'extra'     => '',
				'rules'     => 'A channel update: a bold-feeling first line (plain text, no markdown), two or three short lines, then the link. No hashtags.',
				'manual'    => false,
			),
			'google_business' => array(
				'label'     => __( 'Google', 'chatbot' ),
				'technique' => 'fits',
				'length'    => __( 'Short', 'chatbot' ),
				'link'      => __( '"Learn more" button', 'chatbot' ),
				'extra'     => __( 'No hashtags', 'chatbot' ),
				'rules'     => 'A short local update: what is new and why it matters to nearby customers, then a call to action. No hashtags, no phone numbers, no links in the text.',
				'manual'    => false,
			),
			'whatsapp'        => array(
				'label'     => __( 'WhatsApp', 'chatbot' ),
				'technique' => 'fits',
				'length'    => __( 'Short', 'chatbot' ),
				'link'      => __( 'At the end', 'chatbot' ),
				'extra'     => __( 'Post yourself', 'chatbot' ),
				'rules'     => 'A message for a WhatsApp Status or Channel: one or two short lines, one emoji at most, then the link. No hashtags.',
				'manual'    => true,
			),
		);
		/**
		 * Writing rules per network (the Master module adds its networks).
		 *
		 * @param array $rules network => label, technique, length, link, extra, rules, manual.
		 */
		return (array) apply_filters( 'qcld_wpbot_social/writing_rules', $rules );
	}

	/**
	 * Rules for one network with the owner's choices applied.
	 *
	 * @param string $network Network id.
	 * @return array|null
	 */
	public static function get( $network ) {
		$defaults = self::defaults();
		if ( ! isset( $defaults[ $network ] ) ) {
			return null;
		}
		$rule   = $defaults[ $network ];
		$chosen = Writing_Settings::get( 'networks' );
		if ( isset( $chosen[ $network ]['technique'] ) && isset( self::techniques()[ $chosen[ $network ]['technique'] ] ) ) {
			$rule['technique'] = $chosen[ $network ]['technique'];
		}
		$net          = Module::instance()->network( $network );
		$limits       = $net ? $net->limits() : array();
		$rule['max']  = isset( $limits['max_text'] ) ? (int) $limits['max_text'] : self::manual_max( $network );
		$rule['id']   = $network;
		$rule['link'] = (string) $rule['link'];
		return $rule;
	}

	/**
	 * Length limits for networks WPBot cannot post to (Post yourself).
	 *
	 * @param string $network Network id.
	 * @return int
	 */
	private static function manual_max( $network ) {
		$max = array( 'whatsapp' => 700 );
		/**
		 * Text limits for Post yourself networks.
		 *
		 * @param array $max network => characters.
		 */
		$max = (array) apply_filters( 'qcld_wpbot_social/manual_limits', $max );
		return isset( $max[ $network ] ) ? (int) $max[ $network ] : 2000;
	}
}
