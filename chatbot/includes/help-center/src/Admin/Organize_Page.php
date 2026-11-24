<?php
/**
 * Help Center → Organize: the docs tree (knowledge base → categories →
 * articles). Drag to reorder, or use the Move up / Move down buttons (keyboard).
 * Order is saved through the REST route in Order.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Docs tree screen.
 */
final class Organize_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'wpbot-help-center-organize';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/**
	 * Add the page under Help Center.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . Post_Type::NAME, __( 'Organize the help center', 'chatbot' ), __( 'Organize', 'chatbot' ), 'edit_wpbot_articles', self::SLUG, array( self::class, 'render' ), 3 );
	}

	/**
	 * Script and styles on this page only.
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		$file    = ( defined( 'QCLD_WPBOT_HELP_CENTER_DIR' ) ? QCLD_WPBOT_HELP_CENTER_DIR : dirname( __DIR__, 2 ) ) . '/help-center.php';
		$version = defined( 'QCLD_WPBOT_HELP_CENTER_VERSION' ) ? QCLD_WPBOT_HELP_CENTER_VERSION : '0';
		wp_enqueue_style( 'wpbot-help-admin', plugins_url( 'assets/admin.css', $file ), array(), $version );
		wp_enqueue_script( 'wpbot-help-organize', plugins_url( 'assets/organize.js', $file ), array( 'wp-api-fetch', 'wp-i18n', 'wp-a11y' ), $version, true );
		wp_set_script_translations( 'wpbot-help-organize', 'chatbot' );
		wp_add_inline_script(
			'wpbot-help-organize',
			'window.qcldWpbotHelpCenterOrganize = ' . wp_json_encode(
				array(
					'path'        => '/' . Order::REST_NAMESPACE . '/order',
					'canArticles' => current_user_can( 'publish_wpbot_articles' ),
					'canTerms'    => current_user_can( 'manage_wpbot_article_terms' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Render the tree.
	 *
	 * @return void
	 */
	public static function render() {
		$kbs = get_terms(
			array(
				'taxonomy'   => Taxonomies::KB,
				'hide_empty' => false,
			)
		);
		$kbs = is_array( $kbs ) ? Order::sort_terms( $kbs ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A filter.
		$kb_id = isset( $_GET['kb'] ) ? absint( $_GET['kb'] ) : 0;

		echo '<div class="wrap wpbot-help-organize"><h1>' . esc_html__( 'Organize the help center', 'chatbot' ) . '</h1>';
		echo '<p>' . esc_html__( 'Drag categories and articles to change their order, or use the Move up and Move down buttons. The order is saved at once.', 'chatbot' ) . '</p>';
		if ( $kbs ) {
			echo '<form method="get" class="wpbot-help-organize__filter"><input type="hidden" name="post_type" value="' . esc_attr( Post_Type::NAME ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
			echo '<label for="hc-kb">' . esc_html__( 'Knowledge base', 'chatbot' ) . '</label> <select id="hc-kb" name="kb" onchange="this.form.submit()"><option value="0">' . esc_html__( 'All', 'chatbot' ) . '</option>';
			foreach ( $kbs as $kb ) {
				echo '<option value="' . esc_attr( $kb->term_id ) . '"' . selected( $kb_id, $kb->term_id, false ) . '>' . esc_html( $kb->name ) . '</option>';
			}
			echo '</select> <noscript><button class="button">' . esc_html__( 'Show', 'chatbot' ) . '</button></noscript></form>';
		}
		echo '<p class="wpbot-help-organize__status" role="status" aria-live="polite"></p>';
		self::render_terms( 0, $kb_id );
		$loose = self::articles( 0, $kb_id );
		if ( $loose ) {
			echo '<h2>' . esc_html__( 'Articles without a category', 'chatbot' ) . '</h2>';
			self::render_articles( $loose );
		}
		if ( ! $loose && ! get_terms(
			array(
				'taxonomy'   => Taxonomies::CATEGORY,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			)
		) ) {
			echo '<p>' . esc_html__( 'Nothing to organize yet. Add a category or an article first.', 'chatbot' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Categories under a parent, each with its articles and child categories.
	 *
	 * @param int $parent_id Parent term id.
	 * @param int $kb_id  Knowledge base filter (0 = all).
	 * @return void
	 */
	private static function render_terms( $parent_id, $kb_id ) {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::CATEGORY,
				'hide_empty' => false,
				'parent'     => (int) $parent_id,
			)
		);
		if ( ! is_array( $terms ) || array() === $terms ) {
			return;
		}
		echo '<ol class="wpbot-help-tree" data-type="terms">';
		foreach ( Order::sort_terms( $terms ) as $term ) {
			echo '<li class="wpbot-help-tree__item is-term" data-id="' . esc_attr( $term->term_id ) . '">';
			self::row( $term->name, get_edit_term_link( $term->term_id, Taxonomies::CATEGORY, Post_Type::NAME ), 'category' );
			self::render_articles( self::articles( $term->term_id, $kb_id ) );
			self::render_terms( $term->term_id, $kb_id );
			echo '</li>';
		}
		echo '</ol>';
	}

	/**
	 * A list of articles.
	 *
	 * @param \WP_Post[] $posts Articles.
	 * @return void
	 */
	private static function render_articles( array $posts ) {
		if ( array() === $posts ) {
			return;
		}
		echo '<ol class="wpbot-help-tree is-articles" data-type="articles">';
		foreach ( $posts as $post ) {
			$label = get_the_title( $post );
			if ( 'publish' !== $post->post_status ) {
				$status = get_post_status_object( $post->post_status );
				$label .= ' — ' . ( $status ? $status->label : $post->post_status );
			}
			echo '<li class="wpbot-help-tree__item is-article" data-id="' . esc_attr( $post->ID ) . '">';
			self::row( $label, get_edit_post_link( $post->ID ), 'article' );
			echo '</li>';
		}
		echo '</ol>';
	}

	/**
	 * One row: drag handle, title link, move buttons.
	 *
	 * @param string $label Title.
	 * @param string $link  Edit link.
	 * @param string $kind  category or article.
	 * @return void
	 */
	private static function row( $label, $link, $kind ) {
		echo '<div class="wpbot-help-tree__row"><span class="wpbot-help-tree__handle" aria-hidden="true">⋮⋮</span>';
		echo '<span class="dashicons ' . ( 'category' === $kind ? 'dashicons-category' : 'dashicons-media-document' ) . '" aria-hidden="true"></span> ';
		echo $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label );
		echo ' <span class="wpbot-help-tree__move"><button type="button" class="button-link" data-move="-1">' . esc_html__( 'Move up', 'chatbot' ) . '</button> <button type="button" class="button-link" data-move="1">' . esc_html__( 'Move down', 'chatbot' ) . '</button></span></div>';
	}

	/**
	 * Articles directly in a category (0 = articles without a category), in their order.
	 *
	 * @param int $term_id Category id.
	 * @param int $kb_id   Knowledge base filter (0 = all).
	 * @return \WP_Post[]
	 */
	private static function articles( $term_id, $kb_id ) {
		$tax = array();
		if ( $term_id > 0 ) {
			$tax[] = array(
				'taxonomy'         => Taxonomies::CATEGORY,
				'terms'            => array( (int) $term_id ),
				'include_children' => false,
			);
		} else {
			$tax[] = array(
				'taxonomy' => Taxonomies::CATEGORY,
				'operator' => 'NOT EXISTS',
			);
		}
		if ( $kb_id > 0 ) {
			$tax[] = array(
				'taxonomy' => Taxonomies::KB,
				'terms'    => array( (int) $kb_id ),
			);
		}
		return get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- The tree shows a category's articles on one screen.
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'tax_query'      => $tax, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The tree is by category.
			)
		);
	}
}
