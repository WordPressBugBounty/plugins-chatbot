<?php
/**
 * Local SEO, round 3 I: Google Business Profile. The owner turns it on
 * (setting gbp_on; Google then asks for the Business Profile permission at
 * the next reconnect), links a Business Profile location to the main
 * location of Local SEO, and gets a health check: does the profile say the
 * same as the website (name, address, phone, website, opening hours), are
 * special hours set ahead of the coming weeks, and does it have a category
 * and Google's review link. Read-only: fixes are made on Google, through
 * the link shown.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Local;

use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\WPBotCore\Connections\Business_Profile;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Business Profile in Local SEO.
 */
final class Gbp {

	const LINKS = 'qcld_wpbot_seo_gbp_links';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter(
			'qcld_wpbot_core/business_profile_enabled',
			static function ( $on ) {
				return $on || (bool) Settings::get( 'gbp_on' );
			}
		);
	}

	/**
	 * Is WPBot Core's Business Profile available?
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( Business_Profile::class );
	}

	/**
	 * The Business Profile location linked to a Local SEO location.
	 *
	 * @param int $location_id Local SEO location id (0 = main).
	 * @return array|null account, name, title.
	 */
	public static function link( $location_id = 0 ) {
		$id    = $location_id ? (int) $location_id : self::main_id();
		$links = get_option( self::LINKS );
		return is_array( $links ) && isset( $links[ $id ] ) && is_array( $links[ $id ] ) ? $links[ $id ] : null;
	}

	/**
	 * Link (or unlink with an empty name) a Business Profile location.
	 *
	 * @param int    $location_id Local SEO location id (0 = main).
	 * @param string $account     accounts/123.
	 * @param string $name        locations/456 ('' = unlink).
	 * @param string $title       Its title, for the screen.
	 * @return array|null The link.
	 */
	public static function set_link( $location_id, $account, $name, $title = '' ) {
		$id    = $location_id ? (int) $location_id : self::main_id();
		$links = get_option( self::LINKS );
		$links = is_array( $links ) ? $links : array();
		if ( '' === (string) $name ) {
			unset( $links[ $id ] );
		} elseif ( preg_match( '#^accounts/[0-9]+$#', (string) $account ) && preg_match( '#^locations/[0-9]+$#', (string) $name ) ) {
			$links[ $id ] = array(
				'account' => (string) $account,
				'name'    => (string) $name,
				'title'   => sanitize_text_field( (string) $title ),
			);
		}
		update_option( self::LINKS, $links, false );
		return isset( $links[ $id ] ) ? $links[ $id ] : null;
	}

	/**
	 * The health check of a linked location against Local SEO.
	 *
	 * @param int $location_id Local SEO location id (0 = main).
	 * @return array|WP_Error profile, checks (id, label, ok, website, google, fix), review_link, edit_url.
	 */
	public static function health( $location_id = 0 ) {
		$link = self::link( $location_id );
		if ( ! $link ) {
			return new WP_Error( 'qcld_wpbot_seo_gbp_unlinked', __( 'Choose your Business Profile location first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$profile = Business_Profile::location( $link['name'] );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		$local = $location_id ? Locations::get( $location_id ) : Locations::main();
		$local = is_array( $local ) ? $local : Locations::blank();
		return array(
			'profile'     => $profile,
			'checks'      => self::compare( $local, $profile ),
			'review_link' => Business_Profile::review_link( $profile['place_id'] ),
			'edit_url'    => 'https://business.google.com/',
		);
	}

	/**
	 * Compare a Local SEO location with its Business Profile.
	 *
	 * @param array $local   Local SEO location.
	 * @param array $profile Business_Profile::shape().
	 * @param int   $now     Time (tests).
	 * @return array[] id, label, ok, website, google, fix.
	 */
	public static function compare( array $local, array $profile, $now = 0 ) {
		$now    = $now ? $now : time();
		$checks = array();
		$add    = static function ( $id, $label, $ok, $website, $google, $fix ) use ( &$checks ) {
			$checks[] = array(
				'id'      => $id,
				'label'   => $label,
				'ok'      => (bool) $ok,
				'website' => (string) $website,
				'google'  => (string) $google,
				'fix'     => $ok ? '' : (string) $fix,
			);
		};
		$norm   = static function ( $text ) {
			return trim( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', strtolower( (string) $text ) ) );
		};

		$add( 'name', __( 'Business name', 'chatbot' ), '' === $local['name'] || $norm( $local['name'] ) === $norm( $profile['title'] ), $local['name'], $profile['title'], __( 'Use exactly the same business name on your website and on Google.', 'chatbot' ) );

		$site_street = $norm( $local['street'] );
		$gbp_street  = $norm( $profile['address']['street'] );
		$same_street = '' === $site_street || ( '' !== $gbp_street && ( false !== strpos( $gbp_street, $site_street ) || false !== strpos( $site_street, $gbp_street ) ) );
		$same_city   = '' === $local['city'] || $norm( $local['city'] ) === $norm( $profile['address']['city'] );
		$same_post   = '' === $local['postcode'] || $norm( $local['postcode'] ) === $norm( $profile['address']['postcode'] );
		$add(
			'address',
			__( 'Address', 'chatbot' ),
			$same_street && $same_city && $same_post,
			trim( implode( ', ', array_filter( array( $local['street'], $local['city'], $local['postcode'] ) ) ) ),
			trim( implode( ', ', array_filter( array( $profile['address']['street'], $profile['address']['city'], $profile['address']['postcode'] ) ) ) ),
			__( 'Make the address the same everywhere (street, city and postcode). Search engines compare them.', 'chatbot' )
		);

		$digits = static function ( $phone ) {
			$d = preg_replace( '/\D+/', '', (string) $phone );
			return strlen( $d ) > 9 ? substr( $d, -9 ) : $d;
		};
		$add( 'phone', __( 'Phone number', 'chatbot' ), '' === $local['phone'] || $digits( $local['phone'] ) === $digits( $profile['phone'] ), $local['phone'], $profile['phone'], __( 'Show the same main phone number on your website and on Google.', 'chatbot' ) );

		$site_host = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		$gbp_host  = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( $profile['website'], PHP_URL_HOST ) ) );
		$add( 'website', __( 'Website link', 'chatbot' ), $gbp_host === $site_host, home_url( '/' ), $profile['website'], __( 'Add this website as the website of your Business Profile.', 'chatbot' ) );

		$site_hours = array();
		foreach ( (array) $local['hours'] as $day => $slot ) {
			$site_hours[ $day ] = ! empty( $slot['closed'] ) ? 'closed' : $slot['open'] . '-' . $slot['close'];
		}
		$gbp_hours = array();
		foreach ( $profile['hours'] as $period ) {
			$gbp_hours[ $period['day'] ] = $period['open'] . '-' . ( '24:00' === $period['close'] ? '24:00' : $period['close'] );
		}
		$hours_ok = true;
		foreach ( $site_hours as $day => $value ) {
			$google = isset( $gbp_hours[ $day ] ) ? $gbp_hours[ $day ] : 'closed';
			if ( $google !== $value ) {
				$hours_ok = false;
			}
		}
		$hours_ok = $hours_ok && ( array() === $site_hours || count( array_diff_key( $gbp_hours, $site_hours ) ) === 0 );
		$add( 'hours', __( 'Opening hours', 'chatbot' ), array() === $site_hours ? array() !== $gbp_hours : $hours_ok, self::hours_text( $site_hours ), self::hours_text( $gbp_hours ), array() === $site_hours && array() === $gbp_hours ? __( 'Add your opening hours on Google and in Local SEO.', 'chatbot' ) : __( 'Make the opening hours the same on your website and on Google.', 'chatbot' ) );

		$upcoming = array_filter(
			$profile['special_hours'],
			static function ( $period ) use ( $now ) {
				$time = strtotime( $period['date'] );
				return $time >= $now - DAY_IN_SECONDS && $time <= $now + 60 * DAY_IN_SECONDS;
			}
		);
		$add( 'special_hours', __( 'Special hours (next 60 days)', 'chatbot' ), array() !== $upcoming, '', array() !== $upcoming ? implode( ', ', wp_list_pluck( $upcoming, 'date' ) ) : '', __( 'No special hours for the next 60 days. Add holiday hours on Google so customers don\'t arrive at a closed door.', 'chatbot' ) );

		$add( 'category', __( 'Main category', 'chatbot' ), '' !== $profile['category'], '', $profile['category'], __( 'Choose the main category that best describes your business on Google.', 'chatbot' ) );
		$add( 'review_link', __( 'Review link', 'chatbot' ), '' !== $profile['place_id'], '', $profile['place_id'] ? __( 'Ready', 'chatbot' ) : '', __( 'Google has not shared a place ID for this profile yet, usually because it is not verified.', 'chatbot' ) );
		return $checks;
	}

	/**
	 * Hours as short text.
	 *
	 * @param array $hours Day => "09:00-18:00" or "closed".
	 * @return string
	 */
	private static function hours_text( array $hours ) {
		$out = array();
		foreach ( Locations::DAYS as $day ) {
			if ( isset( $hours[ $day ] ) ) {
				$out[] = substr( $day, 0, 3 ) . ' ' . $hours[ $day ];
			}
		}
		return implode( ', ', $out );
	}

	/**
	 * Main Local SEO location id.
	 *
	 * @return int
	 */
	private static function main_id() {
		$main = Locations::main();
		return is_array( $main ) ? (int) $main['id'] : 0;
	}
}
