<?php
/**
 * Cached-page safety: fresh security tokens, visitor details and live
 * show/hide rules for pages served from a page cache.
 *
 * - A small never-cached endpoint (admin-ajax.php?action=wpbot_ao_fresh)
 *   returns the current visitor's own tokens and details (only what their own
 *   page would have received) and the live opening-hours / mobile /
 *   logged-in answers.
 * - Pages printed for logged-in users carry the logged-out visitor fields;
 *   the guard fills in the real ones from the endpoint, so a cached page
 *   cannot show one visitor's name to another.
 * - The host prints its show/hide answer for every live case; the chatbot is
 *   held hidden until the guard picks the live one.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Fresh per-visitor data for cached pages.
 */
class Fresh {

	const ACTION = 'wpbot_ao_fresh';

	/**
	 * Requests accepted per IP hash in ten minutes.
	 */
	const PER_IP = 120;

	/**
	 * The module.
	 *
	 * @var Module
	 */
	private $module;

	/**
	 * Front end (for the per-view switch), when on a page view.
	 *
	 * @var Frontend|null
	 */
	private $frontend;

	/**
	 * Rules table printed by the host on this page ('' = none).
	 *
	 * @var string
	 */
	private $table = '';

	/**
	 * Constructor.
	 *
	 * @param Module        $module   The module.
	 * @param Frontend|null $frontend Front end, on page views.
	 */
	public function __construct( Module $module, $frontend = null ) {
		$this->module   = $module;
		$this->frontend = $frontend;
	}

	/**
	 * Endpoint hooks (every request type) and, on page views, the page hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
		if ( null === $this->frontend ) {
			return;
		}
		$this->module->host()->register_live_rules( array( $this, 'live_rules_enabled' ), array( $this, 'footer_rules' ) );
		add_action( 'wp_print_scripts', array( $this, 'anonymise' ), 2 );
		add_action( 'wp_print_footer_scripts', array( $this, 'anonymise' ), 2 );
		add_filter( 'qcld_wpbot_ao_guard_config', array( $this, 'guard_config' ) );
	}

	/**
	 * Whether the host uses live show/hide rules on this page view.
	 *
	 * @return bool
	 */
	public function live_rules_enabled() {
		return $this->frontend->on();
	}

	/**
	 * The host printed its rules table: hold the chatbot hidden unless it
	 * shows in every case.
	 *
	 * @param string $table Eight "0"/"1" characters.
	 * @return void
	 */
	public function footer_rules( $table ) {
		$table = (string) $table;
		if ( ! preg_match( '/^[01]{8}$/', $table ) ) {
			return;
		}
		$this->table = $table;
		if ( '11111111' !== $table ) {
			echo '<style id="wpbot-ao-rules">' . $this->frontend->selectors() . "{display:none!important}</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selectors() keeps only CSS selector characters.
		}
	}

	/**
	 * Index into the rules table for live values.
	 *
	 * @param array<string,bool> $live open, mobile, logged_in.
	 * @return int
	 */
	public static function index( array $live ) {
		return ( empty( $live['open'] ) ? 0 : 4 ) + ( empty( $live['mobile'] ) ? 0 : 2 ) + ( empty( $live['logged_in'] ) ? 0 : 1 );
	}

	/**
	 * What the guard needs: when the page was printed, whether it was for a
	 * logged-in user, the rules table and the answer at that moment.
	 *
	 * @param array<string,mixed> $config Guard config.
	 * @return array<string,mixed>
	 */
	public function guard_config( $config ) {
		$config['fresh'] = array(
			't'     => time(),
			'u'     => is_user_logged_in() ? 1 : 0,
			'table' => $this->table,
			'now'   => self::index( $this->module->host()->get_live_values() ),
		);
		return $config;
	}

	/**
	 * Pages printed for logged-in users carry the logged-out visitor fields
	 * (the guard fetches the real ones), so the HTML holds no personal data.
	 *
	 * @return void
	 */
	public function anonymise() {
		if ( ! is_user_logged_in() || ! $this->frontend->on() ) {
			return;
		}
		$anonymous = self::anonymous_data( $this->module->host() );
		if ( array() === $anonymous ) {
			return;
		}
		$scripts = wp_scripts();
		foreach ( $scripts->registered as $handle => $item ) {
			$data = $scripts->get_data( $handle, 'data' );
			if ( is_string( $data ) && '' !== $data ) {
				$changed = self::replace_localized( $data, $anonymous );
				if ( $changed !== $data ) {
					$scripts->add_data( $handle, 'data', $changed );
				}
			}
			// Inline "before" scripts of the form "window.name = {…};" (add-ons pass data this way).
			$before = $scripts->get_data( $handle, 'before' );
			if ( is_array( $before ) && array() !== $before ) {
				$changed = array();
				foreach ( $before as $code ) {
					$changed[] = is_string( $code ) ? self::replace_localized( $code, $anonymous ) : $code;
				}
				if ( $changed !== $before ) {
					$scripts->add_data( $handle, 'before', $changed );
				}
			}
		}
	}

	/**
	 * Replace keys of objects printed by wp_localize_script() ("var name = {…};"
	 * lines) with other values.
	 *
	 * @param string                            $data    Localized data.
	 * @param array<string,array<string,mixed>> $changes Object name => key => value.
	 * @return string
	 */
	public static function replace_localized( $data, array $changes ) {
		$lines = explode( "\n", (string) $data );
		foreach ( $lines as $i => $line ) {
			if ( ! preg_match( '/^(var |window\.)([A-Za-z_$][A-Za-z0-9_$]*) = (\{.*\});$/', $line, $match ) || ! isset( $changes[ $match[2] ] ) ) {
				continue;
			}
			$match  = array( $match[0], $match[2], $match[3], $match[1] );
			$object = json_decode( $match[2], true );
			if ( ! is_array( $object ) ) {
				continue;
			}
			foreach ( $changes[ $match[1] ] as $key => $value ) {
				if ( array_key_exists( $key, $object ) ) {
					$object[ $key ] = $value;
				}
			}
			$lines[ $i ] = $match[3] . $match[1] . ' = ' . wp_json_encode( $object ) . ';';
		}
		return implode( "\n", $lines );
	}

	/**
	 * The endpoint: fresh tokens and visitor details for this visitor, and the
	 * live rule values. Never cached; rate-limited per IP hash.
	 *
	 * @return void
	 */
	public function handle() {
		nocache_headers();
		header( 'X-Robots-Tag: noindex', true );
		if ( ! $this->allow( Safety::client_ip() ) ) {
			wp_send_json_error( array( 'reason' => 'rate limited' ), 429 );
		}
		$host = $this->module->host();
		wp_send_json_success(
			array(
				'objects' => self::fresh_data( $host ),
				'live'    => $host->get_live_values(),
			)
		);
	}

	/**
	 * Fresh per-visitor values: the host's, plus what extensions add.
	 *
	 * @param \QuantumCloud\AssetOptimizer\Host\Host_Adapter $host Host.
	 * @return array<string,array<string,mixed>>
	 */
	public static function fresh_data( $host ) {
		/**
		 * Filter the fresh, per-visitor values for cached pages (object name => key => value).
		 * The Pro module adds the add-ons' tokens here.
		 *
		 * @param array<string,array<string,mixed>> $data Values.
		 */
		return (array) apply_filters( 'wpbot_asset_cache_fresh_data', $host->get_fresh_data() );
	}

	/**
	 * Logged-out visitor values printed on pages for logged-in users.
	 *
	 * @param \QuantumCloud\AssetOptimizer\Host\Host_Adapter $host Host.
	 * @return array<string,array<string,mixed>>
	 */
	public static function anonymous_data( $host ) {
		/**
		 * Filter the logged-out values of visitor fields (object name => key => value).
		 *
		 * @param array<string,array<string,mixed>> $data Values.
		 */
		return (array) apply_filters( 'wpbot_asset_cache_anonymous_data', $host->get_anonymous_visitor_data() );
	}

	/**
	 * Rate limit per IP hash.
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	private function allow( $ip ) {
		$bucket = 'wpbot_ao_fr_' . substr( hash_hmac( 'sha256', (string) $ip, wp_salt( 'nonce' ) ), 0, 16 );
		$count  = (int) get_transient( $bucket );
		if ( $count >= self::PER_IP ) {
			return false;
		}
		set_transient( $bucket, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}
}
