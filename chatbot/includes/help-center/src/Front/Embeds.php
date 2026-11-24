<?php
/**
 * Help Center parts for any page (HC1-13, plan section 11 "Blocks and shortcodes").
 *
 * Each part is a shortcode, a block (server-rendered, so it follows the Help
 * Center as it changes) and, with Elementor, a widget. They all render here:
 *
 * | Part            | Shortcode                  | Block                    |
 * |-----------------|----------------------------|--------------------------|
 * | Whole home      | [wpbot_help_center]        | (shortcode only)         |
 * | Search          | [wpbot_help_search]        | wpbot-help/search-box    |
 * | Knowledge bases | [wpbot_help_kbs]           | wpbot-help/kb-grid       |
 * | Topics          | [wpbot_help_topics]        | wpbot-help/topics        |
 * | Article list    | [wpbot_help_articles]      | wpbot-help/article-list  |
 * | Most read       | [wpbot_help_popular]       | wpbot-help/popular       |
 * | Glossary        | [wpbot_help_glossary]      | wpbot-help/glossary      |
 * | Contents        | [wpbot_help_toc]           | wpbot-help/toc           |
 * | Feedback        | [wpbot_help_feedback]      | wpbot-help/feedback      |
 * | Help launcher   | [wpbot_help_launcher]      | wpbot-help/launcher      |
 *
 * Parts follow the theme's fonts and colours (like the Simple layout) with the
 * Help Center accent. Gated articles are never listed with their content.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Front;

use QuantumCloud\HelpCenter\Access;
use QuantumCloud\HelpCenter\Blocks\Blocks;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;
use QuantumCloud\HelpCenter\Feedback\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes, blocks and their rendering.
 */
final class Embeds {

	/**
	 * Parts: id => attribute defaults (shortcode and block share them).
	 *
	 * @return array<string,array>
	 */
	public static function parts() {
		return array(
			'center'   => array(
				'title'    => '',
				'articles' => 3,
				'popular'  => 5,
			),
			'search'   => array(
				'placeholder' => '',
			),
			'kbs'      => array(),
			'topics'   => array(
				'articles' => 3,
			),
			'articles' => array(
				'category' => '',
				'kb'       => '',
				'tag'      => '',
				'limit'    => 10,
				'orderby'  => 'order',
				'title'    => '',
			),
			'popular'  => array(
				'limit' => 5,
				'title' => '',
			),
			'glossary' => array(),
			'toc'      => array(),
			'feedback' => array(),
			'launcher' => array(
				'label' => '',
			),
		);
	}

	/**
	 * Block name of each part.
	 *
	 * @var array<string,string>
	 */
	const BLOCKS = array(
		'search'   => 'search-box',
		'kbs'      => 'kb-grid',
		'topics'   => 'topics',
		'articles' => 'article-list',
		'popular'  => 'popular',
		'glossary' => 'glossary',
		'toc'      => 'toc',
		'feedback' => 'feedback',
		'launcher' => 'launcher',
	);

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( array_keys( self::parts() ) as $part ) {
			add_shortcode( 'wpbot_help_' . $part, array( self::class, 'shortcode' ) );
		}
		add_action( 'init', array( self::class, 'register_blocks' ), 13 );
		add_action( 'elementor/widgets/register', array( self::class, 'elementor' ) );
		add_action( 'elementor/elements/categories_registered', array( self::class, 'elementor_category' ) );
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array|string $atts Attributes.
	 * @param string|null  $content Content (unused).
	 * @param string       $tag Shortcode name.
	 * @return string
	 */
	public static function shortcode( $atts, $content = null, $tag = '' ) {
		unset( $content );
		$part = substr( (string) $tag, strlen( 'wpbot_help_' ) );
		if ( ! isset( self::parts()[ $part ] ) ) {
			return '';
		}
		return self::render( $part, shortcode_atts( self::parts()[ $part ], is_array( $atts ) ? $atts : array(), $tag ) );
	}

	/**
	 * Register the blocks (block.json in blocks/<name>, rendered here).
	 *
	 * @return void
	 */
	public static function register_blocks() {
		$file    = Blocks::dir() . '/help-center.php';
		$version = defined( 'QCLD_WPBOT_HELP_CENTER_VERSION' ) ? QCLD_WPBOT_HELP_CENTER_VERSION : '0';
		wp_register_script(
			'wpbot-help-embeds-editor',
			plugins_url( 'assets/embeds-editor.js', $file ),
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-data', 'wp-server-side-render' ),
			$version,
			true
		);
		wp_set_script_translations( 'wpbot-help-embeds-editor', 'chatbot' );
		foreach ( self::BLOCKS as $part => $name ) {
			register_block_type(
				Blocks::dir() . '/blocks/' . $name,
				array(
					'render_callback' => static function ( $attributes ) use ( $part ) {
						return self::render( $part, array_merge( self::parts()[ $part ], (array) $attributes ) );
					},
				)
			);
		}
	}

	/**
	 * Render a part.
	 *
	 * @param string $part Part id.
	 * @param array  $atts Attributes.
	 * @return string
	 */
	public static function render( $part, array $atts ) {
		$design = Front::design();
		$view   = new View( array_merge( $design, array( 'layout' => 'simple' ) ) );
		switch ( $part ) {
			case 'center':
				$inner = self::center( $view, $design, $atts );
				break;
			case 'search':
				$inner = $view->search_form( 'embed' );
				if ( '' !== trim( (string) $atts['placeholder'] ) ) {
					$inner = str_replace( 'placeholder="' . esc_attr__( 'Search help', 'chatbot' ) . '"', 'placeholder="' . esc_attr( (string) $atts['placeholder'] ) . '"', $inner );
				}
				break;
			case 'kbs':
				$inner = self::kbs();
				break;
			case 'topics':
				$inner = $view->topics( max( 1, min( 10, (int) $atts['articles'] ) ), true );
				break;
			case 'articles':
				$inner = self::articles( $atts );
				break;
			case 'popular':
				$inner = self::popular( max( 1, min( 20, (int) $atts['limit'] ) ), (string) $atts['title'] );
				break;
			case 'glossary':
				$inner = $view->glossary( true );
				break;
			case 'toc':
				$inner = self::toc( $view );
				break;
			case 'feedback':
				$post  = get_post();
				$inner = $post && Post_Type::NAME === $post->post_type ? Widget::html_if_allowed( $post, $design['contact_url'] ) : '';
				break;
			case 'launcher':
				$inner = self::launcher( (string) $atts['label'] );
				break;
			default:
				$inner = '';
		}//end switch
		if ( '' === $inner ) {
			return '';
		}
		Front::enqueue();
		return sprintf(
			'<div class="wpbot-hc wpbot-hc--simple wpbot-hc-embed wpbot-hc-embed--%1$s" data-scheme="theme" style="%2$s">%3$s</div>',
			esc_attr( $part ),
			esc_attr( '--hc-accent:' . $design['accent'] . ';' ),
			$inner
		);
	}

	/**
	 * The whole Help Center home inside any page (owner, 19 November 2026): title and introduction,
	 * search, knowledge bases, topics with their top articles, most read.
	 *
	 * @param View  $view   View.
	 * @param array $design Design settings.
	 * @param array $atts   title, articles (per topic), popular (0 hides it).
	 * @return string
	 */
	private static function center( View $view, array $design, array $atts ) {
		$title = '' !== trim( (string) $atts['title'] ) ? (string) $atts['title'] : ( '' !== $design['title'] ? $design['title'] : __( 'How can we help?', 'chatbot' ) );
		$out   = '<h2 class="wpbot-hc__h2">' . esc_html( $title ) . '</h2>';
		if ( '' !== $design['lede'] ) {
			$out .= '<p class="wpbot-hc__lede">' . esc_html( $design['lede'] ) . '</p>';
		}
		$out    .= $view->search_form( 'embed' );
		$out    .= self::kbs();
		$out    .= $view->topics( max( 1, min( 10, (int) $atts['articles'] ) ), true );
		$popular = max( 0, min( 20, (int) $atts['popular'] ) );
		if ( $popular > 0 ) {
			$out .= self::popular( $popular, '' );
		}
		$out .= '<p><a class="wpbot-hc__btn is-outline" href="' . esc_url( Urls::home() ) . '">' . esc_html__( 'Open the Help Center', 'chatbot' ) . '</a></p>';
		return $out;
	}

	/**
	 * Knowledge bases as cards.
	 *
	 * @return string
	 */
	private static function kbs() {
		$kbs = Data::knowledge_bases();
		if ( ! $kbs ) {
			return '';
		}
		$out = '<div class="wpbot-hc__cards">';
		foreach ( $kbs as $kb ) {
			$count = Data::count_in( $kb );
			$out  .= '<a class="wpbot-hc__card" href="' . esc_url( get_term_link( $kb ) ) . '"><strong>' . esc_html( $kb->name ) . '</strong>';
			$out  .= '' !== trim( (string) $kb->description ) ? '<span class="wpbot-hc__topic-desc">' . esc_html( $kb->description ) . '</span>' : '';
			/* translators: %d: number of articles. */
			$out .= '<span class="wpbot-hc__meta">' . esc_html( sprintf( _n( '%d article', '%d articles', $count, 'chatbot' ), $count ) ) . '</span></a>';
		}
		return $out . '</div>';
	}

	/**
	 * Query arguments for an article list.
	 *
	 * @param array $atts category, kb, tag (slugs or ids, comma-separated), limit, orderby (order, recent, title, popular).
	 * @return array
	 */
	public static function query_args( array $atts ) {
		$args = array(
			'post_type'      => Post_Type::NAME,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 50, (int) $atts['limit'] ) ),
			'no_found_rows'  => true,
		);
		$tax  = array();
		foreach ( array(
			'category' => Taxonomies::CATEGORY,
			'kb'       => Taxonomies::KB,
			'tag'      => Taxonomies::TAG,
		) as $key => $taxonomy ) {
			$values = array_filter( array_map( 'trim', explode( ',', (string) $atts[ $key ] ) ), 'strlen' );
			if ( ! $values ) {
				continue;
			}
			$numeric = array_filter( $values, 'ctype_digit' ) === $values;
			$tax[]   = array(
				'taxonomy' => $taxonomy,
				'field'    => $numeric ? 'term_id' : 'slug',
				'terms'    => $numeric ? array_map( 'intval', $values ) : array_map( 'sanitize_title', $values ),
			);
		}
		if ( $tax ) {
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filtering by the chosen terms.
		}
		switch ( (string) $atts['orderby'] ) {
			case 'recent':
				$args['orderby'] = 'modified';
				$args['order']   = 'DESC';
				break;
			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'popular':
				$args['meta_key'] = '_wpbot_views'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorted by views.
				$args['orderby']  = array(
					'meta_value_num' => 'DESC',
					'title'          => 'ASC',
				);
				break;
			default:
				$args['orderby'] = array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				);
		}//end switch
		return $args;
	}

	/**
	 * An article list.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	private static function articles( array $atts ) {
		$posts = get_posts( self::query_args( $atts ) );
		if ( ! $posts ) {
			return '';
		}
		$out  = '' !== trim( (string) $atts['title'] ) ? '<h2 class="wpbot-hc__h2s">' . esc_html( (string) $atts['title'] ) . '</h2>' : '';
		$out .= '<ul class="wpbot-hc__list">';
		foreach ( $posts as $post ) {
			$out .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '"><span>' . esc_html( get_the_title( $post ) ) . '</span><span aria-hidden="true" class="wpbot-hc__meta">→</span></a></li>';
		}
		return $out . '</ul>';
	}

	/**
	 * Most read articles.
	 *
	 * @param int    $limit Number.
	 * @param string $title Heading.
	 * @return string
	 */
	private static function popular( $limit, $title ) {
		$posts = Data::most_read( $limit );
		if ( ! $posts ) {
			return '';
		}
		$out = '<h2 class="wpbot-hc__h2s">' . esc_html( '' !== trim( $title ) ? $title : __( 'Most read', 'chatbot' ) ) . '</h2><ol class="wpbot-hc__rows">';
		foreach ( $posts as $i => $post ) {
			$out .= '<li><span class="wpbot-hc__mono">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
		}
		return $out . '</ol>';
	}

	/**
	 * Contents of the article being viewed.
	 *
	 * @param View $view View.
	 * @return string
	 */
	private static function toc( View $view ) {
		$post = get_post();
		if ( ! $post || Post_Type::NAME !== $post->post_type || ! Access::can_read( $post ) ) {
			return '';
		}
		$entries = Data::toc( \QuantumCloud\HelpCenter\Content\Heading_Ids::add( (string) do_blocks( (string) $post->post_content ) ) );
		return $entries ? $view->toc( $entries, false ) : '';
	}

	/**
	 * A button that opens the chatbot (or the Help Center when the chatbot is not on the page).
	 *
	 * @param string $label Label.
	 * @return string
	 */
	private static function launcher( $label ) {
		$label = '' !== trim( $label ) ? $label : __( 'Get help', 'chatbot' );
		return '<a class="wpbot-hc__btn is-solid" href="' . esc_url( Urls::home() ) . '" data-launcher>' . esc_html( $label ) . '</a>';
	}

	/**
	 * Elementor category.
	 *
	 * @param object $manager Elements manager.
	 * @return void
	 */
	public static function elementor_category( $manager ) {
		if ( is_object( $manager ) && method_exists( $manager, 'add_category' ) ) {
			$manager->add_category(
				'wpbot-help',
				array(
					'title' => __( 'Help Center', 'chatbot' ),
					'icon'  => 'eicon-help-o',
				)
			);
		}
	}

	/**
	 * Register the basic Elementor widgets.
	 *
	 * @param object $widgets Widgets manager.
	 * @return void
	 */
	public static function elementor( $widgets ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets ) || ! method_exists( $widgets, 'register' ) ) {
			return;
		}
		require_once __DIR__ . '/Elementor_Widgets.php';
		foreach ( Elementor_Widgets::classes() as $class ) {
			$widgets->register( new $class() );
		}
	}
}
