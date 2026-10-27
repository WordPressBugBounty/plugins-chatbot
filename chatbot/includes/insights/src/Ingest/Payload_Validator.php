<?php
/**
 * Validates and normalizes a decompressed tracker batch.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Ingest;

defined( 'ABSPATH' ) || exit;

final class Payload_Validator {

	const UUID_RE     = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
	const MAX_EVENTS  = 20000;
	const MAX_CLICKS  = 500;
	const MAX_SIGNALS = 50;
	const SIGNAL_TYPES = array( 'rage', 'dead', 'error', 'custom' );
	const MAX_EXT_NS    = 8;
	const MAX_EXT_ITEMS = 200;
	const MAX_EXT_BYTES = 32768;

	/**
	 * Last error code.
	 *
	 * @var string
	 */
	public $error = '';

	/**
	 * Validate a decoded payload.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array<string,mixed>|null Normalized payload or null when invalid.
	 */
	public function validate( $data ) {
		$this->error = '';
		if ( ! is_array( $data ) ) {
			return $this->fail( 'not_object' );
		}
		if ( 1 !== (int) ( $data['v'] ?? 0 ) ) {
			return $this->fail( 'bad_version' );
		}

		foreach ( array( 'sid', 'pv' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) || ! preg_match( self::UUID_RE, $data[ $key ] ) ) {
				return $this->fail( 'bad_' . $key );
			}
		}
		$vid = '';
		if ( isset( $data['vid'] ) && is_string( $data['vid'] ) && preg_match( self::UUID_RE, $data['vid'] ) ) {
			$vid = $data['vid'];
		}

		$seq = isset( $data['seq'] ) ? (int) $data['seq'] : -1;
		if ( $seq < 0 || $seq > 99999 ) {
			return $this->fail( 'bad_seq' );
		}

		// Recording events are optional: visits that are not recorded only send clicks, scroll and signals.
		$events = $data['events'] ?? array();
		if ( ! is_array( $events ) || count( $events ) > self::MAX_EVENTS ) {
			return $this->fail( 'bad_events' );
		}
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) || ! isset( $event['type'], $event['timestamp'] ) || ! is_int( $event['type'] ) || ! is_numeric( $event['timestamp'] ) ) {
				return $this->fail( 'bad_event' );
			}
		}

		$meta = is_array( $data['meta'] ?? null ) ? $data['meta'] : array();
		$url  = $this->clean_url( (string) ( $meta['url'] ?? '' ) );
		if ( '' === $url ) {
			return $this->fail( 'bad_url' );
		}

		$utm = is_array( $meta['utm'] ?? null ) ? $meta['utm'] : array();

		$clicks = $data['clicks'] ?? array();
		if ( ! is_array( $clicks ) || count( $clicks ) > self::MAX_CLICKS ) {
			return $this->fail( 'bad_clicks' );
		}
		$signals = $data['signals'] ?? array();
		if ( ! is_array( $signals ) || count( $signals ) > self::MAX_SIGNALS ) {
			return $this->fail( 'bad_signals' );
		}
		$ext = $this->clean_ext( $data['ext'] ?? array() );
		if ( null === $ext ) {
			return $this->fail( 'bad_ext' );
		}

		return array(
			'sid'       => $data['sid'],
			'vid'       => $vid,
			'pv'        => $data['pv'],
			'seq'       => $seq,
			'events'    => count( $events ),
			'url'       => $url,
			'title'     => substr( sanitize_text_field( (string) ( $meta['title'] ?? '' ) ), 0, 255 ),
			'referrer'  => $this->referrer_host( (string) ( $meta['ref'] ?? '' ) ),
			'vw'        => $this->clamp( $meta['vw'] ?? 0, 0, 10000 ),
			'vh'        => $this->clamp( $meta['vh'] ?? 0, 0, 10000 ),
			'utm'       => array(
				'source'   => substr( sanitize_text_field( (string) ( $utm['source'] ?? '' ) ), 0, 100 ),
				'medium'   => substr( sanitize_text_field( (string) ( $utm['medium'] ?? '' ) ), 0, 100 ),
				'campaign' => substr( sanitize_text_field( (string) ( $utm['campaign'] ?? '' ) ), 0, 100 ),
			),
			'duration'  => $this->clamp( $data['t'] ?? 0, 0, 86400000 ),
			'scroll'    => $this->clamp( $data['scroll'] ?? 0, 0, 100 ),
			'doc_h'     => $this->clamp( $data['docH'] ?? 0, 0, 1000000 ),
			'clicks'    => $this->clean_clicks( $clicks ),
			'signals'   => $this->clean_signals( $signals ),
			'ext'       => $ext,
		);
	}

	/**
	 * Feature data sent with a batch: an object of namespaces (e.g. "forms")
	 * each holding a list of items. Only the shape and size are checked here;
	 * the feature that owns a namespace validates its items.
	 *
	 * @param mixed $ext Raw ext.
	 * @return array<string,array<int,array<string,mixed>>>|null Null when too big or malformed.
	 */
	private function clean_ext( $ext ) {
		if ( array() === $ext || null === $ext ) {
			return array();
		}
		if ( ! is_array( $ext ) || count( $ext ) > self::MAX_EXT_NS ) {
			return null;
		}
		$json = wp_json_encode( $ext );
		if ( false === $json || strlen( $json ) > self::MAX_EXT_BYTES ) {
			return null;
		}
		$out = array();
		foreach ( $ext as $ns => $items ) {
			if ( ! is_string( $ns ) || ! preg_match( '/^[a-z][a-z0-9_.-]{0,31}$/', $ns ) || ! is_array( $items ) || count( $items ) > self::MAX_EXT_ITEMS ) {
				return null;
			}
			$list = array();
			foreach ( $items as $item ) {
				if ( is_array( $item ) ) {
					$list[] = $item;
				}
			}
			if ( $list ) {
				$out[ $ns ] = $list;
			}
		}
		return $out;
	}

	/**
	 * Normalize clicks; malformed items are skipped.
	 *
	 * @param array<int,mixed> $clicks Raw clicks.
	 * @return array<int,array<string,mixed>>
	 */
	private function clean_clicks( array $clicks ) {
		$out = array();
		foreach ( $clicks as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$selector = $this->clean_selector( $c['s'] ?? '' );
			if ( '' === $selector ) {
				continue;
			}
			$x     = $this->clamp( $c['x'] ?? 0, 0, 9999 );
			$y     = $this->clamp( $c['y'] ?? 0, 0, 9999 );
			$out[] = array(
				'selector' => $selector,
				'off_x'    => $x,
				'off_y'    => $y,
				'bin_x'    => intdiv( $x, 1000 ),
				'bin_y'    => intdiv( $y, 1000 ),
				'page_x'   => $this->clamp( $c['px'] ?? 0, 0, 1000000 ),
				'page_y'   => $this->clamp( $c['py'] ?? 0, 0, 1000000 ),
				'doc_w'    => $this->clamp( $c['dw'] ?? 0, 0, 1000000 ),
				'doc_h'    => $this->clamp( $c['dh'] ?? 0, 0, 1000000 ),
				'ts'       => $this->clamp( $c['t'] ?? 0, 0, 86400000 ),
				'dead'     => empty( $c['d'] ) ? 0 : 1,
				'rage'     => empty( $c['r'] ) ? 0 : 1,
			);
		}
		return $out;
	}

	/**
	 * Normalize frustration signals and custom events.
	 *
	 * @param array<int,mixed> $signals Raw signals.
	 * @return array<int,array<string,mixed>>
	 */
	private function clean_signals( array $signals ) {
		$out = array();
		foreach ( $signals as $sig ) {
			if ( ! is_array( $sig ) || ! in_array( $sig['k'] ?? '', \QuantumCloud\Insights\Storage\Signal_Types::browser_types(), true ) ) {
				continue;
			}
			$type = $sig['k'];
			$raw  = is_array( $sig['d'] ?? null ) ? $sig['d'] : array();
			$data = array();
			if ( 'error' === $type ) {
				$data = array(
					'message' => substr( sanitize_text_field( (string) ( $raw['m'] ?? '' ) ), 0, 300 ),
					'source'  => substr( sanitize_text_field( (string) ( $raw['f'] ?? '' ) ), 0, 255 ),
					'line'    => $this->clamp( $raw['l'] ?? 0, 0, 10000000 ),
					'column'  => $this->clamp( $raw['c'] ?? 0, 0, 10000000 ),
				);
			} elseif ( 'custom' === $type ) {
				$name = substr( sanitize_key( (string) ( $raw['n'] ?? '' ) ), 0, 64 );
				if ( '' === $name ) {
					continue;
				}
				$data = array( 'name' => $name );
			} elseif ( isset( $raw['n'] ) ) {
				$data = array( 'count' => $this->clamp( $raw['n'], 0, 1000 ) );
			}
			$out[] = array(
				'type'     => $type,
				'ts'       => $this->clamp( $sig['t'] ?? 0, 0, 86400000 ),
				'selector' => $this->clean_selector( $sig['s'] ?? '' ),
				'data'     => $data,
			);
		}
		return $out;
	}

	/**
	 * Keep only characters the tracker's CSS selectors use.
	 *
	 * @param mixed $selector Raw selector.
	 * @return string
	 */
	private function clean_selector( $selector ) {
		if ( ! is_string( $selector ) ) {
			return '';
		}
		$selector = preg_replace( '/[^A-Za-z0-9_\-#.:()>\s]/', '', $selector );
		return substr( trim( (string) $selector ), 0, 255 );
	}

	/**
	 * Keep scheme, host, path and allow-listed query params; reject other hosts.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public function clean_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
			return '';
		}
		if ( ! in_array( strtolower( $parts['host'] ), $this->allowed_hosts(), true ) ) {
			return '';
		}
		$clean = $parts['scheme'] . '://' . strtolower( $parts['host'] ) . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' ) . ( $parts['path'] ?? '/' );

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			/**
			 * Query parameters kept in stored URLs.
			 *
			 * @param string[] $params Allowed keys.
			 */
			$allowed = (array) apply_filters( 'qcin/allowed_query_params', array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'p', 'page_id', 'post_type' ) );
			$kept    = array_intersect_key( $query, array_flip( $allowed ) );
			if ( $kept ) {
				ksort( $kept );
				$clean .= '?' . http_build_query( $kept );
			}
		}
		return esc_url_raw( $clean );
	}

	/**
	 * Hosts accepted for page URLs and Origin checks.
	 *
	 * @return string[]
	 */
	public function allowed_hosts() {
		$hosts = array_filter(
			array(
				wp_parse_url( home_url(), PHP_URL_HOST ),
				wp_parse_url( site_url(), PHP_URL_HOST ),
			)
		);
		/**
		 * Extra hosts accepted by the ingest endpoint (multi-domain setups).
		 *
		 * @param string[] $hosts Hosts.
		 */
		$hosts = (array) apply_filters( 'qcin/allowed_origins', $hosts );
		return array_values( array_unique( array_map( 'strtolower', $hosts ) ) );
	}

	/**
	 * Host of an external referrer, empty for internal or invalid ones.
	 *
	 * @param string $ref Referrer URL.
	 * @return string
	 */
	private function referrer_host( $ref ) {
		$host = strtolower( (string) wp_parse_url( $ref, PHP_URL_HOST ) );
		if ( '' === $host || in_array( $host, $this->allowed_hosts(), true ) ) {
			return '';
		}
		return substr( sanitize_text_field( $host ), 0, 191 );
	}

	/**
	 * Clamp a number.
	 *
	 * @param mixed $value Value.
	 * @param int   $min   Min.
	 * @param int   $max   Max.
	 * @return int
	 */
	private function clamp( $value, $min, $max ) {
		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Record an error.
	 *
	 * @param string $code Code.
	 * @return null
	 */
	private function fail( $code ) {
		$this->error = $code;
		return null;
	}
}
