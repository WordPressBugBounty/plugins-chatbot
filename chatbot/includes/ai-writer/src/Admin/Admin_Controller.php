<?php
/**
 * AI Writer admin screen: menu, assets (only on this screen), app data.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Admin;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\WPBotCore\Admin\Shell;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\WPBotCore\AI\Key_Health;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the AI Writer screen.
 */
final class Admin_Controller {

	const SLUG   = 'wpbot-ai-writer';
	const HANDLE = 'qcld-wpbot-writer-admin';

	/**
	 * Screen hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Hook into the admin.
	 *
	 * @return void
	 */
	public function hook() {
		add_action( 'admin_menu', array( $this, 'menu' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 20, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_action' ), 20, 2 );
	}

	/**
	 * "Improve with AI" under each post, page or product the user can edit.
	 *
	 * @param string[] $actions Row actions.
	 * @param \WP_Post $post    Post.
	 * @return string[]
	 */
	public function row_action( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || 'trash' === $post->post_status || ! current_user_can( Module::cap( 'use' ) ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->public || get_post_meta( $post->ID, \QuantumCloud\AiWriter\Improve\Updates::META_OF, true ) ) {
			return $actions;
		}
		$actions['qcwr_improve'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( \QuantumCloud\AiWriter\Module::page_url( array( 'improve' => (int) $post->ID ) ) ),
			esc_html__( 'Improve with AI', 'chatbot' )
		);
		return $actions;
	}

	/**
	 * Add the AI Writer submenu where the host wants it.
	 *
	 * @return void
	 */
	public function menu() {
		global $submenu;
		$host = Module::instance()->host();
		if ( ! $host ) {
			return;
		}
		$parent   = $host->get_menu_parent();
		$position = null;
		$before   = array_filter( (array) $host->get_menu_before() );
		if ( array() !== $before && isset( $submenu[ $parent ] ) ) {
			foreach ( array_values( (array) $submenu[ $parent ] ) as $index => $item ) {
				if ( isset( $item[2] ) && in_array( $item[2], $before, true ) ) {
					$position = $index;
					break;
				}
			}
		}

		$this->hook_suffix = (string) add_submenu_page(
			$parent,
			__( 'AI Writer', 'chatbot' ),
			$host->get_menu_title(),
			Module::cap( 'view' ),
			Module::page_slug(),
			array( $this, 'render' ),
			$position
		);
	}

	/**
	 * Load the app on the AI Writer screen only.
	 *
	 * @param string $hook_suffix Current screen.
	 * @return void
	 */
	public function assets( $hook_suffix ) {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}
		$host = Module::instance()->host();
		foreach ( $host ? (array) $host->get_admin_styles() : array() as $style ) {
			if ( is_array( $style ) && ! empty( $style['handle'] ) ) {
				wp_enqueue_style( (string) $style['handle'], isset( $style['src'] ) ? (string) $style['src'] : false, array(), isset( $style['ver'] ) ? (string) $style['ver'] : false );
			} elseif ( is_string( $style ) && '' !== $style ) {
				wp_enqueue_style( $style );
			}
		}

		$asset_file = QCLD_WPBOT_WRITER_DIR . '/assets/build/admin.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => QCLD_WPBOT_WRITER_VERSION,
		);

		if ( class_exists( Shell::class ) ) {
			Shell::enqueue();
		}
		// CR-10: the finished article is edited in the page (WordPress's own editor),
		// turned back into blocks, and its images can come from the Media Library.
		if ( current_user_can( Module::cap( 'use' ) ) ) {
			wp_enqueue_editor();
			wp_enqueue_media();
			$asset['dependencies'] = array_merge( (array) $asset['dependencies'], array( 'wp-blocks', 'wp-block-library' ) );
		}
		wp_enqueue_script( self::HANDLE, Module::url( 'assets/build/admin.js' ), $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'chatbot' );
		wp_enqueue_style( 'wp-components' );
		Ai_Readiness::enqueue_style();
		wp_enqueue_style( self::HANDLE, Module::url( 'assets/build/style-admin.css' ), array( 'wp-components' ), $asset['version'] );
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		wp_add_inline_style( self::HANDLE, $this->brand_css() );

		wp_add_inline_script(
			self::HANDLE,
			'window.qcldWpbotWriter = ' . wp_json_encode( $this->app_data() ) . ';',
			'before'
		);
	}

	/**
	 * Print the screen shell; the app renders into #qcwr-app.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Module::cap( 'view' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to use AI Writer.', 'chatbot' ) );
		}
		$host  = Module::instance()->host();
		$brand = $host ? (array) $host->get_brand() : array();
		$app   = static function () {
			echo '<div id="qcwr-app" class="qcwr-app"><p class="qcwr-loading">' . esc_html__( 'Loading AI Writer…', 'chatbot' ) . '</p></div>';
			echo '<noscript><p>' . esc_html__( 'AI Writer needs JavaScript.', 'chatbot' ) . '</p></noscript>';
		};
		echo '<div class="qcwr-wrap">';
		if ( class_exists( Shell::class ) ) {
			Shell::render(
				array(
					'module'  => Module::ID,
					'label'   => __( 'AI Writer menu', 'chatbot' ),
					'items'   => self::menu_items(),
					'current' => self::current_item(),
					'brand'   => $brand,
					'header'  => $host ? array( $host, 'render_admin_header' ) : null,
					'content' => $app,
				)
			);
		} else {
			if ( $host ) {
				$host->render_admin_header();
			}
			$app();
		}
		echo '</div>';
	}

	/**
	 * URL of an AI Writer menu item.
	 *
	 * @param string $item Item id ('' for Overview).
	 * @return string
	 */
	public static function item_url( $item ) {
		return Module::page_url( '' !== $item && 'overview' !== $item ? array( 'tab' => rawurlencode( $item ) ) : array() );
	}

	/**
	 * The left menu (brief 2.4). Each item: id, label, icon (dashicons
	 * class), url, badge, pro, hidden, ai (the screen shows the AI readiness
	 * notice). Extensions add or change items with
	 * qcld_wpbot_writer/menu_items; the matching screen is registered with
	 * the qcld_wpbot_writer.admin.screens JS filter under the same id.
	 *
	 * @return array[]
	 */
	public static function menu_items() {
		$tasks = \QuantumCloud\AiWriter\Improve\Decay_Alerts::open_tasks();
		$items = array(
			array(
				'id'    => 'overview',
				'ai'    => true,
				'label' => __( 'Overview', 'chatbot' ),
				'icon'  => 'dashicons-dashboard',
			),
			array(
				'id'    => 'write',
				'ai'    => true,
				'label' => __( 'Write', 'chatbot' ),
				'icon'  => 'dashicons-edit',
			),
			array(
				'id'    => 'quick',
				'ai'    => true,
				'label' => __( 'Quick Write', 'chatbot' ),
				'icon'  => 'dashicons-format-quote',
			),
			array(
				'id'    => 'improve',
				'ai'    => true,
				'label' => __( 'Improve Content', 'chatbot' ),
				'icon'  => 'dashicons-update',
				// Open decay alerts: pages losing visits or clicks.
				'badge' => count( $tasks ),
			),
			array(
				'id'    => 'topics',
				'ai'    => true,
				'label' => __( 'Topic Finder', 'chatbot' ),
				'icon'  => 'dashicons-lightbulb',
			),
			array(
				'id'    => 'calendar',
				'ai'    => true,
				'label' => __( 'Content Calendar', 'chatbot' ),
				'icon'  => 'dashicons-calendar-alt',
			),
			array(
				'id'    => 'library',
				'label' => __( 'Library', 'chatbot' ),
				'icon'  => 'dashicons-portfolio',
			),
			array(
				'id'     => 'woo',
				'ai'     => true,
				'label'  => __( 'WooCommerce', 'chatbot' ),
				'icon'   => 'dashicons-cart',
				'hidden' => ! \QuantumCloud\AiWriter\Woo\Woo::active(),
			),
			array(
				// CR-15: Brand Voice and Business Profile (Persuasion Kit, Customer words) on one page.
				'id'    => 'voice',
				'ai'    => true,
				'label' => __( 'Brand & Business', 'chatbot' ),
				'icon'  => 'dashicons-megaphone',
			),
			array(
				'id'    => 'templates',
				'ai'    => true,
				'label' => __( 'Templates', 'chatbot' ),
				'icon'  => 'dashicons-layout',
			),
			array(
				'id'    => 'usage',
				'label' => __( 'Usage & Costs', 'chatbot' ),
				'icon'  => 'dashicons-chart-bar',
			),
			array(
				'id'     => 'settings',
				'label'  => __( 'Settings & AI Provider', 'chatbot' ),
				'icon'   => 'dashicons-admin-generic',
				'hidden' => ! current_user_can( Module::cap( 'settings' ) ),
			),
		);

		// Inactive Pro pages the host offers (Free only); the owner can hide them.
		foreach ( Pro_Previews::menu_items() as $preview ) {
			$items[] = array(
				'id'    => 'preview-' . $preview['id'],
				'label' => $preview['label'],
				'icon'  => 'dashicons-lock',
				'pro'   => true,
			);
		}

		/**
		 * AI Writer's left menu items.
		 *
		 * @param array[] $items id, label, icon, url, badge, pro, hidden.
		 */
		$items = (array) apply_filters( 'qcld_wpbot_writer/menu_items', $items );
		foreach ( $items as $index => $item ) {
			if ( is_array( $item ) && ! empty( $item['id'] ) && empty( $item['url'] ) ) {
				$items[ $index ]['url'] = self::item_url( sanitize_key( $item['id'] ) );
			}
		}
		return $items;
	}

	/**
	 * The Brand & Business tab asked for in the URL (CR-15): `section=voice`,
	 * `business`, `kit` or `words`; the old `tab=profile` opens the Kit.
	 *
	 * @return string '' for the default.
	 */
	public static function current_section() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'profile' === $tab && '' === $section ) {
			return 'kit';
		}
		return in_array( $section, array( 'voice', 'business', 'kit', 'words' ), true ) ? $section : '';
	}

	/**
	 * The item to show: ?tab=, else what a link asked for (a brief, a
	 * planned day, an Improve post, a product review), else Overview.
	 *
	 * @return string
	 */
	public static function current_item() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$ids = array();
		foreach ( self::menu_items() as $item ) {
			if ( is_array( $item ) && ! empty( $item['id'] ) && empty( $item['hidden'] ) ) {
				$ids[] = sanitize_key( $item['id'] );
			}
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// CR-15: the old Business Profile item opens its tab on Brand & Business.
		if ( 'profile' === $tab && in_array( 'voice', $ids, true ) ) {
			return 'voice';
		}
		if ( in_array( $tab, $ids, true ) ) {
			return $tab;
		}
		if ( isset( $_GET['brief'] ) || isset( $_GET['date'] ) ) {
			return 'write';
		}
		if ( isset( $_GET['improve'] ) ) {
			return 'improve';
		}
		if ( isset( $_GET['products_job'] ) ) {
			return in_array( 'woo', $ids, true ) ? 'woo' : 'overview';
		}
		// phpcs:enable
		return 'overview';
	}

	/**
	 * Data the app needs on load. Never contains keys.
	 *
	 * @return array
	 */
	private function app_data() {
		$host  = Module::instance()->host();
		$keys  = current_user_can( 'manage_options' ) && current_user_can( Module::cap( 'settings' ) );
		$brief = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only token lookup; the brief itself is validated on use.
		if ( isset( $_GET['brief'] ) ) {
			$brief = sanitize_key( wp_unslash( $_GET['brief'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: opens the Improve screen for a post; rights are checked by every route.
		$improve = isset( $_GET['improve'] ) ? absint( $_GET['improve'] ) : 0;

		return array(
			'writeDate'     => isset( $_GET['date'] ) && \QuantumCloud\AiWriter\Calendar\Calendar::valid_date( sanitize_text_field( wp_unslash( $_GET['date'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: pre-fills the day of a new draft.
			'calendar'      => array(
				'startOfWeek' => (int) get_option( 'start_of_week', 1 ),
				'postTypes'   => \QuantumCloud\AiWriter\Calendar\Calendar::post_types(),
				'today'       => wp_date( 'Y-m-d' ),
				'timezone'    => wp_timezone_string(),
			),
			'contentTypes'  => \QuantumCloud\AiWriter\Writing\Content_Types::public_view(),
			// CR-16: article formats (labels, sources, downloads) for the brief.
			'formats'       => \QuantumCloud\AiWriter\Formats\Formats::public_view(),
			// CR-16 F4: whether a forms or marketing module can ask for an email before downloads.
			'downloadGate'  => has_filter( 'qcld_wpbot_writer/download_gate' ),
			// Round 3: reader stages, frameworks, angles, techniques (labels only) and section jobs.
			'persuasion'    => \QuantumCloud\AiWriter\Persuasion\Choices::app_data(),
			'regenerate'    => \QuantumCloud\AiWriter\Regenerate\Regenerate::app_data(),
			'productsJob'   => isset( $_GET['products_job'] ) ? absint( $_GET['products_job'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: opens a review; the job route checks ownership.
			'improvePost'   => $improve && current_user_can( 'edit_post', $improve ) ? $improve : 0,
			'improveTitle'  => $improve && current_user_can( 'edit_post', $improve ) ? get_the_title( $improve ) : '',
			'version'       => QCLD_WPBOT_WRITER_VERSION,
			'module'        => Module::ID,
			'restCore'      => esc_url_raw( rest_url( 'wpbot-core/v1/' ) ),
			'restWriter'    => esc_url_raw( rest_url( 'wpbot-writer/v1/' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'can'           => array(
				'view'     => current_user_can( Module::cap( 'view' ) ),
				'use'      => current_user_can( Module::cap( 'use' ) ),
				'publish'  => current_user_can( Module::cap( 'publish' ) ),
				'settings' => current_user_can( Module::cap( 'settings' ) ),
				'keys'     => $keys,
				'upload'   => current_user_can( 'upload_files' ),
			),
			// Image sources for the image picker on the finished article (CR-10).
			'images'        => array(
				'stock'    => \QuantumCloud\AiWriter\Images\Image_Search::stock_sources(),
				'unsplash' => \QuantumCloud\AiWriter\Images\Unsplash::enabled(),
				'ai'       => (bool) \QuantumCloud\AiWriter\Images\Ai_Image::provider(),
			),
			'settings'      => Settings::all(),
			'health'        => Key_Health::notices( Module::ID ),
			'readiness'     => Ai_Readiness::state(),
			'features'      => Module::instance()->features()->ids(),
			'proPreviews'   => Pro_Previews::items(),
			'proOffered'    => array() !== Pro_Previews::offered(),
			'aiSettings'    => $host && method_exists( $host, 'get_ai_settings_url' ) ? esc_url_raw( $host->get_ai_settings_url() ) : '',
			'brief'         => $brief,
			'current'       => self::current_item(),
			// CR-15: which Brand & Business tab to open, and whether Business Profile is there.
			'section'       => self::current_section(),
			'business'      => \QuantumCloud\AiWriter\Persuasion\Persuasion::available(),
			'import'        => \QuantumCloud\AiWriter\Business\Importer::available(),
			'importMax'     => \QuantumCloud\AiWriter\Business\Importer::max_pages(),
			'importDefault' => \QuantumCloud\AiWriter\Business\Importer::default_max_pages(),
			'menu'          => array_values(
				array_map(
					static function ( $item ) {
						return array(
							'id'    => sanitize_key( $item['id'] ),
							'label' => (string) $item['label'],
							'pro'   => ! empty( $item['pro'] ),
							'ai'    => ! empty( $item['ai'] ),
						);
					},
					array_filter(
						self::menu_items(),
						static function ( $item ) {
							return is_array( $item ) && ! empty( $item['id'] ) && empty( $item['hidden'] );
						}
					)
				)
			),
			'adminUrl'      => esc_url_raw( admin_url() ),
			// R8: this screen's URL (the host's slug) and the product names from the host.
			'pageUrl'       => esc_url_raw( Module::page_url() ),
			'product'       => Module::product(),
			'proProduct'    => Module::pro_product(),
			'hasChatbot'    => Module::has_chatbot(),
			/**
			 * Shared connections the registered features use (Connections
			 * settings show these). Default: Google Search Console.
			 *
			 * @param string[] $connections Connection ids (google, youtube).
			 */
			'connections'   => array_values( array_map( 'sanitize_key', (array) apply_filters( 'qcld_wpbot_writer/connections', array( 'google' ) ) ) ),
		);
	}

	/**
	 * Brand values as CSS variables on the AI Writer screen.
	 *
	 * @return string
	 */
	private function brand_css() {
		$host  = Module::instance()->host();
		$brand = $host ? (array) $host->get_brand() : array();
		$vars  = '';
		foreach ( $brand as $key => $value ) {
			$vars .= '--qcwr-' . sanitize_key( str_replace( '_', '-', $key ) ) . ':' . preg_replace( '/[^#a-zA-Z0-9.%() ,-]/', '', (string) $value ) . ';';
		}
		return '.qcwr-wrap{' . $vars . '}';
	}
}
