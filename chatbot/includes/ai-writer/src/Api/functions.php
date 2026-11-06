<?php
/**
 * AI Writer public API for other modules (Social, AI Marketing, AI Studio,
 * SEO & AI Search, Growth Dashboard). The contract is documented in
 * README.md and in the feature plan, section 9.1.
 *
 * @package QuantumCloud\AiWriter
 */

defined( 'ABSPATH' ) || exit;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\WPBotCore\AI\Key_Health;
use QuantumCloud\WPBotCore\AI\Usage;
use QuantumCloud\WPBotCore\Library\Packages;
use QuantumCloud\WPBotCore\Settings as Core_Settings;

if ( ! function_exists( 'qcld_wpbot_writer_get_package' ) ) {
	/**
	 * The content package of a finished piece.
	 *
	 * @param int $post_id Post id.
	 * @return array|null Null when the post has no package.
	 */
	function qcld_wpbot_writer_get_package( $post_id ) {
		$package = Packages::get( (int) $post_id );
		if ( null === $package ) {
			return null;
		}
		return array(
			'post_id'          => (int) $package['post_id'],
			'title'            => $package['title'],
			'url'              => (string) get_permalink( (int) $post_id ),
			'summary'          => $package['summary'],
			'key_points'       => is_array( $package['key_points'] ) ? $package['key_points'] : array(),
			'audience'         => (string) $package['audience'],
			'voice'            => is_array( $package['voice'] ) ? $package['voice'] : array(),
			'facts_used'       => is_array( $package['facts_used'] ) ? $package['facts_used'] : array(),
			'images'           => is_array( $package['images'] ) ? $package['images'] : array(),
			'product'          => is_array( $package['product'] ) ? $package['product'] : null,
			'channel_versions' => $package['channel_versions'],
			'finalized_at'     => $package['finalized_at'],
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_writer_tracked_url' ) ) {
	/**
	 * A link back to the site with UTM tags (Site Insight stores UTM per
	 * visit). Filter: qcld_wpbot_writer/utm_args.
	 *
	 * @param string $url  URL.
	 * @param array  $args source, medium, campaign, content.
	 * @return string
	 */
	function qcld_wpbot_writer_tracked_url( $url, array $args ) {
		$utm = array(
			'utm_source'   => isset( $args['source'] ) ? sanitize_key( $args['source'] ) : 'ai_writer',
			'utm_medium'   => isset( $args['medium'] ) ? sanitize_key( $args['medium'] ) : 'social',
			'utm_campaign' => isset( $args['campaign'] ) ? sanitize_title( $args['campaign'] ) : '',
			'utm_content'  => isset( $args['content'] ) ? sanitize_key( $args['content'] ) : 'ai_writer',
		);
		/**
		 * UTM parameters on tracked links.
		 *
		 * @param array  $utm  utm_* => value.
		 * @param string $url  URL.
		 * @param array  $args Arguments.
		 */
		$utm = array_filter( (array) apply_filters( 'qcld_wpbot_writer/utm_args', $utm, $url, $args ), 'strlen' );
		return (string) add_query_arg( array_map( 'rawurlencode', $utm ), $url );
	}
}

if ( ! function_exists( 'qcld_wpbot_writer_open_brief_url' ) ) {
	/**
	 * URL that opens AI Writer with a pre-filled brief or Improve task.
	 *
	 * The brief is stored for one hour under a random token, so long briefs
	 * never travel in URLs.
	 *
	 * @param array $brief mode (new|improve|answer|refresh), post_id, topic, type, notes, fix, source_module, main_query,
	 *                     supporting_queries (up to 10; each a string, or array( query, volume (int|null),
	 *                     volume_range ([min, max]|null), source )), numbers (label => value, up to 8), map_topic,
	 *                     idea_id; since AI Writer 0.15 also intent (informational|commercial|transactional|
	 *                     navigational|local), questions (up to 10 strings), link_to and link_from (up to 5
	 *                     post ids each), vault_id; since 0.27 format (an article format id). All optional.
	 * @return string Admin URL.
	 */
	function qcld_wpbot_writer_open_brief_url( array $brief ) {
		$modes = array( 'new', 'improve', 'answer', 'refresh' );
		$clean = array(
			'mode'               => isset( $brief['mode'] ) && in_array( $brief['mode'], $modes, true ) ? $brief['mode'] : 'new',
			'post_id'            => isset( $brief['post_id'] ) ? absint( $brief['post_id'] ) : 0,
			'topic'              => isset( $brief['topic'] ) ? sanitize_text_field( $brief['topic'] ) : '',
			'type'               => isset( $brief['type'] ) ? sanitize_key( $brief['type'] ) : '',
			'notes'              => isset( $brief['notes'] ) ? sanitize_textarea_field( $brief['notes'] ) : '',
			'fix'                => isset( $brief['fix'] ) ? sanitize_textarea_field( $brief['fix'] ) : '',
			'source_module'      => isset( $brief['source_module'] ) ? sanitize_key( $brief['source_module'] ) : '',
			// Shared keyword and topic map (brief 7.10).
			'main_query'         => isset( $brief['main_query'] ) ? sanitize_text_field( $brief['main_query'] ) : '',
			'map_topic'          => isset( $brief['map_topic'] ) ? sanitize_text_field( $brief['map_topic'] ) : '',
			'idea_id'            => isset( $brief['idea_id'] ) ? absint( $brief['idea_id'] ) : 0,
			// From SEO & AI Search: supporting searches (with their numbers) and the opportunity's numbers (label => value).
			'supporting_queries' => array(),
			'keyword_numbers'    => array(),
			'numbers'            => array(),
			// Round 2 (CR-6 W1): intent, questions to answer, pages to link to and from.
			'intent'             => isset( $brief['intent'] ) ? \QuantumCloud\AiWriter\Writing\Brief::intent( $brief['intent'] ) : '',
			'questions'          => \QuantumCloud\AiWriter\Writing\Brief::questions( isset( $brief['questions'] ) ? $brief['questions'] : array() ),
			'link_to'            => \QuantumCloud\AiWriter\Writing\Brief::post_ids( isset( $brief['link_to'] ) ? $brief['link_to'] : array() ),
			'link_from'          => \QuantumCloud\AiWriter\Writing\Brief::post_ids( isset( $brief['link_from'] ) ? $brief['link_from'] : array() ),
			'vault_id'           => isset( $brief['vault_id'] ) ? absint( $brief['vault_id'] ) : 0,
			// CR-16: the article format (AI Writer 0.27).
			'format'             => isset( $brief['format'] ) ? \QuantumCloud\AiWriter\Formats\Formats::sanitise( $brief['format'] ) : '',
			// Round 3: persuasion choices for the piece (empty: detected when the brief opens).
			'persuasion'         => \QuantumCloud\AiWriter\Persuasion\Choices::sanitise( isset( $brief['persuasion'] ) ? $brief['persuasion'] : null ),
		);
		$supporting = \QuantumCloud\AiWriter\Writing\Brief::supporting( isset( $brief['supporting_queries'] ) ? (array) $brief['supporting_queries'] : array() );

		$clean['supporting_queries'] = $supporting['queries'];
		$clean['keyword_numbers']    = $supporting['numbers'];
		foreach ( isset( $brief['numbers'] ) ? array_slice( (array) $brief['numbers'], 0, 8, true ) : array() as $label => $value ) {
			$clean['numbers'][ substr( sanitize_text_field( (string) $label ), 0, 60 ) ] = substr( sanitize_text_field( (string) $value ), 0, 60 );
		}
		if ( 'refresh' === $clean['mode'] && '' === $clean['fix'] ) {
			$clean['fix'] = __( 'Refresh the facts, numbers, dates and examples so the page is current. Keep what still holds.', 'chatbot' );
		}
		$token = strtolower( wp_generate_password( 20, false, false ) );
		set_transient( 'qcld_wpbot_writer_brief_' . $token, $clean, HOUR_IN_SECONDS );
		return add_query_arg(
			array(
				'page'  => 'wpbot-ai-writer',
				'brief' => $token,
			),
			admin_url( 'admin.php' )
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_writer_get_dashboard_data' ) ) {
	/**
	 * Numbers for the Growth Dashboard.
	 *
	 * @param array $args Reserved for filters (period).
	 * @return array
	 */
	function qcld_wpbot_writer_get_dashboard_data( array $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Part of the public contract; periods arrive later.
		$health = Key_Health::notices( Module::ID );
		return array(
			'published'      => array(
				'count' => 0,
				'list'  => array(),
			),
			'top_performers' => array(),
			'scheduled_next' => array(),
			'usage'          => array(
				'month_cost' => round( Usage::month_cost( Module::ID ), 4 ),
				'budget'     => Core_Settings::budget( Module::ID ),
			),
			'key_health'     => array(
				'status' => array() === $health ? 'ok' : 'failing',
				'cause'  => array() === $health ? '' : $health[0]['cause'],
				'fix'    => array() === $health ? '' : $health[0]['fix'],
			),
		);
	}
}
