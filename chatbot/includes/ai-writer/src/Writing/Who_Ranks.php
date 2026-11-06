<?php
/**
 * "Who ranks for this search?" (CR-8, Free since AI Writer 0.15).
 *
 * An AI model with web search lists the pages that rank best for a search:
 * title, URL, site and a short note on what each offers. It uses the site's
 * own AI key (a provider that can search the web) and runs only on the
 * owner's click. Nothing from the pages is stored; the sites seen are
 * remembered as competitor suggestions. AI Writer Pro adds "Analyse these
 * pages" on top of the list.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Topics\Competitor_Feeds;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Who ranks.
 */
final class Who_Ranks {

	/**
	 * Whether a turned-on provider can search the web.
	 *
	 * @return bool
	 */
	public static function available() {
		foreach ( Keys::call_order( Module::ID ) as $provider ) {
			$definition = Providers::get( $provider );
			if ( $definition && ! empty( $definition['web_search'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Look up who ranks for a search.
	 *
	 * @param string $query Search.
	 * @return array|WP_Error query, results[title, url, site, note], sources, cost.
	 */
	public static function lookup( $query ) {
		$query = substr( sanitize_text_field( (string) $query ), 0, 191 );
		if ( '' === $query ) {
			return new WP_Error( 'qcld_wpbot_writer_no_query', __( 'Type a search first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! self::available() ) {
			return new WP_Error( 'qcld_wpbot_writer_no_web_search', __( 'Who ranks needs OpenAI, Anthropic Claude, Google Gemini or OpenRouter turned on in AI settings (models with web search).', 'chatbot' ), array( 'status' => 400 ) );
		}
		$system = "Task: who_ranks\nSearch the web for the query and list the pages that rank best for it (up to 8), in order. For each: title, URL, the site's name and one short note on what the page offers (no quotes from it).\n"
			. Prompts::language() . "\nAnswer with one JSON object: {\"results\":[{\"title\":\"\",\"url\":\"\",\"site\":\"\",\"note\":\"\"}]}.";
		$result = Writer_AI::ask_json(
			'who_ranks',
			$system,
			'Query: ' . $query,
			array(
				'web_search' => true,
				'max_tokens' => 1200,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$results = array();
		foreach ( isset( $result['data']['results'] ) ? array_slice( (array) $result['data']['results'], 0, 8 ) : array() as $row ) {
			$url = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'], array( 'http', 'https' ) ) : '';
			if ( '' === $url ) {
				continue;
			}
			$results[] = array(
				'title' => isset( $row['title'] ) ? substr( sanitize_text_field( (string) $row['title'] ), 0, 255 ) : '',
				'url'   => $url,
				'site'  => isset( $row['site'] ) ? substr( sanitize_text_field( (string) $row['site'] ), 0, 100 ) : (string) wp_parse_url( $url, PHP_URL_HOST ),
				'note'  => isset( $row['note'] ) ? substr( sanitize_text_field( (string) $row['note'] ), 0, 300 ) : '',
			);
		}
		// Sites that keep ranking become competitor suggestions.
		Competitor_Feeds::record_seen( $results );
		return array(
			'query'   => $query,
			'results' => $results,
			'sources' => array_slice( (array) $result['response']->sources, 0, 10 ),
			'cost'    => round( (float) $result['response']->cost, 4 ),
		);
	}
}
