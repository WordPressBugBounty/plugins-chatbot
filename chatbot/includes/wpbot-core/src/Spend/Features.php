<?php
/**
 * The spending groups ("features" on screen) and which module/feature ids belong to each.
 *
 * Usage rows keep the module and feature that made the call. The spend strip, the limits and
 * the alerts work on groups, so a group's limit covers every call of its features, wherever
 * they are made (a module, an add-on, the safety net).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

defined( 'ABSPATH' ) || exit;

/**
 * Group registry.
 */
final class Features {

	/**
	 * Group for calls nobody has claimed yet (shown only when it has spend).
	 */
	const OTHER = 'other';

	/**
	 * Groups in display order: id => label.
	 *
	 * @return array<string,string>
	 */
	public static function all() {
		$groups = array(
			'chatbot'        => __( 'Chatbot', 'chatbot' ),
			'knowledge_base' => __( 'Knowledge base', 'chatbot' ),
			'chat_sessions'  => __( 'Chat sessions and reports', 'chatbot' ),
			'forms_tickets'  => __( 'Forms and support tickets', 'chatbot' ),
			'voice'          => __( 'Voice', 'chatbot' ),
			'images'         => __( 'Images', 'chatbot' ),
			'writer'         => __( 'AI Writer', 'chatbot' ),
			'seo'            => __( 'SEO & AI Search', 'chatbot' ),
			'social'         => __( 'Social', 'chatbot' ),
			'insights'       => __( 'Site Insight and AI Marketing', 'chatbot' ),
			'automator'      => __( 'Automator', 'chatbot' ),
		);

		/**
		 * Filter the spending groups (id => label). Ids are lowercase keys.
		 *
		 * @param array $groups Groups.
		 */
		$groups = (array) apply_filters( 'qcld_wpbot_core/spend_features', $groups );

		$clean = array();
		foreach ( $groups as $id => $label ) {
			$id = sanitize_key( (string) $id );
			if ( '' !== $id && self::OTHER !== $id ) {
				$clean[ $id ] = (string) $label;
			}
		}
		$clean[ self::OTHER ] = __( 'Other AI calls', 'chatbot' );
		return $clean;
	}

	/**
	 * Whether a group id exists.
	 *
	 * @param string $id Group id.
	 * @return bool
	 */
	public static function exists( $id ) {
		return array_key_exists( (string) $id, self::all() );
	}

	/**
	 * Group of one call.
	 *
	 * @param string $module  Module id (chatbot, writer, seo, social, insights, an add-on id…).
	 * @param string $feature Feature id inside the module.
	 * @return string Group id.
	 */
	public static function group_of( $module, $feature ) {
		$module  = sanitize_key( (string) $module );
		$feature = sanitize_key( (string) $feature );
		$group   = self::default_group( $module, $feature );

		/**
		 * Filter which spending group a module's feature belongs to.
		 *
		 * @param string $group   Group id.
		 * @param string $module  Module id.
		 * @param string $feature Feature id.
		 */
		$group = sanitize_key( (string) apply_filters( 'qcld_wpbot_core/spend_feature_group', $group, $module, $feature ) );
		return self::exists( $group ) ? $group : self::OTHER;
	}

	/**
	 * Group a module's budget moves to (the module's main feature).
	 *
	 * @param string $module Module id.
	 * @return string
	 */
	public static function group_of_module( $module ) {
		return self::group_of( $module, '' );
	}

	/**
	 * Built-in mapping (pure).
	 *
	 * @param string $module  Module id.
	 * @param string $feature Feature id.
	 * @return string
	 */
	public static function default_group( $module, $feature ) {
		// Features that mean the same thing in any module.
		$by_feature = array(
			'knowledge_base' => array( 'knowledge_base', 'kb', 'embed', 'rag' ),
			'chat_sessions'  => array( 'chat_sessions', 'session', 'report' ),
			'forms_tickets'  => array( 'form', 'ticket', 'support' ),
			'voice'          => array( 'voice', 'tts', 'stt', 'speech', 'transcri' ),
		);
		foreach ( $by_feature as $group => $starts ) {
			foreach ( $starts as $start ) {
				if ( '' !== $feature && 0 === strpos( $feature, $start ) ) {
					return $group;
				}
			}
		}

		$by_module = array(
			'chatbot'          => 'chatbot',
			'channels'         => 'chatbot',
			'knowledge_base'   => 'knowledge_base',
			'chat_sessions'    => 'chat_sessions',
			'forms'            => 'forms_tickets',
			'support_ticket'   => 'forms_tickets',
			'voice'            => 'voice',
			'ai_studio'        => 'images',
			'tryon'            => 'images',
			'images'           => 'images',
			'woo_insights'     => 'images',
			'writer'           => 'writer',
			'seo'              => 'seo',
			'ai_seo_marketing' => 'seo',
			'social'           => 'social',
			'insights'         => 'insights',
			'ai_marketing'     => 'insights',
			'automator'        => 'automator',
		);
		return isset( $by_module[ $module ] ) ? $by_module[ $module ] : self::OTHER;
	}

	/**
	 * Channel of a chatbot call ("chat_whatsapp" → whatsapp, "chat" → web).
	 *
	 * @param string $module  Module id.
	 * @param string $feature Feature id.
	 * @return string '' when the call is not a chat reply.
	 */
	public static function channel_of( $module, $feature ) {
		if ( 'chatbot' !== $module || 'chatbot' !== self::default_group( $module, $feature ) ) {
			return '';
		}
		if ( 'chat' === $feature ) {
			return 'web';
		}
		return 0 === strpos( (string) $feature, 'chat_' ) ? substr( sanitize_key( (string) $feature ), 5, 20 ) : '';
	}
}
