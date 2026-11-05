<?php
/**
 * Turns a scan's page facts and site facts into issues by category, each
 * with examples and a fix, and a health score.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Technical;

use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;
use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Issue builder.
 */
final class Issue_Builder {

	/**
	 * Categories in display order.
	 *
	 * @return array id => label.
	 */
	public static function categories() {
		return array(
			'indexing'   => __( 'Indexing', 'chatbot' ),
			'crawl'      => __( 'Crawl', 'chatbot' ),
			'links'      => __( 'Links', 'chatbot' ),
			'redirects'  => __( 'Redirects', 'chatbot' ),
			'duplicates' => __( 'Duplicates', 'chatbot' ),
			'structure'  => __( 'Structure', 'chatbot' ),
			'schema'     => __( 'Structured data', 'chatbot' ),
			'speed'      => __( 'Speed and mobile', 'chatbot' ),
			'security'   => __( 'Security', 'chatbot' ),
		);
	}

	/**
	 * Required schema properties by type (Google's rich result basics).
	 *
	 * @return array
	 */
	public static function schema_rules() {
		$rules = array(
			'Article'        => array( 'headline', 'datePublished', 'author', 'image' ),
			'BlogPosting'    => array( 'headline', 'datePublished', 'author', 'image' ),
			'NewsArticle'    => array( 'headline', 'datePublished', 'author', 'image' ),
			'Product'        => array( 'name', array( 'offers', 'review', 'aggregateRating' ) ),
			'FAQPage'        => array( 'mainEntity' ),
			'LocalBusiness'  => array( 'name', 'address' ),
			'BreadcrumbList' => array( 'itemListElement' ),
		);
		return (array) apply_filters( 'qcld_wpbot_seo/schema_rules', $rules );
	}

	/**
	 * Build issues for a scan and return the score and counts.
	 *
	 * @param int $scan_id Scan id.
	 * @return array score, counts (category => n), issues (total).
	 */
	public static function build( $scan_id ) {
		global $wpdb;
		$table = Schema::table( Schema::PAGES );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE scan_id = %d AND checked = 1", $scan_id ), ARRAY_A );
		$pages = array();
		foreach ( $rows as $row ) {
			$facts                = json_decode( (string) $row['facts'], true );
			$row['facts']         = is_array( $facts ) ? $facts : array();
			$pages[ $row['url'] ] = $row;
		}
		$summary = Scan::summary( $scan_id );
		$issues  = self::issues( $pages, isset( $summary['site'] ) ? $summary['site'] : array(), isset( $summary['external_broken'] ) ? $summary['external_broken'] : array() );
		/**
		 * Technical issues after the built-in checks (the Pro module adds its own).
		 *
		 * @param array $issues  List of check_id, category, severity, url, post_id, details.
		 * @param array $pages   Page facts by URL.
		 * @param array $summary Scan summary.
		 */
		$issues = (array) apply_filters( 'qcld_wpbot_seo/technical_issues', $issues, $pages, $summary );

		$issue_table = Schema::table( Schema::ISSUES );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$issue_table} WHERE scan_id = %d", $scan_id ) );
		$counts  = array_fill_keys( array_keys( self::categories() ), 0 );
		$weight  = array(
			'high'   => 6,
			'medium' => 2,
			'low'    => 0.5,
		);
		$penalty = array();
		foreach ( $issues as $issue ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$wpdb->insert(
				$issue_table,
				array(
					'scan_id'  => $scan_id,
					'check_id' => $issue['check_id'],
					'category' => $issue['category'],
					'severity' => $issue['severity'],
					'url'      => isset( $issue['url'] ) ? $issue['url'] : '',
					'post_id'  => isset( $issue['post_id'] ) ? (int) $issue['post_id'] : 0,
					'details'  => wp_json_encode( isset( $issue['details'] ) ? $issue['details'] : array() ),
				)
			);
			if ( isset( $counts[ $issue['category'] ] ) ) {
				++$counts[ $issue['category'] ];
			}
			// Each check counts at most 4 times, so one widespread problem cannot sink the score alone.
			$key             = $issue['check_id'];
			$penalty[ $key ] = min( 4, ( isset( $penalty[ $key ] ) ? $penalty[ $key ] : 0 ) + 1 ) * ( isset( $weight[ $issue['severity'] ] ) ? $weight[ $issue['severity'] ] : 1 );
		}
		$score = max( 0, (int) round( 100 - array_sum( $penalty ) ) );
		return array(
			'score'  => $score,
			'counts' => $counts,
			'issues' => count( $issues ),
		);
	}

	/**
	 * Issues from page and site facts (pure logic, unit-tested).
	 *
	 * @param array $pages    url => row (status_code, redirect_to, post_id, source, facts).
	 * @param array $site     Site facts (Site_Checks::run()).
	 * @param array $external Broken outbound links.
	 * @return array
	 */
	public static function issues( array $pages, array $site, array $external ) {
		$out   = array();
		$add   = static function ( $check, $category, $severity, $url = '', $post_id = 0, $details = array() ) use ( &$out ) {
			$out[] = array(
				'check_id' => $check,
				'category' => $category,
				'severity' => $severity,
				'url'      => $url,
				'post_id'  => (int) $post_id,
				'details'  => $details,
			);
		};
		$graph = self::graph( $pages );
		$home  = function_exists( 'home_url' ) ? home_url( '/' ) : '';
		$seen  = array(
			'title'       => array(),
			'description' => array(),
		);
		foreach ( $pages as $url => $page ) {
			$facts = $page['facts'];
			$code  = (int) $page['status_code'];
			$post  = (int) $page['post_id'];
			if ( $code >= 400 || 310 === $code ) {
				$add( 310 === $code ? 'redirect_loop' : 'page_error', 310 === $code ? 'redirects' : 'links', 'high', $url, $post, array( 'status' => $code ) );
				continue;
			}
			if ( ! empty( $facts['chain'] ) && count( $facts['chain'] ) > 1 ) {
				$add( 'redirect_chain', 'redirects', 'medium', $url, $post, array( 'chain' => $facts['chain'] ) );
			}
			if ( ! empty( $facts['noindex'] ) && $post ) {
				$add( 'noindex', 'indexing', 'high', $url, $post );
			}
			foreach ( array( 'title', 'description' ) as $field ) {
				$value = isset( $facts[ $field ] ) ? strtolower( trim( (string) $facts[ $field ] ) ) : '';
				if ( '' !== $value ) {
					$seen[ $field ][ $value ][] = $url;
				}
			}
			if ( ! empty( $facts['canonical'] ) && untrailingslashit( strtok( $facts['canonical'], '?' ) ) !== untrailingslashit( strtok( $url, '?' ) ) && false === strpos( $url, '?' ) ) {
				$add( 'canonical_elsewhere', 'duplicates', 'medium', $url, $post, array( 'canonical' => $facts['canonical'] ) );
			} elseif ( isset( $facts['canonical'] ) && '' === $facts['canonical'] && 'fetch' === $page['source'] && $post ) {
				$add( 'canonical_missing', 'duplicates', 'low', $url, $post );
			}
			foreach ( $facts['internal'] ?? array() as $target ) {
				if ( isset( $pages[ $target ] ) && (int) $pages[ $target ]['status_code'] >= 400 ) {
					$add( 'broken_internal', 'links', 'high', $url, $post, array( 'target' => $target ) );
				} elseif ( isset( $pages[ $target ] ) && '' !== (string) $pages[ $target ]['redirect_to'] ) {
					$add(
						'link_to_redirect',
						'redirects',
						'low',
						$url,
						$post,
						array(
							'target' => $target,
							'to'     => $pages[ $target ]['redirect_to'],
						)
					);
				}
			}
			if ( $post && isset( $graph['inbound'][ $url ] ) && 0 === $graph['inbound'][ $url ] && untrailingslashit( $url ) !== untrailingslashit( $home ) ) {
				$add( 'orphan', 'structure', 'medium', $url, $post );
			}
			if ( isset( $graph['depth'][ $url ] ) && $graph['depth'][ $url ] > 3 ) {
				$add( 'deep_page', 'structure', 'low', $url, $post, array( 'depth' => $graph['depth'][ $url ] ) );
			}
			if ( is_array( $facts['schema'] ?? null ) ) {
				foreach ( $facts['schema'] as $node ) {
					$missing = self::schema_missing( $node );
					if ( array() !== $missing ) {
						$add(
							'schema_fields',
							'schema',
							'medium',
							$url,
							$post,
							array(
								'type'    => $node['type'],
								'missing' => $missing,
							)
						);
					}
				}
				if ( array() === $facts['schema'] && $post && ! Seo_Plugins::handles_head() ) {
					$add( 'schema_none', 'schema', 'low', $url, $post );
				}
			}
			if ( ! empty( $facts['mixed'] ) ) {
				$add( 'mixed_content', 'security', 'medium', $url, $post, array( 'resources' => array_slice( $facts['mixed'], 0, 5 ) ) );
			}
			if ( ! empty( $facts['hreflang'] ) ) {
				$langs = array_column( $facts['hreflang'], 'lang' );
				$bad   = array_filter(
					$langs,
					static function ( $lang ) {
						return 'x-default' !== $lang && ! preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/', $lang );
					}
				);
				$self  = in_array( untrailingslashit( $url ), array_map( 'untrailingslashit', array_column( $facts['hreflang'], 'href' ) ), true );
				if ( array() !== $bad || ! $self ) {
					$add(
						'hreflang',
						'security',
						'low',
						$url,
						$post,
						array(
							'invalid' => array_values( $bad ),
							'self'    => $self,
						)
					);
				}
			}
		}
		foreach ( $seen as $field => $values ) {
			foreach ( $values as $value => $urls ) {
				if ( count( $urls ) > 1 ) {
					$add(
						'duplicate_' . $field,
						'duplicates',
						'title' === $field ? 'medium' : 'low',
						$urls[0],
						0,
						array(
							'urls'  => array_slice( $urls, 0, 10 ),
							'value' => $value,
						)
					);
				}
			}
		}
		foreach ( $external as $link ) {
			$add( 'broken_external', 'links', 'medium', $link['sources'][0], 0, $link );
		}
		// Site facts.
		if ( ! empty( $site['robots']['blocks_all'] ) ) {
			$add( 'robots_blocks_all', 'crawl', 'high', '', 0 );
		}
		if ( isset( $site['sitemap'] ) && empty( $site['sitemap']['reachable'] ) ) {
			$add( 'sitemap_missing', 'crawl', 'medium', '', 0, $site['sitemap'] );
		}
		if ( is_array( $site['gsc_sitemaps'] ?? null ) && ! isset( $site['gsc_sitemaps']['error'] ) ) {
			if ( array() === $site['gsc_sitemaps'] ) {
				$add( 'sitemap_not_submitted', 'crawl', 'medium', '', 0 );
			}
			foreach ( $site['gsc_sitemaps'] as $map ) {
				if ( ! empty( $map['errors'] ) ) {
					$add( 'sitemap_errors', 'crawl', 'medium', $map['path'], 0, $map );
				}
			}
		}
		if ( isset( $site['https']['https'] ) && ! $site['https']['https'] ) {
			$add( 'no_https', 'security', 'high', '', 0 );
		} elseif ( isset( $site['https']['redirect'] ) && false === $site['https']['redirect'] ) {
			$add( 'http_not_redirected', 'redirects', 'high', '', 0 );
		}
		foreach ( (array) ( $site['inspection'] ?? array() ) as $url => $result ) {
			if ( 'PASS' !== $result['verdict'] ) {
				$add( 'not_indexed', 'indexing', 'high', $url, isset( $pages[ $url ] ) ? (int) $pages[ $url ]['post_id'] : 0, $result );
			}
		}
		foreach ( Page_Speed::stored() as $url => $result ) {
			if ( isset( $result['field']['overall'] ) && 'SLOW' === $result['field']['overall'] ) {
				$add( 'cwv_poor', 'speed', 'high', $url, 0, $result['field'] );
			} elseif ( isset( $result['lab']['score'] ) && $result['lab']['score'] < 50 ) {
				$add( 'speed_low', 'speed', 'medium', $url, 0, $result['lab'] );
			}
		}
		return $out;
	}

	/**
	 * Inbound internal links and click depth from the home page.
	 *
	 * @param array $pages Pages by URL.
	 * @return array inbound (url => n), depth (url => clicks).
	 */
	public static function graph( array $pages ) {
		$inbound = array_fill_keys( array_keys( $pages ), 0 );
		$links   = array();
		foreach ( $pages as $url => $page ) {
			foreach ( $page['facts']['internal'] ?? array() as $target ) {
				if ( isset( $inbound[ $target ] ) && $target !== $url ) {
					++$inbound[ $target ];
					$links[ $url ][] = $target;
				}
			}
		}
		$depth = array();
		$home  = function_exists( 'home_url' ) ? home_url( '/' ) : '';
		if ( isset( $pages[ $home ] ) && 'fetch' === $pages[ $home ]['source'] ) {
			$depth[ $home ] = 0;
			$queue          = array( $home );
			while ( $queue ) {
				$url = array_shift( $queue );
				foreach ( $links[ $url ] ?? array() as $target ) {
					if ( ! isset( $depth[ $target ] ) ) {
						$depth[ $target ] = $depth[ $url ] + 1;
						$queue[]          = $target;
					}
				}
			}
		}
		return array(
			'inbound' => $inbound,
			'depth'   => $depth,
		);
	}

	/**
	 * Required properties missing on a schema node.
	 *
	 * @param array $node type, keys.
	 * @return string[]
	 */
	public static function schema_missing( array $node ) {
		$rules = self::schema_rules();
		$type  = isset( $node['type'] ) ? (string) $node['type'] : '';
		if ( ! isset( $rules[ $type ] ) ) {
			return array();
		}
		$missing = array();
		foreach ( $rules[ $type ] as $rule ) {
			$options = (array) $rule;
			if ( array() === array_intersect( $options, (array) $node['keys'] ) ) {
				$missing[] = implode( '|', $options );
			}
		}
		return $missing;
	}
}
