<?php
/**
 * What the owner lets WPBot do on Meta (screen 03, step 4), mapped to the
 * permissions asked for at login.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Accounts;

defined( 'ABSPATH' ) || exit;

/**
 * Meta permission groups.
 */
final class Meta_Scopes {

	/**
	 * Choice id => label, permissions, master-only.
	 *
	 * @return array<string,array{label:string, permissions:string[], master:bool}>
	 */
	public static function options() {
		return array(
			'publish_pages'     => array(
				'label'       => __( 'Publish to my Pages', 'chatbot' ),
				'permissions' => array( 'pages_show_list', 'pages_manage_posts', 'pages_read_engagement', 'business_management' ),
				'master'      => false,
			),
			'publish_instagram' => array(
				'label'       => __( 'Publish to Instagram', 'chatbot' ),
				'permissions' => array( 'instagram_basic', 'instagram_content_publish' ),
				'master'      => false,
			),
			'read_insights'     => array(
				'label'       => __( 'Read post results', 'chatbot' ),
				'permissions' => array( 'read_insights', 'instagram_manage_insights' ),
				'master'      => false,
			),
			'inbox'             => array(
				'label'       => __( 'Answer comments and messages in the Inbox', 'chatbot' ),
				'permissions' => array( 'pages_manage_engagement', 'pages_messaging', 'instagram_manage_comments' ),
				'master'      => true,
			),
		);
	}

	/**
	 * Permissions for chosen groups.
	 *
	 * @param string[] $chosen Choice ids.
	 * @return string[]
	 */
	public static function permissions( array $chosen ) {
		$out = array();
		foreach ( self::options() as $id => $option ) {
			if ( in_array( $id, $chosen, true ) ) {
				$out = array_merge( $out, $option['permissions'] );
			}
		}
		return array_values( array_unique( $out ) );
	}
}
