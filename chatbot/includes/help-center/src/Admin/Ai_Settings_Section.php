<?php
/**
 * The Help Center's switch on AI / Settings (owner, 19 November 2026: the Help Center is not a
 * chatbot module; it is switched on here). Hooked while the Help Center is off too: this
 * section and the switch watcher are the only things that run then.
 *
 * Needs the chatbot module's qcld_wpbot_chat/ai_settings_sections filter (wpbot-chatbot 0.25.0).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Feature_Switch;
use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * AI / Settings section.
 */
final class Ai_Settings_Section {

	/**
	 * Section id.
	 */
	const ID = 'help-center';

	/**
	 * Posted field.
	 */
	const FIELD = 'help_center_enabled';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/ai_settings_sections', array( self::class, 'section' ) );
		add_action( 'qcld_wpbot_chat/ai_settings_section', array( self::class, 'render' ) );
		add_action( 'qcld_wpbot_chat/ai_settings_save', array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * Add the section.
	 *
	 * @param array $sections id => [ title, dashicon, lead ].
	 * @return array
	 */
	public static function section( $sections ) {
		$sections             = is_array( $sections ) ? $sections : array();
		$sections[ self::ID ] = array(
			__( 'Help Center', 'chatbot' ),
			'dashicons-sos',
			__( 'Help articles, documentation and knowledge bases on your site. The chatbot answers from them, with links to the exact section.', 'chatbot' ),
		);
		return $sections;
	}

	/**
	 * Print the section's fields (inside the AI Settings form).
	 *
	 * @param string $section Section id.
	 * @return void
	 */
	public static function render( $section ) {
		if ( self::ID !== $section ) {
			return;
		}
		$on = Feature_Switch::is_on();
		self::render_about();
		echo '<div class="qcwp-card"><h2>' . esc_html__( 'Help Center', 'chatbot' ) . '</h2>';
		echo '<label class="qcwp-check"><input type="checkbox" name="' . esc_attr( self::FIELD ) . '" value="1"' . checked( $on, true, false ) . '><span><strong>' . esc_html__( 'Turn on the Help Center', 'chatbot' ) . '</strong><small>' . esc_html__( 'Adds Help Center to the WPBot menu and publishes your help articles. Off by default. Turning it off hides the Help Center and keeps your articles.', 'chatbot' ) . '</small></span></label>';
		if ( $on ) {
			$address = home_url( '/' . Settings::base() . '/' );
			echo '<p>' . sprintf(
				/* translators: 1: link to the Help Center admin, 2: link to the public Help Center. */
				esc_html__( 'Write and organise articles in %1$s. Visitors find them at %2$s.', 'chatbot' ),
				'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . Post_Type::NAME ) ) . '">' . esc_html__( 'WPBot → Help Center', 'chatbot' ) . '</a>',
				'<a href="' . esc_url( $address ) . '">' . esc_html( $address ) . '</a>'
			) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * What the Help Center does and how it works with the rest of WPBot.
	 *
	 * @return array<int,array{title:string,icon:string,points:string[]}>
	 */
	public static function about() {
		return array(
			array(
				'title'  => __( 'Why turn it on', 'chatbot' ),
				'icon'   => 'dashicons-lightbulb',
				'points' => array(
					__( 'Customers find answers themselves, any time, so fewer people email or chat with the same questions.', 'chatbot' ),
					__( 'The chatbot answers from your own articles, so its answers are accurate and specific to your products, with a link to read more.', 'chatbot' ),
					__( 'Help articles bring visitors from Google and from AI search, and show you which questions people still ask.', 'chatbot' ),
				),
			),
			array(
				'title'  => __( 'For your customers', 'chatbot' ),
				'icon'   => 'dashicons-groups',
				'points' => array(
					__( 'A help center on your site with knowledge bases, topics and articles, in your theme\'s fonts.', 'chatbot' ),
					__( 'Instant search that understands typos and synonyms.', 'chatbot' ),
					__( 'Clear articles with steps, tabs, callouts, screenshots, a table of contents and a glossary.', 'chatbot' ),
					__( '"Was this helpful?" on every article, and "Still need help?" opens the chatbot.', 'chatbot' ),
				),
			),
			array(
				'title'  => __( 'With the ChatBot', 'chatbot' ),
				'icon'   => 'dashicons-format-chat',
				'points' => array(
					__( 'Every published article is added to the chatbot\'s knowledge base, heading by heading.', 'chatbot' ),
					__( 'Answers link to the exact section of the article they came from.', 'chatbot' ),
					__( 'A question asked on an article page is answered with that article in mind.', 'chatbot' ),
					__( 'Questions the chatbot or the search could not answer, and "not helpful" votes, go to Train → Knowledge gaps, where one click starts the missing article.', 'chatbot' ),
					__( 'Articles for signed-in customers are never read out to visitors who may not see them.', 'chatbot' ),
				),
			),
			array(
				'title'  => __( 'With AI Writer', 'chatbot' ),
				'icon'   => 'dashicons-edit-large',
				'points' => array(
					__( 'Write article from a knowledge gap opens a draft with the customer\'s question and a link to AI Writer with that question as the topic.', 'chatbot' ),
					__( 'Get started makes starter categories and draft articles from what your site already has (questions the chatbot could not answer, FAQ, shipping and returns pages). Each draft opens with a link to AI Writer.', 'chatbot' ),
				),
			),
			array(
				'title'  => __( 'With SEO & AI Search', 'chatbot' ),
				'icon'   => 'dashicons-search',
				'points' => array(
					__( 'Structured data for every article (article and breadcrumbs, how-to steps, FAQs), so search results can show rich results.', 'chatbot' ),
					__( 'A Markdown copy of every article and an llms.txt list, so AI assistants such as ChatGPT and Claude read your docs correctly.', 'chatbot' ),
					__( 'If you change the Help Center address, old links redirect to the new ones.', 'chatbot' ),
				),
			),
		);
	}

	/**
	 * Print the explanation cards.
	 *
	 * @return void
	 */
	private static function render_about() {
		echo '<div class="qcwp-card wpbot-hc-about"><h2>' . esc_html__( 'How the Help Center helps', 'chatbot' ) . '</h2><div class="wpbot-hc-about__grid">';
		foreach ( self::about() as $block ) {
			echo '<section class="wpbot-hc-about__item"><h3><span class="dashicons ' . esc_attr( $block['icon'] ) . '" aria-hidden="true"></span> ' . esc_html( $block['title'] ) . '</h3><ul>';
			foreach ( $block['points'] as $point ) {
				echo '<li>' . esc_html( $point ) . '</li>';
			}
			echo '</ul></section>';
		}
		echo '</div></div>';
		echo '<style>.wpbot-hc-about__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}.wpbot-hc-about__item h3{display:flex;align-items:center;gap:6px;margin:0 0 6px;font-size:14px}.wpbot-hc-about__item ul{margin:0;padding-left:18px;list-style:disc}.wpbot-hc-about__item li{margin:0 0 4px}</style>';
	}

	/**
	 * Save the switch. The AI Settings page already checked the user (manage_options) and the nonce.
	 *
	 * @param string $section Section id.
	 * @param array  $post    Posted values (unslashed).
	 * @return void
	 */
	public static function save( $section, $post ) {
		if ( self::ID !== $section || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		Feature_Switch::set( ! empty( $post[ self::FIELD ] ) );
	}
}
