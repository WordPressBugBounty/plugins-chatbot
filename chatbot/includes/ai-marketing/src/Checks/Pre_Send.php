<?php
/**
 * Pre-send check (PLAN.md W9): problems to fix before a campaign goes out.
 * Each problem has a level (error blocks sending; warning only warns).
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Pre-send check.
 */
final class Pre_Send {

	/**
	 * Words that often send email to spam.
	 *
	 * @var string[]
	 */
	const SPAM_WORDS = array( 'free money', '100% free', 'act now', 'click here', 'buy now', 'winner', 'guarantee', 'risk-free', 'no cost', 'cash bonus', 'urgent', 'limited time only', 'order now', '$$$' );

	/**
	 * Check an email (pure; unit tested).
	 *
	 * @param array $email   subject, preview_text, content_html.
	 * @param array $context company_address.
	 * @return array[] Each: level (error|warning), code, message.
	 */
	public static function check( array $email, array $context ) {
		$out     = array();
		$subject = trim( (string) ( isset( $email['subject'] ) ? $email['subject'] : '' ) );
		$html    = (string) ( isset( $email['content_html'] ) ? $email['content_html'] : '' );
		$text    = trim( wp_strip_all_tags( $html ) );
		if ( '' === $subject ) {
			$out[] = self::item( 'error', 'subject', __( 'Add a subject line.', 'chatbot' ) );
		} elseif ( strlen( $subject ) > 78 ) {
			$out[] = self::item( 'warning', 'subject_long', __( 'The subject is long and may be cut off on phones (keep it under about 60 characters).', 'chatbot' ) );
		}
		if ( '' !== $subject && strtoupper( $subject ) === $subject && preg_match( '/[A-Z]{4}/', $subject ) ) {
			$out[] = self::item( 'warning', 'subject_caps', __( 'A subject in capital letters looks like spam.', 'chatbot' ) );
		}
		if ( substr_count( $subject, '!' ) > 1 ) {
			$out[] = self::item( 'warning', 'subject_bang', __( 'Several exclamation marks look like spam.', 'chatbot' ) );
		}
		if ( '' === $text ) {
			$out[] = self::item( 'error', 'empty', __( 'The email is empty.', 'chatbot' ) );
		}
		$found = array();
		foreach ( self::SPAM_WORDS as $word ) {
			if ( false !== stripos( $subject . ' ' . $text, $word ) ) {
				$found[] = $word;
			}
		}
		if ( $found ) {
			/* translators: %s: words. */
			$out[] = self::item( 'warning', 'spam_words', sprintf( __( 'These words often send email to spam: %s', 'chatbot' ), implode( ', ', $found ) ) );
		}
		if ( preg_match_all( '/<img\b(?![^>]*\balt=)[^>]*>/i', $html ) ) {
			$out[] = self::item( 'warning', 'img_alt', __( 'Some images have no alt text (shown when images are blocked).', 'chatbot' ) );
		}
		if ( preg_match( '/href=["\'](?!https?:|mailto:|tel:|#|\{)[^"\']*["\']/i', $html ) ) {
			$out[] = self::item( 'warning', 'links', __( 'Some links do not start with https://, so they may not work in email.', 'chatbot' ) );
		}
		if ( '' === trim( (string) ( isset( $context['company_address'] ) ? $context['company_address'] : '' ) ) ) {
			$out[] = self::item( 'warning', 'address', __( 'Add your postal address in Settings. Anti-spam laws (CAN-SPAM, GDPR) expect it in marketing emails.', 'chatbot' ) );
		}
		if ( '' === trim( (string) ( isset( $email['preview_text'] ) ? $email['preview_text'] : '' ) ) ) {
			$out[] = self::item( 'warning', 'preview', __( 'Add preview text: it shows next to the subject in most inboxes.', 'chatbot' ) );
		}
		return $out;
	}

	/**
	 * One finding.
	 *
	 * @param string $level   error|warning.
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @return array
	 */
	private static function item( $level, $code, $message ) {
		return array(
			'level'   => $level,
			'code'    => $code,
			'message' => $message,
		);
	}
}
