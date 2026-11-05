<?php
/**
 * SEO side of the shared competitor list (round 2, R2). The list itself lives
 * in WPBot Core (Competitors\Competitors). This class:
 * - seeds it once from the competitors the site already had (Pro's list and
 *   AI Writer Pro's Competitor Watch, through qcld_wpbot_seo/competitor_hosts);
 * - tells the competitor AI the site's country, language and business type;
 * - keeps the shared list in the competitor hosts left out of link prospects;
 * - remembers the owner's last brief (what to compete on, which kind of
 *   competitor) and, once one was given, runs suggestions on its own with it
 *   while the competitor field is empty (once per brief, so costs stay low).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Research;

use QuantumCloud\SeoAiSearch\Links\Prospects;
use QuantumCloud\SeoAiSearch\Local\Locations;
use QuantumCloud\SeoAiSearch\Module;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Shared competitor list adapter.
 */
final class Competitor_List {

	const SEEDED = 'qcld_wpbot_seo_competitors_seeded';
	const AUTO   = 'qcld_wpbot_seo_competitors_auto';
	const PREFS  = 'qcld_wpbot_seo_competitor_prefs';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		if ( ! self::available() ) {
			return;
		}
		add_action( 'admin_init', array( __CLASS__, 'seed' ) );
		add_filter( 'qcld_wpbot_core/competitor_context', array( __CLASS__, 'context' ) );
		add_filter( 'qcld_wpbot_seo/competitor_hosts', array( __CLASS__, 'hosts' ), 5 );
	}

	/**
	 * Is the shared list available (WPBot Core with competitors)?
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( '\QuantumCloud\WPBotCore\Competitors\Competitors' );
	}

	/**
	 * Shared list.
	 *
	 * @return array[] domain, reason, source, added_at.
	 */
	public static function all() {
		return self::available() ? \QuantumCloud\WPBotCore\Competitors\Competitors::all() : array();
	}

	/**
	 * Shared domains.
	 *
	 * @return string[]
	 */
	public static function domains() {
		return array_column( self::all(), 'domain' );
	}

	/**
	 * Seed the shared list once from what the site already had.
	 *
	 * @return void
	 */
	public static function seed() {
		if ( get_option( self::SEEDED ) ) {
			return;
		}
		update_option( self::SEEDED, 1, false );
		if ( array() !== self::all() ) {
			return;
		}
		$found = array();
		foreach ( Prospects::competitor_hosts() as $host ) {
			$found[] = array(
				'domain' => $host,
				'source' => 'seo',
			);
		}
		if ( array() !== $found ) {
			\QuantumCloud\WPBotCore\Competitors\Competitors::save( $found );
		}
	}

	/**
	 * Add the shared list to the competitor hosts (left out of link prospects).
	 *
	 * @param array $hosts Hosts.
	 * @return array
	 */
	public static function hosts( $hosts ) {
		return array_merge( (array) $hosts, self::domains() );
	}

	/**
	 * Country, language and business type for the competitor prompt.
	 *
	 * @param array $facts label => text.
	 * @return array
	 */
	public static function context( $facts ) {
		$facts = (array) $facts;
		$place = Research::place( array() );
		if ( '' !== $place['country'] ) {
			$facts['Country'] = strtoupper( $place['country'] );
		}
		if ( '' !== $place['language'] ) {
			$facts['Language'] = $place['language'];
		}
		$main = Locations::main();
		if ( is_array( $main ) && '' !== (string) $main['city'] ) {
			$types                = Locations::types();
			$facts['Business']    = isset( $types[ $main['type'] ] ) ? (string) $types[ $main['type'] ] : (string) $main['type'];
			$facts['Serves area'] = trim( $main['city'] . ', ' . $main['country'], ', ' );
		}
		return $facts;
	}

	/**
	 * The owner's last brief, for the "Suggest competitors" form.
	 *
	 * @return array set (a brief was given), focus, kind, default_focus (site tagline or name), has_location.
	 */
	public static function prefs() {
		$saved   = get_option( self::PREFS );
		$tagline = trim( wp_strip_all_tags( get_bloginfo( 'description' ) ) );
		$main    = Locations::main();
		return array(
			'set'           => is_array( $saved ),
			'focus'         => is_array( $saved ) && isset( $saved['focus'] ) ? (string) $saved['focus'] : '',
			'kind'          => is_array( $saved ) && isset( $saved['kind'] ) ? (string) $saved['kind'] : 'any',
			'default_focus' => '' !== $tagline ? $tagline : trim( wp_strip_all_tags( get_bloginfo( 'name' ) ) ),
			'has_location'  => is_array( $main ) && '' !== (string) $main['city'],
		);
	}

	/**
	 * Verified suggestions from the module's AI.
	 *
	 * A run the owner starts saves the brief (focus and kind) for next time.
	 * An automatic run happens only after the owner gave a brief once, while
	 * the shared list is empty, and only once per brief.
	 *
	 * @param bool   $auto  Automatic run (from an empty competitor field).
	 * @param string $focus What to compete on ('' for the whole site).
	 * @param string $kind  any|local|online|content.
	 * @return array|WP_Error suggestions, dropped, web_search, cost, focus, kind, skipped.
	 */
	public static function suggest( $auto = false, $focus = '', $kind = 'any' ) {
		if ( ! self::available() ) {
			return new WP_Error( 'qcld_wpbot_seo_no_competitors', __( 'Update WPBot to use the shared competitor list.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$suggest = '\QuantumCloud\WPBotCore\Competitors\Suggest';
		$focus   = method_exists( $suggest, 'focus' ) ? $suggest::focus( $focus ) : sanitize_text_field( (string) $focus );
		$kind    = method_exists( $suggest, 'kind' ) ? $suggest::kind( $kind ) : 'any';
		$brief   = md5( strtolower( $focus ) . '|' . $kind );
		if ( $auto ) {
			$ran = get_option( self::AUTO );
			if ( ! self::prefs()['set'] || array() !== self::all() || ( is_array( $ran ) && in_array( $brief, $ran, true ) ) ) {
				return array(
					'suggestions' => array(),
					'dropped'     => array(),
					'skipped'     => true,
					'focus'       => $focus,
					'kind'        => $kind,
				);
			}
		}
		$ready = Research::ai_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		// Remember the brief before the run (the last 30), so a slow or failed run is not repeated on its own.
		$ran   = get_option( self::AUTO );
		$ran   = is_array( $ran ) ? array_diff( $ran, array( $brief ) ) : array();
		$ran[] = $brief;
		update_option( self::AUTO, array_values( array_slice( $ran, -30 ) ), false );
		if ( ! $auto ) {
			update_option(
				self::PREFS,
				array(
					'focus' => $focus,
					'kind'  => $kind,
				),
				false
			);
		}
		$result = qcld_wpbot_core_suggest_competitors(
			array(
				'module'  => Module::ID,
				'limit'   => 8,
				'exclude' => self::domains(),
				'focus'   => $focus,
				'kind'    => $kind,
			)
		);
		if ( ! is_wp_error( $result ) ) {
			$result['skipped'] = false;
			$result['focus']   = $focus;
			$result['kind']    = $kind;
		}
		return $result;
	}

	/**
	 * Save the list (the route checked the permission).
	 *
	 * @param array $items Domains or arrays (domain, reason, source).
	 * @return array[]
	 */
	public static function save( array $items ) {
		return \QuantumCloud\WPBotCore\Competitors\Competitors::save( $items );
	}

	/**
	 * Add ticked suggestions to the list.
	 *
	 * @param array $items Arrays (domain, reason, source).
	 * @return array[]
	 */
	public static function add( array $items ) {
		return \QuantumCloud\WPBotCore\Competitors\Competitors::add( $items );
	}
}
