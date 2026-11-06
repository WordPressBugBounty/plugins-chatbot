<?php
/**
 * Content decay alerts: pages losing visits or search clicks become
 * Improve tasks with the likely cause.
 *
 * Data comes from sources registered on qcld_wpbot_writer/decay_sources:
 * Site Insight (built in, when it is active) and Search Console (when
 * connected). Each source reports per-post numbers for the last 28 days and
 * the 28 days before. A weekly check runs on WP-Cron; admins can also run
 * it from the Improve screen.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Improve;

use QuantumCloud\AiWriter\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Decay detection and the Improve task list.
 */
final class Decay_Alerts {

	const OPTION = 'qcld_wpbot_writer_improve_tasks';
	const CRON   = 'qcld_wpbot_writer_decay_check';

	/**
	 * A drop counts from this share lost (0.3 = 30% fewer).
	 */
	const DROP = 0.3;

	/**
	 * Ignore pages with fewer visits or clicks than this before the drop.
	 */
	const MIN_BEFORE = 20;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( self::CRON, array( __CLASS__, 'check' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
		add_filter( 'qcld_wpbot_writer/decay_sources', array( __CLASS__, 'builtin_sources' ), 5 );
	}

	/**
	 * Weekly check on WP-Cron.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON );
		}
	}

	/**
	 * Site Insight when its storage class is available.
	 *
	 * @param array $sources id => callable.
	 * @return array
	 */
	public static function builtin_sources( $sources ) {
		if ( Site_Insight_Source::available() ) {
			$sources['site_insight'] = array( Site_Insight_Source::class, 'rows' );
		}
		return $sources;
	}

	/**
	 * Run every source and refresh the task list.
	 *
	 * @return array[] Open tasks.
	 */
	public static function check() {
		/**
		 * Decay data sources. Each callable returns rows: post_id, source
		 * (label), metric (visits|clicks), before, after, plus optional
		 * impressions_before, impressions_after, position_before,
		 * position_after, scroll (average % read).
		 *
		 * @param array $sources id => callable.
		 */
		$sources = (array) apply_filters( 'qcld_wpbot_writer/decay_sources', array() );
		$tasks   = self::tasks( true );
		$found   = array();
		foreach ( $sources as $id => $callable ) {
			if ( ! is_callable( $callable ) ) {
				continue;
			}
			foreach ( (array) call_user_func( $callable ) as $row ) {
				$row = self::normalise( $row, (string) $id );
				if ( ! $row || ! self::declining( $row ) ) {
					continue;
				}
				$key = $row['post_id'];
				// Keep the strongest signal per post.
				if ( ! isset( $found[ $key ] ) || $row['drop'] > $found[ $key ]['drop'] ) {
					$found[ $key ] = $row;
				}
			}
		}

		$now = gmdate( 'c' );
		foreach ( $found as $post_id => $row ) {
			$existing = isset( $tasks[ $post_id ] ) ? $tasks[ $post_id ] : null;
			if ( $existing && 'dismissed' === $existing['status'] && strtotime( $existing['updated'] ) > time() - 60 * DAY_IN_SECONDS ) {
				continue;
			}
			$tasks[ $post_id ] = array(
				'post_id'  => $post_id,
				'source'   => $row['source'],
				'metric'   => $row['metric'],
				'before'   => $row['before'],
				'after'    => $row['after'],
				'drop'     => $row['drop'],
				'cause'    => self::cause( $row ),
				'status'   => 'open',
				'detected' => $existing ? $existing['detected'] : $now,
				'updated'  => $now,
			);
		}
		uasort(
			$tasks,
			static function ( $a, $b ) {
				return $b['drop'] <=> $a['drop'];
			}
		);
		update_option( self::OPTION, array_slice( $tasks, 0, 100, true ), false );
		update_option( self::OPTION . '_checked', $now, false );
		return self::open_tasks();
	}

	/**
	 * Clean a source row.
	 *
	 * @param mixed  $row    Raw row.
	 * @param string $source Source id.
	 * @return array|null
	 */
	private static function normalise( $row, $source ) {
		$row     = (array) $row;
		$post_id = isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0;
		if ( ! $post_id || ! get_post( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return null;
		}
		$before = isset( $row['before'] ) ? (float) $row['before'] : 0;
		$after  = isset( $row['after'] ) ? (float) $row['after'] : 0;
		$out    = array(
			'post_id' => $post_id,
			'source'  => isset( $row['source'] ) ? sanitize_text_field( (string) $row['source'] ) : $source,
			'metric'  => isset( $row['metric'] ) && 'clicks' === $row['metric'] ? 'clicks' : 'visits',
			'before'  => $before,
			'after'   => $after,
			'drop'    => $before > 0 ? round( 1 - $after / $before, 3 ) : 0,
		);
		foreach ( array( 'impressions_before', 'impressions_after', 'position_before', 'position_after', 'scroll' ) as $key ) {
			if ( isset( $row[ $key ] ) ) {
				$out[ $key ] = (float) $row[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Whether a row shows a real decline.
	 *
	 * @param array $row Row.
	 * @return bool
	 */
	public static function declining( array $row ) {
		return $row['before'] >= self::MIN_BEFORE && $row['drop'] >= self::DROP;
	}

	/**
	 * The likely cause, in plain words.
	 *
	 * @param array $row Row.
	 * @return string
	 */
	public static function cause( array $row ) {
		$parts = array(
			sprintf(
				/* translators: 1: visits or clicks, 2: number before, 3: number after, 4: source. */
				__( '%1$s fell from %2$d to %3$d over the last 28 days (%4$s).', 'chatbot' ),
				'clicks' === $row['metric'] ? __( 'Search clicks', 'chatbot' ) : __( 'Visits', 'chatbot' ),
				(int) $row['before'],
				(int) $row['after'],
				$row['source']
			),
		);
		if ( isset( $row['impressions_before'], $row['impressions_after'] ) && $row['impressions_before'] > 0 ) {
			$impressions = 1 - $row['impressions_after'] / $row['impressions_before'];
			if ( $impressions < 0.15 ) {
				$parts[] = __( 'It still shows in search as often, but fewer people click: a clearer title and meta description should help.', 'chatbot' );
			} elseif ( isset( $row['position_before'], $row['position_after'] ) && $row['position_after'] - $row['position_before'] >= 2 ) {
				$parts[] = __( 'It dropped in search rankings: newer pages probably answer the topic better, so update and expand it.', 'chatbot' );
			} else {
				$parts[] = __( 'Fewer people search for this now, or it lost rankings.', 'chatbot' );
			}
		}
		if ( isset( $row['scroll'] ) && $row['scroll'] > 0 && $row['scroll'] < 40 ) {
			$parts[] = __( 'Most readers leave before halfway: a clearer, shorter opening should help.', 'chatbot' );
		}
		$modified = get_post_modified_time( 'U', true, $row['post_id'] );
		if ( $modified && $modified < time() - YEAR_IN_SECONDS ) {
			/* translators: %s: date. */
			$parts[] = sprintf( __( 'It was last updated on %s, so some details may be out of date.', 'chatbot' ), wp_date( get_option( 'date_format' ), $modified ) );
		}
		return implode( ' ', $parts );
	}

	/**
	 * Every task, keyed by post id.
	 *
	 * @param bool $raw Include closed ones.
	 * @return array[]
	 */
	private static function tasks( $raw = false ) {
		$tasks = get_option( self::OPTION, array() );
		$tasks = is_array( $tasks ) ? $tasks : array();
		return $raw ? $tasks : array_filter(
			$tasks,
			static function ( $task ) {
				return 'open' === $task['status'];
			}
		);
	}

	/**
	 * Open tasks the current user can work on, with titles and links.
	 *
	 * @return array[]
	 */
	public static function open_tasks() {
		$out = array();
		foreach ( self::tasks() as $task ) {
			if ( ! current_user_can( 'edit_post', $task['post_id'] ) ) {
				continue;
			}
			$task['title']         = get_the_title( $task['post_id'] );
			$task['url']           = (string) get_permalink( $task['post_id'] );
			$task['source_module'] = 'ai_writer';
			$task['fix']           = $task['cause'];
			$out[]                 = $task;
		}
		/**
		 * Improve tasks from other modules (SEO & AI Search on-page fixes and
		 * opportunities), listed next to decay alerts.
		 *
		 * @param array[] $extra Tasks: source_module, post_id, reason, fix.
		 */
		foreach ( (array) apply_filters( 'qcld_wpbot_writer/improve_tasks', array() ) as $task ) {
			$post_id = isset( $task['post_id'] ) ? absint( $task['post_id'] ) : 0;
			if ( ! $post_id || ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$reason = isset( $task['reason'] ) ? sanitize_text_field( (string) $task['reason'] ) : '';
			$out[]  = array(
				'post_id'       => $post_id,
				'title'         => get_the_title( $post_id ),
				'url'           => (string) get_permalink( $post_id ),
				'cause'         => $reason,
				'fix'           => isset( $task['fix'] ) ? sanitize_textarea_field( (string) $task['fix'] ) : $reason,
				'source_module' => isset( $task['source_module'] ) ? sanitize_key( $task['source_module'] ) : 'other',
				'status'        => 'open',
			);
		}
		return $out;
	}

	/**
	 * Dismiss a task (it comes back only if the post keeps declining after 60 days).
	 *
	 * @param int $post_id Post.
	 * @return bool
	 */
	public static function dismiss( $post_id ) {
		return self::set_status( $post_id, 'dismissed' );
	}

	/**
	 * Close a task once changes were applied.
	 *
	 * @param int $post_id Post.
	 * @return bool
	 */
	public static function resolve( $post_id ) {
		return self::set_status( $post_id, 'done' );
	}

	/**
	 * Change a task's status.
	 *
	 * @param int    $post_id Post.
	 * @param string $status  Status.
	 * @return bool
	 */
	private static function set_status( $post_id, $status ) {
		$tasks = self::tasks( true );
		if ( ! isset( $tasks[ $post_id ] ) ) {
			return false;
		}
		$tasks[ $post_id ]['status']  = $status;
		$tasks[ $post_id ]['updated'] = gmdate( 'c' );
		update_option( self::OPTION, $tasks, false );
		return true;
	}

	/**
	 * When the last check ran ('' if never), and whether any source exists.
	 *
	 * @return array{checked: string, sources: string[]}
	 */
	public static function status() {
		/** This filter is documented in check(). */
		$sources = (array) apply_filters( 'qcld_wpbot_writer/decay_sources', array() );
		return array(
			'checked' => (string) get_option( self::OPTION . '_checked', '' ),
			'sources' => array_map( 'strval', array_keys( $sources ) ),
			'can_run' => current_user_can( Module::cap( 'use' ) ),
		);
	}
}
