<?php
/**
 * Domain health check (PLAN.md D1): SPF, DKIM (common selectors) and DMARC
 * records for the From domain, with plain advice. DNS lookups only; nothing
 * leaves the site.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Checks;

use QuantumCloud\AiMarketing\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Domain check.
 */
final class Domain {

	const SELECTORS = array( 'default', 'google', 'selector1', 'selector2', 'k1', 's1', 'mail', 'dkim', 'mandrill', 'smtp' );

	/**
	 * Check the From domain (cached for an hour).
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array domain, spf, dkim, dmarc (each: ok, record, advice).
	 */
	public static function check( $fresh = false ) {
		$email  = (string) Settings::get( 'from_email' );
		$domain = strtolower( (string) substr( strrchr( $email, '@' ), 1 ) );
		$key    = 'qcld_mk_domain_' . md5( $domain );
		$cached = $fresh ? false : get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$txt   = self::txt( $domain );
		$dmarc = self::txt( '_dmarc.' . $domain );
		$dkim  = '';
		foreach ( self::SELECTORS as $selector ) {
			$found = self::find( self::txt( $selector . '._domainkey.' . $domain ), 'v=DKIM1' );
			if ( '' !== $found ) {
				$dkim = $selector . ': ' . $found;
				break;
			}
		}
		$result = self::evaluate( $domain, self::find( $txt, 'v=spf1' ), $dkim, self::find( $dmarc, 'v=DMARC1' ) );
		set_transient( $key, $result, HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Turn records into advice (pure; unit tested).
	 *
	 * @param string $domain Domain.
	 * @param string $spf    SPF record or ''.
	 * @param string $dkim   DKIM record or ''.
	 * @param string $dmarc  DMARC record or ''.
	 * @return array
	 */
	public static function evaluate( $domain, $spf, $dkim, $dmarc ) {
		return array(
			'domain' => $domain,
			'spf'    => array(
				'ok'     => '' !== $spf,
				'record' => $spf,
				'advice' => '' !== $spf ? __( 'SPF is set. If you send through a service (SMTP, Amazon SES, Brevo…), make sure it is included.', 'chatbot' ) : __( 'No SPF record. Add a TXT record such as "v=spf1 include:_spf.yourmailhost.com ~all" (your email or hosting company gives the exact value).', 'chatbot' ),
			),
			'dkim'   => array(
				'ok'     => '' !== $dkim,
				'record' => $dkim,
				'advice' => '' !== $dkim ? __( 'A DKIM key was found.', 'chatbot' ) : __( 'No DKIM key found on common names. Your email or sending service gives you a DKIM record to add; it greatly helps delivery.', 'chatbot' ),
			),
			'dmarc'  => array(
				'ok'     => '' !== $dmarc,
				'record' => $dmarc,
				'advice' => '' !== $dmarc ? __( 'DMARC is set.', 'chatbot' ) : __( 'No DMARC record. Add a TXT record named _dmarc with "v=DMARC1; p=none; rua=mailto:you@yourdomain" to start.', 'chatbot' ),
			),
		);
	}

	/**
	 * TXT records of a host.
	 *
	 * @param string $host Host.
	 * @return string[]
	 */
	private static function txt( $host ) {
		if ( '' === $host || ! function_exists( 'dns_get_record' ) ) {
			return array();
		}
		$records = @dns_get_record( $host, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS failures are expected.
		$out     = array();
		foreach ( is_array( $records ) ? $records : array() as $record ) {
			$out[] = isset( $record['txt'] ) ? (string) $record['txt'] : implode( '', isset( $record['entries'] ) ? (array) $record['entries'] : array() );
		}
		return $out;
	}

	/**
	 * The first record starting with a prefix.
	 *
	 * @param string[] $records Records.
	 * @param string   $prefix  Prefix.
	 * @return string
	 */
	private static function find( array $records, $prefix ) {
		foreach ( $records as $record ) {
			if ( 0 === stripos( trim( $record ), $prefix ) ) {
				return trim( $record );
			}
		}
		return '';
	}
}
