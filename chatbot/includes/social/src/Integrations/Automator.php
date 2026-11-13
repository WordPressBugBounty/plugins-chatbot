<?php
/**
 * Automator (D3): one token store and one log. Automator workflows create
 * social posts through Social's own action instead of their own API calls,
 * and Social's published/failed events start workflows.
 *
 * - Action "wpbot_social" → social_create_post: text, link, networks (ids or
 *   "all"), when (now | draft | Y-m-d H:i); returns the Social post id.
 * - Trigger "wpbot_social" → social_post_published / social_post_failed.
 *
 * Both are registered into Automator's registry when Automator runs. The
 * Automator editor lists apps from its own built metadata, so adding the
 * "WPBot Social" app to its picker is a change in Automator (asked of its
 * owners); workflows that name these ids already run.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Integrations;

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Posts\Composer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Automator integration.
 */
final class Automator {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'init', array( __CLASS__, 'register' ), 11 );
	}

	/**
	 * Register the trigger and the action with Automator (after its registry is ready).
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! class_exists( '\\WPbot_Automator\\Core\\Registry' ) || ! class_exists( '\\WPbot_Automator\\Actions\\Action' ) || ! class_exists( '\\WPbot_Automator\\Triggers\\Trigger' ) ) {
			return;
		}
		\WPbot_Automator\Core\Registry::register_action( new Automator\Social_Action() );
		\WPbot_Automator\Core\Registry::register_trigger( new Automator\Social_Trigger() );
	}

	/**
	 * Whether a workflow's text, link or title is built from {tokens}, which
	 * can carry visitor input (QA #60, #63).
	 *
	 * @param array $cfg Action config.
	 * @return bool
	 */
	public static function has_tokens( array $cfg ) {
		foreach ( array( 'text', 'link', 'title' ) as $key ) {
			if ( isset( $cfg[ $key ] ) && preg_match( '/\{[^}]+\}/', (string) $cfg[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Create a social post (Automator's action, and a public helper for other code).
	 *
	 * @param array $args text, link, title, networks (array of network ids, or "all"),
	 *                    accounts (ids), when ("now", "draft" or site time "Y-m-d H:i"),
	 *                    from_tokens (bool: text came from workflow tokens),
	 *                    publish_without_review (bool, default false), workflow_id.
	 * @return array{id:int, status:string}|WP_Error
	 */
	public static function create_post( array $args ) {
		$text = isset( $args['text'] ) ? (string) $args['text'] : '';
		if ( '' === trim( $text ) ) {
			return new WP_Error( 'qcld_wpbot_social_automator', __( 'The post needs some text.', 'chatbot' ) );
		}
		$networks = isset( $args['networks'] ) ? $args['networks'] : 'all';
		$networks = is_string( $networks ) ? array_filter( array_map( 'trim', explode( ',', $networks ) ) ) : (array) $networks;
		$ids      = array_map( 'absint', isset( $args['accounts'] ) ? (array) $args['accounts'] : array() );
		if ( array() === array_filter( $ids ) ) {
			foreach ( Account_Store::all() as $account ) {
				if ( 'ok' === $account['status'] && 'manual' !== $account['kind'] && ( in_array( 'all', $networks, true ) || in_array( $account['network'], $networks, true ) ) ) {
					$ids[] = (int) $account['id'];
				}
			}
		}
		$when  = isset( $args['when'] ) ? (string) $args['when'] : 'draft';
		$draft = 'draft' === $when;
		$flags = array();
		// QA #60: text built from trigger tokens can be visitor input (forms, chat).
		// It waits for review unless the workflow explicitly opts out.
		if ( ! empty( $args['from_tokens'] ) && empty( $args['publish_without_review'] ) ) {
			$draft   = true;
			$flags[] = __( 'Written by an Automator workflow from visitor input. Review it before posting.', 'chatbot' );
			$flags   = array_merge( $flags, \QuantumCloud\Social\Writing\Honesty::check( $text, isset( $args['link'] ) ? (string) $args['link'] : '' ) );
		}
		$saved = Composer::save_for(
			array(
				'title'       => isset( $args['title'] ) ? (string) $args['title'] : '',
				'text'        => $text,
				'link'        => isset( $args['link'] ) ? (string) $args['link'] : '',
				'accounts'    => array_values( array_unique( array_filter( $ids ) ) ),
				'schedule'    => preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $when ) ? $when : '',
				'status'      => $draft ? 'draft' : 'scheduled',
				'source_type' => 'automator',
				'source_id'   => isset( $args['workflow_id'] ) ? absint( $args['workflow_id'] ) : 0,
				'flags'       => array() !== $flags ? array( 'automator' => $flags ) : array(),
			),
			self::owner( $args )
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return array(
			'id'     => (int) $saved['id'],
			'status' => $draft ? 'draft' : 'scheduled',
		);
	}

	/**
	 * The workflow's owner (QA #77): an "owner" or "user_id" argument, or the
	 * author of the workflow post. 0 when unknown.
	 *
	 * @param array $args Action arguments.
	 * @return int
	 */
	private static function owner( array $args ) {
		foreach ( array( 'owner', 'user_id' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				return absint( $args[ $key ] );
			}
		}
		$workflow = ! empty( $args['workflow_id'] ) ? get_post( absint( $args['workflow_id'] ) ) : null;
		return $workflow ? (int) $workflow->post_author : 0;
	}
}
