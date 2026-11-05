<?php
/**
 * Business locations. Free keeps one main location; the Pro module adds
 * more through the same store.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Local;

use QuantumCloud\SeoAiSearch\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Location store.
 */
final class Locations {

	/**
	 * Days of the week (schema.org names).
	 */
	const DAYS = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );

	/**
	 * Business types offered (schema.org LocalBusiness subtypes).
	 *
	 * @return array type => label.
	 */
	public static function types() {
		$types = array(
			'LocalBusiness'               => __( 'Local business (general)', 'chatbot' ),
			'Store'                       => __( 'Shop', 'chatbot' ),
			'Restaurant'                  => __( 'Restaurant', 'chatbot' ),
			'CafeOrCoffeeShop'            => __( 'Café', 'chatbot' ),
			'Dentist'                     => __( 'Dentist', 'chatbot' ),
			'MedicalClinic'               => __( 'Medical clinic', 'chatbot' ),
			'BeautySalon'                 => __( 'Beauty salon', 'chatbot' ),
			'HairSalon'                   => __( 'Hair salon', 'chatbot' ),
			'AutoRepair'                  => __( 'Car repair', 'chatbot' ),
			'BicycleStore'                => __( 'Bicycle shop', 'chatbot' ),
			'HomeAndConstructionBusiness' => __( 'Home services and building', 'chatbot' ),
			'ProfessionalService'         => __( 'Professional service', 'chatbot' ),
			'LegalService'                => __( 'Legal service', 'chatbot' ),
			'RealEstateAgent'             => __( 'Estate agent', 'chatbot' ),
			'LodgingBusiness'             => __( 'Hotel or lodging', 'chatbot' ),
			'SportsActivityLocation'      => __( 'Gym or sports', 'chatbot' ),
			'EducationalOrganization'     => __( 'School or training', 'chatbot' ),
		);
		return (array) apply_filters( 'qcld_wpbot_seo/business_types', $types );
	}

	/**
	 * Empty location.
	 *
	 * @return array
	 */
	public static function blank() {
		return array(
			'id'           => 0,
			'is_main'      => true,
			'page_id'      => 0,
			'name'         => '',
			'type'         => 'LocalBusiness',
			'street'       => '',
			'city'         => '',
			'region'       => '',
			'postcode'     => '',
			'country'      => '',
			'phone'        => '',
			'email'        => '',
			'url'          => '',
			'service_area' => array(),
			'hours'        => array(),
			'price_range'  => '',
			'lat'          => '',
			'lng'          => '',
		);
	}

	/**
	 * The main location, or null.
	 *
	 * @return array|null
	 */
	public static function main() {
		global $wpdb;
		$table = Schema::table( Schema::LOCATIONS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( "SELECT * FROM {$table} WHERE is_main = 1 ORDER BY id LIMIT 1", ARRAY_A );
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * All locations (main first).
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;
		$table = Schema::table( Schema::LOCATIONS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY is_main DESC, id ASC LIMIT 500", ARRAY_A );
		return array_map( array( __CLASS__, 'shape' ), $rows );
	}

	/**
	 * One location.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::LOCATIONS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * Save the main location (Free) or any location by id (Pro).
	 *
	 * @param array $data Fields.
	 * @param int   $id   Id (0 = main, created when missing).
	 * @return array|WP_Error Saved location.
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;
		$clean = self::clean( $data );
		if ( '' === $clean['name'] ) {
			return new WP_Error( 'qcld_wpbot_seo_no_name', __( 'Enter the business name.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$table = Schema::table( Schema::LOCATIONS );
		$main  = self::main();
		if ( 0 === (int) $id && $main ) {
			$id = $main['id'];
		}
		$row = array(
			'is_main'    => ( 0 === (int) $id && ! $main ) || ( $main && (int) $id === $main['id'] ) ? 1 : 0,
			'page_id'    => (int) $clean['page_id'],
			'data'       => wp_json_encode( array_diff_key( $clean, array_flip( array( 'id', 'is_main', 'page_id' ) ) ) ),
			'updated_at' => current_time( 'mysql', true ),
		);
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
		} else {
			$wpdb->insert( $table, $row );
			$id = (int) $wpdb->insert_id;
		}
		// phpcs:enable
		return self::get( $id );
	}

	/**
	 * Add another location (never the main one; used by the Pro module).
	 *
	 * @param array $data Data.
	 * @return array|WP_Error Saved location.
	 */
	public static function add( array $data ) {
		global $wpdb;
		$clean = self::clean( $data );
		if ( '' === $clean['name'] ) {
			return new WP_Error( 'qcld_wpbot_seo_no_name', __( 'Enter the business name.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! self::main() ) {
			return self::save( $data );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->insert(
			Schema::table( Schema::LOCATIONS ),
			array(
				'is_main'    => 0,
				'page_id'    => (int) $clean['page_id'],
				'data'       => wp_json_encode( array_diff_key( $clean, array_flip( array( 'id', 'is_main', 'page_id' ) ) ) ),
				'updated_at' => current_time( 'mysql', true ),
			)
		);
		return self::get( (int) $wpdb->insert_id );
	}

	/**
	 * Delete a location (not the main one).
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return (bool) $wpdb->delete(
			Schema::table( Schema::LOCATIONS ),
			array(
				'id'      => (int) $id,
				'is_main' => 0,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Sanitise location fields.
	 *
	 * @param array $data Fields.
	 * @return array
	 */
	public static function clean( array $data ) {
		$out   = self::blank();
		$types = self::types();
		foreach ( array( 'name', 'street', 'city', 'region', 'postcode', 'price_range' ) as $key ) {
			$out[ $key ] = isset( $data[ $key ] ) ? substr( sanitize_text_field( (string) $data[ $key ] ), 0, 200 ) : '';
		}
		$out['country']      = isset( $data['country'] ) ? strtoupper( substr( (string) preg_replace( '/[^A-Za-z]/', '', (string) $data['country'] ), 0, 2 ) ) : '';
		$out['phone']        = isset( $data['phone'] ) ? substr( (string) preg_replace( '/[^0-9+()\- .]/', '', (string) $data['phone'] ), 0, 40 ) : '';
		$out['email']        = isset( $data['email'] ) && is_email( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$out['url']          = isset( $data['url'] ) ? esc_url_raw( (string) $data['url'], array( 'http', 'https' ) ) : '';
		$out['type']         = isset( $data['type'] ) && isset( $types[ $data['type'] ] ) ? (string) $data['type'] : 'LocalBusiness';
		$out['page_id']      = isset( $data['page_id'] ) ? absint( $data['page_id'] ) : 0;
		$out['lat']          = isset( $data['lat'] ) && is_numeric( $data['lat'] ) && abs( (float) $data['lat'] ) <= 90 ? (string) (float) $data['lat'] : '';
		$out['lng']          = isset( $data['lng'] ) && is_numeric( $data['lng'] ) && abs( (float) $data['lng'] ) <= 180 ? (string) (float) $data['lng'] : '';
		$areas               = isset( $data['service_area'] ) ? ( is_array( $data['service_area'] ) ? $data['service_area'] : preg_split( '/[\r\n,]+/', (string) $data['service_area'] ) ) : array();
		$out['service_area'] = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', (array) $areas ) ) ), 0, 30 );
		$hours               = array();
		foreach ( isset( $data['hours'] ) ? (array) $data['hours'] : array() as $day => $slot ) {
			if ( ! in_array( $day, self::DAYS, true ) || ! is_array( $slot ) ) {
				continue;
			}
			if ( ! empty( $slot['closed'] ) ) {
				$hours[ $day ] = array( 'closed' => true );
				continue;
			}
			$open  = isset( $slot['open'] ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $slot['open'] ) ? (string) $slot['open'] : '';
			$close = isset( $slot['close'] ) && preg_match( '/^([01]\d|2[0-4]):[0-5]\d$/', (string) $slot['close'] ) ? (string) $slot['close'] : '';
			if ( '' !== $open && '' !== $close ) {
				$hours[ $day ] = array(
					'open'  => $open,
					'close' => $close,
				);
			}
		}
		$out['hours'] = $hours;
		return $out;
	}

	/**
	 * Row to location.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$data = json_decode( (string) $row['data'], true );
		return array_merge(
			self::blank(),
			is_array( $data ) ? $data : array(),
			array(
				'id'      => (int) $row['id'],
				'is_main' => (bool) $row['is_main'],
				'page_id' => (int) $row['page_id'],
			)
		);
	}
}
