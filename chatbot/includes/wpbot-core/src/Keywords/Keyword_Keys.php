<?php
/**
 * The owner's keyword data provider: which one, its key (encrypted,
 * autoload off, masked, never sent to the browser), the monthly budget and
 * what was spent, and health (failing key or empty balance) for the bold
 * notice.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Keywords;

use QuantumCloud\WPBotCore\Crypto\Secret_Box;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword provider settings.
 */
final class Keyword_Keys {

	/**
	 * Option.
	 */
	const OPTION = 'qcld_wpbot_core_keyword_keys';

	/**
	 * Provider classes by id.
	 *
	 * @return array id => class.
	 */
	public static function classes() {
		/**
		 * Keyword data providers.
		 *
		 * @param array $classes id => class implementing Provider.
		 */
		return (array) apply_filters(
			'qcld_wpbot_core/keyword_providers',
			array(
				'dataforseo'         => Providers\Data_For_Seo::class,
				'keywordseverywhere' => Providers\Keywords_Everywhere::class,
				'semrush'            => Providers\Semrush::class,
			)
		);
	}

	/**
	 * The active provider with its key, or null.
	 *
	 * @return Provider|null
	 */
	public static function provider() {
		$all = self::load();
		$id  = $all['provider'];
		$key = self::key( $id );
		if ( '' === $id || '' === $key ) {
			return null;
		}
		$classes = self::classes();
		return isset( $classes[ $id ] ) && class_exists( $classes[ $id ] ) ? new $classes[ $id ]( $key ) : null;
	}

	/**
	 * Google Keyword Planner, when it is turned on (Pro) and Google Ads is connected.
	 *
	 * @return Provider|null
	 */
	public static function planner() {
		/**
		 * Use Google Keyword Planner (SEO & AI Search Pro turns it on).
		 *
		 * @param bool $on Default false.
		 */
		if ( ! apply_filters( 'qcld_wpbot_core/keyword_planner_enabled', false ) || 'connected' !== \QuantumCloud\WPBotCore\Connections\Google_Ads::status()['status'] ) {
			return null;
		}
		return new Providers\Keyword_Planner();
	}

	/**
	 * The source for an operation (round 2, R5): Keyword Planner supplies
	 * volumes and ideas when it is on; the paid provider supplies rankings,
	 * difficulty and backlinks. Without a paid provider, Keyword Planner's
	 * ideas for a site stand in for "domain keywords" (marked as such).
	 *
	 * @param string $op volumes | ideas | domain_keywords | backlinks.
	 * @return Provider|null
	 */
	public static function for_op( $op ) {
		$paid    = self::provider();
		$planner = 'backlinks' === $op ? null : self::planner();
		if ( in_array( $op, array( 'volumes', 'ideas' ), true ) ) {
			$source = $planner ? $planner : $paid;
			// Round 3, B: with nothing set up, free Bing data (owner's Bing key) fills in.
			return $source ? $source : self::free();
		}
		return $paid ? $paid : $planner;
	}

	/**
	 * Free Bing data, when a module supplies the owner's Bing key (round 3, B).
	 *
	 * @return Provider|null
	 */
	public static function free() {
		/**
		 * Use free Bing keyword data when no other source is set (default on).
		 *
		 * @param bool $on On.
		 */
		if ( ! apply_filters( 'qcld_wpbot_core/free_keyword_data', true ) || ! Providers\Bing_Free::available() ) {
			return null;
		}
		return new Providers\Bing_Free();
	}

	/**
	 * Is this the paid provider (budget, balance and health apply)?
	 *
	 * @param Provider $provider Provider.
	 * @return bool
	 */
	public static function is_paid( Provider $provider ) {
		return ! in_array( $provider->id(), array( Providers\Keyword_Planner::ID, Providers\Bing_Free::ID ), true );
	}

	/**
	 * Plain key of a provider, or ''.
	 *
	 * @param string $id Provider.
	 * @return string
	 */
	private static function key( $id ) {
		$all = self::load();
		if ( empty( $all['keys'][ $id ] ) ) {
			return '';
		}
		$plain = Secret_Box::decrypt( (string) $all['keys'][ $id ] );
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Save settings. An empty key leaves the saved one; null removes it.
	 *
	 * @param array $changes provider, key, budget.
	 * @return array|WP_Error Public view.
	 */
	public static function save( array $changes ) {
		$all = self::load();
		if ( array_key_exists( 'provider', $changes ) ) {
			$id = sanitize_key( (string) $changes['provider'] );
			if ( '' !== $id && ! isset( self::classes()[ $id ] ) ) {
				return new WP_Error( 'qcld_wpbot_core_invalid', __( 'Unknown keyword data provider.', 'chatbot' ), array( 'status' => 400 ) );
			}
			if ( $id !== $all['provider'] ) {
				$all['health'] = array();
			}
			$all['provider'] = $id;
		}
		if ( array_key_exists( 'key', $changes ) && '' !== $all['provider'] ) {
			if ( null === $changes['key'] ) {
				unset( $all['keys'][ $all['provider'] ] );
			} else {
				$value = (string) preg_replace( '/[^\x21-\x7E]/', '', (string) $changes['key'] );
				if ( '' !== $value ) {
					$all['keys'][ $all['provider'] ] = Secret_Box::encrypt( substr( $value, 0, 300 ) );
					$all['health']                   = array();
				}
			}
		}
		if ( array_key_exists( 'budget', $changes ) ) {
			$all['budget'] = max( 0, round( (float) $changes['budget'], 2 ) );
		}
		self::put( $all );
		return self::view();
	}

	/**
	 * Settings for the screen (masked key, never the key itself).
	 *
	 * @return array
	 */
	public static function view() {
		$all       = self::load();
		$providers = array();
		foreach ( self::classes() as $id => $class ) {
			$object      = new $class( '' );
			$key         = self::key( $id );
			$providers[] = array(
				'id'    => $id,
				'label' => $object->label(),
				'unit'  => $object->unit(),
				'set'   => '' !== $key,
				'hint'  => '' !== $key ? Secret_Box::mask( $key ) : '',
			);
		}
		$provider = self::provider();
		return array(
			'providers' => $providers,
			'provider'  => $all['provider'],
			'connected' => null !== $provider,
			'unit'      => $provider ? $provider->unit() : '',
			'budget'    => (float) $all['budget'],
			'spent'     => self::spent(),
			'health'    => $all['health'],
			'balance'   => $all['balance'],
		);
	}

	/**
	 * Spent this month (provider units).
	 *
	 * @return float
	 */
	public static function spent() {
		$all = self::load();
		return isset( $all['spent']['month'] ) && gmdate( 'Y-m' ) === $all['spent']['month'] ? (float) $all['spent']['amount'] : 0.0;
	}

	/**
	 * Left in this month's budget (null = no budget set).
	 *
	 * @return float|null
	 */
	public static function left() {
		$budget = (float) self::load()['budget'];
		return $budget > 0 ? max( 0.0, $budget - self::spent() ) : null;
	}

	/**
	 * Record what a lookup cost.
	 *
	 * @param float $amount Amount.
	 * @return void
	 */
	public static function record_spend( $amount ) {
		$all          = self::load();
		$all['spent'] = array(
			'month'  => gmdate( 'Y-m' ),
			'amount' => self::spent() + (float) $amount,
		);
		self::put( $all );
	}

	/**
	 * Mark the key failing (refused key or empty balance) or healthy.
	 *
	 * @param WP_Error|null $error Error, or null when a call worked.
	 * @return void
	 */
	public static function record_health( $error ) {
		$all = self::load();
		if ( null === $error ) {
			if ( array() !== $all['health'] ) {
				$all['health'] = array();
				self::put( $all );
			}
			return;
		}
		if ( ! in_array( $error->get_error_code(), array( 'qcld_wpbot_core_keyword_key', 'qcld_wpbot_core_keyword_balance' ), true ) ) {
			return;
		}
		$all['health'] = array(
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'since'   => isset( $all['health']['since'] ) ? (int) $all['health']['since'] : time(),
		);
		self::put( $all );
	}

	/**
	 * Save a balance read.
	 *
	 * @param float $amount Balance.
	 * @return void
	 */
	public static function record_balance( $amount ) {
		$all            = self::load();
		$all['balance'] = array(
			'amount'  => (float) $amount,
			'checked' => time(),
		);
		self::put( $all );
	}

	/**
	 * Notice in the shape modules use for failing AI keys (bold notice).
	 *
	 * @return array[] Zero or one notice.
	 */
	public static function notices() {
		$all      = self::load();
		$provider = self::provider();
		if ( ! $provider || empty( $all['health']['code'] ) ) {
			return array();
		}
		return array(
			array(
				'provider'       => $provider->id(),
				'provider_label' => $provider->label(),
				'cause_code'     => 'qcld_wpbot_core_keyword_balance' === $all['health']['code'] ? 'balance' : 'key',
				'cause'          => (string) $all['health']['message'],
				'fix'            => 'qcld_wpbot_core_keyword_balance' === $all['health']['code']
					? __( 'Top up your balance with the provider. Keyword lookups are paused; everything else keeps working on Search Console data.', 'chatbot' )
					: __( 'Save the key again in Settings > Connections > Keyword data.', 'chatbot' ),
				'since'          => (int) $all['health']['since'],
			),
		);
	}

	/**
	 * Delete everything (uninstall).
	 *
	 * @return void
	 */
	public static function delete() {
		delete_option( self::OPTION );
	}

	/**
	 * Stored settings with defaults.
	 *
	 * @return array
	 */
	private static function load() {
		$all = get_option( self::OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		return array_merge(
			array(
				'provider' => '',
				'keys'     => array(),
				'budget'   => 0,
				'spent'    => array(),
				'health'   => array(),
				'balance'  => array(),
			),
			$all
		);
	}

	/**
	 * Save.
	 *
	 * @param array $all Settings.
	 * @return void
	 */
	private static function put( array $all ) {
		update_option( self::OPTION, $all, false );
	}
}
