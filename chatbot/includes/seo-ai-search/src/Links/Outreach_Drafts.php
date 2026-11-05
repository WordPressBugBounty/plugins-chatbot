<?php
/**
 * AI drafts for outreach in the brand voice, with a "Make it yours"
 * question the owner answers so the message carries real experience.
 * No personal data goes to the AI: the contact's name and email are added
 * after generation, and notes are redacted.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\Privacy\Redactor;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Outreach drafts.
 */
final class Outreach_Drafts {

	/**
	 * Placeholder the AI uses for the contact's name.
	 */
	const NAME = '[Name]';

	/**
	 * Draft (or redraft) the message for a contact.
	 *
	 * @param int   $id   Outreach id.
	 * @param array $args answer (to the "Make it yours" question), idea (index of a chosen idea).
	 * @return array|WP_Error The contact with its draft.
	 */
	public static function draft( $id, array $args = array() ) {
		$item = Outreach::get( $id );
		if ( ! $item ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This contact was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$previous = is_array( $item['draft'] ) ? $item['draft'] : array();
		$answer   = isset( $args['answer'] ) ? sanitize_textarea_field( (string) $args['answer'] ) : ( isset( $previous['answer'] ) ? $previous['answer'] : '' );
		$idea     = isset( $args['idea'] ) && isset( $previous['ideas'][ (int) $args['idea'] ] ) ? $previous['ideas'][ (int) $args['idea'] ] : null;
		$response = AI_Service::generate(
			Module::ID,
			'outreach_draft',
			Request::make(
				self::system(),
				self::prompt( $item, $answer, $idea ),
				array(
					'max_tokens' => 900,
					'json'       => true,
					'mode'       => 'creative',
				)
			),
			array( 'cache' => false )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$json = $response->json();
		if ( ! is_array( $json ) || empty( $json['body'] ) ) {
			return new WP_Error( 'qcld_wpbot_seo_ai_empty', __( 'The AI did not return a message. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$draft           = Outreach::clean_draft( $json );
		$draft['answer'] = $answer;
		if ( null !== $idea ) {
			$draft['ideas']  = $previous['ideas'];
			$draft['chosen'] = (int) $args['idea'];
		}
		$draft['body'] = self::finish( $draft['body'], $item );
		Outreach::update( $id, array( 'draft' => $draft ) );
		Outreach::log( $id, 'draft', '', '', $draft['subject'] );
		return Outreach::get( $id );
	}

	/**
	 * Put the contact's name in and add a signature that identifies the sender.
	 *
	 * @param string $body Body from the AI.
	 * @param array  $item Contact.
	 * @return string
	 */
	public static function finish( $body, array $item ) {
		$name = '' !== $item['contact_name'] ? $item['contact_name'] : __( 'there', 'chatbot' );
		$body = str_replace( array( self::NAME, '[name]' ), $name, (string) $body );
		$user = wp_get_current_user();
		$sign = trim( ( $user && $user->exists() ? $user->display_name . "\n" : '' ) . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . "\n" . home_url( '/' ) );
		if ( false === strpos( $body, home_url() ) ) {
			$body = rtrim( $body ) . "\n\n" . $sign;
		}
		return $body;
	}

	/**
	 * System instructions.
	 *
	 * @return string
	 */
	private static function system() {
		return 'You help a small business owner write personal outreach messages to other websites. Honest, short, friendly and specific; never pushy; no flattery, no buzzwords; never offer money or a link swap; never invent facts about the business. Write in the same language as the site. Use ' . self::NAME . ' where the recipient\'s name goes. Do not add a signature.';
	}

	/**
	 * Prompt for one contact.
	 *
	 * @param array      $item   Contact.
	 * @param string     $answer Owner's answer to the question.
	 * @param array|null $idea   Chosen idea.
	 * @return string
	 */
	private static function prompt( array $item, $answer, $idea ) {
		$voice  = self::voice();
		$target = '' !== $item['target_url'] ? $item['target_url'] : home_url( '/' );
		$title  = $item['target_post_id'] ? get_the_title( $item['target_post_id'] ) : '';
		$lines  = array(
			'Task: outreach_draft',
			'Type: ' . $item['type'],
			'Our site: ' . get_bloginfo( 'name' ) . ' (' . home_url( '/' ) . ')',
			'What we do: ' . get_bloginfo( 'description' ),
			'Their site: ' . ( '' !== $item['name'] ? $item['name'] . ' ' : '' ) . $item['website'],
			'Our page to point to: ' . ( '' !== $title ? $title . ' ' : '' ) . $target,
		);
		if ( '' !== $voice ) {
			$lines[] = 'Brand voice: ' . $voice;
		}
		if ( '' !== $item['notes'] ) {
			$lines[] = 'Why they fit (owner notes): ' . Redactor::redact( self::cut( $item['notes'], 600 ) );
		}
		if ( '' !== $answer ) {
			$lines[] = 'Owner\'s own words to use (real experience): ' . Redactor::redact( self::cut( $answer, 600 ) );
		}
		if ( 'guest_post' === $item['type'] ) {
			$lines[] = 'Our recent articles (ideas must differ from these): ' . implode( '; ', self::recent_titles() );
			$lines[] = $idea ? 'Pitch this idea: ' . $idea['title'] . ' - ' . $idea['angle'] : 'Suggest 2 or 3 article ideas that fit their publication and differ from our articles, then pitch the best one.';
			$lines[] = 'The pitch offers an original article written for them, following their guidelines, with at most one or two links using our brand name or page title.';
		} elseif ( 'collaboration' === $item['type'] ) {
			$lines[] = $idea ? 'Propose this idea: ' . $idea['title'] . ' - ' . $idea['angle'] : 'Suggest 2 or 3 collaboration ideas that suit both sides (co-written guide, joint offer or bundle, event or webinar, case study or testimonial, expert quote, local resource page, podcast or newsletter feature); for each, say where a link would come naturally. Then propose the best one.';
		} else {
			$lines[] = 'Ask, briefly and politely, to add or fix a link to our page because it helps their readers; say why it fits.';
		}
		$lines[] = 'Return JSON {"question": "one short question to ask the owner so the message carries real experience", "ideas": [{"title": "...", "angle": "...", "link": "where a link fits"}], "subject": "...", "body": "..."}. Body under 160 words. Ideas only for guest posts and collaborations.';
		return implode( "\n", $lines );
	}

	/**
	 * Brand voice summary from AI Writer's site voice, when it exists.
	 *
	 * @return string
	 */
	private static function voice() {
		if ( ! class_exists( '\QuantumCloud\AiWriter\Voice\Voices' ) || ! method_exists( '\QuantumCloud\AiWriter\Voice\Voices', 'site_voice' ) ) {
			return '';
		}
		$voice = \QuantumCloud\AiWriter\Voice\Voices::site_voice();
		if ( ! is_array( $voice ) || empty( $voice['profile'] ) || ! is_array( $voice['profile'] ) ) {
			return '';
		}
		$profile = $voice['profile'];
		$parts   = array();
		foreach ( array( 'summary', 'point_of_view', 'reading_level' ) as $key ) {
			if ( ! empty( $profile[ $key ] ) && is_string( $profile[ $key ] ) ) {
				$parts[] = $profile[ $key ];
			}
		}
		if ( ! empty( $profile['avoid_words'] ) && is_array( $profile['avoid_words'] ) ) {
			$parts[] = 'Avoid: ' . implode( ', ', array_slice( array_map( 'strval', $profile['avoid_words'] ), 0, 10 ) );
		}
		return self::cut( implode( ' ', $parts ), 500 );
	}

	/**
	 * Recent post titles.
	 *
	 * @return string[]
	 */
	private static function recent_titles() {
		return array_map(
			'get_the_title',
			get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 20,
					'fields'         => 'ids',
				)
			)
		);
	}

	/**
	 * Cut text.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function cut( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, $max ) : substr( (string) $text, 0, $max );
	}
}
