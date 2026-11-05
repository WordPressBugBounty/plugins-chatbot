<?php
/**
 * Guardrails in line with Google's spam policies (pure, unit-tested):
 * over-optimised anchors, guest-post pacing, exchange share, warning
 * signs on publications.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Warnings only; nothing is blocked.
 */
final class Link_Guard {

	/**
	 * Does an anchor look over-optimised? Brand names, page titles, URLs and
	 * generic words are fine; an anchor that is (or mostly is) a search
	 * phrase is not.
	 *
	 * @param string   $anchor  Anchor text.
	 * @param string   $brand   Brand or site name.
	 * @param string   $title   Linked page title.
	 * @param string[] $queries Searches the site wants to rank for.
	 * @return bool
	 */
	public static function anchor_over_optimised( $anchor, $brand, $title, array $queries ) {
		$a = self::norm( $anchor );
		if ( '' === $a || preg_match( '#^(https?://|www\.)#', $a ) ) {
			return false;
		}
		$brand_n = self::norm( $brand );
		if ( '' !== $brand_n && false !== strpos( $a, $brand_n ) ) {
			return false;
		}
		if ( '' !== self::norm( $title ) && self::norm( $title ) === $a ) {
			return false;
		}
		if ( in_array( $a, array( 'here', 'click here', 'this page', 'website', 'this guide', 'read more', 'source', 'this article' ), true ) ) {
			return false;
		}
		foreach ( $queries as $query ) {
			$q = self::norm( $query );
			if ( '' === $q ) {
				continue;
			}
			if ( $a === $q ) {
				return true;
			}
			$aw = array_filter( explode( ' ', $a ) );
			$qw = array_filter( explode( ' ', $q ) );
			if ( count( $qw ) >= 2 && count( array_intersect( $aw, $qw ) ) >= max( 2, count( $aw ) - 1 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Guest-post pacing warnings.
	 *
	 * @param array $posts List of date (Y-m-d), anchor of published guest posts.
	 * @param int   $now   Timestamp.
	 * @return string[] Warning ids: many_recent, same_anchor.
	 */
	public static function pacing( array $posts, $now ) {
		$warnings = array();
		$recent   = 0;
		foreach ( $posts as $post ) {
			if ( strtotime( $post['date'] ) >= $now - 30 * DAY_IN_SECONDS ) {
				++$recent;
			}
		}
		if ( $recent > 4 ) {
			$warnings[] = 'many_recent';
		}
		$anchors = array_filter( array_map( array( __CLASS__, 'norm' ), array_column( $posts, 'anchor' ) ) );
		if ( count( $anchors ) >= 3 ) {
			$counts = array_count_values( $anchors );
			if ( max( $counts ) / count( $anchors ) > 0.5 ) {
				$warnings[] = 'same_anchor';
			}
		}
		return $warnings;
	}

	/**
	 * Exchange warnings (Google's policy on excessive link exchanges).
	 *
	 * @param int $exchanges     Live or proposed exchanges.
	 * @param int $links         Known links to the site.
	 * @param int $recent        Exchanges added in the last 30 days.
	 * @return string[] Warning ids: large_share, many_recent.
	 */
	public static function exchanges( $exchanges, $links, $recent ) {
		$warnings = array();
		if ( $exchanges >= 3 && $exchanges > 0.2 * max( 1, $links ) ) {
			$warnings[] = 'large_share';
		}
		if ( $recent > 3 ) {
			$warnings[] = 'many_recent';
		}
		return $warnings;
	}

	/**
	 * Warning signs on a publication's page text (link sellers).
	 *
	 * @param string $text          Page text.
	 * @param int    $outbound      Outbound links on the page.
	 * @param int    $words         Words on the page.
	 * @return string[] sells_links, link_heavy.
	 */
	public static function publication_flags( $text, $outbound, $words ) {
		$flags = array();
		if ( preg_match( '/(sponsored post|guest post)\s*(price|fee|rate|cost)|\bprice list\b|(\$|usd|€|£)\s?\d+\s*(per|\/)\s*(post|article|link)|\bdo-?follow links?\s+(for sale|available)|\bbuy (a )?(guest post|backlink)/i', (string) $text ) ) {
			$flags[] = 'sells_links';
		}
		if ( $outbound > 60 && $outbound > $words / 15 ) {
			$flags[] = 'link_heavy';
		}
		return $flags;
	}

	/**
	 * Lowercase words only.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function norm( $text ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text ) : strtolower( (string) $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) preg_replace( '/[^\p{L}\p{N}\s.:\/]+/u', ' ', $text ) ) );
	}
}
