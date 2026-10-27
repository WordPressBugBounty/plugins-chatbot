<?php
/**
 * Unguessable IDs for public trigger nodes (Incoming Webhook, Facebook Lead Ads).
 *
 * The public URL of these triggers is built from the node ID
 * (/wp-json/wpbot-automator/v1/webhook/<id without "node-">), so the node ID is the secret.
 * Template nodes such as "node-trigger-1" are identical on every site; they are replaced with a
 * random ID when a workflow is saved, imported or migrated.
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webhook_Secrets
 */
class Webhook_Secrets {

	/** Trigger action IDs reachable from the public internet. */
	const PUBLIC_TRIGGERS = array( 'incoming_webhook', 'facebook_lead_ads' );

	/**
	 * Whether a node ID is hard to guess: 32+ random characters, or a legacy builder
	 * ID (millisecond timestamp) kept so existing integrations don't break.
	 */
	public static function is_strong( $node_id ) {
		return (bool) preg_match( '/^node-([A-Za-z0-9]{32,}|\d{13})$/', (string) $node_id );
	}

	/**
	 * Replace guessable IDs of public trigger nodes.
	 *
	 * @param array      $workflow_data Workflow data (nodes + connections).
	 * @param array|null $previous      Stored data before this save, so a re-save from an editor
	 *                                  that still holds the old ID keeps the same new ID.
	 * @return array
	 */
	public static function harden( $workflow_data, $previous = null ) {
		if ( ! is_array( $workflow_data ) || empty( $workflow_data['nodes'] ) || ! is_array( $workflow_data['nodes'] ) ) {
			return $workflow_data;
		}

		$known = array();
		if ( is_array( $previous ) && ! empty( $previous['nodes'] ) ) {
			foreach ( $previous['nodes'] as $node ) {
				if ( ! empty( $node['data']['legacy_node_id'] ) && isset( $node['id'] ) ) {
					$known[ $node['data']['legacy_node_id'] ] = $node['id'];
				}
			}
		}

		$renamed = array();
		foreach ( $workflow_data['nodes'] as $i => $node ) {
			$action_id = isset( $node['data']['actionId'] ) ? $node['data']['actionId'] : '';
			if ( ! isset( $node['id'] ) || ! in_array( $action_id, self::PUBLIC_TRIGGERS, true ) || self::is_strong( $node['id'] ) ) {
				continue;
			}
			$new_id = isset( $known[ $node['id'] ] ) ? $known[ $node['id'] ] : 'node-' . wp_generate_password( 32, false, false );

			$renamed[ $node['id'] ]                              = $new_id;
			$workflow_data['nodes'][ $i ]['data']['legacy_node_id'] = $node['id'];
			$workflow_data['nodes'][ $i ]['id']                     = $new_id;
		}

		if ( $renamed && ! empty( $workflow_data['connections'] ) && is_array( $workflow_data['connections'] ) ) {
			foreach ( $workflow_data['connections'] as $i => $conn ) {
				foreach ( array( 'from', 'to', 'source', 'target' ) as $end ) {
					if ( isset( $conn[ $end ] ) && isset( $renamed[ $conn[ $end ] ] ) ) {
						$workflow_data['connections'][ $i ][ $end ] = $renamed[ $conn[ $end ] ];
					}
				}
			}
		}

		return $workflow_data;
	}

	/**
	 * One-time pass over stored workflows (runs on upgrade).
	 */
	public static function migrate_existing_workflows() {
		global $wpdb;
		$table = Database::get_workflows_table();
		$rows  = $wpdb->get_results( "SELECT id, workflow_data FROM {$table}", ARRAY_A );

		foreach ( (array) $rows as $row ) {
			$data = json_decode( $row['workflow_data'], true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$hardened = self::harden( $data );
			if ( $hardened !== $data ) {
				$wpdb->update( $table, array( 'workflow_data' => wp_json_encode( $hardened ) ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );
			}
		}
	}
}
