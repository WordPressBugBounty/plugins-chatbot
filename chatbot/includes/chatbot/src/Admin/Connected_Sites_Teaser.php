<?php
/**
 * ChatBot → Connected sites, marked Pro, where the feature is not part of the package (WPBot Free,
 * WPBot Pro Starter): what the shared knowledge base does and which packages have it.
 *
 * An information page only: no feature code, no locked controls, no licence check. WPBot Pro
 * Professional and Master register the real page (qcld_wpbot_chat/connected_sites returns true)
 * and this one is not added.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Connected sites (Pro) information page.
 */
final class Connected_Sites_Teaser {

	/**
	 * Page slug (the same as the real page, so links keep working after an upgrade).
	 */
	const SLUG = 'wpbot-connected-sites';

	/**
	 * Hook in (admin).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 100 );
		add_filter( 'qcld_wpbot_chat/admin_pages', array( __CLASS__, 'page' ), 100 );
	}

	/**
	 * Whether this package has the real Connected sites page.
	 *
	 * @return bool
	 */
	public static function has_feature() {
		/**
		 * Whether this package has Connected sites (the shared knowledge base). WPBot Pro
		 * Professional and Master return true.
		 *
		 * @param bool $has Default false.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/connected_sites', false );
	}

	/**
	 * The page in the host's ChatBot menu.
	 *
	 * @param array[] $pages Pages.
	 * @return array[]
	 */
	public static function page( $pages ) {
		if ( self::has_feature() ) {
			return $pages;
		}
		$pages[] = array(
			'slug'       => self::SLUG,
			'title'      => __( 'Connected sites (Pro)', 'chatbot' ),
			'icon'       => 'dashicons-networking',
			'capability' => 'manage_options',
		);
		return $pages;
	}

	/**
	 * Menu item.
	 *
	 * @return void
	 */
	public static function menu() {
		if ( self::has_feature() ) {
			return;
		}
		add_submenu_page(
			Train_Page::parent(),
			esc_html__( 'Connected sites', 'chatbot' ),
			esc_html__( 'Connected sites', 'chatbot' ) . ' <span class="awaiting-mod" style="background:var(--wpb-accent,#15803d)">' . esc_html__( 'Pro', 'chatbot' ) . '</span>',
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Benefits and features, for the page and for tests.
	 *
	 * @return array{benefits: array[], features: string[]}
	 */
	public static function content() {
		return array(
			'benefits' => array(
				array(
					'title' => __( 'One bot for all your sites', 'chatbot' ),
					'text'  => __( 'Your main site, your docs site, your blog and shop: the chatbot answers from all of them, with the same answers everywhere and one set of settings.', 'chatbot' ),
				),
				array(
					'title' => __( 'Answers link to the right page', 'chatbot' ),
					'text'  => __( 'An answer from your docs site cites that page on your docs site, down to the section, even when the chat happens on your sales site.', 'chatbot' ),
				),
				array(
					'title' => __( 'Everything in one place', 'chatbot' ),
					'text'  => __( 'Conversations, unanswered questions, leads and AI costs from every site land on one site, so you train one bot and pay one AI bill.', 'chatbot' ),
				),
			),
			'features' => array(
				__( 'Connect another WordPress site in three ways: it sends its content (custom fields, products and help articles included), the hub reads its public REST API, or the hub reads another site of the same WordPress network.', 'chatbot' ),
				__( 'Changes arrive when a post is saved, unpublished or deleted, with a full resync once a week.', 'chatbot' ),
				__( 'Rules per site: post types and categories to include or leave out, a weight (rank the blog lower than the docs), and support or sales purpose.', 'chatbot' ),
				__( 'The hub can answer the chats of your other sites, with their own page as context, or each site keeps its own chatbot and AI and searches the shared knowledge.', 'chatbot' ),
				__( 'Health per site: pages, pieces ready for search, last read, last error.', 'chatbot' ),
				__( 'Public content, plus members-only content if you choose: only members of the site it comes from get answers from it. Signed and encrypted connections, and AI embeddings made once, on the hub.', 'chatbot' ),
			),
		);
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$content = self::content();
		Admin_Pages::header( self::SLUG, __( 'Connected sites', 'chatbot' ) );
		echo '<p class="qcwp-lead"><span class="qcwp-pill" style="background:var(--wpb-accent-soft,#e9f6ee);color:var(--wpb-accent-text,#137a3a);margin-right:8px">' . esc_html__( 'Pro', 'chatbot' ) . '</span>' . esc_html__( 'Several WordPress sites, one knowledge base and one chatbot. Available in WPBot Pro Professional and Master.', 'chatbot' ) . '</p>';
		// Open columns, no boxes (WPBot Core admin design, owner 20 November 2026).
		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px;margin:20px 0">';
		foreach ( $content['benefits'] as $benefit ) {
			echo '<div><span style="display:block;color:var(--wpb-accent-text,#137a3a);font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em">' . esc_html__( 'Pro', 'chatbot' ) . '</span><h2 style="margin:4px 0 6px">' . esc_html( $benefit['title'] ) . '</h2><p class="qcwp-help" style="margin:0">' . esc_html( $benefit['text'] ) . '</p></div>';
		}
		echo '</div>';
		echo '<div class="qcwp-card"><h2>' . esc_html__( 'What you get', 'chatbot' ) . '</h2><ul style="list-style:disc;padding-left:20px">';
		foreach ( $content['features'] as $feature ) {
			echo '<li>' . esc_html( $feature ) . '</li>';
		}
		echo '</ul>';
		echo '<p class="qcwp-help">' . esc_html__( 'Example: wpbot.pro answers from its own pages and from wpbot.pro/docs (documentation, help articles and the blog), and every answer about a feature links to the right section of the docs.', 'chatbot' ) . '</p>';
		echo '<div class="qcwp-actions"><a class="qcwp-btn is-primary" href="' . esc_url( 'https://www.wpbot.pro/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'See WPBot Pro', 'chatbot' ) . '</a></div></div>';
		Admin_Pages::footer();
	}
}
