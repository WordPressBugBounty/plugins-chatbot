<?php
/**
 * "Build your help center" (HC1-15, owner decision D4).
 *
 * Offered on the Help Center screens and the dashboard while the Help Center has
 * no articles. One screen: starter categories (shop categories when WooCommerce
 * is active), and starter drafts from what the site already has: questions the
 * chatbot could not answer (Knowledge Gaps) and pages that read like help
 * (FAQ, shipping, returns, warranty…). Everything is created as drafts; nothing
 * is published. Each draft opens with a link to AI Writer.
 *
 * Writing all drafts at once with AI ("help center" mode) comes with
 * qcld/wpbot-ai-writer#67; this screen then offers it as the last step.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Chat\Gap_Writer;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * First-run screen.
 */
final class Start_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'wpbot-help-center-start';

	/**
	 * User meta: the offer was dismissed.
	 */
	const DISMISSED = 'qcld_wpbot_help_center_start_dismissed';

	/**
	 * Words in a page title that make it a starter article candidate.
	 */
	const PAGE_WORDS = '/\b(faq|faqs|help|support|shipping|delivery|returns?|refunds?|exchanges?|warranty|guarantee|how to|guide|getting started|installation|setup|set up|pricing|payments?|account|troubleshoot\w*|size guide|care)\b/i';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_notices', array( self::class, 'offer' ) );
		// With WPBot Core's design, notices go into the bell: the offer is a page part there.
		add_action( 'qcld_wpbot_core/design_page_top', array( self::class, 'offer_in_page' ) );
		add_action( 'admin_post_qcld_wpbot_help_center_start', array( self::class, 'build' ) );
		add_action( 'admin_post_qcld_wpbot_help_center_start_dismiss', array( self::class, 'dismiss' ) );
	}

	/**
	 * Add the page under Help Center.
	 *
	 * @return void
	 */
	public static function menu() {
		$type = get_post_type_object( Post_Type::NAME );
		add_submenu_page( 'edit.php?post_type=' . Post_Type::NAME, __( 'Build your help center', 'chatbot' ), __( 'Get started', 'chatbot' ), $type ? $type->cap->edit_posts : 'edit_posts', self::SLUG, array( self::class, 'render' ), 0 );
	}

	/**
	 * Whether the Help Center has no articles at all (any status but trash).
	 *
	 * @return bool
	 */
	public static function is_empty() {
		$counts = wp_count_posts( Post_Type::NAME );
		$total  = 0;
		foreach ( (array) $counts as $status => $n ) {
			if ( 'trash' !== $status && 'auto-draft' !== $status ) {
				$total += (int) $n;
			}
		}
		return 0 === $total;
	}

	/**
	 * The offer on the dashboard and the Help Center screens.
	 *
	 * @return void
	 */
	public static function offer() {
		if ( Menu::design_on() ) {
			return;
		}
		self::print_offer();
	}

	/**
	 * The offer at the top of a Help Center page that uses WPBot Core's design.
	 *
	 * @return void
	 */
	public static function offer_in_page() {
		if ( Menu::design_on() ) {
			self::print_offer();
		}
	}

	/**
	 * Print the offer when the Help Center is empty.
	 *
	 * @return void
	 */
	private static function print_offer() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$type   = get_post_type_object( Post_Type::NAME );
		if ( ! $screen || ! $type || ! current_user_can( $type->cap->edit_posts ) || get_user_meta( get_current_user_id(), self::DISMISSED, true ) ) {
			return;
		}
		$ours = Post_Type::NAME === $screen->post_type || 'dashboard' === $screen->id;
		if ( ! $ours || false !== strpos( (string) $screen->id, self::SLUG ) || ! self::is_empty() ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=qcld_wpbot_help_center_start_dismiss' ), 'qcld_wpbot_help_center_start_dismiss' );
		echo '<div class="' . ( Menu::design_on() ? 'wpb-hc-offer' : 'notice notice-info' ) . '"><p><strong>' . esc_html__( 'Your help center is empty.', 'chatbot' ) . '</strong> ' . esc_html__( 'Start it from what your site already has: questions visitors asked the chatbot and your FAQ, shipping or returns pages become drafts you can finish.', 'chatbot' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG ) ) . '">' . esc_html__( 'Build my help center', 'chatbot' ) . '</a> <a class="button-link" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Not now', 'chatbot' ) . '</a></p></div>';
		if ( Menu::design_on() ) {
			echo '<style>.wpb-hc-offer{margin:16px 0;padding:12px 16px;background:var(--wpb-soft,#f6f6f4);border-radius:var(--wpb-radius,10px)}.wpb-hc-offer p{margin:.4em 0}</style>';
		}
	}

	/**
	 * Hide the offer for this user.
	 *
	 * @return void
	 */
	public static function dismiss() {
		check_admin_referer( 'qcld_wpbot_help_center_start_dismiss' );
		update_user_meta( get_current_user_id(), self::DISMISSED, 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Suggested starter categories.
	 *
	 * @param bool $shop Whether the site is a shop (WooCommerce).
	 * @return array<string,string> Slug => name.
	 */
	public static function categories( $shop ) {
		$cats = array(
			'getting-started' => __( 'Getting started', 'chatbot' ),
			'your-account'    => __( 'Your account', 'chatbot' ),
		);
		if ( $shop ) {
			$cats['orders-delivery']  = __( 'Orders & delivery', 'chatbot' );
			$cats['returns-refunds']  = __( 'Returns & refunds', 'chatbot' );
			$cats['payments-billing'] = __( 'Payments & billing', 'chatbot' );
		} else {
			$cats['billing'] = __( 'Billing', 'chatbot' );
		}
		$cats['troubleshooting'] = __( 'Troubleshooting', 'chatbot' );
		/**
		 * Filters the starter categories offered on the first-run screen.
		 *
		 * @param array<string,string> $cats Slug => name.
		 * @param bool                 $shop Whether WooCommerce is active.
		 */
		return (array) apply_filters( 'qcld_wpbot_help_center/starter_categories', $cats, $shop );
	}

	/**
	 * Whether a page title reads like help.
	 *
	 * @param string $title Title.
	 * @return bool
	 */
	public static function helpful_title( $title ) {
		return 1 === preg_match( self::PAGE_WORDS, (string) $title );
	}

	/**
	 * Pages that read like help.
	 *
	 * @return \WP_Post[]
	 */
	public static function pages() {
		$skip  = array_filter( array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ), (int) get_option( 'wp_page_for_privacy_policy' ) ) );
		$out   = array();
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Titles only, read once on this screen.
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
		foreach ( $pages as $page ) {
			if ( ! in_array( (int) $page->ID, $skip, true ) && '' === (string) $page->post_password && self::helpful_title( get_the_title( $page ) ) ) {
				$out[] = $page;
			}
		}
		return array_slice( $out, 0, 20 );
	}

	/**
	 * Open questions from Knowledge Gaps.
	 *
	 * @return array[]
	 */
	public static function gaps() {
		if ( ! class_exists( '\QuantumCloud\Chatbot\Train\Gaps' ) ) {
			return array();
		}
		$found = \QuantumCloud\Chatbot\Train\Gaps::items( 'open', 1, 15 );
		return isset( $found['items'] ) ? (array) $found['items'] : array();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Message after the redirect.
		$made = isset( $_GET['made'] ) ? absint( $_GET['made'] ) : -1;
		$shop = class_exists( 'WooCommerce' );
		echo '<div class="wrap wpbot-hc-start"><h1>' . esc_html__( 'Build your help center', 'chatbot' ) . '</h1>';
		if ( $made >= 0 ) {
			/* translators: %d: number of drafts. */
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( _n( '%d draft is ready to finish.', '%d drafts are ready to finish.', $made, 'chatbot' ), $made ) ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&post_status=draft' ) ) . '">' . esc_html__( 'Open the drafts', 'chatbot' ) . '</a></p></div>';
		}
		echo '<p class="description">' . esc_html__( 'Choose what to start with. Everything becomes a draft in the Help Center; nothing is published until you publish it.', 'chatbot' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'qcld_wpbot_help_center_start' );
		echo '<input type="hidden" name="action" value="qcld_wpbot_help_center_start">';

		echo '<h2>' . esc_html__( '1. Categories', 'chatbot' ) . '</h2><fieldset><legend class="screen-reader-text">' . esc_html__( 'Categories', 'chatbot' ) . '</legend>';
		foreach ( self::categories( $shop ) as $slug => $name ) {
			$exists = term_exists( $slug, Taxonomies::CATEGORY );
			echo '<label class="wpbot-hc-start__item"><input type="checkbox" name="cats[]" value="' . esc_attr( $slug ) . '"' . checked( true, true, false ) . disabled( (bool) $exists, true, false ) . '> ' . esc_html( $name ) . ( $exists ? ' <span class="description">(' . esc_html__( 'already there', 'chatbot' ) . ')</span>' : '' ) . '</label>';
		}
		echo '</fieldset>';

		$gaps = self::gaps();
		echo '<h2>' . esc_html__( '2. Questions visitors asked', 'chatbot' ) . '</h2>';
		if ( $gaps ) {
			echo '<p class="description">' . esc_html__( 'The chatbot could not answer these. Each becomes a draft titled with the question, in the closest category.', 'chatbot' ) . '</p><fieldset><legend class="screen-reader-text">' . esc_html__( 'Questions', 'chatbot' ) . '</legend>';
			foreach ( $gaps as $gap ) {
				/* translators: %d: how many times. */
				echo '<label class="wpbot-hc-start__item"><input type="checkbox" name="gaps[]" value="' . (int) $gap['id'] . '" checked> ' . esc_html( (string) $gap['question'] ) . ' <span class="description">' . esc_html( sprintf( _n( 'asked %d time', 'asked %d times', (int) $gap['hits'], 'chatbot' ), (int) $gap['hits'] ) ) . '</span></label>';
			}
			echo '</fieldset>';
		} else {
			echo '<p class="description">' . esc_html__( 'No open questions yet. Questions the chatbot cannot answer appear in Knowledge Gaps and here.', 'chatbot' ) . '</p>';
		}

		$pages = self::pages();
		echo '<h2>' . esc_html__( '3. Pages that read like help', 'chatbot' ) . '</h2>';
		if ( $pages ) {
			echo '<p class="description">' . esc_html__( 'Each chosen page is copied into a draft article. The page itself stays as it is.', 'chatbot' ) . '</p><fieldset><legend class="screen-reader-text">' . esc_html__( 'Pages', 'chatbot' ) . '</legend>';
			foreach ( $pages as $page ) {
				echo '<label class="wpbot-hc-start__item"><input type="checkbox" name="pages[]" value="' . (int) $page->ID . '" checked> ' . esc_html( get_the_title( $page ) ) . ' <a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'view', 'chatbot' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'chatbot' ) . '</span></a></label>';
			}
			echo '</fieldset>';
		} else {
			echo '<p class="description">' . esc_html__( 'No FAQ, shipping, returns or similar pages were found.', 'chatbot' ) . '</p>';
		}

		echo '<h2>' . esc_html__( '4. Write with AI', 'chatbot' ) . '</h2><p class="description">';
		echo function_exists( 'qcld_wpbot_writer_open_brief_url' )
			? esc_html__( 'Each draft opens with a link to AI Writer. Writing all drafts at once comes with the next AI Writer update.', 'chatbot' )
			: esc_html__( 'With AI Writer switched on, each draft opens with a link to write it with AI.', 'chatbot' );
		echo '</p>';
		submit_button( __( 'Create the drafts', 'chatbot' ) );
		echo '</form></div>';
	}

	/**
	 * Create the chosen categories and drafts.
	 *
	 * @return void
	 */
	public static function build() {
		$type = get_post_type_object( Post_Type::NAME );
		if ( ! $type || ! current_user_can( $type->cap->edit_posts ) ) {
			wp_die( esc_html__( 'You are not allowed to write help articles.', 'chatbot' ), 403 );
		}
		check_admin_referer( 'qcld_wpbot_help_center_start' );
		$shop  = class_exists( 'WooCommerce' );
		$names = self::categories( $shop );
		$cats  = isset( $_POST['cats'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['cats'] ) ) : array();
		if ( current_user_can( 'manage_wpbot_article_terms' ) ) {
			foreach ( $cats as $slug ) {
				if ( isset( $names[ $slug ] ) && ! term_exists( $slug, Taxonomies::CATEGORY ) ) {
					wp_insert_term( $names[ $slug ], Taxonomies::CATEGORY, array( 'slug' => $slug ) );
				}
			}
		}
		$made = 0;
		$gaps = isset( $_POST['gaps'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['gaps'] ) ) : array();
		foreach ( self::gaps() as $gap ) {
			if ( ! in_array( (int) $gap['id'], $gaps, true ) ) {
				continue;
			}
			$url = Gap_Writer::write( '', $gap );
			if ( '' === $url ) {
				continue;
			}
			++$made;
			// No article to take the category from yet (a new help center): use the question.
			if ( preg_match( '/[?&]post=(\d+)/', $url, $m ) && ! wp_get_object_terms( (int) $m[1], Taxonomies::CATEGORY, array( 'fields' => 'ids' ) ) ) {
				self::assign( (int) $m[1], (string) $gap['question'], $shop );
			}
		}
		$pages = isset( $_POST['pages'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['pages'] ) ) : array();
		foreach ( self::pages() as $page ) {
			if ( ! in_array( (int) $page->ID, $pages, true ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => Post_Type::NAME,
					'post_status'  => 'draft',
					'post_title'   => $page->post_title,
					'post_content' => $page->post_content,
					'post_excerpt' => $page->post_excerpt,
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta(
				$id,
				'_wpbot_source',
				array(
					'type' => 'page',
					'id'   => (int) $page->ID,
					'url'  => (string) get_permalink( $page ),
				)
			);
			self::assign( (int) $id, (string) $page->post_title, $shop );
			++$made;
		}//end foreach
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG . '&made=' . $made ) );
		exit;
	}

	/**
	 * Put a draft in the starter category its title suggests (when that category exists).
	 *
	 * @param int    $post_id Draft id.
	 * @param string $title   Title.
	 * @param bool   $shop    Whether the site is a shop.
	 * @return void
	 */
	private static function assign( $post_id, $title, $shop ) {
		$term = get_term_by( 'slug', self::category_for( $title, $shop ), Taxonomies::CATEGORY );
		if ( $term ) {
			wp_set_object_terms( $post_id, array( (int) $term->term_id ), Taxonomies::CATEGORY );
		}
	}

	/**
	 * A starter category for a title (the first starter category when nothing matches).
	 *
	 * @param string $title Page title.
	 * @param bool   $shop  Whether the site is a shop.
	 * @return string Slug.
	 */
	public static function category_for( $title, $shop ) {
		$title = strtolower( (string) $title );
		$rules = array(
			'returns-refunds'  => '/return|refund|exchange|warranty|guarantee/',
			'orders-delivery'  => '/shipping|delivery|order/',
			'payments-billing' => '/payment|pricing|billing|invoice/',
			'billing'          => '/payment|pricing|billing|invoice/',
			'your-account'     => '/account|login|sign in|password/',
			'troubleshooting'  => '/troubleshoot|problem|error/',
			'getting-started'  => '/getting started|how to|guide|setup|set up|installation|faq|help|support/',
		);
		$cats  = self::categories( $shop );
		foreach ( $rules as $slug => $pattern ) {
			if ( isset( $cats[ $slug ] ) && 1 === preg_match( $pattern, $title ) ) {
				return $slug;
			}
		}
		return (string) key( $cats );
	}
}
