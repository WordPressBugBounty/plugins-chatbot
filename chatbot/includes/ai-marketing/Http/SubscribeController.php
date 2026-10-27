<?php
/**
 * Public subscribe route used by the [wpbot_subscribe] form.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Domain\ContactService;
use QuantumCloud\WPBot\CRM\Domain\GroupRepository;
use QuantumCloud\WPBot\CRM\Integrations\Shortcode;
use QuantumCloud\WPBot\CRM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * POST /subscribe — public (wp_rest nonce), honeypot `website_url`, 10 attempts per IP per 10 minutes.
 */
class SubscribeController extends RestController {

	const RATE_LIMIT  = 10;
	const RATE_WINDOW = 600;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/subscribe',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'subscribe' ),
				'permission_callback' => array( $this, 'public_permission' ),
			)
		);
	}

	/**
	 * Anyone may subscribe, but the request must carry a valid wp_rest nonce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function public_permission( $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( ! $nonce || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'wpbot_crm_bad_nonce', __( 'This form has expired. Please reload the page and try again.', 'chatbot' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Subscribe an email.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function subscribe( $request ) {
		if ( ! $this->within_rate_limit() ) {
			return $this->error( 'wpbot_crm_rate_limited', __( 'Too many attempts. Please wait a few minutes and try again.', 'chatbot' ), 429 );
		}

		$doi    = Settings::bool( 'double_opt_in' );
		$thanks = array(
			'success' => true,
			'message' => $doi
				? __( 'Thanks! Please check your inbox and click the link in our email to confirm your subscription.', 'chatbot' )
				: __( 'Thanks for subscribing!', 'chatbot' ),
		);

		// Bots fill every field; pretend it worked.
		if ( '' !== trim( (string) $request->get_param( 'website_url' ) ) ) {
			return $thanks;
		}

		$email = strtolower( trim( sanitize_email( (string) $request->get_param( 'email' ) ) ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return $this->error( 'wpbot_crm_invalid_email', __( 'Please enter a valid email address.', 'chatbot' ) );
		}

		$service  = new ContactService();
		$existing = $service->find_by_email( $email );
		$data     = array( 'email' => $email );
		$name     = $request->get_param( 'first_name' );
		if ( is_scalar( $name ) && '' !== trim( (string) $name ) ) {
			$data['first_name'] = (string) $name;
		}

		$id = $service->upsert( $data, 'shortcode', $this->group_ids( $request ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		// Existing pending (or unsubscribed, with double opt-in) contacts get a fresh confirmation email.
		if ( $existing && $doi ) {
			if ( 'unsubscribed' === $existing['status'] ) {
				$service->change_status( (int) $id, 'pending' );
			}
			if ( in_array( $existing['status'], array( 'pending', 'unsubscribed' ), true ) ) {
				$service->send_confirmation( $service->find( (int) $id ) );
			}
		}
		return $thanks;
	}

	/**
	 * Lists/tags from the form. Existing groups are joined by id, slug or name; missing ones are
	 * created only when the shortcode signature proves the site owner chose them.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int[]
	 */
	protected function group_ids( $request ) {
		$list   = sanitize_text_field( (string) $request->get_param( 'list' ) );
		$tag    = sanitize_text_field( (string) $request->get_param( 'tag' ) );
		$signed = hash_equals( Shortcode::sign( $list, $tag ), (string) $request->get_param( 'sig' ) );
		$groups = new GroupRepository();
		$ids    = array();
		foreach ( array(
			'list' => $list,
			'tag'  => $tag,
		) as $type => $value ) {
			foreach ( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) as $ref ) {
				$id = $this->resolve( $groups, $type, $ref, $signed );
				if ( $id ) {
					$ids[] = $id;
				}
			}
		}
		return $ids;
	}

	/**
	 * Group id for an id/slug/name reference.
	 *
	 * @param GroupRepository $groups Repository.
	 * @param string          $type   list|tag.
	 * @param string          $ref    Reference.
	 * @param bool            $create Create when missing.
	 * @return int
	 */
	protected function resolve( GroupRepository $groups, $type, $ref, $create ) {
		if ( ctype_digit( $ref ) && $groups->find_typed( (int) $ref, $type ) ) {
			return (int) $ref;
		}
		$group = $groups->find_by_slug( $type, sanitize_title( $ref ) );
		if ( ! $group ) {
			$group = $groups->find_by_slug( $type, GroupRepository::slugify( $ref ) );
		}
		if ( $group ) {
			return (int) $group['id'];
		}
		return $create ? $groups->ensure( $type, $ref ) : 0;
	}

	/**
	 * Count this attempt against the visitor's IP; false when over the limit.
	 *
	 * @return bool
	 */
	protected function within_rate_limit() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$key = 'wpbot_crm_sub_' . md5( $ip );
		$now = time();
		$hit = get_transient( $key );
		if ( ! is_array( $hit ) || ! isset( $hit['n'], $hit['t'] ) || $now - (int) $hit['t'] >= self::RATE_WINDOW ) {
			$hit = array(
				'n' => 0,
				't' => $now,
			);
		}
		if ( (int) $hit['n'] >= self::RATE_LIMIT ) {
			return false;
		}
		++$hit['n'];
		set_transient( $key, $hit, max( 1, self::RATE_WINDOW - ( $now - (int) $hit['t'] ) ) );
		return true;
	}
}
