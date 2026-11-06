<?php
/**
 * Topic Finder source "Keyword Vault" (CR-4): keywords marked Targeted in
 * SEO & AI Search that have no page yet. Registered only when WPBot Core
 * has the Vault; SEO & AI Search may replace it with its own definition
 * under the same id ("seo").
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics\Sources;

use QuantumCloud\AiWriter\Topics\Vault_Link;
use QuantumCloud\WPBotCore\Map\Topic_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword Vault source.
 */
final class Vault_Source {

	/**
	 * Ideas from targeted keywords without a page.
	 *
	 * @param array $context Context (unused).
	 * @return array[]
	 */
	public static function ideas( array $context = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Source callback signature.
		$ideas = array();
		foreach ( Vault_Link::targeted_without_page( 40 ) as $row ) {
			$topic = '';
			if ( ! empty( $row['topic_id'] ) && class_exists( Topic_Map::class ) ) {
				$found = Topic_Map::get( (int) $row['topic_id'] );
				$topic = $found ? (string) $found['name'] : '';
			}
			$reason  = '' !== (string) ( isset( $row['context']['reason'] ) ? $row['context']['reason'] : '' ) ? (string) $row['context']['reason'] : __( 'Targeted in your Keyword Vault; no page yet.', 'chatbot' );
			$ideas[] = array_merge(
				array(
					'title'      => (string) $row['keyword'],
					'ref'        => 'vault:' . (int) $row['id'],
					'reason'     => $reason,
					'relevance'  => 'high' === $row['demand'] ? 80 : ( 'low' === $row['demand'] ? 40 : 60 ),
					'main_query' => (string) $row['keyword'],
					'topic'      => $topic,
					'questions'  => isset( $row['context']['questions'] ) ? array_slice( (array) $row['context']['questions'], 0, 5 ) : array(),
				),
				Vault_Link::demand_extras( $row )
			);
		}
		return $ideas;
	}

	/**
	 * Source status.
	 *
	 * @return array
	 */
	public static function status() {
		return array( 'status' => 'connected' );
	}
}
