<?php
/**
 * Topic Finder: the source registry, the refresh job (one step per
 * source, so a slow feed or API never times out a request), coverage
 * checks against the site's own pages, and the weekly schedule.
 *
 * Sources register on qcld_wpbot_writer/idea_sources. The Free screen shows
 * each source in its own list; an extension may merge them into one ranked
 * list through qcld_wpbot_writer/topics_combined.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\AiWriter\Topics\Sources\Brainstorm_Source;
use QuantumCloud\AiWriter\Topics\Sources\Rss_Source;
use QuantumCloud\AiWriter\Topics\Sources\Search_Console_Source;
use QuantumCloud\AiWriter\Topics\Sources\Search_Log_Source;
use QuantumCloud\AiWriter\Topics\Sources\Seasonal_Source;
use QuantumCloud\AiWriter\Topics\Sources\Store_Source;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Topic Finder service.
 */
final class Topic_Finder {

	const JOB_TYPE  = 'topics';
	const STEP      = 'topic_source';
	const CRON      = 'qcld_wpbot_writer_topics_refresh';
	const RUNS      = 'qcld_wpbot_writer_topics_runs';
	const LAST_JOB  = 'qcld_wpbot_writer_topics_job';
	const MAX_IDEAS = 40;

	/**
	 * Hook the schedule, the search logger and the decay source.
	 *
	 * @return void
	 */
	public static function hook() {
		Search_Logger::hook();
		Weekly_Email::hook();
		add_action( self::CRON, array( __CLASS__, 'scheduled_refresh' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_filter( 'qcld_wpbot_writer/decay_sources', array( Search_Console_Source::class, 'decay_source' ) );
	}

	/**
	 * Keep the weekly refresh scheduled (or not, when set to manual).
	 *
	 * @return void
	 */
	public static function schedule() {
		$next = wp_next_scheduled( self::CRON );
		if ( 'weekly' === Settings::get( 'topic_refresh' ) ) {
			if ( ! $next ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON );
			}
		} elseif ( $next ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * Step handlers.
	 *
	 * @return array
	 */
	public static function handlers() {
		return array( self::STEP => array( __CLASS__, 'step' ) );
	}

	/**
	 * Registered sources, in display order.
	 *
	 * Each: label, description, callback( array $context ): array|WP_Error,
	 * status_callback(): array( status, message, fix ), free (bool),
	 * questions (bool: its ideas are visitor questions), refresh (bool:
	 * part of the weekly refresh), coverage (bool: check site coverage).
	 *
	 * @return array<string,array>
	 */
	public static function sources() {
		$sources = array(
			'search_log'     => array(
				'label'           => __( 'Site searches', 'chatbot' ),
				'description'     => __( 'What visitors search for on your site, especially searches that found nothing.', 'chatbot' ),
				'callback'        => array( Search_Log_Source::class, 'ideas' ),
				'status_callback' => array( Search_Log_Source::class, 'status' ),
			),
			'search_console' => array(
				'label'           => __( 'Search Console', 'chatbot' ),
				'description'     => __( 'Rising Google searches and searches with no matching page.', 'chatbot' ),
				'callback'        => array( Search_Console_Source::class, 'ideas' ),
				'status_callback' => array( Search_Console_Source::class, 'status' ),
			),
			'store'          => array(
				'label'           => __( 'Store and visits', 'chatbot' ),
				'description'     => __( 'Best sellers and top landing pages.', 'chatbot' ),
				'callback'        => array( Store_Source::class, 'ideas' ),
				'status_callback' => array( Store_Source::class, 'status' ),
				'coverage'        => false,
			),
			'seasonal'       => array(
				'label'           => __( 'Seasons and events', 'chatbot' ),
				'description'     => __( 'Holidays, shopping days and your own store events in the next 60 days.', 'chatbot' ),
				'callback'        => array( Seasonal_Source::class, 'ideas' ),
				'status_callback' => array( Seasonal_Source::class, 'status' ),
				'coverage'        => false,
			),
			'rss'            => array(
				'label'           => __( 'News feeds', 'chatbot' ),
				'description'     => __( 'New posts and news from feeds you follow (headlines and links only).', 'chatbot' ),
				'callback'        => array( Rss_Source::class, 'ideas' ),
				'status_callback' => array( Rss_Source::class, 'status' ),
			),
			'brainstorm'     => array(
				'label'           => __( 'AI brainstorm', 'chatbot' ),
				'description'     => __( 'Ideas from what your site is about.', 'chatbot' ),
				'callback'        => array( Brainstorm_Source::class, 'ideas' ),
				'status_callback' => array( Brainstorm_Source::class, 'status' ),
			),
		);

		// Keyword Vault (CR-4): only when WPBot Core has the Vault (SEO & AI Search).
		if ( Vault_Link::available() ) {
			$sources['seo'] = array(
				'label'           => __( 'Keyword Vault', 'chatbot' ),
				'description'     => __( 'Keywords you marked Targeted in SEO & AI Search that have no page yet, with their search numbers.', 'chatbot' ),
				'callback'        => array( Sources\Vault_Source::class, 'ideas' ),
				'status_callback' => array( Sources\Vault_Source::class, 'status' ),
				'coverage'        => false,
			);
		}

		/**
		 * Topic Finder sources. Add id => array( label, description,
		 * callback, status_callback, free, questions, refresh, coverage ).
		 *
		 * @param array $sources Sources.
		 */
		$sources = (array) apply_filters( 'qcld_wpbot_writer/idea_sources', $sources );
		$out     = array();
		foreach ( $sources as $id => $source ) {
			if ( ! is_array( $source ) || empty( $source['label'] ) ) {
				continue;
			}
			$out[ sanitize_key( $id ) ] = array_merge(
				array(
					'label'           => '',
					'description'     => '',
					'callback'        => null,
					'status_callback' => null,
					'free'            => true,
					'questions'       => false,
					'refresh'         => true,
					'coverage'        => true,
				),
				$source
			);
		}
		return $out;
	}

	/**
	 * A source's state for the Sources strip.
	 *
	 * @param string $id     Source id.
	 * @param array  $source Definition.
	 * @return array id, label, description, status, message, fix, questions, last_run.
	 */
	public static function source_state( $id, array $source ) {
		$state = is_callable( $source['status_callback'] ) ? (array) call_user_func( $source['status_callback'] ) : array();
		$runs  = get_option( self::RUNS, array() );
		$run   = is_array( $runs ) && isset( $runs[ $id ] ) ? $runs[ $id ] : null;
		$fix   = isset( $state['fix'] ) && is_array( $state['fix'] ) ? $state['fix'] : null;
		return array(
			'id'          => $id,
			'label'       => (string) $source['label'],
			'description' => (string) $source['description'],
			'status'      => isset( $state['status'] ) && in_array( $state['status'], array( 'connected', 'not_connected', 'not_available', 'failing' ), true ) ? $state['status'] : 'connected',
			'message'     => isset( $state['message'] ) ? (string) $state['message'] : '',
			'fix'         => $fix ? array(
				'label' => isset( $fix['label'] ) ? (string) $fix['label'] : '',
				'tab'   => isset( $fix['tab'] ) ? sanitize_key( $fix['tab'] ) : '',
				'url'   => isset( $fix['url'] ) ? esc_url_raw( (string) $fix['url'] ) : '',
			) : null,
			'questions'   => (bool) $source['questions'],
			'refresh'     => (bool) $source['refresh'],
			'last_run'    => $run,
		);
	}

	/**
	 * Everything the Topic Finder screen shows.
	 *
	 * @return array sources, lists, combined, job_id, refreshed.
	 */
	public static function overview() {
		$sources = self::sources();
		$states  = array();
		$lists   = array();
		foreach ( $sources as $id => $source ) {
			$states[]     = self::source_state( $id, $source );
			$lists[ $id ] = Ideas::by_sources( array( $id ), self::MAX_IDEAS );
		}

		/**
		 * One ranked list across every source (null: show one list per
		 * source). Each item has the idea shape plus sources (labels).
		 *
		 * @param array[]|null $combined Combined list.
		 * @param array        $lists    source id => ideas.
		 * @param array        $sources  Source definitions.
		 */
		$lists    = self::with_volumes( $lists );
		$combined = apply_filters( 'qcld_wpbot_writer/topics_combined', null, $lists, $sources );
		$job_id   = (int) get_option( self::LAST_JOB, 0 );
		$job      = $job_id ? Job_Store::get( $job_id ) : null;
		$runs     = get_option( self::RUNS, array() );
		$times    = is_array( $runs ) ? array_filter( wp_list_pluck( $runs, 'at' ) ) : array();
		return array(
			'sources'   => $states,
			'lists'     => $lists,
			'combined'  => is_array( $combined ) ? array_values( $combined ) : null,
			'job_id'    => $job && in_array( $job['status'], array( 'queued', 'running', 'paused' ), true ) ? $job_id : 0,
			'refreshed' => array() !== $times ? max( $times ) : '',
			'keywords'  => self::keywords(),
		);
	}

	/**
	 * Add search numbers to ideas (CR-6 W2): numbers an idea came with
	 * (from SEO & AI Search) are kept; others come from WPBot Core's keyword
	 * data cache when it has them. Each idea gets volume (int|null) and
	 * demand (volume, source, trend, competition …; empty when unknown).
	 *
	 * @param array $lists source id => ideas.
	 * @return array
	 */
	public static function with_volumes( array $lists ) {
		$queries = array();
		foreach ( $lists as $ideas ) {
			foreach ( $ideas as $idea ) {
				$queries[] = self::idea_query( $idea );
			}
		}
		$numbers = Keyword_Numbers::lookup( $queries );
		$extra   = Coverage::volumes( $queries );
		foreach ( $lists as $id => $ideas ) {
			foreach ( $ideas as $i => $idea ) {
				$key    = Keyword_Numbers::normalise( self::idea_query( $idea ) );
				$demand = isset( $idea['demand'] ) && is_array( $idea['demand'] ) ? $idea['demand'] : array();
				if ( ( array() === $demand || null === $demand['volume'] ) && isset( $numbers[ $key ] ) ) {
					$demand = array_merge( $demand, $numbers[ $key ] );
				}
				$volume = array() !== $demand && null !== $demand['volume'] ? (int) $demand['volume'] : ( isset( $extra[ $key ] ) ? (int) $extra[ $key ] : null );

				$lists[ $id ][ $i ]['demand'] = $demand;
				$lists[ $id ][ $i ]['volume'] = $volume;
			}
		}
		return $lists;
	}

	/**
	 * The search an idea targets (its main search, else its title).
	 *
	 * @param array $idea Idea.
	 * @return string
	 */
	private static function idea_query( array $idea ) {
		return isset( $idea['main_query'] ) && '' !== (string) $idea['main_query'] ? (string) $idea['main_query'] : (string) $idea['title'];
	}

	/**
	 * Start a refresh job for the given sources (all refreshable when empty).
	 *
	 * @param string[] $only Source ids.
	 * @return int|WP_Error Job id.
	 */
	public static function refresh( array $only = array() ) {
		$running = (int) get_option( self::LAST_JOB, 0 );
		$job     = $running ? Job_Store::get( $running ) : null;
		if ( $job && in_array( $job['status'], array( 'queued', 'running' ), true ) ) {
			return $running;
		}
		$steps = array();
		foreach ( self::sources() as $id => $source ) {
			if ( ! $source['refresh'] || ( array() !== $only && ! in_array( $id, $only, true ) ) ) {
				continue;
			}
			$state = self::source_state( $id, $source );
			if ( in_array( $state['status'], array( 'not_available', 'not_connected' ), true ) ) {
				continue;
			}
			$steps[] = array(
				'kind'  => self::STEP,
				/* translators: %s: source name. */
				'label' => sprintf( __( 'Reading %s', 'chatbot' ), $source['label'] ),
				'input' => array( 'source' => $id ),
			);
		}
		if ( array() === $steps ) {
			return new WP_Error( 'qcld_wpbot_writer_no_sources', __( 'No topic source is connected yet. Seasons and events always work: check Settings > Topic Finder.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$job_id = Job_Store::create(
			Module::ID,
			self::JOB_TYPE,
			array(
				'title'   => __( 'Finding topics', 'chatbot' ),
				'payload' => array( 'errors' => array() ),
			),
			$steps
		);
		if ( ! $job_id ) {
			return new WP_Error( 'qcld_wpbot_writer_job_failed', __( 'The job could not be created.', 'chatbot' ), array( 'status' => 500 ) );
		}
		update_option( self::LAST_JOB, $job_id, false );
		return $job_id;
	}

	/**
	 * Cron: weekly refresh.
	 *
	 * @return void
	 */
	public static function scheduled_refresh() {
		if ( 'weekly' === Settings::get( 'topic_refresh' ) ) {
			self::refresh();
		}
	}

	/**
	 * Step: read one source and store its ideas. A failing source is
	 * recorded (shown on the strip) and does not stop the other sources.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array
	 */
	public static function step( array $step, array $job ) {
		$id      = isset( $step['input']['source'] ) ? sanitize_key( $step['input']['source'] ) : '';
		$sources = self::sources();
		if ( ! isset( $sources[ $id ] ) || ! is_callable( $sources[ $id ]['callback'] ) ) {
			return array( 'skipped' => $id );
		}
		$result = self::run_source( $id, $sources[ $id ], (int) $job['id'] );
		$out    = array(
			'source' => $id,
			'count'  => $result['count'],
		);
		if ( ! empty( $result['usage'] ) ) {
			$out['_usage'] = $result['usage'];
		}
		return $out;
	}

	/**
	 * Run one source now and store what it returns.
	 *
	 * @param string $id     Source id.
	 * @param array  $source Definition.
	 * @param int    $job_id Job (for usage and the run id).
	 * @return array{count: int, error: string, usage: array}
	 */
	public static function run_source( $id, array $source, $job_id = 0 ) {
		$run_id = 'job' . (int) $job_id . '-' . time();
		$ideas  = call_user_func(
			$source['callback'],
			array(
				'keywords' => self::keywords(),
				'job_id'   => (int) $job_id,
				'run_id'   => $run_id,
			)
		);
		$usage  = array();
		$error  = '';
		$count  = 0;
		if ( is_wp_error( $ideas ) ) {
			$error = $ideas->get_error_message();
		} else {
			$ideas = (array) $ideas;
			if ( isset( $ideas['_usage'] ) ) {
				$usage = (array) $ideas['_usage'];
				unset( $ideas['_usage'] );
			}
			foreach ( array_slice( $ideas, 0, self::MAX_IDEAS * 2 ) as $idea ) {
				if ( self::store( $id, (array) $idea, $run_id, (bool) $source['coverage'] ) ) {
					++$count;
				}
			}
			Ideas::prune( $id, $run_id );
		}
		$runs        = get_option( self::RUNS, array() );
		$runs        = is_array( $runs ) ? $runs : array();
		$runs[ $id ] = array(
			'at'    => gmdate( 'c' ),
			'count' => $count,
			'error' => $error,
		);
		update_option( self::RUNS, $runs, false );
		return array(
			'count' => $count,
			'error' => $error,
			'usage' => $usage,
		);
	}

	/**
	 * Store one idea from a source.
	 *
	 * @param string $source   Source id.
	 * @param array  $idea     Idea: title, ref, reason, trend, relevance, rank_score, link, metrics, questions, session_count, coverage.
	 * @param string $run_id   Run id.
	 * @param bool   $coverage Check site coverage.
	 * @return bool
	 */
	public static function store( $source, array $idea, $run_id, $coverage = true ) {
		$title = isset( $idea['title'] ) ? (string) $idea['title'] : '';
		if ( '' === trim( $title ) ) {
			return false;
		}
		$sample = array();
		if ( ! empty( $idea['link'] ) ) {
			$sample['link'] = esc_url_raw( (string) $idea['link'], array( 'http', 'https' ) );
		}
		if ( ! empty( $idea['metrics'] ) && is_array( $idea['metrics'] ) ) {
			$sample['metrics'] = array_map( 'sanitize_text_field', array_map( 'strval', $idea['metrics'] ) );
		}
		if ( ! empty( $idea['questions'] ) ) {
			$sample['questions'] = array_slice( array_map( 'sanitize_text_field', array_map( 'strval', (array) $idea['questions'] ) ), 0, 5 );
		}
		if ( ! empty( $idea['sources'] ) ) {
			$sample['sources'] = array_slice( array_map( 'sanitize_text_field', array_map( 'strval', (array) $idea['sources'] ) ), 0, 10 );
		}
		$check = $coverage && ( ! isset( $idea['coverage'] ) || false !== $idea['coverage'] );
		// Searches people typed are the search an article on them would target.
		$query = isset( $idea['main_query'] ) ? (string) $idea['main_query'] : ( in_array( $source, array( 'search_log', 'search_console' ), true ) ? (string) $title : '' );
		$id    = Ideas::upsert(
			$title,
			$source,
			array(
				'source_ref'      => isset( $idea['ref'] ) ? (string) $idea['ref'] : md5( strtolower( $title ) ),
				'reason'          => isset( $idea['reason'] ) ? (string) $idea['reason'] : '',
				'trend'           => isset( $idea['trend'] ) && ( is_array( $idea['trend'] ) || in_array( $idea['trend'], array( 'rising', 'steady', 'seasonal', 'falling' ), true ) ) ? $idea['trend'] : 'steady',
				'relevance'       => isset( $idea['relevance'] ) ? (int) $idea['relevance'] : 0,
				'rank_score'      => isset( $idea['rank_score'] ) ? (float) $idea['rank_score'] : 0,
				'session_count'   => isset( $idea['session_count'] ) ? (int) $idea['session_count'] : 0,
				'sample'          => $sample,
				'covered_post_id' => $check ? self::covered_by( $title, $query ) : 0,
				'run_id'          => $run_id,
				'topic'           => isset( $idea['topic'] ) ? (string) $idea['topic'] : self::topic_for( $title ),
				'main_query'      => $query,
			) + array_intersect_key( $idea, array_flip( array( 'volume', 'volume_range', 'volume_source', 'competition', 'numbers', 'estimate' ) ) )
		);
		return ! is_wp_error( $id );
	}

	/**
	 * A published page that already covers a topic (0 when none): most of
	 * the topic's meaningful words appear in the page's title.
	 *
	 * The shared keyword map answers first (a live page that targets the
	 * search); titles are the fallback.
	 *
	 * @param string $topic      Topic.
	 * @param string $main_query Main search ('' to use the topic).
	 * @return int Post id.
	 */
	public static function covered_by( $topic, $main_query = '' ) {
		$map = Map_Sync::lookup( '' !== $main_query ? $main_query : $topic );
		if ( $map['covered'] ) {
			return (int) $map['covered']['post_id'];
		}
		$words = self::words( $topic );
		if ( array() === $words ) {
			return 0;
		}
		$longest = $words;
		usort(
			$longest,
			static function ( $a, $b ) {
				return strlen( $b ) <=> strlen( $a );
			}
		);
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		$ids   = get_posts(
			array(
				'post_type'        => $types,
				'post_status'      => 'publish',
				's'                => implode( ' ', array_slice( $longest, 0, 2 ) ),
				'posts_per_page'   => 10,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		foreach ( $ids as $post_id ) {
			$have = self::words( get_the_title( $post_id ) );
			if ( array() !== $have && count( array_intersect( $words, $have ) ) / count( $words ) >= 0.6 ) {
				return (int) $post_id;
			}
		}
		return 0;
	}

	/**
	 * The site main topic a text belongs to ('' when none fits): the first
	 * main topic whose words appear in the text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function topic_for( $text ) {
		$have = self::words( $text );
		foreach ( self::keywords() as $keyword ) {
			$words = self::words( $keyword );
			foreach ( $words as $word ) {
				foreach ( $have as $other ) {
					// "bike" and "bikes" belong together.
					if ( 0 === strpos( $other, $word ) || 0 === strpos( $word, $other ) ) {
						return $keyword;
					}
				}
			}
		}
		return '';
	}

	/**
	 * Meaningful words of a text (lower case, no short or common words).
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function words( $text ) {
		$text  = function_exists( 'mb_strtolower' ) ? mb_strtolower( wp_strip_all_tags( (string) $text ), 'UTF-8' ) : strtolower( wp_strip_all_tags( (string) $text ) );
		$stop  = array( 'the', 'and', 'for', 'with', 'your', 'you', 'how', 'what', 'why', 'when', 'where', 'which', 'who', 'does', 'from', 'that', 'this', 'are', 'can', 'get', 'best', 'into', 'about', 'more', 'most', 'should', 'will', 'have', 'has', 'our', 'their', 'there', 'than', 'then', 'them', 'they', 'its', 'not', 'any', 'all', 'one' );
		$words = array();
		foreach ( preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
			$long = function_exists( 'mb_strlen' ) ? mb_strlen( $word ) >= 3 : strlen( $word ) >= 3;
			if ( ( $long || ctype_digit( $word ) ) && ! in_array( $word, $stop, true ) ) {
				$words[] = $word;
			}
		}
		return array_values( array_unique( $words ) );
	}

	/**
	 * Main topics of the site: the owner's list, else the biggest
	 * categories and product categories.
	 *
	 * @return string[]
	 */
	public static function keywords() {
		$list = (array) Settings::get( 'topic_keywords' );
		if ( array() !== $list ) {
			return $list;
		}
		$out = array();
		foreach ( array( 'category', 'product_cat' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 5,
					'hide_empty' => true,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( (int) get_option( 'default_category' ) !== (int) $term->term_id && 'uncategorized' !== $term->slug ) {
					$out[] = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
				}
			}
		}
		if ( array() === $out ) {
			$out[] = (string) get_bloginfo( 'name' );
		}
		return array_slice( array_values( array_unique( $out ) ), 0, 8 );
	}
}
