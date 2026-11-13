<?php
/**
 * WPBot › Social admin screens in WPBot Core's Shell.
 *
 * Screens render on the server with real links; small actions (save an
 * app, connect, toggle auto-share, disconnect) call the REST API.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Admin;

use QuantumCloud\Social\Module;
use QuantumCloud\WPBotCore\Admin\Shell;

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
final class Admin_Controller {

	/**
	 * Page slug.
	 */
	const SLUG = 'wpbot-social';

	/**
	 * Screen hook suffix.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Add WPBot › Social.
	 *
	 * @return void
	 */
	public static function menu() {
		$host = Module::instance()->host();
		if ( ! $host ) {
			return;
		}
		self::$hook = (string) add_submenu_page(
			$host->get_menu_parent(),
			__( 'Social', 'chatbot' ),
			$host->get_menu_title(),
			'wpbot_social_view',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Styles and scripts on the Social screens only.
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}
		$host = Module::instance()->host();
		Shell::enqueue();
		foreach ( $host->get_admin_styles() as $style ) {
			if ( ! empty( $style['handle'] ) && ! empty( $style['src'] ) ) {
				wp_enqueue_style( $style['handle'], $style['src'], array(), isset( $style['ver'] ) ? $style['ver'] : false );
			}
		}
		$base = plugins_url( 'assets/', QCLD_WPBOT_SOCIAL_FILE );
		wp_enqueue_style( 'qcld-wpbot-social-admin', $base . 'admin.css', array( Shell::HANDLE ), QCLD_WPBOT_SOCIAL_VERSION );
		if ( in_array( self::view(), array( 'compose', 'write' ), true ) ) {
			wp_enqueue_media();
		}
		if ( 'manual' === self::view() ) {
			wp_enqueue_script( 'qcld-wpbot-social-qrcode', $base . 'vendor/qrcode.js', array(), '1.4.4', true );
		}
		wp_enqueue_script( 'qcld-wpbot-social-admin', $base . 'admin.js', array(), QCLD_WPBOT_SOCIAL_VERSION, true );
		wp_localize_script(
			'qcld-wpbot-social-admin',
			'qcldWpbotSocial',
			array(
				'rest'  => esc_url_raw( rest_url( 'wpbot-social/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'admin' => esc_url_raw( admin_url( 'admin.php?page=' . self::SLUG ) ),
				'i18n'  => array(
					'saved'        => __( 'Saved.', 'chatbot' ),
					'gDisconnect'  => __( 'Disconnect Google? AI Writer and SEO & AI Search use the same connection and stop reading Google data until you connect again.', 'chatbot' ),
					'writeOne'     => __( 'Write', 'chatbot' ),
					/* translators: %d: number of versions. */
					'writeMany'    => __( 'Write %d versions', 'chatbot' ),
					'writing'      => __( 'Writing…', 'chatbot' ),
					/* translators: 1: current network number, 2: number of networks. */
					'writingOf'    => __( 'Writing %1$d of %2$d…', 'chatbot' ),
					'noAi'         => __( 'Blank: no AI call, no cost.', 'chatbot' ),
					'noProvider'   => __( 'No AI provider is turned on for Social.', 'chatbot' ),
					/* translators: 1: number of AI calls, 2: estimated cost. */
					'estimate'     => __( 'About %1$d AI calls · est. %2$s', 'chatbot' ),
					/* translators: %s: amount left this month. */
					'budgetLeft'   => __( '%s left this month', 'chatbot' ),
					'overBudget'   => __( 'This would go over this month\'s AI budget for Social. Raise it in Settings › Writing.', 'chatbot' ),
					'postYourself' => __( 'Post yourself', 'chatbot' ),
					'technique'    => __( 'Technique:', 'chatbot' ),
					'rewrite'      => __( 'Rewrite', 'chatbot' ),
					'use'          => __( 'Use', 'chatbot' ),
					'oneVersion'   => __( 'Your version', 'chatbot' ),
					/* translators: %d: number of versions. */
					'versions'     => __( '%d versions from one idea', 'chatbot' ),
					/* translators: %s: cost of the AI calls. */
					'cost'         => __( 'Written. Cost: %s', 'chatbot' ),
					'pickOne'      => __( 'Tick at least one version.', 'chatbot' ),
					'emptyText'    => __( 'The ticked versions have no text yet. Write or rewrite them first.', 'chatbot' ),
					'writeFirst'   => __( 'Write the versions first, then tick the ones you want.', 'chatbot' ),
					/* translators: %s: network names, comma separated. */
					'noAccount'    => __( 'No account is connected for %s. Connect one to schedule these versions; your versions stay here.', 'chatbot' ),
					'goAccounts'   => __( 'Open Accounts', 'chatbot' ),
					'rescheduleTo' => __( 'New time (site time, YYYY-MM-DD HH:MM):', 'chatbot' ),
					'addImage'     => __( 'Add this image', 'chatbot' ),
					'noImages'     => __( 'No images found.', 'chatbot' ),
					/* translators: %d: number of alternative versions. */
					'variants'     => __( '%d other versions', 'chatbot' ),
					'useThis'      => __( 'Use this one', 'chatbot' ),
					'copied'       => __( 'Copied', 'chatbot' ),
					'error'        => __( 'Something went wrong. Try again.', 'chatbot' ),
					'confirmAll'   => __( 'Remove every account and token from this site? Posts already published stay on the networks. The ChatBot\'s Messenger, Instagram and Telegram channels are not affected.', 'chatbot' ),
					'tgForget'     => __( 'Channels that post through this bot will need connecting again. Remove the bot token?', 'chatbot' ),
					'confirmOne'   => __( 'Disconnect this account from WPBot Social? Posts already published stay on the network.', 'chatbot' ),
					'confirmUsed'  => __( 'Your chatbot answers messages on this Page. Disconnecting here only stops WPBot Social from posting; the chatbot keeps working, because Social never removes the Meta permission or the Page\'s webhook subscription. Disconnect from Social?', 'chatbot' ),
					'checking'     => __( 'Checking…', 'chatbot' ),
					/* translators: %d: number of selected accounts. */
					'selected'     => __( '%d accounts selected', 'chatbot' ),
					/* translators: %d: number of posts. */
					'schedule'     => __( 'Schedule %d posts', 'chatbot' ),
					/* translators: %d: number of posts. */
					'postNow'      => __( 'Post %d now', 'chatbot' ),
					'allGood'      => __( 'All checks passed', 'chatbot' ),
					'pickImage'    => __( 'Choose images', 'chatbot' ),
					'useImage'     => __( 'Use these images', 'chatbot' ),
					'pickVideo'    => __( 'Choose a video', 'chatbot' ),
					'useVideo'     => __( 'Use this video', 'chatbot' ),
					'scheduleOne'  => __( 'Schedule 1 post', 'chatbot' ),
					'postNowOne'   => __( 'Post now', 'chatbot' ),
					'confirmDel'   => __( 'Delete this post from WPBot? Posts already published stay on the networks.', 'chatbot' ),
				),
			)
		);
	}

	/**
	 * Current view.
	 *
	 * @return string
	 */
	public static function view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'accounts';
		return in_array( $view, array( 'write', 'compose', 'manual', 'calendar', 'posts', 'accounts', 'connect', 'pages', 'settings' ), true ) || isset( self::extra_views()[ $view ] ) ? $view : 'accounts';
	}

	/**
	 * Views added by the Master module or later milestones.
	 *
	 * Each: view id => [ 'render' => callable, 'cap' => capability, 'menu' => menu item id ].
	 *
	 * @return array<string,array{render:callable, cap:string, menu:string}>
	 */
	public static function extra_views() {
		$views = (array) apply_filters( 'qcld_wpbot_social/admin_views', array() );
		$out   = array();
		foreach ( $views as $id => $view ) {
			if ( is_array( $view ) && isset( $view['render'] ) && is_callable( $view['render'] ) ) {
				$out[ sanitize_key( $id ) ] = array(
					'render' => $view['render'],
					'cap'    => isset( $view['cap'] ) ? (string) $view['cap'] : 'wpbot_social_settings',
					'menu'   => isset( $view['menu'] ) ? sanitize_key( $view['menu'] ) : 'accounts',
				);
			}
		}
		return $out;
	}

	/**
	 * URL of a view.
	 *
	 * @param string $view View.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $view, array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::SLUG,
					'view' => $view,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Print the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'wpbot_social_view' ) ) {
			wp_die( esc_html__( 'You are not allowed to open Social.', 'chatbot' ), '', array( 'response' => 403 ) );
		}
		$host    = Module::instance()->host();
		$view    = self::view();
		$extra   = self::extra_views();
		$current = in_array( $view, array( 'connect', 'pages' ), true ) ? 'accounts' : ( isset( $extra[ $view ] ) ? $extra[ $view ]['menu'] : ( 'write' === $view ? 'compose' : ( 'manual' === $view ? 'posts' : $view ) ) );
		Shell::render(
			array(
				'module'  => 'social',
				'label'   => __( 'Social menu', 'chatbot' ),
				'items'   => self::items(),
				'current' => $current,
				'brand'   => $host->get_brand(),
				'header'  => static function () use ( $host ) {
					$host->render_admin_header();
					self::render_header();
				},
				'content' => static function () use ( $view, $extra ) {
					if ( isset( $extra[ $view ] ) ) {
						if ( ! current_user_can( $extra[ $view ]['cap'] ) ) {
							echo '<div class="qcsoc-card"><p>' . esc_html__( 'You are not allowed to open this screen.', 'chatbot' ) . '</p></div>';
							return;
						}
						call_user_func( $extra[ $view ]['render'] );
						return;
					}
					if ( in_array( $view, array( 'compose', 'write' ), true ) && ! current_user_can( 'wpbot_social_use' ) ) {
						echo '<div class="qcsoc-card"><p>' . esc_html__( 'You are not allowed to create posts.', 'chatbot' ) . '</p></div>';
						return;
					}
					if ( ( 'settings' === $view || 'connect' === $view || 'pages' === $view ) && ! current_user_can( 'wpbot_social_settings' ) ) {
						echo '<div class="qcsoc-card"><p>' . esc_html__( 'Only people who manage Social settings can open this screen.', 'chatbot' ) . '</p></div>';
						return;
					}
					include __DIR__ . '/views/' . $view . '.php';
				},
			)
		);
	}

	/**
	 * Menu items. Screens arrive milestone by milestone; items for screens
	 * that are not built yet are not shown.
	 *
	 * @return array[]
	 */
	public static function items() {
		$items = array(
			array(
				'id'     => 'compose',
				'label'  => __( 'Create post', 'chatbot' ),
				'url'    => self::url( 'write' ),
				'icon'   => 'dashicons-edit',
				'hidden' => ! current_user_can( 'wpbot_social_use' ),
			),
			array(
				'id'    => 'calendar',
				'label' => __( 'Calendar', 'chatbot' ),
				'url'   => self::url( 'calendar' ),
				'icon'  => 'dashicons-calendar-alt',
			),
			array(
				'id'    => 'posts',
				'label' => __( 'Posts', 'chatbot' ),
				'url'   => self::url( 'posts' ),
				'icon'  => 'dashicons-list-view',
				'badge' => self::failed_count() + \QuantumCloud\Social\Posts\Target_Store::manual_count(),
			),
			array(
				'id'    => 'accounts',
				'label' => __( 'Accounts', 'chatbot' ),
				'url'   => self::url( 'accounts' ),
				'icon'  => 'dashicons-admin-plugins',
			),
			array(
				'id'     => 'settings',
				'label'  => __( 'Settings', 'chatbot' ),
				'url'    => self::url( 'settings' ),
				'icon'   => 'dashicons-admin-generic',
				'hidden' => ! current_user_can( 'wpbot_social_settings' ),
			),
		);
		/**
		 * Filters the Social menu items (later milestones and the Master module add theirs).
		 *
		 * @param array[] $items Items.
		 */
		return (array) apply_filters( 'qcld_wpbot_social/menu_items', $items );
	}

	/**
	 * Posts that failed or need checking (menu badge).
	 *
	 * @return int
	 */
	public static function failed_count() {
		global $wpdb;
		$table = \QuantumCloud\Social\Storage\Schema::table( 'post_targets' );
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$table} WHERE state IN ('failed','check')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table, no input.
	}

	/**
	 * The Social title card.
	 *
	 * @return void
	 */
	private static function render_header() {
		?>
		<div class="qcsoc-hero">
			<span class="qcsoc-hero__icon dashicons dashicons-share" aria-hidden="true"></span>
			<div class="qcsoc-hero__text">
				<h1><?php esc_html_e( 'Social', 'chatbot' ); ?></h1>
				<p><?php echo esc_html( \QuantumCloud\Social\Module::MODE_MASTER === \QuantumCloud\Social\Module::instance()->mode() ? __( 'Plan, publish and answer on your social accounts from WordPress.', 'chatbot' ) : __( 'Plan and publish to your social accounts from WordPress.', 'chatbot' ) ); ?></p>
			</div>
			<a class="button qcsoc-hero__help" href="https://wpbot.pro/docs/" target="_blank" rel="noopener"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span> <?php esc_html_e( 'Help', 'chatbot' ); ?></a>
		</div>
		<?php
		$missed = (int) get_option( 'qcld_wpbot_social_missed_notice', 0 );
		if ( $missed > 0 && current_user_can( 'wpbot_social_publish' ) ) {
			// One notice for posts that came due while Social could not run (QA #58).
			printf(
				'<div class="notice notice-warning" data-qcsoc-missed-notice><p>%s <a href="%s">%s</a> <button type="button" class="button-link" data-qcsoc-missed-dismiss>%s</button></p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of posts. */
						_n( '%d social post came due while WPBot Social could not run, so it was not sent late.', '%d social posts came due while WPBot Social could not run, so they were not sent late.', $missed, 'chatbot' ),
						$missed
					)
				),
				esc_url( self::url( 'posts', array( 'status' => 'missed' ) ) ),
				esc_html__( 'Choose what to do with them', 'chatbot' ),
				esc_html__( 'Dismiss', 'chatbot' )
			);
		}
	}

	/**
	 * Network label.
	 *
	 * @param string $network Network id.
	 * @return string
	 */
	public static function network_label( $network ) {
		$labels = array(
			'facebook'        => __( 'Facebook', 'chatbot' ),
			'instagram'       => __( 'Instagram', 'chatbot' ),
			'threads'         => __( 'Threads', 'chatbot' ),
			'google_business' => __( 'Google Business Profile', 'chatbot' ),
			'telegram'        => __( 'Telegram', 'chatbot' ),
		);
		/**
		 * Network names (the Master module adds its networks).
		 *
		 * @param array<string,string> $labels Network id => name.
		 */
		$labels = (array) apply_filters( 'qcld_wpbot_social/network_labels', $labels );
		return isset( $labels[ $network ] ) ? (string) $labels[ $network ] : ucfirst( $network );
	}

	/**
	 * Initials for an avatar.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function initials( $name ) {
		$words = preg_split( '/[^\p{L}\p{N}]+/u', trim( (string) $name ), -1, PREG_SPLIT_NO_EMPTY );
		$out   = '';
		foreach ( array_slice( (array) $words, 0, 2 ) as $word ) {
			$out .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
		}
		return '' === $out ? '?' : $out;
	}
}
