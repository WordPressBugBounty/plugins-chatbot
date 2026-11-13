<?php
/**
 * AI spending: the one place every AI call asks before it runs and reports after it ran.
 *
 * - allow():  is the call within the owner's limits (global, its group, not paused)?
 * - record(): store what the call used and cost (estimated), then check the alerts.
 * - wrap():   allow + run the caller's own HTTP call + read its usage + record, for code that
 *             keeps its own transport (image studio, voice, add-ons).
 *
 * Limits are checked before each call, so calls already in flight can pass a limit slightly
 * (about one call per concurrent visitor). That small overshoot is accepted and documented.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Spending facade.
 */
final class Spend {

	/**
	 * Error code of a refused call. The same code as the old monthly budget, so existing
	 * callers (chatbot fallback, AI readiness) keep working.
	 */
	const ERROR = 'qcld_wpbot_core_budget_reached';

	/**
	 * State option (alerts sent, one-click raises), autoload off.
	 */
	const STATE = 'qcld_wpbot_core_spend_state';

	/**
	 * How many wrap() calls are running (the safety net skips their requests).
	 *
	 * @var int
	 */
	private static $metering = 0;

	/**
	 * Limit-reached events already fired in this request.
	 *
	 * @var array
	 */
	private static $fired = array();

	/**
	 * Hook the spend services into WordPress (called by Core::boot()).
	 *
	 * @return void
	 */
	public static function hook() {
		Spend_Schema::hook();
		Net::hook();
		Alerts::hook();
		Privacy::hook();
		add_action( 'qcld_wpbot_core/spend_settings_saved', array( __CLASS__, 'resume_jobs' ) );
	}

	/**
	 * Capability that may see and change spending (default manage_options).
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Capability for the spending screens and REST routes.
		 *
		 * @param string $capability Capability.
		 */
		return (string) apply_filters( 'qcld_wpbot_core/spend_capability', 'manage_options' );
	}

	/**
	 * One-click raise: add half of the owner's limit for the rest of this period.
	 *
	 * @param string $scope 'global' or a group id.
	 * @return float|WP_Error The new limit for this period.
	 */
	public static function raise( $scope ) {
		$scope    = sanitize_key( (string) $scope );
		$settings = Spend_Settings::all();
		if ( 'global' !== $scope && ! Features::exists( $scope ) ) {
			return new WP_Error( 'qcld_wpbot_core_spend_scope', __( 'Unknown feature.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$base = 'global' === $scope ? (float) $settings['global'] : Spend_Settings::limit( $scope );
		if ( $base <= 0 ) {
			return new WP_Error( 'qcld_wpbot_core_spend_no_limit', __( 'This has no limit to raise.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$period = Period::current( $settings );
		$state  = get_option( self::STATE, array() );
		$state  = is_array( $state ) ? $state : array();
		$extra  = isset( $state['raised'][ $period['key'] ][ $scope ] ) ? (float) $state['raised'][ $period['key'] ][ $scope ] : 0.0;

		$state['raised'][ $period['key'] ][ $scope ] = round( $extra + $base / 2, 2 );
		update_option( self::STATE, $state, false );
		self::$fired = array();
		self::resume_jobs();
		return self::limit( $scope, $settings, $period );
	}

	/**
	 * Give jobs paused by a spending limit another chance.
	 *
	 * @return void
	 */
	public static function resume_jobs() {
		\QuantumCloud\WPBotCore\Jobs\Job_Store::resume_spend_waiting();
	}

	/**
	 * May this call run?
	 *
	 * @param string $module   Module id.
	 * @param string $feature  Feature id.
	 * @param float  $estimate Expected cost of the call in USD (0 when unknown).
	 * @param string $group    Spending group, when the caller knows better than the module map
	 *                         (the safety net maps files to groups).
	 * @return true|WP_Error
	 */
	public static function allow( $module, $feature, $estimate = 0.0, $group = '' ) {
		$group    = '' !== $group && Features::exists( $group ) ? $group : Features::group_of( $module, $feature );
		$settings = Spend_Settings::all();
		$period   = Period::current( $settings );
		$estimate = max( 0.0, (float) $estimate );

		if ( Spend_Settings::is_paused( $group ) ) {
			return self::refuse( 'paused', $group, $period, $module, $feature );
		}
		$limits = array(
			'global' => self::limit( 'global', $settings, $period ),
			$group   => self::limit( $group, $settings, $period ),
		);
		if ( max( $limits ) <= 0 ) {
			return true; // No limit set: nothing to read.
		}
		$totals = Ledger::totals( $period['start'] );
		foreach ( $limits as $scope => $limit ) {
			$spent = 'global' === $scope ? (float) $totals['global'] : ( isset( $totals['groups'][ $scope ] ) ? (float) $totals['groups'][ $scope ] : 0.0 );
			if ( $limit > 0 && ( $spent >= $limit || ( $estimate > 0 && $spent + $estimate > $limit ) ) ) {
				return self::refuse( $spent >= $limit ? 'limit' : 'estimate', $scope, $period, $module, $feature );
			}
		}
		return true;
	}

	/**
	 * Record one call.
	 *
	 * @param string $module  Module id.
	 * @param string $feature Feature id.
	 * @param array  $usage   Usage_Reader::blank() shape (missing keys are 0).
	 * @param array  $meta    provider, model, task, job_id, channel, source ('routed' or 'net'),
	 *                        cost (USD, skips the estimate), image_quality, group (overrides the map).
	 * @return array{id: int, cost: float, price_known: bool, group: string}
	 */
	public static function record( $module, $feature, array $usage, array $meta = array() ) {
		$module   = substr( sanitize_key( (string) $module ), 0, 40 );
		$feature  = substr( sanitize_key( (string) $feature ), 0, 60 );
		$usage    = array_merge( Usage_Reader::blank(), $usage );
		$provider = isset( $meta['provider'] ) ? substr( sanitize_key( (string) $meta['provider'] ), 0, 40 ) : '';
		$model    = isset( $meta['model'] ) && '' !== (string) $meta['model'] ? (string) $meta['model'] : (string) $usage['model'];
		$model    = substr( sanitize_text_field( $model ), 0, 120 );
		if ( isset( $meta['image_quality'] ) ) {
			$usage['image_quality'] = (string) $meta['image_quality'];
		}

		if ( isset( $meta['cost'] ) && is_numeric( $meta['cost'] ) ) {
			$price = array(
				'cost'        => max( 0.0, (float) $meta['cost'] ),
				'price_known' => ! isset( $meta['price_known'] ) || (bool) $meta['price_known'],
			);
		} else {
			$price = Prices::estimate( $provider, $model, $usage );
		}

		$group = isset( $meta['group'] ) && Features::exists( (string) $meta['group'] ) ? (string) $meta['group'] : Features::group_of( $module, $feature );
		$row   = array(
			'module'             => $module,
			'feature'            => $feature,
			'grp'                => $group,
			'task'               => substr( sanitize_key( isset( $meta['task'] ) ? (string) $meta['task'] : $feature ), 0, 60 ),
			'provider'           => $provider,
			'model'              => $model,
			'channel'            => substr( sanitize_key( isset( $meta['channel'] ) ? (string) $meta['channel'] : Features::channel_of( $module, $feature ) ), 0, 20 ),
			'source'             => isset( $meta['source'] ) && 'net' === $meta['source'] ? 'net' : 'routed',
			'job_id'             => isset( $meta['job_id'] ) ? (int) $meta['job_id'] : 0,
			'user_id'            => self::admin_user_id(),
			'tokens_in'          => (int) $usage['tokens_in'],
			'tokens_out'         => (int) $usage['tokens_out'],
			'tokens_cached'      => (int) $usage['tokens_cached'],
			'tokens_cache_write' => (int) $usage['tokens_cache_write'],
			'tokens_reasoning'   => (int) $usage['tokens_reasoning'],
			'images'             => (int) $usage['images'],
			'web_searches'       => (int) $usage['web_searches'],
			'audio_seconds'      => (int) $usage['audio_seconds'],
			'characters'         => (int) $usage['characters'],
			'cost'               => (float) $price['cost'],
			'price_known'        => (bool) $price['price_known'],
		);
		$id    = Ledger::insert( $row );

		/**
		 * One AI call was recorded (alerts listen here).
		 *
		 * @param array $row Stored row (no prompt or answer text is ever part of it).
		 */
		do_action( 'qcld_wpbot_core/spend_recorded', $row );

		return array(
			'id'          => $id,
			'cost'        => (float) $price['cost'],
			'price_known' => (bool) $price['price_known'],
			'group'       => $group,
		);
	}

	/**
	 * Allow, run the caller's own call, read its usage, record it.
	 *
	 * The callable returns a wp_remote_* response (its JSON body is read), a WP_Error, or any
	 * value plus $meta['usage'] (an array, or a callable that gets the result and returns one).
	 *
	 * @param string   $module  Module id.
	 * @param string   $feature Feature id.
	 * @param callable $call    The call.
	 * @param array    $meta    record() meta, plus estimate (USD) and usage.
	 * @return mixed|WP_Error The call's result, or the refusal.
	 */
	public static function wrap( $module, $feature, callable $call, array $meta = array() ) {
		$allowed = self::allow( $module, $feature, isset( $meta['estimate'] ) ? (float) $meta['estimate'] : 0.0 );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		++self::$metering;
		try {
			$result = call_user_func( $call );
		} finally {
			--self::$metering;
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$usage = null;
		if ( isset( $meta['usage'] ) ) {
			$usage = is_callable( $meta['usage'] ) ? call_user_func( $meta['usage'], $result ) : $meta['usage'];
		} elseif ( is_array( $result ) && isset( $result['body'], $result['response'] ) ) {
			$code = isset( $result['response']['code'] ) ? (int) $result['response']['code'] : 0;
			if ( $code >= 200 && $code < 300 ) {
				$usage = Usage_Reader::from_body( json_decode( (string) $result['body'], true ) );
				if ( null === $usage ) {
					$usage = Usage_Reader::blank();
				}
			}
		}
		if ( is_array( $usage ) ) {
			unset( $meta['usage'], $meta['estimate'] );
			self::record( $module, $feature, $usage, $meta );
		}
		return $result;
	}

	/**
	 * Whether a wrap() call is running (its HTTP request is already counted).
	 *
	 * @return bool
	 */
	public static function is_metering() {
		return self::$metering > 0;
	}

	/**
	 * Pre-flight check for an expensive job (bulk images, re-embedding).
	 *
	 * @param string $module   Module id.
	 * @param string $feature  Feature id.
	 * @param float  $estimate Estimated cost of the whole job (USD).
	 * @return array{estimate: float, remaining: float|null, fits: bool, scope: string}
	 *         remaining is null when no limit applies.
	 */
	public static function estimate_job( $module, $feature, $estimate ) {
		$group     = Features::group_of( $module, $feature );
		$status    = self::status();
		$remaining = null;
		$scope     = '';
		foreach ( array(
			'global' => $status['global'],
			$group   => isset( $status['groups'][ $group ] ) ? $status['groups'][ $group ] : null,
		) as $id => $entry ) {
			if ( $entry && $entry['limit'] > 0 ) {
				$left = max( 0.0, $entry['limit'] - $entry['spent'] );
				if ( null === $remaining || $left < $remaining ) {
					$remaining = $left;
					$scope     = $id;
				}
			}
		}
		$estimate = max( 0.0, round( (float) $estimate, 4 ) );
		return array(
			'estimate'  => $estimate,
			'remaining' => null === $remaining ? null : round( $remaining, 4 ),
			'fits'      => ! Spend_Settings::is_paused( $group ) && ( null === $remaining || $estimate <= $remaining ),
			'scope'     => $scope,
		);
	}

	/**
	 * Spend against limits for this period: global and every group.
	 *
	 * @return array{period: array, global: array, groups: array<string,array>}
	 *         Each entry: id, label, spent, limit, percent, state (ok, warn, over, paused).
	 */
	public static function status() {
		$settings = Spend_Settings::all();
		$period   = Period::current( $settings );
		$totals   = Ledger::totals( $period['start'] );
		$groups   = array();
		foreach ( Features::all() as $id => $label ) {
			$spent         = isset( $totals['groups'][ $id ] ) ? (float) $totals['groups'][ $id ] : 0.0;
			$groups[ $id ] = self::entry( $id, $label, $spent, self::limit( $id, $settings, $period ), Spend_Settings::is_paused( $id ) );
		}
		return array(
			'period' => $period,
			'global' => self::entry( 'global', __( 'All AI', 'chatbot' ), (float) $totals['global'], self::limit( 'global', $settings, $period ), false ),
			'groups' => $groups,
		);
	}

	/**
	 * The limit that applies this period (the owner's limit plus any one-click raise).
	 *
	 * @param string     $scope    'global' or a group id.
	 * @param array|null $settings Settings.
	 * @param array|null $period   Period.
	 * @return float 0 = no limit.
	 */
	public static function limit( $scope, $settings = null, $period = null ) {
		$settings = is_array( $settings ) ? $settings : Spend_Settings::all();
		$period   = is_array( $period ) ? $period : Period::current( $settings );
		$base     = 'global' === $scope ? (float) $settings['global'] : ( isset( $settings['limits'][ $scope ] ) ? (float) $settings['limits'][ $scope ] : 0.0 );
		if ( $base <= 0 ) {
			return 0.0;
		}
		$state = get_option( self::STATE, array() );
		$extra = isset( $state['raised'][ $period['key'] ][ $scope ] ) ? (float) $state['raised'][ $period['key'] ][ $scope ] : 0.0;
		return round( $base + max( 0.0, $extra ), 2 );
	}

	/**
	 * One status entry (pure).
	 *
	 * @param string $id     Id.
	 * @param string $label  Label.
	 * @param float  $spent  Spend.
	 * @param float  $limit  Limit (0 = none).
	 * @param bool   $paused Paused.
	 * @return array
	 */
	public static function entry( $id, $label, $spent, $limit, $paused ) {
		$percent = $limit > 0 ? (int) floor( $spent / $limit * 100 ) : 0;
		if ( $paused ) {
			$state = 'paused';
		} elseif ( $limit > 0 && $spent >= $limit ) {
			$state = 'over';
		} elseif ( $limit > 0 && $percent >= 80 ) {
			$state = 'warn';
		} else {
			$state = 'ok';
		}
		return array(
			'id'      => $id,
			'label'   => $label,
			'spent'   => round( $spent, 4 ),
			'limit'   => round( $limit, 2 ),
			'percent' => $percent,
			'state'   => $state,
		);
	}

	/**
	 * Refuse a call.
	 *
	 * @param string $reason  limit, estimate or paused.
	 * @param string $scope   'global' or a group id.
	 * @param array  $period  Period.
	 * @param string $module  Module id.
	 * @param string $feature Feature id.
	 * @return WP_Error
	 */
	private static function refuse( $reason, $scope, array $period, $module, $feature ) {
		if ( 'estimate' !== $reason && ! isset( self::$fired[ $scope ] ) ) {
			self::$fired[ $scope ] = true;
			/**
			 * A limit stopped an AI call (once per scope and request).
			 *
			 * @param string $scope   'global' or a group id.
			 * @param string $reason  limit or paused.
			 * @param string $module  Module id.
			 * @param string $feature Feature id.
			 */
			do_action( 'qcld_wpbot_core/spend_limit_reached', $scope, $reason, $module, $feature );
		}
		if ( 'paused' === $reason ) {
			$message = __( 'AI is paused for this feature in AI Settings > Spending and limits.', 'chatbot' );
		} elseif ( 'estimate' === $reason ) {
			$message = __( 'This job would go over your AI spending limit. Raise the limit in AI Settings > Spending and limits, or run a smaller job.', 'chatbot' );
		} else {
			/* translators: %s: date the limit resets. */
			$message = sprintf( __( 'Your AI spending limit is reached. Raise it in AI Settings > Spending and limits, or wait until it resets on %s.', 'chatbot' ), $period['end'] );
		}
		return new WP_Error(
			self::ERROR,
			$message,
			array(
				'status'    => 429,
				'cause'     => 'budget_reached',
				'reason'    => $reason,
				'scope'     => $scope,
				'resets_at' => $period['end'],
			)
		);
	}

	/**
	 * The user id kept with a call: only for admin actions, never for visitors.
	 *
	 * @return int
	 */
	private static function admin_user_id() {
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ? (int) get_current_user_id() : 0;
	}

	/**
	 * Forget per-request state (tests).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$fired    = array();
		self::$metering = 0;
		Ledger::flush();
		Spend_Settings::flush();
	}
}
