<?php
/**
 * Help Center → AI & GitHub (HC3-A, owner 20 November 2026): connect Claude, give it a prompt
 * for a GitHub repository, choose drafts or auto-publish, review suggestions and see what the
 * AI changed. Uses WPBot Core's admin design when it is available.
 *
 * The connection key is a WordPress Application Password for the signed-in user. It is shown
 * once, in the response to the form that creates it, and is never stored by the Help Center.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Front\Data;
use QuantumCloud\HelpCenter\Mcp\Change_Log;
use QuantumCloud\HelpCenter\Mcp\Docs_Server;
use QuantumCloud\HelpCenter\Mcp\Review;
use QuantumCloud\HelpCenter\Mcp\Server;
use QuantumCloud\HelpCenter\Mcp\Tools;
use QuantumCloud\HelpCenter\Permissions;
use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * AI & GitHub page.
 */
final class Ai_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'wpbot-help-center-ai';

	/**
	 * Application Password name.
	 */
	const KEY_NAME = 'WPBot Help Center (Claude)';

	/**
	 * The key created in this request (shown once).
	 *
	 * @var string
	 */
	private static $new_key = '';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_qcld_wpbot_help_center_ai', array( self::class, 'save' ) );
	}

	/**
	 * The page address.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( $args, admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG ) );
	}

	/**
	 * Add the page.
	 *
	 * @return void
	 */
	public static function menu() {
		$hook = add_submenu_page( 'edit.php?post_type=' . Post_Type::NAME, __( 'AI & GitHub', 'chatbot' ), __( 'AI & GitHub', 'chatbot' ), 'edit_wpbot_articles', self::SLUG, array( self::class, 'render' ), 88 );
		if ( $hook ) {
			add_action( 'load-' . $hook, array( self::class, 'maybe_create_key' ) );
		}
	}

	/**
	 * Whether Application Passwords can be used by the current user (HTTPS, or a local site).
	 *
	 * @return bool
	 */
	public static function keys_available() {
		return function_exists( 'wp_is_application_passwords_available_for_user' ) && class_exists( '\WP_Application_Passwords' ) && wp_is_application_passwords_available_for_user( wp_get_current_user() );
	}

	/**
	 * Create a connection key when the form was posted to this page (shown in this response only).
	 *
	 * @return void
	 */
	public static function maybe_create_key() {
		if ( empty( $_POST['qcld_hc_create_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked below.
			return;
		}
		check_admin_referer( 'qcld_hc_create_key' );
		if ( ! current_user_can( 'edit_wpbot_articles' ) || ! self::keys_available() ) {
			wp_die( esc_html__( 'You cannot create a connection key here.', 'chatbot' ), 403 );
		}
		$created = \WP_Application_Passwords::create_new_application_password( get_current_user_id(), array( 'name' => self::KEY_NAME ) );
		if ( ! is_wp_error( $created ) && isset( $created[0] ) ) {
			self::$new_key = (string) $created[0];
			Change_Log::add( 'key_created', 'connection', '', get_current_user_id(), 'Connection key created' );
		}
	}

	/**
	 * The HTTP Basic value for a key.
	 *
	 * @param string $key Application Password.
	 * @return string
	 */
	public static function basic( $key ) {
		return 'Basic ' . base64_encode( wp_get_current_user()->user_login . ':' . preg_replace( '/\s+/', '', $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic.
	}

	/**
	 * Claude Code command.
	 *
	 * @param string $auth Authorization value.
	 * @return string
	 */
	public static function claude_code_command( $auth ) {
		return 'claude mcp add --transport http wpbot-help-center ' . Server::url() . ' --header "Authorization: ' . $auth . '"';
	}

	/**
	 * Claude Desktop configuration (through mcp-remote).
	 *
	 * @param string $auth Authorization value.
	 * @return string
	 */
	public static function claude_desktop_config( $auth ) {
		return (string) wp_json_encode(
			array(
				'mcpServers' => array(
					'wpbot-help-center' => array(
						'command' => 'npx',
						'args'    => array( '-y', 'mcp-remote', Server::url(), '--header', 'Authorization:${AUTH_HEADER}' ),
						'env'     => array( 'AUTH_HEADER' => $auth ),
					),
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * The ready-made prompt.
	 *
	 * @param string $repo Repository address or owner/name.
	 * @return string
	 */
	public static function prompt( $repo ) {
		$repo = '' !== trim( $repo ) ? trim( $repo ) : 'https://github.com/OWNER/REPOSITORY';
		/* translators: %s: GitHub repository. */
		return sprintf( __( "Use the wpbot-help-center tools to build the help center of my WordPress site from the GitHub repository %s.\n\n1. Read the repository: README, docs/, readme.txt, the changelog, settings screens and help texts, shortcodes and blocks. Skip code that customers never see, keys, vendor and build folders.\n2. Call helpcenter_get_structure, then propose a structure: knowledge bases, categories and the articles in each. Wait for my OK.\n3. Create the knowledge bases and categories, then write each article in Markdown for customers (not developers): what it is for, numbered steps, where to click, tips and warnings. Add the GitHub path and commit as the source.\n4. Use stable slugs, so running this again updates the articles instead of duplicating them.\n5. Then check helpcenter_list_gaps and write articles for the questions visitors asked.\n6. Finish with a list of what you created.", 'chatbot' ), $repo );
	}

	/**
	 * Save: publishing mode, publish all AI drafts, apply or dismiss a suggestion.
	 *
	 * @return void
	 */
	public static function save() {
		check_admin_referer( 'qcld_wpbot_help_center_ai' );
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$back = self::url();
		switch ( $do ) {
			case 'mode':
				if ( ! current_user_can( Permissions::cap( 'settings' ) ) ) {
					wp_die( esc_html__( 'You are not allowed to change the Help Center settings.', 'chatbot' ), 403 );
				}
				$settings               = Settings::all();
				$settings['ai_mode']    = isset( $_POST['ai_mode'] ) && 'autopilot' === sanitize_key( wp_unslash( $_POST['ai_mode'] ) ) ? 'autopilot' : 'review';
				$settings['ai_publish'] = 'autopilot' === $settings['ai_mode'] ? 'publish' : 'draft';
				foreach ( array( 'auto_unpublish', 'auto_human_edited', 'undo_limit', 'auto_digest', 'use_queue' ) as $key ) {
					$settings[ $key ] = empty( $_POST[ $key ] ) ? '' : '1';
				}
				$settings['undo_days'] = isset( $_POST['undo_days'] ) ? max( 1, min( 365, absint( $_POST['undo_days'] ) ) ) : 30;
				update_option( Settings::OPTION, $settings );
				Review::schedule();
				$back = self::url( array( 'hc_ai' => 'mode' ) ) . '#hc-ai-review';
				break;
			case 'docs':
				if ( ! current_user_can( Permissions::cap( 'settings' ) ) ) {
					wp_die( esc_html__( 'You are not allowed to change the Help Center settings.', 'chatbot' ), 403 );
				}
				$settings                   = Settings::all();
				$settings['docs_mcp']       = empty( $_POST['docs_mcp'] ) ? '' : '1';
				$settings['docs_mcp_proxy'] = empty( $_POST['docs_mcp_proxy'] ) ? '' : '1';
				$shared                     = isset( $_POST['docs_kb'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_POST['docs_kb'] ) ) : array();
				$excluded                   = array();
				foreach ( Data::knowledge_bases() as $kb ) {
					if ( ! in_array( $kb->slug, $shared, true ) ) {
						$excluded[] = $kb->slug;
					}
				}
				$settings['docs_mcp_exclude'] = $excluded;
				update_option( Settings::OPTION, $settings );
				$back = self::url( array( 'hc_ai' => 'docs' ) ) . '#hc-ai-docs';
				break;
			case 'publish_all':
				$count = Bulk_Publish::publish( Tools::ai_drafts() );
				$back  = self::url( array( 'hc_ai' => 'published-' . $count ) );
				break;
			case 'apply':
			case 'dismiss':
			case 'dismiss_unpublish':
				$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
				if ( 'apply' === $do ) {
					$ok = Tools::apply_proposal( $post_id );
				} else {
					$ok = Tools::dismiss( $post_id, 'dismiss_unpublish' === $do ? 'unpublish' : 'proposal' );
				}
				$back = self::url( array( 'hc_ai' => $ok ? $do : 'failed' ) );
				break;
		}//end switch
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Message after an action (in the WPBot Core strip when the design is on).
	 *
	 * @return string
	 */
	private static function message() {
		$code = isset( $_GET['hc_ai'] ) ? sanitize_key( wp_unslash( $_GET['hc_ai'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A message after a redirect.
		if ( 0 === strpos( $code, 'published-' ) ) {
			$count = (int) substr( $code, 10 );
			/* translators: %d: number of articles. */
			return sprintf( _n( '%d AI draft published.', '%d AI drafts published.', $count, 'chatbot' ), $count );
		}
		$messages = array(
			'mode'              => __( 'Saved.', 'chatbot' ),
			'docs'              => __( 'Saved.', 'chatbot' ),
			'apply'             => __( 'Suggestion applied.', 'chatbot' ),
			'dismiss'           => __( 'Suggestion dismissed.', 'chatbot' ),
			'dismiss_unpublish' => __( 'Suggestion dismissed.', 'chatbot' ),
			'failed'            => __( 'That did not work. Reload the page and try again.', 'chatbot' ),
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
	}

	/**
	 * A hidden-field form opening for save().
	 *
	 * @param string $action Action.
	 * @return string
	 */
	private static function form( $action ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wpb-hc-ai__inline">' . wp_nonce_field( 'qcld_wpbot_help_center_ai', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="qcld_wpbot_help_center_ai"><input type="hidden" name="do" value="' . esc_attr( $action ) . '">';
	}

	/**
	 * Copy box.
	 *
	 * @param string $id    Element id.
	 * @param string $label Label.
	 * @param string $text  Text.
	 * @param int    $rows  Rows.
	 * @return string
	 */
	private static function copy_box( $id, $label, $text, $rows = 3 ) {
		return '<div class="wpb-hc-ai__copy"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><textarea id="' . esc_attr( $id ) . '" readonly rows="' . (int) $rows . '" spellcheck="false">' . esc_textarea( $text ) . '</textarea><button type="button" class="button" data-hc-copy="' . esc_attr( $id ) . '">' . esc_html__( 'Copy', 'chatbot' ) . '</button></div>';
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	public static function render() {
		$design = class_exists( '\QuantumCloud\WPBotCore\Admin\Design' ) && method_exists( '\QuantumCloud\WPBotCore\Admin\Design', 'page_head' );
		echo '<div class="wrap wpb-hc-ai">';
		$title = __( 'AI & GitHub', 'chatbot' );
		$desc  = __( 'Connect Claude to your help center. Claude reads your GitHub repository with its own access and writes the knowledge bases, categories and articles here.', 'chatbot' );
		if ( $design ) {
			\QuantumCloud\WPBotCore\Admin\Design::page_head(
				array(
					'title'       => $title,
					'description' => $desc,
				)
			);
		} else {
			echo '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $desc ) . '</p>';
		}
		echo '<hr class="wp-header-end">';
		$message = self::message();
		if ( '' !== $message ) {
			echo '<div class="wpb-strip notice notice-success" role="status"><p>' . esc_html( $message ) . '</p></div>';
		}
		self::section_connect();
		self::section_prompt();
		self::section_review();
		self::section_suggestions();
		self::section_log();
		self::section_docs();
		echo '</div>';
		self::assets();
	}

	/**
	 * Open a section (title and text on the left, content on the right).
	 *
	 * @param string $id    Anchor.
	 * @param string $title Title.
	 * @param string $text  One-line explanation.
	 * @return void
	 */
	private static function open( $id, $title, $text ) {
		echo '<section class="wpb-hc-ai__section" id="' . esc_attr( $id ) . '"><div class="wpb-hc-ai__intro"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p></div><div class="wpb-hc-ai__body">';
	}

	/**
	 * Close a section.
	 *
	 * @return void
	 */
	private static function close() {
		echo '</div></section>';
	}

	/**
	 * 1. Connect Claude.
	 *
	 * @return void
	 */
	private static function section_connect() {
		self::open( 'hc-ai-connect', __( '1. Connect Claude', 'chatbot' ), __( 'Claude Code (terminal or desktop app) and Claude Desktop connect with a key for your WordPress account. Claude can do what your account may do in the Help Center.', 'chatbot' ) );
		if ( ! self::keys_available() ) {
			echo '<p class="wpb-hc-ai__warn">' . esc_html__( 'Connection keys (WordPress Application Passwords) need your site on HTTPS, or are turned off on this site. Turn on HTTPS, then come back.', 'chatbot' ) . '</p>';
			self::close();
			return;
		}
		if ( '' !== self::$new_key ) {
			$auth = self::basic( self::$new_key );
			echo '<p class="wpb-hc-ai__ok"><strong>' . esc_html__( 'Your connection key is ready. It is shown only now: copy it into Claude before you leave this page.', 'chatbot' ) . '</strong></p>';
			echo self::copy_box( 'hc-ai-code', __( 'Claude Code: run this in a terminal', 'chatbot' ), self::claude_code_command( $auth ), 3 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in copy_box().
			echo self::copy_box( 'hc-ai-desktop', __( 'Claude Desktop: add this to claude_desktop_config.json (needs Node.js)', 'chatbot' ), self::claude_desktop_config( $auth ), 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in copy_box().
			echo '<p class="description">' . esc_html__( 'Then restart Claude and ask it to list the wpbot-help-center tools.', 'chatbot' ) . '</p>';
		} else {
			echo '<form method="post" action="' . esc_url( self::url() ) . '">';
			wp_nonce_field( 'qcld_hc_create_key' );
			echo '<input type="hidden" name="qcld_hc_create_key" value="1"><button type="submit" class="button button-primary">' . esc_html__( 'Create a connection key', 'chatbot' ) . '</button></form>';
		}
		echo '<p class="description">' . sprintf(
			/* translators: 1: endpoint address, 2: link to the profile. */
			esc_html__( 'Server address: %1$s. You can see and revoke keys in %2$s.', 'chatbot' ),
			'<code>' . esc_html( Server::url() ) . '</code>',
			'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'your profile', 'chatbot' ) . '</a>'
		) . '</p>';
		self::close();
	}

	/**
	 * 2. The prompt.
	 *
	 * @return void
	 */
	private static function section_prompt() {
		self::open( 'hc-ai-prompt', __( '2. Tell Claude what to build', 'chatbot' ), __( 'Paste this into Claude. Claude reads the repository with its own GitHub access (in Claude Code, the gh command); your site never needs a GitHub token.', 'chatbot' ) );
		echo '<p><label for="hc-ai-repo">' . esc_html__( 'GitHub repository', 'chatbot' ) . '</label><br><input type="url" id="hc-ai-repo" class="regular-text" placeholder="https://github.com/owner/repository" data-hc-prompt-template="' . esc_attr( self::prompt( '%REPO%' ) ) . '"></p>';
		echo self::copy_box( 'hc-ai-prompt-text', __( 'Prompt', 'chatbot' ), self::prompt( '' ), 11 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in copy_box().
		self::close();
	}

	/**
	 * 3. Review or auto-publish.
	 *
	 * @return void
	 */
	private static function section_review() {
		self::open( 'hc-ai-review', __( '3. Review or Autopilot', 'chatbot' ), __( 'Choose whether what Claude writes waits for you or goes live at once. Every change keeps a revision and is logged.', 'chatbot' ) );
		$mode  = Settings::ai_mode();
		$queue = class_exists( Review::QUEUE );
		if ( current_user_can( Permissions::cap( 'settings' ) ) ) {
			$switch = static function ( $key, $label, $help, $extra = '' ) {
				return '<label class="wpb-hc-ai__choice"><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( Settings::on( $key ), true, false ) . '> ' . esc_html( $label ) . $extra . ( '' !== $help ? '<span>' . esc_html( $help ) . '</span>' : '' ) . '</label>';
			};
			echo self::form( 'mode' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
			echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Mode', 'chatbot' ) . '</legend>';
			echo '<label class="wpb-hc-ai__choice"><input type="radio" name="ai_mode" value="review"' . checked( $mode, 'review', false ) . '> <strong>' . esc_html__( 'Review (recommended)', 'chatbot' ) . '</strong><span>' . esc_html__( 'New articles are saved as drafts. Changes to published articles and unpublishing wait for your approval.', 'chatbot' ) . '</span></label>';
			echo '<label class="wpb-hc-ai__choice"><input type="radio" name="ai_mode" value="autopilot"' . checked( $mode, 'autopilot', false ) . '> <strong>' . esc_html__( 'Autopilot', 'chatbot' ) . '</strong><span>' . esc_html__( 'New articles are published and changes apply at once. You can undo each one.', 'chatbot' ) . '</span></label>';
			echo '</fieldset><fieldset class="wpb-hc-ai__row"><legend><strong>' . esc_html__( 'In Autopilot', 'chatbot' ) . '</strong></legend>';
			echo $switch( 'auto_unpublish', __( 'Claude may unpublish articles', 'chatbot' ), __( 'Off: unpublishing waits for your approval.', 'chatbot' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $switch.
			echo $switch( 'auto_human_edited', __( 'Claude may change articles a person edited', 'chatbot' ), __( 'Off: those changes wait for your approval.', 'chatbot' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $switch.
			$days = '<input type="number" name="undo_days" min="1" max="365" value="' . esc_attr( (string) max( 1, (int) Settings::all()['undo_days'] ) ) . '" class="small-text" aria-label="' . esc_attr__( 'Days', 'chatbot' ) . '"> ' . esc_html__( 'days', 'chatbot' );
			echo $switch( 'undo_limit', __( 'Limit Undo to', 'chatbot' ), __( 'Off: changes can be undone at any time, as long as nobody edited the article since.', 'chatbot' ), ' ' . $days ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $switch and above.
			echo $switch( 'auto_digest', __( 'Email me a daily summary of what Claude changed', 'chatbot' ), __( 'Sent to the site\'s admin email, only on days with changes.', 'chatbot' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $switch.
			echo '</fieldset>';
			if ( $queue ) {
				echo '<fieldset class="wpb-hc-ai__row"><legend><strong>' . esc_html__( 'Where you review', 'chatbot' ) . '</strong></legend>';
				echo $switch( 'use_queue', __( 'Review Claude\'s changes in ChatBot → Train → Changes', 'chatbot' ), __( 'Off: they stay in the Suggestions list on this page.', 'chatbot' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $switch.
				echo '</fieldset>';
			}
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save', 'chatbot' ) . '</button></form>';
		} else {
			echo '<p>' . esc_html( 'autopilot' === $mode ? __( 'Autopilot: Claude\'s articles and changes go live at once.', 'chatbot' ) : __( 'Review: Claude\'s articles are saved as drafts and changes wait for approval.', 'chatbot' ) ) . '</p>';
		}//end if
		$drafts = Tools::ai_drafts();
		if ( $drafts && current_user_can( 'publish_wpbot_articles' ) ) {
			echo '<div class="wpb-hc-ai__row">';
			/* translators: %d: number of drafts. */
			echo '<p>' . esc_html( sprintf( _n( '%d draft written by Claude is waiting.', '%d drafts written by Claude are waiting.', count( $drafts ), 'chatbot' ), count( $drafts ) ) ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&post_status=draft' ) ) . '">' . esc_html__( 'Review drafts', 'chatbot' ) . '</a></p>';
			echo self::form( 'publish_all' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
			echo '<button type="submit" class="button">' . esc_html__( 'Publish all AI drafts', 'chatbot' ) . '</button></form></div>';
		}
		self::close();
	}

	/**
	 * Suggestions.
	 *
	 * @return void
	 */
	private static function section_suggestions() {
		if ( Review::active() ) {
			self::open( 'hc-ai-suggestions', __( 'Changes waiting', 'chatbot' ), __( 'Claude\'s changes to published articles, to articles a person edited, and unpublishing, wait for you in ChatBot → Train → Changes. Autopilot changes are listed there too, with Undo.', 'chatbot' ) );
			$count = Review::waiting();
			$url   = Review::queue_url();
			/* translators: %d: number of changes. */
			echo '<p class="' . ( $count ? 'wpb-hc-ai__warn' : 'wpb-hc-ai__empty' ) . '">' . esc_html( $count ? sprintf( _n( '%d change is waiting for you.', '%d changes are waiting for you.', $count, 'chatbot' ), $count ) : __( 'No changes are waiting.', 'chatbot' ) ) . ( '' !== $url ? ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Train → Changes', 'chatbot' ) . '</a>' : '' ) . '</p>';
			self::close();
			return;
		}
		$posts = Tools::with_suggestions();
		self::open( 'hc-ai-suggestions', __( 'Suggestions', 'chatbot' ), __( 'Changes Claude made to articles a person edited, or to published articles while you review, and articles Claude suggests unpublishing.', 'chatbot' ) );
		if ( ! $posts ) {
			echo '<p class="wpb-hc-ai__empty">' . esc_html__( 'No suggestions.', 'chatbot' ) . '</p>';
			self::close();
			return;
		}
		echo '<ul class="wpb-hc-ai__list">';
		foreach ( $posts as $post ) {
			$proposal  = get_post_meta( $post->ID, Tools::PROPOSAL, true );
			$unpublish = get_post_meta( $post->ID, Tools::UNPUBLISH, true );
			echo '<li><a href="' . esc_url( (string) get_edit_post_link( $post->ID ) ) . '"><strong>' . esc_html( get_the_title( $post ) ) . '</strong></a> ';
			if ( is_array( $proposal ) ) {
				echo '<span>' . esc_html__( 'New version from Claude', 'chatbot' ) . '</span> ';
				echo self::form( 'apply' ) . '<input type="hidden" name="post_id" value="' . (int) $post->ID . '"><button type="submit" class="button button-small button-primary">' . esc_html__( 'Apply', 'chatbot' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
				echo self::form( 'dismiss' ) . '<input type="hidden" name="post_id" value="' . (int) $post->ID . '"><button type="submit" class="button button-small">' . esc_html__( 'Dismiss', 'chatbot' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
			}
			if ( is_array( $unpublish ) ) {
				/* translators: %s: reason. */
				echo '<span>' . esc_html( sprintf( __( 'Claude suggests unpublishing: %s', 'chatbot' ), (string) $unpublish['reason'] ) ) . '</span> ';
				echo self::form( 'dismiss_unpublish' ) . '<input type="hidden" name="post_id" value="' . (int) $post->ID . '"><button type="submit" class="button button-small">' . esc_html__( 'Dismiss', 'chatbot' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
			}
			echo '</li>';
		}
		echo '</ul>';
		self::close();
	}

	/**
	 * Recent changes.
	 *
	 * @return void
	 */
	private static function section_log() {
		self::open( 'hc-ai-log', __( 'What Claude changed', 'chatbot' ), __( 'The latest changes made through the Help Center tools.', 'chatbot' ) );
		$log = Change_Log::all( 25 );
		if ( ! $log ) {
			echo '<p class="wpb-hc-ai__empty">' . esc_html__( 'Nothing yet.', 'chatbot' ) . '</p>';
			self::close();
			return;
		}
		$labels = array(
			'created'             => __( 'Created', 'chatbot' ),
			'updated'             => __( 'Updated', 'chatbot' ),
			'suggested'           => __( 'Suggested a change', 'chatbot' ),
			'unpublish_suggested' => __( 'Suggested unpublishing', 'chatbot' ),
			'ordered'             => __( 'Changed the order', 'chatbot' ),
			'applied'             => __( 'Suggestion applied', 'chatbot' ),
			'dismissed'           => __( 'Suggestion dismissed', 'chatbot' ),
			'key_created'         => __( 'Connection key created', 'chatbot' ),
			'published'           => __( 'Published (approved)', 'chatbot' ),
			'unpublished'         => __( 'Unpublished (approved)', 'chatbot' ),
			'auto_published'      => __( 'Published (Autopilot)', 'chatbot' ),
			'auto_updated'        => __( 'Changed (Autopilot)', 'chatbot' ),
			'auto_unpublished'    => __( 'Unpublished (Autopilot)', 'chatbot' ),
			'undone'              => __( 'Undone', 'chatbot' ),
		);
		$types  = array(
			'article'        => __( 'Article', 'chatbot' ),
			'category'       => __( 'Category', 'chatbot' ),
			'knowledge_base' => __( 'Knowledge base', 'chatbot' ),
			'connection'     => __( 'Connection', 'chatbot' ),
		);
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'When', 'chatbot' ) . '</th><th scope="col">' . esc_html__( 'What', 'chatbot' ) . '</th><th scope="col">' . esc_html__( 'Item', 'chatbot' ) . '</th></tr></thead><tbody>';
		foreach ( $log as $entry ) {
			$what = isset( $labels[ $entry['action'] ] ) ? $labels[ $entry['action'] ] : $entry['action'];
			$type = isset( $types[ $entry['type'] ] ) ? $types[ $entry['type'] ] : $entry['type'];
			if ( 'article' === $entry['type'] && $entry['id'] && get_post( (int) $entry['id'] ) ) {
				$name  = get_the_title( (int) $entry['id'] );
				$item  = '<a href="' . esc_url( (string) get_edit_post_link( (int) $entry['id'] ) ) . '">' . esc_html( '' !== $name ? $name : $entry['slug'] ) . '</a>';
				$item .= '' !== $entry['note'] && $entry['note'] !== $name ? ' <span class="wpb-hc-ai__note">' . esc_html( $entry['note'] ) . '</span>' : '';
			} else {
				$item = esc_html( '' !== $entry['note'] ? $entry['note'] : $entry['slug'] );
			}
			/* translators: %s: time ago. */
			echo '<tr><td>' . esc_html( sprintf( __( '%s ago', 'chatbot' ), human_time_diff( (int) $entry['time'] ) ) ) . '</td><td>' . esc_html( $what . ' · ' . $type ) . '</td><td>' . $item . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $item is escaped above.
		}
		echo '</tbody></table>';
		self::close();
	}

	/**
	 * The public docs server for customers' own AI assistants (HC3-B).
	 *
	 * @return void
	 */
	private static function section_docs() {
		self::open( 'hc-ai-docs', __( "Let your customers' AI assistants read your docs", 'chatbot' ), __( 'A public, read-only address that Claude, ChatGPT, Cursor and other assistants can connect to, so your customers get answers from your published articles with links to them. No key: it shares only what a signed-out visitor can already read.', 'chatbot' ) );
		$on       = Docs_Server::enabled();
		$can      = current_user_can( Permissions::cap( 'settings' ) );
		$excluded = Docs_Server::excluded();
		echo self::form( 'docs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		echo '<label class="wpb-hc-ai__choice"><input type="checkbox" name="docs_mcp" value="1"' . checked( $on, true, false ) . disabled( $can, false, false ) . '> ' . esc_html__( 'Turn on the public docs server', 'chatbot' ) . '<span>' . esc_html__( 'Off by default. Searches that find nothing appear in Knowledge gaps.', 'chatbot' ) . '</span></label>';
		echo '<label class="wpb-hc-ai__choice"><input type="checkbox" name="docs_mcp_proxy" value="1"' . checked( '1' === (string) Settings::all()['docs_mcp_proxy'], true, false ) . disabled( $can, false, false ) . '> ' . esc_html__( 'Visitors reach this site through Cloudflare or another proxy', 'chatbot' ) . '<span>' . esc_html__( 'Each visitor may make 120 requests every 10 minutes. Behind a proxy, turn this on so visitors are told apart by the address the proxy passes on. Leave it off otherwise: anyone can send that header.', 'chatbot' ) . '</span></label>';
		$kbs = Data::knowledge_bases();
		if ( count( $kbs ) > 1 ) {
			echo '<fieldset class="wpb-hc-ai__row"><legend><strong>' . esc_html__( 'Knowledge bases it shares', 'chatbot' ) . '</strong></legend>';
			foreach ( $kbs as $kb ) {
				echo '<label class="wpb-hc-ai__choice"><input type="checkbox" name="docs_kb[]" value="' . esc_attr( $kb->slug ) . '"' . checked( ! in_array( $kb->slug, $excluded, true ), true, false ) . disabled( $can, false, false ) . '> ' . esc_html( $kb->name ) . '</label>';
			}
			echo '</fieldset>';
		} else {
			foreach ( $kbs as $kb ) {
				echo '<input type="hidden" name="docs_kb[]" value="' . esc_attr( $kb->slug ) . '">';
			}
		}
		if ( $can ) {
			echo '<button type="submit" class="button">' . esc_html__( 'Save', 'chatbot' ) . '</button>';
		}
		echo '</form>';
		if ( $on ) {
			$name = sanitize_title( get_bloginfo( 'name' ) );
			$name = '' !== $name ? $name . '-help' : 'help-center';
			echo '<div class="wpb-hc-ai__row">';
			echo self::copy_box( 'hc-ai-docs-url', __( 'Server address', 'chatbot' ), Docs_Server::url(), 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in copy_box().
			echo self::copy_box( 'hc-ai-docs-cc', __( 'Claude Code', 'chatbot' ), 'claude mcp add --transport http ' . $name . ' ' . Docs_Server::url(), 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in copy_box().
			echo '<p class="wpb-hc-ai__note">' . esc_html__( "In Claude.ai or ChatGPT, add the address as a custom connector. Share it on your help center or in your developer docs; it is also listed in your help center's llms.txt.", 'chatbot' ) . '</p>';
			echo '</div>';
		}
		self::close();
	}

	/**
	 * Styles (WPBot Core tokens with plain fallbacks) and the copy and prompt scripts.
	 *
	 * @return void
	 */
	private static function assets() {
		echo '<style>.wpb-hc-ai__section{display:grid;grid-template-columns:minmax(200px,300px) 1fr;gap:24px;padding:24px 0;border-top:1px solid var(--wpb-line,#ebebe8)}.wpb-hc-ai__intro h2{margin:0 0 6px;font-size:15px}.wpb-hc-ai__intro p{margin:0;color:var(--wpb-muted,#6d6d68)}.wpb-hc-ai__body>*:first-child{margin-top:0}.wpb-hc-ai__copy{margin:0 0 16px}.wpb-hc-ai__copy label{display:block;font-weight:600;margin:0 0 6px}.wpb-hc-ai__copy textarea{display:block;width:100%;max-width:760px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;background:var(--wpb-soft,#f6f6f4);color:var(--wpb-text,#161616);border:0;border-radius:var(--wpb-radius,10px);padding:10px 12px;margin:0 0 8px}.wpb-hc-ai__choice{display:block;margin:0 0 10px}.wpb-hc-ai input[type=url]{max-width:100%}.wpb-hc-ai__note{display:block;color:var(--wpb-muted,#6d6d68);font-size:12px}.wpb-hc-ai__choice span{display:block;margin-left:24px;color:var(--wpb-muted,#6d6d68)}.wpb-hc-ai__inline{display:inline}.wpb-hc-ai__row{margin-top:16px;padding-top:16px;border-top:1px solid var(--wpb-line,#ebebe8)}.wpb-hc-ai__list{margin:0}.wpb-hc-ai__list li{padding:10px 0;border-bottom:1px solid var(--wpb-line,#ebebe8)}.wpb-hc-ai__list li>*{margin-right:6px}.wpb-hc-ai__empty{padding:12px 14px;background:var(--wpb-soft,#f6f6f4);border-radius:var(--wpb-radius,10px);color:var(--wpb-muted,#6d6d68)}.wpb-hc-ai__ok{padding:10px 12px;background:var(--wpb-ok-bg,#e9f6ee);color:var(--wpb-ok-text,#146c35);border-radius:var(--wpb-radius,10px)}.wpb-hc-ai__warn{padding:10px 12px;background:var(--wpb-warn-bg,#fdf6e7);color:var(--wpb-warn-text,#7a4a06);border-radius:var(--wpb-radius,10px)}.wpb-hc-ai__intro,.wpb-hc-ai__body{min-width:0}.wpb-hc-ai__body code{overflow-wrap:anywhere}@media (max-width:782px){.wpb-hc-ai__section{grid-template-columns:minmax(0,1fr);gap:12px}}</style>';
		echo '<script>(function(){document.addEventListener("click",function(e){var b=e.target.closest("[data-hc-copy]");if(!b){return;}var t=document.getElementById(b.getAttribute("data-hc-copy"));if(!t){return;}var d=b.textContent,done=function(){b.textContent=' . wp_json_encode( __( 'Copied', 'chatbot' ) ) . ';setTimeout(function(){b.textContent=d;},1500);};if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t.value).then(done);}else{t.select();document.execCommand("copy");done();}});var r=document.getElementById("hc-ai-repo"),p=document.getElementById("hc-ai-prompt-text");if(r&&p){r.addEventListener("input",function(){var v=r.value.trim()||"https://github.com/OWNER/REPOSITORY";p.value=r.getAttribute("data-hc-prompt-template").split("%REPO%").join(v);});}})();</script>';
	}
}
