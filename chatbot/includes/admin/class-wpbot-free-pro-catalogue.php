<?php
/**
 * WPBot Free: what WPBot Pro adds (owner, 12 November 2026).
 *
 * One catalogue for every upgrade prompt in WPBot Free:
 * - the WordPress menu's unlock meter ("9 of 25 features"; counted from the lists below, so it stays true);
 * - the Plugins page: a gold "Upgrade to Pro" link and chips naming what Pro adds;
 * - two detailed lists for each Pro feature, Benefits and Features (with its integrations), printed by
 *   self::print_feature() wherever WPBot Free shows that feature.
 *
 * Every entry describes what WPBot Pro does today (its add-ons and Pro modules); nothing is planned or invented.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Free_Pro_Catalogue' ) ) {

	/**
	 * WPBot Pro's features, as WPBot Free shows them.
	 */
	final class QCLD_WPBot_Free_Pro_Catalogue {

		const PRO_URL = 'https://www.wpbot.pro/';

		/**
		 * The upgrade link, tagged with where the click came from.
		 *
		 * @param string $where admin-menu, plugins-row, plugins-chips, feature-<id>, sidebar.
		 * @return string
		 */
		public static function upgrade_url( $where = 'admin' ) {
			return add_query_arg(
				array(
					'utm_source'   => 'wpbot-free',
					'utm_medium'   => sanitize_key( $where ),
					'utm_campaign' => 'upgrade',
				),
				self::PRO_URL
			);
		}

		/**
		 * What WPBot Free includes (the meter's "of").
		 *
		 * @return string[]
		 */
		public static function free_features() {
			return array(
				__( 'AI chatbot with 7 AI providers', 'chatbot' ),
				__( 'Knowledge Base from your content', 'chatbot' ),
				__( 'Simple Text Responses', 'chatbot' ),
				__( 'FAQ', 'chatbot' ),
				__( 'Site search', 'chatbot' ),
				__( 'Email, call-back and feedback forms', 'chatbot' ),
				__( 'Business Context', 'chatbot' ),
				__( 'AI Writer', 'chatbot' ),
				__( 'SEO & AI Search', 'chatbot' ),
				__( 'Social', 'chatbot' ),
				__( 'Site Insight', 'chatbot' ),
				__( 'AI Marketing', 'chatbot' ),
				__( 'Automator', 'chatbot' ),
				__( 'Permissions', 'chatbot' ),
				__( 'Spending limits and error log', 'chatbot' ),
			);
		}

		/**
		 * WPBot Pro's features: name, chip, lead, benefits, features (integrations included).
		 *
		 * @return array<string,array{name:string,chip:string,lead:string,benefits:string[],features:string[]}>
		 */
		public static function pro_features() {
			static $cache = null;
			if ( null === $cache ) {
				$features = require __DIR__ . '/pro-catalogue-data.php';
				/**
				 * WPBot Pro's features as WPBot Free shows them.
				 *
				 * @param array $features Features: name, chip, lead, benefits, features, integrations, listed.
				 */
				$cache = (array) apply_filters( 'qcld_wpbot_free_pro_features', $features );
			}
			return $cache;
		}

		/**
		 * The Pro features counted in the menu meter and shown as chips (not one-teaser details).
		 *
		 * @return array
		 */
		public static function listed_features() {
			return array_filter(
				self::pro_features(),
				static function ( $f ) {
					return ! isset( $f['listed'] ) || false !== $f['listed'];
				}
			);
		}

		/**
		 * One Pro feature, or null.
		 *
		 * @param string $id Feature id.
		 * @return array|null
		 */
		public static function feature( $id ) {
			$all = self::pro_features();
			return isset( $all[ $id ] ) ? $all[ $id ] : null;
		}

		/**
		 * The meter's numbers.
		 *
		 * @return array{free:int,total:int,pro:int,percent:int}
		 */
		public static function counts() {
			$free  = count( self::free_features() );
			$pro   = count( self::listed_features() );
			$total = $free + $pro;
			return array(
				'free'    => $free,
				'total'   => $total,
				'pro'     => $pro,
				'percent' => $total > 0 ? (int) round( 100 * $free / $total ) : 0,
			);
		}

		/**
		 * The WordPress menu's "Upgrade to Pro" item: the unlock meter (owner's choice, design sample D).
		 *
		 * @return string Menu title HTML.
		 */
		public static function menu_meter() {
			$c = self::counts();
			return '<span class="qc-up-pro-link-chatbot qcld-wpbot-meter">'
				/* translators: 1: features in WPBot Free, 2: all features. */
				. '<span class="qcld-wpbot-meter__row"><span>' . esc_html__( 'Free plan', 'chatbot' ) . '</span><b>' . esc_html( sprintf( __( '%1$d/%2$d', 'chatbot' ), $c['free'], $c['total'] ) ) . '</b></span>'
				/* translators: 1: features in WPBot Free, 2: all features. */
				. '<span class="qcld-wpbot-meter__bar" role="img" aria-label="' . esc_attr( sprintf( __( 'WPBot Free has %1$d of %2$d WPBot features', 'chatbot' ), $c['free'], $c['total'] ) ) . '"><i style="width:' . (int) $c['percent'] . '%"></i></span>'
				/* translators: %d: features WPBot Pro adds. */
				. '<span class="qcld-wpbot-meter__go">' . esc_html( sprintf( __( 'Unlock %d Pro features', 'chatbot' ), $c['pro'] ) ) . ' <span aria-hidden="true">&rarr;</span></span>'
				. '</span>';
		}

		/**
		 * The meter's styles, on every admin page (the menu is everywhere).
		 *
		 * @return void
		 */
		public static function menu_css() {
			echo '<style id="qcld-wpbot-meter-css">'
				. '#adminmenu .qcld-wpbot-meter{display:grid;gap:5px;margin:4px -4px 2px;padding:8px 9px;border-radius:7px;background:#3a3f46;color:#fff;line-height:1.3;white-space:normal}'
				. '#adminmenu a:hover .qcld-wpbot-meter,#adminmenu a:focus .qcld-wpbot-meter{background:#454b53}'
				. '#adminmenu .qcld-wpbot-meter__row{display:flex;justify-content:space-between;gap:6px;font-size:11px;color:#c3c4c7}'
				. '#adminmenu .qcld-wpbot-meter__row b{color:#fff;font-weight:600}'
				. '#adminmenu .qcld-wpbot-meter__bar{display:block;height:5px;border-radius:9px;background:#23282d;overflow:hidden}'
				. '#adminmenu .qcld-wpbot-meter__bar i{display:block;height:100%;border-radius:9px;background:linear-gradient(90deg,#7f6fe0,#b29cff)}'
				. '.qcld-wpbot-pro-label{display:inline-block;margin-left:6px;padding:1px 6px;border-radius:4px;background:#5b4e96;color:#fff;font-size:10px;font-weight:700;letter-spacing:.06em;vertical-align:middle;line-height:1.6}'
				. '#adminmenu .qcld-wpbot-meter__go{font-weight:600;font-size:12px;color:#cfc6ff}'
				. '.folded #adminmenu .qcld-wpbot-meter__row,.folded #adminmenu .qcld-wpbot-meter__bar{display:none}'
				. '</style>';
		}

		/**
		 * The Plugins page: the gold link and the feature chips.
		 *
		 * @return void
		 */
		public static function plugins_page_css() {
			echo '<style id="qcld-wpbot-plugins-css">'
				. '.plugins .qcld-wpbot-upgrade-pill{display:inline-flex;align-items:center;gap:4px;padding:1px 9px;border-radius:99px;background:linear-gradient(135deg,#ffd66b,#f4a51c);color:#3b2600;font-weight:600;text-decoration:none}'
				. '.plugins .qcld-wpbot-upgrade-pill:hover,.plugins .qcld-wpbot-upgrade-pill:focus{color:#3b2600;background:linear-gradient(135deg,#ffe08f,#f6b23f)}'
				. '.qcld-wpbot-chips{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:8px 0 2px}'
				. '.qcld-wpbot-chips__label{font-size:12px;font-weight:600;color:#3a2f78}'
				. '.qcld-wpbot-chips span.qcld-wpbot-chip{font-size:11.5px;padding:1px 8px;border-radius:99px;background:#f0edff;color:#4a3f8a;border:1px solid #ddd6ff}'
				. '.qcld-wpbot-chips a{font-size:12px;font-weight:600;color:#5b4e96;text-decoration:none}'
				. '</style>';
		}

		/**
		 * The feature chips under WPBot Free's row on the Plugins page.
		 *
		 * @return void
		 */
		public static function print_chips() {
			$features = self::pro_features();
			$shown    = array( 'live_chat', 'woocommerce', 'whatsapp', 'forms', 'sessions', 'crm' );
			$c        = self::counts();
			echo '<div class="qcld-wpbot-chips"><span class="qcld-wpbot-chips__label">' . esc_html__( 'WPBot Pro adds:', 'chatbot' ) . '</span>';
			foreach ( $shown as $id ) {
				if ( isset( $features[ $id ] ) ) {
					echo '<span class="qcld-wpbot-chip">' . esc_html( $features[ $id ]['chip'] ) . '</span>';
				}
			}
			/* translators: %d: more WPBot Pro features. */
			echo '<a href="' . esc_url( self::upgrade_url( 'plugins-chips' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( sprintf( __( '+ %d more in Pro', 'chatbot' ), max( 0, $c['pro'] - count( $shown ) ) ) ) . ' <span aria-hidden="true">&rarr;</span></a></div>';
		}

		/**
		 * One Pro feature's two lists, Benefits and Features, with the upgrade link (for teasers).
		 *
		 * @param string $id      Feature id.
		 * @param array  $args    heading (bool, default true), compact (bool).
		 * @return void
		 */
		public static function print_feature( $id, array $args = array() ) {
			self::styles_once();
			echo self::render_feature( $id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_feature().
		}

		/**
		 * One Pro feature's lists as HTML (escaped): title with the PRO badge, lead, Benefits,
		 * Features with the services it works with, and the upgrade button.
		 *
		 * @param string $id   Feature id.
		 * @param array  $args heading (bool, default true), compact (bool), button (bool, default true).
		 * @return string
		 */
		public static function render_feature( $id, array $args = array() ) {
			$f = self::feature( $id );
			if ( ! $f ) {
				return '';
			}
			$args = array_merge(
				array(
					'heading' => true,
					'compact' => false,
					'button'  => true,
				),
				$args
			);
			$html = '<div class="qcld-wpbot-pro' . ( $args['compact'] ? ' is-compact' : '' ) . '" data-pro-feature="' . esc_attr( $id ) . '">';
			if ( $args['heading'] ) {
				$html .= '<p class="qcld-wpbot-pro__title"><span class="qcld-wpbot-pro__badge">' . esc_html__( 'PRO', 'chatbot' ) . '</span> ' . esc_html( $f['name'] ) . '</p>';
			}
			$html .= '<p class="qcld-wpbot-pro__lead">' . esc_html( $f['lead'] ) . '</p><div class="qcld-wpbot-pro__lists">';
			$html .= '<div><p class="qcld-wpbot-pro__h">' . esc_html__( 'Benefits', 'chatbot' ) . '</p><ul class="is-benefits">';
			foreach ( $f['benefits'] as $item ) {
				$html .= '<li>' . esc_html( $item ) . '</li>';
			}
			$html .= '</ul></div><div><p class="qcld-wpbot-pro__h">' . esc_html__( 'Features', 'chatbot' ) . '</p><ul class="is-features">';
			foreach ( $f['features'] as $item ) {
				$html .= '<li>' . esc_html( $item ) . '</li>';
			}
			$html .= '</ul>';
			if ( ! empty( $f['integrations'] ) ) {
				$html .= '<p class="qcld-wpbot-pro__with"><b>' . esc_html__( 'Works with:', 'chatbot' ) . '</b> ' . esc_html( implode( ', ', $f['integrations'] ) ) . '</p>';
			}
			$html .= '</div></div>';
			if ( $args['button'] ) {
				$html .= '<p class="qcld-wpbot-pro__cta"><a class="qcld-wpbot-pro__btn" href="' . esc_url( self::upgrade_url( 'feature-' . $id ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Upgrade to Pro', 'chatbot' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'chatbot' ) . '</span></a></p>';
			}
			return $html . '</div>';
		}

		/**
		 * The lists for script-built screens: each element with data-qcld-pro="<id>" is filled
		 * with that feature's lists (React and jQuery screens included, as they render).
		 *
		 * @param string[] $ids    Feature ids used on the page.
		 * @param string   $handle Script that should run after the data (optional dependency).
		 * @param array[]  $inject Rules for screens without placeholders: scope, containers, id (see the script).
		 * @return void
		 */
		public static function enqueue_lists( array $ids, $handle = '', array $inject = array() ) {
			$html = array();
			foreach ( $ids as $id ) {
				$html[ $id ] = self::render_feature( $id, array( 'heading' => false ) );
			}
			wp_enqueue_script( 'qcld-wpbot-pro-lists', QCLD_wpCHATBOT_PLUGIN_URL . 'js/wpbot-free-pro-lists.js', $handle ? array( $handle ) : array(), QCLD_wpCHATBOT_VERSION, true );
			wp_localize_script(
				'qcld-wpbot-pro-lists',
				'qcldWpbotProLists',
				array(
					'html'   => $html,
					'css'    => self::css(),
					'inject' => $inject,
				)
			);
		}

		/**
		 * The generic upgrade panel (sidebars): what Pro adds overall, as Benefits and Features.
		 *
		 * @param array $args heading (bool, default true), compact (bool: feature names only, for narrow sidebars).
		 * @return void
		 */
		public static function print_overview( array $args = array() ) {
			self::styles_once();
			$features = self::listed_features();
			$compact  = ! empty( $args['compact'] );
			echo '<div class="qcld-wpbot-pro is-overview' . ( $compact ? ' is-sidebar' : '' ) . '">';
			if ( ! isset( $args['heading'] ) || $args['heading'] ) {
				echo '<p class="qcld-wpbot-pro__title"><span class="qcld-wpbot-pro__badge">' . esc_html__( 'PRO', 'chatbot' ) . '</span> ' . esc_html__( 'WPBot Pro', 'chatbot' ) . '</p>';
			}
			echo '<p class="qcld-wpbot-pro__h">' . esc_html__( 'Benefits', 'chatbot' ) . '</p><ul class="is-benefits">';
			$benefits = array(
				__( 'Sell more: product search, cart and checkout in chat, and abandoned-cart recovery.', 'chatbot' ),
				__( 'Never miss a visitor: live chat from WordPress, Slack or Microsoft Teams.', 'chatbot' ),
				__( 'Be where customers are: WhatsApp, Messenger, Instagram and Telegram.', 'chatbot' ),
				__( 'Capture more leads with smart forms, straight into your CRM.', 'chatbot' ),
				__( 'Learn from every chat with AI summaries and unanswered questions.', 'chatbot' ),
				__( 'Run AI on your own server with Ollama, and let the bot look up orders, products and your own systems.', 'chatbot' ),
				__( 'Priority support from the WPBot team.', 'chatbot' ),
			);
			foreach ( $benefits as $item ) {
				echo '<li>' . esc_html( $item ) . '</li>';
			}
			echo '</ul><p class="qcld-wpbot-pro__h">' . esc_html__( 'Features', 'chatbot' ) . '</p><ul class="is-features">';
			foreach ( $features as $f ) {
				if ( $compact ) {
					echo '<li>' . esc_html( $f['name'] ) . '</li>';
				} else {
					echo '<li><b>' . esc_html( $f['name'] ) . '</b>: ' . esc_html( implode( ', ', array_slice( $f['features'], 0, 3 ) ) ) . '</li>';
				}
			}
			echo '</ul><p class="qcld-wpbot-pro__cta"><a class="qcld-wpbot-pro__btn" href="' . esc_url( self::upgrade_url( 'sidebar' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Upgrade to Pro', 'chatbot' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'chatbot' ) . '</span></a></p></div>';
		}

		/**
		 * The lists' styles, printed once per page.
		 *
		 * @return void
		 */
		private static function styles_once() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			echo '<style id="qcld-wpbot-pro-css">' . self::css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS.
		}

		/**
		 * The lists' CSS.
		 *
		 * @return string
		 */
		public static function css() {
			return '.qcld-wpbot-pro p{opacity:1}'
				. '.qcld-wpbot-pro{border:1px solid #ddd6ff;background:linear-gradient(180deg,#faf9ff,#fff);border-radius:14px;padding:18px 20px;margin:14px 0;color:#1d2327;text-align:left;box-sizing:border-box}'
				. '.qcld-wpbot-pro p.qcld-wpbot-pro__title{margin:0 0 6px;font-size:16px;font-weight:600;color:#3a2f78;display:flex;align-items:center;gap:8px}'
				. '.qcld-wpbot-pro__badge{display:inline-block;padding:1px 7px;border-radius:5px;background:#5b4e96;color:#fff;font-size:11px;letter-spacing:.06em;font-weight:700}'
				. '.qcld-wpbot-pro p.qcld-wpbot-pro__lead{margin:0 0 12px;color:#50575e;font-size:14px;line-height:1.55}'
				. '.qcld-wpbot-pro__lists{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:12px 28px}'
				. '.qcld-wpbot-pro p.qcld-wpbot-pro__h{margin:4px 0 6px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5b4e96}'
				. '.qcld-wpbot-pro ul{margin:0 0 8px;padding-left:0;list-style:none;display:grid;gap:6px;font-size:13.5px;line-height:1.5}'
				. '.qcld-wpbot-pro ul li{margin:0;padding-left:22px;position:relative;list-style:none !important;background:none}'
				. '.qcld-wpbot-pro li:before{position:absolute;left:0;top:0;font-weight:700}'
				. '.qcld-wpbot-pro ul.is-benefits li:before{content:"\\2605";color:#d99a0b}'
				. '.qcld-wpbot-pro ul.is-features li:before{content:"\\2713";color:#5b4e96}'
				. '.qcld-wpbot-pro p.qcld-wpbot-pro__with{margin:6px 0 0;font-size:13px;color:#50575e;line-height:1.5}'
				. '.qcld-wpbot-pro p.qcld-wpbot-pro__cta{margin:14px 0 0}'
				. '.qcld-wpbot-pro a.qcld-wpbot-pro__btn{display:inline-block;padding:8px 16px;border-radius:8px;background:linear-gradient(135deg,#ffd66b,#f4a51c);color:#3b2600 !important;font-weight:600;text-decoration:none}'
				. '.qcld-wpbot-pro a.qcld-wpbot-pro__btn:hover,.qcld-wpbot-pro a.qcld-wpbot-pro__btn:focus{background:linear-gradient(135deg,#ffe08f,#f6b23f)}'
				. '.qcld-wpbot-pro a.qcld-wpbot-pro__btn:focus-visible{outline:2px solid #5b4e96;outline-offset:2px}'
				. '.qcld-wpbot-pro.is-compact{padding:14px 16px}'
				. '.qcld-wpbot-pro.is-overview ul{font-size:13px}'
				. '.qcld-wpbot-pro.is-sidebar{padding:14px 12px;margin:10px 0;background:#fff}'
				. '.qcld-wpbot-pro.is-sidebar ul{font-size:12.5px;gap:5px}'
				. '.qcld-wpbot-pro.is-sidebar li{padding-left:18px}'
				. '.qcld-wpbot-pro.is-sidebar a.qcld-wpbot-pro__btn{display:block;text-align:center}';
		}
	}

	add_action( 'admin_head', array( 'QCLD_WPBot_Free_Pro_Catalogue', 'menu_css' ) );
}
