<?php
/**
 * Help Center content blocks (plan section 11, HC1-05).
 *
 * Callout, steps, tabs, accordion, screenshot with markers, "Applies to",
 * "In short" and related articles, registered from blocks/<name>/block.json.
 * Callout, In short, Applies to and Related articles are rendered here, so
 * their translated labels are never saved into the post. Code blocks in
 * articles get a copy button. Loaded only while the Help Center is on.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Blocks;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Block registration and server-side rendering.
 */
final class Blocks {

	/**
	 * Block category.
	 */
	const CATEGORY = 'wpbot-help';

	/**
	 * Blocks (folder names under blocks/).
	 *
	 * @var string[]
	 */
	const NAMES = array( 'callout', 'summary', 'steps', 'step', 'tabs', 'tab', 'accordion', 'screenshot', 'applies-to', 'related' );

	/**
	 * Callout types.
	 *
	 * @var string[]
	 */
	const CALLOUT_TYPES = array( 'note', 'tip', 'warning', 'important' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register' ), 12 );
		add_filter( 'block_categories_all', array( self::class, 'categories' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'article_assets' ) );
	}

	/**
	 * Module folder (the booted copy).
	 *
	 * @return string
	 */
	public static function dir() {
		return defined( 'QCLD_WPBOT_HELP_CENTER_DIR' ) ? QCLD_WPBOT_HELP_CENTER_DIR : dirname( __DIR__, 2 );
	}

	/**
	 * Register the scripts, styles and blocks.
	 *
	 * @return void
	 */
	public static function register() {
		$file    = self::dir() . '/help-center.php';
		$version = defined( 'QCLD_WPBOT_HELP_CENTER_VERSION' ) ? QCLD_WPBOT_HELP_CENTER_VERSION : '0';
		wp_register_script(
			'wpbot-help-blocks-editor',
			plugins_url( 'assets/blocks-editor.js', $file ),
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-data', 'wp-server-side-render' ),
			$version,
			true
		);
		wp_set_script_translations( 'wpbot-help-blocks-editor', 'chatbot' );
		wp_register_script(
			'wpbot-help-blocks-view',
			plugins_url( 'assets/blocks-view.js', $file ),
			array(),
			$version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script(
			'wpbot-help-blocks-view',
			'window.qcldWpbotHelpCenterBlocks = ' . wp_json_encode(
				array(
					'copy'   => __( 'Copy', 'chatbot' ),
					'copied' => __( 'Copied', 'chatbot' ),
					'zoom'   => __( 'Zoom the screenshot', 'chatbot' ),
				)
			) . ';',
			'before'
		);
		wp_register_style( 'wpbot-help-blocks', plugins_url( 'assets/blocks.css', $file ), array(), $version );
		wp_register_style( 'wpbot-help-blocks-editor', plugins_url( 'assets/blocks-editor.css', $file ), array(), $version );

		foreach ( self::NAMES as $name ) {
			register_block_type( self::dir() . '/blocks/' . $name, self::args( $name ) );
		}
	}

	/**
	 * Extra registration arguments (server-side rendering).
	 *
	 * @param string $name Block folder name.
	 * @return array
	 */
	public static function args( $name ) {
		$callbacks = array(
			'callout'    => 'render_callout',
			'summary'    => 'render_summary',
			'applies-to' => 'render_applies_to',
			'related'    => 'render_related',
		);
		return isset( $callbacks[ $name ] ) ? array( 'render_callback' => array( self::class, $callbacks[ $name ] ) ) : array();
	}

	/**
	 * Add the "Help Center" block category.
	 *
	 * @param array $categories Categories.
	 * @return array
	 */
	public static function categories( $categories ) {
		$categories   = (array) $categories;
		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'Help Center', 'chatbot' ),
			'icon'  => null,
		);
		return $categories;
	}

	/**
	 * Article pages: the copy button for code blocks and the block styles.
	 *
	 * @return void
	 */
	public static function article_assets() {
		if ( function_exists( 'is_singular' ) && is_singular( Post_Type::NAME ) ) {
			wp_enqueue_script( 'wpbot-help-blocks-view' );
			wp_enqueue_style( 'wpbot-help-blocks' );
		}
	}

	/**
	 * Callout label.
	 *
	 * @param string $type Callout type.
	 * @return string
	 */
	public static function callout_label( $type ) {
		$labels = array(
			'note'      => __( 'Note', 'chatbot' ),
			'tip'       => __( 'Tip', 'chatbot' ),
			'warning'   => __( 'Warning', 'chatbot' ),
			'important' => __( 'Important', 'chatbot' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $labels['note'];
	}

	/**
	 * Render a callout around its inner blocks.
	 *
	 * @param array  $attributes Attributes.
	 * @param string $content    Inner blocks HTML.
	 * @return string
	 */
	public static function render_callout( $attributes, $content ) {
		$type = isset( $attributes['type'] ) && in_array( $attributes['type'], self::CALLOUT_TYPES, true ) ? $attributes['type'] : 'note';
		return sprintf(
			'<div %1$s role="note"><p class="wpbot-help-callout__label">%2$s</p><div class="wpbot-help-callout__body">%3$s</div></div>',
			get_block_wrapper_attributes( array( 'class' => 'wpbot-help-callout is-' . $type ) ),
			esc_html( self::callout_label( $type ) ),
			$content
		);
	}

	/**
	 * Render "In short" around its inner blocks.
	 *
	 * @param array  $attributes Attributes.
	 * @param string $content    Inner blocks HTML.
	 * @return string
	 */
	public static function render_summary( $attributes, $content ) {
		unset( $attributes );
		if ( '' === trim( wp_strip_all_tags( (string) $content ) ) ) {
			return '';
		}
		return sprintf(
			'<aside %1$s><p class="wpbot-help-summary__label">%2$s</p>%3$s</aside>',
			get_block_wrapper_attributes( array( 'class' => 'wpbot-help-summary' ) ),
			esc_html__( 'In short', 'chatbot' ),
			$content
		);
	}

	/**
	 * Render "Applies to".
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public static function render_applies_to( $attributes ) {
		$text = isset( $attributes['text'] ) ? trim( (string) $attributes['text'] ) : '';
		if ( '' === $text ) {
			return '';
		}
		return sprintf(
			'<p %1$s><span class="wpbot-help-applies__badge"><span class="wpbot-help-applies__label">%2$s</span> <span class="wpbot-help-applies__text">%3$s</span></span></p>',
			get_block_wrapper_attributes( array( 'class' => 'wpbot-help-applies' ) ),
			esc_html__( 'Applies to', 'chatbot' ),
			esc_html( $text )
		);
	}

	/**
	 * Render related articles: other published articles in the same categories.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public static function render_related( $attributes ) {
		$post_id = (int) get_the_ID();
		$count   = isset( $attributes['count'] ) ? max( 1, min( 10, (int) $attributes['count'] ) ) : 5;
		$title   = isset( $attributes['title'] ) && '' !== trim( (string) $attributes['title'] ) ? (string) $attributes['title'] : __( 'Related articles', 'chatbot' );
		$ids     = self::related_ids( $post_id, $count );
		if ( array() === $ids ) {
			return defined( 'REST_REQUEST' ) && REST_REQUEST ? '<p>' . esc_html__( 'Related articles appear here when other articles share this article\'s category.', 'chatbot' ) . '</p>' : '';
		}
		$items = '';
		foreach ( $ids as $id ) {
			$items .= sprintf( '<li><a href="%1$s">%2$s</a></li>', esc_url( get_permalink( $id ) ), esc_html( get_the_title( $id ) ) );
		}
		return sprintf(
			'<nav %1$s aria-label="%2$s"><h2 class="wpbot-help-related__title">%3$s</h2><ul>%4$s</ul></nav>',
			get_block_wrapper_attributes( array( 'class' => 'wpbot-help-related' ) ),
			esc_attr( $title ),
			esc_html( $title ),
			$items
		);
	}

	/**
	 * Ids of other published articles in the same categories, by menu order then title.
	 *
	 * @param int $post_id Article id.
	 * @param int $count   Most articles.
	 * @return int[]
	 */
	public static function related_ids( $post_id, $count ) {
		if ( $post_id <= 0 ) {
			return array();
		}
		$terms = get_the_terms( $post_id, Taxonomies::CATEGORY );
		if ( ! is_array( $terms ) || array() === $terms ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $count,
				'post__not_in'   => array( (int) $post_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- One id, small result.
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Related articles need the category.
					array(
						'taxonomy' => Taxonomies::CATEGORY,
						'field'    => 'term_id',
						'terms'    => wp_list_pluck( $terms, 'term_id' ),
					),
				),
			)
		);
		return array_map( 'intval', (array) $ids );
	}
}
