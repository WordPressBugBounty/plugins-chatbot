<?php
/**
 * Public endpoint that receives tracker batches: POST /wp-json/qcin/v1/ingest
 *
 * It does the minimum on the request (checks, one file write, a few row
 * updates); everything else runs later in background jobs.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Ingest;

use QuantumCloud\Insights\Module;
use QuantumCloud\Insights\Settings;
use QuantumCloud\Insights\Processing\Health_Check;
use QuantumCloud\Insights\Storage\Heatmap_Repository;
use QuantumCloud\Insights\Storage\Session_Repository;
use QuantumCloud\Insights\Storage\Signal_Repository;

defined( 'ABSPATH' ) || exit;

final class Rest_Controller {

	const NS               = 'qcin/v1';
	const MAX_BODY         = 524288;   // 512 KB compressed per request.
	const MAX_DECOMPRESSED = 5242880;  // 5 MB after decompression.
	const MAX_PV_BYTES     = 2097152;  // 2 MB compressed per pageview.
	const SESSION_TIMEOUT  = 1800;     // 30 minutes.

	/**
	 * Register the route.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/ingest',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'ingest' ),
				'permission_callback' => '__return_true', // Public by design; protected by token, origin, rate and size checks below.
			)
		);
	}

	/**
	 * Handle one batch.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function ingest( \WP_REST_Request $request ) {
		$module = Module::instance();

		if ( ! Settings::get( 'enabled' ) ) {
			return $this->reply( 'stop', 'disabled' );
		}

		if ( ! hash_equals( $module->site_token(), (string) $request->get_param( 'token' ) ) ) {
			return $this->reply( 'reject', 'token', 403 );
		}

		if ( ! $this->origin_ok() ) {
			return $this->reply( 'reject', 'origin', 403 );
		}

		$ua = Client_Info::user_agent();
		if ( Client_Info::is_bot( $ua ) ) {
			return $this->reply( 'stop', 'bot' );
		}

		$limiter = new Rate_Limiter();
		if ( ! $limiter->allow( Client_Info::ip() ) ) {
			return $this->reply( 'reject', 'rate', 429 );
		}

		$body = $request->get_body();
		if ( '' === $body || strlen( $body ) > self::MAX_BODY ) {
			return $this->reply( 'reject', 'size', 413 );
		}

		$json = $this->decompress( $body );
		if ( null === $json ) {
			return $this->reply( 'reject', 'decode', 400 );
		}
		$data = json_decode( $json, true );

		$validator = new Payload_Validator();
		$payload   = $validator->validate( $data );
		if ( null === $payload ) {
			return $this->reply( 'reject', $validator->error, 400 );
		}

		$repo  = new Session_Repository();
		$now   = current_time( 'mysql', true );
		$rec   = $payload['events'] > 0;
		$norec = '';

		// Session.
		$session = $repo->find_session( $payload['sid'] );
		if ( ! $session ) {
			$visitor_hash = '' !== $payload['vid'] ? hash_hmac( 'sha256', $payload['vid'], wp_salt( 'auth' ) ) : '';
			$entry_url_id = $repo->url_id( $payload['url'], $payload['title'] );
			$session_id   = $repo->create_session(
				array(
					'uuid'          => $payload['sid'],
					'visitor_hash'  => '' !== $visitor_hash ? $visitor_hash : null,
					'started_at'    => $now,
					'last_seen_at'  => $now,
					'device'        => Client_Info::device( $ua, $payload['vw'] ),
					'os'            => Client_Info::os( $ua ),
					'browser'       => Client_Info::browser( $ua ),
					'country'       => Client_Info::country(),
					'referrer_host' => $payload['referrer'],
					'utm_source'    => $payload['utm']['source'],
					'utm_medium'    => $payload['utm']['medium'],
					'utm_campaign'  => $payload['utm']['campaign'],
					'entry_url_id'  => $entry_url_id,
					'exit_url_id'   => $entry_url_id,
					'is_returning'  => $repo->visitor_seen( $visitor_hash ) ? 1 : 0,
				)
			);
			if ( ! $session_id ) {
				return $this->reply( 'reject', 'db', 500 );
			}
			$session = $repo->find_session( $payload['sid'] );
		} elseif ( strtotime( $session->last_seen_at . ' UTC' ) < time() - self::SESSION_TIMEOUT ) {
			// The browser kept an expired session id; ask it to start a new one.
			return $this->reply( 'restart', 'expired' );
		}

		// Daily recording cap: checked when a session sends its first recording chunk.
		// Clicks, scroll and signals are still collected for capped sessions.
		if ( $rec && ! (int) $session->has_recording ) {
			if ( $repo->count_recordings_since( gmdate( 'Y-m-d 00:00:00' ) ) >= (int) Settings::get( 'daily_cap' ) ) {
				$rec   = false;
				$norec = 'daily_cap';
			}
		}

		// Pageview.
		$pageview = $repo->find_pageview( $payload['pv'] );
		if ( ! $pageview ) {
			$repo->create_pageview(
				array(
					'session_id'   => (int) $session->id,
					'uuid'         => $payload['pv'],
					'url_id'       => $repo->url_id( $payload['url'], $payload['title'] ),
					'seq'          => min( 65535, (int) $session->pageviews ),
					'started_at'   => $now,
					'last_seen_at' => $now,
					'viewport_w'   => $payload['vw'],
					'viewport_h'   => $payload['vh'],
				)
			);
			$pageview = $repo->find_pageview( $payload['pv'] );
			if ( ! $pageview ) {
				return $this->reply( 'reject', 'db', 500 );
			}
		} elseif ( (int) $pageview->session_id !== (int) $session->id ) {
			return $this->reply( 'reject', 'mismatch', 400 );
		}

		if ( $rec && (int) $pageview->bytes + strlen( $body ) > self::MAX_PV_BYTES ) {
			$rec   = false;
			$norec = 'pageview_full';
		}

		$bytes = 0;
		if ( $rec ) {
			$written = $module->store()->put( $payload['sid'], $payload['pv'], $payload['seq'], $body );
			if ( false === $written ) {
				return $this->reply( 'reject', 'storage', 500 );
			}
			$bytes = strlen( $body );
		}
		$repo->touch( $pageview, $bytes, $now, $payload['duration'], $payload['scroll'], $session, $payload['doc_h'] );

		// Clicks and frustration signals.
		if ( $payload['clicks'] || $payload['signals'] ) {
			$heat = new Heatmap_Repository();
			$ids  = $heat->selector_ids(
				array_merge( array_column( $payload['clicks'], 'selector' ), array_column( $payload['signals'], 'selector' ) )
			);
			$heat->insert_clicks( $pageview, (string) $session->device, $payload['clicks'], $ids, $now );
			if ( $payload['signals'] ) {
				$bits = ( new Signal_Repository() )->insert( (int) $session->id, (int) $pageview->id, $payload['signals'], $ids, $now );
				$repo->add_flags( (int) $session->id, $bits );
				foreach ( $payload['signals'] as $signal ) {
					/**
					 * A frustration signal or custom event was stored.
					 *
					 * @param array<string,mixed> $signal   Type, ts (ms into the page), selector, data.
					 * @param object              $session  Session row.
					 * @param object              $pageview Pageview row.
					 */
					do_action( 'qcin/signal_recorded', $signal, $session, $pageview );
				}
			}
		}

		if ( $payload['ext'] ) {
			/**
			 * Feature data that came with a batch, after all checks passed.
			 * Each feature reads only its own namespace and validates every item.
			 *
			 * @param array<string,array<int,array>> $ext      Items by namespace.
			 * @param object                         $session  Session row.
			 * @param object                         $pageview Pageview row.
			 */
			do_action( 'qcin/ingest_ext', $payload['ext'], $session, $pageview );
		}

		// "norec" tells the browser to stop sending recording events but keep sending clicks.
		return '' !== $norec ? $this->reply( 'norec', $norec ) : $this->reply( 'ok', '' );
	}

	/**
	 * Gunzip with a size cap (stops gzip bombs). Plain JSON is accepted too.
	 *
	 * @param string $body Raw body.
	 * @return string|null
	 */
	private function decompress( $body ) {
		if ( "\x1f\x8b" !== substr( $body, 0, 2 ) ) {
			return ( '{' === substr( ltrim( $body ), 0, 1 ) ) ? $body : null;
		}
		if ( ! function_exists( 'inflate_init' ) ) {
			$out = @gzdecode( $body, self::MAX_DECOMPRESSED ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false === $out ? null : $out;
		}
		$ctx = inflate_init( ZLIB_ENCODING_GZIP );
		if ( false === $ctx ) {
			return null;
		}
		$out = '';
		foreach ( str_split( $body, 8192 ) as $piece ) {
			$chunk = @inflate_add( $ctx, $piece, ZLIB_SYNC_FLUSH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $chunk ) {
				return null;
			}
			$out .= $chunk;
			if ( strlen( $out ) > self::MAX_DECOMPRESSED ) {
				return null;
			}
		}
		$tail = @inflate_add( $ctx, '', ZLIB_FINISH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false !== $tail ) {
			$out .= $tail;
		}
		return strlen( $out ) > self::MAX_DECOMPRESSED ? null : $out;
	}

	/**
	 * Origin / Referer must match the site when the browser sends them.
	 *
	 * @return bool
	 */
	private function origin_ok() {
		$header = '';
		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}
		if ( '' === $header || 'null' === $header ) {
			return true; // Some privacy settings strip both; token and rate limits still apply.
		}
		$host = strtolower( (string) wp_parse_url( $header, PHP_URL_HOST ) );
		return in_array( $host, ( new Payload_Validator() )->allowed_hosts(), true );
	}

	/**
	 * Short JSON reply. `stop` tells the tracker to stop for this page,
	 * `norec` to stop recording but keep sending clicks, `restart` to start
	 * a new session.
	 *
	 * @param string $status ok|norec|stop|restart|reject.
	 * @param string $code   Reason.
	 * @param int    $http   HTTP status.
	 * @return \WP_REST_Response
	 */
	private function reply( $status, $code, $http = 200 ) {
		if ( 'reject' === $status && ! in_array( $code, array( 'token', 'origin', 'rate' ), true ) ) {
			Health_Check::count_ingest_error();
		}
		$response = new \WP_REST_Response(
			array(
				's' => $status,
				'c' => $code,
			),
			$http
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
