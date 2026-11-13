<?php
/**
 * Email when a post fails for good or needs the owner to check
 * (Settings › Publishing › Failure email). One email per post per hour.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Settings;
use QuantumCloud\Social\Support\Redact;

defined( 'ABSPATH' ) || exit;

/**
 * Failure emails.
 */
final class Notifications {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_social/publish_failed', array( __CLASS__, 'failed' ), 10, 2 );
		add_action( 'qcld_wpbot_social/manual_due', array( __CLASS__, 'manual_due' ), 10, 2 );
	}

	/**
	 * Send the failure email.
	 *
	 * @param array $row    Target row.
	 * @param array $fields Saved fields.
	 * @return bool Whether an email was sent.
	 */
	public static function failed( $row, $fields ) {
		if ( ! Settings::get( 'failure_email' ) ) {
			return false;
		}
		$key = 'qcld_wpbot_social_mailed_' . (int) $row['post_id'];
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, HOUR_IN_SECONDS );
		$to    = sanitize_email( (string) Settings::get( 'failure_email_to' ) );
		$to    = '' !== $to ? $to : (string) get_option( 'admin_email' );
		$post  = Post_Store::get( (int) $row['post_id'] );
		$title = $post && '' !== $post['title'] ? $post['title'] : __( '(untitled post)', 'chatbot' );
		$link  = admin_url( 'admin.php?page=wpbot-social&view=posts&status=failed' );
		/* translators: %s: post title. */
		$subject = sprintf( __( 'A social post needs your attention: %s', 'chatbot' ), wp_strip_all_tags( $title ) );
		$body    = sprintf(
			/* translators: 1: post title, 2: reason, 3: link. */
			__( "WPBot Social could not finish \"%1\$s\".\n\nReason: %2\$s\n\nOpen Posts to fix and retry:\n%3\$s", 'chatbot' ),
			wp_strip_all_tags( $title ),
			Redact::secrets( isset( $fields['error_message'] ) ? (string) $fields['error_message'] : '' ),
			$link
		);
		return (bool) wp_mail( $to, $subject, $body );
	}

	/**
	 * Remind the owner that a Post yourself copy is ready (one email per post).
	 *
	 * @param array $row     Target row.
	 * @param array $account Account.
	 * @return bool Whether an email was sent.
	 */
	public static function manual_due( $row, $account ) {
		$key = 'qcld_wpbot_social_manual_mailed_' . (int) $row['post_id'];
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, 12 * HOUR_IN_SECONDS );
		$post   = Post_Store::get( (int) $row['post_id'] );
		$author = $post && ! empty( $post['author_id'] ) ? get_userdata( (int) $post['author_id'] ) : false;
		$to     = $author && is_email( $author->user_email ) ? $author->user_email : (string) get_option( 'admin_email' );
		$title  = $post && '' !== $post['title'] ? $post['title'] : __( '(untitled post)', 'chatbot' );
		$link   = admin_url( 'admin.php?page=wpbot-social&view=manual' );
		/* translators: 1: network, 2: post title. */
		$subject = sprintf( __( 'Time to post on %1$s: %2$s', 'chatbot' ), isset( $account['name'] ) ? wp_strip_all_tags( (string) $account['name'] ) : '', wp_strip_all_tags( $title ) );
		$body    = sprintf(
			/* translators: 1: post title, 2: link. */
			__( "Your copy for \"%1\$s\" is ready. WPBot can't post to this network for you, so it has the text and image waiting.\n\nCopy, open the network, paste, then mark it as posted:\n%2\$s", 'chatbot' ),
			wp_strip_all_tags( $title ),
			$link
		);
		return (bool) wp_mail( $to, $subject, $body );
	}
}
