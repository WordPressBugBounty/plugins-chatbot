<?php
/**
 * Trait ParsesAiJson — shared JSON parsing for AI responses.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: modules/content-generator/services/trait-parses-ai-json.php
 * Copyright (C) Noor Alam (wpthemespace.com). Licensed GPLv2 or later.
 *
 * Modified by QuantumCloud for WPBot, 2026: Namespace, prefixes and text
 * domain renamed, integrated with WPBot.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Qcld_Wpbot_Ai_Seo_Content_Parses_Ai_Json {

	/**
	 * Attempt to parse a JSON string returned by an AI model.
	 *
	 * Stages:
	 *  1. Direct json_decode.
	 *  2. Extract from fenced code block.
	 *  3. Extract first { … } object.
	 *  4. Fix unescaped newlines inside JSON string values, then re-try.
	 *  5. Regex extraction of individual fields (last resort).
	 *
	 * @param string $raw The raw response string.
	 * @return array|null Decoded array or null on failure.
	 */
	private function parse_json_response( string $raw ): ?array {
		// Use the global parser which handles thinking-model output.
		$result = qcld_wpbot_ai_seo_parse_ai_json( $raw );
		if ( $result ) {
			return $result;
		}

		// Stage 5 — Regex extraction of individual fields (last resort).
		return $this->extract_fields_fallback( $raw );
	}

	/**
	 * Last-resort field extraction via regex.
	 */
	private function extract_fields_fallback( string $raw ): ?array {
		$result = array();

		// Title.
		if ( preg_match( '/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*?)"/s', $raw, $m ) ) {
			$result['title'] = qcld_wpbot_ai_seo_json_unescape( $m[1] );
		}

		// Excerpt.
		if ( preg_match( '/"excerpt"\s*:\s*"((?:[^"\\\\]|\\\\.)*?)"/s', $raw, $m ) ) {
			$result['excerpt'] = qcld_wpbot_ai_seo_json_unescape( $m[1] );
		}

		// Body — capture everything between "body": " and the next top-level key or closing brace.
		if ( preg_match( '/"body"\s*:\s*"([\s\S]*?)"\s*(?:,\s*"(?:excerpt|outline|title)"|}\s*$)/s', $raw, $m ) ) {
			$result['body'] = qcld_wpbot_ai_seo_json_unescape( $m[1] );
		}

		// Outline (try to grab it as an array).
		if ( preg_match( '/"outline"\s*:\s*(\[[\s\S]*?\])/', $raw, $m ) ) {
			$outline = json_decode( $m[1], true );
			if ( is_array( $outline ) ) {
				$result['outline'] = $outline;
			}
		}

		return ! empty( $result ) ? $result : null;
	}
}
