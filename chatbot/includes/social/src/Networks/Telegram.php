<?php
/**
 * Telegram channel adapter through the ChatBot's bot (sendMessage /
 * sendPhoto). Social never touches the bot's webhook (build-brief A3).
 *
 * Telegram has no way for a bot to read a channel's history, so an
 * ambiguous send cannot be looked up: the owner is asked to check and
 * confirm, and the post is never resent automatically.
 * Sources: https://core.telegram.org/bots/api#sendmessage and #sendphoto.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

use QuantumCloud\Social\Http\Transport;
use QuantumCloud\Social\Http\Wp_Transport;
use QuantumCloud\Social\Support\Redact;

defined( 'ABSPATH' ) || exit;

/**
 * Telegram adapter.
 */
class Telegram implements Network {

	/**
	 * Transport.
	 *
	 * @var Transport
	 */
	private $transport;

	/**
	 * Build.
	 *
	 * @param Transport|null $transport Transport.
	 */
	public function __construct( ?Transport $transport = null ) {
		$this->transport = $transport ? $transport : new Wp_Transport();
	}

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'telegram';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Telegram', 'chatbot' );
	}

	/**
	 * Limits: 4,096 characters for a message, 1,024 for a photo caption.
	 *
	 * @return array<string,mixed>
	 */
	public function limits() {
		return array(
			'max_text'           => 4096,
			'max_caption'        => 1024,
			'max_images'         => 1,
			'image_mimes'        => array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ),
			'needs_image'        => false,
			'needs_public_media' => true,
			'link_in_text'       => true,
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
		$text     = $this->text( $draft );
		if ( '' === trim( $text ) && array() === $draft->images() ) {
			$problems[] = __( 'Add some text or an image.', 'chatbot' );
		}
		$max = array() === $draft->images() ? 4096 : 1024;
		if ( mb_strlen( $text ) > $max ) {
			/* translators: %d: character limit. */
			$problems[] = sprintf( __( 'Telegram allows up to %d characters here.', 'chatbot' ), $max );
		}
		if ( count( $draft->images() ) > 1 ) {
			$problems[] = __( 'Telegram posts here take one image.', 'chatbot' );
		}
		return $problems;
	}

	/**
	 * Send the post.
	 *
	 * @param Target $target Target (account id = chat id, token = bot token).
	 * @param Draft  $draft  Draft.
	 * @return Publish_Result
	 */
	public function publish( Target $target, Draft $draft ) {
		$images = $draft->images();
		if ( array() !== $images ) {
			$method = 'sendPhoto';
			$fields = array(
				'chat_id' => $target->account_id(),
				'photo'   => $images[0]['url'],
				'caption' => $this->text( $draft ),
			);
		} else {
			$method = 'sendMessage';
			$fields = array(
				'chat_id' => $target->account_id(),
				'text'    => $this->text( $draft ),
			);
		}
		$response = $this->transport->request( 'POST', 'https://api.telegram.org/bot' . $target->token() . '/' . $method, array(), $fields );
		if ( $response->no_response() ) {
			return Publish_Result::unknown( 'timeout', __( 'Telegram did not answer in time. Please check the channel and confirm whether the post went out.', 'chatbot' ) );
		}
		$body = $response->body;
		if ( ! empty( $body['ok'] ) && isset( $body['result']['message_id'] ) ) {
			$id   = (string) $body['result']['message_id'];
			$chat = isset( $body['result']['chat']['username'] ) ? (string) $body['result']['chat']['username'] : '';
			return Publish_Result::done( $id, '' !== $chat ? 'https://t.me/' . rawurlencode( $chat ) . '/' . rawurlencode( $id ) : '' );
		}
		$code = isset( $body['error_code'] ) ? (int) $body['error_code'] : $response->code;
		$desc = isset( $body['description'] ) ? Redact::secrets( (string) $body['description'] ) : '';
		if ( 429 === $code ) {
			return Publish_Result::failed( 'rate_limited', __( 'Telegram is limiting how often the bot can post. WPBot will try again later.', 'chatbot' ), true );
		}
		if ( 401 === $code ) {
			$result                = Publish_Result::failed( 'auth_expired', __( 'The Telegram bot token no longer works. Update it in Social › Connect › Telegram (or ChatBot › Telegram if you use the ChatBot\'s bot).', 'chatbot' ) );
			$result->pause_account = true;
			return $result;
		}
		if ( 403 === $code ) {
			return Publish_Result::failed( 'permission', __( 'The bot is not an admin of the channel, or may not post there. Fix it in Telegram, then retry.', 'chatbot' ) );
		}
		if ( $code >= 500 ) {
			return Publish_Result::unknown( 'server_error', __( 'Telegram had a temporary problem. Please check the channel and confirm whether the post went out.', 'chatbot' ) );
		}
		/* translators: %s: Telegram's error message. */
		return Publish_Result::failed( 'bad_request', sprintf( __( 'Telegram refused the post: %s', 'chatbot' ), $desc ) );
	}

	/**
	 * Telegram cannot look posts up: ask the owner.
	 *
	 * @param Target $target Target.
	 * @param Draft  $draft  Draft.
	 * @return Reconcile_Result
	 */
	public function reconcile( Target $target, Draft $draft ) {
		return new Reconcile_Result( Reconcile_Result::CANNOT_CHECK, '', __( 'Telegram does not let WPBot check a channel\'s posts. Please look at the channel and confirm whether this post went out.', 'chatbot' ) );
	}

	/**
	 * Text with the link appended.
	 *
	 * @param Draft $draft Draft.
	 * @return string
	 */
	private function text( Draft $draft ) {
		return '' === $draft->link() ? $draft->text() : trim( $draft->text() . "\n\n" . $draft->link() );
	}
}
