<?php
/**
 * AI crawler access: which AI bots may read the site, applied through
 * WordPress's robots.txt (or as text to paste when a physical robots.txt
 * file exists, which WordPress cannot change).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Readiness;

use QuantumCloud\SeoAiSearch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * AI crawler rules.
 */
final class Ai_Crawlers {

	/**
	 * Known AI crawlers: id => [ user agent, who, purpose ].
	 *
	 * @return array
	 */
	public static function bots() {
		$bots = array(
			'gptbot'             => array( 'GPTBot', 'OpenAI', __( 'trains OpenAI models', 'chatbot' ) ),
			'oai-searchbot'      => array( 'OAI-SearchBot', 'OpenAI', __( 'shows your pages in ChatGPT search answers', 'chatbot' ) ),
			'chatgpt-user'       => array( 'ChatGPT-User', 'OpenAI', __( 'opens pages when a ChatGPT user asks for them', 'chatbot' ) ),
			'claudebot'          => array( 'ClaudeBot', 'Anthropic', __( 'trains Anthropic models', 'chatbot' ) ),
			'claude-searchbot'   => array( 'Claude-SearchBot', 'Anthropic', __( 'shows your pages in Claude search answers', 'chatbot' ) ),
			'claude-user'        => array( 'Claude-User', 'Anthropic', __( 'opens pages when a Claude user asks for them', 'chatbot' ) ),
			'google-extended'    => array( 'Google-Extended', 'Google', __( 'lets Gemini use your content (Google Search is not affected)', 'chatbot' ) ),
			'perplexitybot'      => array( 'PerplexityBot', 'Perplexity', __( 'shows your pages in Perplexity answers', 'chatbot' ) ),
			'applebot-extended'  => array( 'Applebot-Extended', 'Apple', __( 'lets Apple Intelligence use your content', 'chatbot' ) ),
			'ccbot'              => array( 'CCBot', 'Common Crawl', __( 'builds an open web archive many AI models train on', 'chatbot' ) ),
			'meta-externalagent' => array( 'meta-externalagent', 'Meta', __( 'trains Meta AI models', 'chatbot' ) ),
			'bytespider'         => array( 'Bytespider', 'ByteDance', __( 'trains ByteDance models', 'chatbot' ) ),
		);
		return (array) apply_filters( 'qcld_wpbot_seo/ai_crawlers', $bots );
	}

	/**
	 * Hook WordPress's robots.txt.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 20, 2 );
	}

	/**
	 * Owner's rules: id => allow|block (allow when not set).
	 *
	 * @return array
	 */
	public static function rules() {
		$saved = (array) Settings::get( 'ai_crawlers' );
		$out   = array();
		foreach ( array_keys( self::bots() ) as $id ) {
			$out[ $id ] = isset( $saved[ $id ] ) && 'block' === $saved[ $id ] ? 'block' : 'allow';
		}
		return $out;
	}

	/**
	 * Lines to add for blocked bots.
	 *
	 * @return string
	 */
	public static function lines() {
		$bots  = self::bots();
		$lines = '';
		foreach ( self::rules() as $id => $rule ) {
			if ( 'block' === $rule && isset( $bots[ $id ] ) ) {
				$lines .= 'User-agent: ' . $bots[ $id ][0] . "\nDisallow: /\n\n";
			}
		}
		return $lines;
	}

	/**
	 * Add the rules to WordPress's robots.txt.
	 *
	 * @param string $output    Robots.txt.
	 * @param bool   $is_public Site is public.
	 * @return string
	 */
	public static function robots( $output, $is_public ) {
		$lines = self::lines();
		if ( ! $is_public || '' === $lines ) {
			return $output;
		}
		return rtrim( (string) $output ) . "\n\n# AI crawlers (" . \QuantumCloud\SeoAiSearch\Module::product() . " SEO & AI Search)\n" . $lines;
	}

	/**
	 * Whether a physical robots.txt file overrides WordPress's.
	 *
	 * @return bool
	 */
	public static function physical_file() {
		return file_exists( trailingslashit( ABSPATH ) . 'robots.txt' );
	}

	/**
	 * Bots blocked in a robots.txt text (by the owner or anyone).
	 *
	 * @param string $text Robots.txt text.
	 * @return string[] Bot ids.
	 */
	public static function blocked_in( $text ) {
		$blocked = array();
		$groups  = preg_split( '/(?=^User-agent:)/mi', (string) $text );
		foreach ( self::bots() as $id => $bot ) {
			foreach ( $groups as $group ) {
				if ( preg_match( '/^User-agent:\s*' . preg_quote( $bot[0], '/' ) . '\s*$/mi', $group ) && preg_match( '#^Disallow:\s*/\s*$#mi', $group ) ) {
					$blocked[] = $id;
				}
			}
		}
		return $blocked;
	}
}
