<?php
/**
 * Technical scan in WPBot Core's job queue (short steps, shared hosting):
 * collect pages, fetch them from the site itself in small batches (or read
 * them from the database when the host blocks loopback requests), check
 * site-wide files, check outbound links politely, then build issues.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Technical;

use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Onpage\Audit;
use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\SeoAiSearch\Storage\Schema;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Scan runs.
 */
final class Scan {

	/**
	 * Most pages per scan.
	 */
	const MAX_PAGES = 2000;

	/**
	 * Outbound links checked per scan.
	 */
	const MAX_EXTERNAL = 200;

	/**
	 * Job step handlers.
	 *
	 * @return array
	 */
	public static function handlers() {
		return array(
			'seo_tech_prepare' => array( __CLASS__, 'step_prepare' ),
			'seo_tech_fetch'   => array( __CLASS__, 'step_fetch' ),
			'seo_tech_site'    => array( __CLASS__, 'step_site' ),
			'seo_tech_links'   => array( __CLASS__, 'step_links' ),
			'seo_tech_finish'  => array( __CLASS__, 'step_finish' ),
		);
	}

	/**
	 * Start a scan (one at a time).
	 *
	 * @param string $mode auto|database.
	 * @return array|WP_Error scan_id, job_id.
	 */
	public static function start( $mode = 'auto' ) {
		$running = self::running();
		if ( $running ) {
			return $running;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->insert(
			Schema::table( Schema::SCANS ),
			array(
				'mode'       => 'database' === $mode ? 'database' : 'loopback',
				'status'     => 'running',
				'started_at' => current_time( 'mysql', true ),
			)
		);
		$scan_id = (int) $wpdb->insert_id;
		$job     = Job_Store::create(
			Module::ID,
			'tech_scan',
			array(
				'title'   => __( 'Technical SEO scan', 'chatbot' ),
				'payload' => array( 'scan_id' => $scan_id ),
			),
			array(
				array(
					'kind'  => 'seo_tech_prepare',
					'label' => __( 'List pages', 'chatbot' ),
					'input' => array( 'scan_id' => $scan_id ),
				),
			)
		);
		if ( ! $job ) {
			return new WP_Error( 'qcld_wpbot_seo_job', __( 'The scan could not be started.', 'chatbot' ) );
		}
		self::update( $scan_id, array( 'job_id' => (int) $job ) );
		return array(
			'scan_id' => $scan_id,
			'job_id'  => (int) $job,
		);
	}

	/**
	 * Running scan, or null.
	 *
	 * @return array|null scan_id, job_id.
	 */
	public static function running() {
		$scan = self::latest( 'running' );
		if ( ! $scan ) {
			return null;
		}
		$job = Job_Store::get( $scan['job_id'] );
		if ( $job && in_array( $job['status'], array( 'queued', 'running', 'paused' ), true ) ) {
			return array(
				'scan_id' => $scan['id'],
				'job_id'  => $scan['job_id'],
			);
		}
		self::update( $scan['id'], array( 'status' => 'failed' ) );
		return null;
	}

	/**
	 * Step: list pages and plan the fetch batches.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function step_prepare( array $step, array $job ) {
		global $wpdb;
		$scan_id = (int) $step['input']['scan_id'];
		$urls    = array( home_url( '/' ) => (int) get_option( 'page_on_front' ) );
		$ids     = get_posts(
			array(
				'post_type'      => Audit::post_types(),
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => self::MAX_PAGES, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Ids only; fetched in batches.
				'orderby'        => 'modified',
			)
		);
		foreach ( $ids as $id ) {
			$urls[ (string) get_permalink( $id ) ] = (int) $id;
		}
		foreach ( get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
				'number'     => 30,
			)
		) as $term ) {
			$link = get_term_link( $term );
			if ( is_string( $link ) ) {
				$urls[ $link ] = 0;
			}
		}
		$excluded = (array) Settings::get( 'excluded_paths' );
		$table    = Schema::table( Schema::PAGES );
		foreach ( array_slice( $urls, 0, self::MAX_PAGES, true ) as $url => $post_id ) {
			if ( self::excluded( $url, $excluded ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
			$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (scan_id,url_hash,url,post_id) VALUES (%d,%s,%s,%d)', $table, $scan_id, md5( $url ), $url, $post_id ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$page_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE scan_id = %d ORDER BY id', $table, $scan_id ) ) );
		$scan     = self::get( $scan_id );
		$mode     = $scan['mode'];
		if ( 'loopback' === $mode && is_wp_error( self::fetch( home_url( '/' ) ) ) ) {
			// The host blocks requests to itself: read pages from the database instead.
			$mode = 'database';
		}
		self::update(
			$scan_id,
			array(
				'mode'  => $mode,
				'pages' => count( $page_ids ),
			)
		);
		$steps = array();
		foreach ( array_chunk( $page_ids, max( 1, (int) Settings::get( 'scan_batch' ) ) ) as $index => $chunk ) {
			$steps[] = array(
				'kind'  => 'seo_tech_fetch',
				/* translators: %d: batch number. */
				'label' => sprintf( __( 'Check pages, batch %d', 'chatbot' ), $index + 1 ),
				'input' => array(
					'scan_id' => $scan_id,
					'ids'     => $chunk,
				),
			);
		}
		foreach ( array(
			'seo_tech_site'   => __( 'Robots.txt, sitemaps, HTTPS and indexing', 'chatbot' ),
			'seo_tech_links'  => __( 'Check links to other sites', 'chatbot' ),
			'seo_tech_finish' => __( 'Build the report', 'chatbot' ),
		) as $kind => $label ) {
			$steps[] = array(
				'kind'  => $kind,
				'label' => $label,
				'input' => array( 'scan_id' => $scan_id ),
			);
		}
		Job_Store::add_steps( (int) $job['id'], $steps );
		return array(
			'pages' => count( $page_ids ),
			'mode'  => $mode,
		);
	}

	/**
	 * Step: fetch (or read) one batch of pages.
	 *
	 * @param array $step Step.
	 * @return array
	 */
	public static function step_fetch( array $step ) {
		global $wpdb;
		$scan_id = (int) $step['input']['scan_id'];
		$scan    = self::get( $scan_id );
		$table   = Schema::table( Schema::PAGES );
		$done    = 0;
		foreach ( (array) $step['input']['ids'] as $page_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
			$page = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND scan_id = %d', $table, (int) $page_id, $scan_id ), ARRAY_A );
			if ( ! $page || (int) $page['checked'] ) {
				continue;
			}
			$result = 'database' === $scan['mode'] ? null : self::fetch( $page['url'] );
			if ( null === $result || is_wp_error( $result ) ) {
				$row = self::from_database( $page );
			} else {
				$row = array(
					'source'      => 'fetch',
					'status_code' => $result['code'],
					'redirect_to' => $result['location'],
					'facts'       => $result['code'] >= 200 && $result['code'] < 300 ? Page_Parser::parse( $result['body'], $page['url'], home_url( '/' ) ) : array( 'chain' => $result['chain'] ),
				);
				if ( '' !== $result['location'] ) {
					$row['facts']['chain'] = $result['chain'];
				}
			}
			self::save_page( (int) $page['id'], $row );
			++$done;
		}
		return array( 'pages' => $done );
	}

	/**
	 * Save one page's facts (also used by the browser checks).
	 *
	 * @param int   $page_id Page row id.
	 * @param array $row     source, status_code, redirect_to, facts.
	 * @return void
	 */
	public static function save_page( $page_id, array $row ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->update(
			Schema::table( Schema::PAGES ),
			array(
				'source'      => isset( $row['source'] ) ? $row['source'] : 'fetch',
				'status_code' => (int) $row['status_code'],
				'redirect_to' => isset( $row['redirect_to'] ) ? (string) $row['redirect_to'] : '',
				'facts'       => wp_json_encode( isset( $row['facts'] ) ? $row['facts'] : array() ),
				'checked'     => 1,
			),
			array( 'id' => (int) $page_id ),
			array( '%s', '%d', '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Facts from the database when a page cannot be fetched.
	 *
	 * @param array $page Page row.
	 * @return array
	 */
	public static function from_database( array $page ) {
		$post_id = (int) $page['post_id'];
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post ) {
			return array(
				'source'      => 'database',
				'status_code' => 200,
				'facts'       => array(),
			);
		}
		$fields  = Seo_Bridge::read( $post_id );
		$content = (string) $post->post_content;
		$facts   = Page_Parser::parse( '<html><head></head><body>' . $content . '</body></html>', $page['url'], home_url( '/' ) );
		$facts   = array_merge(
			$facts,
			array(
				'title'       => '' !== $fields['title'] ? $fields['title'] : $post->post_title,
				'description' => (string) $fields['description'],
				'canonical'   => (string) $fields['canonical'],
				'noindex'     => (bool) $fields['noindex'],
				'h1'          => 1 + (int) preg_match_all( '#<h1\b#i', $content ),
				'schema'      => null,
			)
		);
		return array(
			'source'      => 'database',
			'status_code' => 'publish' === $post->post_status ? 200 : 404,
			'facts'       => $facts,
		);
	}

	/**
	 * Fetch a URL of this site, following up to 5 redirects by hand.
	 *
	 * @param string $url     URL.
	 * @param int    $timeout Seconds per request.
	 * @return array|WP_Error code, body, location (final), chain[].
	 */
	public static function fetch( $url, $timeout = 10 ) {
		$chain = array();
		$next  = $url;
		for ( $hop = 0; $hop <= 5; $hop++ ) {
			$response = wp_remote_get(
				$next,
				array(
					'timeout'     => max( 1, (int) $timeout ),
					'redirection' => 0,
					'user-agent'  => self::user_agent(),
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter for requests to this site.
					'headers'     => array( 'Accept' => 'text/html' ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return 0 === $hop ? $response : array(
					'code'     => 0,
					'body'     => '',
					'location' => $next,
					'chain'    => $chain,
				);
			}
			$code     = (int) wp_remote_retrieve_response_code( $response );
			$location = (string) wp_remote_retrieve_header( $response, 'location' );
			if ( $code >= 300 && $code < 400 && '' !== $location ) {
				$location = Page_Parser::absolute( $location, $next );
				$chain[]  = array(
					'from' => $next,
					'to'   => $location,
					'code' => $code,
				);
				if ( in_array( $location, array_column( $chain, 'from' ), true ) ) {
					return array(
						'code'     => 310,
						'body'     => '',
						'location' => $location,
						'chain'    => $chain,
					);
				}
				$next = $location;
				continue;
			}
			return array(
				'code'     => $code,
				'body'     => substr( (string) wp_remote_retrieve_body( $response ), 0, 2000000 ),
				'location' => array() === $chain ? '' : $next,
				'chain'    => $chain,
			);
		}
		return array(
			'code'     => 310,
			'body'     => '',
			'location' => $next,
			'chain'    => $chain,
		);
	}

	/**
	 * Step: robots.txt, sitemaps, HTTPS, and URL Inspection for top pages.
	 *
	 * @param array $step Step.
	 * @return array
	 */
	public static function step_site( array $step ) {
		$scan_id = (int) $step['input']['scan_id'];
		$site    = Site_Checks::run();
		$summary = self::summary( $scan_id );
		self::update( $scan_id, array( 'summary' => wp_json_encode( array_merge( $summary, array( 'site' => $site ) ) ) ) );
		return array( 'site' => array_keys( $site ) );
	}

	/**
	 * Step: check outbound links politely (HEAD, short timeouts, capped).
	 *
	 * @param array $step Step.
	 * @return array
	 */
	public static function step_links( array $step ) {
		global $wpdb;
		$scan_id = (int) $step['input']['scan_id'];
		$table   = Schema::table( Schema::PAGES );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT url, facts FROM %i WHERE scan_id = %d AND checked = 1', $table, $scan_id ), ARRAY_A );
		$targets = array();
		foreach ( (array) $rows as $row ) {
			$facts = json_decode( (string) $row['facts'], true );
			foreach ( isset( $facts['external'] ) ? (array) $facts['external'] : array() as $link ) {
				$targets[ $link ][] = $row['url'];
			}
		}
		$broken  = array();
		$checked = 0;
		foreach ( array_slice( $targets, 0, self::MAX_EXTERNAL, true ) as $link => $sources ) {
			$status = Link_Check::status( $link );
			++$checked;
			if ( $status >= 400 || 0 === $status ) {
				$broken[] = array(
					'url'     => $link,
					'status'  => $status,
					'sources' => array_slice( array_unique( $sources ), 0, 5 ),
				);
			}
		}
		$summary                     = self::summary( $scan_id );
		$summary['external_broken']  = $broken;
		$summary['external_checked'] = $checked;
		self::update( $scan_id, array( 'summary' => wp_json_encode( $summary ) ) );
		return array(
			'checked' => $checked,
			'broken'  => count( $broken ),
		);
	}

	/**
	 * Step: link graph (depth, inbound), issues, score.
	 *
	 * @param array $step Step.
	 * @return array
	 */
	public static function step_finish( array $step ) {
		$scan_id = (int) $step['input']['scan_id'];
		$result  = Issue_Builder::build( $scan_id );
		self::update(
			$scan_id,
			array(
				'status'      => 'done',
				'score'       => $result['score'],
				'finished_at' => current_time( 'mysql', true ),
				'summary'     => wp_json_encode( array_merge( self::summary( $scan_id ), array( 'counts' => $result['counts'] ) ) ),
			)
		);
		/**
		 * A technical scan finished.
		 *
		 * @param int   $scan_id Scan id.
		 * @param array $summary score, counts.
		 */
		do_action( 'qcld_wpbot_seo/scan_completed', $scan_id, $result );
		return $result;
	}

	/**
	 * One scan.
	 *
	 * @param int $scan_id Scan id.
	 * @return array|null
	 */
	public static function get( $scan_id ) {
		global $wpdb;
		$table = Schema::table( Schema::SCANS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, (int) $scan_id ), ARRAY_A );
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * Latest scan (optionally with a status).
	 *
	 * @param string $status Status filter.
	 * @return array|null
	 */
	public static function latest( $status = '' ) {
		global $wpdb;
		$table = Schema::table( Schema::SCANS );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = '' === $status
			? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 1', $table ), ARRAY_A )
			: $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY id DESC LIMIT 1', $table, $status ), ARRAY_A );
		// phpcs:enable
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * Update a scan row.
	 *
	 * @param int   $scan_id Scan id.
	 * @param array $fields  Fields.
	 * @return void
	 */
	public static function update( $scan_id, array $fields ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->update( Schema::table( Schema::SCANS ), $fields, array( 'id' => (int) $scan_id ) );
	}

	/**
	 * Stored summary of a scan.
	 *
	 * @param int $scan_id Scan id.
	 * @return array
	 */
	public static function summary( $scan_id ) {
		$scan = self::get( $scan_id );
		return $scan ? $scan['summary'] : array();
	}

	/**
	 * Identify WPBot's scanner with the site URL.
	 *
	 * @return string
	 */
	public static function user_agent() {
		return \QuantumCloud\SeoAiSearch\Module::agent_token() . '-SEO-Scan/1.0 (+' . home_url( '/' ) . ')';
	}

	/**
	 * Whether a URL matches an excluded path (with * wildcards).
	 *
	 * @param string   $url      URL.
	 * @param string[] $excluded Paths.
	 * @return bool
	 */
	public static function excluded( $url, array $excluded ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		foreach ( $excluded as $pattern ) {
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( (string) $pattern, '#' ) ) . '#i';
			if ( '' !== $pattern && preg_match( $regex, $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Scan row for screens.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$summary = json_decode( (string) $row['summary'], true );
		return array(
			'id'          => (int) $row['id'],
			'job_id'      => (int) $row['job_id'],
			'mode'        => (string) $row['mode'],
			'status'      => (string) $row['status'],
			'pages'       => (int) $row['pages'],
			'score'       => null === $row['score'] ? null : (int) $row['score'],
			'summary'     => is_array( $summary ) ? $summary : array(),
			'started_at'  => (string) $row['started_at'],
			'finished_at' => (string) $row['finished_at'],
		);
	}
}
