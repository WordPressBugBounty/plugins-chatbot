<?php
/**
 * Threads adapter (text or one image; container then publish).
 *
 * Sources: https://developers.facebook.com/documentation/threads/posts
 * (250 posts per 24 h; containers; threads_publish). Public image URL required.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

use QuantumCloud\Social\Http\Response;
use QuantumCloud\Social\Http\Transport;
use QuantumCloud\Social\Http\Wp_Transport;

defined( 'ABSPATH' ) || exit;

/**
 * Threads adapter.
 */
class Threads implements Network {

	/**
	 * API base (version path included).
	 */
	const BASE = 'https://graph.threads.net/v1.0';

	/**
	 * Transport.
	 *
	 * @var Transport
	 */
	private $transport;

	/**
	 * The last container status response (for reconcile errors).
	 *
	 * @var \QuantumCloud\Social\Http\Response|null
	 */
	private $last_status_response;

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
		return 'threads';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Threads', 'chatbot' );
	}

	/**
	 * Limits.
	 *
	 * @return array<string,mixed>
	 */
	public function limits() {
		return array(
			'max_text'           => 500,
			'max_images'         => 1,
			'image_mimes'        => array( 'image/jpeg', 'image/png' ),
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
		if ( '' === trim( $draft->text() ) && array() === $draft->images() ) {
			$problems[] = __( 'Add some text or an image.', 'chatbot' );
		}
		if ( mb_strlen( $this->text( $draft ) ) > 500 ) {
			$problems[] = __( 'Threads posts can be up to 500 characters (the link counts).', 'chatbot' );
		}
		if ( count( $draft->images() ) > 1 ) {
			$problems[] = __( 'Threads posts here take one image.', 'chatbot' );
		}
		foreach ( $draft->images() as $image ) {
			if ( ! in_array( $image['mime'], array( 'image/jpeg', 'image/png' ), true ) ) {
				$problems[] = __( 'Threads accepts JPEG or PNG images.', 'chatbot' );
			}
		}
		return $problems;
	}

	/**
	 * Publish in steps.
	 *
	 * @param Target $target Target.
	 * @param Draft  $draft  Draft.
	 * @return Publish_Result
	 */
	public function publish( Target $target, Draft $draft ) {
		$user      = rawurlencode( $target->account_id() );
		$container = (string) $target->state( 'container_id', '' );
		if ( '' === $container ) {
			$images = $draft->images();
			$fields = array(
				'media_type' => array() === $images ? 'TEXT' : 'IMAGE',
				'text'       => $this->text( $draft ),
			);
			if ( array() !== $images ) {
				$fields['image_url'] = $images[0]['url'];
			}
			$response = $this->call( 'POST', '/' . $user . '/threads', $target, $fields );
			if ( ! $response->ok() ) {
				$result = Meta_Graph::classify( $response, false );
				return $result;
			}
			$container = isset( $response->body['id'] ) ? (string) $response->body['id'] : '';
			if ( '' === $container ) {
				return Publish_Result::failed( 'no_id', __( 'Threads did not return a container. WPBot will try again shortly.', 'chatbot' ), true );
			}
		}
		$state                  = array( 'container_id' => $container );
		list( $status, $error ) = $this->status( $target, $container );
		if ( $error ) {
			$error->state = $state;
			return $error;
		}
		if ( 'PUBLISHED' === $status ) {
			return Publish_Result::unknown( 'published_no_id', __( 'Threads shows the post as published; WPBot is finding its link.', 'chatbot' ), $state );
		}
		if ( 'IN_PROGRESS' === $status ) {
			return Publish_Result::pending( $state, __( 'Threads is still processing the image.', 'chatbot' ) );
		}
		if ( 'ERROR' === $status || 'EXPIRED' === $status ) {
			return Publish_Result::failed( 'media_error', __( 'Threads could not process the post. Check that the image is public and retry.', 'chatbot' ) );
		}
		$response = $this->call( 'POST', '/' . $user . '/threads_publish', $target, array( 'creation_id' => $container ) );
		if ( ! $response->ok() ) {
			$result        = Meta_Graph::classify( $response, true );
			$result->state = $state;
			return $result;
		}
		if ( empty( $response->body['id'] ) ) {
			return Publish_Result::unknown( 'no_id', __( 'Threads accepted the post but returned no id. WPBot will check before trying again.', 'chatbot' ), $state );
		}
		return Publish_Result::done( (string) $response->body['id'] );
	}

	/**
	 * Reconcile from the container status, then the profile's recent threads.
	 *
	 * @param Target $target Target.
	 * @param Draft  $draft  Draft.
	 * @return Reconcile_Result
	 */
	public function reconcile( Target $target, Draft $draft ) {
		$container = (string) $target->state( 'container_id', '' );
		if ( '' === $container ) {
			return new Reconcile_Result( Reconcile_Result::NOT_FOUND );
		}
		$early = Settled_Lookup::too_early( $target );
		if ( $early ) {
			return $early;
		}
		list( $status, $error ) = $this->status( $target, $container );
		if ( $error ) {
			return Meta_Graph::reconcile_error( $this->last_status_response, __( 'WPBot could not check Threads. Please check the profile and confirm.', 'chatbot' ) );
		}
		if ( 'ERROR' === $status || 'EXPIRED' === $status ) {
			return new Reconcile_Result( Reconcile_Result::NOT_FOUND );
		}
		if ( 'FINISHED' === $status ) {
			return Settled_Lookup::missed( $target );
		}
		if ( 'PUBLISHED' !== $status ) {
			return Reconcile_Result::wait( Settled_Lookup::GAP, array(), 'Threads is still processing the post.' );
		}
		$response = $this->call(
			'GET',
			'/' . rawurlencode( $target->account_id() ) . '/threads',
			$target,
			array(
				'fields' => 'id,text,timestamp',
				'limit'  => 10,
			)
		);
		$wanted   = $draft->fingerprint( $this->text( $draft ) );
		foreach ( $response->ok() && isset( $response->body['data'] ) ? (array) $response->body['data'] : array() as $post ) {
			if ( isset( $post['id'], $post['text'] ) && $draft->fingerprint( (string) $post['text'] ) === $wanted ) {
				return new Reconcile_Result( Reconcile_Result::FOUND, (string) $post['id'] );
			}
		}
		return new Reconcile_Result( Reconcile_Result::FOUND, '', __( 'Published on Threads; the link appears after the next check.', 'chatbot' ) );
	}

	/**
	 * Text with the link appended (Threads makes links clickable).
	 *
	 * @param Draft $draft Draft.
	 * @return string
	 */
	private function text( Draft $draft ) {
		return '' === $draft->link() ? $draft->text() : trim( $draft->text() . "\n\n" . $draft->link() );
	}

	/**
	 * Container status and, when it could not be read, the classified error.
	 *
	 * @param Target $target    Target.
	 * @param string $container Container id.
	 * @return array{0:string,1:Publish_Result|null}
	 */
	private function status( Target $target, $container ) {
		$this->last_status_response = $this->call( 'GET', '/' . rawurlencode( $container ), $target, array( 'fields' => 'status,error_message' ) );
		return Meta_Graph::container_status( $this->last_status_response, 'status' );
	}

	/**
	 * Call the Threads API with the token in the Authorization header.
	 *
	 * @param string $method Method.
	 * @param string $path   Path.
	 * @param Target $target Target.
	 * @param array  $fields Fields.
	 * @return Response
	 */
	private function call( $method, $path, Target $target, array $fields ) {
		$url = self::BASE . $path;
		if ( 'GET' === $method && array() !== $fields ) {
			$url   .= '?' . http_build_query( $fields, '', '&' );
			$fields = array();
		}
		return $this->transport->request( $method, $url, array( 'Authorization' => 'Bearer ' . $target->token() ), $fields );
	}
}
