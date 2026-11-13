<?php
/**
 * Google Business Profile adapter: updates, offers and events (localPosts,
 * still on the v4 API). Uses WPBot Core's Google connection for the token.
 *
 * Source: https://developers.google.com/my-business/reference/rest/v4/accounts.locations.localPosts
 * (topicType STANDARD, EVENT, OFFER; EVENT and OFFER need an event).
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Networks;

use QuantumCloud\Social\Http\Response;
use QuantumCloud\Social\Http\Transport;
use QuantumCloud\Social\Http\Wp_Transport;
use QuantumCloud\Social\Support\Redact;

defined( 'ABSPATH' ) || exit;

/**
 * Google Business Profile adapter.
 */
class Google_Business implements Network {

	/**
	 * API base.
	 */
	const BASE = 'https://mybusiness.googleapis.com/v4/';

	/**
	 * Post types: draft extra "gbp_type".
	 *
	 * @var string[]
	 */
	const TYPES = array( 'update', 'offer', 'event' );

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
		return 'google_business';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Google Business Profile', 'chatbot' );
	}

	/**
	 * Limits.
	 *
	 * @return array<string,mixed>
	 */
	public function limits() {
		return array(
			'max_text'           => 1500,
			'max_images'         => 1,
			'image_mimes'        => array( 'image/jpeg', 'image/png' ),
			'needs_image'        => false,
			'needs_public_media' => true,
			'link_in_text'       => false,
		);
	}

	/**
	 * Validate, including offer and event fields.
	 *
	 * @param Draft $draft Draft.
	 * @return string[]
	 */
	public function validate( Draft $draft ) {
		$problems = array();
		$type     = $this->type( $draft );
		if ( '' === trim( $draft->text() ) ) {
			$problems[] = __( 'Google posts need some text.', 'chatbot' );
		}
		if ( mb_strlen( $draft->text() ) > 1500 ) {
			$problems[] = __( 'Google posts can be up to 1,500 characters.', 'chatbot' );
		}
		if ( preg_match( '/\+?\d[\d\s().-]{7,}\d/', $draft->text() ) ) {
			$problems[] = __( 'Google rejects posts that include a phone number in the text. Use the button instead.', 'chatbot' );
		}
		if ( in_array( $type, array( 'offer', 'event' ), true ) ) {
			if ( '' === trim( (string) $draft->extra( 'gbp_title', '' ) ) ) {
				$problems[] = __( 'Offers and events need a title.', 'chatbot' );
			}
			$start = strtotime( (string) $draft->extra( 'gbp_start', '' ) );
			$end   = strtotime( (string) $draft->extra( 'gbp_end', '' ) );
			if ( ! $start || ! $end || $end < $start ) {
				$problems[] = __( 'Offers and events need a start and an end date (end after start).', 'chatbot' );
			}
		}
		return $problems;
	}

	/**
	 * The localPost body for a draft.
	 *
	 * @param Draft $draft Draft.
	 * @return array
	 */
	public function body( Draft $draft ) {
		$type   = $this->type( $draft );
		$body   = array(
			'languageCode' => (string) $draft->extra( 'language', 'en' ),
			'summary'      => $draft->text(),
			'topicType'    => array(
				'update' => 'STANDARD',
				'offer'  => 'OFFER',
				'event'  => 'EVENT',
			)[ $type ],
		);
		$images = $draft->images();
		if ( array() !== $images ) {
			$body['media'] = array(
				array(
					'mediaFormat' => 'PHOTO',
					'sourceUrl'   => $images[0]['url'],
				),
			);
		}
		if ( '' !== $draft->link() && 'offer' !== $type ) {
			$body['callToAction'] = array(
				'actionType' => (string) $draft->extra( 'gbp_button', 'LEARN_MORE' ),
				'url'        => $draft->link(),
			);
		}
		if ( 'update' !== $type ) {
			$body['event'] = array(
				'title'    => (string) $draft->extra( 'gbp_title', '' ),
				'schedule' => array(
					'startDate' => self::date( (string) $draft->extra( 'gbp_start', '' ) ),
					'endDate'   => self::date( (string) $draft->extra( 'gbp_end', '' ) ),
				),
			);
		}
		if ( 'offer' === $type ) {
			$body['offer'] = array_filter(
				array(
					'couponCode'      => (string) $draft->extra( 'gbp_coupon', '' ),
					'redeemOnlineUrl' => $draft->link(),
					'termsConditions' => (string) $draft->extra( 'gbp_terms', '' ),
				)
			);
		}
		return $body;
	}

	/**
	 * Publish.
	 *
	 * @param Target $target Target (account id = accounts/x/locations/y; token = Google access token).
	 * @param Draft  $draft  Draft.
	 * @return Publish_Result
	 */
	public function publish( Target $target, Draft $draft ) {
		$response = $this->call( 'POST', $target->account_id() . '/localPosts', $target, $this->body( $draft ) );
		if ( $response->ok() && ! empty( $response->body['name'] ) ) {
			return Publish_Result::done( (string) $response->body['name'], isset( $response->body['searchUrl'] ) ? (string) $response->body['searchUrl'] : '' );
		}
		if ( $response->no_response() || $response->code >= 500 ) {
			return Publish_Result::unknown( $response->no_response() ? 'timeout' : 'server_error', __( 'Google did not answer clearly. WPBot will check the profile before trying again.', 'chatbot' ) );
		}
		$message = isset( $response->body['error']['message'] ) ? Redact::secrets( (string) $response->body['error']['message'] ) : (string) $response->code;
		if ( 401 === $response->code ) {
			$result                = Publish_Result::failed( 'auth_expired', __( 'The Google connection expired. Reconnect Google in Social › Accounts › Google Business Profile, then retry.', 'chatbot' ) );
			$result->pause_account = true;
			return $result;
		}
		if ( 429 === $response->code ) {
			return Publish_Result::failed( 'rate_limited', __( 'Google is limiting requests (or API access is not approved yet). WPBot will try again later.', 'chatbot' ), true );
		}
		if ( 403 === $response->code ) {
			return Publish_Result::failed( 'permission', __( 'Google refused: the Business Profile API is not enabled or approved for your Google project.', 'chatbot' ) );
		}
		/* translators: %s: Google's error message. */
		return Publish_Result::failed( 'bad_request', sprintf( __( 'Google refused the post: %s', 'chatbot' ), $message ) );
	}

	/**
	 * Look for the post among the location's recent posts.
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
		$response = $this->call( 'GET', $target->account_id() . '/localPosts?pageSize=10', $target, array() );
		if ( ! $response->ok() ) {
			return new Reconcile_Result( Reconcile_Result::CANNOT_CHECK, '', __( 'WPBot could not read the profile\'s posts. Please check Google and confirm.', 'chatbot' ) );
		}
		foreach ( isset( $response->body['localPosts'] ) ? (array) $response->body['localPosts'] : array() as $post ) {
			if ( isset( $post['name'], $post['summary'] ) && $draft->fingerprint( (string) $post['summary'] ) === $draft->fingerprint() ) {
				return new Reconcile_Result( Reconcile_Result::FOUND, (string) $post['name'] );
			}
		}
		return Settled_Lookup::missed( $target );
	}

	/**
	 * Post type from the draft.
	 *
	 * @param Draft $draft Draft.
	 * @return string
	 */
	private function type( Draft $draft ) {
		$type = (string) $draft->extra( 'gbp_type', 'update' );
		return in_array( $type, self::TYPES, true ) ? $type : 'update';
	}

	/**
	 * Google Date object.
	 *
	 * @param string $date Date string.
	 * @return array{year:int, month:int, day:int}
	 */
	private static function date( $date ) {
		$time = strtotime( $date );
		$time = $time ? $time : time();
		return array(
			'year'  => (int) gmdate( 'Y', $time ),
			'month' => (int) gmdate( 'n', $time ),
			'day'   => (int) gmdate( 'j', $time ),
		);
	}

	/**
	 * Call the API (JSON body).
	 *
	 * @param string $method Method.
	 * @param string $path   Path after v4/.
	 * @param Target $target Target.
	 * @param array  $body   JSON body.
	 * @return Response
	 */
	private function call( $method, $path, Target $target, array $body ) {
		return $this->transport->request(
			$method,
			self::BASE . $path,
			array(
				'Authorization' => 'Bearer ' . $target->token(),
				'Content-Type'  => 'application/json',
			),
			array() === $body ? array() : array( '__json' => wp_json_encode( $body ) )
		);
	}
}
