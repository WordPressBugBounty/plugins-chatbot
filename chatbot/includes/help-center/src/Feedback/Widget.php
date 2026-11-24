<?php
/**
 * "Was this article helpful?" under each article (HC1-10).
 *
 * Yes thanks the visitor. No asks why (a choice and optional words), then offers
 * the assistant and the contact address (plan 5.1: "No" offers Ask AI or a
 * ticket; Pro adds the ticket through qcld_wpbot_help_center/feedback_actions).
 * The script shows the box; without JavaScript there is nothing to send.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Feedback;

use QuantumCloud\HelpCenter\Access;
use QuantumCloud\HelpCenter\Front\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Feedback box.
 */
final class Widget {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'qcld_wpbot_help_center/after_article', array( self::class, 'render' ) );
	}

	/**
	 * Print the box.
	 *
	 * @param \WP_Post $post Article.
	 * @return void
	 */
	public static function render( $post ) {
		echo self::html_if_allowed( $post, Front::design()['contact_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in html().
	}

	/**
	 * The box for a published article the visitor may read, else ''.
	 *
	 * @param \WP_Post|null $post        Article.
	 * @param string        $contact_url Contact address or ''.
	 * @return string
	 */
	public static function html_if_allowed( $post, $contact_url ) {
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || ! Access::can_read( $post ) ) {
			return '';
		}
		return self::html( $post, $contact_url );
	}

	/**
	 * Markup of the box.
	 *
	 * @param \WP_Post $post        Article.
	 * @param string   $contact_url Contact address or ''.
	 * @return string
	 */
	public static function html( $post, $contact_url ) {
		$id   = 'wpbot-hc-fb-' . (int) $post->ID;
		$out  = '<section class="wpbot-hc__feedback" data-feedback="' . (int) $post->ID . '" aria-labelledby="' . esc_attr( $id ) . '" hidden>';
		$out .= '<div class="wpbot-hc__fb-ask"><h2 id="' . esc_attr( $id ) . '" class="wpbot-hc__fb-title">' . esc_html__( 'Was this article helpful?', 'chatbot' ) . '</h2>';
		$out .= '<div class="wpbot-hc__fb-buttons">';
		$out .= '<button type="button" class="wpbot-hc__btn is-outline" data-vote="yes" aria-pressed="false">' . esc_html__( 'Yes', 'chatbot' ) . '</button>';
		$out .= '<button type="button" class="wpbot-hc__btn is-outline" data-vote="no" aria-pressed="false">' . esc_html__( 'No', 'chatbot' ) . '</button>';
		$out .= '</div></div>';

		$out .= '<form class="wpbot-hc__fb-why" hidden><fieldset><legend>' . esc_html__( 'What went wrong?', 'chatbot' ) . '</legend>';
		foreach ( Votes::reasons() as $key => $label ) {
			$out .= '<label class="wpbot-hc__fb-choice"><input type="radio" name="reason" value="' . esc_attr( $key ) . '"> ' . esc_html( $label ) . '</label>';
		}
		$out .= '</fieldset>';
		$out .= '<label class="wpbot-hc__fb-label" for="' . esc_attr( $id ) . '-c">' . esc_html__( 'What were you looking for? (optional)', 'chatbot' ) . '</label>';
		$out .= '<textarea id="' . esc_attr( $id ) . '-c" name="comment" rows="2" maxlength="' . (int) Votes::COMMENT_MAX . '" aria-describedby="' . esc_attr( $id ) . '-n"></textarea>';
		$out .= '<p class="wpbot-hc__meta" id="' . esc_attr( $id ) . '-n">' . esc_html__( 'Please don’t include personal details.', 'chatbot' ) . '</p>';
		$out .= '<button type="submit" class="wpbot-hc__btn is-solid">' . esc_html__( 'Send', 'chatbot' ) . '</button></form>';

		$out .= '<p class="wpbot-hc__fb-status" role="status" aria-live="polite"></p>';

		$actions   = array();
		$actions[] = '<button type="button" class="wpbot-hc__btn is-solid" data-ask data-needs-chat hidden>' . esc_html__( 'Ask the assistant', 'chatbot' ) . '</button>';
		if ( '' !== $contact_url ) {
			$actions[] = '<a class="wpbot-hc__btn is-outline" href="' . esc_url( $contact_url ) . '">' . esc_html__( 'Contact us', 'chatbot' ) . '</a>';
		}
		/**
		 * Ways to get help after a "no" vote, as HTML (Pro adds a ticket form).
		 *
		 * @param string[] $actions Buttons and links.
		 * @param \WP_Post $post    Article.
		 */
		$actions = (array) apply_filters( 'qcld_wpbot_help_center/feedback_actions', $actions, $post );
		$out    .= '<div class="wpbot-hc__fb-next" hidden><p>' . esc_html__( 'Sorry about that. Get help another way:', 'chatbot' ) . '</p><div class="wpbot-hc__fb-buttons">' . implode( '', $actions ) . '</div></div>';
		return $out . '</section>';
	}
}
