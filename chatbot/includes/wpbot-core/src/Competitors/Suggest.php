<?php
/**
 * AI-suggested competitors (round 2, R2). The module's AI (with web search
 * when the model can search) names sites that compete for the same customers
 * or readers; other modules can add candidates. Every candidate is verified
 * before it is shown: the site must load, must not be the site itself, a
 * marketplace, directory, social network or large general site, and must not
 * already be in the list. Nothing is saved here: the owner ticks and saves.
 *
 * The owner can say what to compete on (a product, service or topic) and
 * which kind of competitor to look for (local businesses, online shops and
 * services, blogs and content sites, or any); both are optional.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Competitors;

use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;
use QuantumCloud\WPBotCore\AI\Request;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Competitor suggestions.
 */
final class Suggest {

	/**
	 * Large general sites that are never a site's direct competitor (filter: qcld_wpbot_core/competitor_skip_hosts).
	 */
	const SKIP = array( 'amazon', 'ebay', 'etsy', 'walmart', 'aliexpress', 'alibaba', 'temu', 'daraz', 'flipkart', 'wikipedia', 'wikihow', 'facebook', 'instagram', 'twitter', 'x', 'linkedin', 'pinterest', 'tiktok', 'youtube', 'reddit', 'quora', 'medium', 'google', 'bing', 'yahoo', 'apple', 'microsoft', 'yelp', 'tripadvisor', 'trustpilot', 'yellowpages', 'craigslist', 'booking', 'expedia', 'airbnb', 'indeed', 'glassdoor', 'github', 'wordpress', 'blogspot', 'wix', 'shopify', 'bbc', 'cnn', 'nytimes', 'forbes' );

	/**
	 * Kinds of competitor the owner can ask for.
	 */
	const KINDS = array( 'any', 'local', 'online', 'content' );

	/**
	 * Suggest competitors.
	 *
	 * @param array $args module (AI settings to use, default "seo"), limit (1–15, default 8), exclude (domains),
	 *                    focus (optional: product, service or topic to compete on), kind (optional: any|local|online|content).
	 * @return array|WP_Error suggestions (domain, reason, source, title), dropped (domain, why), web_search, cost.
	 */
	public static function run( array $args = array() ) {
		$module  = sanitize_key( isset( $args['module'] ) ? (string) $args['module'] : 'seo' );
		$limit   = max( 1, min( 15, isset( $args['limit'] ) ? (int) $args['limit'] : 8 ) );
		$exclude = array_filter( array_map( array( Competitors::class, 'domain' ), isset( $args['exclude'] ) ? (array) $args['exclude'] : array() ) );
		$known   = array_merge( $exclude, Competitors::domains(), array( Competitors::own_domain() ) );
		$context = self::context() . self::brief( isset( $args['focus'] ) ? $args['focus'] : '', isset( $args['kind'] ) ? $args['kind'] : 'any' );
		$web     = self::web_search( $module );

		$response = AI_Service::generate(
			$module,
			'competitor_suggest',
			Request::make(
				'You help a website owner find their direct competitors: other websites that compete for the same customers or readers, with a similar offer, audience and place. You only name websites you are confident exist. Never name marketplaces, directories, review sites, social networks, news sites, encyclopedias or other large general websites. Answer with JSON only, exactly in the requested shape.',
				"Task: competitor_suggest\nFind up to {$limit} direct competitors of this website.\n" . $context . "\nAlready known (do not repeat): " . implode( ', ', array_filter( $known ) ) . "\n\nReturn JSON exactly in this shape:\n" . '{"competitors":[{"domain":"example.com","reason":"One short sentence: what they offer that competes, and where."}]}',
				array(
					'max_tokens' => 1200,
					'json'       => true,
					'mode'       => 'strict',
					'web_search' => $web,
				)
			),
			array( 'cache' => false )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$json       = $response->json();
		$candidates = array();
		foreach ( is_array( $json ) && isset( $json['competitors'] ) ? array_slice( (array) $json['competitors'], 0, 20 ) : array() as $row ) {
			if ( is_array( $row ) && isset( $row['domain'] ) ) {
				$candidates[] = array(
					'domain' => (string) $row['domain'],
					'reason' => isset( $row['reason'] ) ? (string) $row['reason'] : '',
					'source' => 'ai',
				);
			}
		}
		/**
		 * More competitor candidates (for example from a keyword data provider). Each is verified like the AI's.
		 *
		 * @param array[] $candidates domain, reason, source.
		 * @param array   $args       The request arguments.
		 */
		$candidates = (array) apply_filters( 'qcld_wpbot_core/competitor_candidates', $candidates, $args );

		$dropped = array();
		$checked = array();
		foreach ( $candidates as $candidate ) {
			$domain = Competitors::domain( is_array( $candidate ) && isset( $candidate['domain'] ) ? $candidate['domain'] : '' );
			if ( '' === $domain || isset( $checked[ $domain ] ) || in_array( $domain, $known, true ) ) {
				continue;
			}
			if ( self::skipped( $domain ) ) {
				$dropped[] = array(
					'domain' => $domain,
					'why'    => __( 'A marketplace, directory or large general site, not a direct competitor.', 'chatbot' ),
				);
				continue;
			}
			$source             = isset( $candidate['source'] ) ? sanitize_key( (string) $candidate['source'] ) : 'ai';
			$checked[ $domain ] = array(
				'domain' => $domain,
				'reason' => substr( sanitize_text_field( isset( $candidate['reason'] ) ? (string) $candidate['reason'] : '' ), 0, 200 ),
				'source' => in_array( $source, Competitors::SOURCES, true ) ? $source : 'ai',
			);
		}
		$results     = self::verify( array_keys( array_slice( $checked, 0, 15, true ) ) );
		$suggestions = array();
		foreach ( $results as $domain => $result ) {
			if ( true !== $result['ok'] ) {
				$dropped[] = array(
					'domain' => $domain,
					'why'    => $result['why'],
				);
				continue;
			}
			if ( count( $suggestions ) < $limit ) {
				$suggestions[] = $checked[ $domain ] + array( 'title' => $result['title'] );
			}
		}
		return array(
			'suggestions' => $suggestions,
			'dropped'     => $dropped,
			'web_search'  => $web,
			'cost'        => (float) $response->cost,
			'focus'       => self::focus( isset( $args['focus'] ) ? $args['focus'] : '' ),
			'kind'        => self::kind( isset( $args['kind'] ) ? $args['kind'] : 'any' ),
		);
	}

	/**
	 * Clean "compete on" text: plain text, at most 120 characters.
	 *
	 * @param mixed $focus Text.
	 * @return string
	 */
	public static function focus( $focus ) {
		return trim( substr( sanitize_text_field( (string) $focus ), 0, 120 ) );
	}

	/**
	 * A known kind of competitor (unknown values mean "any").
	 *
	 * @param mixed $kind Kind.
	 * @return string
	 */
	public static function kind( $kind ) {
		$kind = sanitize_key( (string) $kind );
		return in_array( $kind, self::KINDS, true ) ? $kind : 'any';
	}

	/**
	 * The owner's brief for the prompt: what to compete on and which kind of competitor.
	 *
	 * @param string $focus Product, service or topic ('' for the whole site).
	 * @param string $kind  any|local|online|content.
	 * @return string Lines starting with a line break, or ''.
	 */
	public static function brief( $focus, $kind ) {
		$focus = self::focus( $focus );
		$lines = array();
		if ( '' !== $focus ) {
			$lines[] = 'Compete on: ' . $focus . ' (only sites that compete for this, not for the rest of the site)';
		}
		$kinds = array(
			'local'   => 'Kind of competitor: local businesses that serve the same area (use the address, area or country above)',
			'online'  => 'Kind of competitor: online shops and services selling to the same country',
			'content' => 'Kind of competitor: blogs and content sites that publish for the same readers',
		);
		$kind  = self::kind( $kind );
		if ( isset( $kinds[ $kind ] ) ) {
			$lines[] = $kinds[ $kind ];
		}
		return array() === $lines ? '' : "\n" . implode( "\n", $lines );
	}

	/**
	 * What the AI is told about the site: name, tagline, address, recent page titles, plus module details (no personal data).
	 *
	 * @return string
	 */
	public static function context() {
		$lines   = array(
			'Website: ' . home_url( '/' ),
			'Name: ' . wp_strip_all_tags( get_bloginfo( 'name' ) ),
		);
		$tagline = wp_strip_all_tags( get_bloginfo( 'description' ) );
		if ( '' !== $tagline ) {
			$lines[] = 'Tagline: ' . $tagline;
		}
		$types  = array_values( array_intersect( array( 'page', 'post', 'product' ), get_post_types( array( 'public' => true ) ) ) );
		$titles = get_posts(
			array(
				'post_type'        => $types,
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => 25,
				'orderby'          => 'modified',
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);
		$names  = array();
		foreach ( $titles as $id ) {
			$title = wp_strip_all_tags( get_the_title( $id ) );
			if ( '' !== $title ) {
				$names[] = $title;
			}
		}
		if ( array() !== $names ) {
			$lines[] = 'Some pages: ' . implode( ' | ', $names );
		}
		/**
		 * More facts for the competitor prompt (for example the business type, city and country), label => text. No personal data.
		 *
		 * @param array $facts label => text.
		 */
		// The owner's Business Context: what the business is, sells and where.
		if ( class_exists( '\\QuantumCloud\\WPBotCore\\Context\\Context' ) ) {
			$summary = \QuantumCloud\WPBotCore\Context\Context::get()['summary'];
			if ( is_array( $summary ) ) {
				foreach ( array(
					'business' => 'Business',
					'offers'   => 'Offers',
					'audience' => 'Customers',
					'places'   => 'Where',
				) as $key => $label ) {
					$value = isset( $summary[ $key ] ) ? $summary[ $key ] : '';
					$value = is_array( $value ) ? implode( '; ', $value ) : (string) $value;
					if ( '' !== trim( $value ) ) {
						$lines[] = $label . ': ' . substr( sanitize_text_field( $value ), 0, 300 );
					}
				}
			}
		}
		foreach ( (array) apply_filters( 'qcld_wpbot_core/competitor_context', array() ) as $label => $text ) {
			$text = trim( wp_strip_all_tags( (string) $text ) );
			if ( '' !== $text ) {
				$lines[] = sanitize_text_field( (string) $label ) . ': ' . substr( $text, 0, 300 );
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Can the module's AI search the web?
	 *
	 * @param string $module Module id.
	 * @return bool
	 */
	public static function web_search( $module ) {
		foreach ( Keys::call_order( $module ) as $provider ) {
			$definition = Providers::get( $provider );
			if ( $definition && ! empty( $definition['web_search'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is this a marketplace, directory or large general site?
	 *
	 * @param string $domain Domain.
	 * @return bool
	 */
	public static function skipped( $domain ) {
		/**
		 * Site names never suggested as competitors (the label before the top-level domain, e.g. "amazon").
		 *
		 * @param string[] $names Names.
		 */
		$names = (array) apply_filters( 'qcld_wpbot_core/competitor_skip_hosts', self::SKIP );
		$parts = explode( '.', $domain );
		foreach ( $parts as $index => $part ) {
			// Any label but the last (amazon.co.uk, shop.ebay.com, en.wikipedia.org).
			if ( $index < count( $parts ) - 1 && in_array( $part, $names, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Open each site's home page (in parallel, short timeout) and keep the ones that load on their own domain.
	 *
	 * @param string[] $domains Domains.
	 * @return array domain => ok (bool), why, title.
	 */
	public static function verify( array $domains ) {
		$out      = array();
		$requests = array();
		foreach ( $domains as $domain ) {
			/**
			 * The address opened to check a suggested competitor (tests point it at a mock server).
			 *
			 * @param string $url    URL.
			 * @param string $domain Domain.
			 */
			$url = (string) apply_filters( 'qcld_wpbot_core/competitor_check_url', 'https://' . $domain . '/', $domain );
			if ( ! wp_http_validate_url( $url ) ) {
				$out[ $domain ] = self::failed( __( 'This site could not be found.', 'chatbot' ) );
				continue;
			}
			$requests[ $domain ] = array(
				'url'     => $url,
				'type'    => 'GET',
				'headers' => array( 'User-Agent' => 'Mozilla/5.0 (compatible; WPBot competitor check; ' . home_url( '/' ) . ')' ),
			);
		}
		if ( array() === $requests ) {
			return $out;
		}
		$options = array(
			'timeout'          => 8,
			'connect_timeout'  => 5,
			'redirects'        => 3,
			'follow_redirects' => true,
			'verify'           => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
		);
		$proxy   = new \WP_HTTP_Proxy();
		if ( $proxy->is_enabled() ) {
			$options['proxy'] = $proxy->use_authentication() ? array( $proxy->host() . ':' . $proxy->port(), $proxy->username(), $proxy->password() ) : $proxy->host() . ':' . $proxy->port();
		}
		$responses = \WpOrg\Requests\Requests::request_multiple( $requests, $options );
		foreach ( $requests as $domain => $request ) {
			$response = isset( $responses[ $domain ] ) ? $responses[ $domain ] : null;
			if ( ! $response instanceof \WpOrg\Requests\Response ) {
				$out[ $domain ] = self::failed( __( 'The site did not answer.', 'chatbot' ) );
				continue;
			}
			if ( $response->status_code < 200 || $response->status_code >= 400 ) {
				/* translators: %d: HTTP status code. */
				$out[ $domain ] = self::failed( sprintf( __( 'The site answered with an error (%d).', 'chatbot' ), (int) $response->status_code ) );
				continue;
			}
			$final = Competitors::domain( (string) $response->url );
			$start = Competitors::domain( (string) $request['url'] );
			if ( '' !== $final && $final !== $start && $final !== $domain && ! self::same_site( $final, $domain ) ) {
				/* translators: %s: domain the site moved to. */
				$out[ $domain ] = self::failed( sprintf( __( 'The site moves to %s.', 'chatbot' ), $final ) );
				continue;
			}
			$title          = preg_match( '#<title[^>]*>(.*?)</title>#is', substr( (string) $response->body, 0, 200000 ), $match ) ? trim( html_entity_decode( wp_strip_all_tags( $match[1] ), ENT_QUOTES, 'UTF-8' ) ) : '';
			$out[ $domain ] = array(
				'ok'    => true,
				'why'   => '',
				'title' => substr( sanitize_text_field( $title ), 0, 120 ),
			);
		}
		return $out;
	}

	/**
	 * Is one domain a subdomain of the other?
	 *
	 * @param string $a Domain.
	 * @param string $b Domain.
	 * @return bool
	 */
	private static function same_site( $a, $b ) {
		return substr( $a, -strlen( '.' . $b ) ) === '.' . $b || substr( $b, -strlen( '.' . $a ) ) === '.' . $a;
	}

	/**
	 * A failed check.
	 *
	 * @param string $why Reason.
	 * @return array
	 */
	private static function failed( $why ) {
		return array(
			'ok'    => false,
			'why'   => $why,
			'title' => '',
		);
	}
}
