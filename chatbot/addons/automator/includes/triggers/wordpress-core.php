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
				'hook'  => 'clear_auth_cookie',
			),
			'wp_post_publish'  => array(
				'title' => __( 'Post/Page Published', 'wpbot-automator' ),
				'hook'  => 'save_post',
			),
			'wp_comment_added' => array(
				'title' => __( 'Comment Added', 'wpbot-automator' ),
				'hook'  => 'comment_post',
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
			add_action( $config['hook'], function( ...$args ) use ( $id ) {
				$this->handle_trigger( $id, $args );
			}, 10, 10 );
		}
	}

	/**
	 * Handle the trigger execution
	 * 
	 * @param string $sub_id Sub-trigger ID.
	 * @param array $args Arguments from the hook.
	 */
	public function handle_trigger( $sub_id, $args ) {
		$data = array();

		switch ( $sub_id ) {
			case 'wp_user_register':
				$data['user_id'] = $args[0];
				$user = get_userdata( $data['user_id'] );
				$data['user_email'] = $user ? $user->user_email : '';
				$data['user_login'] = $user ? $user->user_login : '';
				break;
			case 'wp_user_login':
				$data['user_login'] = $args[0];
				$data['user'] = $args[1];
				$data['user_id'] = $data['user']->ID;
				break;
			case 'wp_post_publish':
				$data['post_id']      = $args[0];
				$data['post']         = $args[1];
				$data['update']       = $args[2];
				$data['post_title']   = $data['post']->post_title;
				$data['post_content']       = wp_strip_all_tags($data['post']->post_content);
				$data['post_excerpt']       = wp_strip_all_tags($data['post']->post_excerpt);
				$data['post_url']           = get_permalink( $data['post']->ID );
				$data['featured_image_url'] = get_the_post_thumbnail_url($data['post']->ID, 'full') ?: 'Image Not found';
				// Only trigger for published posts.
				if ( 'publish' !== get_post_status( $data['post_id'] ) ) {
					return;
				}
				break;
			case 'wp_comment_added':
				$data['comment_id']       = $args[0];
				$data['comment_approved'] = $args[1];
				$data['comment_data']     = $args[2];
				$data['comment_content']  = isset( $args[2]['comment_content'] ) ? $args[2]['comment_content'] : '';
				$data['comment_author']   = isset( $args[2]['comment_author'] ) ? $args[2]['comment_author'] : '';
				$data['comment_author_email'] = isset( $args[2]['comment_author_email'] ) ? $args[2]['comment_author_email'] : '';
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
}
