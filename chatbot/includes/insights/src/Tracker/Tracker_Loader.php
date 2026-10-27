<?php
/**
 * Adds the 2 KB loader to front-end pages that should be tracked.
 * The loader decides in the browser (consent, Do Not Track, sampling)
 * whether to fetch the recorder bundle (recorded visits) or the small
 * collector bundle (clicks, scroll and frustration signals only).
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Tracker;

use QuantumCloud\Insights\Module;
use QuantumCloud\Insights\Settings;

defined( 'ABSPATH' ) || exit;

final class Tracker_Loader {

	/**
	 * Hook into the front end.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'init', array( $this, 'heatmap_mode' ) );
	}

	/**
	 * The admin heatmap screen loads pages with ?qcin_heatmap=1 in an iframe:
	 * hide the admin bar there so the page matches what visitors see.
	 */
	public function heatmap_mode() {
		if ( isset( $_GET['qcin_heatmap'] ) && is_user_logged_in() && current_user_can( Module::instance()->capability() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only switch.
			add_filter( 'show_admin_bar', '__return_false' );
		}
	}

	/**
	 * Enqueue the loader and its config.
	 */
	public function enqueue() {
		if ( ! $this->should_track() ) {
			return;
		}
		$ver = QCIN_VERSION;
		wp_enqueue_script(
			'qcin-loader',
			plugins_url( 'assets/build/loader.js', QCIN_FILE ),
			array(),
			$ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script( 'qcin-loader', 'window.qcInsightsConfig=' . wp_json_encode( $this->config() ) . ';', 'before' );
	}

	/**
	 * Config sent to the browser. Contains nothing visitor-specific, so
	 * page caches can serve it.
	 *
	 * @return array<string,mixed>
	 */
	public function config() {
		$config = array(
			'endpoint'     => esc_url_raw( rest_url( 'qcin/v1/ingest' ) ),
			'token'        => Module::instance()->site_token(),
			'recorder'     => plugins_url( 'assets/build/recorder.js', QCIN_FILE ) . '?ver=' . rawurlencode( QCIN_VERSION ),
			'collector'    => plugins_url( 'assets/build/collector.js', QCIN_FILE ) . '?ver=' . rawurlencode( QCIN_VERSION ),
			'sample'       => (int) Settings::get( 'sample_rate' ),
			'hsample'      => (int) Settings::get( 'heatmap_sample_rate' ),
			'rules'        => $this->frustration_rules(),
			'consent'      => (string) Settings::get( 'consent_mode' ),
			'dnt'          => (bool) Settings::get( 'respect_dnt' ),
			'maskText'     => (bool) Settings::get( 'mask_all_text' ),
			'maskSel'      => implode( ',', array_merge( $this->default_mask_selectors(), Settings::lines( 'mask_selectors' ) ) ),
			'blockSel'     => implode( ',', Settings::lines( 'block_selectors' ) ),
			'flushMs'      => 10000,
			'maxMinutes'   => 30,
			'sessionMin'   => 30,
			'automation'   => false,
			'ext'          => $this->extensions(),
		);
		/**
		 * Config sent to the browser tracker.
		 *
		 * @param array<string,mixed> $config Config.
		 */
		return (array) apply_filters( 'qcin/tracker_config', $config );
	}

	/**
	 * Extra tracker scripts from features. The loader adds them only after
	 * the recorder or collector has started, and only for the visits they
	 * ask for, so pages without a feature that needs them pay nothing.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function extensions() {
		/**
		 * Extra tracker scripts.
		 * Each item: array( 'id' => 'my-feature', 'src' => URL, 'when' => 'any' | 'recorded' | 'collected' ).
		 * A script can use window.qcInsights (on, event, push, getContext, registerDetector).
		 *
		 * @param array<int,array<string,string>> $extensions Extensions.
		 */
		$raw = (array) apply_filters( 'qcin/tracker_extensions', array() );
		$out = array();
		foreach ( $raw as $ext ) {
			if ( ! is_array( $ext ) || empty( $ext['src'] ) ) {
				continue;
			}
			$when  = isset( $ext['when'] ) && in_array( $ext['when'], array( 'any', 'recorded', 'collected' ), true ) ? $ext['when'] : 'any';
			$out[] = array(
				'id'   => sanitize_key( (string) ( $ext['id'] ?? '' ) ),
				'src'  => esc_url_raw( (string) $ext['src'] ),
				'when' => $when,
			);
		}
		return $out;
	}

	/**
	 * Thresholds for frustration signals detected in the browser.
	 *
	 * @return array<string,int>
	 */
	private function frustration_rules() {
		/**
		 * Frustration thresholds.
		 *
		 * rageClicks clicks within rageMs and rageRadius px make a rage click;
		 * a click with no page reaction within deadMs is a dead click;
		 * at most maxErrors JavaScript errors are kept per page.
		 *
		 * @param array<string,int> $rules Rules.
		 */
		$rules = (array) apply_filters(
			'qcin/frustration_rules',
			array(
				'rageClicks' => 3,
				'rageMs'     => 1000,
				'rageRadius' => 30,
				'deadMs'     => 500,
				'maxErrors'  => 20,
			)
		);
		return array_map( 'intval', $rules );
	}

	/**
	 * Areas masked by default: account, order and address details.
	 *
	 * @return string[]
	 */
	private function default_mask_selectors() {
		/**
		 * Selectors whose text is always masked.
		 *
		 * @param string[] $selectors CSS selectors.
		 */
		return (array) apply_filters(
			'qcin/default_mask_selectors',
			array(
				'.woocommerce-MyAccount-content',
				'.woocommerce-order-details',
				'.woocommerce-customer-details',
				'.woocommerce-checkout #customer_details',
				'.woocommerce-checkout-review-order',
				'address',
			)
		);
	}

	/**
	 * Whether this front-end request should get the tracker.
	 *
	 * @return bool
	 */
	public function should_track() {
		$track = true;

		if ( ! Settings::get( 'enabled' ) ) {
			$track = false;
		} elseif ( is_admin() || wp_doing_ajax() || wp_doing_cron() || wp_is_json_request() || is_feed() || is_preview() || is_customize_preview() || is_robots() || is_trackback() ) {
			$track = false;
		} elseif ( isset( $_GET['qcin_heatmap'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only switch.
			$track = false;
		} elseif ( $this->user_excluded() || $this->url_excluded() ) {
			$track = false;
		}

		/**
		 * Final decision on adding the tracker to this request.
		 *
		 * @param bool $track Decision.
		 */
		return (bool) apply_filters( 'qcin/should_track', $track );
	}

	/**
	 * Logged-in user has an excluded role.
	 *
	 * @return bool
	 */
	private function user_excluded() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$roles = (array) Settings::get( 'exclude_roles', array() );
		if ( empty( $roles ) ) {
			return false;
		}
		$user = wp_get_current_user();
		return (bool) array_intersect( $roles, (array) $user->roles );
	}

	/**
	 * Current path matches an excluded URL pattern (supports * wildcards).
	 *
	 * @return bool
	 */
	private function url_excluded() {
		$patterns = Settings::lines( 'exclude_urls' );
		if ( empty( $patterns ) ) {
			return false;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		foreach ( $patterns as $pattern ) {
			$pattern = trim( $pattern );
			if ( preg_match( '#^https?://#i', $pattern ) ) {
				$pattern = (string) wp_parse_url( $pattern, PHP_URL_PATH );
			}
			$re = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
			if ( preg_match( $re, $path ) || preg_match( $re, untrailingslashit( $path ) ) ) {
				return true;
			}
		}
		return false;
	}
}
