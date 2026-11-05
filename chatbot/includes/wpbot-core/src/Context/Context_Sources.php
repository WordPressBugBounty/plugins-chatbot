<?php
/**
 * Business Context > "Data you already have": every source that can feed
 * the business context, whether it is in use, and for a missing one, why
 * it helps and where to set it up. WPBot Core lists its own sources (site
 * scan, WooCommerce products, chatbot knowledge base, customer chat
 * questions, competitors, Brand & Business); modules add theirs through the
 * filter qcld_wpbot_core/context_sources (SEO & AI Search: Search Console,
 * Keyword Vault, location, Google Business Profile).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Context;

use QuantumCloud\WPBotCore\Competitors\Competitors;

defined( 'ABSPATH' ) || exit;

/**
 * Context sources.
 */
final class Context_Sources {

	/**
	 * All sources, in screen order.
	 *
	 * @param array $state Context::get().
	 * @return array[] id, label, adds, status (ok|missing|off), detail, action (label, url or op).
	 */
	public static function all( array $state ) {
		$by    = array();
		$facts = isset( $state['sources'] ) ? (array) $state['sources'] : array();
		foreach ( $facts as $layer ) {
			$by[ $layer['id'] ] = $layer;
		}
		$sources = array();

		$site      = isset( $by['site'] ) ? $by['site'] : array();
		$counts    = isset( $site['detail'] ) ? (array) $site['detail'] : array();
		$sources[] = self::item(
			'site',
			__( 'Your website', 'chatbot' ),
			__( 'Pages, menus, categories and products. Read on this site, free, no AI.', 'chatbot' ),
			'' !== (string) $state['scanned_at'] && ! empty( $site['count'] ) ? 'ok' : 'missing',
			'' !== (string) $state['scanned_at']
				? self::site_detail( isset( $counts['pages'] ) ? (int) $counts['pages'] : 0, isset( $counts['products'] ) ? (int) $counts['products'] : 0 )
				: __( 'Not read yet', 'chatbot' ),
			'' !== (string) $state['scanned_at'] ? null : array(
				'label' => __( 'Read my website', 'chatbot' ),
				'op'    => 'scan',
			)
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$products  = isset( $counts['products'] ) ? (int) $counts['products'] : 0;
			$sources[] = self::item(
				'woocommerce',
				__( 'WooCommerce products', 'chatbot' ),
				__( 'Your best sellers, with prices, so every tool names the right products.', 'chatbot' ),
				$products > 0 ? 'ok' : 'missing',
				/* translators: %d: products. */
				$products > 0 ? sprintf( _n( '%d product read', '%d products read', $products, 'chatbot' ), $products ) : __( 'No published products yet', 'chatbot' ),
				$products > 0 ? null : array(
					'label' => __( 'Add products', 'chatbot' ),
					'url'   => admin_url( 'post-new.php?post_type=product' ),
				)
			);
		}

		$knowledge = isset( $by['knowledge'] ) ? $by['knowledge'] : array();
		if ( ! empty( $knowledge['available'] ) || ! empty( $knowledge['writable'] ) ) {
			$on        = ! empty( $knowledge['on'] );
			$sources[] = self::item(
				'knowledge',
				__( "Your chatbot's knowledge base", 'chatbot' ),
				__( 'The answers your chatbot already gives: prices, policies, opening hours.', 'chatbot' ),
				$on && ! empty( $knowledge['count'] ) ? 'ok' : ( $on ? 'missing' : 'off' ),
				$on
					/* translators: %d: answers. */
					? ( ! empty( $knowledge['count'] ) ? sprintf( __( '%d answers read', 'chatbot' ), (int) $knowledge['count'] ) : __( 'The knowledge base is empty', 'chatbot' ) )
					: __( 'Not used', 'chatbot' ),
				$on
					? ( empty( $knowledge['count'] ) && ! empty( $knowledge['writable'] ) ? array(
						'label' => __( 'Build it from my site', 'chatbot' ),
						'op'    => 'knowledge',
					) : null )
					: array(
						'label' => __( 'Use it', 'chatbot' ),
						'op'    => 'on:knowledge',
					)
			);
		}

		$questions = isset( $by['questions'] ) ? $by['questions'] : array();
		if ( ! empty( $questions['available'] ) ) {
			$on        = ! empty( $questions['on'] );
			$sources[] = self::item(
				'questions',
				__( 'What customers ask your chatbot', 'chatbot' ),
				__( 'Questions from chats in the last 6 months, without names or contact details.', 'chatbot' ),
				$on ? 'ok' : 'off',
				/* translators: %d: questions. */
				$on ? sprintf( __( '%d questions read', 'chatbot' ), (int) $questions['count'] ) : __( 'Not used', 'chatbot' ),
				$on ? null : array(
					'label' => __( 'Use them', 'chatbot' ),
					'op'    => 'on:questions',
				)
			);
		}

		$domains   = class_exists( Competitors::class ) ? Competitors::domains() : array();
		$sources[] = self::item(
			'competitors',
			__( 'Competitors', 'chatbot' ),
			__( 'Who you compete with, so tools can say what makes you different.', 'chatbot' ),
			array() !== $domains ? 'ok' : 'missing',
			/* translators: %d: competitors. */
			array() !== $domains ? sprintf( _n( '%d competitor', '%d competitors', count( $domains ), 'chatbot' ), count( $domains ) ) : __( 'None added yet', 'chatbot' ),
			null
		);

		$kit      = function_exists( 'qcld_wpbot_core_persuasion_kit' ) ? (array) qcld_wpbot_core_persuasion_kit() : array();
		$words    = function_exists( 'qcld_wpbot_core_voice_of_customer' ) ? (array) qcld_wpbot_core_voice_of_customer( array( 'limit' => 200 ) ) : array();
		$brand_ok = array() !== $kit || array() !== $words;
		/**
		 * Where the owner edits Brand & Business (AI Writer sets its own tab; '' hides the link).
		 *
		 * @param string $url Admin URL.
		 */
		$brand_url = (string) apply_filters( 'qcld_wpbot_core/context_brand_url', function_exists( 'qcld_wpbot_writer_open_brief_url' ) ? admin_url( 'admin.php?page=wpbot-ai-writer&tab=voice' ) : '' );
		$sources[] = self::item(
			'brand',
			__( 'Brand & Business', 'chatbot' ),
			__( 'Your proof (reviews, guarantees, years in business), the words customers use, and your brand voice.', 'chatbot' ),
			$brand_ok ? 'ok' : 'missing',
			/* translators: 1: proof items, 2: customer phrases. */
			$brand_ok ? sprintf( __( '%1$d proof items, %2$d customer phrases', 'chatbot' ), count( $kit ), count( $words ) ) : __( 'Nothing added yet', 'chatbot' ),
			'' !== $brand_url ? array(
				'label' => $brand_ok ? __( 'Open Brand & Business', 'chatbot' ) : __( 'Add your proof and brand voice', 'chatbot' ),
				'url'   => $brand_url,
			) : null
		);

		$docs      = Context_Documents::all();
		$sources[] = self::item(
			'documents',
			__( 'Your documents', 'chatbot' ),
			__( 'Price lists, brochures, service sheets: upload them and AI keeps the facts.', 'chatbot' ),
			array() !== $docs ? 'ok' : 'missing',
			/* translators: %d: documents. */
			array() !== $docs ? sprintf( _n( '%d document', '%d documents', count( $docs ), 'chatbot' ), count( $docs ) ) : __( 'None uploaded', 'chatbot' ),
			array() !== $docs ? null : array(
				'label' => __( 'Upload documents', 'chatbot' ),
				'op'    => 'tab:documents',
			)
		);

		/**
		 * Sources modules add (or change) on the Business Context screen.
		 *
		 * Each: id, label, adds (why it helps), status (ok|missing|off),
		 * detail, action (label plus url) or null.
		 *
		 * @param array[] $sources Sources.
		 * @param array   $state   Context::get().
		 */
		$sources = (array) apply_filters( 'qcld_wpbot_core/context_sources', $sources, $state );
		$out     = array();
		foreach ( $sources as $source ) {
			if ( is_array( $source ) && ! empty( $source['id'] ) && ! empty( $source['label'] ) ) {
				$out[] = self::clean( $source );
			}
		}
		return $out;
	}

	/**
	 * What the site scan read, in words ("40 pages" or "40 pages, 9 products").
	 *
	 * @param int $pages    Pages.
	 * @param int $products Products.
	 * @return string
	 */
	private static function site_detail( $pages, $products ) {
		/* translators: %d: pages. */
		$text = sprintf( _n( '%d page', '%d pages', $pages, 'chatbot' ), $pages );
		if ( $products > 0 ) {
			/* translators: 1: pages, 2: products. */
			$text = sprintf( _n( '%1$s, %2$d product', '%1$s, %2$d products', $products, 'chatbot' ), $text, $products );
		}
		return $text;
	}

	/**
	 * One source.
	 *
	 * @param string     $id     Id.
	 * @param string     $label  Label.
	 * @param string     $adds   Why it helps.
	 * @param string     $status ok|missing|off.
	 * @param string     $detail What was read.
	 * @param array|null $action label plus url or op.
	 * @return array
	 */
	public static function item( $id, $label, $adds, $status, $detail, $action ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'adds'   => $adds,
			'status' => $status,
			'detail' => $detail,
			'action' => $action,
		);
	}

	/**
	 * Clean one source for the screen.
	 *
	 * @param array $source Source.
	 * @return array
	 */
	private static function clean( array $source ) {
		$action = isset( $source['action'] ) && is_array( $source['action'] ) && ! empty( $source['action']['label'] ) ? $source['action'] : null;
		if ( $action ) {
			$action = array(
				'label' => sanitize_text_field( (string) $action['label'] ),
				'url'   => isset( $action['url'] ) ? esc_url_raw( (string) $action['url'] ) : '',
				'op'    => isset( $action['op'] ) ? preg_replace( '/[^a-z_:]/', '', (string) $action['op'] ) : '',
			);
		}
		$status = isset( $source['status'] ) && in_array( $source['status'], array( 'ok', 'missing', 'off' ), true ) ? $source['status'] : 'missing';
		return array(
			'id'     => sanitize_key( (string) $source['id'] ),
			'label'  => sanitize_text_field( (string) $source['label'] ),
			'adds'   => isset( $source['adds'] ) ? sanitize_text_field( (string) $source['adds'] ) : '',
			'status' => $status,
			'detail' => isset( $source['detail'] ) ? sanitize_text_field( (string) $source['detail'] ) : '',
			'action' => $action,
		);
	}
}
