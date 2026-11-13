<?php
/**
 * Technical SEO: scans, issues, browser checks, redirects, 404 log, speed.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;
use QuantumCloud\SeoAiSearch\Storage\Schema;
use QuantumCloud\SeoAiSearch\Technical\Issue_Builder;
use QuantumCloud\SeoAiSearch\Technical\Not_Found_Log;
use QuantumCloud\SeoAiSearch\Technical\Page_Parser;
use QuantumCloud\SeoAiSearch\Technical\Page_Speed;
use QuantumCloud\SeoAiSearch\Technical\Redirects;
use QuantumCloud\SeoAiSearch\Technical\Scan;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /technical, /redirects, /not-found, /pagespeed.
 */
final class Technical_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/technical'                          => array( WP_REST_Server::READABLE, 'report', 'can_view' ),
			'/technical/scan'                     => array( WP_REST_Server::CREATABLE, 'scan', 'can_use' ),
			'/technical/scan/(?P<id>\d+)/next'    => array( WP_REST_Server::READABLE, 'next_pages', 'can_use' ),
			'/technical/scan/(?P<id>\d+)/page'    => array( WP_REST_Server::CREATABLE, 'browser_page', 'can_use' ),
			'/technical/scan/(?P<id>\d+)/rebuild' => array( WP_REST_Server::CREATABLE, 'rebuild', 'can_use' ),
			'/technical/fix/(?P<id>\d+)'          => array( WP_REST_Server::CREATABLE, 'fix', 'can_publish' ),
			'/redirects'                          => array( WP_REST_Server::READABLE, 'redirects', 'can_view' ),
			'/redirects/save'                     => array( WP_REST_Server::CREATABLE, 'save_redirect', 'can_manage' ),
			'/redirects/(?P<id>\d+)/delete'       => array( WP_REST_Server::CREATABLE, 'delete_redirect', 'can_manage' ),
			'/not-found'                          => array( WP_REST_Server::READABLE, 'not_found', 'can_view' ),
			'/not-found/(?P<id>\d+)/delete'       => array( WP_REST_Server::CREATABLE, 'delete_not_found', 'can_use' ),
			'/pagespeed'                          => array( WP_REST_Server::CREATABLE, 'pagespeed', 'can_use' ),
		);
		foreach ( $routes as $route => $def ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => array( $this, $def[2] ),
				)
			);
		}
	}

	/**
	 * Latest scan report: score, categories with counts and examples.
	 *
	 * @return \WP_REST_Response
	 */
	public function report() {
		global $wpdb;
		$scan    = Scan::latest();
		$running = Scan::running();
		$out     = array(
			'scan'       => $scan,
			'running'    => $running,
			'categories' => array(),
			'speed'      => Page_Speed::stored(),
			'redirects'  => array(
				'active'  => Redirects::active(),
				'handled' => Seo_Plugins::handles_redirects(),
			),
			'seoPlugin'  => Seo_Plugins::label(),
		);
		$done    = $scan && 'done' === $scan['status'] ? $scan : Scan::latest( 'done' );
		if ( $done ) {
			$out['done'] = $done;
			$table       = Schema::table( Schema::ISSUES );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE scan_id = %d ORDER BY FIELD(severity,'high','medium','low'), id", $table, $done['id'] ), ARRAY_A );
			$cats = array();
			foreach ( Issue_Builder::categories() as $id => $label ) {
				$cats[ $id ] = array(
					'id'     => $id,
					'label'  => $label,
					'count'  => 0,
					'checks' => array(),
				);
			}
			foreach ( $rows as $row ) {
				$cat = isset( $cats[ $row['category'] ] ) ? $row['category'] : 'links';
				++$cats[ $cat ]['count'];
				$check = $row['check_id'];
				if ( ! isset( $cats[ $cat ]['checks'][ $check ] ) ) {
					$text                             = self::check_text( $check );
					$cats[ $cat ]['checks'][ $check ] = array(
						'id'       => $check,
						'label'    => $text['label'],
						'fix'      => $text['fix'],
						'severity' => $row['severity'],
						'count'    => 0,
						'examples' => array(),
					);
				}
				++$cats[ $cat ]['checks'][ $check ]['count'];
				if ( count( $cats[ $cat ]['checks'][ $check ]['examples'] ) < 5 ) {
					$details                                        = json_decode( (string) $row['details'], true );
					$post_id                                        = (int) $row['post_id'];
					$cats[ $cat ]['checks'][ $check ]['examples'][] = array(
						'issue_id'  => (int) $row['id'],
						'url'       => esc_url_raw( (string) $row['url'] ),
						'post_id'   => $post_id,
						'title'     => $post_id ? get_the_title( $post_id ) : '',
						'edit_link' => $post_id && current_user_can( 'edit_post', $post_id ) ? (string) get_edit_post_link( $post_id, 'raw' ) : '',
						'details'   => is_array( $details ) ? $details : array(),
					);
				}
			}
			foreach ( $cats as $id => $cat ) {
				$cats[ $id ]['checks'] = array_values( $cat['checks'] );
			}
			$out['categories'] = array_values( $cats );
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Start a scan.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function scan( WP_REST_Request $request ) {
		return $this->respond( Scan::start( 'database' === $request->get_param( 'mode' ) ? 'database' : 'auto' ) );
	}

	/**
	 * Pages read from the database that the admin's browser can check (5 at a time).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function next_pages( WP_REST_Request $request ) {
		global $wpdb;
		$table = Schema::table( Schema::PAGES );
		$after = absint( $request->get_param( 'after' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, url FROM %i WHERE scan_id = %d AND source = 'database' AND id > %d ORDER BY id LIMIT 5", $table, (int) $request['id'], $after ), ARRAY_A );
		return rest_ensure_response(
			array_map(
				static function ( $row ) {
					return array(
						'id'  => (int) $row['id'],
						'url' => esc_url_raw( $row['url'] ),
					);
				},
				$rows
			)
		);
	}

	/**
	 * A page the admin's browser fetched (same site only): parse and save.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function browser_page( WP_REST_Request $request ) {
		global $wpdb;
		$table = Schema::table( Schema::PAGES );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$page = $wpdb->get_row( $wpdb->prepare( 'SELECT id, url FROM %i WHERE id = %d AND scan_id = %d', $table, absint( $request->get_param( 'page_id' ) ), (int) $request['id'] ), ARRAY_A );
		if ( ! $page ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This page is not part of the scan.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$status = absint( $request->get_param( 'status' ) );
		$html   = substr( (string) $request->get_param( 'html' ), 0, 1000000 );
		$final  = esc_url_raw( (string) $request->get_param( 'final_url' ) );
		Scan::save_page(
			(int) $page['id'],
			array(
				'source'      => 'browser',
				'status_code' => $status,
				'redirect_to' => '' !== $final && untrailingslashit( $final ) !== untrailingslashit( $page['url'] ) ? $final : '',
				'facts'       => $status >= 200 && $status < 300 ? Page_Parser::parse( $html, $page['url'], home_url( '/' ) ) : array(),
			)
		);
		return rest_ensure_response( array( 'saved' => (int) $page['id'] ) );
	}

	/**
	 * Rebuild a scan's issues (after browser checks).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rebuild( WP_REST_Request $request ) {
		$scan_id = (int) $request['id'];
		$result  = Issue_Builder::build( $scan_id );
		Scan::update(
			$scan_id,
			array(
				'score'   => $result['score'],
				'summary' => wp_json_encode( array_merge( Scan::summary( $scan_id ), array( 'counts' => $result['counts'] ) ) ),
			)
		);
		return rest_ensure_response( $result );
	}

	/**
	 * One-click fixes for issues that have a safe automatic fix (noindex).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function fix( WP_REST_Request $request ) {
		global $wpdb;
		$table = Schema::table( Schema::ISSUES );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$issue = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, (int) $request['id'] ), ARRAY_A );
		if ( ! $issue || 'noindex' !== $issue['check_id'] || ! current_user_can( 'edit_post', (int) $issue['post_id'] ) ) {
			return new WP_Error( 'qcld_wpbot_seo_no_fix', __( 'This issue has no automatic fix; use the steps shown.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$change = Seo_Bridge::set( (int) $issue['post_id'], 'noindex', false, 'fix' );
		return $this->respond( is_wp_error( $change ) ? $change : array( 'change_id' => (int) $change ) );
	}

	/**
	 * Redirect list.
	 *
	 * @return \WP_REST_Response
	 */
	public function redirects() {
		return rest_ensure_response(
			array(
				'items'   => Redirects::all(),
				'active'  => Redirects::active(),
				'handled' => Seo_Plugins::handles_redirects(),
			)
		);
	}

	/**
	 * Add a redirect.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save_redirect( WP_REST_Request $request ) {
		$id = Redirects::save( (string) $request->get_param( 'source' ), (string) $request->get_param( 'target' ), (int) $request->get_param( 'code' ) );
		if ( ! is_wp_error( $id ) && $request->get_param( 'not_found_id' ) ) {
			Not_Found_Log::delete( absint( $request->get_param( 'not_found_id' ) ) );
		}
		return $this->respond( is_wp_error( $id ) ? $id : array( 'id' => $id ) );
	}

	/**
	 * Delete a redirect.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_redirect( WP_REST_Request $request ) {
		Redirects::delete( (int) $request['id'] );
		return rest_ensure_response( array( 'deleted' => (int) $request['id'] ) );
	}

	/**
	 * 404 log.
	 *
	 * @return \WP_REST_Response
	 */
	public function not_found() {
		return rest_ensure_response( Not_Found_Log::query() );
	}

	/**
	 * Remove a 404 entry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_not_found( WP_REST_Request $request ) {
		Not_Found_Log::delete( (int) $request['id'] );
		return rest_ensure_response( array( 'deleted' => (int) $request['id'] ) );
	}

	/**
	 * PageSpeed check for a page of this site.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function pagespeed( WP_REST_Request $request ) {
		$url  = esc_url_raw( (string) $request->get_param( 'url' ) );
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' === $url || (string) wp_parse_url( $url, PHP_URL_HOST ) !== $host ) {
			return new WP_Error( 'qcld_wpbot_seo_bad_url', __( 'Choose a page of this site.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return $this->respond( Page_Speed::check( $url ) );
	}

	/**
	 * Label and fix text for a check.
	 *
	 * @param string $check Check id.
	 * @return array label, fix.
	 */
	public static function check_text( $check ) {
		$texts = array(
			'page_error'            => array( __( 'Pages that return an error', 'chatbot' ), __( 'Restore the page, or add a redirect to the closest page.', 'chatbot' ) ),
			'redirect_loop'         => array( __( 'Redirect loops', 'chatbot' ), __( 'Remove one of the redirects so the address ends on a page.', 'chatbot' ) ),
			'redirect_chain'        => array( __( 'Redirect chains', 'chatbot' ), __( 'Point the first redirect straight to the final page.', 'chatbot' ) ),
			'link_to_redirect'      => array( __( 'Links to redirected addresses', 'chatbot' ), __( 'Update the link to the final address.', 'chatbot' ) ),
			'noindex'               => array( __( 'Pages hidden from Google (noindex)', 'chatbot' ), __( 'If the page should be found, remove noindex (one click; saved in your SEO plugin).', 'chatbot' ) ),
			'not_indexed'           => array( __( 'Important pages not indexed', 'chatbot' ), __( 'Check the reason Google gives, fix it, then ask for indexing in Search Console.', 'chatbot' ) ),
			'canonical_elsewhere'   => array( __( 'Canonical points to another page', 'chatbot' ), __( 'If the page should rank itself, set its canonical to its own address.', 'chatbot' ) ),
			'canonical_missing'     => array( __( 'Missing canonical', 'chatbot' ), __( 'Your SEO plugin or WordPress should print one; check the theme.', 'chatbot' ) ),
			'duplicate_title'       => array( __( 'Duplicate titles', 'chatbot' ), __( 'Give each page its own title (Fixes > Fix with AI).', 'chatbot' ) ),
			'duplicate_description' => array( __( 'Duplicate descriptions', 'chatbot' ), __( 'Give each page its own description (Fixes > Fix with AI).', 'chatbot' ) ),
			'broken_internal'       => array( __( 'Broken links to your own pages', 'chatbot' ), __( 'Fix the link in the page, or add a redirect.', 'chatbot' ) ),
			'broken_external'       => array( __( 'Broken links to other sites', 'chatbot' ), __( 'Update or remove the link.', 'chatbot' ) ),
			'orphan'                => array( __( 'Orphan pages', 'chatbot' ), __( 'Link to them from related pages (Fixes > link suggestions).', 'chatbot' ) ),
			'deep_page'             => array( __( 'Pages too deep', 'chatbot' ), __( 'Link to them from the menu, a category or a popular page.', 'chatbot' ) ),
			'schema_fields'         => array( __( 'Structured data missing required fields', 'chatbot' ), __( 'Fill the missing fields in your SEO plugin or the page.', 'chatbot' ) ),
			'schema_none'           => array( __( 'No structured data', 'chatbot' ), sprintf( /* translators: %s: product name, for example WPBot. */ __( '%s prints basic schema when no SEO plugin does; check the page after the next scan.', 'chatbot' ), \QuantumCloud\SeoAiSearch\Module::product() ) ),
			'mixed_content'         => array( __( 'Mixed content', 'chatbot' ), __( 'Change the http:// addresses of images and scripts to https://.', 'chatbot' ) ),
			'hreflang'              => array( __( 'Language tags (hreflang)', 'chatbot' ), __( 'Use valid language codes and include the page itself in its list.', 'chatbot' ) ),
			'robots_blocks_all'     => array( __( 'Search engines are blocked', 'chatbot' ), __( 'Settings > Reading: untick "Discourage search engines", and check robots.txt.', 'chatbot' ) ),
			'sitemap_missing'       => array( __( 'No XML sitemap found', 'chatbot' ), __( 'Turn on the sitemap in your SEO plugin (WordPress has one at /wp-sitemap.xml).', 'chatbot' ) ),
			'sitemap_not_submitted' => array( __( 'Sitemap not submitted to Search Console', 'chatbot' ), __( 'Add your sitemap address in Search Console > Sitemaps.', 'chatbot' ) ),
			'sitemap_errors'        => array( __( 'Sitemap errors in Search Console', 'chatbot' ), __( 'Open the sitemap report in Search Console to see the errors.', 'chatbot' ) ),
			'no_https'              => array( __( 'Site not on HTTPS', 'chatbot' ), __( 'Ask your host for a free certificate, then change the site address to https:// in Settings > General.', 'chatbot' ) ),
			'http_not_redirected'   => array( __( 'HTTP does not redirect to HTTPS', 'chatbot' ), __( 'Ask your host to redirect http:// to https://.', 'chatbot' ) ),
			'cwv_poor'              => array( __( 'Slow for real visitors (Core Web Vitals)', 'chatbot' ), __( 'See the speed fix list in Site Insight; images and caching are the usual causes.', 'chatbot' ) ),
			'speed_low'             => array( __( 'Low speed score', 'chatbot' ), __( 'Compress images, use caching, and remove unused plugins.', 'chatbot' ) ),
		);
		$text  = isset( $texts[ $check ] ) ? $texts[ $check ] : array( $check, '' );
		/**
		 * Label and fix for technical checks added by extensions.
		 *
		 * @param array  $text  [ label, fix ].
		 * @param string $check Check id.
		 */
		$text = (array) apply_filters( 'qcld_wpbot_seo/technical_check_text', $text, $check );
		return array(
			'label' => (string) $text[0],
			'fix'   => (string) $text[1],
		);
	}
}
