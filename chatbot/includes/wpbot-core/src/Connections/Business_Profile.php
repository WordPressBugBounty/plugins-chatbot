<?php
/**
 * Google Business Profile through the owner's own Google connection
 * (round 3, I and J): accounts and locations, a location's profile
 * (name, address, phone, website, hours, special hours, categories, place
 * ID), its reviews and a reply on the owner's click, and (Pro) its
 * performance: calls, website clicks, direction requests, views, and the
 * searches people used to find it.
 *
 * Google's Business Profile APIs need access approval for the owner's
 * Google Cloud project; until then calls fail with a quota of 0 or a
 * permission error, which explain() turns into the step to take.
 * Scope business.manage (no read-only scope exists) is asked for only when
 * a module turns Business Profile on (filter qcld_wpbot_core/business_profile_enabled).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Google Business Profile.
 */
final class Business_Profile {

	const SCOPE      = 'https://www.googleapis.com/auth/business.manage';
	const ACCESS_URL = 'https://developers.google.com/my-business/content/prereqs#request-access';
	const READ_MASK  = 'name,title,storefrontAddress,phoneNumbers,websiteUri,regularHours,specialHours,categories,metadata';
	const STATE      = 'qcld_wpbot_core_gbp_state';

	/**
	 * Is Business Profile wanted by a module?
	 *
	 * @return bool
	 */
	public static function wanted() {
		/**
		 * Use Google Business Profile (adds the business.manage permission to the Google connection).
		 *
		 * @param bool $on Default false.
		 */
		return (bool) apply_filters( 'qcld_wpbot_core/business_profile_enabled', false );
	}

	/**
	 * State for screens: off, not_connected, reconnect, pending (no API access yet), connected.
	 *
	 * @return array status, message.
	 */
	public static function status() {
		if ( ! self::wanted() ) {
			return array(
				'status'  => 'off',
				'message' => '',
			);
		}
		if ( ! Google::connected() ) {
			return array(
				'status'  => 'not_connected',
				'message' => __( 'Connect Google first.', 'chatbot' ),
			);
		}
		if ( ! Google::has_scope( self::SCOPE ) ) {
			return array(
				'status'  => 'reconnect',
				'message' => __( 'Reconnect Google once to allow Business Profile.', 'chatbot' ),
			);
		}
		$state = get_option( self::STATE );
		if ( is_array( $state ) && ! empty( $state['pending'] ) && (int) $state['at'] > time() - DAY_IN_SECONDS ) {
			return array(
				'status'  => 'pending',
				'message' => (string) $state['message'],
			);
		}
		return array(
			'status'  => 'connected',
			'message' => '',
		);
	}

	/**
	 * Accounts the owner can manage.
	 *
	 * @return array|WP_Error List of name (accounts/123), label.
	 */
	public static function accounts() {
		$data = self::call( 'GET', '/v1/accounts?pageSize=20', null, 'gbpaccount' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['accounts'] ) ? (array) $data['accounts'] : array() as $account ) {
			if ( ! empty( $account['name'] ) ) {
				$out[] = array(
					'name'  => sanitize_text_field( (string) $account['name'] ),
					'label' => isset( $account['accountName'] ) ? sanitize_text_field( (string) $account['accountName'] ) : (string) $account['name'],
				);
			}
		}
		return $out;
	}

	/**
	 * Locations of all accounts (cached for an hour).
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array|WP_Error List of account, name (locations/456), title, address, place_id.
	 */
	public static function locations( $fresh = false ) {
		$cached = get_transient( 'qcld_wpbot_core_gbp_locations' );
		if ( ! $fresh && is_array( $cached ) ) {
			return $cached;
		}
		$accounts = self::accounts();
		if ( is_wp_error( $accounts ) ) {
			return $accounts;
		}
		$out = array();
		foreach ( $accounts as $account ) {
			$data = self::call( 'GET', '/v1/' . $account['name'] . '/locations?pageSize=100&readMask=' . rawurlencode( self::READ_MASK ), null, 'gbpinfo' );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			foreach ( isset( $data['locations'] ) ? (array) $data['locations'] : array() as $location ) {
				$profile = self::shape( $location );
				if ( '' !== $profile['name'] ) {
					$profile['account'] = $account['name'];
					$out[]              = $profile;
				}
			}
		}
		set_transient( 'qcld_wpbot_core_gbp_locations', $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * One location's profile.
	 *
	 * @param string $name locations/456.
	 * @return array|WP_Error
	 */
	public static function location( $name ) {
		$name = self::clean_name( $name, 'locations' );
		if ( '' === $name ) {
			return new WP_Error( 'qcld_wpbot_core_gbp_invalid', __( 'Choose a Business Profile location.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$data = self::call( 'GET', '/v1/' . $name . '?readMask=' . rawurlencode( self::READ_MASK ), null, 'gbpinfo' );
		return is_wp_error( $data ) ? $data : self::shape( $data );
	}

	/**
	 * Reviews of a location, newest first.
	 *
	 * @param string $account accounts/123.
	 * @param string $name    locations/456.
	 * @param string $page    Page token.
	 * @return array|WP_Error reviews (id, stars, text, reviewer, created, updated, reply, reply_at), average, total, next.
	 */
	public static function reviews( $account, $name, $page = '' ) {
		$account = self::clean_name( $account, 'accounts' );
		$name    = self::clean_name( $name, 'locations' );
		if ( '' === $account || '' === $name ) {
			return new WP_Error( 'qcld_wpbot_core_gbp_invalid', __( 'Link a Business Profile location first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$query = 'pageSize=50&orderBy=' . rawurlencode( 'updateTime desc' ) . ( '' !== (string) $page ? '&pageToken=' . rawurlencode( (string) $page ) : '' );
		$data  = self::call( 'GET', '/v4/' . $account . '/' . $name . '/reviews?' . $query, null, 'gbpreviews' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$stars = array(
			'ONE'   => 1,
			'TWO'   => 2,
			'THREE' => 3,
			'FOUR'  => 4,
			'FIVE'  => 5,
		);
		$out   = array();
		foreach ( isset( $data['reviews'] ) ? (array) $data['reviews'] : array() as $review ) {
			$id = isset( $review['reviewId'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $review['reviewId'] ) : '';
			if ( '' === $id ) {
				continue;
			}
			$out[] = array(
				'id'       => $id,
				'stars'    => isset( $review['starRating'], $stars[ $review['starRating'] ] ) ? $stars[ $review['starRating'] ] : 0,
				'text'     => isset( $review['comment'] ) ? sanitize_textarea_field( (string) $review['comment'] ) : '',
				'reviewer' => isset( $review['reviewer']['displayName'] ) ? sanitize_text_field( (string) $review['reviewer']['displayName'] ) : '',
				'created'  => isset( $review['createTime'] ) ? substr( sanitize_text_field( (string) $review['createTime'] ), 0, 19 ) : '',
				'updated'  => isset( $review['updateTime'] ) ? substr( sanitize_text_field( (string) $review['updateTime'] ), 0, 19 ) : '',
				'reply'    => isset( $review['reviewReply']['comment'] ) ? sanitize_textarea_field( (string) $review['reviewReply']['comment'] ) : '',
				'reply_at' => isset( $review['reviewReply']['updateTime'] ) ? substr( sanitize_text_field( (string) $review['reviewReply']['updateTime'] ), 0, 19 ) : '',
			);
		}
		return array(
			'reviews' => $out,
			'average' => isset( $data['averageRating'] ) ? round( (float) $data['averageRating'], 1 ) : null,
			'total'   => isset( $data['totalReviewCount'] ) ? (int) $data['totalReviewCount'] : count( $out ),
			'next'    => isset( $data['nextPageToken'] ) ? sanitize_text_field( (string) $data['nextPageToken'] ) : '',
		);
	}

	/**
	 * Post (or update) the owner's reply to a review. Only ever called on the owner's click.
	 *
	 * @param string $account   accounts/123.
	 * @param string $name      locations/456.
	 * @param string $review_id Review id.
	 * @param string $comment   Reply (plain text, up to 4,000 characters).
	 * @return array|WP_Error comment, updated.
	 */
	public static function reply( $account, $name, $review_id, $comment ) {
		$account   = self::clean_name( $account, 'accounts' );
		$name      = self::clean_name( $name, 'locations' );
		$review_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $review_id );
		$comment   = trim( sanitize_textarea_field( (string) $comment ) );
		if ( '' === $account || '' === $name || '' === $review_id || '' === $comment ) {
			return new WP_Error( 'qcld_wpbot_core_gbp_invalid', __( 'Write a reply first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$comment = function_exists( 'mb_substr' ) ? mb_substr( $comment, 0, 4000 ) : substr( $comment, 0, 4000 );
		$data    = self::call( 'PUT', '/v4/' . $account . '/' . $name . '/reviews/' . $review_id . '/reply', array( 'comment' => $comment ), 'gbpreviews' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'comment' => isset( $data['comment'] ) ? sanitize_textarea_field( (string) $data['comment'] ) : $comment,
			'updated' => isset( $data['updateTime'] ) ? substr( sanitize_text_field( (string) $data['updateTime'] ), 0, 19 ) : gmdate( 'Y-m-d\TH:i:s' ),
		);
	}

	/**
	 * Performance for the last days: calls, website clicks, direction requests, views (Pro uses it).
	 *
	 * @param string $name locations/456.
	 * @param int    $days Days (7–180).
	 * @return array|WP_Error calls, website, directions, views (totals), days.
	 */
	public static function performance( $name, $days = 30 ) {
		$name  = self::clean_name( $name, 'locations' );
		$days  = max( 7, min( 180, (int) $days ) );
		$end   = time() - 3 * DAY_IN_SECONDS;
		$from  = $end - ( $days - 1 ) * DAY_IN_SECONDS;
		$map   = array(
			'CALL_CLICKS'                         => 'calls',
			'WEBSITE_CLICKS'                      => 'website',
			'BUSINESS_DIRECTION_REQUESTS'         => 'directions',
			'BUSINESS_IMPRESSIONS_DESKTOP_MAPS'   => 'views',
			'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH' => 'views',
			'BUSINESS_IMPRESSIONS_MOBILE_MAPS'    => 'views',
			'BUSINESS_IMPRESSIONS_MOBILE_SEARCH'  => 'views',
		);
		$query = array();
		foreach ( array_keys( $map ) as $metric ) {
			$query[] = 'dailyMetrics=' . $metric;
		}
		$query[] = 'dailyRange.start_date.year=' . gmdate( 'Y', $from );
		$query[] = 'dailyRange.start_date.month=' . gmdate( 'n', $from );
		$query[] = 'dailyRange.start_date.day=' . gmdate( 'j', $from );
		$query[] = 'dailyRange.end_date.year=' . gmdate( 'Y', $end );
		$query[] = 'dailyRange.end_date.month=' . gmdate( 'n', $end );
		$query[] = 'dailyRange.end_date.day=' . gmdate( 'j', $end );
		$data    = self::call( 'GET', '/v1/' . $name . ':fetchMultiDailyMetricsTimeSeries?' . implode( '&', $query ), null, 'gbpperf' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array(
			'calls'      => 0,
			'website'    => 0,
			'directions' => 0,
			'views'      => 0,
			'days'       => $days,
		);
		foreach ( isset( $data['multiDailyMetricTimeSeries'] ) ? (array) $data['multiDailyMetricTimeSeries'] : array() as $group ) {
			foreach ( isset( $group['dailyMetricTimeSeries'] ) ? (array) $group['dailyMetricTimeSeries'] : array() as $series ) {
				$key = isset( $series['dailyMetric'], $map[ $series['dailyMetric'] ] ) ? $map[ $series['dailyMetric'] ] : '';
				if ( '' === $key ) {
					continue;
				}
				foreach ( isset( $series['timeSeries']['datedValues'] ) ? (array) $series['timeSeries']['datedValues'] : array() as $point ) {
					$out[ $key ] += isset( $point['value'] ) ? (int) $point['value'] : 0;
				}
			}
		}
		return $out;
	}

	/**
	 * Searches people used to find the profile, last full months (Pro uses it).
	 *
	 * @param string $name   locations/456.
	 * @param int    $months Months (1–6).
	 * @return array|WP_Error List of search, impressions (null when Google shows "under 15").
	 */
	public static function search_keywords( $name, $months = 3 ) {
		$name   = self::clean_name( $name, 'locations' );
		$months = max( 1, min( 6, (int) $months ) );
		$end    = strtotime( gmdate( 'Y-m-01' ) . ' -1 month' );
		$start  = strtotime( gmdate( 'Y-m-01', $end ) . ' -' . ( $months - 1 ) . ' months' );
		$query  = 'monthlyRange.start_month.year=' . gmdate( 'Y', $start ) . '&monthlyRange.start_month.month=' . gmdate( 'n', $start ) . '&monthlyRange.end_month.year=' . gmdate( 'Y', $end ) . '&monthlyRange.end_month.month=' . gmdate( 'n', $end ) . '&pageSize=50';
		$data   = self::call( 'GET', '/v1/' . $name . '/searchkeywords/impressions/monthly?' . $query, null, 'gbpperf' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['searchKeywordsCounts'] ) ? (array) $data['searchKeywordsCounts'] : array() as $row ) {
			$search = isset( $row['searchKeyword'] ) ? sanitize_text_field( (string) $row['searchKeyword'] ) : '';
			if ( '' === $search ) {
				continue;
			}
			$out[] = array(
				'search'      => $search,
				'impressions' => isset( $row['insightsValue']['value'] ) ? (int) $row['insightsValue']['value'] : null,
			);
		}
		return $out;
	}

	/**
	 * Google's answers in words the owner can act on.
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	public static function explain( WP_Error $error ) {
		$data   = (array) $error->get_error_data();
		$reason = isset( $data['reason'] ) ? strtolower( (string) $data['reason'] ) : '';
		$status = isset( $data['status'] ) ? (int) $data['status'] : 0;
		$text   = strtolower( $error->get_error_message() ) . ' ' . $reason;
		if ( 429 === $status || false !== strpos( $text, 'quota' ) || false !== strpos( $text, 'ratelimit' ) || false !== strpos( $text, 'service_disabled' ) || false !== strpos( $text, 'accessnotconfigured' ) ) {
			$message = sprintf(
				/* translators: %s: Google's access request page. */
				__( 'Google has not given your Google Cloud project access to the Business Profile APIs yet. Request access (%s), wait for Google\'s approval email, then turn on "My Business Account Management API", "My Business Business Information API" and "Business Profile Performance API" in your project.', 'chatbot' ),
				self::ACCESS_URL
			);
			update_option(
				self::STATE,
				array(
					'pending' => true,
					'message' => $message,
					'at'      => time(),
				),
				false
			);
			return new WP_Error( 'qcld_wpbot_core_gbp_pending', $message, array( 'status' => 403 ) );
		}
		if ( 403 === $status ) {
			return new WP_Error( 'qcld_wpbot_core_gbp_denied', __( 'Google refused access to this Business Profile. Sign in to Google with an account that owns or manages the profile, then reconnect.', 'chatbot' ), array( 'status' => 403 ) );
		}
		return $error;
	}

	/**
	 * A call that never flags the Search Console connection; success clears "pending".
	 *
	 * @param string     $method Method.
	 * @param string     $path   Path.
	 * @param array|null $body   Body.
	 * @param string     $base   Endpoint.
	 * @return array|WP_Error
	 */
	private static function call( $method, $path, $body, $base ) {
		$state = self::status();
		if ( in_array( $state['status'], array( 'off', 'not_connected', 'reconnect' ), true ) ) {
			return new WP_Error( 'qcld_wpbot_core_gbp_' . $state['status'], '' !== $state['message'] ? $state['message'] : __( 'Turn on Google Business Profile first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$data = Google::request( $method, $path, $body, $base, false );
		if ( is_wp_error( $data ) ) {
			return self::explain( $data );
		}
		delete_option( self::STATE );
		return $data;
	}

	/**
	 * A location from the API, shaped for modules.
	 *
	 * @param array $location API location.
	 * @return array name, title, address (street, city, region, postcode, country), phone, website, hours, special_hours, category, place_id, maps_url.
	 */
	public static function shape( $location ) {
		$location = (array) $location;
		$address  = isset( $location['storefrontAddress'] ) ? (array) $location['storefrontAddress'] : array();
		$hours    = array();
		foreach ( isset( $location['regularHours']['periods'] ) ? (array) $location['regularHours']['periods'] : array() as $period ) {
			$day = isset( $period['openDay'] ) ? ucfirst( strtolower( (string) $period['openDay'] ) ) : '';
			if ( '' !== $day ) {
				$hours[] = array(
					'day'   => $day,
					'open'  => self::time_of( isset( $period['openTime'] ) ? $period['openTime'] : array() ),
					'close' => self::time_of( isset( $period['closeTime'] ) ? $period['closeTime'] : array() ),
				);
			}
		}
		$special = array();
		foreach ( isset( $location['specialHours']['specialHourPeriods'] ) ? (array) $location['specialHours']['specialHourPeriods'] : array() as $period ) {
			if ( isset( $period['startDate']['year'], $period['startDate']['month'], $period['startDate']['day'] ) ) {
				$special[] = array(
					'date'   => sprintf( '%04d-%02d-%02d', (int) $period['startDate']['year'], (int) $period['startDate']['month'], (int) $period['startDate']['day'] ),
					'closed' => ! empty( $period['closed'] ),
				);
			}
		}
		$phone = isset( $location['phoneNumbers']['primaryPhone'] ) ? (string) $location['phoneNumbers']['primaryPhone'] : '';
		return array(
			'name'          => isset( $location['name'] ) ? sanitize_text_field( (string) $location['name'] ) : '',
			'title'         => isset( $location['title'] ) ? sanitize_text_field( (string) $location['title'] ) : '',
			'address'       => array(
				'street'   => isset( $address['addressLines'] ) ? sanitize_text_field( implode( ', ', array_map( 'strval', (array) $address['addressLines'] ) ) ) : '',
				'city'     => isset( $address['locality'] ) ? sanitize_text_field( (string) $address['locality'] ) : '',
				'region'   => isset( $address['administrativeArea'] ) ? sanitize_text_field( (string) $address['administrativeArea'] ) : '',
				'postcode' => isset( $address['postalCode'] ) ? sanitize_text_field( (string) $address['postalCode'] ) : '',
				'country'  => isset( $address['regionCode'] ) ? strtoupper( sanitize_text_field( (string) $address['regionCode'] ) ) : '',
			),
			'phone'         => sanitize_text_field( $phone ),
			'website'       => isset( $location['websiteUri'] ) ? esc_url_raw( (string) $location['websiteUri'] ) : '',
			'hours'         => $hours,
			'special_hours' => $special,
			'category'      => isset( $location['categories']['primaryCategory']['displayName'] ) ? sanitize_text_field( (string) $location['categories']['primaryCategory']['displayName'] ) : '',
			'place_id'      => isset( $location['metadata']['placeId'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $location['metadata']['placeId'] ) : '',
			'maps_url'      => isset( $location['metadata']['mapsUri'] ) ? esc_url_raw( (string) $location['metadata']['mapsUri'] ) : '',
		);
	}

	/**
	 * Google's review link for a place ID ('' without one).
	 *
	 * @param string $place_id Place ID.
	 * @return string
	 */
	public static function review_link( $place_id ) {
		$place_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $place_id );
		return '' === $place_id ? '' : 'https://search.google.com/local/writereview?placeid=' . $place_id;
	}

	/**
	 * "HH:MM" from Google's time of day.
	 *
	 * @param array $time hours, minutes.
	 * @return string
	 */
	private static function time_of( $time ) {
		$time = (array) $time;
		return sprintf( '%02d:%02d', isset( $time['hours'] ) ? (int) $time['hours'] : 0, isset( $time['minutes'] ) ? (int) $time['minutes'] : 0 );
	}

	/**
	 * A resource name such as accounts/123 or locations/456 ('' when invalid).
	 *
	 * @param string $name   Name.
	 * @param string $prefix accounts|locations.
	 * @return string
	 */
	private static function clean_name( $name, $prefix ) {
		return preg_match( '#^' . $prefix . '/[0-9]+$#', (string) $name ) ? (string) $name : '';
	}
}
