<?php
/**
 * Keyword research without Search Console (S14): Keywords, Niche and
 * Competitor gap.
 *
 * With a keyword data provider connected, keyword lists and competitor
 * rankings come from the provider (real numbers, with a source badge) and
 * AI only adds intent, clusters and reasons. Without one, AI produces the
 * lists with demand and difficulty as high / medium / low estimates only.
 * Every AI answer passes Schema_Guard before it is stored or shown.
 *
 * Every run shows its cost first, uses the module's AI key, is cached 30
 * days per seed, language, country and data source, and is kept in the
 * research history.
 *
 * Not used: Google Autocomplete, scraped search result pages or any
 * unofficial Google endpoint.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Research;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Keywords\Volumes;
use QuantumCloud\SeoAiSearch\Module;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\AI\Usage;
use QuantumCloud\WPBotCore\Connections\Google;
use QuantumCloud\WPBotCore\Keywords\Keyword_Data;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Research runs.
 */
final class Research {

	const KINDS = array( 'keywords', 'niche', 'gap' );

	/**
	 * Most competitor domains in one gap run (Free: one run at a time).
	 */
	const MAX_COMPETITORS = 5;

	/**
	 * Words that start a question.
	 */
	const QUESTION_WORDS = array( 'who', 'what', 'when', 'where', 'why', 'how', 'can', 'does', 'do', 'is', 'are', 'should', 'which', 'will' );

	/**
	 * Language and country for a run (the module's defaults when empty).
	 *
	 * @param array $args language, country.
	 * @return array language, country.
	 */
	public static function place( array $args ) {
		$default  = Volumes::place();
		$language = isset( $args['language'] ) && '' !== (string) $args['language'] ? $args['language'] : $default['language'];
		$country  = isset( $args['country'] ) && '' !== (string) $args['country'] ? $args['country'] : $default['country'];
		return array(
			'language' => substr( sanitize_key( (string) $language ), 0, 8 ),
			'country'  => substr( sanitize_key( (string) $country ), 0, 8 ),
		);
	}

	/**
	 * Seed for a run: a word, or a page's main search (or title).
	 *
	 * @param array  $args seed, post_id, competitors.
	 * @param string $kind Kind.
	 * @return string
	 */
	public static function seed( array $args, $kind ) {
		if ( 'gap' === $kind ) {
			return implode( ', ', self::competitors( $args ) );
		}
		if ( ! empty( $args['post_id'] ) ) {
			$post_id = absint( $args['post_id'] );
			$main    = Keyword_Map::main_query( $post_id );
			return $main ? (string) $main['query'] : (string) get_the_title( $post_id );
		}
		return trim( sanitize_text_field( isset( $args['seed'] ) ? (string) $args['seed'] : '' ) );
	}

	/**
	 * Clean competitor domains (max five, never this site).
	 *
	 * @param array $args competitors (string[] or comma list).
	 * @return string[]
	 */
	public static function competitors( array $args ) {
		$raw  = isset( $args['competitors'] ) ? $args['competitors'] : array();
		$raw  = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$own  = self::own_domain();
		$list = array();
		foreach ( $raw as $item ) {
			$host = strtolower( trim( (string) preg_replace( '#^https?://(www\.)?|/.*$#i', '', trim( (string) $item ) ) ) );
			$host = (string) preg_replace( '/[^a-z0-9.\-]/', '', $host );
			if ( '' !== $host && false !== strpos( $host, '.' ) && $host !== $own && ! in_array( $host, $list, true ) ) {
				$list[] = $host;
			}
		}
		return array_slice( $list, 0, self::MAX_COMPETITORS );
	}

	/**
	 * This site's domain.
	 *
	 * @return string
	 */
	public static function own_domain() {
		return strtolower( (string) preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) );
	}

	/**
	 * Is a keyword data provider connected?
	 *
	 * @return bool
	 */
	public static function has_provider() {
		return Volumes::connected();
	}

	/**
	 * Can the module's AI search the web (primary or backup supports it)?
	 *
	 * @return bool
	 */
	public static function web_search_available() {
		foreach ( Keys::call_order( Module::ID ) as $provider ) {
			$definition = Providers::get( $provider );
			if ( $definition && ! empty( $definition['web_search'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Cost estimate before a run.
	 *
	 * @param string $kind Kind.
	 * @param array  $args seed, post_id, language, country, competitors, refresh.
	 * @return array|WP_Error ai_cost, provider_cost, total, cached, provider, web_search, note.
	 */
	public static function estimate( $kind, array $args ) {
		$check = self::validate( $kind, $args );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$place    = self::place( $args );
		$seed     = self::seed( $args, $kind );
		$source   = self::source_id( $kind );
		$cached   = empty( $args['refresh'] ) ? Research_Store::cached( Research_Store::cache_key( $kind, $seed, $place['language'], $place['country'], $source ) ) : null;
		$web      = 'keywords' !== $kind && self::web_search_available();
		$ai_cost  = self::ai_cost( $kind, $web );
		$provider = 0.0;
		$label    = '';
		if ( self::uses_provider( $kind ) ) {
			$info  = qcld_wpbot_core_keyword_provider();
			$label = $info ? (string) $info['label'] : '';
			if ( 'keywords' === $kind ) {
				$est      = Keyword_Data::research(
					'ideas',
					$seed,
					array_merge(
						$place,
						array(
							'limit'         => 100,
							'estimate_only' => true,
						)
					)
				);
				$provider = is_wp_error( $est ) ? 0.0 : (float) $est['cost'];
			} else {
				foreach ( array_merge( self::competitors( $args ), Google::connected() ? array() : array( self::own_domain() ) ) as $domain ) {
					$est       = Keyword_Data::research(
						'domain_keywords',
						$domain,
						array_merge(
							$place,
							array(
								'limit'         => 200,
								'estimate_only' => true,
							)
						)
					);
					$provider += is_wp_error( $est ) ? 0.0 : (float) $est['cost'];
				}
			}
		}
		$note = '';
		if ( 'keywords' !== $kind && ! $web ) {
			$note = __( 'Results are better with a model that can search the web (OpenAI, Anthropic Claude, Google Gemini or OpenRouter). This run uses what the AI already knows.', 'chatbot' );
		}
		$unit  = self::uses_provider( $kind ) && qcld_wpbot_core_keyword_provider() ? (string) qcld_wpbot_core_keyword_provider()['unit'] : '';
		$money = $cached ? 0.0 : $ai_cost + ( 'usd' === $unit ? $provider : 0.0 );
		$limit = (float) Settings::get( 'research_confirm_above' );
		return array(
			'money'         => round( $money, 4 ),
			'limit'         => $limit,
			// Ask first above the owner's limit (0 = always ask; provider credits in other units always ask).
			'confirm'       => ! $cached && ( $limit <= 0 || $money > $limit || ( $provider > 0 && 'usd' !== $unit && '' !== $unit ) ),
			'ai_cost'       => $cached ? 0.0 : round( $ai_cost, 4 ),
			'provider_cost' => $cached ? 0.0 : round( $provider, 4 ),
			'total'         => $cached ? 0.0 : round( $ai_cost, 4 ),
			'provider_unit' => $unit,
			'cached'        => (bool) $cached,
			'cached_id'     => $cached ? $cached['id'] : 0,
			'provider'      => $label,
			'web_search'    => $web,
			'note'          => $note,
		);
	}

	/**
	 * Run research (or reuse a cached run).
	 *
	 * @param string $kind Kind.
	 * @param array  $args seed, post_id, language, country, competitors, refresh.
	 * @return array|WP_Error The run (id, kind, status, result…); a gap without a provider returns status "running" with a job id.
	 */
	public static function run( $kind, array $args ) {
		$check = self::validate( $kind, $args );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$ready = self::ai_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$place  = self::place( $args );
		$seed   = self::seed( $args, $kind );
		$source = self::source_id( $kind );
		$key    = Research_Store::cache_key( $kind, $seed, $place['language'], $place['country'], $source );
		if ( empty( $args['refresh'] ) ) {
			$cached = Research_Store::cached( $key );
			if ( $cached ) {
				$cached['from_cache'] = true;
				return $cached;
			}
		}

		$run = array(
			'kind'      => $kind,
			'seed'      => $seed,
			'language'  => $place['language'],
			'country'   => $place['country'],
			'cache_key' => $key,
		);

		if ( 'gap' === $kind && ! self::uses_provider( 'gap' ) ) {
			$id  = Research_Store::save( array_merge( $run, array( 'status' => 'running' ) ) );
			$job = Competitor_Fetch::start( $id, self::competitors( $args ), $place );
			if ( is_wp_error( $job ) ) {
				Research_Store::finish( $id, 'failed', array( 'error' => $job->get_error_message() ) );
				return $job;
			}
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, write.
			$wpdb->update( \QuantumCloud\SeoAiSearch\Storage\Schema::table( \QuantumCloud\SeoAiSearch\Storage\Schema::RESEARCH ), array( 'job_id' => (int) $job ), array( 'id' => $id ) );
			return Research_Store::get( $id );
		}

		switch ( $kind ) {
			case 'keywords':
				$out = self::uses_provider( 'keywords' ) ? self::keywords_with_provider( $seed, $place ) : self::keywords_with_ai( $seed, $place );
				break;
			case 'niche':
				$out = self::niche( $seed, $place );
				break;
			default:
				$out = self::gap_with_provider( self::competitors( $args ), $place );
		}
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$id = Research_Store::save( array_merge( $run, $out ) );
		return Research_Store::get( $id );
	}

	/**
	 * Keywords from the provider, with AI intent, clusters and reasons.
	 *
	 * @param string $seed  Seed.
	 * @param array  $place language, country.
	 * @return array|WP_Error result, sources, cost.
	 */
	private static function keywords_with_provider( $seed, array $place ) {
		$data = Keyword_Data::research( 'ideas', $seed, array_merge( $place, array( 'limit' => 100 ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$rows = array_slice( (array) $data['rows'], 0, 100 );
		if ( array() === $rows ) {
			return new WP_Error( 'qcld_wpbot_seo_research_empty', __( 'Your keyword data provider found no keywords for this seed. Try a broader word.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$keywords = array();
		foreach ( $rows as $row ) {
			$keywords[] = Schema_Guard::keyword( (string) $row['keyword'] );
		}
		$ai = self::ask(
			'research_enrich',
			'enrich',
			"Seed: {$seed}\nLanguage: {$place['language']}\nCountry: {$place['country']}\nKeywords:\n- " . implode( "\n- ", $keywords ) . "\n\nFor each keyword give its search intent, a short cluster name that groups related keywords (3 to 10 clusters in total) and one short reason why it is worth targeting. Do not give any numbers.",
			false
		);
		if ( is_wp_error( $ai ) ) {
			return $ai;
		}
		$enrich = array();
		foreach ( $ai['result']['items'] as $item ) {
			$enrich[ $item['keyword'] ] = $item;
		}
		$lists    = array(
			'related'   => array(),
			'long_tail' => array(),
			'questions' => array(),
		);
		$clusters = array();
		foreach ( $rows as $row ) {
			$keyword = Schema_Guard::keyword( (string) $row['keyword'] );
			if ( '' === $keyword ) {
				continue;
			}
			$extra                               = isset( $enrich[ $keyword ] ) ? $enrich[ $keyword ] : array();
			$item                                = array(
				'keyword'    => $keyword,
				'intent'     => isset( $extra['intent'] ) ? $extra['intent'] : '',
				'demand'     => '',
				'difficulty' => '',
				'reason'     => isset( $extra['reason'] ) ? $extra['reason'] : '',
			);
			$lists[ self::bucket( $keyword ) ][] = $item;
			if ( ! empty( $extra['cluster'] ) ) {
				$clusters[ $extra['cluster'] ][] = $keyword;
			}
		}
		$result             = $lists;
		$result['clusters'] = array();
		foreach ( $clusters as $name => $members ) {
			$result['clusters'][] = array(
				'name'     => (string) $name,
				'keywords' => array_slice( $members, 0, 30 ),
			);
		}
		$info = qcld_wpbot_core_keyword_provider();
		return array(
			'result'  => $result,
			'sources' => array( 'provider:' . ( $info ? $info['id'] : '' ), 'ai:' . $ai['provider'] ),
			'cost'    => (float) $ai['cost'],
		);
	}

	/**
	 * Keywords from AI only (no provider): labels, never numbers.
	 *
	 * @param string $seed  Seed.
	 * @param array  $place language, country.
	 * @return array|WP_Error result, sources, cost.
	 */
	private static function keywords_with_ai( $seed, array $place ) {
		$ai = self::ask(
			'research_keywords',
			'keywords',
			"Seed: {$seed}\nLanguage: {$place['language']}\nCountry: {$place['country']}\nSite: " . self::site_context() . "\n\nList keywords real people would search for around this seed: 15 to 30 related keywords, 15 to 30 long-tail keywords (four words or more), 10 to 20 questions, and 3 to 8 topic clusters that group them. For each keyword give intent, demand and difficulty as high, medium or low only (your estimate), and one short reason. Never give search volumes, prices, percentages or any other numbers.",
			false
		);
		if ( is_wp_error( $ai ) ) {
			return $ai;
		}
		if ( array() === array_merge( $ai['result']['related'], $ai['result']['long_tail'], $ai['result']['questions'] ) ) {
			return new WP_Error( 'qcld_wpbot_seo_research_empty', __( 'The AI did not suggest any keywords. Try another seed or try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		return array(
			'result'  => $ai['result'],
			'sources' => array( 'ai:' . $ai['provider'] ),
			'cost'    => (float) $ai['cost'],
		);
	}

	/**
	 * Niche research (web search when the model supports it).
	 *
	 * @param string $seed  Niche or industry.
	 * @param array  $place language, country.
	 * @return array|WP_Error result, sources, cost.
	 */
	private static function niche( $seed, array $place ) {
		$web = self::web_search_available();
		$ai  = self::ask(
			'research_niche',
			'niche',
			"Niche or industry: {$seed}\nLanguage: {$place['language']}\nCountry: {$place['country']}\nSite: " . self::site_context() . "\n\nDescribe the niche for someone planning a website's content: a short overview, the main subtopics (5 to 12, each with why it matters), content gaps (topics that are under-served), a first cluster of 10 to 20 pages to build (title, main keyword, intent, page type) and notes on how sites in this niche earn money. Plain language. Never give search volumes, market sizes, prices or other numbers.",
			$web
		);
		if ( is_wp_error( $ai ) ) {
			return $ai;
		}
		$result               = $ai['result'];
		$result['web_search'] = $web;
		$result['citations']  = $ai['citations'];
		return array(
			'result'  => $result,
			'sources' => array( 'ai:' . $ai['provider'], $web ? 'web' : 'no_web' ),
			'cost'    => (float) $ai['cost'],
		);
	}

	/**
	 * Competitor gap with the provider: their ranked keywords minus the
	 * site's own (from Search Console when connected, else the provider).
	 *
	 * @param string[] $competitors Domains.
	 * @param array    $place       language, country.
	 * @return array|WP_Error result, sources, cost.
	 */
	private static function gap_with_provider( array $competitors, array $place ) {
		$own = self::own_keywords( $place );
		if ( is_wp_error( $own ) ) {
			return $own;
		}
		$theirs = array();
		foreach ( $competitors as $domain ) {
			$data = Keyword_Data::research( 'domain_keywords', $domain, array_merge( $place, array( 'limit' => 200 ) ) );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			foreach ( (array) $data['rows'] as $row ) {
				$keyword = Schema_Guard::keyword( (string) $row['keyword'] );
				if ( '' === $keyword ) {
					continue;
				}
				if ( ! isset( $theirs[ $keyword ] ) ) {
					$theirs[ $keyword ] = array(
						'keyword' => $keyword,
						'volume'  => isset( $row['volume'] ) ? $row['volume'] : null,
						'domains' => array(),
					);
				}
				$theirs[ $keyword ]['domains'][] = $domain;
			}
		}
		$gaps   = array();
		$shared = array();
		foreach ( $theirs as $keyword => $row ) {
			if ( isset( $own[ $keyword ] ) ) {
				$shared[] = array( 'keyword' => $keyword );
			} else {
				$gaps[] = $row;
			}
		}
		usort(
			$gaps,
			static function ( $a, $b ) {
				return (int) $b['volume'] <=> (int) $a['volume'];
			}
		);
		$gaps = array_slice( $gaps, 0, 80 );
		if ( array() === $gaps ) {
			return array(
				'result'  => array(
					'gaps'       => array(),
					'shared'     => array_slice( $shared, 0, 60 ),
					'advantages' => array(),
					'plan'       => array(),
				),
				'sources' => array( 'provider' ),
				'cost'    => 0.0,
			);
		}
		$ai = self::ask(
			'research_gap',
			'gap',
			'Competitors: ' . implode( ', ', $competitors ) . "\nThis site: " . self::own_domain() . "\nSite: " . self::site_context() . "\nKeywords competitors rank for and this site does not:\n- " . implode( "\n- ", wp_list_pluck( $gaps, 'keyword' ) ) . "\nKeywords both rank for:\n- " . implode( "\n- ", array_slice( wp_list_pluck( $shared, 'keyword' ), 0, 40 ) ) . "\n\nFor each gap keyword give intent, demand (high, medium or low), the page type to build and why. Then list this site's advantages and a short action plan in plain language. Never give numbers.",
			false
		);
		if ( is_wp_error( $ai ) ) {
			return $ai;
		}
		$result = $ai['result'];
		// Keep the provider's keywords even where the AI skipped one.
		$described = wp_list_pluck( $result['gaps'], 'keyword' );
		foreach ( $gaps as $row ) {
			if ( ! in_array( $row['keyword'], $described, true ) ) {
				$result['gaps'][] = array(
					'keyword'   => $row['keyword'],
					'intent'    => '',
					'demand'    => '',
					'page_type' => 'other',
					'why'       => '',
				);
			}
		}
		foreach ( $result['gaps'] as $index => $row ) {
			$result['gaps'][ $index ]['domains'] = isset( $theirs[ $row['keyword'] ] ) ? array_values( array_unique( $theirs[ $row['keyword'] ]['domains'] ) ) : array();
		}
		if ( array() === $result['shared'] ) {
			$result['shared'] = array_slice( $shared, 0, 60 );
		}
		$info = qcld_wpbot_core_keyword_provider();
		return array(
			'result'  => $result,
			'sources' => array( 'provider:' . ( $info ? $info['id'] : '' ), 'ai:' . $ai['provider'] ),
			'cost'    => (float) $ai['cost'],
		);
	}

	/**
	 * Gap from fetched competitor titles and headings (no provider). Called
	 * by Competitor_Fetch when its pages are in.
	 *
	 * @param string[] $competitors Domains.
	 * @param array    $pages       domain => list of title, headings.
	 * @param array    $place       language, country.
	 * @return array|WP_Error result, sources, cost.
	 */
	public static function gap_from_pages( array $competitors, array $pages, array $place ) {
		$web   = self::web_search_available();
		$lines = array();
		foreach ( $pages as $domain => $list ) {
			$lines[] = "Competitor {$domain}:";
			foreach ( array_slice( (array) $list, 0, 40 ) as $page ) {
				$lines[] = '- ' . $page['title'] . ( $page['headings'] ? ' (' . implode( '; ', array_slice( $page['headings'], 0, 6 ) ) . ')' : '' );
			}
		}
		$titles = array();
		foreach ( get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 80,
				'fields'         => 'ids',
			)
		) as $post_id ) {
			$titles[] = get_the_title( $post_id );
		}
		$ai = self::ask(
			'research_gap',
			'gap',
			'Competitors: ' . implode( ', ', $competitors ) . "\nThis site: " . self::own_domain() . "\nLanguage: {$place['language']}\nCountry: {$place['country']}\nSite: " . self::site_context() . "\nThis site's page titles:\n- " . implode( "\n- ", $titles ) . "\n\nCompetitor page titles and headings:\n" . implode( "\n", $lines ) . "\n\nFind the keywords and topics the competitors cover that this site does not (gaps), the ones both cover (shared), this site's advantages and a short action plan. For each gap give intent, demand (high, medium or low), the page type to build and why. Never give numbers.",
			$web
		);
		if ( is_wp_error( $ai ) ) {
			return $ai;
		}
		$result               = $ai['result'];
		$result['web_search'] = $web;
		$result['citations']  = $ai['citations'];
		return array(
			'result'  => $result,
			'sources' => array( 'ai:' . $ai['provider'], 'competitor_pages', $web ? 'web' : 'no_web' ),
			'cost'    => (float) $ai['cost'],
		);
	}

	/**
	 * Keywords this site already appears for.
	 *
	 * @param array $place language, country.
	 * @return array|WP_Error keyword => true.
	 */
	private static function own_keywords( array $place ) {
		$own = array();
		if ( Google::connected() ) {
			foreach ( Data_Store::period_rows( 'query', gmdate( 'Y-m-d', time() - 90 * DAY_IN_SECONDS ), gmdate( 'Y-m-d' ), 1, 2000 ) as $row ) {
				$own[ Schema_Guard::keyword( $row['query'] ) ] = true;
			}
			return $own;
		}
		$data = Keyword_Data::research( 'domain_keywords', self::own_domain(), array_merge( $place, array( 'limit' => 200 ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		foreach ( (array) $data['rows'] as $row ) {
			$own[ Schema_Guard::keyword( (string) $row['keyword'] ) ] = true;
		}
		return $own;
	}

	/**
	 * Ask the module's AI and clean the answer.
	 *
	 * @param string $task   Task name (first prompt line).
	 * @param string $kind   Schema kind.
	 * @param string $prompt Prompt body.
	 * @param bool   $web    Use web search.
	 * @return array|WP_Error result, cost, provider, citations.
	 */
	private static function ask( $task, $kind, $prompt, $web ) {
		$response = AI_Service::generate(
			Module::ID,
			$task,
			Request::make(
				'You are a careful keyword research assistant. You never invent numbers: search volumes, difficulty scores, costs per click, prices and percentages are not part of your answer. Demand and difficulty are only "high", "medium" or "low". Answer with JSON only, exactly in the requested shape.',
				"Task: {$task}\n{$prompt}\n\nReturn JSON exactly in this shape:\n" . Schema_Guard::shape( $kind ),
				array(
					'max_tokens' => 'enrich' === $kind ? 3000 : 2500,
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
		$json = $response->json();
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'qcld_wpbot_seo_research_format', __( 'The AI answer could not be read. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$citations = array();
		foreach ( array_slice( (array) $response->sources, 0, 10 ) as $source ) {
			$url = is_array( $source ) && isset( $source['url'] ) ? esc_url_raw( (string) $source['url'], array( 'http', 'https' ) ) : '';
			if ( '' !== $url ) {
				$citations[] = array(
					'url'   => $url,
					'title' => is_array( $source ) && isset( $source['title'] ) ? sanitize_text_field( (string) $source['title'] ) : '',
				);
			}
		}
		return array(
			'result'    => Schema_Guard::clean( $kind, $json ),
			'cost'      => (float) $response->cost,
			'provider'  => (string) $response->provider,
			'citations' => $citations,
		);
	}

	/**
	 * Refuse early when AI isn't ready (the shared notice explains why).
	 *
	 * @return true|WP_Error
	 */
	public static function ai_ready() {
		if ( ! function_exists( 'qcld_wpbot_core_ai_readiness' ) ) {
			return true;
		}
		$state = qcld_wpbot_core_ai_readiness( Module::ID );
		if ( $state['ready'] ) {
			return true;
		}
		return new WP_Error(
			'qcld_wpbot_seo_ai_not_ready',
			$state['message'],
			array(
				'status'    => 409,
				'readiness' => $state,
			)
		);
	}

	/**
	 * Check the inputs of a run.
	 *
	 * @param string $kind Kind.
	 * @param array  $args Args.
	 * @return true|WP_Error
	 */
	private static function validate( $kind, array $args ) {
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return new WP_Error( 'qcld_wpbot_seo_research_kind', __( 'Unknown research type.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 'gap' === $kind ) {
			return array() === self::competitors( $args )
				? new WP_Error( 'qcld_wpbot_seo_research_competitors', __( 'Enter at least one competitor domain, for example example.com.', 'chatbot' ), array( 'status' => 400 ) )
				: true;
		}
		$seed = self::seed( $args, $kind );
		if ( '' === $seed || strlen( $seed ) < 2 ) {
			return new WP_Error( 'qcld_wpbot_seo_research_seed', 'niche' === $kind ? __( 'Enter a niche or industry.', 'chatbot' ) : __( 'Enter a seed keyword or choose a page.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Uses the keyword data provider for this kind?
	 *
	 * @param string $kind Kind.
	 * @return bool
	 */
	private static function uses_provider( $kind ) {
		return 'niche' !== $kind && self::has_provider();
	}

	/**
	 * Data source part of the cache key.
	 *
	 * @param string $kind Kind.
	 * @return string
	 */
	private static function source_id( $kind ) {
		if ( ! self::uses_provider( $kind ) ) {
			return 'ai';
		}
		$info = qcld_wpbot_core_keyword_provider();
		return $info ? (string) $info['id'] : 'provider';
	}

	/**
	 * Estimated AI cost of one run (module's primary provider).
	 *
	 * @param string $kind Kind.
	 * @param bool   $web  Web search.
	 * @return float USD.
	 */
	private static function ai_cost( $kind, $web ) {
		$order = Keys::call_order( Module::ID );
		if ( array() === $order ) {
			return 0.0;
		}
		$creds = Keys::credentials( Module::ID, $order[0] );
		$model = is_wp_error( $creds ) ? '' : (string) $creds['model'];
		$sizes = array(
			'keywords' => array( 900, 2500 ),
			'niche'    => array( 900, 2500 ),
			'gap'      => array( 4000, 2500 ),
		);
		$size  = isset( $sizes[ $kind ] ) ? $sizes[ $kind ] : $sizes['keywords'];
		$cost  = Usage::estimate( $order[0], $model, $size[0], $size[1] );
		if ( $web ) {
			/** This filter is documented in the Pro module (web search fee per call). */
			$cost += (float) apply_filters( 'qcld_wpbot_seo_pro/web_search_fee', 0.01, $order[0] );
		}
		return (float) $cost;
	}

	/**
	 * Which list a keyword belongs in: related, long_tail or questions.
	 *
	 * @param string $keyword Keyword.
	 * @return string
	 */
	public static function bucket( $keyword ) {
		$words = preg_split( '/\s+/u', trim( $keyword ) );
		if ( $words && in_array( strtolower( (string) $words[0] ), self::QUESTION_WORDS, true ) ) {
			return 'questions';
		}
		return count( (array) $words ) >= 4 ? 'long_tail' : 'related';
	}

	/**
	 * Short, non-personal description of the site for prompts.
	 *
	 * @return string
	 */
	private static function site_context() {
		$text = get_bloginfo( 'name' ) . ' — ' . get_bloginfo( 'description' );
		return substr( wp_strip_all_tags( $text ), 0, 200 );
	}
}
