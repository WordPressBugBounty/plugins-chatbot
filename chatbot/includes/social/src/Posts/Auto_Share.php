<?php
/**
 * Auto-share new content (Settings › Publishing, screen 21): when a blog
 * post, page or product is published for the first time, WPBot writes a
 * post for every account with auto-share on and saves it as a draft to
 * check, or schedules it a few minutes later.
 *
 * QA #21: shared once per item (post meta plus a stored source check), at
 * most N auto-shares per account per day, skipped categories honoured,
 * password-protected and private items never shared. The writing runs in a
 * background event, so publishing on the site is never slowed down.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Settings;
use QuantumCloud\Social\Storage\Schema;
use QuantumCloud\Social\Support\Log;
use QuantumCloud\Social\Writing\Source;
use QuantumCloud\Social\Writing\Writer;

defined( 'ABSPATH' ) || exit;

/**
 * Auto-share.
 */
final class Auto_Share {

	/**
	 * Background event.
	 */
	const EVENT = 'qcld_wpbot_social_auto_share';

	/**
	 * Post meta marking an item as shared (or queued for sharing).
	 */
	const META = '_qcld_wpbot_social_shared';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'transition_post_status', array( __CLASS__, 'transition' ), 10, 3 );
		add_action( self::EVENT, array( __CLASS__, 'run' ) );
	}

	/**
	 * Content types that can be auto-shared.
	 *
	 * @return array<string,string> post type => label.
	 */
	public static function types() {
		$types = array(
			'post' => __( 'Blog posts', 'chatbot' ),
			'page' => __( 'Pages', 'chatbot' ),
		);
		if ( post_type_exists( 'product' ) ) {
			$types['product'] = __( 'New products', 'chatbot' );
		}
		/**
		 * Content types auto-share offers.
		 *
		 * @param array $types post type => label.
		 */
		return (array) apply_filters( 'qcld_wpbot_social/auto_share_types', $types );
	}

	/**
	 * A post changed status: queue the share on its first publish.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! self::eligible( $post ) ) {
			return;
		}
		update_post_meta( $post->ID, self::META, 'queued' );
		wp_schedule_single_event( time() + 30, self::EVENT, array( (int) $post->ID ) );
	}

	/**
	 * Whether a published item should be shared.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function eligible( $post ) {
		if ( ! $post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || '' !== (string) $post->post_password ) {
			return false;
		}
		if ( ! in_array( $post->post_type, (array) Settings::get( 'auto_share_types' ), true ) || ! isset( self::types()[ $post->post_type ] ) ) {
			return false;
		}
		if ( get_post_meta( $post->ID, self::META, true ) ) {
			return false;
		}
		$skip = array_map( 'intval', (array) Settings::get( 'auto_share_skip_cats' ) );
		if ( array() !== $skip && 'post' === $post->post_type && array_intersect( $skip, wp_get_post_categories( $post->ID ) ) ) {
			return false;
		}
		return array() !== self::accounts();
	}

	/**
	 * Accounts with auto-share on, connected and under today's cap.
	 *
	 * @return array[]
	 */
	public static function accounts() {
		$cap = max( 1, (int) Settings::get( 'auto_share_cap' ) );
		$out = array();
		foreach ( Account_Store::all() as $account ) {
			if ( empty( $account['auto_share'] ) || 'ok' !== $account['status'] || 'manual' === $account['kind'] ) {
				continue;
			}
			if ( self::shared_today( (int) $account['id'] ) >= $cap ) {
				continue;
			}
			$out[] = $account;
		}
		return $out;
	}

	/**
	 * Auto-shares made for an account since midnight (site time).
	 *
	 * @param int $account_id Account id.
	 * @return int
	 */
	public static function shared_today( $account_id ) {
		global $wpdb;
		$posts   = Schema::table( 'posts' );
		$targets = Schema::table( 'post_targets' );
		$since   = get_gmt_from_date( current_time( 'Y-m-d' ) . ' 00:00:00' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$targets} t INNER JOIN {$posts} p ON p.id = t.post_id WHERE t.account_id = %d AND p.source_type = 'auto_share' AND p.created_at >= %s", (int) $account_id, $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
	}

	/**
	 * Whether a Social post already exists for this item.
	 *
	 * @param int $post_id WordPress post id.
	 * @return bool
	 */
	public static function already( $post_id ) {
		global $wpdb;
		$posts = Schema::table( 'posts' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$posts} WHERE source_type = 'auto_share' AND source_id = %d LIMIT 1", (int) $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Background: write and save the share.
	 *
	 * @param int $post_id WordPress post id.
	 * @return int|false Social post id.
	 */
	public static function run( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || self::already( $post->ID ) ) {
			return false;
		}
		// Terms are saved after the status change in one editor save, so check them again here.
		$skip     = array_map( 'intval', (array) Settings::get( 'auto_share_skip_cats' ) );
		$skipped  = 'post' === $post->post_type && array() !== $skip && array_intersect( $skip, wp_get_post_categories( $post->ID ) );
		$accounts = self::accounts();
		if ( 'publish' !== $post->post_status || $skipped || array() === $accounts ) {
			// Not shared: clear the mark so a later publish can share it, and say why.
			delete_post_meta( $post->ID, self::META );
			/* translators: %s: post title. */
			Log::add( 'info', 'auto_share_skipped', sprintf( 'Auto-share skipped "%s": %s', get_the_title( $post ), 'publish' !== $post->post_status ? 'not published' : ( $skipped ? 'skipped category' : 'no account with auto-share on is under today\'s cap' ) ) );
			return false;
		}
		$kind      = 'product' === $post->post_type ? 'product' : 'post';
		$source    = Source::build(
			array(
				'kind'       => $kind,
				'post_id'    => $post->ID,
				'product_id' => $post->ID,
			)
		);
		$text      = self::template( $post );
		$overrides = array();
		$flags     = array();
		if ( ! is_wp_error( $source ) && Settings::get( 'auto_share_ai' ) && Writer::ready() ) {
			foreach ( array_unique( wp_list_pluck( $accounts, 'network' ) ) as $network ) {
				$version = Writer::write_one( $network, $source, array( 'goal' => 'traffic' ) );
				if ( is_wp_error( $version ) ) {
					// Budget used up, no key or a refusal: the plain text below is used.
					Log::add( 'info', 'auto_share_ai', 'Auto-share used the plain text: ' . $version->get_error_message() );
					break;
				}
				if ( '' !== $version['text'] ) {
					$overrides[ $network ] = array( 'text' => $version['text'] );
					if ( $version['flags'] ) {
						$flags[ $network ] = $version['flags'];
					}
				}
			}
		}
		$draft = 'schedule' !== Settings::get( 'auto_share_mode' ) || array() !== $flags;
		$when  = (int) strtotime( $post->post_date_gmt . ' UTC' ) + 60 * max( 0, (int) Settings::get( 'auto_share_delay' ) );
		$saved = Composer::save_for(
			array(
				'title'       => get_the_title( $post ),
				'text'        => $text,
				'link'        => get_permalink( $post ),
				'images'      => array_filter( array( (int) get_post_thumbnail_id( $post ) ) ),
				'accounts'    => wp_list_pluck( $accounts, 'id' ),
				'overrides'   => $overrides,
				'flags'       => $flags,
				'schedule'    => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', max( $when, time() + 120 ) ), 'Y-m-d H:i' ),
				// A flagged version always waits for the owner (QA #26: no auto-post of flagged drafts).
				'status'      => $draft ? 'draft' : 'scheduled',
				'source_type' => 'auto_share',
				'source_id'   => $post->ID,
			),
			(int) $post->post_author
		);
		if ( is_wp_error( $saved ) ) {
			// Checks failed (for example an Instagram account and no image): keep it as a draft.
			$saved = Composer::save(
				array(
					'title'       => get_the_title( $post ),
					'text'        => $text,
					'link'        => get_permalink( $post ),
					'accounts'    => wp_list_pluck( $accounts, 'id' ),
					'overrides'   => $overrides,
					'status'      => 'draft',
					'source_type' => 'auto_share',
					'source_id'   => $post->ID,
				)
			);
		}
		if ( is_wp_error( $saved ) ) {
			delete_post_meta( $post->ID, self::META );
			Log::add( 'error', 'auto_share_failed', 'Auto-share could not save: ' . $saved->get_error_message() );
			return false;
		}
		update_post_meta( $post->ID, self::META, (int) $saved['id'] );
		/* translators: %s: post title. */
		Log::add( 'ok', 'auto_share', sprintf( 'Auto-share prepared for "%s"', get_the_title( $post ) ), array( 'post' => (int) $saved['id'] ) );
		return (int) $saved['id'];
	}

	/**
	 * Plain text when AI is off or unavailable: title and a short excerpt.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function template( $post ) {
		$excerpt = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( Source::plain( $post->post_content ), 30, '…' );
		return trim( get_the_title( $post ) . "\n\n" . Source::plain( $excerpt ) );
	}
}
