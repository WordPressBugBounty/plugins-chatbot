<?php
/**
 * Public keyword data API for modules (AI Writer reads volumes here).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_keyword_data' ) ) {
	/**
	 * Cached keyword data (never costs anything; lookups are started by the
	 * module that owns the provider settings).
	 *
	 * @param string[] $keywords Keywords.
	 * @param array    $args     country, language (default: site locale).
	 * @return array Normalised keyword => volume, cpc, competition (0–1), trend (int[] oldest first), provider, fetched_at. Missing keywords are not in the array; a null volume means the provider had no data.
	 */
	function qcld_wpbot_core_keyword_data( array $keywords, array $args = array() ) {
		return \QuantumCloud\WPBotCore\Keywords\Keyword_Data::cached(
			$keywords,
			isset( $args['country'] ) ? (string) $args['country'] : '',
			isset( $args['language'] ) ? (string) $args['language'] : ''
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_core_keyword_provider' ) ) {
	/**
	 * The keyword data source for volumes and ideas, or null. Since round 2
	 * (R5) Google Keyword Planner (Pro) supplies volumes and ideas when it is
	 * on; the paid provider then still supplies rankings, difficulty and backlinks.
	 *
	 * @return array|null id, label, unit, failing (bool), planner (bool), rankings (paid provider label or ''), backlinks (bool).
	 */
	function qcld_wpbot_core_keyword_provider() {
		$provider = \QuantumCloud\WPBotCore\Keywords\Keyword_Keys::for_op( 'volumes' );
		if ( ! $provider ) {
			return null;
		}
		$paid = \QuantumCloud\WPBotCore\Keywords\Keyword_Keys::provider();
		return array(
			'id'        => $provider->id(),
			'label'     => $provider->label(),
			'unit'      => $provider->unit(),
			'failing'   => $paid && $paid->id() === $provider->id() ? array() !== \QuantumCloud\WPBotCore\Keywords\Keyword_Keys::notices() : 'failing' === \QuantumCloud\WPBotCore\Connections\Google_Ads::status()['status'],
			'planner'   => \QuantumCloud\WPBotCore\Keywords\Providers\Keyword_Planner::ID === $provider->id(),
			'rankings'  => $paid ? $paid->label() : '',
			'backlinks' => (bool) $paid,
		);
	}
}

if ( ! function_exists( 'qcld_wpbot_core_youtube_search' ) ) {
	/**
	 * Videos that come up on YouTube for a search (owner's Google connection, read-only; cached 7 days).
	 *
	 * @param string $query Search.
	 * @param array  $args  limit, region, language.
	 * @return array|WP_Error List of video_id, title, url, channel, published, views.
	 */
	function qcld_wpbot_core_youtube_search( $query, array $args = array() ) {
		return \QuantumCloud\WPBotCore\Connections\YouTube::search( $query, $args );
	}
}
