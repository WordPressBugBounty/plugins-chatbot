<?php
/**
 * A keyword data provider on the owner's own account and key.
 *
 * Rows are normalised to: keyword, volume (int|null), cpc (float|null),
 * competition (float 0–1|null), trend (int[] oldest month first).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords;

defined( 'ABSPATH' ) || exit;

/**
 * Provider contract.
 */
interface Provider {

	/**
	 * Stable id.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Name shown to the owner.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Unit costs and balances are counted in: usd, credits or units.
	 *
	 * @return string
	 */
	public function unit();

	/**
	 * Most keywords per volume request.
	 *
	 * @return int
	 */
	public function batch_size();

	/**
	 * Estimated cost of an operation (shown before it runs).
	 *
	 * @param string $op    volumes | ideas | domain_keywords | backlinks.
	 * @param int    $count Keywords (volumes) or rows asked for.
	 * @return float
	 */
	public function estimate( $op, $count );

	/**
	 * Volumes for keywords (one batch).
	 *
	 * @param string[] $keywords Keywords.
	 * @param string   $country  ISO country code.
	 * @param string   $language Language code.
	 * @return array|\WP_Error List of rows.
	 */
	public function volumes( array $keywords, $country, $language );

	/**
	 * Keyword ideas for a seed.
	 *
	 * @param string $seed     Seed keyword.
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @param int    $limit    Rows.
	 * @return array|\WP_Error List of rows.
	 */
	public function ideas( $seed, $country, $language, $limit );

	/**
	 * Keywords a domain ranks for.
	 *
	 * @param string $domain   Domain.
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @param int    $limit    Rows.
	 * @return array|\WP_Error List of rows with position.
	 */
	public function domain_keywords( $domain, $country, $language, $limit );

	/**
	 * Backlinks to a domain.
	 *
	 * @param string $domain Domain.
	 * @param int    $limit  Rows.
	 * @return array|\WP_Error List of source_url, target_url, anchor, rel.
	 */
	public function backlinks( $domain, $limit );

	/**
	 * Balance left on the owner's account.
	 *
	 * @return float|\WP_Error
	 */
	public function balance();
}
