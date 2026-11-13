<?php
/**
 * AI Writer bridge (build-brief SM3, D-amendment: "Send to Social" OPENS
 * Create post filled and never schedules).
 *
 * - qcld_wpbot_writer/social_active → true while Social runs (licensed).
 * - qcld_wpbot_writer/channels: Social adds the networks AI Writer does not
 *   know (Threads, Telegram, WhatsApp) with each one's writing rules.
 * - qcld_wpbot_writer/send_to_social: each piece becomes a Social draft,
 *   with the suggested time kept but not scheduled; the owner opens it from
 *   Posts (or the link AI Writer shows) and schedules it.
 *
 * Without AI Writer nothing here runs and Social still writes on its own.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Integrations;

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Posts\Composer;
use QuantumCloud\Social\Support\Log;
use QuantumCloud\Social\Writing\Network_Rules;

defined( 'ABSPATH' ) || exit;

/**
 * AI Writer bridge.
 */
final class Ai_Writer {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_writer/social_active', '__return_true' );
		add_filter( 'qcld_wpbot_writer/channels', array( __CLASS__, 'channels' ) );
		add_action( 'qcld_wpbot_writer/send_to_social', array( __CLASS__, 'receive' ) );
	}

	/**
	 * Add Social's networks to AI Writer's channel versions.
	 *
	 * @param array $channels id => label, kind, limit, rules, medium.
	 * @return array
	 */
	public static function channels( $channels ) {
		$channels = (array) $channels;
		foreach ( Network_Rules::defaults() as $network => $rule ) {
			if ( isset( $channels[ $network ] ) ) {
				continue;
			}
			$full                 = Network_Rules::get( $network );
			$channels[ $network ] = array(
				'label'  => $rule['label'],
				'kind'   => 'social',
				'limit'  => $full ? (int) $full['max'] : 0,
				'rules'  => $rule['rules'],
				'medium' => 'social',
			);
		}
		return $channels;
	}

	/**
	 * A piece from AI Writer: save it as a Social draft.
	 *
	 * @param array $piece text, channel, schedule (site time Y-m-d[ H:i] or ''), source (quick|version|campaign), ref.
	 * @return int Social post id, or 0.
	 */
	public static function receive( $piece ) {
		$piece   = (array) $piece;
		$text    = isset( $piece['text'] ) ? sanitize_textarea_field( (string) $piece['text'] ) : '';
		$channel = isset( $piece['channel'] ) ? sanitize_key( (string) $piece['channel'] ) : '';
		if ( '' === trim( $text ) ) {
			return 0;
		}
		$accounts = array();
		foreach ( Account_Store::all() as $account ) {
			if ( 'ok' === $account['status'] && $account['network'] === $channel ) {
				$accounts[] = (int) $account['id'];
			}
		}
		$rule = Network_Rules::get( $channel );
		if ( array() === $accounts && $rule && $rule['manual'] ) {
			$accounts[] = 'manual:' . $channel;
		}
		$ref      = isset( $piece['ref'] ) ? absint( $piece['ref'] ) : 0;
		$source   = isset( $piece['source'] ) ? sanitize_key( (string) $piece['source'] ) : '';
		$article  = 'version' === $source && $ref ? get_post( $ref ) : null;
		$schedule = isset( $piece['schedule'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', (string) $piece['schedule'] ) ? (string) $piece['schedule'] : '';
		if ( 10 === strlen( $schedule ) ) {
			$schedule .= ' 09:30';
		}
		$saved = Composer::save(
			array(
				'title'       => $article ? get_the_title( $article ) : __( 'From AI Writer', 'chatbot' ),
				'text'        => $text,
				'link'        => $article && 'publish' === $article->post_status ? get_permalink( $article ) : '',
				'accounts'    => $accounts,
				'overrides'   => '' !== $channel ? array( $channel => array( 'text' => $text ) ) : array(),
				'schedule'    => $schedule,
				'status'      => 'draft',
				'source_type' => 'ai_writer',
				'source_id'   => $ref,
			)
		);
		if ( is_wp_error( $saved ) ) {
			Log::add( 'error', 'ai_writer_piece', 'A piece from AI Writer could not be saved: ' . $saved->get_error_message() );
			return 0;
		}
		/* translators: %s: network. */
		Log::add( 'ok', 'ai_writer_piece', sprintf( 'Draft from AI Writer (%s) is waiting in Posts', '' !== $channel ? $channel : 'social' ), array( 'post' => (int) $saved['id'] ) );
		return (int) $saved['id'];
	}
}
