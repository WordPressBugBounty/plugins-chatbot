<?php
/**
 * Live chat agents: who may answer, their display names, and presence (plan 3.2: one small map,
 * updated by the console's poll; no user meta writes on admin pages).
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

/**
 * Agents and presence.
 */
final class Agents {

	/**
	 * Capability of agents (administrators always answer).
	 */
	const CAP = 'qcld_wpbot_live_agent';

	/**
	 * Option with the presence map (not autoloaded).
	 */
	const PRESENCE = 'qcld_wpbot_chat_live_presence';

	/**
	 * Seconds after the last poll an agent counts as online.
	 */
	const ONLINE_FOR = 60;

	/**
	 * Seconds between two presence writes for the same agent and status.
	 */
	const WRITE_EVERY = 30;

	/**
	 * Whether a user answers live chats.
	 *
	 * @param int $user_id User (0: current).
	 * @return bool
	 */
	public static function is_agent( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		$is = user_can( $user_id, 'manage_options' ) || user_can( $user_id, self::CAP );
		/**
		 * Whether a user answers live chats (WPBot Pro adds the agent roles).
		 *
		 * @param bool $is      Default: administrators and users with the agent capability.
		 * @param int  $user_id User.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/live_is_agent', $is, $user_id );
	}

	/**
	 * Whether a user sees every open conversation (managers).
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function is_manager( $user_id ) {
		return user_can( (int) $user_id, 'manage_options' );
	}

	/**
	 * Display name of an agent (first name when set).
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function name( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return '';
		}
		$first = (string) get_user_meta( (int) $user_id, 'first_name', true );
		return '' !== $first ? $first : (string) $user->display_name;
	}

	/**
	 * Departments of an agent ([] = every department).
	 *
	 * @param int $user_id User.
	 * @return string[]
	 */
	public static function departments( $user_id ) {
		/**
		 * Departments an agent answers (WPBot Pro). Empty: every department.
		 *
		 * @param string[] $departments Default none.
		 * @param int      $user_id     User.
		 */
		return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'qcld_wpbot_chat/live_agent_departments', array(), (int) $user_id ) ) ) );
	}

	/**
	 * Record that an agent's console polled.
	 *
	 * @param int    $user_id User.
	 * @param string $status  online or away.
	 * @return void
	 */
	public static function heartbeat( $user_id, $status = 'online' ) {
		$status = 'away' === $status ? 'away' : 'online';
		$map    = self::map();
		$now    = time();
		$was    = isset( $map[ $user_id ] ) ? $map[ $user_id ] : null;
		if ( $was && $was['s'] === $status && $now - (int) $was['t'] < self::WRITE_EVERY ) {
			return;
		}
		$map[ (int) $user_id ] = array(
			't' => $now,
			's' => $status,
			'd' => self::departments( $user_id ),
		);
		foreach ( $map as $id => $entry ) {
			if ( $now - (int) $entry['t'] > DAY_IN_SECONDS ) {
				unset( $map[ $id ] );
			}
		}
		update_option( self::PRESENCE, $map, false );
	}

	/**
	 * An agent closed the console or went offline.
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	public static function offline( $user_id ) {
		$map = self::map();
		if ( isset( $map[ $user_id ] ) ) {
			unset( $map[ $user_id ] );
			update_option( self::PRESENCE, $map, false );
		}
	}

	/**
	 * The presence map.
	 *
	 * @return array user id => { t: last poll, s: status, d: departments }.
	 */
	private static function map() {
		$map = get_option( self::PRESENCE, array() );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * Agents online now (optionally in a department).
	 *
	 * @param string $department Department ('' = any).
	 * @return int[] User ids.
	 */
	public static function online( $department = '' ) {
		$now = time();
		$out = array();
		foreach ( self::map() as $id => $entry ) {
			if ( 'online' !== $entry['s'] || $now - (int) $entry['t'] > self::ONLINE_FOR ) {
				continue;
			}
			if ( '' !== $department && ! empty( $entry['d'] ) && ! in_array( $department, (array) $entry['d'], true ) ) {
				continue;
			}
			$out[] = (int) $id;
		}
		return $out;
	}
}
