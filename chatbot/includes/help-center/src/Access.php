<?php
/**
 * Who may read an article (plan 5.1 "Access": public and signed-in visitors).
 *
 * The labels are the ones stored with the knowledge base (WPBot Core access
 * labels): none or "public" for everyone, "logged_in" for signed-in visitors,
 * "role:<role>" for a role. Other labels (Pro: "purchased:*") are decided by the
 * filter qcld_wpbot_help_center/can_read. People who may edit the article can
 * always read it.
 *
 * Gated articles keep their title in lists and search, so visitors know they
 * exist; the content, excerpt and REST content are replaced by a sign-in note.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

use QuantumCloud\HelpCenter\Content\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Article access.
 */
final class Access {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'the_content', array( self::class, 'content' ), 999 );
		add_filter( 'get_the_excerpt', array( self::class, 'excerpt' ), 999, 2 );
		add_filter( 'rest_prepare_' . Post_Type::NAME, array( self::class, 'rest' ), 10, 2 );
	}

	/**
	 * Access labels of an article.
	 *
	 * @param int $post_id Article id.
	 * @return string[]
	 */
	public static function labels( $post_id ) {
		$labels = get_post_meta( (int) $post_id, '_wpbot_access', true );
		return is_array( $labels ) ? array_values( array_filter( array_map( 'strval', $labels ) ) ) : array();
	}

	/**
	 * Whether labels let a visitor read.
	 *
	 * @param string[] $labels    Labels.
	 * @param bool     $logged_in Whether the visitor is signed in.
	 * @param string[] $roles     The visitor's roles.
	 * @return bool|null True or false when the labels decide; null when an unknown label needs the filter.
	 */
	public static function decide( array $labels, $logged_in, array $roles ) {
		$labels = array_values( array_diff( $labels, array( 'public', '' ) ) );
		if ( array() === $labels ) {
			return true;
		}
		$unknown = false;
		foreach ( $labels as $label ) {
			if ( 'logged_in' === $label ) {
				if ( $logged_in ) {
					return true;
				}
			} elseif ( 0 === strpos( $label, 'role:' ) ) {
				if ( in_array( substr( $label, 5 ), $roles, true ) ) {
					return true;
				}
			} else {
				$unknown = true;
			}
		}
		return $unknown ? null : false;
	}

	/**
	 * Whether the current visitor may read an article.
	 *
	 * @param int|\WP_Post $post Article.
	 * @return bool
	 */
	public static function can_read( $post ) {
		$post = get_post( $post );
		if ( ! $post || Post_Type::NAME !== $post->post_type ) {
			return true;
		}
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return true;
		}
		$labels  = self::labels( $post->ID );
		$user    = wp_get_current_user();
		$allowed = self::decide( $labels, is_user_logged_in(), $user ? (array) $user->roles : array() );
		/**
		 * Whether the current visitor may read an article (Pro adds member and buyer labels).
		 *
		 * @param bool|null $allowed Decision from the built-in labels; null when only unknown labels did not match.
		 * @param \WP_Post  $post    Article.
		 * @param string[]  $labels  Access labels.
		 */
		return (bool) apply_filters( 'qcld_wpbot_help_center/can_read', null === $allowed ? false : $allowed, $post, $labels );
	}

	/**
	 * The note shown instead of a gated article.
	 *
	 * @param \WP_Post $post Article.
	 * @return string
	 */
	public static function note( $post ) {
		$out = '<div class="wpbot-hc__gated"><p>' . esc_html__( 'This article is for signed-in customers.', 'chatbot' ) . '</p>';
		if ( ! is_user_logged_in() ) {
			$out .= '<p><a class="wpbot-hc__btn is-solid" href="' . esc_url( wp_login_url( (string) get_permalink( $post ) ) ) . '">' . esc_html__( 'Sign in to read', 'chatbot' ) . '</a></p>';
		}
		return $out . '</div>';
	}

	/**
	 * Replace the content of a gated article (pages, feeds, theme loops).
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function content( $content ) {
		$post = get_post();
		if ( $post && Post_Type::NAME === $post->post_type && ! self::can_read( $post ) ) {
			return self::note( $post );
		}
		return $content;
	}

	/**
	 * No excerpt for a gated article.
	 *
	 * @param string        $excerpt Excerpt.
	 * @param \WP_Post|null $post    Post.
	 * @return string
	 */
	public static function excerpt( $excerpt, $post = null ) {
		$post = get_post( $post );
		if ( $post && Post_Type::NAME === $post->post_type && ! self::can_read( $post ) ) {
			return __( 'This article is for signed-in customers.', 'chatbot' );
		}
		return $excerpt;
	}

	/**
	 * No content in REST responses for visitors who may not read the article.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param \WP_Post          $post     Article.
	 * @return \WP_REST_Response
	 */
	public static function rest( $response, $post ) {
		if ( self::can_read( $post ) ) {
			return $response;
		}
		$data = $response->get_data();
		foreach ( array( 'content', 'excerpt' ) as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ]['rendered']  = '';
				$data[ $field ]['protected'] = true;
				unset( $data[ $field ]['raw'], $data[ $field ]['block_version'] );
			}
		}
		$response->set_data( $data );
		return $response;
	}
}
