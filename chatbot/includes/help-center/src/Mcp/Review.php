<?php
/**
 * Review and Autopilot for AI changes (HC3-C, plan 6.3, owner's go 26 November 2026).
 *
 * Claude's changes to help articles go through the chatbot's approval queue (ChatBot → Train →
 * Changes) as three change kinds: help_article_update (a new version of a published article or
 * one a person edited), help_article_create (publishing an article Claude wrote, Autopilot only)
 * and help_article_unpublish. In Review mode every one waits for the owner; in Autopilot they
 * apply at once and can be undone, except unpublishing (switch auto_unpublish) and articles a
 * person edited (switch auto_human_edited), which wait unless the owner turned those on. Undo
 * restores the previous version (a new article goes back to draft; nothing is deleted), only
 * while nobody edited the article since, and within undo_days when undo_limit is on. Autopilot
 * sends a daily summary email (switch auto_digest). Without the chatbot's queue, or with
 * use_queue off, the Help Center keeps its own Suggestions list.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Mcp;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Review and Autopilot.
 */
final class Review {

	/**
	 * The chatbot's approval queue.
	 */
	const QUEUE = '\QuantumCloud\Chatbot\Abilities\Changes';

	/**
	 * Daily summary event.
	 */
	const DIGEST = 'qcld_wpbot_help_center_ai_digest';

	/**
	 * When the last summary covered up to (Unix time).
	 */
	const DIGEST_OPTION = 'qcld_wpbot_help_center_ai_digest_at';

	/**
	 * Set once open suggestions moved into the queue.
	 */
	const MIGRATED = 'qcld_wpbot_help_center_suggestions_moved';

	/**
	 * Hook in (only while the Help Center is on).
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/change_kinds', array( self::class, 'kinds' ) );
		add_filter( 'qcld_wpbot_chat/change_needs_approval', array( self::class, 'needs_approval_filter' ), 10, 3 );
		add_action( 'admin_init', array( self::class, 'migrate' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
		add_action( self::DIGEST, array( self::class, 'send_digest' ) );
	}

	/**
	 * Whether Claude's changes go through the chatbot's queue.
	 *
	 * @return bool
	 */
	public static function active() {
		return Settings::on( 'use_queue' ) && class_exists( self::QUEUE ) && method_exists( self::QUEUE, 'propose' );
	}

	/**
	 * Train → Changes, or '' without the chatbot.
	 *
	 * @return string
	 */
	public static function queue_url() {
		return class_exists( '\QuantumCloud\Chatbot\Admin\Train_Page' ) && method_exists( '\QuantumCloud\Chatbot\Admin\Train_Page', 'url' ) ? (string) \QuantumCloud\Chatbot\Admin\Train_Page::url( 'changes' ) : '';
	}

	/**
	 * Help Center changes waiting in the queue.
	 *
	 * @return int
	 */
	public static function waiting() {
		if ( ! self::active() || ! method_exists( self::QUEUE, 'items' ) ) {
			return 0;
		}
		$page  = (array) call_user_func( array( self::QUEUE, 'items' ), 'pending', 1, 200 );
		$count = 0;
		foreach ( isset( $page['items'] ) ? (array) $page['items'] : array() as $item ) {
			if ( 0 === strpos( (string) ( (array) $item )['kind'], 'help_article_' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Change kinds for the queue.
	 *
	 * @param array $kinds Kinds.
	 * @return array
	 */
	public static function kinds( $kinds ) {
		$kinds                           = (array) $kinds;
		$kinds['help_article_update']    = array(
			'label'    => __( 'Change a help article', 'chatbot' ),
			'apply'    => array( self::class, 'apply_update' ),
			'undo'     => array( self::class, 'undo_update' ),
			'approval' => true,
		);
		$kinds['help_article_create']    = array(
			'label'    => __( 'Publish a new help article', 'chatbot' ),
			'apply'    => array( self::class, 'apply_create' ),
			'undo'     => array( self::class, 'undo_create' ),
			'approval' => true,
		);
		$kinds['help_article_unpublish'] = array(
			'label'    => __( 'Unpublish a help article', 'chatbot' ),
			'apply'    => array( self::class, 'apply_unpublish' ),
			'undo'     => array( self::class, 'undo_unpublish' ),
			'approval' => true,
		);
		return $kinds;
	}

	/**
	 * Whether a Help Center change waits for the owner.
	 *
	 * @param string $kind  Kind.
	 * @param array  $input Input (human: a person edited the article; can_publish; migrated).
	 * @return bool
	 */
	public static function needs_approval( $kind, array $input ) {
		if ( ! empty( $input['migrated'] ) || 'autopilot' !== Settings::ai_mode() || empty( $input['can_publish'] ) ) {
			return true;
		}
		if ( 'help_article_unpublish' === $kind ) {
			return ! Settings::on( 'auto_unpublish' );
		}
		if ( 'help_article_update' === $kind && ! empty( $input['human'] ) ) {
			return ! Settings::on( 'auto_human_edited' );
		}
		return false;
	}

	/**
	 * The queue's approval filter (our kinds only).
	 *
	 * @param bool   $needs Default.
	 * @param string $kind  Kind.
	 * @param array  $input Input.
	 * @return bool
	 */
	public static function needs_approval_filter( $needs, $kind, $input ) {
		if ( 0 !== strpos( (string) $kind, 'help_article_' ) ) {
			return $needs;
		}
		return self::needs_approval( (string) $kind, (array) $input );
	}

	/**
	 * Put a change in the queue.
	 *
	 * @param string   $kind    Kind.
	 * @param \WP_Post $post    Article.
	 * @param array    $input   Kind's data (post_id is added).
	 * @param string   $summary One line for the owner.
	 * @return array|\WP_Error { status: pending|applied, change_id }
	 */
	public static function propose( $kind, $post, array $input, $summary ) {
		$input         = array_merge(
			array(
				'post_id'     => (int) $post->ID,
				'slug'        => (string) $post->post_name,
				'can_publish' => current_user_can( 'publish_wpbot_articles' ),
			),
			$input
		);
		$input['auto'] = ! self::needs_approval( $kind, $input );
		return call_user_func( array( self::QUEUE, 'propose' ), $kind, $input, $summary, 'mcp' );
	}

	/**
	 * A short plain preview for the queue screen (which shows input "content").
	 *
	 * @param string $blocks Block markup.
	 * @return string
	 */
	public static function preview( $blocks ) {
		$text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) preg_replace( '/<!--.*?-->/s', ' ', (string) $blocks ) ) ) );
		return function_exists( 'mb_substr' ) && mb_strlen( $text ) > 300 ? mb_substr( $text, 0, 300 ) . '…' : ( strlen( $text ) > 300 ? substr( $text, 0, 300 ) . '…' : $text );
	}

	/**
	 * Apply a new version of an article.
	 *
	 * @param array $input post_id, title, blocks, excerpt, source, type, auto.
	 * @return array|\WP_Error
	 */
	public static function apply_update( array $input ) {
		$post = self::article( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$undo   = array(
			'post_id' => (int) $post->ID,
			'title'   => (string) $post->post_title,
			'content' => (string) $post->post_content,
			'excerpt' => (string) $post->post_excerpt,
			'time'    => time(),
			'after'   => Tools::hash( (string) $input['title'], (string) $input['blocks'] ),
		);
		$result = Tools::write_version( (int) $post->ID, (string) $input['title'], (string) $input['blocks'], isset( $input['excerpt'] ) ? $input['excerpt'] : null, isset( $input['source'] ) ? $input['source'] : null, isset( $input['type'] ) ? (string) $input['type'] : '' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Change_Log::add( ! empty( $input['auto'] ) ? 'auto_updated' : 'applied', 'article', $post->post_name, $post->ID, (string) $input['title'] );
		return array(
			'result' => array( 'post_id' => (int) $post->ID ),
			'undo'   => $undo,
		);
	}

	/**
	 * Undo a new version: the previous one comes back.
	 *
	 * @param array $undo post_id, title, content, excerpt, time, after.
	 * @return true|\WP_Error
	 */
	public static function undo_update( array $undo ) {
		$post = self::article( $undo, true );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( Tools::hash( $post->post_title, $post->post_content ) !== (string) $undo['after'] ) {
			return new \WP_Error( 'qcld_wpbot_help_center_edited', __( 'The article was edited after this change, so it cannot be undone here. Use the article\'s revisions instead.', 'chatbot' ) );
		}
		$result = Tools::write_version( (int) $post->ID, (string) $undo['title'], (string) $undo['content'], (string) $undo['excerpt'], null, '' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Change_Log::add( 'undone', 'article', $post->post_name, $post->ID, __( 'Change undone', 'chatbot' ) );
		return true;
	}

	/**
	 * Publish an article Claude wrote (Autopilot).
	 *
	 * @param array $input post_id, auto.
	 * @return array|\WP_Error
	 */
	public static function apply_create( array $input ) {
		return self::set_status( $input, 'publish', ! empty( $input['auto'] ) ? 'auto_published' : 'published' );
	}

	/**
	 * Undo publishing: back to draft (never deleted).
	 *
	 * @param array $undo post_id, status, time.
	 * @return true|\WP_Error
	 */
	public static function undo_create( array $undo ) {
		return self::restore_status( $undo );
	}

	/**
	 * Unpublish (back to draft).
	 *
	 * @param array $input post_id, reason, auto.
	 * @return array|\WP_Error
	 */
	public static function apply_unpublish( array $input ) {
		return self::set_status( $input, 'draft', ! empty( $input['auto'] ) ? 'auto_unpublished' : 'unpublished' );
	}

	/**
	 * Undo unpublishing: published again.
	 *
	 * @param array $undo post_id, status, time.
	 * @return true|\WP_Error
	 */
	public static function undo_unpublish( array $undo ) {
		return self::restore_status( $undo );
	}

	/**
	 * Change an article's status, keeping the old one for undo.
	 *
	 * @param array  $input  post_id.
	 * @param string $status New status.
	 * @param string $action Log action.
	 * @return array|\WP_Error
	 */
	private static function set_status( array $input, $status, $action ) {
		$post = self::article( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$undo   = array(
			'post_id' => (int) $post->ID,
			'status'  => (string) $post->post_status,
			'now'     => $status,
			'time'    => time(),
		);
		$result = wp_update_post(
			array(
				'ID'          => (int) $post->ID,
				'post_status' => $status,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Change_Log::add( $action, 'article', $post->post_name, $post->ID, isset( $input['reason'] ) ? (string) $input['reason'] : (string) $post->post_title );
		return array(
			'result' => array(
				'post_id' => (int) $post->ID,
				'status'  => $status,
			),
			'undo'   => $undo,
		);
	}

	/**
	 * Put the old status back (only while the status is still the one the change set).
	 *
	 * @param array $undo post_id, status, now, time.
	 * @return true|\WP_Error
	 */
	private static function restore_status( array $undo ) {
		$post = self::article( $undo, true );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( isset( $undo['now'] ) && (string) $undo['now'] !== $post->post_status ) {
			return new \WP_Error( 'qcld_wpbot_help_center_edited', __( 'The article\'s status changed after this change, so it cannot be undone here.', 'chatbot' ) );
		}
		$old    = in_array( (string) $undo['status'], array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ? (string) $undo['status'] : 'draft';
		$result = wp_update_post(
			array(
				'ID'          => (int) $post->ID,
				'post_status' => $old,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Change_Log::add( 'undone', 'article', $post->post_name, $post->ID, __( 'Change undone', 'chatbot' ) );
		return true;
	}

	/**
	 * The article a change is about, checked (and the undo window, for undo).
	 *
	 * @param array $data post_id, time.
	 * @param bool  $undo Whether this is an undo.
	 * @return \WP_Post|\WP_Error
	 */
	private static function article( array $data, $undo = false ) {
		$post = isset( $data['post_id'] ) ? get_post( (int) $data['post_id'] ) : null;
		if ( ! $post || Post_Type::NAME !== $post->post_type || 'trash' === $post->post_status ) {
			return new \WP_Error( 'qcld_wpbot_help_center_gone', __( 'The help article no longer exists.', 'chatbot' ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error( 'qcld_wpbot_help_center_forbidden', __( 'You may not edit this help article.', 'chatbot' ) );
		}
		$days = Settings::undo_days();
		if ( $undo && $days > 0 && isset( $data['time'] ) && time() - (int) $data['time'] > $days * DAY_IN_SECONDS ) {
			/* translators: %d: number of days. */
			return new \WP_Error( 'qcld_wpbot_help_center_too_old', sprintf( _n( 'Changes can be undone for %d day. Use the article\'s revisions instead.', 'Changes can be undone for %d days. Use the article\'s revisions instead.', $days, 'chatbot' ), $days ) );
		}
		return $post;
	}

	/**
	 * Move open Help Center suggestions into the queue once (they wait for the owner).
	 *
	 * @return void
	 */
	public static function migrate() {
		if ( ! self::active() || get_option( self::MIGRATED ) || ! current_user_can( 'edit_wpbot_articles' ) ) {
			return;
		}
		update_option( self::MIGRATED, time(), false );
		foreach ( Tools::with_suggestions() as $post ) {
			$proposal = get_post_meta( $post->ID, Tools::PROPOSAL, true );
			if ( is_array( $proposal ) ) {
				self::propose(
					'help_article_update',
					$post,
					array(
						'title'    => (string) $proposal['title'],
						'blocks'   => (string) $proposal['content'],
						'content'  => self::preview( (string) $proposal['content'] ),
						'excerpt'  => isset( $proposal['excerpt'] ) ? $proposal['excerpt'] : null,
						'source'   => isset( $proposal['source'] ) ? $proposal['source'] : null,
						'migrated' => true,
					),
					/* translators: %s: article title. */
					sprintf( __( 'New version of "%s" (from Claude)', 'chatbot' ), $post->post_title )
				);
				delete_post_meta( $post->ID, Tools::PROPOSAL );
			}
			$unpublish = get_post_meta( $post->ID, Tools::UNPUBLISH, true );
			if ( is_array( $unpublish ) ) {
				self::propose(
					'help_article_unpublish',
					$post,
					array(
						'reason'   => (string) $unpublish['reason'],
						'content'  => (string) $unpublish['reason'],
						'migrated' => true,
					),
					/* translators: %s: article title. */
					sprintf( __( 'Unpublish "%s" (Claude suggests)', 'chatbot' ), $post->post_title )
				);
				delete_post_meta( $post->ID, Tools::UNPUBLISH );
			}
		}//end foreach
	}

	/**
	 * Schedule (or clear) the daily summary.
	 *
	 * @return void
	 */
	public static function schedule() {
		$want = 'autopilot' === Settings::ai_mode() && Settings::on( 'auto_digest' );
		$next = wp_next_scheduled( self::DIGEST );
		if ( $want && ! $next ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::DIGEST );
		} elseif ( ! $want && $next ) {
			wp_clear_scheduled_hook( self::DIGEST );
		}
	}

	/**
	 * What Autopilot changed since the last summary.
	 *
	 * @param int $since Unix time.
	 * @return array[] Log entries.
	 */
	public static function digest_entries( $since ) {
		$out = array();
		foreach ( Change_Log::all() as $entry ) {
			if ( (int) $entry['time'] <= (int) $since ) {
				break;
			}
			if ( 0 === strpos( (string) $entry['action'], 'auto_' ) ) {
				$out[] = $entry;
			}
		}
		return $out;
	}

	/**
	 * The summary email's text.
	 *
	 * @param array[] $entries Log entries.
	 * @return string
	 */
	public static function digest_text( array $entries ) {
		$labels = array(
			'auto_published'   => __( 'Published', 'chatbot' ),
			'auto_updated'     => __( 'Changed', 'chatbot' ),
			'auto_unpublished' => __( 'Unpublished', 'chatbot' ),
		);
		/* translators: %d: number of changes. */
		$lines = array( sprintf( _n( 'Claude made %d change to your help center in Autopilot:', 'Claude made %d changes to your help center in Autopilot:', count( $entries ), 'chatbot' ), count( $entries ) ), '' );
		foreach ( $entries as $entry ) {
			$label   = isset( $labels[ $entry['action'] ] ) ? $labels[ $entry['action'] ] : $entry['action'];
			$link    = (int) $entry['id'] > 0 ? (string) get_permalink( (int) $entry['id'] ) : '';
			$lines[] = '- ' . $label . ': ' . $entry['note'] . ( '' !== $link ? ' (' . $link . ')' : '' );
		}
		$lines[] = '';
		$url     = self::queue_url();
		$lines[] = '' !== $url ? __( 'Review or undo them here:', 'chatbot' ) . ' ' . $url : __( 'See them under Help Center → AI & GitHub.', 'chatbot' );
		return implode( "\n", $lines );
	}

	/**
	 * Send the daily summary (nothing on days without changes).
	 *
	 * @return bool Whether an email was sent.
	 */
	public static function send_digest() {
		if ( 'autopilot' !== Settings::ai_mode() || ! Settings::on( 'auto_digest' ) ) {
			return false;
		}
		$since   = (int) get_option( self::DIGEST_OPTION, time() - DAY_IN_SECONDS );
		$entries = self::digest_entries( $since );
		update_option( self::DIGEST_OPTION, time(), false );
		if ( ! $entries ) {
			return false;
		}
		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] Help Center: what Claude changed today', 'chatbot' ), wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
		return (bool) wp_mail( (string) get_option( 'admin_email' ), $subject, self::digest_text( $entries ) );
	}
}
