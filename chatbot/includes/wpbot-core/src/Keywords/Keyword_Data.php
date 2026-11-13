<?php
/**
 * Keyword data read API and lookups. Reading cached data never costs
 * anything; a lookup first shows its estimate, respects the monthly budget,
 * and caches results for 30 days per keyword, country and language (a
 * keyword the provider has no data for is cached too, so a repeat costs
 * nothing). Large lookups run in the job queue in provider-sized batches.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords;

use QuantumCloud\WPBotCore\Jobs\Job_Store;
use QuantumCloud\WPBotCore\Map\Keyword_Map;
use QuantumCloud\WPBotCore\Storage\Seo_Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword data.
 */
final class Keyword_Data {

	/**
	 * Cache days.
	 */
	const DAYS = 30;

	/**
	 * Job step handlers.
	 *
	 * @param array $handlers Handlers.
	 * @return array
	 */
	public static function handlers( $handlers ) {
		$handlers['core_keyword_lookup'] = array( __CLASS__, 'step' );
		return $handlers;
	}

	/**
	 * Cached rows (no cost).
	 *
	 * @param string[] $keywords Keywords.
	 * @param string   $country  Country ('' = site default).
	 * @param string   $language Language ('' = site default).
	 * @return array normalised keyword => row (volume, cpc, competition, trend, fetched_at, provider).
	 */
	public static function cached( array $keywords, $country = '', $language = '' ) {
		global $wpdb;
		$country  = Countries::clean( $country );
		$language = Countries::language( $language );
		$hashes   = array();
		foreach ( $keywords as $keyword ) {
			$norm = Keyword_Map::normalise( $keyword );
			if ( '' !== $norm ) {
				$hashes[ md5( $norm ) ] = $norm;
			}
		}
		if ( array() === $hashes ) {
			return array();
		}
		$table = Seo_Schema::table( Seo_Schema::KEYWORD_DATA );
		$out   = array();
		foreach ( array_chunk( array_keys( $hashes ), 500 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from a constant; placeholders built for the chunk.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE country = %s AND language = %s AND fetched_at >= %s AND keyword_hash IN ({$in})", array_merge( array( $country, $language, gmdate( 'Y-m-d H:i:s', time() - self::DAYS * DAY_IN_SECONDS ) ), $chunk ) ), ARRAY_A );
			foreach ( $rows as $row ) {
				$out[ $hashes[ $row['keyword_hash'] ] ] = self::shape( $row );
			}
		}
		return $out;
	}

	/**
	 * What a lookup would cost (only keywords not cached count).
	 *
	 * @param string[] $keywords Keywords.
	 * @param string   $country  Country.
	 * @param string   $language Language.
	 * @return array|WP_Error keywords, cached, cost, unit, left (budget left or null), provider.
	 */
	public static function estimate( array $keywords, $country = '', $language = '' ) {
		$provider = Keyword_Keys::for_op( 'volumes' );
		if ( ! $provider ) {
			return self::not_connected();
		}
		$all     = self::unique( $keywords );
		$cached  = self::cached( $all, $country, $language );
		$missing = array_values( array_diff( $all, array_keys( $cached ) ) );
		return array(
			'keywords' => count( $missing ),
			'cached'   => count( $cached ),
			'cost'     => $missing ? $provider->estimate( 'volumes', count( $missing ) ) : 0.0,
			'unit'     => $provider->unit(),
			'left'     => Keyword_Keys::is_paid( $provider ) ? Keyword_Keys::left() : null,
			'provider' => $provider->label(),
		);
	}

	/**
	 * Look keywords up now (cached ones are free).
	 *
	 * @param string[] $keywords Keywords (one batch or a few).
	 * @param string   $country  Country.
	 * @param string   $language Language.
	 * @return array|WP_Error normalised keyword => row, plus '_cost'.
	 */
	public static function lookup( array $keywords, $country = '', $language = '' ) {
		$provider = Keyword_Keys::for_op( 'volumes' );
		$paid     = $provider && Keyword_Keys::is_paid( $provider );
		if ( ! $provider ) {
			return self::not_connected();
		}
		$country  = Countries::clean( $country );
		$language = Countries::language( $language );
		$all      = self::unique( $keywords );
		$out      = self::cached( $all, $country, $language );
		$missing  = array_values( array_diff( $all, array_keys( $out ) ) );
		$cost     = 0.0;
		foreach ( array_chunk( $missing, $provider->batch_size() ) as $batch ) {
			$price = $provider->estimate( 'volumes', count( $batch ) );
			$check = $paid ? self::can_spend( $price ) : true;
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$rows = $provider->volumes( $batch, $country, $language );
			if ( $paid ) {
				Keyword_Keys::record_health( is_wp_error( $rows ) ? $rows : null );
			}
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			if ( $paid ) {
				Keyword_Keys::record_spend( $price );
			}
			$cost += $price;
			$seen  = array();
			foreach ( $rows as $row ) {
				$norm = Keyword_Map::normalise( $row['keyword'] );
				if ( '' === $norm ) {
					continue;
				}
				self::store( $norm, $country, $language, $provider->id(), $row );
				$seen[ $norm ] = true;
			}
			// Keywords without data are cached as "no data", so a repeat costs nothing.
			foreach ( $batch as $norm ) {
				if ( ! isset( $seen[ $norm ] ) ) {
					self::store( $norm, $country, $language, $provider->id(), array( 'volume' => null ) );
				}
			}
		}
		$out          = self::cached( $all, $country, $language );
		$out['_cost'] = $cost;
		return $out;
	}

	/**
	 * Queue a large lookup (provider-sized batches).
	 *
	 * @param string   $module   Calling module id.
	 * @param string[] $keywords Keywords.
	 * @param string   $country  Country.
	 * @param string   $language Language.
	 * @return int|WP_Error Job id (0 when everything was cached).
	 */
	public static function start_lookup( $module, array $keywords, $country = '', $language = '' ) {
		$provider = Keyword_Keys::for_op( 'volumes' );
		if ( ! $provider ) {
			return self::not_connected();
		}
		$country  = Countries::clean( $country );
		$language = Countries::language( $language );
		$all      = self::unique( $keywords );
		$missing  = array_values( array_diff( $all, array_keys( self::cached( $all, $country, $language ) ) ) );
		if ( array() === $missing ) {
			return 0;
		}
		$check = Keyword_Keys::is_paid( $provider ) ? self::can_spend( $provider->estimate( 'volumes', count( $missing ) ) ) : true;
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$steps = array();
		foreach ( array_chunk( $missing, $provider->batch_size() ) as $index => $batch ) {
			$steps[] = array(
				'kind'  => 'core_keyword_lookup',
				/* translators: %d: batch number. */
				'label' => sprintf( __( 'Look up search volumes, batch %d', 'chatbot' ), $index + 1 ),
				'input' => array(
					'keywords' => $batch,
					'country'  => $country,
					'language' => $language,
				),
			);
		}
		return (int) Job_Store::create( sanitize_key( $module ), 'keyword_lookup', array( 'title' => __( 'Look up search volumes', 'chatbot' ) ), $steps );
	}

	/**
	 * Job step.
	 *
	 * @param array $step Step.
	 * @return array|WP_Error
	 */
	public static function step( array $step ) {
		$input = $step['input'];
		$rows  = self::lookup( (array) $input['keywords'], (string) $input['country'], (string) $input['language'] );
		if ( is_wp_error( $rows ) ) {
			// A refused key, empty balance or reached budget is not retried.
			if ( in_array( $rows->get_error_code(), array( 'qcld_wpbot_core_keyword_key', 'qcld_wpbot_core_keyword_balance', 'qcld_wpbot_core_keyword_budget' ), true ) ) {
				$rows->add_data( array( 'retryable' => false ) );
			}
			return $rows;
		}
		return array( 'found' => count( $rows ) - 1 );
	}

	/**
	 * Ideas, domain keywords or backlinks (cached 30 days; backlinks 7).
	 *
	 * @param string $op       ideas | domain_keywords | backlinks | serp (Google's organic results; paid providers that offer it).
	 * @param string $subject  Seed keyword or domain.
	 * @param array  $args     country, language, limit, estimate_only.
	 * @return array|WP_Error rows, cost, cached; or the estimate.
	 */
	public static function research( $op, $subject, array $args = array() ) {
		if ( 'serp' === $op ) {
			$provider = Keyword_Keys::provider();
			if ( $provider && ! method_exists( $provider, 'serp' ) ) {
				/* translators: %s: provider name. */
				return new WP_Error( 'qcld_wpbot_core_not_offered', sprintf( __( '%s does not offer search results here. DataForSEO does.', 'chatbot' ), $provider->label() ), array( 'status' => 400 ) );
			}
		} else {
			$provider = in_array( $op, array( 'ideas', 'domain_keywords', 'backlinks' ), true ) ? Keyword_Keys::for_op( $op ) : null;
		}
		if ( ! $provider ) {
			return self::not_connected();
		}
		$paid = Keyword_Keys::is_paid( $provider );
		if ( '' === trim( (string) $subject ) ) {
			return new WP_Error( 'qcld_wpbot_core_invalid', __( 'Enter a keyword or a domain.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$country  = Countries::clean( isset( $args['country'] ) ? $args['country'] : '' );
		$language = Countries::language( isset( $args['language'] ) ? $args['language'] : '' );
		$limit    = max( 10, min( 'serp' === $op ? 100 : 1000, isset( $args['limit'] ) ? (int) $args['limit'] : 50 ) );
		$subject  = in_array( $op, array( 'ideas', 'serp' ), true ) ? Keyword_Map::normalise( $subject ) : strtolower( trim( (string) preg_replace( '#^https?://(www\.)?|/.*$#i', '', trim( (string) $subject ) ) ) );
		$key      = 'qcld_wpbot_core_kw_' . md5( implode( '|', array( $provider->id(), $op, $subject, $country, $language, $limit ) ) );
		$cached   = get_transient( $key );
		$price    = $provider->estimate( $op, $limit );
		if ( ! empty( $args['estimate_only'] ) ) {
			return array(
				'cost'     => is_array( $cached ) ? 0.0 : $price,
				'unit'     => $provider->unit(),
				'left'     => $paid ? Keyword_Keys::left() : null,
				'cached'   => is_array( $cached ),
				'provider' => $provider->label(),
			);
		}
		if ( is_array( $cached ) ) {
			return array(
				'rows'   => $cached,
				'cost'   => 0.0,
				'cached' => true,
			);
		}
		$check = $paid ? self::can_spend( $price ) : true;
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		if ( 'ideas' === $op ) {
			$rows = $provider->ideas( $subject, $country, $language, $limit );
		} elseif ( 'domain_keywords' === $op ) {
			$rows = $provider->domain_keywords( $subject, $country, $language, $limit );
		} elseif ( 'serp' === $op ) {
			$rows = $provider->serp( $subject, $country, $language, $limit );
		} else {
			$rows = $provider->backlinks( $subject, $limit );
		}
		if ( $paid ) {
			Keyword_Keys::record_health( is_wp_error( $rows ) ? $rows : null );
		}
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		if ( $paid ) {
			Keyword_Keys::record_spend( $price );
		}
		if ( ! in_array( $op, array( 'backlinks', 'serp' ), true ) ) {
			foreach ( $rows as $row ) {
				if ( null !== $row['volume'] ) {
					self::store( Keyword_Map::normalise( $row['keyword'] ), $country, $language, $provider->id(), $row );
				}
			}
		}
		set_transient( $key, $rows, ( 'backlinks' === $op ? 7 : self::DAYS ) * DAY_IN_SECONDS );
		return array(
			'rows'     => $rows,
			'cost'     => $price,
			'cached'   => false,
			'provider' => $provider->label(),
		);
	}

	/**
	 * Read the balance and record it.
	 *
	 * @return array|WP_Error amount, unit.
	 */
	public static function balance() {
		$provider = Keyword_Keys::provider();
		if ( ! $provider ) {
			return self::not_connected();
		}
		$amount = $provider->balance();
		Keyword_Keys::record_health( is_wp_error( $amount ) ? $amount : null );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}
		Keyword_Keys::record_balance( $amount );
		if ( $amount <= 0 ) {
			Keyword_Keys::record_health( new WP_Error( 'qcld_wpbot_core_keyword_balance', sprintf( /* translators: %s: provider name. */ __( 'Your %s balance is empty. Top it up in your account; lookups are paused until then.', 'chatbot' ), $provider->label() ) ) );
		}
		return array(
			'amount' => (float) $amount,
			'unit'   => $provider->unit(),
		);
	}

	/**
	 * Can this amount be spent (budget, health)?
	 *
	 * @param float $amount Amount.
	 * @return true|WP_Error
	 */
	private static function can_spend( $amount ) {
		$view = Keyword_Keys::view();
		if ( ! empty( $view['health']['code'] ) && 'qcld_wpbot_core_keyword_balance' === $view['health']['code'] ) {
			return new WP_Error( 'qcld_wpbot_core_keyword_balance', (string) $view['health']['message'], array( 'status' => 402 ) );
		}
		$left = Keyword_Keys::left();
		if ( null !== $left && $amount > $left + 0.00001 ) {
			return new WP_Error( 'qcld_wpbot_core_keyword_budget', __( 'This lookup would go over this month\'s keyword data budget. Raise the budget in Settings, or wait until next month.', 'chatbot' ), array( 'status' => 402 ) );
		}
		return true;
	}

	/**
	 * Save one row.
	 *
	 * @param string $norm     Normalised keyword.
	 * @param string $country  Country.
	 * @param string $language Language.
	 * @param string $provider Provider id.
	 * @param array  $row      Row.
	 * @return void
	 */
	private static function store( $norm, $country, $language, $provider, array $row ) {
		global $wpdb;
		if ( '' === $norm ) {
			return;
		}
		$table = Seo_Schema::table( Seo_Schema::KEYWORD_DATA );
		$hash  = md5( $norm );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table; NULL means "no data".
		$wpdb->replace(
			$table,
			array(
				'keyword_hash'      => $hash,
				'keyword'           => $norm,
				'country'           => $country,
				'language'          => $language,
				'provider'          => $provider,
				'volume'            => isset( $row['volume'] ) && null !== $row['volume'] ? (int) $row['volume'] : null,
				'cpc'               => isset( $row['cpc'] ) && null !== $row['cpc'] ? (float) $row['cpc'] : null,
				'competition'       => isset( $row['competition'] ) && null !== $row['competition'] ? (float) $row['competition'] : null,
				'trend'             => wp_json_encode( isset( $row['trend'] ) ? array_values( (array) $row['trend'] ) : array() ),
				'competition_level' => isset( $row['competition_level'] ) ? substr( sanitize_key( (string) $row['competition_level'] ), 0, 8 ) : '',
				'bid_low'           => isset( $row['bid_low'] ) && null !== $row['bid_low'] ? (float) $row['bid_low'] : null,
				'bid_high'          => isset( $row['bid_high'] ) && null !== $row['bid_high'] ? (float) $row['bid_high'] : null,
				'fetched_at'        => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Row for callers.
	 *
	 * @param array $row DB row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$trend = json_decode( (string) $row['trend'], true );
		return array(
			'keyword'           => $row['keyword'],
			'volume'            => null === $row['volume'] ? null : (int) $row['volume'],
			'cpc'               => null === $row['cpc'] ? null : (float) $row['cpc'],
			'competition'       => null === $row['competition'] ? null : (float) $row['competition'],
			'trend'             => is_array( $trend ) ? array_map( 'intval', $trend ) : array(),
			'competition_level' => isset( $row['competition_level'] ) ? (string) $row['competition_level'] : '',
			'bid_low'           => isset( $row['bid_low'] ) && null !== $row['bid_low'] ? (float) $row['bid_low'] : null,
			'bid_high'          => isset( $row['bid_high'] ) && null !== $row['bid_high'] ? (float) $row['bid_high'] : null,
			'provider'          => $row['provider'],
			'fetched_at'        => $row['fetched_at'],
		);
	}

	/**
	 * Forget cached numbers for keywords, so the next lookup asks again (monthly refreshes).
	 *
	 * @param string[] $keywords Keywords.
	 * @param string   $country  Country.
	 * @param string   $language Language.
	 * @return int Rows removed.
	 */
	public static function forget( array $keywords, $country = '', $language = '' ) {
		global $wpdb;
		$country  = Countries::clean( $country );
		$language = Countries::language( $language );
		$hashes   = array_map( 'md5', self::unique( $keywords ) );
		$removed  = 0;
		$table    = Seo_Schema::table( Seo_Schema::KEYWORD_DATA );
		foreach ( array_chunk( $hashes, 500 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from a constant; placeholders built for the chunk.
			$removed += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE country = %s AND language = %s AND keyword_hash IN ({$in})", array_merge( array( $country, $language ), $chunk ) ) );
		}
		return $removed;
	}

	/**
	 * Direction of a 12-month trend: the last three months against the first
	 * three (a change of 20% or more counts).
	 *
	 * @param int[] $trend Monthly searches, oldest first.
	 * @return string rising | falling | steady, or '' with too little data.
	 */
	public static function trend_direction( array $trend ) {
		$trend = array_values( array_map( 'intval', $trend ) );
		if ( count( $trend ) < 6 ) {
			return '';
		}
		$first = array_sum( array_slice( $trend, 0, 3 ) ) / 3;
		$last  = array_sum( array_slice( $trend, -3 ) ) / 3;
		if ( $first <= 0 ) {
			return $last > 0 ? 'rising' : '';
		}
		$change = ( $last - $first ) / $first;
		if ( $change >= 0.2 ) {
			return 'rising';
		}
		return $change <= -0.2 ? 'falling' : 'steady';
	}

	/**
	 * Unique normalised keywords.
	 *
	 * @param string[] $keywords Keywords.
	 * @return string[]
	 */
	private static function unique( array $keywords ) {
		return array_values( array_unique( array_filter( array_map( array( Keyword_Map::class, 'normalise' ), $keywords ) ) ) );
	}

	/**
	 * Error when no provider is connected.
	 *
	 * @return WP_Error
	 */
	private static function not_connected() {
		return new WP_Error( 'qcld_wpbot_core_keyword_none', __( 'Connect a keyword data provider in Settings > Connections first. Everything else works without one.', 'chatbot' ), array( 'status' => 400 ) );
	}
}
