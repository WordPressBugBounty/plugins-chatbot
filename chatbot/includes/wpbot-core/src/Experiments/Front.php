<?php
/**
 * Experiments on the public site (round 3, P4).
 *
 * Cache-safe splitting: the page carries every variant, each in a wrapper
 * hidden by CSS. A tiny inline script in <head> picks the visitor's
 * variant (sticky) and adds a class to <html> before the body is painted,
 * so a cached page serves all variants and nothing flickers. Without
 * JavaScript (and for crawlers that do not run it) the control shows.
 *
 * Consent follows Site Insight's consent mode (option qcin_settings, key
 * consent_mode; filter qcld_wpbot_core/experiments_consent_mode):
 * - never: no consent needed; the assignment is kept in a first-party cookie and counted;
 * - auto: the WP Consent API decides when present ("statistics"), otherwise as "never";
 * - always: consent required; without a consent plugin nothing is stored or counted.
 * Without consent the visitor still sees one variant for that page view,
 * but it is not remembered or counted.
 *
 * Measurement: a beacon to wpbot-core/v1/experiments/beacon (views,
 * impressions, clicks, calls to action, scroll, engaged time), WooCommerce
 * add to cart and orders, and leads through the action
 * qcld_wpbot_core/experiment_goal. Daily counts only.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Experiments;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end splitting and measurement.
 */
final class Front {

	/** Assignment cookie (experiment.variant pairs). */
	const COOKIE = 'qcx_a';

	/** Metrics the beacon may count. */
	const BEACON_METRICS = array( 'impressions', 'views', 'clicks', 'cta', 'scroll', 'engaged' );

	/**
	 * Hook in (only while split tests run).
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_core/experiment_goal', array( self::class, 'goal' ), 10, 2 );
		add_action( 'woocommerce_add_to_cart', array( self::class, 'add_to_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( self::class, 'order' ) );
		add_action( 'template_redirect', array( self::class, 'tracked_link' ), 1 );
		if ( is_admin() || array() === Experiments::front()['tests'] ) {
			return;
		}
		add_action( 'wp_head', array( self::class, 'head' ), 1 );
		add_action( 'wp_footer', array( self::class, 'footer' ), 50 );
	}

	/**
	 * Wrap variants for the page (cache-safe; the control shows without JavaScript).
	 *
	 * @param int    $id   Experiment.
	 * @param array  $html Variant id => HTML (already escaped or kses-filtered by the caller).
	 * @param string $tag  span or div.
	 * @return string
	 */
	public static function markup( $id, array $html, $tag = 'span' ) {
		$front = Experiments::front();
		if ( ! isset( $front['tests'][ (int) $id ] ) ) {
			return '';
		}
		$test = $front['tests'][ (int) $id ];
		$tag  = 'div' === $tag ? 'div' : 'span';
		$out  = '';
		foreach ( $test['v'] as $variant_id ) {
			if ( ! isset( $html[ $variant_id ] ) ) {
				continue;
			}
			$out .= sprintf(
				'<%1$s class="qcx-v" data-qcx="%2$d" data-qcx-v="%3$d"%4$s>%5$s</%1$s>',
				$tag,
				(int) $id,
				(int) $variant_id,
				(int) $variant_id === (int) $test['c'] ? ' data-qcx-c="1"' : '',
				$html[ $variant_id ]
			);
		}
		return $out;
	}

	/**
	 * Consent mode (Site Insight's setting).
	 *
	 * @return string auto|always|never
	 */
	public static function consent_mode() {
		$settings = get_option( 'qcin_settings', array() );
		$mode     = is_array( $settings ) && isset( $settings['consent_mode'] ) ? (string) $settings['consent_mode'] : 'auto';
		/**
		 * Consent mode for experiment cookies and counting.
		 *
		 * @param string $mode auto|always|never.
		 */
		$mode = (string) apply_filters( 'qcld_wpbot_core/experiments_consent_mode', $mode );
		return in_array( $mode, array( 'auto', 'always', 'never' ), true ) ? $mode : 'auto';
	}

	/**
	 * CSS and the assignment script, first thing in <head>.
	 *
	 * @return void
	 */
	public static function head() {
		$tests = Experiments::front()['tests'];
		$css   = '.qcx-v{display:none}html:not(.qcx-js) .qcx-v[data-qcx-c]{display:revert}';
		$conf  = array();
		foreach ( $tests as $id => $test ) {
			foreach ( $test['v'] as $variant_id ) {
				$css .= sprintf( 'html.qcx-%1$d-%2$d .qcx-v[data-qcx-v="%2$d"]{display:revert}', (int) $id, (int) $variant_id );
			}
			$conf[ (int) $id ] = array(
				'v' => array_map( 'intval', $test['v'] ),
				'c' => (int) $test['c'],
				'l' => 0 === strpos( (string) $test['l'], 'headline_tests:' ),
			);
		}
		$data = array(
			'tests'   => $conf,
			'consent' => self::consent_mode(),
			'secure'  => is_ssl(),
		);
		// The CSS is built from integers only.
		echo '<style id="qcx-css">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from integers.
		echo '<script id="qcx-assign">' . self::assign_script( $data ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed script with JSON-encoded data.
	}

	/**
	 * Measurement script, at the end of the page.
	 *
	 * @return void
	 */
	public static function footer() {
		$tests = array();
		foreach ( Experiments::front()['tests'] as $id => $test ) {
			$tests[ (int) $id ] = array(
				'k' => $test['k'],
				'o' => (int) $test['o'],
				'g' => $test['g'],
			);
		}
		$data = array(
			'tests'  => $tests,
			'page'   => is_singular() ? (int) get_queried_object_id() : 0,
			'beacon' => esc_url_raw( rest_url( 'wpbot-core/v1/experiments/beacon' ) ),
		);
		echo '<script id="qcx-measure">' . self::measure_script( $data ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed script with JSON-encoded data.
	}

	/**
	 * Count beacon events (public route; validated against running tests and rate limited).
	 *
	 * @param array $events List of e (experiment), v (variant), m (metric).
	 * @return int Events counted.
	 */
	public static function beacon( array $events ) {
		if ( self::limited( count( $events ) ) ) {
			return 0;
		}
		$counted = 0;
		foreach ( array_slice( $events, 0, 10 ) as $event ) {
			if ( ! is_array( $event ) || ! isset( $event['e'], $event['v'], $event['m'] ) ) {
				continue;
			}
			$metric = sanitize_key( (string) $event['m'] );
			if ( in_array( $metric, self::BEACON_METRICS, true ) && Experiments::record( absint( $event['e'] ), absint( $event['v'] ), $metric ) ) {
				++$counted;
			}
		}
		return $counted;
	}

	/**
	 * A visit through a tracked test link (?qcx_s=experiment.variant, used by
	 * social and other time or channel tests): one click for that version.
	 * Nothing is stored about the visitor.
	 *
	 * @return void
	 */
	public static function tracked_link() {
		$raw = isset( $_GET['qcx_s'] ) ? sanitize_text_field( wp_unslash( $_GET['qcx_s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A public link; it only counts a click.
		if ( ! preg_match( '/^(\d+)\.(\d+)$/', $raw, $m ) || self::limited( 1 ) ) {
			return;
		}
		Experiments::record( (int) $m[1], (int) $m[2], 'link_clicks' );
	}

	/**
	 * A tracked link for one version of a time or channel test.
	 *
	 * @param string $url        Link (may already carry UTM parameters).
	 * @param int    $id         Experiment.
	 * @param int    $variant_id Variant.
	 * @return string
	 */
	public static function link( $url, $id, $variant_id ) {
		return add_query_arg( 'qcx_s', (int) $id . '.' . (int) $variant_id, (string) $url );
	}

	/**
	 * The visitor's variant of a test, from the assignment cookie (0 when
	 * not assigned or not consented).
	 *
	 * @param int $id Experiment.
	 * @return int Variant id.
	 */
	public static function assigned( $id ) {
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		foreach ( explode( ',', $raw ) as $pair ) {
			$parts = explode( '.', $pair );
			if ( 2 === count( $parts ) && (int) $parts[0] === (int) $id ) {
				return absint( $parts[1] );
			}
		}
		return 0;
	}

	/**
	 * WooCommerce: an add to cart for a product under test.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product.
	 * @return void
	 */
	public static function add_to_cart( $cart_item_key, $product_id ) {
		self::count_for_object( (int) $product_id, 'add_to_cart' );
	}

	/**
	 * WooCommerce: an order with products under test (counted once per order).
	 *
	 * @param int $order_id Order.
	 * @return void
	 */
	public static function order( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order || $order->get_meta( '_qcx_counted' ) ) {
			return;
		}
		$seen = array();
		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			if ( ! isset( $seen[ $product_id ] ) ) {
				$seen[ $product_id ] = true;
				self::count_for_object( $product_id, 'orders' );
			}
		}
		$order->update_meta_data( '_qcx_counted', 1 );
		$order->save();
	}

	/**
	 * A goal reached (lead forms, chat leads): counted for the page's test.
	 *
	 * @param string $goal      Metric (for example leads).
	 * @param int    $object_id Page the goal belongs to (0: the referring page).
	 * @return void
	 */
	public static function goal( $goal, $object_id = 0 ) {
		$object_id = absint( $object_id );
		if ( ! $object_id && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$object_id = url_to_postid( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
		}
		if ( $object_id ) {
			self::count_for_object( $object_id, sanitize_key( (string) $goal ) );
		}
	}

	/**
	 * Count a server-side event for the visitor's variant of every running
	 * split test on an object.
	 *
	 * @param int    $object_id Object.
	 * @param string $metric    Metric.
	 * @return void
	 */
	private static function count_for_object( $object_id, $metric ) {
		foreach ( Experiments::front()['tests'] as $id => $test ) {
			if ( (int) $test['o'] !== (int) $object_id ) {
				continue;
			}
			$variant = self::assigned( $id );
			if ( $variant ) {
				Experiments::record( $id, $variant, $metric );
			}
		}
	}

	/**
	 * Simple per-visitor rate limit for the public beacon (hashed address,
	 * nothing stored beyond an hour).
	 *
	 * @param int $n Events in this request.
	 * @return bool Over the limit.
	 */
	private static function limited( $n ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'qcx_rl_' . substr( hash_hmac( 'sha256', $ip . gmdate( 'YmdH' ), wp_salt( 'nonce' ) ), 0, 20 );
		$was = (int) get_transient( $key );
		if ( $was + $n > 120 ) {
			return true;
		}
		set_transient( $key, $was + $n, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * The assignment script (runs in <head>, before the body is painted).
	 *
	 * @param array $data Config.
	 * @return string
	 */
	private static function assign_script( array $data ) {
		return '(function(d,w){var c=' . wp_json_encode( $data ) . ';var h=d.documentElement;h.className+=" qcx-js";'
			. 'function consent(){if(c.consent==="never"){return "allow";}if(typeof w.wp_has_consent!=="function"){return c.consent==="always"?"deny":"allow";}return w.wp_has_consent("statistics")?"allow":"wait";}'
			. 'var state=consent(),kept={},legacy="",m=d.cookie.match(/(?:^|; )qcx_a=([^;]*)/),l=d.cookie.match(/(?:^|; )qcwr_ab=(a|b)/);'
			. 'if(state==="allow"&&m){decodeURIComponent(m[1]).split(",").forEach(function(p){var x=p.split(".");kept[x[0]]=+x[1];});}'
			. 'if(state==="allow"&&l){legacy=l[1];}'
			. 'var out=[],pick={};Object.keys(c.tests).forEach(function(id){var t=c.tests[id],v=kept[id];'
			. 'if(t.v.indexOf(v)<0){v=t.l&&legacy?(legacy==="a"?t.v[0]:t.v[1]):t.v[Math.floor(Math.random()*t.v.length)];}'
			. 'pick[id]=v;out.push(id+"."+v);h.className+=" qcx-"+id+"-"+v;});'
			. 'if(state==="allow"&&out.length){d.cookie="qcx_a="+encodeURIComponent(out.join(","))+";path=/;max-age=5184000;SameSite=Lax"+(c.secure?";Secure":"");}'
			. 'w.qcxState={pick:pick,consent:state,mode:c.consent};})(document,window);';
	}

	/**
	 * The measurement script (one beacon per page view and metric).
	 *
	 * @param array $data Config.
	 * @return string
	 */
	private static function measure_script( array $data ) {
		return '(function(d,w){var c=' . wp_json_encode( $data ) . ',s=w.qcxState;if(!s||/bot|crawl|spider|slurp/i.test(navigator.userAgent)){return;}'
			. 'var queue=[],sent={};'
			. 'function send(){if(!queue.length){return;}var body=JSON.stringify({events:queue.splice(0,10)});'
			. 'if(navigator.sendBeacon){navigator.sendBeacon(c.beacon,new Blob([body],{type:"application/json"}));}'
			. 'else{fetch(c.beacon,{method:"POST",body:body,keepalive:true,headers:{"Content-Type":"application/json"}});}if(queue.length){send();}}'
			. 'function add(id,m){var k=id+":"+m;if(sent[k]||!s.pick[id]){return;}sent[k]=1;queue.push({e:+id,v:s.pick[id],m:m});}'
			. 'function run(){var own=d.referrer&&d.referrer.indexOf(location.protocol+"//"+location.host)===0;'
			. 'Object.keys(c.tests).forEach(function(id){var t=c.tests[id],here=c.page&&c.page===t.o,shown=d.querySelector("[data-qcx=\\""+id+"\\"]");'
			. 'if(t.k==="headline"){if(here){add(id,"views");if(own){add(id,"clicks");}}else if(shown){add(id,"impressions");}return;}'
			. 'if(!here&&!shown){return;}add(id,"views");'
			. 'if(t.g==="cta"){d.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a,button");if(!a){return;}var box=a.closest("[data-qcx=\\""+id+"\\"]");if(box||(here&&a.closest(".wp-block-buttons,.qcx-goal"))){add(id,"cta");send();}},true);}'
			. 'if(t.g==="scroll"){w.addEventListener("scroll",function(){var el=d.documentElement;if((w.scrollY+w.innerHeight)/el.scrollHeight>=0.75){add(id,"scroll");send();}},{passive:true});}'
			. 'if(t.g==="engaged"){setTimeout(function(){if(!d.hidden){add(id,"engaged");send();}},30000);}'
			. '});send();}'
			. 'if(s.consent==="allow"){if(d.readyState==="loading"){d.addEventListener("DOMContentLoaded",run);}else{run();}}'
			. 'else if(s.consent==="wait"){d.addEventListener("wp_listen_for_consent_change",function(){if(w.wp_has_consent&&w.wp_has_consent("statistics")){s.consent="allow";run();}});}'
			. '})(document,window);';
	}
}
