<?php
/**
 * Help Center → Analytics (HC1-14).
 *
 * For a period (7, 30, 90 days or a year): views, helpful rate, searches and
 * searches with no result, the questions the chatbot could not answer (open
 * gaps in Train), most read and least helpful articles (with the reasons
 * visitors gave), most searched and searches with no result (with a link that
 * starts an article titled with the search).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Analytics\Stats;
use QuantumCloud\HelpCenter\Mcp\Docs_Server;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Feedback\Votes;
use QuantumCloud\HelpCenter\Permissions;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics screen.
 */
final class Analytics_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'wpbot-help-center-analytics';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
	}

	/**
	 * Add the page.
	 *
	 * @return void
	 */
	public static function menu() {
		$hook = add_submenu_page( 'edit.php?post_type=' . Post_Type::NAME, __( 'Help Center analytics', 'chatbot' ), __( 'Analytics', 'chatbot' ), Permissions::cap( 'view' ), self::SLUG, array( self::class, 'render' ), 70 );
		if ( $hook ) {
			add_action( 'admin_print_styles-' . $hook, array( self::class, 'styles' ) );
		}
	}

	/**
	 * Styles.
	 *
	 * @return void
	 */
	public static function styles() {
		$file    = ( defined( 'QCLD_WPBOT_HELP_CENTER_DIR' ) ? QCLD_WPBOT_HELP_CENTER_DIR : dirname( __DIR__, 2 ) ) . '/help-center.php';
		$version = defined( 'QCLD_WPBOT_HELP_CENTER_VERSION' ) ? QCLD_WPBOT_HELP_CENTER_VERSION : '0';
		wp_enqueue_style( 'wpbot-help-admin', plugins_url( 'assets/admin.css', $file ), array(), $version );
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only period choice.
		$days   = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		$days   = in_array( $days, array( 7, 30, 90, 365 ), true ) ? $days : 30;
		$report = Stats::report( $days );
		$t      = $report['totals'];
		$base   = admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG );

		echo '<div class="wrap wpbot-hc-analytics"><h1>' . esc_html__( 'Help Center analytics', 'chatbot' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Period', 'chatbot' ) . '">';
		foreach ( array(
			7   => __( 'Last 7 days', 'chatbot' ),
			30  => __( 'Last 30 days', 'chatbot' ),
			90  => __( 'Last 90 days', 'chatbot' ),
			365 => __( 'Last year', 'chatbot' ),
		) as $value => $label ) {
			$on = $value === $days;
			echo '<a class="nav-tab' . ( $on ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'days', $value, $base ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		$cards = array(
			array( __( 'Article views', 'chatbot' ), number_format_i18n( $t['views'] ), '' ),
			array(
				__( 'Helpful', 'chatbot' ),
				null === $t['rate'] ? '—' : $t['rate'] . '%',
				/* translators: 1: yes votes, 2: no votes. */
				sprintf( __( '%1$s yes · %2$s no', 'chatbot' ), number_format_i18n( $t['yes'] ), number_format_i18n( $t['no'] ) ),
			),
			array( __( 'Searches', 'chatbot' ), number_format_i18n( $t['searches'] ), '' ),
			array(
				__( 'Searches with no result', 'chatbot' ),
				number_format_i18n( $t['empty'] ),
				/* translators: %d: percent of searches. */
				$t['searches'] > 0 ? sprintf( __( '%d%% of searches', 'chatbot' ), (int) round( 100 * $t['empty'] / $t['searches'] ) ) : '',
			),
			array( __( 'Questions the chatbot could not answer', 'chatbot' ), number_format_i18n( $report['gaps'] ), __( 'Open in Knowledge Gaps', 'chatbot' ) ),
		);
		echo '<ul class="wpbot-hc-analytics__cards">';
		foreach ( $cards as $i => $card ) {
			$note = $card[2];
			if ( 4 === $i && class_exists( '\QuantumCloud\Chatbot\Admin\Train_Page' ) ) {
				$note = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . Admin::GAPS_SLUG ) ) . '">' . esc_html( $note ) . '</a>';
			} else {
				$note = esc_html( 4 === $i ? '' : $note );
			}
			echo '<li><span class="wpbot-hc-analytics__label">' . esc_html( $card[0] ) . '</span><strong>' . esc_html( $card[1] ) . '</strong><span class="wpbot-hc-analytics__note">' . $note . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $note escaped above.
		}
		echo '</ul>';

		echo self::chart( $report['daily'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in chart().

		echo '<div class="wpbot-hc-analytics__grid">';
		self::table(
			__( 'Most read', 'chatbot' ),
			array( __( 'Article', 'chatbot' ), __( 'Views', 'chatbot' ), __( 'Helpful', 'chatbot' ) ),
			array_map(
				static function ( $row ) {
					$rate = Stats::rate( (int) $row['yes'], (int) $row['no'] );
					return array( self::article_link( (int) $row['post_id'] ), esc_html( number_format_i18n( (int) $row['views'] ) ), esc_html( null === $rate ? '—' : $rate . '%' ) );
				},
				$report['top']
			),
			__( 'No views in this period yet.', 'chatbot' )
		);
		self::table(
			__( 'Least helpful (3 votes or more)', 'chatbot' ),
			array( __( 'Article', 'chatbot' ), __( 'Helpful', 'chatbot' ), __( 'Reasons given', 'chatbot' ) ),
			array_map(
				static function ( $row ) {
					$rate = Stats::rate( (int) $row['yes'], (int) $row['no'] );
					return array( self::article_link( (int) $row['post_id'] ), esc_html( $rate . '% (' . (int) $row['yes'] . '/' . ( (int) $row['yes'] + (int) $row['no'] ) . ')' ), esc_html( self::reasons( (int) $row['post_id'] ) ) );
				},
				$report['unhelpful']
			),
			__( 'No article has three or more votes with a "no" in this period.', 'chatbot' )
		);
		self::table(
			__( 'Most searched', 'chatbot' ),
			array( __( 'Search', 'chatbot' ), __( 'Times', 'chatbot' ), __( 'Results', 'chatbot' ) ),
			array_map(
				static function ( $row ) {
					return array( esc_html( (string) $row['search'] ), esc_html( number_format_i18n( (int) $row['times'] ) ), esc_html( number_format_i18n( (int) $row['results'] ) ) );
				},
				$report['searches']
			),
			__( 'No searches in this period yet.', 'chatbot' )
		);
		$can_write = current_user_can( get_post_type_object( Post_Type::NAME )->cap->edit_posts );
		self::table(
			__( 'Searches with no result', 'chatbot' ),
			array( __( 'Search', 'chatbot' ), __( 'Times', 'chatbot' ), '' ),
			array_map(
				static function ( $row ) use ( $can_write ) {
					$write = $can_write ? '<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . Post_Type::NAME . '&post_title=' . rawurlencode( (string) $row['search'] ) ) ) . '">' . esc_html__( 'Write an article', 'chatbot' ) . '</a>' : '';
					return array( esc_html( (string) $row['search'] ), esc_html( number_format_i18n( (int) $row['times'] ) ), $write );
				},
				$report['no_results']
			),
			__( 'Every search found something in this period.', 'chatbot' )
		);
		echo '</div>';
		self::ai_section( $report['ai'], $can_write );
		echo '</div>';
	}

	/**
	 * Searches and reads from customers' AI assistants through the public docs server (0.19.1).
	 * Shown while the docs server is on, or when the period has any of its traffic.
	 *
	 * @param array $ai        The report's "ai" part.
	 * @param bool  $can_write Whether the user may write articles.
	 * @return void
	 */
	private static function ai_section( array $ai, $can_write ) {
		if ( ! Docs_Server::enabled() && 0 === $ai['searches'] + $ai['reads'] ) {
			return;
		}
		echo '<h2 class="wpbot-hc-analytics__section" id="hc-analytics-ai">' . esc_html__( 'From AI assistants', 'chatbot' ) . '</h2>';
		echo '<p class="description">' . esc_html__( "Searches and article reads from your customers' AI assistants (Claude, ChatGPT and others) through the public docs server.", 'chatbot' ) . '</p>';
		$cards = array(
			array( __( 'Searches', 'chatbot' ), $ai['searches'] ),
			array( __( 'Searches with no result', 'chatbot' ), $ai['empty'] ),
			array( __( 'Articles read', 'chatbot' ), $ai['reads'] ),
		);
		echo '<ul class="wpbot-hc-analytics__cards">';
		foreach ( $cards as $card ) {
			echo '<li><span class="wpbot-hc-analytics__label">' . esc_html( $card[0] ) . '</span><strong>' . esc_html( number_format_i18n( (int) $card[1] ) ) . '</strong><span class="wpbot-hc-analytics__note"></span></li>';
		}
		echo '</ul><div class="wpbot-hc-analytics__grid">';
		self::table(
			__( 'Most searched by AI assistants', 'chatbot' ),
			array( __( 'Search', 'chatbot' ), __( 'Times', 'chatbot' ), __( 'Results', 'chatbot' ) ),
			array_map(
				static function ( $row ) {
					return array( esc_html( (string) $row['search'] ), esc_html( number_format_i18n( (int) $row['times'] ) ), esc_html( number_format_i18n( (int) $row['results'] ) ) );
				},
				$ai['top']
			),
			__( 'No searches from AI assistants in this period yet.', 'chatbot' )
		);
		self::table(
			__( 'AI assistant searches with no result', 'chatbot' ),
			array( __( 'Search', 'chatbot' ), __( 'Times', 'chatbot' ), '' ),
			array_map(
				static function ( $row ) use ( $can_write ) {
					$write = $can_write ? '<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . Post_Type::NAME . '&post_title=' . rawurlencode( (string) $row['search'] ) ) ) . '">' . esc_html__( 'Write an article', 'chatbot' ) . '</a>' : '';
					return array( esc_html( (string) $row['search'] ), esc_html( number_format_i18n( (int) $row['times'] ) ), $write );
				},
				$ai['no_results']
			),
			__( 'Every AI assistant search found something in this period.', 'chatbot' )
		);
		self::table(
			__( 'Most read by AI assistants', 'chatbot' ),
			array( __( 'Article', 'chatbot' ), __( 'Reads', 'chatbot' ) ),
			array_map(
				static function ( $row ) {
					return array( self::article_link( (int) $row['post_id'] ), esc_html( number_format_i18n( (int) $row['times_read'] ) ) );
				},
				$ai['read']
			),
			__( 'No AI assistant read an article in this period yet.', 'chatbot' )
		);
		echo '</div>';
	}

	/**
	 * Daily views as a bar chart, with the numbers in a table for screen readers.
	 *
	 * @param array<string,int> $series Day => views.
	 * @return string
	 */
	public static function chart( array $series ) {
		if ( ! $series ) {
			return '';
		}
		$max   = max( 1, max( $series ) );
		$count = count( $series );
		$width = 720;
		$gap   = $count > 60 ? 0 : 2;
		$slot  = ( $width - $gap * ( $count - 1 ) ) / $count;
		$bar   = max( 1, min( 24, $slot ) );
		$total = array_sum( $series );
		/* translators: 1: total views, 2: number of days, 3: busiest day's views. */
		$label = sprintf( __( 'Article views per day: %1$s in %2$d days, at most %3$s on one day.', 'chatbot' ), number_format_i18n( $total ), $count, number_format_i18n( $max ) );
		$svg   = '<svg class="wpbot-hc-analytics__chart" viewBox="0 0 ' . $width . ' 120" preserveAspectRatio="none" role="img" aria-label="' . esc_attr( $label ) . '"><title>' . esc_html( $label ) . '</title>';
		$i     = 0;
		foreach ( $series as $day => $views ) {
			$h    = $views > 0 ? max( 2, round( 116 * $views / $max ) ) : 0;
			$svg .= '<rect x="' . esc_attr( (string) round( $i * ( $slot + $gap ) + ( $slot - $bar ) / 2, 2 ) ) . '" y="' . esc_attr( (string) ( 120 - $h ) ) . '" width="' . esc_attr( (string) round( $bar, 2 ) ) . '" height="' . esc_attr( (string) $h ) . '"><title>' . esc_html( $day . ': ' . number_format_i18n( $views ) ) . '</title></rect>';
			++$i;
		}
		$svg  .= '<rect class="is-base" x="0" y="119" width="' . $width . '" height="1"/></svg>';
		$table = '<details class="wpbot-hc-analytics__days"><summary>' . esc_html__( 'Views per day', 'chatbot' ) . '</summary><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Day', 'chatbot' ) . '</th><th scope="col">' . esc_html__( 'Views', 'chatbot' ) . '</th></tr></thead><tbody>';
		foreach ( array_reverse( $series, true ) as $day => $views ) {
			$table .= '<tr><td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $day . ' 12:00:00' ) ) ) . '</td><td>' . esc_html( number_format_i18n( $views ) ) . '</td></tr>';
		}
		return '<section class="wpbot-hc-analytics__panel"><h2>' . esc_html__( 'Views per day', 'chatbot' ) . '</h2>' . $svg . $table . '</tbody></table></details></section>';
	}

	/**
	 * A report table.
	 *
	 * @param string   $title   Heading.
	 * @param string[] $columns Column headings.
	 * @param array[]  $rows    Cells (escaped HTML).
	 * @param string   $none    Text when there are no rows.
	 * @return void
	 */
	private static function table( $title, array $columns, array $rows, $none ) {
		echo '<section class="wpbot-hc-analytics__panel"><h2>' . esc_html( $title ) . '</h2>';
		if ( ! $rows ) {
			echo '<p class="description">' . esc_html( $none ) . '</p></section>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( $columns as $column ) {
			echo '<th scope="col">' . ( '' !== $column ? esc_html( $column ) : '<span class="screen-reader-text">' . esc_html__( 'Actions', 'chatbot' ) . '</span>' ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $cells ) {
			echo '<tr><td>' . implode( '</td><td>', $cells ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cells are escaped by the callers.
		}
		echo '</tbody></table></section>';
	}

	/**
	 * Link to an article's editor (or its title).
	 *
	 * @param int $post_id Article id.
	 * @return string
	 */
	private static function article_link( $post_id ) {
		$title = get_the_title( $post_id );
		$title = '' !== $title ? $title : __( '(deleted article)', 'chatbot' );
		$edit  = get_edit_post_link( $post_id );
		return $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title );
	}

	/**
	 * Reasons visitors gave for "no" (all time), most given first.
	 *
	 * @param int $post_id Article id.
	 * @return string
	 */
	private static function reasons( $post_id ) {
		$counts = get_post_meta( $post_id, '_wpbot_helpful_reasons', true );
		if ( ! is_array( $counts ) || ! $counts ) {
			return '—';
		}
		arsort( $counts );
		$labels = Votes::reasons();
		$parts  = array();
		foreach ( $counts as $key => $n ) {
			$parts[] = ( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ) . ' (' . (int) $n . ')';
		}
		return implode( ', ', $parts );
	}
}
