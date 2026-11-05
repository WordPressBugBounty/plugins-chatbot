<?php
/**
 * Polite checks of known links: is the link still on the page, follow or
 * nofollow or sponsored, anchor text, and does the target page work.
 * Batched in the job queue; robots.txt respected; one request per page.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Technical\Link_Check;
use QuantumCloud\SeoAiSearch\Technical\Scan;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Link checks.
 */
final class Link_Checker {

	/**
	 * Links per job step.
	 */
	const PER_STEP = 5;

	/**
	 * Job step handlers.
	 *
	 * @return array
	 */
	public static function handlers() {
		return array( 'seo_link_check' => array( __CLASS__, 'step' ) );
	}

	/**
	 * Start a check job.
	 *
	 * @param int[] $ids Links (empty = all, oldest check first).
	 * @return int|WP_Error Job id.
	 */
	public static function start( array $ids = array() ) {
		$ids = Link_Store::ids_to_check( $ids );
		if ( array() === $ids ) {
			return new WP_Error( 'qcld_wpbot_seo_nothing', __( 'There are no links to check yet.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$steps = array();
		foreach ( array_chunk( $ids, self::PER_STEP ) as $index => $chunk ) {
			$steps[] = array(
				'kind'  => 'seo_link_check',
				/* translators: %d: batch number. */
				'label' => sprintf( __( 'Check links, batch %d', 'chatbot' ), $index + 1 ),
				'input' => array( 'ids' => $chunk ),
			);
		}
		return (int) Job_Store::create( Module::ID, 'link_check', array( 'title' => __( 'Check known links', 'chatbot' ) ), $steps );
	}

	/**
	 * Job step: check a few links.
	 *
	 * @param array $step Step.
	 * @return array
	 */
	public static function step( array $step ) {
		$out = array();
		foreach ( (array) $step['input']['ids'] as $id ) {
			$link = Link_Store::get( (int) $id );
			if ( ! $link ) {
				continue;
			}
			$result = self::check( $link['source_url'], $link['target_url'], $link );
			Link_Store::save_check( (int) $id, $result );
			$out[ (int) $id ] = $result['status'];
		}
		return array( 'checked' => $out );
	}

	/**
	 * Check one link now (no queue).
	 *
	 * @param string $source Page that should link.
	 * @param string $target Page linked to.
	 * @param array  $known  Known anchor and rel (to spot changes).
	 * @param bool   $any_page Accept a link to any page of the target's site.
	 * @return array status, http_code, rel, anchor, target_ok, message.
	 */
	public static function check( $source, $target, array $known = array(), $any_page = false ) {
		$result = array(
			'status'    => 'error',
			'http_code' => 0,
			'rel'       => '',
			'anchor'    => '',
			'target_ok' => self::target_ok( $target ),
			'message'   => '',
		);
		if ( ! Robots::may_fetch( $source ) ) {
			$result['status']  = 'blocked';
			$result['message'] = __( 'The site\'s robots.txt asks checkers not to visit this page.', 'chatbot' );
			return $result;
		}
		$page = self::fetch( $source );
		if ( is_wp_error( $page ) ) {
			$result['message'] = $page->get_error_message();
			return $result;
		}
		$result['http_code'] = $page['code'];
		if ( $page['code'] >= 400 ) {
			$result['status']  = 'lost';
			$result['message'] = __( 'The linking page no longer works.', 'chatbot' );
			return $result;
		}
		/**
		 * HTML of a page checked for a link (tests can replace it).
		 *
		 * @param string $html   HTML.
		 * @param string $source Page URL.
		 */
		$html  = (string) apply_filters( 'qcld_wpbot_seo/link_check_html', $page['body'], $source );
		$found = Link_Finder::find( $html, $target, $page['url'], $any_page );
		if ( null === $found ) {
			$result['status']  = 'lost';
			$result['message'] = __( 'The page no longer links to you.', 'chatbot' );
			return $result;
		}
		$result['rel']    = $found['rel'];
		$result['anchor'] = $found['anchor'];
		$result['status'] = 'live';
		if ( ! empty( $known['rel'] ) && $known['rel'] !== $found['rel'] && 'follow' === $known['rel'] ) {
			$result['status']  = 'changed';
			$result['message'] = __( 'The link changed from a normal link to nofollow or sponsored.', 'chatbot' );
		}
		return $result;
	}

	/**
	 * Does a page of this site still work?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function target_ok( $url ) {
		if ( '' === (string) $url ) {
			return true;
		}
		if ( Link_Finder::host( $url ) === Link_Finder::host( home_url() ) ) {
			$post_id = url_to_postid( $url );
			if ( $post_id ) {
				return 'publish' === get_post_status( $post_id );
			}
			$page = Scan::fetch( $url, 5 );
			return ! is_wp_error( $page ) && $page['code'] > 0 && $page['code'] < 400;
		}
		$code = Link_Check::status( $url );
		return $code > 0 && $code < 400;
	}

	/**
	 * Fetch another site's page (redirects followed, 1.5 MB cap).
	 *
	 * @param string $url URL.
	 * @return array|WP_Error code, body, url.
	 */
	public static function fetch( $url ) {
		/**
		 * Short-circuit a fetch (tests and caching).
		 *
		 * @param array|WP_Error|null $pre Result or null.
		 * @param string              $url URL.
		 */
		$pre = apply_filters( 'qcld_wpbot_seo/link_check_fetch', null, $url );
		if ( null !== $pre ) {
			return $pre;
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 3,
				'user-agent'          => Scan::user_agent(),
				'limit_response_size' => 1500000,
				'headers'             => array( 'Accept' => 'text/html' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'qcld_wpbot_seo_fetch', __( 'The linking page could not be reached (timeout or connection error). It is checked again next time.', 'chatbot' ) );
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => (string) wp_remote_retrieve_body( $response ),
			'url'  => $url,
		);
	}
}
