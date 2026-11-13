<?php
/**
 * A network apps cannot post to ("Post yourself", screen 08): WhatsApp in
 * core; Medium, Nextdoor, Quora and Snapchat from the Master module. WPBot
 * writes the copy and gets the image ready; at the scheduled time the target
 * waits in state "manual" and the owner is reminded, posts it, and marks it
 * as posted. If a network opens a posting API later, it moves to an
 * automatic adapter without changing the owner's posts.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * Manual network.
 */
class Manual_Network implements Network {

	/**
	 * Id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Text limit.
	 *
	 * @var int
	 */
	private $max;

	/**
	 * How the owner posts it: open (URL template with {text} and {url}),
	 * phone (true when the network is phone-only: show a QR code), kind
	 * (what the copy is: Status, Story, Answer…), note.
	 *
	 * @var array<string,mixed>
	 */
	private $how;

	/**
	 * Build.
	 *
	 * @param string $id    Id.
	 * @param string $label Label.
	 * @param int    $max   Text limit.
	 * @param array  $how   open, phone, kind, note.
	 */
	public function __construct( $id, $label, $max, array $how = array() ) {
		$this->id    = (string) $id;
		$this->label = (string) $label;
		$this->max   = (int) $max;
		$this->how   = array_merge(
			array(
				'open'  => '',
				'phone' => false,
				'kind'  => '',
				'note'  => '',
			),
			$how
		);
	}

	/**
	 * WhatsApp, the core module's manual network.
	 *
	 * @return self
	 */
	public static function whatsapp() {
		return new self(
			'whatsapp',
			__( 'WhatsApp', 'chatbot' ),
			700,
			array(
				'open'  => 'https://wa.me/?text={text}',
				'phone' => true,
				'kind'  => __( 'Status & Channel', 'chatbot' ),
				'note'  => __( 'Status is phone only: scan to open this copy on your phone.', 'chatbot' ),
			)
		);
	}

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return $this->label;
	}

	/**
	 * How the owner posts it.
	 *
	 * @return array<string,mixed>
	 */
	public function how() {
		return $this->how;
	}

	/**
	 * The "Open" link for a copy ('' when the network has none).
	 *
	 * @param string $text Copy.
	 * @param string $url  Link.
	 * @return string
	 */
	public function open_url( $text, $url ) {
		if ( '' === $this->how['open'] ) {
			return '';
		}
		return str_replace( array( '{text}', '{url}' ), array( rawurlencode( (string) $text ), rawurlencode( (string) $url ) ), (string) $this->how['open'] );
	}

	/**
	 * Limits.
	 *
	 * @return array<string,mixed>
	 */
	public function limits() {
		return array(
			'max_text'           => $this->max,
			'max_images'         => 1,
			'image_mimes'        => array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ),
			'needs_image'        => false,
			'needs_public_media' => false,
			'link_in_text'       => true,
			'manual'             => true,
		);
	}

	/**
	 * Validate.
	 *
	 * @param Draft $draft Draft.
	 * @return string[]
	 */
	public function validate( Draft $draft ) {
		$problems = array();
		if ( '' === trim( $draft->text() ) && array() === $draft->images() ) {
			$problems[] = __( 'Add some text or an image.', 'chatbot' );
		}
		if ( $this->max > 0 && mb_strlen( $draft->text() ) > $this->max ) {
			/* translators: 1: network, 2: character limit. */
			$problems[] = sprintf( __( '%1$s copies work best under %2$d characters.', 'chatbot' ), $this->label, $this->max );
		}
		return $problems;
	}

	/**
	 * Never called: the publisher hands manual targets to the owner.
	 *
	 * @param Target $target Target.
	 * @param Draft  $draft  Draft.
	 * @return Publish_Result
	 */
	public function publish( Target $target, Draft $draft ) {
		return Publish_Result::failed( 'manual', __( 'This network is posted by you.', 'chatbot' ) );
	}

	/**
	 * Manual posts are confirmed by the owner.
	 *
	 * @param Target $target Target.
	 * @param Draft  $draft  Draft.
	 * @return Reconcile_Result
	 */
	public function reconcile( Target $target, Draft $draft ) {
		return new Reconcile_Result( Reconcile_Result::CANNOT_CHECK );
	}
}
