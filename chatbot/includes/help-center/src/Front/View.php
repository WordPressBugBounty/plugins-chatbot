<?php
/**
 * Help Center pages in three layouts (owner decision D1): Help Center (home with
 * search and the assistant, topics, most read, recently updated, contact), Docs
 * (left navigation, article, table of contents) and Simple (one column inside the
 * theme, in the theme's fonts). Rendered inside the theme's header and footer.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Front;

use QuantumCloud\HelpCenter\Blocks\Blocks;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Access;
use QuantumCloud\HelpCenter\Content\Taxonomies;
use QuantumCloud\HelpCenter\Glossary\Glossary;
use QuantumCloud\HelpCenter\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Help Center views.
 */
final class View {

	/**
	 * Design settings for this request.
	 *
	 * @var array
	 */
	private $design;

	/**
	 * Constructor.
	 *
	 * @param array $design Design settings (Settings::design()).
	 */
	public function __construct( array $design ) {
		$this->design = $design;
	}

	/**
	 * The whole Help Center area for a view.
	 *
	 * @param string $view home, archive, single or search.
	 * @return string
	 */
	public function render( $view ) {
		$layout = $this->design['layout'];
		$method = 'docs' === $layout ? 'docs' : $view;
		$inner  = 'docs' === $layout ? $this->docs( $view ) : $this->$method();
		$style  = '--hc-accent:' . $this->design['accent'] . ';';
		return sprintf(
			'<div class="wpbot-hc wpbot-hc--%1$s wpbot-hc--%2$s" data-scheme="%3$s" style="%4$s">%5$s</div>',
			esc_attr( $layout ),
			esc_attr( $view ),
			// Simple follows the theme's colours.
			esc_attr( 'simple' === $layout ? 'theme' : $this->design['scheme'] ),
			esc_attr( $style ),
			$inner
		);
	}

	/**
	 * Home.
	 *
	 * @return string
	 */
	private function home() {
		$title = '' !== $this->design['title'] ? $this->design['title'] : __( 'How can we help?', 'chatbot' );
		$lede  = '' !== $this->design['lede'] ? $this->design['lede'] : sprintf( /* translators: %s: site name. */ __( 'Answers about %s, from our help articles and the assistant.', 'chatbot' ), get_bloginfo( 'name' ) );
		if ( 'simple' === $this->design['layout'] ) {
			$out  = '<div class="wpbot-hc__col">';
			$out .= '<h1 class="wpbot-hc__h1">' . esc_html( $title ) . '</h1><p class="wpbot-hc__lede">' . esc_html( $lede ) . '</p>';
			$out .= $this->search_form( 'simple' );
			$out .= $this->topics( 3, true );
			$out .= $this->glossary_link();
			$out .= $this->cant_find();
			return $out . '</div>';
		}
		$out    = '<section class="wpbot-hc__hero"><div class="wpbot-hc__wrap wpbot-hc__hero-grid"><div>';
		$out   .= '<p class="wpbot-hc__eyebrow">' . esc_html( get_bloginfo( 'name' ) ) . ' · ' . esc_html__( 'Help Center', 'chatbot' ) . '</p>';
		$out   .= '<h1 class="wpbot-hc__h1">' . esc_html( $title ) . '</h1><p class="wpbot-hc__lede">' . esc_html( $lede ) . '</p>';
		$out   .= $this->search_form( 'hero' );
		$out   .= '</div>' . $this->assistant_card() . '</div></section>';
		$out   .= '<div class="wpbot-hc__wrap">' . $this->topics( 3, false ) . $this->glossary_link();
		$most   = Data::most_read( 5 );
		$recent = Data::recent( 5 );
		if ( $most || $recent ) {
			$out .= '<div class="wpbot-hc__two">';
			if ( $most ) {
				$out .= '<section><h2 class="wpbot-hc__h2s">' . esc_html__( 'Most read', 'chatbot' ) . '</h2><ol class="wpbot-hc__rows">';
				foreach ( $most as $i => $post ) {
					$cat  = Data::main_category( $post->ID );
					$out .= '<li><span class="wpbot-hc__mono">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a><span class="wpbot-hc__meta">' . esc_html( $cat ? $cat->name : '' ) . '</span></li>';
				}
				$out .= '</ol></section>';
			}
			if ( $recent ) {
				$out .= '<section><h2 class="wpbot-hc__h2s">' . esc_html__( 'Recently updated', 'chatbot' ) . '</h2><ol class="wpbot-hc__rows">';
				foreach ( $recent as $post ) {
					$out .= '<li><time class="wpbot-hc__mono" datetime="' . esc_attr( get_post_modified_time( 'c', true, $post ) ) . '">' . esc_html( get_the_modified_date( 'M j, Y', $post ) ) . '</time><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
				}
				$out .= '</ol></section>';
			}
			$out .= '</div>';
		}
		$out .= '</div>' . $this->contact();
		return $out;
	}

	/**
	 * Category or knowledge base archive.
	 *
	 * @return string
	 */
	private function archive() {
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return $this->home();
		}
		$out  = '<div class="wpbot-hc__' . ( 'simple' === $this->design['layout'] ? 'col' : 'wrap wpbot-hc__page' ) . '">';
		$out .= $this->breadcrumbs( $term );
		$out .= '<h1 class="wpbot-hc__h1 is-page">' . esc_html( $term->name ) . '</h1>';
		if ( '' !== trim( (string) $term->description ) ) {
			$out .= '<p class="wpbot-hc__lede">' . esc_html( $term->description ) . '</p>';
		}
		$out .= $this->term_body( $term );
		return $out . '</div>';
	}

	/**
	 * A term's child categories and articles.
	 *
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	private function term_body( $term ) {
		$out = '';
		if ( Taxonomies::CATEGORY === $term->taxonomy ) {
			$children = Data::categories( $term->term_id );
			if ( $children ) {
				$out .= '<div class="wpbot-hc__cards">';
				foreach ( $children as $child ) {
					$out .= '<a class="wpbot-hc__card" href="' . esc_url( get_term_link( $child ) ) . '"><strong>' . esc_html( $child->name ) . '</strong><span class="wpbot-hc__meta">' . esc_html( $this->count_label( Data::count_in( $child ) ) ) . '</span></a>';
				}
				$out .= '</div>';
			}
			$posts = Data::articles_in( $term->term_id, -1, false );
		} else {
			global $wp_query;
			$posts = $wp_query instanceof \WP_Query ? $wp_query->posts : array();
		}
		if ( $posts ) {
			$out .= '<ul class="wpbot-hc__list">';
			foreach ( $posts as $post ) {
				$out .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '"><span>' . esc_html( get_the_title( $post ) ) . '</span><span aria-hidden="true" class="wpbot-hc__meta">→</span></a></li>';
			}
			$out .= '</ul>';
		} elseif ( '' === $out ) {
			$out .= '<p class="wpbot-hc__meta">' . esc_html__( 'No articles here yet.', 'chatbot' ) . '</p>';
		}
		return $out;
	}

	/**
	 * Single article.
	 *
	 * @return string
	 */
	private function single() {
		$post = get_post();
		if ( ! $post ) {
			return '';
		}
		$content = (string) apply_filters( 'the_content', get_the_content( null, false, $post ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.
		$toc     = Data::toc( $content );
		$simple  = 'simple' === $this->design['layout'];
		$out     = '<div class="wpbot-hc__' . ( $simple ? 'col' : 'wrap wpbot-hc__page' ) . '">' . $this->breadcrumbs( $post );
		$out    .= '<div class="wpbot-hc__article-grid' . ( $simple || ! $toc ? ' is-single' : '' ) . '"><article class="wpbot-hc__article wpbot-help-article" id="wpbot-hc-article">';
		$out    .= $this->article_head( $post, $content );
		if ( $toc ) {
			// Simple: always inline. Help Center and Docs: inline on phones only (the side column is hidden there).
			$out .= $this->toc( $toc, true, ! $simple );
		}
		$out .= '<div class="wpbot-hc__content">' . $content . '</div>';
		ob_start();
		/**
		 * After an article's content (votes and feedback hook in here, HC1-10).
		 *
		 * @param \WP_Post $post Article.
		 */
		do_action( 'qcld_wpbot_help_center/after_article', $post );
		$out .= (string) ob_get_clean();
		$out .= $this->related( $post, $content ) . $this->prev_next( $post ) . $this->still_need_help() . '</article>';
		if ( $toc && ! $simple ) {
			$out .= '<aside class="wpbot-hc__toc-col">' . $this->toc( $toc, false ) . '</aside>';
		}
		return $out . '</div></div>';
	}

	/**
	 * Search results.
	 *
	 * @return string
	 */
	private function search() {
		global $wp_query;
		$query = get_search_query();
		$out   = '<div class="wpbot-hc__' . ( 'simple' === $this->design['layout'] ? 'col' : 'wrap wpbot-hc__page' ) . '">';
		$out  .= $this->breadcrumbs( null, __( 'Search', 'chatbot' ) );
		/* translators: %s: what the visitor searched for. */
		$out .= '<h1 class="wpbot-hc__h1 is-page">' . esc_html( sprintf( __( 'Results for “%s”', 'chatbot' ), $query ) ) . '</h1>';
		$out .= $this->search_form( 'page' );
		ob_start();
		/**
		 * Above the search results (the AI answer hooks in here, HC1-08).
		 *
		 * @param string $query Search.
		 */
		do_action( 'qcld_wpbot_help_center/before_results', $query );
		$out  .= (string) ob_get_clean();
		$found = $wp_query instanceof \WP_Query ? Search::results( $wp_query ) : array(
			'posts'     => array(),
			'total'     => 0,
			'corrected' => '',
		);
		$posts = $found['posts'];
		if ( '' !== $found['corrected'] ) {
			/* translators: %s: the corrected search. */
			$out .= '<p class="wpbot-hc__meta wpbot-hc__corrected">' . esc_html( sprintf( __( 'Showing results for “%s”', 'chatbot' ), $found['corrected'] ) ) . '</p>';
		}
		if ( $posts ) {
			/* translators: %d: number of articles. */
			$out .= '<p class="wpbot-hc__meta">' . esc_html( sprintf( _n( '%d article', '%d articles', (int) $found['total'], 'chatbot' ), (int) $found['total'] ) ) . '</p><ul class="wpbot-hc__results">';
			foreach ( $posts as $post ) {
				$cat  = Data::main_category( $post->ID );
				$out .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>';
				$out .= '<p>' . esc_html( wp_trim_words( has_excerpt( $post ) || ! Access::can_read( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 28 ) ) . '</p>';
				$out .= $cat ? '<span class="wpbot-hc__meta">' . esc_html( $cat->name ) . '</span>' : '';
				$out .= '</li>';
			}
			$out .= '</ul>';
			$out .= get_the_posts_pagination( array( 'mid_size' => 1 ) );
		} else {
			$out .= '<p class="wpbot-hc__lede">' . esc_html__( 'No article matches yet.', 'chatbot' ) . '</p>';
		}
		$out .= $this->cant_find();
		return $out . '</div>';
	}

	/**
	 * Docs layout: knowledge bases and search on top, the tree on the left, the page in the middle.
	 *
	 * @param string $view View.
	 * @return string
	 */
	private function docs( $view ) {
		$current = get_queried_object();
		$out     = '<div class="wpbot-hc__docs-bar">';
		$kbs     = Data::knowledge_bases();
		if ( count( $kbs ) > 1 ) {
			$out .= '<nav class="wpbot-hc__kbs" aria-label="' . esc_attr__( 'Knowledge bases', 'chatbot' ) . '">';
			foreach ( $kbs as $kb ) {
				$on   = $current instanceof \WP_Term && $current->term_id === $kb->term_id;
				$out .= '<a href="' . esc_url( get_term_link( $kb ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $kb->name ) . '</a>';
			}
			$out .= '</nav>';
		}
		$out .= $this->search_form( 'bar' ) . '</div>';
		$out .= '<div class="wpbot-hc__docs-grid"><nav class="wpbot-hc__tree" aria-label="' . esc_attr__( 'Documentation', 'chatbot' ) . '">';
		$out .= '<button type="button" class="wpbot-hc__tree-toggle" aria-expanded="false" hidden>' . esc_html__( 'Browse docs', 'chatbot' ) . '</button>';
		$out .= '<div class="wpbot-hc__tree-body">' . $this->tree( 0, $current instanceof \WP_Post ? $current->ID : 0 ) . $this->glossary_link( 'glossary' === $view ) . '</div></nav>';
		$out .= '<div class="wpbot-hc__docs-main">';
		if ( 'single' === $view ) {
			$out .= $this->single();
		} elseif ( 'search' === $view ) {
			$out .= $this->search();
		} elseif ( 'archive' === $view ) {
			$out .= $this->archive();
		} elseif ( 'glossary' === $view ) {
			$out .= $this->glossary();
		} else {
			$out .= '<div class="wpbot-hc__page"><h1 class="wpbot-hc__h1 is-page">' . esc_html( '' !== $this->design['title'] ? $this->design['title'] : __( 'Documentation', 'chatbot' ) ) . '</h1>';
			$out .= '' !== $this->design['lede'] ? '<p class="wpbot-hc__lede">' . esc_html( $this->design['lede'] ) . '</p>' : '';
			$out .= $this->topics( 5, false ) . '</div>';
		}
		return $out . '</div></div>';
	}

	/**
	 * The docs tree under a parent category.
	 *
	 * @param int $parent_id  Parent term id.
	 * @param int $current_id Current article id.
	 * @return string
	 */
	private function tree( $parent_id, $current_id ) {
		$out = '';
		foreach ( Data::categories( $parent_id ) as $term ) {
			$posts = Data::articles_in( $term->term_id, -1, false );
			$items = '';
			$open  = false;
			foreach ( $posts as $post ) {
				$on     = $post->ID === $current_id;
				$open   = $open || $on;
				$items .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( get_the_title( $post ) ) . '</a></li>';
			}
			$sub  = $this->tree( $term->term_id, $current_id );
			$open = $open || false !== strpos( $sub, 'aria-current' ) || ( get_queried_object() instanceof \WP_Term && get_queried_object()->term_id === $term->term_id );
			$out .= '<details class="wpbot-hc__tree-sec"' . ( $open || ( 0 === $parent_id && '' === $out ) ? ' open' : '' ) . '><summary>' . esc_html( $term->name ) . '</summary>';
			$out .= ( '' !== $items ? '<ul>' . $items . '</ul>' : '' ) . $sub . '</details>';
		}
		return $out;
	}

	/**
	 * Glossary A–Z page (or the list alone, for embeds).
	 *
	 * @param bool $embed Without breadcrumbs and page title.
	 * @return string
	 */
	public function glossary( $embed = false ) {
		$groups = Glossary::group( Glossary::terms() );
		if ( $embed ) {
			$out = '<div class="wpbot-hc__glossary">';
		} else {
			$out  = '<div class="wpbot-hc__' . ( 'simple' === $this->design['layout'] ? 'col' : 'wrap wpbot-hc__page' ) . ' wpbot-hc__glossary">';
			$out .= $this->breadcrumbs( null, __( 'Glossary', 'chatbot' ) );
			$out .= '<h1 class="wpbot-hc__h1 is-page">' . esc_html__( 'Glossary', 'chatbot' ) . '</h1>';
		}
		if ( ! $groups ) {
			return $out . '<p class="wpbot-hc__meta">' . esc_html__( 'No terms yet.', 'chatbot' ) . '</p></div>';
		}
		$letters = array_unique( array_merge( range( 'A', 'Z' ), array_keys( $groups ) ) );
		$out    .= '<nav class="wpbot-hc__az" aria-label="' . esc_attr__( 'Letters', 'chatbot' ) . '"><ul>';
		foreach ( $letters as $letter ) {
			$letter = (string) $letter;
			$out   .= isset( $groups[ $letter ] )
				? '<li><a href="#' . esc_attr( self::letter_id( $letter ) ) . '">' . esc_html( $letter ) . '</a></li>'
				: '<li><span aria-hidden="true">' . esc_html( $letter ) . '</span></li>';
		}
		$out .= '</ul></nav>';
		foreach ( $groups as $letter => $terms ) {
			$id   = self::letter_id( (string) $letter );
			$out .= '<section class="wpbot-hc__az-group" aria-labelledby="' . esc_attr( $id ) . '"><h2 id="' . esc_attr( $id ) . '" class="wpbot-hc__h2s">' . esc_html( (string) $letter ) . '</h2><dl>';
			foreach ( $terms as $term ) {
				$out .= '<dt id="g-' . esc_attr( $term['slug'] ) . '">' . esc_html( $term['name'] ) . '</dt><dd>' . wp_kses_post( wpautop( $term['definition'] ) ) . '</dd>';
			}
			$out .= '</dl></section>';
		}
		return $out . '</div>';
	}

	/**
	 * Id of a letter's heading.
	 *
	 * @param string $letter Letter.
	 * @return string
	 */
	private static function letter_id( $letter ) {
		return 'letter-' . ( '#' === $letter ? 'other' : rawurlencode( strtolower( $letter ) ) );
	}

	/**
	 * Link to the glossary, when it has terms.
	 *
	 * @param bool $current Whether the glossary is the current page.
	 * @return string
	 */
	private function glossary_link( $current = false ) {
		if ( ! Glossary::terms() ) {
			return '';
		}
		return '<p class="wpbot-hc__glossary-link"><a href="' . esc_url( Glossary::url() ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'Glossary', 'chatbot' ) . ' <span aria-hidden="true">→</span></a></p>';
	}

	/**
	 * The search form.
	 *
	 * @param string $variant hero, page, bar, simple or embed.
	 * @return string
	 */
	public function search_form( $variant ) {
		static $used = array();
		$id          = 'wpbot-hc-s-' . $variant;
		// Several forms of one kind on a page (embeds) get their own ids.
		$used[ $id ] = isset( $used[ $id ] ) ? $used[ $id ] + 1 : 1;
		if ( $used[ $id ] > 1 ) {
			$id .= '-' . $used[ $id ];
		}
		$out  = '<form role="search" class="wpbot-hc__search is-' . esc_attr( $variant ) . '" action="' . esc_url( Urls::home() ) . '" method="get"' . ( 'page' !== $variant ? ' data-live' : '' ) . '>';
		$out .= '<label for="' . esc_attr( $id ) . '" class="wpbot-hc__sr">' . esc_html__( 'Search help', 'chatbot' ) . '</label>';
		$out .= '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';
		$out .= '<input id="' . esc_attr( $id ) . '" type="search" name="s" autocomplete="off" value="' . esc_attr( get_search_query() ) . '" placeholder="' . esc_attr__( 'Search help', 'chatbot' ) . '">';
		$out .= '<button type="submit" class="wpbot-hc__sr">' . esc_html__( 'Search', 'chatbot' ) . '</button>';
		$out .= '<div class="wpbot-hc__live" hidden></div><p class="wpbot-hc__sr" role="status" aria-live="polite"></p>';
		return $out . '</form>';
	}

	/**
	 * The assistant card on the home page (shown only when the chatbot is on the page).
	 *
	 * @return string
	 */
	private function assistant_card() {
		$out  = '<aside class="wpbot-hc__assistant" aria-label="' . esc_attr__( 'Assistant', 'chatbot' ) . '" data-needs-chat hidden>';
		$out .= '<p class="wpbot-hc__dotline"><span class="wpbot-hc__dot" aria-hidden="true"></span>' . esc_html__( 'Ask the assistant', 'chatbot' ) . '</p>';
		$out .= '<p class="wpbot-hc__assistant-q">' . esc_html__( 'Ask in your own words. The answer links to the exact part of the article it comes from.', 'chatbot' ) . '</p>';
		return $out . '<button type="button" class="wpbot-hc__btn is-outline" data-ask>' . esc_html__( 'Ask a question →', 'chatbot' ) . '</button></aside>';
	}

	/**
	 * Topics: top-level categories with their first articles.
	 *
	 * @param int  $per    Articles per topic.
	 * @param bool $simple Simple layout markup.
	 * @return string
	 */
	public function topics( $per, $simple ) {
		$cats = Data::categories( 0 );
		if ( ! $cats ) {
			return '<p class="wpbot-hc__meta wpbot-hc__empty">' . esc_html__( 'Articles appear here once they are published.', 'chatbot' ) . '</p>';
		}
		static $count = 0;
		++$count;
		$hid = 'wpbot-hc-topics' . ( $count > 1 ? '-' . $count : '' );
		$out = '<section class="wpbot-hc__topics" aria-labelledby="' . esc_attr( $hid ) . '"><h2 id="' . esc_attr( $hid ) . '" class="' . ( $simple ? 'wpbot-hc__sr' : 'wpbot-hc__h2' ) . '">' . esc_html__( 'Browse topics', 'chatbot' ) . '</h2><div class="wpbot-hc__topic-grid">';
		foreach ( $cats as $i => $term ) {
			$posts = Data::articles_in( $term->term_id, $per + 1, true );
			$count = Data::count_in( $term );
			$out  .= '<div class="wpbot-hc__topic">';
			$out  .= '<h3><span class="wpbot-hc__mono" aria-hidden="true">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span><a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a></h3>';
			$out  .= '' !== trim( (string) $term->description ) ? '<p class="wpbot-hc__topic-desc">' . esc_html( $term->description ) . '</p>' : '';
			$out  .= '<ul>';
			foreach ( array_slice( $posts, 0, $per ) as $post ) {
				$out .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
			}
			$out .= '</ul>';
			if ( $count > $per ) {
				/* translators: %d: number of articles. */
				$out .= '<a class="wpbot-hc__more" href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( sprintf( __( 'All %d articles →', 'chatbot' ), $count ) ) . '</a>';
			}
			$out .= '</div>';
		}
		return $out . '</div></section>';
	}

	/**
	 * Breadcrumbs.
	 *
	 * @param \WP_Post|\WP_Term|null $current Current object.
	 * @param string                 $last   Label of the current page when $current is null.
	 * @return string
	 */
	private function breadcrumbs( $current, $last = '' ) {
		$trail = Data::breadcrumbs( $current );
		if ( null === $current && '' !== $last ) {
			$trail[0][1] = Urls::home();
			$trail[]     = array( $last, '' );
		}
		$parts = array();
		foreach ( $trail as $crumb ) {
			$parts[] = '' !== $crumb[1] ? '<a href="' . esc_url( $crumb[1] ) . '">' . esc_html( $crumb[0] ) . '</a>' : '<span aria-current="page">' . esc_html( $crumb[0] ) . '</span>';
		}
		return '<nav class="wpbot-hc__crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'chatbot' ) . '">' . implode( '<span aria-hidden="true"> / </span>', $parts ) . '</nav>';
	}

	/**
	 * Title, meta and actions of an article.
	 *
	 * @param \WP_Post $post    Article.
	 * @param string   $content Rendered content.
	 * @return string
	 */
	private function article_head( $post, $content ) {
		$version = trim( (string) get_post_meta( $post->ID, '_wpbot_product_version', true ) );
		$minutes = Data::reading_minutes( $content );
		$out     = '<header class="wpbot-hc__article-head"><h1 class="wpbot-hc__h1 is-article">' . esc_html( get_the_title( $post ) ) . '</h1><p class="wpbot-hc__article-meta">';
		/* translators: %s: time since the article was updated, for example "2 days". */
		$out .= '<time datetime="' . esc_attr( get_post_modified_time( 'c', true, $post ) ) . '">' . esc_html( sprintf( __( 'Updated %s ago', 'chatbot' ), human_time_diff( (int) get_post_modified_time( 'U', true, $post ), time() ) ) ) . '</time>';
		/* translators: %d: minutes. */
		$out .= '<span>' . esc_html( sprintf( _n( '%d min read', '%d min read', $minutes, 'chatbot' ), $minutes ) ) . '</span>';
		/* translators: %s: product version. */
		$out .= '' !== $version ? '<span class="wpbot-hc__pill">' . esc_html( sprintf( __( 'Applies to %s', 'chatbot' ), $version ) ) . '</span>' : '';
		$out .= '</p><div class="wpbot-hc__actions">';
		$out .= '<button type="button" class="wpbot-hc__btn is-solid" data-ask data-needs-chat hidden>' . esc_html__( 'Ask about this article', 'chatbot' ) . '</button>';
		$out .= '<button type="button" class="wpbot-hc__btn is-outline" data-print>' . esc_html__( 'Print', 'chatbot' ) . '</button>';
		$out .= '<button type="button" class="wpbot-hc__btn is-outline" data-copy-link data-done="' . esc_attr__( 'Link copied', 'chatbot' ) . '">' . esc_html__( 'Copy link', 'chatbot' ) . '</button>';
		return $out . '</div></header>';
	}

	/**
	 * Table of contents.
	 *
	 * @param array[] $toc        Entries.
	 * @param bool    $inline     Inline (collapsible) instead of the side column.
	 * @param bool    $phone_only Inline box shown on phones only.
	 * @return string
	 */
	public function toc( array $toc, $inline, $phone_only = false ) {
		$items = '';
		foreach ( $toc as $entry ) {
			$items .= '<li class="is-h' . (int) $entry['level'] . '"><a href="#' . esc_attr( $entry['id'] ) . '">' . esc_html( $entry['text'] ) . '</a></li>';
		}
		$label = esc_html__( 'On this page', 'chatbot' );
		if ( $inline ) {
			return '<details class="wpbot-hc__toc is-inline' . ( $phone_only ? ' is-phone' : '' ) . '"><summary>' . $label . '</summary><ul>' . $items . '</ul></details>';
		}
		return '<nav class="wpbot-hc__toc" aria-label="' . esc_attr( $label ) . '"><p class="wpbot-hc__label">' . $label . '</p><ul>' . $items . '</ul>'
			. '<p class="wpbot-hc__toc-ask" data-needs-chat hidden><button type="button" class="wpbot-hc__link" data-ask>' . esc_html__( 'Ask AI about this page', 'chatbot' ) . '</button></p></nav>';
	}

	/**
	 * Related articles (unless the article already has the Related articles block).
	 *
	 * @param \WP_Post $post    Article.
	 * @param string   $content Rendered content.
	 * @return string
	 */
	private function related( $post, $content ) {
		if ( false !== strpos( $content, 'wpbot-help-related' ) ) {
			return '';
		}
		$ids = Blocks::related_ids( $post->ID, 4 );
		if ( ! $ids ) {
			return '';
		}
		$out = '<section class="wpbot-hc__related" aria-labelledby="wpbot-hc-related"><h2 id="wpbot-hc-related" class="wpbot-hc__h2s">' . esc_html__( 'Related articles', 'chatbot' ) . '</h2><div class="wpbot-hc__cards">';
		foreach ( $ids as $id ) {
			$cat  = Data::main_category( $id );
			$out .= '<a class="wpbot-hc__card" href="' . esc_url( get_permalink( $id ) ) . '"><strong>' . esc_html( get_the_title( $id ) ) . '</strong><span class="wpbot-hc__meta">' . esc_html( $cat ? $cat->name : '' ) . '</span></a>';
		}
		return $out . '</div></section>';
	}

	/**
	 * Previous and next links.
	 *
	 * @param \WP_Post $post Article.
	 * @return string
	 */
	private function prev_next( $post ) {
		list( $prev, $next ) = Data::prev_next( $post );
		if ( ! $prev && ! $next ) {
			return '';
		}
		$out  = '<nav class="wpbot-hc__prevnext" aria-label="' . esc_attr__( 'Previous and next', 'chatbot' ) . '">';
		$out .= $prev ? '<a href="' . esc_url( get_permalink( $prev ) ) . '"><span class="wpbot-hc__label">' . esc_html__( '← Previous', 'chatbot' ) . '</span>' . esc_html( get_the_title( $prev ) ) . '</a>' : '<span></span>';
		$out .= $next ? '<a class="is-next" href="' . esc_url( get_permalink( $next ) ) . '"><span class="wpbot-hc__label">' . esc_html__( 'Next →', 'chatbot' ) . '</span>' . esc_html( get_the_title( $next ) ) . '</a>' : '';
		return $out . '</nav>';
	}

	/**
	 * The contact strip.
	 *
	 * @return string
	 */
	private function contact() {
		$cells   = array();
		$cells[] = '<div data-needs-chat hidden><h2>' . esc_html__( 'Ask the assistant', 'chatbot' ) . '</h2><p>' . esc_html__( 'Answers in seconds, with links to the articles.', 'chatbot' ) . '</p><button type="button" class="wpbot-hc__btn is-solid" data-ask>' . esc_html__( 'Ask the assistant', 'chatbot' ) . '</button></div>';
		if ( '' !== $this->design['contact_url'] ) {
			$cells[] = '<div><h2>' . esc_html__( 'Contact us', 'chatbot' ) . '</h2><p>' . esc_html__( 'Still stuck? Our team will help.', 'chatbot' ) . '</p><a class="wpbot-hc__btn is-outline" href="' . esc_url( $this->design['contact_url'] ) . '">' . esc_html__( 'Contact us', 'chatbot' ) . '</a></div>';
		}
		/**
		 * Extra cells for the contact strip (for example a ticket form or live chat), as HTML.
		 *
		 * @param string[] $cells Cells.
		 */
		$cells = (array) apply_filters( 'qcld_wpbot_help_center/contact_cells', $cells );
		return '<section class="wpbot-hc__contact" id="contact" aria-label="' . esc_attr__( 'Contact', 'chatbot' ) . '"><div class="wpbot-hc__wrap wpbot-hc__contact-grid">' . implode( '', $cells ) . '</div></section>';
	}

	/**
	 * "Still need help?" under an article: the assistant (with the article as context) and the contact address.
	 *
	 * @return string
	 */
	private function still_need_help() {
		$contact = $this->design['contact_url'];
		$out     = '<aside class="wpbot-hc__still" aria-label="' . esc_attr__( 'Still need help?', 'chatbot' ) . '"' . ( '' === $contact ? ' data-needs-chat hidden' : '' ) . '>';
		$out    .= '<p><strong>' . esc_html__( 'Still need help?', 'chatbot' ) . '</strong> <span>' . esc_html__( 'The assistant knows this article and the rest of our help center.', 'chatbot' ) . '</span></p><div class="wpbot-hc__fb-buttons">';
		$out    .= '<button type="button" class="wpbot-hc__btn is-solid" data-ask data-needs-chat hidden>' . esc_html__( 'Ask the assistant', 'chatbot' ) . '</button>';
		if ( '' !== $contact ) {
			$out .= '<a class="wpbot-hc__btn is-outline" href="' . esc_url( $contact ) . '">' . esc_html__( 'Contact us', 'chatbot' ) . '</a>';
		}
		return $out . '</div></aside>';
	}

	/**
	 * "Can't find it?" line.
	 *
	 * @return string
	 */
	private function cant_find() {
		// Nothing to offer without the chatbot or a contact address: the script shows the line when the chatbot is there.
		$out  = '<p class="wpbot-hc__cant"' . ( '' === $this->design['contact_url'] ? ' data-needs-chat hidden' : '' ) . '><span class="wpbot-hc__dot" aria-hidden="true"></span>' . esc_html__( 'Can\'t find it?', 'chatbot' ) . ' ';
		$out .= '<button type="button" class="wpbot-hc__link" data-ask data-needs-chat hidden>' . esc_html__( 'Ask the assistant', 'chatbot' ) . '</button>';
		if ( '' !== $this->design['contact_url'] ) {
			$out .= ' <a href="' . esc_url( $this->design['contact_url'] ) . '">' . esc_html__( 'Contact us', 'chatbot' ) . '</a>';
		}
		return $out . '</p>';
	}

	/**
	 * "N articles".
	 *
	 * @param int $count Count.
	 * @return string
	 */
	private function count_label( $count ) {
		/* translators: %d: number of articles. */
		return sprintf( _n( '%d article', '%d articles', (int) $count, 'chatbot' ), (int) $count );
	}
}
