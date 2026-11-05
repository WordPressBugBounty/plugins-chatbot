<?php
/**
 * Competitor gap without a keyword data provider (S14): a polite look at
 * each competitor's sitemap, page titles and headings, then one AI call.
 *
 * Polite: robots.txt is respected (WPBot user agent), private addresses are
 * refused (wp_safe_remote_get), pages are fetched in small batches through
 * the job queue (five a step, short timeouts), at most 25 pages a
 * competitor. When AI Writer Pro's Competitor Watch already holds a
 * competitor's titles and headings, they are read from it instead.
 *
 * No competitor text is stored beyond titles and headings for the run: they
 * live in a transient for the job and are deleted when it finishes; the
 * saved result keeps only topics, keywords and page types.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Research;

use QuantumCloud\SeoAiSearch\Links\Robots;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Technical\Scan;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Competitor page fetch job.
 */
final class Competitor_Fetch {

	const STEP_DISCOVER = 'seo_gap_discover';
	const STEP_PAGES    = 'seo_gap_pages';
	const STEP_FINISH   = 'seo_gap_finish';

	/**
	 * Pages per step and steps per competitor.
	 */
	const PER_STEP  = 5;
	const STEPS     = 5;
	const MAX_PAGES = 25;

	/**
	 * Job step handlers.
	 *
	 * @return array
	 */
	public static function handlers() {
		return array(
			self::STEP_DISCOVER => array( __CLASS__, 'discover' ),
			self::STEP_PAGES    => array( __CLASS__, 'pages' ),
			self::STEP_FINISH   => array( __CLASS__, 'finish' ),
		);
	}

	/**
	 * Start the job for a research run.
	 *
	 * @param int      $research_id Research run.
	 * @param string[] $competitors Domains.
	 * @param array    $place       language, country.
	 * @return int|WP_Error Job id.
	 */
	public static function start( $research_id, array $competitors, array $place ) {
		$steps = array();
		foreach ( $competitors as $domain ) {
			$steps[] = array(
				'kind'  => self::STEP_DISCOVER,
				/* translators: %s: domain. */
				'label' => sprintf( __( 'Read the sitemap of %s', 'chatbot' ), $domain ),
				'input' => array(
					'research' => (int) $research_id,
					'domain'   => $domain,
				),
			);
			for ( $i = 0; $i < self::STEPS; $i++ ) {
				$steps[] = array(
					'kind'  => self::STEP_PAGES,
					/* translators: 1: domain, 2: batch number. */
					'label' => sprintf( __( 'Read page titles of %1$s, batch %2$d', 'chatbot' ), $domain, $i + 1 ),
					'input' => array(
						'research' => (int) $research_id,
						'domain'   => $domain,
						'batch'    => $i,
					),
				);
			}
		}
		$steps[] = array(
			'kind'  => self::STEP_FINISH,
			'label' => __( 'Compare with your site', 'chatbot' ),
			'input' => array(
				'research'    => (int) $research_id,
				'competitors' => $competitors,
				'place'       => $place,
			),
		);
		$job     = (int) Job_Store::create(
			Module::ID,
			'research_gap',
			array(
				'title'     => __( 'Competitor gap research', 'chatbot' ),
				'object_id' => (int) $research_id,
			),
			$steps
		);
		if ( ! $job ) {
			return new WP_Error( 'qcld_wpbot_seo_job_failed', __( 'The research could not be started because the job could not be saved to the database.', 'chatbot' ), array( 'status' => 500 ) );
		}
		return $job;
	}

	/**
	 * Step: find the competitor's page URLs (or read them from Competitor Watch).
	 *
	 * @param array $step Step.
	 * @return array
	 */
	public static function discover( array $step ) {
		$input  = (array) $step['input'];
		$domain = (string) $input['domain'];
		$state  = self::state( (int) $input['research'] );

		$watched = self::from_competitor_watch( $domain );
		if ( array() !== $watched ) {
			$state['pages'][ $domain ] = $watched;
			$state['urls'][ $domain ]  = array();
			self::save_state( (int) $input['research'], $state );
			return array(
				'source' => 'competitor_watch',
				'pages'  => count( $watched ),
			);
		}

		$urls = self::sitemap_urls( $domain );
		if ( array() === $urls ) {
			// No readable sitemap: the home page still tells something.
			$urls = self::allowed( 'https://' . $domain . '/' ) ? array( 'https://' . $domain . '/' ) : array();
		}
		$state['urls'][ $domain ]  = array_slice( $urls, 0, self::MAX_PAGES );
		$state['pages'][ $domain ] = isset( $state['pages'][ $domain ] ) ? $state['pages'][ $domain ] : array();
		self::save_state( (int) $input['research'], $state );
		return array(
			'source' => 'sitemap',
			'urls'   => count( $state['urls'][ $domain ] ),
		);
	}

	/**
	 * Step: titles and headings of a few pages.
	 *
	 * @param array $step Step.
	 * @return array
	 */
	public static function pages( array $step ) {
		$input  = (array) $step['input'];
		$domain = (string) $input['domain'];
		$state  = self::state( (int) $input['research'] );
		$urls   = isset( $state['urls'][ $domain ] ) ? array_slice( (array) $state['urls'][ $domain ], (int) $input['batch'] * self::PER_STEP, self::PER_STEP ) : array();
		$read   = 0;
		foreach ( $urls as $url ) {
			if ( ! self::allowed( $url ) ) {
				continue;
			}
			$page = self::fetch( $url );
			if ( is_wp_error( $page ) || 200 !== $page['code'] ) {
				continue;
			}
			$outline = self::outline( $page['body'] );
			if ( '' !== $outline['title'] ) {
				$state['pages'][ $domain ][] = $outline;
				++$read;
			}
		}
		self::save_state( (int) $input['research'], $state );
		return array( 'pages' => $read );
	}

	/**
	 * Step: one AI call over the titles and headings, then forget them.
	 *
	 * @param array $step Step.
	 * @return array|WP_Error
	 */
	public static function finish( array $step ) {
		$input    = (array) $step['input'];
		$research = (int) $input['research'];
		$state    = self::state( $research );
		$pages    = array_filter( isset( $state['pages'] ) ? (array) $state['pages'] : array() );
		if ( array() === $pages ) {
			delete_transient( self::key( $research ) );
			$error = __( 'No competitor pages could be read (their robots.txt may not allow it, or the sites did not answer). Try other competitors, or connect a keyword data provider for real ranking data.', 'chatbot' );
			Research_Store::finish( $research, 'failed', array( 'error' => $error ) );
			return new WP_Error( 'qcld_wpbot_seo_gap_no_pages', $error );
		}
		$out = Research::gap_from_pages( (array) $input['competitors'], $pages, (array) $input['place'] );
		delete_transient( self::key( $research ) );
		if ( is_wp_error( $out ) ) {
			Research_Store::finish( $research, 'failed', array( 'error' => $out->get_error_message() ) );
			return $out;
		}
		$out['result']['pages_read'] = array_map( 'count', $pages );
		Research_Store::finish( $research, 'done', $out['result'], $out['cost'], $out['sources'] );
		return array( 'done' => true );
	}

	/**
	 * Page URLs from the competitor's sitemap (robots.txt Sitemap lines,
	 * then /sitemap.xml; one level of sitemap index).
	 *
	 * @param string $domain Domain.
	 * @return string[]
	 */
	public static function sitemap_urls( $domain ) {
		$base    = 'https://' . $domain;
		$robots  = self::fetch( $base . '/robots.txt' );
		$indexes = array();
		if ( ! is_wp_error( $robots ) && 200 === $robots['code'] && preg_match_all( '/^\s*sitemap:\s*(\S+)/im', $robots['body'], $found ) ) {
			$indexes = array_slice( $found[1], 0, 2 );
		}
		if ( array() === $indexes ) {
			$indexes = array( $base . '/sitemap.xml' );
		}
		$urls = array();
		foreach ( $indexes as $sitemap ) {
			$list = self::read_sitemap( $sitemap );
			foreach ( $list['sitemaps'] as $child ) {
				if ( count( $urls ) >= self::MAX_PAGES * 2 ) {
					break;
				}
				$urls = array_merge( $urls, self::read_sitemap( $child )['urls'] );
			}
			$urls = array_merge( $urls, $list['urls'] );
		}
		$own   = array();
		$other = array();
		foreach ( array_unique( $urls ) as $url ) {
			$host = strtolower( (string) preg_replace( '#^www\.#', '', (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
			if ( $host !== $domain ) {
				continue;
			}
			// Content pages first; tag, category, author and paging lists last.
			if ( preg_match( '#/(tag|category|author|page)/#i', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) {
				$other[] = $url;
			} else {
				$own[] = $url;
			}
		}
		return array_slice( array_merge( $own, $other ), 0, self::MAX_PAGES );
	}

	/**
	 * Title and headings (h1–h3, at most eight) of a page.
	 *
	 * @param string $html HTML.
	 * @return array title, headings.
	 */
	public static function outline( $html ) {
		$title = '';
		if ( preg_match( '#<title[^>]*>(.*?)</title>#is', (string) $html, $match ) ) {
			$title = self::clean( $match[1], 160 );
		}
		$headings = array();
		if ( preg_match_all( '#<h([1-3])[^>]*>(.*?)</h\1>#is', (string) $html, $found ) ) {
			foreach ( $found[2] as $text ) {
				$text = self::clean( $text, 120 );
				if ( '' !== $text && ! in_array( $text, $headings, true ) ) {
					$headings[] = $text;
				}
				if ( count( $headings ) >= 8 ) {
					break;
				}
			}
		}
		return array(
			'title'    => $title,
			'headings' => $headings,
		);
	}

	/**
	 * Fetch a competitor URL (short timeout, WPBot user agent, size cap).
	 *
	 * @param string $url URL.
	 * @return array|WP_Error code, body.
	 */
	public static function fetch( $url ) {
		/**
		 * Short-circuit a competitor fetch (tests and caching).
		 *
		 * @param array|WP_Error|null $pre Result (code, body) or null.
		 * @param string              $url URL.
		 */
		$pre = apply_filters( 'qcld_wpbot_seo/competitor_fetch', null, $url );
		if ( null !== $pre ) {
			return $pre;
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 5,
				'redirection'         => 2,
				'user-agent'          => Scan::user_agent(),
				'limit_response_size' => 800000,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * May WPBot fetch this URL (the competitor's robots.txt, cached a day)?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function allowed( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false;
		}
		$origin = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'];
		$key    = 'qcld_wpbot_seo_comp_robots_' . md5( $origin );
		$text   = get_transient( $key );
		if ( false === $text ) {
			$robots = self::fetch( $origin . '/robots.txt' );
			$text   = ! is_wp_error( $robots ) && 200 === $robots['code'] ? (string) $robots['body'] : '';
			set_transient( $key, $text, DAY_IN_SECONDS );
		}
		$path = ( isset( $parts['path'] ) ? $parts['path'] : '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		return Robots::allowed( (string) $text, $path );
	}

	/**
	 * URLs and child sitemaps listed in a sitemap.
	 *
	 * @param string $url Sitemap URL.
	 * @return array urls, sitemaps.
	 */
	private static function read_sitemap( $url ) {
		$out = array(
			'urls'     => array(),
			'sitemaps' => array(),
		);
		if ( ! self::allowed( $url ) ) {
			return $out;
		}
		$response = self::fetch( $url );
		if ( is_wp_error( $response ) || 200 !== $response['code'] ) {
			return $out;
		}
		$body     = (string) $response['body'];
		$is_index = false !== stripos( $body, '<sitemapindex' );
		if ( preg_match_all( '#<loc>\s*(.*?)\s*</loc>#is', $body, $found ) ) {
			foreach ( $found[1] as $loc ) {
				$loc = esc_url_raw( html_entity_decode( trim( $loc ), ENT_QUOTES ), array( 'http', 'https' ) );
				if ( '' === $loc ) {
					continue;
				}
				if ( $is_index ) {
					$out['sitemaps'][] = $loc;
				} else {
					$out['urls'][] = $loc;
				}
			}
		}
		$out['sitemaps'] = array_slice( $out['sitemaps'], 0, 3 );
		$out['urls']     = array_slice( $out['urls'], 0, 500 );
		return $out;
	}

	/**
	 * Titles and headings AI Writer Pro's Competitor Watch already holds.
	 *
	 * @param string $domain Domain.
	 * @return array[]
	 */
	private static function from_competitor_watch( $domain ) {
		if ( ! class_exists( '\QuantumCloud\AiWriterPro\Competitors\Competitors' ) || ! class_exists( '\QuantumCloud\AiWriterPro\Competitors\Insights' ) ) {
			return array();
		}
		foreach ( (array) \QuantumCloud\AiWriterPro\Competitors\Competitors::all() as $competitor ) {
			$host = strtolower( (string) preg_replace( '#^www\.#', '', (string) wp_parse_url( isset( $competitor['url'] ) ? (string) $competitor['url'] : '', PHP_URL_HOST ) ) );
			if ( $host !== $domain || empty( $competitor['id'] ) ) {
				continue;
			}
			$pages = array();
			foreach ( (array) \QuantumCloud\AiWriterPro\Competitors\Insights::pages(
				array(
					'competitor' => (string) $competitor['id'],
					'fetched'    => true,
				),
				self::MAX_PAGES * 2
			) as $row ) {
				$headings = array();
				foreach ( (array) $row['headings'] as $heading ) {
					$text = is_array( $heading ) ? ( isset( $heading['text'] ) ? $heading['text'] : '' ) : $heading;
					$text = self::clean( (string) $text, 120 );
					if ( '' !== $text ) {
						$headings[] = $text;
					}
				}
				$title = self::clean( (string) $row['title'], 160 );
				if ( '' !== $title ) {
					$pages[] = array(
						'title'    => $title,
						'headings' => array_slice( $headings, 0, 8 ),
					);
				}
			}
			return $pages;
		}
		return array();
	}

	/**
	 * Plain text, single spaces, capped.
	 *
	 * @param string $text Text or HTML.
	 * @param int    $len  Max length.
	 * @return string
	 */
	private static function clean( $text, $len ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $len ) : substr( $text, 0, $len );
	}

	/**
	 * Run state (URLs and titles) for the job only.
	 *
	 * @param int $research Research id.
	 * @return array
	 */
	private static function state( $research ) {
		$state = get_transient( self::key( $research ) );
		return is_array( $state ) ? $state : array(
			'urls'  => array(),
			'pages' => array(),
		);
	}

	/**
	 * Save the run state (a day at most).
	 *
	 * @param int   $research Research id.
	 * @param array $state    State.
	 * @return void
	 */
	private static function save_state( $research, array $state ) {
		set_transient( self::key( $research ), $state, DAY_IN_SECONDS );
	}

	/**
	 * Transient key.
	 *
	 * @param int $research Research id.
	 * @return string
	 */
	private static function key( $research ) {
		return 'qcld_wpbot_seo_gap_' . (int) $research;
	}
}
