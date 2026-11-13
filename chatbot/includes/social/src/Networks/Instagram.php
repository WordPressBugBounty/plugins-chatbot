<?php
/**
 * Instagram professional account adapter (single image, container flow).
 *
 * Flow: POST /{ig-user}/media (container) → GET /{container}?fields=status_code
 * until FINISHED → POST /{ig-user}/media_publish. The container id is saved
 * as state between steps, so a lost publish answer can be reconciled from
 * the container status (PUBLISHED) instead of posting twice.
 * Sources: https://developers.facebook.com/docs/instagram-platform/content-publishing/
 * and https://developers.facebook.com/documentation/instagram-platform/instagram-graph-api/reference/ig-container.md
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

use QuantumCloud\Social\Http\Transport;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes single-image posts to an Instagram professional account.
 */
class Instagram implements Network {

	/**
	 * Graph client.
	 *
	 * @var Meta_Graph
	 */
	protected $graph;

	/**
	 * The last container status response (for reconcile errors).
	 *
	 * @var \QuantumCloud\Social\Http\Response|null
	 */
	private $last_status_response;

	/**
	 * Build.
	 *
	 * @param Transport|null $transport Transport for tests or replay.
	 */
	public function __construct( ?Transport $transport = null ) {
		$this->graph = new Meta_Graph( $transport );
	}

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'instagram';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Instagram', 'chatbot' );
	}

	/**
	 * Limits (JPEG only; 2,200-character captions; 30 hashtags).
	 *
	 * @return array<string,mixed>
	 */
	public function limits() {
		return array(
			'max_text'           => 2200,
			'max_hashtags'       => 30,
			/**
			 * Images per post: 1, or up to 10 for a carousel when the Master module allows it.
			 *
			 * @param int    $max     Images.
			 * @param string $network Network id.
			 */
			'max_images'         => max( 1, min( 10, (int) apply_filters( 'qcld_wpbot_social/max_images', 1, 'instagram' ) ) ),
			'image_mimes'        => array( 'image/jpeg' ),
			'needs_image'        => true,
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
		$limits   = $this->limits();
		$images   = $draft->images();
		$video    = $draft->extra( 'video', null );
		if ( array() === $images && ! is_array( $video ) ) {
			$problems[] = __( 'Instagram posts need an image or a video.', 'chatbot' );
		}
		if ( is_array( $video ) && 'story' === $draft->extra( 'ig_format', '' ) && ! empty( $video['seconds'] ) && (int) $video['seconds'] > 60 ) {
			$problems[] = __( 'Instagram Stories take videos up to 60 seconds.', 'chatbot' );
		}
		if ( is_array( $video ) && ! empty( $video['seconds'] ) && (int) $video['seconds'] > 900 ) {
			$problems[] = __( 'Instagram Reels take videos up to 15 minutes.', 'chatbot' );
		}
		if ( count( $images ) > $limits['max_images'] ) {
			$problems[] = 1 === $limits['max_images']
				? __( 'Instagram posts here take one image.', 'chatbot' )
				/* translators: %d: image limit. */
				: sprintf( __( 'An Instagram carousel takes up to %d images.', 'chatbot' ), $limits['max_images'] );
		}
		foreach ( $images as $image ) {
			if ( ! in_array( $image['mime'], $limits['image_mimes'], true ) ) {
				$problems[] = __( 'Instagram only accepts JPEG images. WPBot can convert the image for you.', 'chatbot' );
			}
		}
		if ( mb_strlen( $draft->text() ) > $limits['max_text'] ) {
			/* translators: %d: character limit. */
			$problems[] = sprintf( __( 'Instagram captions can be up to %d characters.', 'chatbot' ), $limits['max_text'] );
		}
		if ( preg_match_all( '/(^|\s)#[\p{L}\p{N}_]+/u', $draft->text() ) > $limits['max_hashtags'] ) {
			/* translators: %d: hashtag limit. */
			$problems[] = sprintf( __( 'Instagram allows up to %d hashtags.', 'chatbot' ), $limits['max_hashtags'] );
		}
		return $problems;
	}

	/**
	 * Publish in steps: container, status, publish.
	 *
	 * @param Target $target Target (state may hold container_id).
	 * @param Draft  $draft  Draft.
	 * @return Publish_Result
	 */
	public function publish( Target $target, Draft $draft ) {
		$user      = rawurlencode( $target->account_id() );
		$container = (string) $target->state( 'container_id', '' );

		// Step 1: create the container. A lost answer here is safe to retry:
		// a container on its own publishes nothing and expires after 24 hours.
		if ( '' === $container ) {
			$images = $draft->images();
			$fields = array(
				'image_url' => isset( $images[0]['url'] ) ? $images[0]['url'] : '',
				'caption'   => $draft->text(),
			);
			$video  = $draft->extra( 'video', null );
			$format = (string) $draft->extra( 'ig_format', '' );
			if ( is_array( $video ) && ! empty( $video['url'] ) ) {
				// Reels (feed) or Stories; Instagram processes the video, so the status check below may say "in progress" for a while.
				$fields = array(
					'media_type' => 'story' === $format ? 'STORIES' : 'REELS',
					'video_url'  => (string) $video['url'],
				);
				if ( 'story' !== $format ) {
					$fields['caption']       = $draft->text();
					$fields['share_to_feed'] = 'true';
				}
			} elseif ( 'story' === $format && array() !== $images ) {
				$fields = array(
					'media_type' => 'STORIES',
					'image_url'  => $images[0]['url'],
				);
			} elseif ( count( $images ) > 1 ) {
				// Carousel: one child container per image, then the carousel container.
				// Children on their own publish nothing and expire, so a retry is safe.
				$children = array();
				foreach ( array_slice( $images, 0, 10 ) as $image ) {
					$child = $this->graph->call(
						'POST',
						'/' . $user . '/media',
						$target,
						array(
							'image_url'        => $image['url'],
							'is_carousel_item' => 'true',
						)
					);
					if ( ! $child->ok() || empty( $child->body['id'] ) ) {
						$result = Meta_Graph::classify( $child, false );
						return Publish_Result::UNKNOWN === $result->status ? Publish_Result::failed( $result->code, $result->message, true ) : $result;
					}
					$children[] = (string) $child->body['id'];
				}
				$fields = array(
					'media_type' => 'CAROUSEL',
					'children'   => implode( ',', $children ),
					'caption'    => $draft->text(),
				);
			}
			$response = $this->graph->call( 'POST', '/' . $user . '/media', $target, $fields );
			if ( ! $response->ok() ) {
				$result = Meta_Graph::classify( $response, false );
				return Publish_Result::UNKNOWN === $result->status ? Publish_Result::failed( $result->code, $result->message, true ) : $result;
			}
			$container = isset( $response->body['id'] ) ? (string) $response->body['id'] : '';
			if ( '' === $container ) {
				return Publish_Result::failed( 'no_id', __( 'Instagram did not return a media container. WPBot will try again shortly.', 'chatbot' ), true );
			}
		}
		$state = array( 'container_id' => $container );

		// Step 2: one status check per step (QA #39); still processing means
		// "pending" and the scheduler comes back later.
		list( $status, $error ) = $this->container_status( $target, $container );
		if ( $error ) {
			// QA #38: an expired token pauses the account, a rate limit backs
			// off; the container is kept so the retry does not make another.
			$error->state = $state;
			return $error;
		}
		if ( 'PUBLISHED' === $status ) {
			return $this->find_published( $target, $draft, $state );
		}
		if ( 'IN_PROGRESS' === $status ) {
			return Publish_Result::pending( $state, __( 'Instagram is still processing the image.', 'chatbot' ) );
		}
		if ( 'FINISHED' !== $status ) {
			return Publish_Result::failed( 'media_error', __( 'Instagram could not process the image (it must be a public JPEG). Check the image and retry.', 'chatbot' ), false );
		}

		// Step 3: publish. A lost answer here is ambiguous.
		$response = $this->graph->call( 'POST', '/' . $user . '/media_publish', $target, array( 'creation_id' => $container ) );
		if ( ! $response->ok() ) {
			$result = Meta_Graph::classify( $response, true );
			if ( 'media_not_ready' === $result->code ) {
				return Publish_Result::pending( $state, $result->message );
			}
			$result->state = $state;
			return $result;
		}
		if ( empty( $response->body['id'] ) ) {
			return Publish_Result::unknown( 'no_id', __( 'Instagram accepted the request but returned no post id. WPBot will check before trying again.', 'chatbot' ), $state );
		}
		return Publish_Result::done( (string) $response->body['id'] );
	}

	/**
	 * Reconcile from the saved container's status.
	 *
	 * @param Target $target Target with state container_id.
	 * @param Draft  $draft  Draft.
	 * @return Reconcile_Result
	 */
	public function reconcile( Target $target, Draft $draft ) {
		$container = (string) $target->state( 'container_id', '' );
		if ( '' === $container ) {
			// No container means nothing could have been published.
			return new Reconcile_Result( Reconcile_Result::NOT_FOUND );
		}
		$early = Settled_Lookup::too_early( $target );
		if ( $early ) {
			return $early;
		}
		list( $status, $error ) = $this->container_status( $target, $container );
		if ( $error ) {
			return Meta_Graph::reconcile_error( $this->last_status_response, __( 'WPBot could not check Instagram. Please check the account and confirm.', 'chatbot' ) );
		}
		if ( 'PUBLISHED' === $status ) {
			$found = $this->find_published( $target, $draft, array() );
			return Publish_Result::DONE === $found->status
				? new Reconcile_Result( Reconcile_Result::FOUND, $found->remote_id )
				: new Reconcile_Result( Reconcile_Result::FOUND, '', __( 'Published on Instagram; the post link will appear after the next metrics check.', 'chatbot' ) );
		}
		if ( 'EXPIRED' === $status || 'ERROR' === $status ) {
			// The container can no longer be published: definitely not posted.
			return new Reconcile_Result( Reconcile_Result::NOT_FOUND );
		}
		if ( 'FINISHED' === $status ) {
			// Ready but not published yet. Meta may still be finishing the
			// publish call, so look again before saying "not found".
			return Settled_Lookup::missed( $target );
		}
		return Reconcile_Result::wait( Settled_Lookup::GAP, array(), 'Instagram is still processing the post.' );
	}

	/**
	 * Container status_code and, when it could not be read, the classified error.
	 *
	 * @param Target $target    Target.
	 * @param string $container Container id.
	 * @return array{0:string,1:Publish_Result|null}
	 */
	private function container_status( Target $target, $container ) {
		$this->last_status_response = $this->graph->call( 'GET', '/' . rawurlencode( $container ), $target, array( 'fields' => 'status_code' ) );
		return Meta_Graph::container_status( $this->last_status_response, 'status_code' );
	}

	/**
	 * Find the media id of a published container by matching the caption.
	 *
	 * @param Target $target Target.
	 * @param Draft  $draft  Draft.
	 * @param array  $state  State to keep.
	 * @return Publish_Result
	 */
	private function find_published( Target $target, Draft $draft, array $state ) {
		$response = $this->graph->call(
			'GET',
			'/' . rawurlencode( $target->account_id() ) . '/media',
			$target,
			array(
				'fields' => 'id,caption,timestamp,permalink',
				'limit'  => 10,
			)
		);
		if ( $response->ok() && isset( $response->body['data'] ) ) {
			foreach ( (array) $response->body['data'] as $media ) {
				if ( isset( $media['id'], $media['caption'] ) && $draft->fingerprint( (string) $media['caption'] ) === $draft->fingerprint() ) {
					return Publish_Result::done( (string) $media['id'], isset( $media['permalink'] ) ? (string) $media['permalink'] : '' );
				}
			}
		}
		return Publish_Result::unknown( 'published_no_id', __( 'Instagram shows the post as published, but WPBot could not find its link yet.', 'chatbot' ), $state );
	}
}
