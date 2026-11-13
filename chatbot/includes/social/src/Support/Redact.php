<?php
/**
 * Removes tokens and secrets from any text before it is logged or shown.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Token redactor (build-brief A4, QA #11). Runs on every log entry, error
 * and notice.
 */
final class Redact {

	/**
	 * Replace secrets in a string.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function secrets( $text ) {
		$patterns = array(
			// Query or form values: any *_token, secrets, proofs and OAuth code/state (QA #41).
			'/\b([a-z_]*token|appsecret_proof|client_secret|state)=([^&\s"\']+)/i' => '$1=[redacted]',
			// OAuth authorization codes are long; short values ("error code=100") stay readable.
			'/\b(code)=([^&\s"\']{16,})/i'                 => '$1=[redacted]',
			// JSON keys: any *_token, secrets and proofs.
			'/(["\'](?:[a-z_]*token|appsecret_proof|client_secret)["\']\s*:\s*["\'])[^"\']+/i' => '$1[redacted]',
			'/\b(Bearer|OAuth)\s+[A-Za-z0-9\-._~+\/]+=*/i' => '$1 [redacted]',
			'/\bbot\d{5,}:[A-Za-z0-9_-]{20,}/'             => 'bot[redacted]',
			// Bare Meta tokens: Facebook (EAA), Instagram (IGAA/IGQ) and Threads (TH).
			'/\b(?:EAA|IGAA|IGQ|TH)[A-Za-z0-9_-]{20,}/'    => '[redacted]',
		);
		return (string) preg_replace( array_keys( $patterns ), array_values( $patterns ), (string) $text );
	}
}
