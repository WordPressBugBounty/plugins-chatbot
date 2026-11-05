<?php
/**
 * Safety net on the server: repair in place.
 *
 * When the browser guard finds that a cached file failed to load or run, it
 * reports the handle. Cached pages cannot carry a nonce, so the endpoint
 * accepts only a registered handle whose cached file exists, can only switch
 * that file back to its original, is rate-limited per IP hash, sends no-cache
 * headers and is logged in the status panel. Worst case for abuse: one file
 * loses its minification until its source changes.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Failure reports and repair in place.
 */
class Safety {

	const ACTION = 'wpbot_ao_report';

	/**
	 * Reports accepted per IP hash in ten minutes.
	 */
	const PER_IP = 5;

	/**
	 * Reports accepted per hour for the whole site.
	 */
	const PER_SITE = 60;

	/**
	 * The module.
	 *
	 * @var Module
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param Module $module The module.
	 */
	public function __construct( Module $module ) {
		$this->module = $module;
	}

	/**
	 * Hooks (admin-ajax.php, for visitors and logged-in users).
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle_report' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_report' ) );
	}

	/**
	 * A browser reports a failed cached file.
	 *
	 * @return void
	 */
	public function handle_report() {
		nocache_headers();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Cached pages cannot carry a nonce; see the class comment.
		$type   = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$handle = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';
		$key    = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = $this->report( $type, $handle, $key, self::client_ip() );
		if ( '' === $result ) {
			wp_send_json_success( array(), 200 );
		}
		wp_send_json_error( array( 'reason' => $result ), 'rate limited' === $result ? 429 : 400 );
	}

	/**
	 * Validate and act on one report.
	 *
	 * @param string $type   "script" or "style".
	 * @param string $handle Handle.
	 * @param string $key    Cache key of the file the page used ('' = current).
	 * @param string $ip     Client IP (only its hash is stored).
	 * @return string '' when repaired (or already repaired), otherwise why not.
	 */
	public function report( $type, $handle, $key, $ip ) {
		if ( ! in_array( $type, array( 'script', 'style' ), true ) ) {
			return 'unknown file';
		}
		$state = $this->module->state();
		$state->refresh();
		$entry = $state->file( $type, $handle );
		if ( null === $entry || empty( $entry['path'] ) || ! $this->module->registry()->has( $type, $handle, (string) $entry['path'] ) ) {
			return 'unknown file';
		}
		$keys = array_filter( array( isset( $entry['key'] ) ? (string) $entry['key'] : '', isset( $entry['previous'] ) ? (string) $entry['previous'] : '' ) );
		$key  = '' === $key ? (string) $entry['key'] : $key;
		if ( ! in_array( $key, $keys, true ) ) {
			return 'unknown file';
		}
		$file = Storage::file_name( $type, $handle, $key );
		if ( '' === $file || ! $this->module->storage()->exists( $type, $file ) ) {
			return 'unknown file';
		}
		$repaired = (array) $state->get( 'repaired' );
		$id       = State::file_id( $type, $handle );
		if ( isset( $repaired[ $id ]['keys'] ) && in_array( $key, (array) $repaired[ $id ]['keys'], true ) ) {
			return '';
		}
		if ( ! $this->allow( $ip ) ) {
			return 'rate limited';
		}
		return $this->repair( $type, $handle, $key, $file, (string) $entry['path'] );
	}

	/**
	 * Rate limits: per IP hash and for the whole site.
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	private function allow( $ip ) {
		$bucket = 'wpbot_ao_rl_' . substr( hash_hmac( 'sha256', (string) $ip, wp_salt( 'nonce' ) ), 0, 16 );
		$count  = (int) get_transient( $bucket );
		$site   = (int) get_transient( 'wpbot_ao_rl_site' );
		if ( $count >= self::PER_IP || $site >= self::PER_SITE ) {
			return false;
		}
		set_transient( $bucket, $count + 1, 10 * MINUTE_IN_SECONDS );
		set_transient( 'wpbot_ao_rl_site', $site + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Overwrite the cached file with the original at the same URL, so pages
	 * that still point at it heal too, and serve the original for this file
	 * version from now on.
	 *
	 * @param string $type     "script" or "style".
	 * @param string $handle   Handle.
	 * @param string $key      Key of the reported file.
	 * @param string $file     Cache file name.
	 * @param string $relative Source path relative to the host folder.
	 * @return string '' on success.
	 */
	private function repair( $type, $handle, $key, $file, $relative ) {
		$root = $this->module->registry()->root_dir();
		$path = realpath( $root . '/' . ltrim( $relative, '/' ) );
		$path = false === $path ? '' : wp_normalize_path( $path );
		if ( '' === $root || '' === $path || 0 !== strpos( $path, $root . '/' ) || ! is_file( $path ) ) {
			return 'unknown file';
		}
		if ( 'script' === $type ) {
			$source = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file inside the plugin folder.
			if ( false === $source ) {
				return 'unreadable';
			}
			$contents = $source . Processor::marker( $handle );
		} else {
			// The original stylesheet, imported from its own folder so its relative url()s keep working.
			$url      = $this->module->processor()->source_url( $relative );
			$contents = '@import url(' . wp_json_encode( $url, JSON_UNESCAPED_SLASHES ) . ");\n";
		}
		if ( ! $this->module->storage()->write( $type, $file, $contents ) ) {
			return 'write failed';
		}

		$state    = $this->module->state();
		$id       = State::file_id( $type, $handle );
		$repaired = (array) $state->get( 'repaired' );
		$keys     = isset( $repaired[ $id ]['keys'] ) ? (array) $repaired[ $id ]['keys'] : array();
		$keys[]   = $key;
		$state->log(
			'repaired',
			$id,
			array(
				'key'    => $key,
				'keys'   => array_values( array_unique( $keys ) ),
				'reason' => __( 'a browser reported it failed; the original is served', 'chatbot' ),
			)
		);

		/**
		 * Fires after a cached file was switched back to its original.
		 *
		 * @param string $handle Handle.
		 * @param string $type   "script" or "style".
		 */
		do_action( 'qcld_wpbot_ao_repaired', $handle, $type );
		return '';
	}

	/**
	 * The client's IP address, for rate limits: REMOTE_ADDR, because forwarded
	 * headers can be forged. Behind a proxy that does not restore the
	 * visitor's address (all visitors would share one limit), a site can
	 * supply it through the filter.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * Filter the visitor IP address used for the rate limits of the report
		 * and fresh-data endpoints (only its keyed hash is stored).
		 *
		 * @param string $ip REMOTE_ADDR.
		 */
		return (string) apply_filters( 'wpbot_asset_cache_client_ip', $ip );
	}
}
