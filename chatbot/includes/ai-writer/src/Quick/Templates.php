<?php
/**
 * Quick write templates and per-network rules.
 *
 * Each template has two to four fields, the rules the writing must follow
 * and a hard character limit per piece (0 = none). Limits are re-checked
 * after writing, so every variation fits its network.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Quick;

defined( 'ABSPATH' ) || exit;

/**
 * Template registry.
 */
final class Templates {

	/**
	 * All templates, filterable (Social adds networks through qcld_wpbot_writer/channels).
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$about    = array(
			'key'   => 'about',
			'label' => __( 'What is it about?', 'chatbot' ),
			'type'  => 'textarea',
		);
		$audience = array(
			'key'   => 'audience',
			'label' => __( 'Who is it for?', 'chatbot' ),
			'type'  => 'text',
		);
		$link     = array(
			'key'   => 'link',
			'label' => __( 'Link (optional)', 'chatbot' ),
			'type'  => 'text',
		);
		$goal     = array(
			'key'   => 'goal',
			'label' => __( 'What should people do?', 'chatbot' ),
			'type'  => 'text',
		);

		$templates = array(
			'linkedin'        => array(
				'label'  => __( 'LinkedIn post', 'chatbot' ),
				'fields' => array( $about, $audience, $link ),
				'limit'  => 3000,
				'rules'  => 'A strong first line that works on its own before "see more". Short paragraphs with line breaks. One idea. Up to 3 hashtags at the end. Put a link at the end, or say "link in the comments" if none is given.',
			),
			'facebook'        => array(
				'label'  => __( 'Facebook post', 'chatbot' ),
				'fields' => array( $about, $goal, $link ),
				'limit'  => 2000,
				'rules'  => 'Friendly and conversational. The first 125 characters must carry the point. 0 to 2 hashtags. Link at the end.',
			),
			'instagram'       => array(
				'label'  => __( 'Instagram caption or carousel', 'chatbot' ),
				'fields' => array(
					$about,
					array(
						'key'     => 'format',
						'label'   => __( 'Caption or carousel slides?', 'chatbot' ),
						'type'    => 'select',
						'options' => array( 'caption', 'carousel' ),
					),
					$audience,
				),
				'limit'  => 2200,
				'rules'  => 'Hook in the first line. Links do not work in captions: say "link in bio" if needed. 3 to 8 relevant hashtags at the end. For a carousel, write 5 to 8 slides of at most 20 words each, one per line starting "Slide 1:".',
			),
			'x'               => array(
				'label'  => __( 'X post or thread', 'chatbot' ),
				'fields' => array(
					$about,
					array(
						'key'     => 'format',
						'label'   => __( 'One post or a thread?', 'chatbot' ),
						'type'    => 'select',
						'options' => array( 'post', 'thread' ),
					),
					$link,
				),
				'limit'  => 280,
				'split'  => true,
				'rules'  => 'Each post at most 280 characters. For a thread, 3 to 6 posts separated by a blank line, the first one a hook. 0 to 2 hashtags.',
			),
			'google_business' => array(
				'label'  => __( 'Google Business Profile update', 'chatbot' ),
				'fields' => array( $about, $goal ),
				'limit'  => 1500,
				'rules'  => 'Local and practical. The first 100 characters must carry the point. No phone numbers or links in the text (use the button). No hashtags.',
			),
			'email'           => array(
				'label'  => __( 'Email or newsletter blurb', 'chatbot' ),
				'fields' => array( $about, $audience, $goal ),
				'limit'  => 0,
				'rules'  => 'Start with a subject line on its own line ("Subject: ..."), under 50 characters. Then 60 to 150 words, one clear call to action.',
			),
			'ad'              => array(
				'label'  => __( 'Ad copy (Google, Meta)', 'chatbot' ),
				'fields' => array(
					$about,
					array(
						'key'     => 'network',
						'label'   => __( 'Network', 'chatbot' ),
						'type'    => 'select',
						'options' => array( 'google', 'meta' ),
					),
					$goal,
				),
				'limit'  => 0,
				'rules'  => 'Google: 3 headlines of at most 30 characters and 2 descriptions of at most 90, one per line, labelled "Headline:" and "Description:". Meta: primary text under 125 characters, a headline under 40, labelled. No claims that are not in the facts.',
			),
			'product_blurb'   => array(
				'label'  => __( 'Product blurb', 'chatbot' ),
				'fields' => array( $about, $audience ),
				'limit'  => 600,
				'rules'  => '40 to 80 words. Lead with the benefit. Only features given in the facts.',
			),
			'landing'         => array(
				'label'  => __( 'Landing-page section', 'chatbot' ),
				'fields' => array( $about, $audience, $goal ),
				'limit'  => 0,
				'rules'  => 'A heading line, then 60 to 120 words, then a button label on its own line starting "Button:".',
			),
			'press'           => array(
				'label'  => __( 'Press release', 'chatbot' ),
				'fields' => array(
					$about,
					array(
						'key'   => 'where_when',
						'label' => __( 'Where and when?', 'chatbot' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'quote',
						'label' => __( 'A real quote (optional)', 'chatbot' ),
						'type'  => 'textarea',
					),
				),
				'limit'  => 0,
				'rules'  => 'Headline, dateline, then 250 to 400 words in the inverted pyramid. Use only the quote given; if none, write [quote from the owner]. End with "About" and "###".',
			),
			'video_script'    => array(
				'label'  => __( 'Video or Reels script', 'chatbot' ),
				'fields' => array(
					$about,
					array(
						'key'     => 'length',
						'label'   => __( 'Length', 'chatbot' ),
						'type'    => 'select',
						'options' => array( '30 seconds', '60 seconds', '3 minutes' ),
					),
					$audience,
				),
				'limit'  => 0,
				'rules'  => 'Hook in the first 3 seconds. Short spoken lines, with [shot: ...] directions on their own lines. About 2.5 words per second of length.',
			),
			'review_reply'    => array(
				'label'  => __( 'Review reply', 'chatbot' ),
				'fields' => array(
					array(
						'key'   => 'review',
						'label' => __( 'The review', 'chatbot' ),
						'type'  => 'textarea',
					),
					array(
						'key'   => 'notes',
						'label' => __( 'Anything to mention?', 'chatbot' ),
						'type'  => 'text',
					),
				),
				'limit'  => 1000,
				'rules'  => 'Thank them by name if given. Address the specific points. For criticism, no excuses: acknowledge, say what you will do, offer to talk offline. 40 to 100 words. No marketing.',
			),
			'job_post'        => array(
				'label'  => __( 'Job post', 'chatbot' ),
				'fields' => array(
					array(
						'key'   => 'role',
						'label' => __( 'Role', 'chatbot' ),
						'type'  => 'text',
					),
					array(
						'key'   => 'details',
						'label' => __( 'Hours, pay, place, must-haves', 'chatbot' ),
						'type'  => 'textarea',
					),
					$audience,
				),
				'limit'  => 0,
				'rules'  => 'What the job is, what a day looks like, what you need, what you offer, how to apply. Only details given; [placeholder] for missing pay or hours. Inclusive language.',
			),
			'bio'             => array(
				'label'  => __( 'Bio or About text', 'chatbot' ),
				'fields' => array(
					$about,
					array(
						'key'     => 'length',
						'label'   => __( 'Length', 'chatbot' ),
						'type'    => 'select',
						'options' => array( 'one line', 'short paragraph', 'full About page' ),
					),
				),
				'limit'  => 0,
				'rules'  => 'Concrete: who, what, for whom, why it matters. Only facts given.',
			),
		);

		/**
		 * Add or change Quick write templates. Social registers networks here
		 * (or through qcld_wpbot_writer/channels).
		 *
		 * @param array $templates id => label, fields, limit, rules, split.
		 */
		$templates = (array) apply_filters( 'qcld_wpbot_writer/quick_templates', $templates );

		foreach ( $templates as $id => $template ) {
			$templates[ $id ] = wp_parse_args(
				(array) $template,
				array(
					'label'  => $id,
					'fields' => array(),
					'limit'  => 0,
					'split'  => false,
					'rules'  => '',
				)
			);
		}
		return $templates;
	}

	/**
	 * Public view for the screen (no prompt rules).
	 *
	 * @return array[]
	 */
	public static function public_view() {
		$out = array();
		foreach ( self::all() as $id => $template ) {
			$out[] = array(
				'id'     => (string) $id,
				'label'  => (string) $template['label'],
				'fields' => array_values( (array) $template['fields'] ),
				'limit'  => (int) $template['limit'],
			);
		}
		return $out;
	}
}
