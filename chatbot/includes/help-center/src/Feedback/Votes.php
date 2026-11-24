<?php
/**
 * Helpful votes with a reason, and view counts (HC1-10, plan 5.1 "Feedback").
 *
 * REST (public, no nonce, so cached pages keep working):
 * - POST wpbot-help-center/v1/vote {post, value: yes|no} counts a vote. One vote
 *   per visitor and article for a day (a changed vote moves the count). The
 *   answer carries a token for the reason.
 * - POST wpbot-help-center/v1/feedback {post, token, reason, comment} adds the
 *   reason of a "no" vote. It joins the chatbot's knowledge gaps (reason
 *   not_helpful): the visitor's words when given, otherwise the article title.
 * - POST wpbot-help-center/v1/view {post} counts a view (the page script sends
 *   it once per visit, so cached pages count too). Editors and bots are skipped.
 *
 * Visitors are told apart by a hash of their IP address and browser; nothing
 * personal is stored. Each address may send a limited number of requests an hour.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Feedback;

use QuantumCloud\HelpCenter\Access;
use QuantumCloud\HelpCenter\Content\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Votes and views.
 */
final class Votes {

	/**
	 * REST namespace.
	 */
	const NS = 'wpbot-help-center/v1';

	/**
	 * Requests an address may send in an hour.
	 */
	const LIMIT = 60;

	/**
	 * Longest comment kept (the gap list keeps questions up to 255 characters).
	 */
	const COMMENT_MAX = 200;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Reasons for a "no" vote.
	 *
	 * @return array<string,string> Key => label.
	 */
	public static function reasons() {
		$reasons = array(
			'missing'  => __( 'It doesn’t answer my question', 'chatbot' ),
			'unclear'  => __( 'It’s hard to understand', 'chatbot' ),
			'broken'   => __( 'The steps didn’t work', 'chatbot' ),
			'outdated' => __( 'It’s out of date', 'chatbot' ),
		);
		/**
		 * Filters the reasons offered after a "no" vote.
		 *
		 * @param array<string,string> $reasons Key => label.
		 */
		return (array) apply_filters( 'qcld_wpbot_help_center/vote_reasons', $reasons );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function routes() {
		$post = array(
			'type'     => 'integer',
			'required' => true,
			'minimum'  => 1,
		);
		register_rest_route(
			self::NS,
			'/vote',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'vote' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post'  => $post,
					'value' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'yes', 'no' ),
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'feedback' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post'    => $post,
					'token'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'reason'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'comment' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/view',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'view' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'post' => $post ),
			)
		);
	}

	/**
	 * Count a vote.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function vote( $request ) {
		$post = self::article( (int) $request['post'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! self::allow() ) {
			return self::too_many();
		}
		$value    = 'yes' === $request['value'] ? 'yes' : 'no';
		$key      = 'qcld_wpbot_hc_vote_' . md5( self::visitor() . '|' . $post->ID );
		$previous = (string) get_transient( $key );
		if ( $previous !== $value ) {
			if ( 'yes' === $previous || 'no' === $previous ) {
				self::bump( $post->ID, '_wpbot_helpful_' . $previous, -1 );
			}
			self::bump( $post->ID, '_wpbot_helpful_' . $value, 1 );
			set_transient( $key, $value, DAY_IN_SECONDS );
			/**
			 * A helpful vote was counted (analytics listen here).
			 *
			 * @param int    $post_id  Article id.
			 * @param string $value    yes or no.
			 * @param string $previous The visitor's earlier vote today, or ''.
			 */
			do_action( 'qcld_wpbot_help_center/voted', $post->ID, $value, $previous );
		}
		return rest_ensure_response(
			array(
				'ok'    => true,
				'token' => 'no' === $value ? self::token( $post->ID, self::bucket() ) : '',
			)
		);
	}

	/**
	 * Add the reason of a "no" vote.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function feedback( $request ) {
		$post = self::article( (int) $request['post'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! self::token_ok( $post->ID, (string) $request['token'] ) ) {
			return new \WP_Error( 'wpbot_help_center_token', __( 'Please vote again.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$used = 'qcld_wpbot_hc_why_' . md5( (string) $request['token'] . '|' . self::visitor() );
		if ( get_transient( $used ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}
		if ( ! self::allow() ) {
			return self::too_many();
		}
		set_transient( $used, 1, 2 * HOUR_IN_SECONDS );
		$reason  = self::reason( (string) $request['reason'] );
		$comment = self::comment( (string) $request['comment'] );
		if ( '' !== $reason ) {
			$counts            = get_post_meta( $post->ID, '_wpbot_helpful_reasons', true );
			$counts            = is_array( $counts ) ? $counts : array();
			$counts[ $reason ] = ( isset( $counts[ $reason ] ) ? (int) $counts[ $reason ] : 0 ) + 1;
			update_post_meta( $post->ID, '_wpbot_helpful_reasons', $counts );
		}
		$text = self::gap_text( get_the_title( $post ), $comment );
		do_action( 'qcld_wpbot_chat/knowledge_gap', $text, 'not_helpful', (object) array( 'language' => self::language() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The chatbot module's action.
		/**
		 * A "no" vote got its reason (analytics and Pro ticket follow-ups listen here).
		 *
		 * @param int    $post_id Article id.
		 * @param string $reason  Reason key or ''.
		 * @param string $comment The visitor's words or ''.
		 */
		do_action( 'qcld_wpbot_help_center/vote_reason', $post->ID, $reason, $comment );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Count a view.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function view( $request ) {
		$post = self::article( (int) $request['post'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( current_user_can( 'edit_post', $post->ID ) || self::is_bot( $agent ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}
		if ( ! self::allow( 'view', 300 ) ) {
			return self::too_many();
		}
		self::bump( $post->ID, '_wpbot_views', 1 );
		/**
		 * An article view was counted (analytics listen here).
		 *
		 * @param int $post_id Article id.
		 */
		do_action( 'qcld_wpbot_help_center/viewed', $post->ID );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Gap text for a "no" vote: the visitor's words, or the article title.
	 * A one-word title gets a question mark so the gap list keeps it.
	 *
	 * @param string $title   Article title.
	 * @param string $comment Visitor's words.
	 * @return string
	 */
	public static function gap_text( $title, $comment ) {
		$text = '' !== trim( $comment ) ? trim( $comment ) : trim( wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) ) );
		if ( false === strpos( $text, ' ' ) && false === strpos( $text, '?' ) ) {
			$text .= '?';
		}
		return $text;
	}

	/**
	 * A known reason key, or ''.
	 *
	 * @param string $reason Raw.
	 * @return string
	 */
	public static function reason( $reason ) {
		$reason = sanitize_key( $reason );
		return array_key_exists( $reason, self::reasons() ) ? $reason : '';
	}

	/**
	 * Clean a comment: plain text on one line, at most COMMENT_MAX characters.
	 *
	 * @param string $comment Raw.
	 * @return string
	 */
	public static function comment( $comment ) {
		$comment = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $comment ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $comment, 0, self::COMMENT_MAX ) : substr( $comment, 0, self::COMMENT_MAX );
	}

	/**
	 * Whether a user agent looks like a crawler or a link preview.
	 *
	 * @param string $agent User agent.
	 * @return bool
	 */
	public static function is_bot( $agent ) {
		return '' === trim( $agent ) || 1 === preg_match( '/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse|curl|wget|python|httpclient/i', $agent );
	}

	/**
	 * A published article the visitor may read.
	 *
	 * @param int $id Post id.
	 * @return \WP_Post|\WP_Error
	 */
	private static function article( $id ) {
		$post = get_post( $id );
		if ( ! $post || Post_Type::NAME !== $post->post_type || 'publish' !== $post->post_status || ! Access::can_read( $post ) ) {
			return new \WP_Error( 'wpbot_help_center_no_article', __( 'Article not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * Add to a counter.
	 *
	 * @param int    $post_id Article id.
	 * @param string $key     Meta key.
	 * @param int    $by      Step.
	 * @return void
	 */
	private static function bump( $post_id, $key, $by ) {
		$value = max( 0, (int) get_post_meta( $post_id, $key, true ) + $by );
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Hash that tells visitors apart (IP address and browser), without storing either.
	 *
	 * @return string
	 */
	private static function visitor() {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return wp_hash( $ip . '|' . $agent, 'nonce' );
	}

	/**
	 * Count a request from this address; false when it sent too many this hour.
	 *
	 * @param string $kind  Counter name.
	 * @param int    $limit Requests an hour.
	 * @return bool
	 */
	private static function allow( $kind = 'vote', $limit = self::LIMIT ) {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'qcld_wpbot_hc_rate_' . $kind . '_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Too many requests.
	 *
	 * @return \WP_Error
	 */
	private static function too_many() {
		return new \WP_Error( 'wpbot_help_center_rate', __( 'Too many requests. Please try again later.', 'chatbot' ), array( 'status' => 429 ) );
	}

	/**
	 * Current hour.
	 *
	 * @return int
	 */
	private static function bucket() {
		return (int) floor( time() / HOUR_IN_SECONDS );
	}

	/**
	 * Token for the reason of a "no" vote.
	 *
	 * @param int $post_id Article id.
	 * @param int $bucket  Hour.
	 * @return string
	 */
	private static function token( $post_id, $bucket ) {
		return substr( wp_hash( 'wpbot-hc-why|' . $post_id . '|' . $bucket . '|' . self::visitor(), 'nonce' ), 0, 20 );
	}

	/**
	 * Whether a token is from this hour or the one before, for this visitor and article.
	 *
	 * @param int    $post_id Article id.
	 * @param string $token   Token.
	 * @return bool
	 */
	private static function token_ok( $post_id, $token ) {
		$now = self::bucket();
		return '' !== $token && ( hash_equals( self::token( $post_id, $now ), $token ) || hash_equals( self::token( $post_id, $now - 1 ), $token ) );
	}

	/**
	 * Two-letter language of the request.
	 *
	 * @return string
	 */
	private static function language() {
		return substr( (string) determine_locale(), 0, 2 );
	}
}
