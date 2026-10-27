<?php
/**
 * Turns one visit into a short text log for the AI: pages, clicks, fields
 * used (never their values), frustration signals and errors.
 *
 * Raw rrweb data is never sent. Page text is already masked by the
 * recorder; emails, phone numbers and card-like numbers are redacted again
 * here, and URL query strings are dropped.
 *
 * Example:
 *     Visit · mobile · iOS · Safari · from google.com · new visitor · 4m 12s · 2 pages
 *     00:00 view /pricing "Pricing – Acme" · stayed 1m 10s · scrolled 85%
 *     00:31 click button "Compare plans"
 *     00:44 rage-click button#apply-coupon (4 clicks)
 *     00:52 js-error "Cannot read properties of null" in checkout.js
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

use QuantumCloud\Insights\Storage\Recording_Events;
use QuantumCloud\Insights\Storage\Schema;
use QuantumCloud\Insights\Storage\Session_Repository;
use QuantumCloud\Insights\Storage\Signal_Repository;

defined( 'ABSPATH' ) || exit;

final class Digest_Builder {

	const MAX_CHARS          = 8000; // About 2,000 tokens.
	const MAX_LINES_PER_PAGE = 25;
	const MAX_RECORDED_PAGES = 15;

	const INTERACTIVE = array( 'a', 'button', 'input', 'select', 'textarea', 'label', 'summary', 'option' );
	const ROLES       = array( 'button', 'link', 'tab', 'menuitem', 'checkbox', 'radio', 'switch', 'option' );

	/**
	 * Build the digest of a session.
	 *
	 * @param object $session Session row.
	 * @return array{text:string,hash:string,pages:int,lines:int}
	 */
	public function build( $session ) {
		$repo      = new Session_Repository();
		$pageviews = $repo->pageviews( (int) $session->id );
		$signals   = ( new Signal_Repository() )->for_session( (int) $session->id );
		$start     = strtotime( $session->started_at . ' UTC' );

		$by_pv = array();
		foreach ( $signals as $sig ) {
			$by_pv[ $sig['pageview'] ][] = $sig;
		}

		$lines    = array();
		$recorded = 0;
		$last_end = 0;
		$last_url = '';
		foreach ( $pageviews as $pv ) {
			$offset = max( 0, strtotime( $pv->started_at . ' UTC' ) - $start );
			$page   = array();

			$view = sprintf( '%s view %s', self::clock( $offset ), self::path( (string) $pv->url ) );
			if ( '' !== trim( (string) $pv->title ) ) {
				$view .= ' "' . self::short( (string) $pv->title, 80 ) . '"';
			}
			$view .= ' · ' . sprintf( 'stayed %s', self::duration( (int) $pv->duration_ms / 1000 ) );
			$view .= ' · ' . sprintf( 'scrolled %d%%', (int) $pv->max_scroll_pct );
			$lines[] = array( $offset * 1000, $view );

			$events = array();
			if ( (int) $pv->chunk_count > 0 && $recorded < self::MAX_RECORDED_PAGES ) {
				++$recorded;
				foreach ( $this->interactions( Recording_Events::load( $session->uuid, $pv->uuid ) ) as $item ) {
					$events[] = array( $offset * 1000 + $item[0], $item[1] );
				}
			} else {
				$events = $this->clicks_without_recording( (int) $pv->id, $offset );
			}

			foreach ( $by_pv[ $pv->uuid ] ?? array() as $sig ) {
				$line = $this->signal_line( $sig );
				if ( '' !== $line ) {
					$events[] = array( $offset * 1000 + (int) $sig['ts'], $line );
				}
			}

			usort(
				$events,
				static function ( $a, $b ) {
					return $a[0] <=> $b[0];
				}
			);
			$events = self::collapse( $events );
			if ( count( $events ) > self::MAX_LINES_PER_PAGE ) {
				$more   = count( $events ) - self::MAX_LINES_PER_PAGE;
				$events = array_slice( $events, 0, self::MAX_LINES_PER_PAGE );
				/* Kept in English: the digest is for the AI, not for people. */
				$events[] = array( end( $events )[0], sprintf( '… %d more actions on this page', $more ) );
			}
			foreach ( $events as $e ) {
				$lines[] = array( $e[0], self::clock( (int) floor( $e[0] / 1000 ) ) . ' ' . $e[1] );
			}

			$last_end = max( $last_end, $offset + (int) round( (int) $pv->duration_ms / 1000 ) );
			$last_url = self::path( (string) $pv->url );
		}

		$total = max( (int) $session->duration_s, $last_end );
		if ( 0 !== (int) $session->status ) {
			$lines[] = array( $total * 1000, self::clock( $total ) . ' visit ends on ' . ( '' !== $last_url ? $last_url : 'the last page' ) );
		} else {
			$lines[] = array( $total * 1000, self::clock( $total ) . ' visit still in progress' );
		}

		$text = $this->header( $session, $total, count( $pageviews ) ) . "\n" . $this->fit( array_column( $lines, 1 ) );
		$text = self::redact( $text );

		return array(
			'text'  => $text,
			'hash'  => md5( $text ),
			'pages' => count( $pageviews ),
			'lines' => substr_count( $text, "\n" ) + 1,
		);
	}

	/**
	 * First line: device, browser, source, visitor type, length.
	 *
	 * @param object $s     Session row.
	 * @param int    $total Seconds.
	 * @param int    $pages Page views.
	 * @return string
	 */
	private function header( $s, $total, $pages ) {
		$parts = array( 'Visit' );
		foreach ( array( $s->device, $s->os, $s->browser ) as $v ) {
			if ( '' !== (string) $v ) {
				$parts[] = (string) $v;
			}
		}
		if ( '' !== (string) $s->country ) {
			$parts[] = 'country ' . $s->country;
		}
		$source = '' !== (string) $s->referrer_host ? (string) $s->referrer_host : (string) $s->utm_source;
		$parts[] = '' !== $source ? 'from ' . $source : 'direct visit';
		if ( '' !== (string) $s->utm_campaign ) {
			$parts[] = 'campaign ' . $s->utm_campaign;
		}
		$parts[] = (int) $s->is_returning ? 'returning visitor' : 'new visitor';
		$parts[] = self::duration( $total );
		$parts[] = 1 === $pages ? '1 page' : $pages . ' pages';
		return implode( ' · ', $parts );
	}

	/**
	 * Clicks and field use from rrweb events, as [ms from page start, text].
	 *
	 * @param array<int,array<string,mixed>> $events Events.
	 * @return array<int,array{0:int,1:string}>
	 */
	private function interactions( array $events ) {
		if ( ! $events ) {
			return array();
		}
		$first = (int) ( $events[0]['timestamp'] ?? 0 );
		$nodes = array();
		$out   = array();
		$typed = array();

		foreach ( $events as $e ) {
			$type = (int) ( $e['type'] ?? -1 );
			$data = isset( $e['data'] ) && is_array( $e['data'] ) ? $e['data'] : array();

			if ( 2 === $type && isset( $data['node'] ) ) {
				$nodes = array();
				$this->index( $data['node'], 0, $nodes );
				continue;
			}
			if ( 3 !== $type ) {
				continue;
			}
			$source = (int) ( $data['source'] ?? -1 );
			$at     = max( 0, (int) ( $e['timestamp'] ?? $first ) - $first );

			if ( 0 === $source ) {
				foreach ( (array) ( $data['adds'] ?? array() ) as $add ) {
					if ( isset( $add['node'] ) && is_array( $add['node'] ) ) {
						$this->index( $add['node'], (int) ( $add['parentId'] ?? 0 ), $nodes );
					}
				}
				foreach ( (array) ( $data['texts'] ?? array() ) as $t ) {
					if ( isset( $t['id'], $nodes[ $t['id'] ] ) ) {
						$nodes[ $t['id'] ]['text'] = (string) ( $t['value'] ?? '' );
					}
				}
			} elseif ( 2 === $source && 2 === (int) ( $data['type'] ?? -1 ) ) {
				$label = $this->describe( (int) ( $data['id'] ?? 0 ), $nodes );
				$out[] = array( $at, 'click ' . ( '' !== $label ? $label : 'on the page' ) );
			} elseif ( 5 === $source ) {
				$id = (int) ( $data['id'] ?? 0 );
				if ( isset( $typed[ $id ] ) ) {
					continue;
				}
				$typed[ $id ] = true;
				$field        = $this->field( $id, $nodes );
				$out[]        = array( $at, 'filled in ' . ( '' !== $field ? $field : 'a form field' ) . ' (value hidden)' );
			}
		}
		return $out;
	}

	/**
	 * Index a serialized rrweb node tree by id.
	 *
	 * @param array<string,mixed>           $node   Node.
	 * @param int                           $parent Parent id.
	 * @param array<int,array<string,mixed>> $nodes  Index (by reference).
	 */
	private function index( array $node, $parent, array &$nodes ) {
		$stack = array( array( $node, $parent ) );
		$count = 0;
		while ( $stack && $count < 50000 ) {
			list( $n, $pid ) = array_pop( $stack );
			if ( ! isset( $n['id'] ) ) {
				continue;
			}
			++$count;
			$id           = (int) $n['id'];
			$nodes[ $id ] = array(
				'type'     => (int) ( $n['type'] ?? 0 ),
				'tag'      => strtolower( (string) ( $n['tagName'] ?? '' ) ),
				'attrs'    => isset( $n['attributes'] ) && is_array( $n['attributes'] ) ? $n['attributes'] : array(),
				'text'     => 3 === (int) ( $n['type'] ?? 0 ) ? (string) ( $n['textContent'] ?? '' ) : '',
				'parent'   => (int) $pid,
				'children' => array(),
			);
			if ( $pid && isset( $nodes[ $pid ] ) ) {
				$nodes[ $pid ]['children'][] = $id;
			}
			$children = isset( $n['childNodes'] ) && is_array( $n['childNodes'] ) ? $n['childNodes'] : array();
			for ( $i = count( $children ) - 1; $i >= 0; $i-- ) {
				if ( is_array( $children[ $i ] ) ) {
					$stack[] = array( $children[ $i ], $id );
				}
			}
		}
	}

	/**
	 * Describe a clicked element: role, visible label and where a link goes.
	 *
	 * @param int                            $id    Node id.
	 * @param array<int,array<string,mixed>> $nodes Index.
	 * @return string
	 */
	private function describe( $id, array $nodes ) {
		if ( ! isset( $nodes[ $id ] ) ) {
			return '';
		}
		if ( 2 !== $nodes[ $id ]['type'] && $nodes[ $id ]['parent'] ) {
			$id = $nodes[ $id ]['parent'];
		}
		$target = $id;
		$cur    = $id;
		for ( $i = 0; $i < 5 && isset( $nodes[ $cur ] ); $i++ ) {
			$n    = $nodes[ $cur ];
			$role = isset( $n['attrs']['role'] ) ? (string) $n['attrs']['role'] : '';
			if ( in_array( $n['tag'], self::INTERACTIVE, true ) || in_array( $role, self::ROLES, true ) ) {
				$target = $cur;
				break;
			}
			if ( ! $n['parent'] ) {
				break;
			}
			$cur = $n['parent'];
		}
		if ( ! isset( $nodes[ $target ] ) ) {
			return '';
		}
		$n     = $nodes[ $target ];
		$kind  = $this->kind( $n );
		$label = $this->label( $target, $nodes );
		$out   = $kind;
		if ( '' !== $label ) {
			$out .= ' "' . $label . '"';
		} else {
			$out .= self::css_hint( $n );
		}
		if ( 'a' === $n['tag'] && isset( $n['attrs']['href'] ) ) {
			$href = self::link_target( (string) $n['attrs']['href'] );
			if ( '' !== $href ) {
				$out .= ' → ' . $href;
			}
		}
		return $out;
	}

	/**
	 * Name of a form field without its value.
	 *
	 * @param int                            $id    Node id.
	 * @param array<int,array<string,mixed>> $nodes Index.
	 * @return string
	 */
	private function field( $id, array $nodes ) {
		if ( ! isset( $nodes[ $id ] ) ) {
			return '';
		}
		$n     = $nodes[ $id ];
		$a     = $n['attrs'];
		$type  = isset( $a['type'] ) ? strtolower( (string) $a['type'] ) : '';
		$name  = '';
		foreach ( array( 'aria-label', 'placeholder', 'name', 'id' ) as $attr ) {
			if ( isset( $a[ $attr ] ) && is_string( $a[ $attr ] ) && '' !== trim( $a[ $attr ] ) && ! self::masked( $a[ $attr ] ) ) {
				$name = self::short( $a[ $attr ], 40 );
				break;
			}
		}
		$what = 'select' === $n['tag'] ? 'dropdown' : ( in_array( $type, array( 'checkbox', 'radio' ), true ) ? $type : 'field' );
		return $what . ( '' !== $name ? ' "' . $name . '"' : '' ) . ( '' !== $type && 'field' === $what ? ' (' . $type . ')' : '' );
	}

	/**
	 * Short element kind.
	 *
	 * @param array<string,mixed> $n Node.
	 * @return string
	 */
	private function kind( array $n ) {
		$role = isset( $n['attrs']['role'] ) ? (string) $n['attrs']['role'] : '';
		if ( 'a' === $n['tag'] ) {
			return 'link';
		}
		if ( 'button' === $n['tag'] || 'button' === $role ) {
			return 'button';
		}
		if ( 'input' === $n['tag'] ) {
			$type = strtolower( (string) ( $n['attrs']['type'] ?? 'text' ) );
			return in_array( $type, array( 'submit', 'button', 'image', 'reset' ), true ) ? 'button' : $type . ' field';
		}
		if ( in_array( $n['tag'], array( 'select', 'textarea', 'label', 'summary', 'option' ), true ) ) {
			return $n['tag'];
		}
		if ( in_array( $n['tag'], array( 'img', 'svg', 'picture' ), true ) ) {
			return 'image';
		}
		if ( '' !== $role ) {
			return $role;
		}
		if ( in_array( $n['tag'], array( 'p', 'span', 'strong', 'em', 'b', 'i', 'small', 'li', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote' ), true ) ) {
			return 'text';
		}
		return in_array( $n['tag'], array( 'div', 'section', 'article', 'main', 'header', 'footer', 'nav', 'aside', 'body', 'form', 'ul', 'ol' ), true ) ? 'area' : 'element';
	}

	/**
	 * Visible label of an element (masked text stays masked).
	 *
	 * @param int                            $id    Node id.
	 * @param array<int,array<string,mixed>> $nodes Index.
	 * @return string
	 */
	private function label( $id, array $nodes ) {
		$a = $nodes[ $id ]['attrs'];
		if ( isset( $a['aria-label'] ) && is_string( $a['aria-label'] ) && '' !== trim( $a['aria-label'] ) ) {
			return self::short( $a['aria-label'], 60 );
		}
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $this->text_of( $id, $nodes, 0 ) ) );
		if ( '' !== $text ) {
			return self::masked( $text ) ? 'hidden text' : self::short( $text, 60 );
		}
		if ( 'input' === $nodes[ $id ]['tag'] && in_array( strtolower( (string) ( $a['type'] ?? '' ) ), array( 'submit', 'button' ), true ) && isset( $a['value'] ) && ! self::masked( (string) $a['value'] ) ) {
			return self::short( (string) $a['value'], 60 );
		}
		foreach ( array( 'title', 'alt' ) as $attr ) {
			if ( isset( $a[ $attr ] ) && is_string( $a[ $attr ] ) && '' !== trim( $a[ $attr ] ) ) {
				return self::short( $a[ $attr ], 60 );
			}
		}
		foreach ( $nodes[ $id ]['children'] as $child ) {
			if ( isset( $nodes[ $child ] ) && 'img' === $nodes[ $child ]['tag'] && ! empty( $nodes[ $child ]['attrs']['alt'] ) ) {
				return self::short( (string) $nodes[ $child ]['attrs']['alt'], 60 );
			}
		}
		return '';
	}

	/**
	 * Text inside an element, skipping scripts and styles.
	 *
	 * @param int                            $id    Node id.
	 * @param array<int,array<string,mixed>> $nodes Index.
	 * @param int                            $depth Depth.
	 * @return string
	 */
	private function text_of( $id, array $nodes, $depth ) {
		if ( ! isset( $nodes[ $id ] ) || $depth > 6 ) {
			return '';
		}
		$n = $nodes[ $id ];
		if ( 3 === $n['type'] ) {
			return $n['text'];
		}
		if ( in_array( $n['tag'], array( 'script', 'style', 'noscript', 'template' ), true ) ) {
			return '';
		}
		$out = '';
		foreach ( $n['children'] as $child ) {
			$out .= ' ' . $this->text_of( $child, $nodes, $depth + 1 );
			if ( strlen( $out ) > 200 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Clicks of a page that was not recorded (heatmap data only).
	 *
	 * @param int $pageview_id Page view id.
	 * @param int $offset      Seconds from visit start.
	 * @return array<int,array{0:int,1:string}>
	 */
	private function clicks_without_recording( $pageview_id, $offset ) {
		global $wpdb;
		$c    = Schema::table( 'clicks' );
		$sel  = Schema::table( 'selectors' );
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT s.selector, COUNT(*) AS n FROM {$c} c LEFT JOIN {$sel} s ON s.id = c.selector_id WHERE c.pageview_id = %d GROUP BY s.selector ORDER BY MIN(c.id) LIMIT 15", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$pageview_id
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$what  = '' !== (string) $r->selector ? (string) $r->selector : 'the page';
			$out[] = array( $offset * 1000 + 1, 'click ' . $what . ( (int) $r->n > 1 ? ' (' . (int) $r->n . ' times, time unknown)' : ' (time unknown)' ) );
		}
		return $out;
	}

	/**
	 * Text for a frustration or error signal.
	 *
	 * @param array<string,mixed> $sig Signal.
	 * @return string
	 */
	private function signal_line( array $sig ) {
		$data = (array) $sig['data'];
		$el   = '' !== $sig['selector'] ? ' ' . self::short( $sig['selector'], 80 ) : '';
		switch ( $sig['type'] ) {
			case 'rage':
				return 'rage-click' . $el . ( ! empty( $data['count'] ) ? ' (' . (int) $data['count'] . ' clicks)' : '' );
			case 'dead':
				return 'dead click (nothing happened)' . $el;
			case 'error':
				$file = isset( $data['source'] ) ? basename( (string) wp_parse_url( (string) $data['source'], PHP_URL_PATH ) ) : '';
				return 'js-error "' . self::short( (string) ( $data['message'] ?? '' ), 120 ) . '"' . ( '' !== $file ? ' in ' . $file : '' );
			case 'quickback':
				return 'went back within 5 seconds (quick back)';
			case 'custom':
				return 'event ' . (string) ( $data['name'] ?? '' );
		}
		return '';
	}

	/**
	 * Merge repeated identical actions in a row ("click X ×3").
	 *
	 * @param array<int,array{0:int,1:string}> $events Events.
	 * @return array<int,array{0:int,1:string}>
	 */
	private static function collapse( array $events ) {
		$out = array();
		foreach ( $events as $e ) {
			$last = count( $out ) - 1;
			if ( $last >= 0 && $out[ $last ][2] === $e[1] ) {
				++$out[ $last ][3];
				continue;
			}
			$out[] = array( $e[0], $e[1], $e[1], 1 );
		}
		foreach ( $out as &$o ) {
			if ( $o[3] > 1 ) {
				$o[1] .= ' ×' . $o[3];
			}
			$o = array( $o[0], $o[1] );
		}
		unset( $o );
		return $out;
	}

	/**
	 * Keep the digest under the size cap: the start and the end of the
	 * visit matter most.
	 *
	 * @param string[] $lines Lines.
	 * @return string
	 */
	private function fit( array $lines ) {
		$text = implode( "\n", $lines );
		if ( strlen( $text ) <= self::MAX_CHARS ) {
			return $text;
		}
		$head = array();
		$size = 0;
		foreach ( $lines as $line ) {
			if ( $size + strlen( $line ) > self::MAX_CHARS * 0.7 ) {
				break;
			}
			$head[] = $line;
			$size  += strlen( $line ) + 1;
		}
		$tail = array();
		$size = 0;
		for ( $i = count( $lines ) - 1; $i >= count( $head ); $i-- ) {
			if ( $size + strlen( $lines[ $i ] ) > self::MAX_CHARS * 0.25 ) {
				break;
			}
			array_unshift( $tail, $lines[ $i ] );
			$size += strlen( $lines[ $i ] ) + 1;
		}
		$skipped = count( $lines ) - count( $head ) - count( $tail );
		return implode( "\n", $head ) . "\n" . sprintf( '… %d lines left out …', $skipped ) . "\n" . implode( "\n", $tail );
	}

	/**
	 * Remove emails, phone numbers and card-like numbers.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function redact( $text ) {
		$text = (string) preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $text );
		$text = (string) preg_replace( '/\b(?:\d[ \-]?){13,19}\b/', '[number]', $text );
		$text = (string) preg_replace( '/(?<![\w:])\+?\(?\d{2,4}\)?[ .\-]?\d{3,4}[ .\-]?\d{3,4}\b/', '[phone]', $text );
		return $text;
	}

	/**
	 * Path of a URL without query string or fragment.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function path( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' !== $path ? $path : '/';
	}

	/**
	 * Where a link goes: a path on this site, or another site's host.
	 *
	 * @param string $href Link.
	 * @return string
	 */
	private static function link_target( $href ) {
		$href = trim( $href );
		if ( '' === $href || '#' === $href[0] || 0 === stripos( $href, 'javascript:' ) ) {
			return '';
		}
		if ( 0 === stripos( $href, 'mailto:' ) ) {
			return 'email link';
		}
		if ( 0 === stripos( $href, 'tel:' ) ) {
			return 'phone link';
		}
		$host = (string) wp_parse_url( $href, PHP_URL_HOST );
		$home = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' !== $host && strtolower( $host ) !== strtolower( $home ) ) {
			return $host;
		}
		return self::path( $href );
	}

	/**
	 * "#id" or ".class" hint when an element has no label.
	 *
	 * @param array<string,mixed> $n Node.
	 * @return string
	 */
	private static function css_hint( array $n ) {
		$a = $n['attrs'];
		if ( isset( $a['id'] ) && is_string( $a['id'] ) && preg_match( '/^[A-Za-z][\w\-]{0,40}$/', $a['id'] ) ) {
			return ' #' . $a['id'];
		}
		if ( isset( $a['class'] ) && is_string( $a['class'] ) ) {
			$classes = preg_split( '/\s+/', trim( $a['class'] ) );
			foreach ( (array) $classes as $cls ) {
				if ( preg_match( '/^[A-Za-z][\w\-]{1,40}$/', $cls ) && 0 !== strpos( $cls, 'qcin-' ) ) {
					return ' .' . $cls;
				}
			}
		}
		return '';
	}

	/**
	 * Whether text is masked (only asterisks and spaces).
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	private static function masked( $text ) {
		return (bool) preg_match( '/^[\s*]+$/', $text );
	}

	/**
	 * Shorten and clean a label.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function short( $text, $max ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
		$text = str_replace( '"', "'", $text );
		return mb_strlen( $text ) > $max ? rtrim( mb_substr( $text, 0, $max - 1 ) ) . '…' : $text;
	}

	/**
	 * Seconds as mm:ss.
	 *
	 * @param int $seconds Seconds.
	 * @return string
	 */
	private static function clock( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		return sprintf( '%02d:%02d', floor( $seconds / 60 ), $seconds % 60 );
	}

	/**
	 * Seconds as "4m 12s".
	 *
	 * @param float $seconds Seconds.
	 * @return string
	 */
	private static function duration( $seconds ) {
		$s = (int) round( $seconds );
		if ( $s < 60 ) {
			return $s . 's';
		}
		return floor( $s / 60 ) . 'm ' . ( $s % 60 ) . 's';
	}
}
