<?php
/**
 * Site-wide technical facts: robots.txt, XML sitemap (WordPress or SEO
 * plugin), Search Console sitemaps, HTTPS and the HTTP-to-HTTPS redirect,
 * and URL Inspection for the most-visited pages (within Google's quota).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Technical;

use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\WPBotCore\Connections\Google;
use QuantumCloud\WPBotCore\Connections\Search_Console;

defined( 'ABSPATH' ) || exit;

/**
 * Site checks.
 */
final class Site_Checks {

	/**
	 * Pages sent to URL Inspection per scan.
	 */
	const INSPECT = 20;

	/**
	 * All site facts.
	 *
	 * @return array robots, sitemap, gsc_sitemaps, https, inspection.
	 */
	public static function run() {
		return array(
			'robots'       => self::robots(),
			'sitemap'      => self::sitemap(),
			'gsc_sitemaps' => self::gsc_sitemaps(),
			'https'        => self::https(),
			'inspection'   => self::inspection(),
		);
	}

	/**
	 * Robots.txt: reachable, blocks everything, lists a sitemap.
	 *
	 * @return array
	 */
	public static function robots() {
		$fetched = Scan::fetch( home_url( '/robots.txt' ) );
		$text    = is_array( $fetched ) && 200 === $fetched['code'] ? (string) $fetched['body'] : '';
		if ( '' === $text ) {
			// Loopback blocked: what WordPress would serve.
			ob_start();
			do_action( 'do_robotstxt' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook, run to build its robots.txt.
			$text = (string) apply_filters( 'robots_txt', ob_get_clean(), (bool) get_option( 'blog_public' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.
		}
		$blocks_all = (bool) preg_match( '#User-agent:\s*\*\s*(?:\R(?!User-agent).*)*\RDisallow:\s*/\s*(\R|$)#i', "\n" . $text );
		return array(
			'reachable'  => is_array( $fetched ) && 200 === $fetched['code'],
			'blocks_all' => $blocks_all || ! get_option( 'blog_public' ),
			'sitemaps'   => preg_match_all( '#^Sitemap:\s*(\S+)#mi', $text, $m ) ? $m[1] : array(),
			'text'       => substr( $text, 0, 4000 ),
		);
	}

	/**
	 * The site's XML sitemap.
	 *
	 * @return array url, reachable.
	 */
	public static function sitemap() {
		$candidates = array( home_url( '/sitemap_index.xml' ), home_url( '/wp-sitemap.xml' ), home_url( '/sitemap.xml' ) );
		foreach ( $candidates as $url ) {
			$fetched = Scan::fetch( $url );
			$xml     = is_array( $fetched ) && 200 === $fetched['code'] ? (string) $fetched['body'] : '';
			if ( false !== stripos( $xml, '<urlset' ) || false !== stripos( $xml, '<sitemapindex' ) ) {
				return array(
					'url'       => $url,
					'reachable' => true,
				);
			}
		}
		$core = function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled();
		return array(
			'url'       => $core ? home_url( '/wp-sitemap.xml' ) : '',
			'reachable' => false,
			'enabled'   => $core,
		);
	}

	/**
	 * Sitemaps submitted in Search Console.
	 *
	 * @return array|null
	 */
	public static function gsc_sitemaps() {
		if ( ! Google::connected() ) {
			return null;
		}
		$maps = Search_Console::sitemaps();
		return is_wp_error( $maps ) ? array( 'error' => $maps->get_error_message() ) : $maps;
	}

	/**
	 * HTTPS on and HTTP redirecting to it.
	 *
	 * @return array
	 */
	public static function https() {
		$home  = home_url( '/' );
		$https = 0 === strpos( $home, 'https://' );
		$out   = array(
			'https'    => $https,
			'redirect' => null,
		);
		if ( $https ) {
			$fetched         = Scan::fetch( 'http://' . substr( $home, 8 ) );
			$out['redirect'] = is_array( $fetched ) && '' !== $fetched['location'] && 0 === strpos( $fetched['location'], 'https://' );
		}
		return $out;
	}

	/**
	 * URL Inspection for the pages with most clicks.
	 *
	 * @return array url => verdict, coverage, indexing.
	 */
	public static function inspection() {
		if ( ! Google::connected() ) {
			return array();
		}
		$period = Period::last( 28 );
		$out    = array();
		foreach ( Data_Store::top( 'page', $period['start'], $period['end'], self::INSPECT ) as $page ) {
			$result = Search_Console::inspect( $page['label'] );
			if ( is_wp_error( $result ) ) {
				break;
			}
			$out[ $page['label'] ] = array(
				'verdict'  => $result['verdict'],
				'coverage' => $result['coverage'],
				'indexing' => $result['indexing'],
			);
		}
		return $out;
	}
}
