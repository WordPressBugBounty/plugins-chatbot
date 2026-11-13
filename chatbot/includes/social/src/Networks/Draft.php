<?php
/**
 * One network's version of a post: the text and media to send.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable post content for one network.
 */
final class Draft {

	/**
	 * Post text.
	 *
	 * @var string
	 */
	private $text;

	/**
	 * Link to attach, if any.
	 *
	 * @var string
	 */
	private $link;

	/**
	 * Images: each [ 'url' => public URL, 'mime' => MIME type ].
	 *
	 * @var array<int,array{url:string,mime:string}>
	 */
	private $images;

	/**
	 * Network-specific extras, for example Google post type and event dates.
	 *
	 * @var array<string,mixed>
	 */
	private $extras;

	/**
	 * Build a draft.
	 *
	 * @param string $text   Text.
	 * @param string $link   Link URL or ''.
	 * @param array  $images Images, each with url and mime.
	 * @param array  $extras Network-specific extras.
	 */
	public function __construct( $text, $link = '', array $images = array(), array $extras = array() ) {
		$this->extras = $extras;
		$this->text   = (string) $text;
		$this->link   = (string) $link;
		$this->images = array();
		foreach ( $images as $image ) {
			if ( is_array( $image ) && ! empty( $image['url'] ) ) {
				$this->images[] = array(
					'url'  => (string) $image['url'],
					'mime' => isset( $image['mime'] ) ? strtolower( (string) $image['mime'] ) : '',
				);
			}
		}
	}

	/**
	 * Text.
	 *
	 * @return string
	 */
	public function text() {
		return $this->text;
	}

	/**
	 * Link.
	 *
	 * @return string
	 */
	public function link() {
		return $this->link;
	}

	/**
	 * Images.
	 *
	 * @return array<int,array{url:string,mime:string}>
	 */
	public function images() {
		return $this->images;
	}

	/**
	 * One extra value.
	 *
	 * @param string $key      Key.
	 * @param mixed  $fallback Value when missing.
	 * @return mixed
	 */
	public function extra( $key, $fallback = null ) {
		return array_key_exists( $key, $this->extras ) ? $this->extras[ $key ] : $fallback;
	}

	/**
	 * Plain array for storage.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'text'   => $this->text,
			'link'   => $this->link,
			'images' => $this->images,
			'extras' => $this->extras,
		);
	}

	/**
	 * Build from storage.
	 *
	 * @param array $data Stored draft.
	 * @return Draft
	 */
	public static function from_array( array $data ) {
		return new self(
			isset( $data['text'] ) ? (string) $data['text'] : '',
			isset( $data['link'] ) ? (string) $data['link'] : '',
			isset( $data['images'] ) ? (array) $data['images'] : array(),
			isset( $data['extras'] ) ? (array) $data['extras'] : array()
		);
	}

	/**
	 * A fingerprint of the text, used to recognise the post when reconciling.
	 *
	 * Whitespace and case are normalised because networks may trim or
	 * reflow text.
	 *
	 * @param string $text Text to fingerprint; defaults to this draft's text.
	 * @return string
	 */
	public function fingerprint( $text = null ) {
		$text = null === $text ? $this->text : (string) $text;
		$text = strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $text ) ) );
		return hash( 'sha256', $text );
	}
}
