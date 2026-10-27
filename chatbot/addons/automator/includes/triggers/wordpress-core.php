<?php
/**
 * WordPress Core Triggers
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WordPress_Core
 */
class WordPress_Core extends Trigger {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'wordpress';
		$this->group = __( 'WordPress', 'wpbot-automator' );
	}

	/**
	 * Get available sub-triggers
	 */
	public static function get_sub_triggers() {
		return array(
			'wp_user_register' => array(
				'title' => __( 'User Registers', 'wpbot-automator' ),
				'hook'  => 'user_register',
			),
			'wp_user_login'    => array(
				'title' => __( 'User Logs In', 'wpbot-automator' ),
				'hook'  => 'wp_login',
			),
			'wp_user_logout'   => array(
				'title' => __( 'User Logs Out', 'wpbot-automator' ),
				'hook'  => 'wp_logout',
			),
			'wp_post_publish'  => array(
				'title' => __( 'Post/Page Published', 'wpbot-automator' ),
				'hook'  => 'transition_post_status',
			),
			'wp_post_update'   => array(
				'title' => __( 'Post/Page Updated', 'wpbot-automator' ),
				'hook'  => 'transition_post_status',
			),
			'wp_comment_added' => array(
				'title' => __( 'Comment Added', 'wpbot-automator' ),
				'hook'  => 'comment_post',
			),
			// Comments held for moderation fire once they are approved.
			'wp_comment_approved' => array(
				'title'  => __( 'Comment Approved', 'wpbot-automator' ),
				'hook'   => 'comment_unapproved_to_approved',
				'sub_id' => 'wp_comment_added',
			),
			'wp_comment_spam_approved' => array(
				'title'  => __( 'Comment Approved (from spam)', 'wpbot-automator' ),
				'hook'   => 'comment_spam_to_approved',
				'sub_id' => 'wp_comment_added',
			),
			'wp_media_upload'  => array(
				'title' => __( 'Media Uploaded', 'wpbot-automator' ),
				'hook'  => 'add_attachment',
			),
		);
	}

	/**
	 * Register the triggers
	 */
	public function register() {
		$triggers = self::get_sub_triggers();

		foreach ( $triggers as $id => $config ) {
			$sub_id = isset( $config['sub_id'] ) ? $config['sub_id'] : $id;
			add_action( $config['hook'], function( ...$args ) use ( $id, $sub_id ) {
				$this->handle_trigger( $sub_id, $args, $id );
			}, 10, 10 );
		}
	}

	/**
	 * Handle the trigger execution
	 * 
	 * @param string $sub_id Sub-trigger ID.
	 * @param array $args Arguments from the hook.
	 */
	public function handle_trigger( $sub_id, $args, $source = '' ) {
		$data = array();

		switch ( $sub_id ) {
			case 'wp_user_register':
				$data['user_id'] = $args[0];
				$user = get_userdata( $data['user_id'] );
				$data['user_email'] = $user ? $user->user_email : '';
				$data['user_login'] = $user ? $user->user_login : '';
				break;
			case 'wp_user_login':
				// Pass only safe profile fields; the WP_User object carries the password hash.
				$data = self::user_data( isset( $args[1] ) ? $args[1] : get_user_by( 'login', $args[0] ) );
				break;
			case 'wp_user_logout':
				$data = self::user_data( isset( $args[0] ) ? get_userdata( (int) $args[0] ) : null );
				if ( empty( $data['user_id'] ) ) {
					return;
				}
				break;
			case 'wp_post_publish':
			case 'wp_post_update':
				// transition_post_status( $new, $old, $post ): "publish" fires when a public post becomes
				// published, "update" when an already published one is saved again.
				list( $new_status, $old_status, $post ) = array_pad( $args, 3, null );
				$is_update = 'wp_post_update' === $sub_id;
				if ( ! $post instanceof \WP_Post || 'publish' !== $new_status || ( 'publish' === $old_status ) !== $is_update ) {
					return;
				}
				// The block editor saves twice (REST + meta boxes); report each post once per request.
				static $seen = array();
				if ( isset( $seen[ $sub_id ][ $post->ID ] ) ) {
					return;
				}
				$seen[ $sub_id ][ $post->ID ] = true;
				if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || ! is_post_type_viewable( $post->post_type ) || 'attachment' === $post->post_type ) {
					return;
				}
				$data['post_id']            = $post->ID;
				$data['post_type']          = $post->post_type;
				$data['update']             = 'new' !== $old_status && 'auto-draft' !== $old_status;
				$data['post_title']         = $post->post_title;
				$data['post_content']       = wp_strip_all_tags( $post->post_content );
				$data['post_excerpt']       = wp_strip_all_tags( $post->post_excerpt );
				$data['post_url']           = get_permalink( $post->ID );
				$data['featured_image_url'] = get_the_post_thumbnail_url( $post->ID, 'full' ) ?: 'Image Not found';
				break;
			case 'wp_comment_added':
				if ( in_array( $source, array( 'wp_comment_approved', 'wp_comment_spam_approved' ), true ) ) {
					// comment_{unapproved|spam}_to_approved( $comment ).
					$comment = isset( $args[0] ) ? get_comment( $args[0] ) : null;
				} else {
					// comment_post( $id, $approved, $commentdata ): spam and held comments wait for approval.
					if ( 1 !== (int) $args[1] ) {
						return;
					}
					$comment = get_comment( $args[0] );
				}
				if ( ! $comment ) {
					return;
				}
				$data['comment_id']           = (int) $comment->comment_ID;
				$data['comment_approved']     = 1;
				$data['post_id']              = (int) $comment->comment_post_ID;
				$data['comment_content']      = $comment->comment_content;
				$data['comment_author']       = $comment->comment_author;
				$data['comment_author_email'] = $comment->comment_author_email;
				break;
			case 'wp_media_upload':
				$data['attachment_id'] = $args[0];
				$data['file_path'] = get_attached_file( $data['attachment_id'] );
				$data['file_url'] = wp_get_attachment_url( $data['attachment_id'] );
				break;
		}

		$this->run( array(
			'sub_id' => $sub_id,
			'data'   => $data,
		) );
	}

	/**
	 * Profile fields safe to hand to workflows (no password hash or activation key).
	 *
	 * @param \WP_User|false|null $user User.
	 * @return array
	 */
	private static function user_data( $user ) {
		if ( ! $user instanceof \WP_User ) {
			return array();
		}
		return array(
			'user_id'      => $user->ID,
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'roles'        => implode( ',', (array) $user->roles ),
		);
	}
}
