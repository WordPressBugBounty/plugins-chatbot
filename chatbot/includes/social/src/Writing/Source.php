<?php
/**
 * What a post is about: an idea, a post or page, a product, a web address,
 * an event or offer, or nothing (blank). Turned into plain facts the writer
 * may use, plus the link the versions point to.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Writing;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Source material for Write with AI.
 */
final class Source {

	/**
	 * Kinds of start.
	 */
	const KINDS = array( 'idea', 'post', 'product', 'url', 'event', 'blank' );

	/**
	 * Longest source text sent to the AI, in characters.
	 */
	const MAX = 6000;

	/**
	 * Build the source.
	 *
	 * @param array $input kind, about (text), post_id, product_id, url, event (title, date, place, offer, code, ends).
	 * @return array{kind:string, text:string, link:string, title:string, image_id:int}|WP_Error
	 */
	public static function build( array $input ) {
		$kind  = isset( $input['kind'] ) && in_array( $input['kind'], self::KINDS, true ) ? $input['kind'] : 'idea';
		$about = trim( sanitize_textarea_field( isset( $input['about'] ) ? (string) $input['about'] : '' ) );
		$out   = array(
			'kind'     => $kind,
			'text'     => $about,
			'link'     => esc_url_raw( isset( $input['link'] ) ? (string) $input['link'] : '' ),
			'title'    => '',
			'image_id' => 0,
		);
		switch ( $kind ) {
			case 'post':
				$post = get_post( absint( isset( $input['post_id'] ) ? $input['post_id'] : 0 ) );
				if ( ! $post || 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
					return new WP_Error( 'qcld_wpbot_social_source', __( 'Choose a published post or page.', 'chatbot' ), array( 'status' => 400 ) );
				}
				$out['title']    = get_the_title( $post );
				$out['link']     = get_permalink( $post );
				$out['image_id'] = (int) get_post_thumbnail_id( $post );
				$out['text']     = self::join( array( 'Title: ' . $out['title'], $post->post_excerpt ? 'Summary: ' . $post->post_excerpt : '', 'Content: ' . self::plain( $post->post_content ), $about ? 'Notes from the owner: ' . $about : '' ) );
				break;
			case 'product':
				$id      = absint( isset( $input['product_id'] ) ? $input['product_id'] : 0 );
				$product = function_exists( 'wc_get_product' ) && $id ? wc_get_product( $id ) : null;
				// QA #59: published and visible products only, like posts.
				if ( ! $product || 'publish' !== $product->get_status() || 'hidden' === $product->get_catalog_visibility() || '' !== (string) get_post_field( 'post_password', $id ) ) {
					return new WP_Error( 'qcld_wpbot_social_source', __( 'Choose a published product (WooCommerce must be active).', 'chatbot' ), array( 'status' => 400 ) );
				}
				$out['title']    = $product->get_name();
				$out['link']     = get_permalink( $id );
				$out['image_id'] = (int) $product->get_image_id();
				$price           = function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $product->get_price() ) ) : (string) $product->get_price();
				$out['text']     = self::join( array( 'Product: ' . $out['title'], '' !== (string) $product->get_price() ? 'Price: ' . html_entity_decode( $price ) : '', 'Description: ' . self::plain( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ), $about ? 'Notes from the owner: ' . $about : '' ) );
				break;
			case 'url':
				$url = esc_url_raw( isset( $input['url'] ) ? (string) $input['url'] : '' );
				if ( '' === $url || ! wp_http_validate_url( $url ) ) {
					return new WP_Error( 'qcld_wpbot_social_source', __( 'Enter a full web address (https://…).', 'chatbot' ), array( 'status' => 400 ) );
				}
				$page = self::fetch( $url );
				if ( is_wp_error( $page ) ) {
					return $page;
				}
				$out['title'] = $page['title'];
				$out['link']  = $url;
				$out['text']  = self::join( array( 'Page title: ' . $page['title'], $page['description'] ? 'Page summary: ' . $page['description'] : '', 'Page text: ' . $page['text'], $about ? 'Notes from the owner: ' . $about : '' ) );
				break;
			case 'event':
				$event        = isset( $input['event'] ) && is_array( $input['event'] ) ? $input['event'] : array();
				$fields       = array(
					'title' => 'Event or offer',
					'date'  => 'When',
					'place' => 'Where',
					'offer' => 'Offer',
					'code'  => 'Code',
					'ends'  => 'Ends',
				);
				$lines        = array();
				$out['title'] = isset( $event['title'] ) ? sanitize_text_field( (string) $event['title'] ) : '';
				foreach ( $fields as $key => $label ) {
					$value = isset( $event[ $key ] ) ? trim( sanitize_text_field( (string) $event[ $key ] ) ) : '';
					if ( '' !== $value ) {
						$lines[] = $label . ': ' . $value;
					}
				}
				if ( $about ) {
					$lines[] = 'Notes from the owner: ' . $about;
				}
				$out['text'] = self::join( $lines );
				break;
			case 'blank':
				return $out;
		}
		if ( '' === trim( $out['text'] ) ) {
			return new WP_Error( 'qcld_wpbot_social_source', __( 'Say what the post is about.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$out['text'] = mb_substr( $out['text'], 0, self::MAX );
		return $out;
	}

	/**
	 * Fetch a public web page (private and local addresses are refused).
	 *
	 * @param string $url URL.
	 * @return array{title:string, description:string, text:string}|WP_Error
	 */
	public static function fetch( $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 3,
				'limit_response_size' => 1048576,
				'user-agent'          => 'WPBot Social; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'qcld_wpbot_social_fetch', __( 'WPBot could not read that page. Check the address, or paste the text into "What is it about?".', 'chatbot' ), array( 'status' => 400 ) );
		}
		return self::parse_html( (string) wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Title, description and readable text of an HTML page.
	 *
	 * @param string $html HTML.
	 * @return array{title:string, description:string, text:string}
	 */
	public static function parse_html( $html ) {
		$title       = preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ? self::plain( $m[1] ) : '';
		$description = preg_match( '#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)#i', $html, $m ) ? self::plain( $m[1] ) : '';
		if ( '' === $description && preg_match( '#<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']*)#i', $html, $m ) ) {
			$description = self::plain( $m[1] );
		}
		$body = preg_replace( '#<(script|style|nav|footer|header|noscript|svg|form)[^>]*>.*?</\1>#is', ' ', $html );
		return array(
			'title'       => mb_substr( $title, 0, 200 ),
			'description' => mb_substr( $description, 0, 400 ),
			'text'        => mb_substr( self::plain( (string) $body ), 0, 4000 ),
		);
	}

	/**
	 * Plain text from HTML or block content.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function plain( $html ) {
		$text = wp_strip_all_tags( strip_shortcodes( (string) $html ) );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Join non-empty lines.
	 *
	 * @param string[] $lines Lines.
	 * @return string
	 */
	private static function join( array $lines ) {
		return implode( "\n", array_filter( array_map( 'trim', $lines ) ) );
	}
}
