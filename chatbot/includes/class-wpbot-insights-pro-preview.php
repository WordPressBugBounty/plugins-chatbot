<?php
/**
 * Site Insight in WPBot Free: shows the Site Insight Pro features as
 * inactive previews, each marked "Pro" with a link to WPBot Pro.
 *
 * Nothing here runs a Pro feature. The previews are labels, sample
 * pictures and disabled controls drawn by one small script; the features
 * themselves exist only in WPBot Pro. The previews are left out when the
 * Pro module is active.
 *
 * Loaded from class-wpbot-insights.php.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pro previews for the Site Insight screens.
 */
final class QCLD_WPBot_Insights_Pro_Preview {

	/**
	 * Where "Get WPBot Pro" links go.
	 */
	const UPGRADE_URL = 'https://www.wpbot.pro/';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_filter( 'qcin/admin_screens', array( __CLASS__, 'screens' ), 20 );
		add_filter( 'qcin/admin_scripts', array( __CLASS__, 'scripts' ) );
		add_filter( 'qcin/admin_styles', array( __CLASS__, 'styles' ) );
	}

	/**
	 * Whether to show the previews: only under WPBot Free's own adapter and
	 * only when no Site Insight Pro feature is loaded.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$show = false;
		if ( class_exists( '\QuantumCloud\Insights\Module' ) && class_exists( 'QCLD_WPBot_Insights_Adapter' ) ) {
			$module = \QuantumCloud\Insights\Module::instance();
			$show   = $module->host() instanceof QCLD_WPBot_Insights_Adapter;
			if ( $show ) {
				foreach ( $module->registry()->ids() as $id ) {
					if ( 0 === strpos( (string) $id, 'pro.' ) ) {
						$show = false;
						break;
					}
				}
			}
		}

		/**
		 * Whether Site Insight shows the WPBot Pro previews.
		 *
		 * @param bool $show Show them.
		 */
		return (bool) apply_filters( 'wpbot_insights_show_pro_preview', $show );
	}

	/**
	 * Menu entries for the Pro screens, after Heatmaps.
	 *
	 * @param array<int,array<string,string>> $screens Screens.
	 * @return array<int,array<string,string>>
	 */
	public static function screens( $screens ) {
		if ( ! is_array( $screens ) || ! self::enabled() ) {
			return $screens;
		}
		$pro = array(
			array(
				'id'    => 'pro-issues',
				'label' => __( 'Issues', 'chatbot' ),
			),
			array(
				'id'    => 'pro-segments',
				'label' => __( 'Segments', 'chatbot' ),
			),
			array(
				'id'    => 'pro-funnels',
				'label' => __( 'Funnels', 'chatbot' ),
			),
			array(
				'id'    => 'pro-forms',
				'label' => __( 'Forms', 'chatbot' ),
			),
		);
		$out   = array();
		$added = false;
		foreach ( $screens as $screen ) {
			if ( ! $added && is_array( $screen ) && isset( $screen['id'] ) && 'settings' === $screen['id'] ) {
				$out   = array_merge( $out, $pro );
				$added = true;
			}
			$out[] = $screen;
		}
		return $added ? $out : array_merge( $out, $pro );
	}

	/**
	 * The preview script.
	 *
	 * @param array<int,array<string,mixed>> $scripts Scripts.
	 * @return array<int,array<string,mixed>>
	 */
	public static function scripts( $scripts ) {
		if ( ! self::enabled() ) {
			return $scripts;
		}
		$scripts   = (array) $scripts;
		$scripts[] = array(
			'handle'   => 'wpbot-insights-pro-preview',
			'src'      => plugins_url( 'insights-pro-preview/pro-preview.js', __FILE__ ),
			'deps'     => array( 'wp-element', 'wp-i18n', 'wp-hooks' ),
			'ver'      => self::version( 'pro-preview.js' ),
			'data_var' => 'wpbotInsightsProPreview',
			'data'     => array(
				'upgradeUrl' => self::UPGRADE_URL,
				'woo'        => class_exists( 'WooCommerce' ),
			),
			'domain'   => 'chatbot',
		);
		return $scripts;
	}

	/**
	 * The preview styles.
	 *
	 * @param array<int,array<string,mixed>> $styles Styles.
	 * @return array<int,array<string,mixed>>
	 */
	public static function styles( $styles ) {
		if ( ! self::enabled() ) {
			return $styles;
		}
		$styles   = (array) $styles;
		$styles[] = array(
			'handle' => 'wpbot-insights-pro-preview',
			'src'    => plugins_url( 'insights-pro-preview/pro-preview.css', __FILE__ ),
			'ver'    => self::version( 'pro-preview.css' ),
		);
		return $styles;
	}

	/**
	 * Asset version from the file time, so updates are not cached.
	 *
	 * @param string $file File name.
	 * @return string
	 */
	private static function version( $file ) {
		$path = __DIR__ . '/insights-pro-preview/' . $file;
		return file_exists( $path ) ? (string) filemtime( $path ) : '1';
	}
}

QCLD_WPBot_Insights_Pro_Preview::init();
