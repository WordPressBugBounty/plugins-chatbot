<?php
/**
 * Email templates: starting points for campaigns. A few starters are
 * created on first use; templates copied from the old module are kept.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Content;

use QuantumCloud\AiMarketing\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Templates.
 */
final class Templates {

	/**
	 * All templates (starters created on first call).
	 *
	 * @return array[]
	 */
	public static function all() {
		global $wpdb;
		$table = Schema::table( 'templates' );
		if ( ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			self::seed();
		}
		$rows = (array) $wpdb->get_results( "SELECT id, name, category, subject, content_html, is_builtin FROM `{$table}` ORDER BY is_builtin DESC, name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		foreach ( $rows as $i => $row ) {
			$rows[ $i ]['id']         = (int) $row['id'];
			$rows[ $i ]['is_builtin'] = (bool) $row['is_builtin'];
		}
		return $rows;
	}

	/**
	 * Save a campaign's content as a template.
	 *
	 * @param string $name    Name.
	 * @param string $subject Subject.
	 * @param string $html    Content.
	 * @return int|WP_Error
	 */
	public static function create( $name, $subject, $html ) {
		global $wpdb;
		$name = trim( sanitize_text_field( (string) $name ) );
		if ( '' === $name ) {
			return new WP_Error( 'qcld_mk_name', __( 'Give it a name.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$now = current_time( 'mysql', true );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'templates' ),
			array(
				'name'         => substr( $name, 0, 190 ),
				'category'     => 'saved',
				'subject'      => sanitize_text_field( (string) $subject ),
				'content_html' => wp_kses_post( (string) $html ),
				'is_builtin'   => 0,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a saved template (starters stay).
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'templates' ),
			array(
				'id'         => (int) $id,
				'is_builtin' => 0,
			)
		);
	}

	/**
	 * Create the starter templates.
	 *
	 * @return void
	 */
	private static function seed() {
		global $wpdb;
		$now      = current_time( 'mysql', true );
		$starters = array(
			array(
				__( 'Newsletter', 'chatbot' ),
				'newsletter',
				__( 'News from {site_name}', 'chatbot' ),
				'<h1>' . esc_html__( 'Hi {first_name|there},', 'chatbot' ) . '</h1><p>' . esc_html__( 'Here is what is new this month.', 'chatbot' ) . '</p><h2>' . esc_html__( 'Story title', 'chatbot' ) . '</h2><p>' . esc_html__( 'A short summary of the story.', 'chatbot' ) . '</p><p><a href="{site_url}" style="background:#5b4e96;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;display:inline-block;">' . esc_html__( 'Read more', 'chatbot' ) . '</a></p>',
			),
			array(
				__( 'Announcement', 'chatbot' ),
				'promotion',
				__( 'Something new at {site_name}', 'chatbot' ),
				'<h1>' . esc_html__( 'Big news, {first_name|friend}!', 'chatbot' ) . '</h1><p>' . esc_html__( 'Tell your subscribers what is new and why it matters to them.', 'chatbot' ) . '</p><p><a href="{site_url}" style="background:#5b4e96;color:#fff;padding:12px 22px;border-radius:6px;text-decoration:none;display:inline-block;">' . esc_html__( 'See it now', 'chatbot' ) . '</a></p>',
			),
			array(
				__( 'Plain letter', 'chatbot' ),
				'plain',
				'',
				'<p>' . esc_html__( 'Hi {first_name|there},', 'chatbot' ) . '</p><p>' . esc_html__( 'Write your message here.', 'chatbot' ) . '</p><p>' . esc_html__( 'Thanks,', 'chatbot' ) . '<br>{site_name}</p>',
			),
		);
		foreach ( $starters as $starter ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				Schema::table( 'templates' ),
				array(
					'name'         => $starter[0],
					'category'     => $starter[1],
					'subject'      => $starter[2],
					'content_html' => $starter[3],
					'is_builtin'   => 1,
					'created_at'   => $now,
					'updated_at'   => $now,
				)
			);
		}
	}
}
