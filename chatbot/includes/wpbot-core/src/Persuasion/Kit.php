<?php
/**
 * Persuasion Kit (round 3, CR-13): the owner's real proof, by type, in the
 * Business Profile. AI may use only what is here (plus live WooCommerce
 * prices, sale dates and stock). Quotes need the "permission to quote"
 * flag; an offer or deadline needs an end date and is never used after it.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Persuasion;

use QuantumCloud\WPBotCore\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persuasion Kit store and rules.
 */
final class Kit {

	/** Types that are customer words (need permission to quote). */
	const QUOTES = array( 'review', 'testimonial' );

	/** Most entries kept. */
	const MAX = 500;

	/**
	 * Entry types with labels and what the "value" field holds.
	 *
	 * @return array<string,array{label: string, value: string, help: string}>
	 */
	public static function types() {
		return array(
			'review'            => array(
				'label' => __( 'Review', 'chatbot' ),
				'value' => __( 'Rating (for example 5/5)', 'chatbot' ),
				'help'  => __( 'What a customer wrote. Tick "Permission to quote" only if they agreed.', 'chatbot' ),
			),
			'testimonial'       => array(
				'label' => __( 'Testimonial', 'chatbot' ),
				'value' => __( 'Customer name or role (as allowed)', 'chatbot' ),
				'help'  => __( 'A quote you may publish. Tick "Permission to quote" only if the customer agreed.', 'chatbot' ),
			),
			'customer_count'    => array(
				'label' => __( 'Number of customers', 'chatbot' ),
				'value' => __( 'The number (for example 1,200)', 'chatbot' ),
				'help'  => __( 'A real, current count, for example customers served or bikes repaired.', 'chatbot' ),
			),
			'case_study'        => array(
				'label' => __( 'Case study or result', 'chatbot' ),
				'value' => __( 'The result in numbers (optional)', 'chatbot' ),
				'help'  => __( 'A real result you achieved for a customer.', 'chatbot' ),
			),
			'certification'     => array(
				'label' => __( 'Certification or award', 'chatbot' ),
				'value' => __( 'Year (optional)', 'chatbot' ),
				'help'  => __( 'Who gave it and what for.', 'chatbot' ),
			),
			'media_mention'     => array(
				'label' => __( 'Media mention', 'chatbot' ),
				'value' => __( 'Publication name', 'chatbot' ),
				'help'  => __( 'Where you were featured. Add the link if you have it.', 'chatbot' ),
			),
			'years_in_business' => array(
				'label' => __( 'Years in business', 'chatbot' ),
				'value' => __( 'Year founded (for example 2009)', 'chatbot' ),
				'help'  => __( 'The year you started, so the number of years stays right.', 'chatbot' ),
			),
			'free_offer'        => array(
				'label' => __( 'Free offer', 'chatbot' ),
				'value' => __( 'What is free (short)', 'chatbot' ),
				'help'  => __( 'Something useful you give away, for example a free check or guide.', 'chatbot' ),
			),
			'guarantee'         => array(
				'label' => __( 'Guarantee or return policy', 'chatbot' ),
				'value' => __( 'Period (for example 30 days)', 'chatbot' ),
				'help'  => __( 'Exactly what you promise if the customer is not happy.', 'chatbot' ),
			),
			'offer'             => array(
				'label' => __( 'Offer or deadline', 'chatbot' ),
				'value' => __( 'The offer (for example 20% off servicing)', 'chatbot' ),
				'help'  => __( 'A real offer with a real end date. It is never used after that date.', 'chatbot' ),
			),
			'founder_story'     => array(
				'label' => __( 'Founder story', 'chatbot' ),
				'value' => __( 'Founder name (optional)', 'chatbot' ),
				'help'  => __( 'Why the business started, in your own words.', 'chatbot' ),
			),
			'values'            => array(
				'label' => __( 'Values', 'chatbot' ),
				'value' => '',
				'help'  => __( 'What you stand for, with an example of it in practice.', 'chatbot' ),
			),
			'local_identity'    => array(
				'label' => __( 'Local identity', 'chatbot' ),
				'value' => __( 'Place (town or area)', 'chatbot' ),
				'help'  => __( 'Your local roots: where you are, who you serve, local partners.', 'chatbot' ),
			),
		);
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		return Schema::table( Schema::KIT );
	}

	/**
	 * Clean an entry from a form or another module.
	 *
	 * @param array $raw type, label, body, value, source, url, permission, expires_on (Y-m-d), sort.
	 * @return array|WP_Error
	 */
	public static function clean( array $raw ) {
		$type = isset( $raw['type'] ) ? sanitize_key( (string) $raw['type'] ) : '';
		if ( ! isset( self::types()[ $type ] ) ) {
			return new WP_Error( 'qcld_wpbot_core_kit_type', __( 'Choose what kind of proof this is.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$entry = array(
			'type'       => $type,
			'label'      => isset( $raw['label'] ) ? self::cut( sanitize_text_field( (string) $raw['label'] ), 191 ) : '',
			'body'       => isset( $raw['body'] ) ? self::cut( sanitize_textarea_field( (string) $raw['body'] ), 2000 ) : '',
			'value'      => isset( $raw['value'] ) ? self::cut( sanitize_text_field( (string) $raw['value'] ), 100 ) : '',
			'source'     => isset( $raw['source'] ) ? self::cut( sanitize_text_field( (string) $raw['source'] ), 255 ) : '',
			'url'        => isset( $raw['url'] ) ? self::cut( esc_url_raw( trim( (string) $raw['url'] ), array( 'http', 'https' ) ), 255 ) : '',
			'permission' => in_array( $type, self::QUOTES, true ) && ! empty( $raw['permission'] ) ? 1 : 0,
			'expires_on' => self::date( isset( $raw['expires_on'] ) ? $raw['expires_on'] : '' ),
			'sort'       => isset( $raw['sort'] ) ? (int) $raw['sort'] : 0,
		);
		if ( '' === $entry['body'] && '' === $entry['value'] && '' === $entry['label'] ) {
			return new WP_Error( 'qcld_wpbot_core_kit_empty', __( 'Write the proof first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 'offer' === $type && '' === $entry['expires_on'] ) {
			return new WP_Error( 'qcld_wpbot_core_kit_deadline', __( 'An offer needs its real end date, so it is never used after it ends.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 'years_in_business' === $type && '' !== $entry['value'] ) {
			$year = (int) preg_replace( '/\D/', '', $entry['value'] );
			if ( $year < 1800 || $year > (int) gmdate( 'Y' ) ) {
				return new WP_Error( 'qcld_wpbot_core_kit_year', __( 'Enter the year the business started, for example 2009.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$entry['value'] = (string) $year;
		}
		return $entry;
	}

	/**
	 * Add or update an entry.
	 *
	 * @param array $raw Entry; with "id" to update.
	 * @return array|WP_Error Saved entry.
	 */
	public static function save( array $raw ) {
		global $wpdb;
		$entry = self::clean( $raw );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$id  = isset( $raw['id'] ) ? absint( $raw['id'] ) : 0;
		$row = $entry;
		if ( '' === $row['expires_on'] ) {
			$row['expires_on'] = null;
		}
		$row['updated_at'] = $now;
		if ( $id ) {
			if ( ! self::get( $id, true ) ) {
				return new WP_Error( 'qcld_wpbot_core_kit_missing', __( 'That entry was not found.', 'chatbot' ), array( 'status' => 404 ) );
			}
			$wpdb->update( self::table(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		} else {
			if ( self::count() >= self::MAX ) {
				return new WP_Error( 'qcld_wpbot_core_kit_full', __( 'The Persuasion Kit holds 500 entries. Delete old ones first.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$row['created_by'] = get_current_user_id();
			$row['created_at'] = $now;
			$wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
			$id = (int) $wpdb->insert_id;
		}
		/**
		 * The Persuasion Kit changed.
		 *
		 * @param int $id Entry id.
		 */
		do_action( 'qcld_wpbot_core/kit_changed', $id );
		return self::get( $id, true );
	}

	/**
	 * Delete an entry.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$done = (bool) $wpdb->delete( self::table(), array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		if ( $done ) {
			do_action( 'qcld_wpbot_core/kit_changed', absint( $id ) );
		}
		return $done;
	}

	/**
	 * One entry.
	 *
	 * @param int  $id              Id.
	 * @param bool $include_expired Return it even when expired (admin).
	 * @return array|null
	 */
	public static function get( $id, $include_expired = false ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		if ( ! $row ) {
			return null;
		}
		$entry = self::shape( $row );
		return $include_expired || ! $entry['expired'] ? $entry : null;
	}

	/**
	 * Number of entries.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
	}

	/**
	 * Entries.
	 *
	 * @param array $args types (string[]), quotable (bool: only reviews and testimonials with permission), include_expired (bool; admin lists only), product_id (adds live WooCommerce data under "woo").
	 * @return array{entries: array[], woo: array|null}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY type ASC, sort ASC, id DESC LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		$out   = self::filter( array_map( array( __CLASS__, 'shape' ), (array) $rows ), $args );
		return array(
			'entries' => $out,
			'woo'     => ! empty( $args['product_id'] ) ? self::woo( absint( $args['product_id'] ) ) : null,
		);
	}

	/**
	 * The rules applied to a list of entries (pure; unit-tested).
	 *
	 * @param array[] $entries Shaped entries.
	 * @param array   $args    types, quotable, include_expired, today (Y-m-d, for tests).
	 * @return array[]
	 */
	public static function filter( array $entries, array $args = array() ) {
		$today = isset( $args['today'] ) ? (string) $args['today'] : gmdate( 'Y-m-d' );
		$types = ! empty( $args['types'] ) ? array_map( 'strval', (array) $args['types'] ) : array();
		$out   = array();
		foreach ( $entries as $entry ) {
			$expired = '' !== (string) $entry['expires_on'] && (string) $entry['expires_on'] < $today;
			if ( $expired && empty( $args['include_expired'] ) ) {
				continue;
			}
			if ( $types && ! in_array( $entry['type'], $types, true ) ) {
				continue;
			}
			if ( ! empty( $args['quotable'] ) && ( ! in_array( $entry['type'], self::QUOTES, true ) || empty( $entry['permission'] ) ) ) {
				continue;
			}
			$entry['expired'] = $expired;
			$out[]            = $entry;
		}
		return $out;
	}

	/**
	 * A row as returned by the API.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$types   = self::types();
		$expires = isset( $row['expires_on'] ) && $row['expires_on'] ? substr( (string) $row['expires_on'], 0, 10 ) : '';
		return array(
			'id'         => (int) $row['id'],
			'type'       => (string) $row['type'],
			'type_label' => isset( $types[ $row['type'] ] ) ? $types[ $row['type'] ]['label'] : (string) $row['type'],
			'label'      => (string) $row['label'],
			'body'       => (string) $row['body'],
			'value'      => (string) $row['value'],
			'source'     => (string) $row['source'],
			'url'        => (string) $row['url'],
			'permission' => (bool) (int) $row['permission'],
			'expires_on' => $expires,
			'expired'    => '' !== $expires && $expires < gmdate( 'Y-m-d' ),
			'sort'       => (int) $row['sort'],
			'updated_at' => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
		);
	}

	/**
	 * The kit as plain text for an AI prompt (writing). Quotes without
	 * permission are given as "wording only, do not quote"; expired entries
	 * are left out by query().
	 *
	 * @param array[] $entries Entries from query().
	 * @param array   $woo     Live WooCommerce data or null.
	 * @return string
	 */
	public static function prompt( array $entries, $woo = null ) {
		$lines = array();
		foreach ( $entries as $entry ) {
			$text = trim( implode( ' — ', array_filter( array( $entry['label'], $entry['value'], $entry['body'], $entry['source'] ) ) ) );
			if ( '' === $text ) {
				continue;
			}
			$note = '';
			if ( in_array( $entry['type'], self::QUOTES, true ) ) {
				$note = $entry['permission'] ? ' (may be quoted)' : ' (wording only, do not quote or attribute)';
			}
			if ( '' !== $entry['expires_on'] ) {
				$note .= ' (ends ' . $entry['expires_on'] . ')';
			}
			if ( 'years_in_business' === $entry['type'] && '' !== $entry['value'] ) {
				$text = 'In business since ' . $entry['value'];
			}
			$lines[] = '- [' . $entry['type'] . '] ' . $text . $note;
		}
		if ( is_array( $woo ) && ! empty( $woo['price'] ) ) {
			$lines[] = '- [store] Price ' . $woo['price'] . ( ! empty( $woo['regular_price'] ) && $woo['regular_price'] !== $woo['price'] ? ' (regular ' . $woo['regular_price'] . ')' : '' ) . ( ! empty( $woo['sale_end'] ) ? ', sale ends ' . $woo['sale_end'] : '' ) . ( null !== $woo['stock_quantity'] ? ', ' . (int) $woo['stock_quantity'] . ' in stock' : '' );
		}
		return array() === $lines ? '' : "Persuasion Kit (the only proof you may use):\n" . implode( "\n", $lines );
	}

	/**
	 * Live WooCommerce data for a product (prices, sale end, stock).
	 *
	 * @param int $product_id Product.
	 * @return array|null price, regular_price, sale_price, sale_end (Y-m-d), stock_status, stock_quantity (int|null), currency.
	 */
	public static function woo( $product_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}
		$sale_end = $product->get_date_on_sale_to();
		$quantity = $product->managing_stock() ? $product->get_stock_quantity() : null;
		return array(
			'price'          => (string) $product->get_price(),
			'regular_price'  => (string) $product->get_regular_price(),
			'sale_price'     => (string) $product->get_sale_price(),
			'sale_end'       => $sale_end ? $sale_end->date( 'Y-m-d' ) : '',
			'stock_status'   => (string) $product->get_stock_status(),
			'stock_quantity' => null === $quantity ? null : (int) $quantity,
			'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
		);
	}

	/**
	 * A date as Y-m-d, or ''.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function date( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && checkdate( (int) substr( $value, 5, 2 ), (int) substr( $value, 8, 2 ), (int) substr( $value, 0, 4 ) ) ? $value : '';
	}

	/**
	 * Cut a string to a length (multibyte safe).
	 *
	 * @param string $text   Text.
	 * @param int    $length Length.
	 * @return string
	 */
	private static function cut( $text, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length, 'UTF-8' ) : substr( $text, 0, $length );
	}
}
