<?php
/**
 * Facebook Page adapter (Graph API, Page access token).
 *
 * Sources: https://developers.facebook.com/docs/pages-api/getting-started/
 * and https://developers.facebook.com/docs/graph-api/reference/page/feed/
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

use QuantumCloud\Social\Http\Transport;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes text, link and single-image posts to a Facebook Page.
 */
class Facebook implements Network {

	/**
	 * How far back reconcile() looks for the post, in seconds.
	 */
	const RECONCILE_WINDOW = 900;

	/**
	 * Graph client.
	 *
	 * @var Meta_Graph
	 */
	protected $graph;

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
		return 'facebook';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Facebook', 'chatbot' );
	}

	/**
	 * Limits.
	 *
	 * @return array<string,mixed>
	 */
	public function limits() {
		return array(
			'max_text'           => 63206,
			/** This filter is documented in src/Networks/Instagram.php */
			'max_images'         => max( 1, min( 10, (int) apply_filters( 'qcld_wpbot_social/max_images', 1, 'facebook' ) ) ),
			'image_mimes'        => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
			'needs_image'        => false,
			'needs_public_media' => true,
			'link_in_text'       => false,
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
		if ( '' === trim( $draft->text() ) && array() === $draft->images() && '' === $draft->link() ) {
			$problems[] = __( 'Add some text, a link or an image.', 'chatbot' );
		}
		if ( mb_strlen( $draft->text() ) > $limits['max_text'] ) {
			/* translators: %d: character limit. */
			$problems[] = sprintf( __( 'Facebook posts can be up to %d characters.', 'chatbot' ), $limits['max_text'] );
		}
		if ( count( $draft->images() ) > $limits['max_images'] ) {
			$problems[] = 1 === $limits['max_images'] ? __( 'Facebook posts here take one image.', 'chatbot' ) : sprintf( /* translators: %d: image limit. */ __( 'Facebook posts take up to %d photos.', 'chatbot' ), $limits['max_images'] );
		}
		foreach ( $draft->images() as $image ) {
			if ( ! in_array( $image['mime'], $limits['image_mimes'], true ) ) {
				$problems[] = __( 'Facebook accepts JPEG, PNG, GIF or WebP images.', 'chatbot' );
			}
		}
		return $problems;
	}

	/**
	 * Publish: one request, so either done, failed or unknown.
	 *
	 * @param Target $target Target.
	 * @param Draft  $draft  Draft.
	 * @return Publish_Result
	 */
	public function publish( Target $target, Draft $draft ) {
		$page   = rawurlencode( $target->account_id() );
		$images = $draft->images();
		if ( count( $images ) > 1 ) {
			// Several photos: upload each unpublished (publishes nothing, safe to retry), then one feed post with them attached.
			$fields = array( 'message' => $this->text_with_link( $draft ) );
			foreach ( array_slice( $images, 0, 10 ) as $i => $image ) {
				$photo = $this->graph->call(
					'POST',
					'/' . $page . '/photos',
					$target,
					array(
						'url'       => $image['url'],
						'published' => 'false',
					)
				);
				if ( ! $photo->ok() || empty( $photo->body['id'] ) ) {
					$result = Meta_Graph::classify( $photo, false );
					return Publish_Result::UNKNOWN === $result->status ? Publish_Result::failed( $result->code, $result->message, true ) : $result;
				}
				$fields[ 'attached_media[' . $i . ']' ] = (string) wp_json_encode( array( 'media_fbid' => (string) $photo->body['id'] ) );
			}
			$path = '/' . $page . '/feed';
		} elseif ( array() !== $images ) {
			$fields = array(
				'url'     => $images[0]['url'],
				'caption' => $this->text_with_link( $draft ),
			);
			$path   = '/' . $page . '/photos';
		} else {
			$fields = array( 'message' => $draft->text() );
			if ( '' !== $draft->link() ) {
				$fields['link'] = $draft->link();
			}
			$path = '/' . $page . '/feed';
		}

		$response = $this->graph->call( 'POST', $path, $target, $fields );
		if ( ! $response->ok() ) {
			return Meta_Graph::classify( $response, true );
		}
		$id = isset( $response->body['post_id'] ) ? (string) $response->body['post_id'] : ( isset( $response->body['id'] ) ? (string) $response->body['id'] : '' );
		if ( '' === $id ) {
			return Publish_Result::unknown( 'no_id', __( 'Facebook accepted the request but returned no post id. WPBot will check the Page before trying again.', 'chatbot' ) );
		}
		return Publish_Result::done( $id, 'https://www.facebook.com/' . rawurlencode( $id ) );
	}

	/**
	 * Look for the post on the Page's recent feed, matched by text.
	 *
	 * The feed can lag the write, so this follows Settled_Lookup: no look
	 * before five minutes, and "not found" only after two empty reads.
	 *
	 * @param Target $target Target.
	 * @param Draft  $draft  Draft.
	 * @return Reconcile_Result
	 */
	public function reconcile( Target $target, Draft $draft ) {
		$early = Settled_Lookup::too_early( $target );
		if ( $early ) {
			return $early;
		}
		$since    = max( 0, $target->attempt_started() - 60 );
		$response = $this->graph->call(
			'GET',
			'/' . rawurlencode( $target->account_id() ) . '/feed',
			$target,
			array(
				'fields' => 'id,message,created_time,is_published',
				'since'  => $since,
				'limit'  => 25,
			)
		);
		if ( ! $response->ok() ) {
			return Meta_Graph::reconcile_error( $response, __( 'WPBot could not read the Page to check the post. Please check Facebook and confirm.', 'chatbot' ) );
		}
		$wanted = $draft->fingerprint( array() !== $draft->images() ? $this->text_with_link( $draft ) : $draft->text() );
		$posts  = isset( $response->body['data'] ) ? (array) $response->body['data'] : array();
		foreach ( $posts as $post ) {
			if ( isset( $post['message'], $post['id'] ) && $draft->fingerprint( (string) $post['message'] ) === $wanted ) {
				return new Reconcile_Result( Reconcile_Result::FOUND, (string) $post['id'] );
			}
		}
		return Settled_Lookup::missed( $target );
	}

	/**
	 * Photo captions carry the link in the text (photos have no link field).
	 *
	 * @param Draft $draft Draft.
	 * @return string
	 */
	private function text_with_link( Draft $draft ) {
		return '' === $draft->link() ? $draft->text() : trim( $draft->text() . "\n\n" . $draft->link() );
	}
}
