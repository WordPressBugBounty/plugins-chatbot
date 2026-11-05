<?php
/**
 * YouTube Data API v3 through the owner's own Google connection (round 3, B):
 * the videos that come up for a search, with their channel and views.
 * Read-only (youtube.readonly), free within Google's daily quota (a search
 * costs 100 of the 10,000 daily units). Results are cached for 7 days.
 *
 * The scope is asked for only when a module turns YouTube on
 * (filter qcld_wpbot_core/youtube_enabled); the owner then reconnects Google
 * once, as for Google Analytics.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * YouTube.
 */
final class YouTube {

	const SCOPE = 'https://www.googleapis.com/auth/youtube.readonly';

	/**
	 * Hook in: ask for the scope when a module turned YouTube on.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/google_scopes', array( __CLASS__, 'scope' ) );
	}

	/**
	 * Is YouTube wanted by a module?
	 *
	 * @return bool
	 */
	public static function wanted() {
		/**
		 * Use YouTube search (adds the read-only YouTube permission to the Google connection).
		 *
		 * @param bool $on Default false.
		 */
		return (bool) apply_filters( 'qcld_wpbot_core/youtube_enabled', false );
	}

	/**
	 * Add the scope.
	 *
	 * @param array $scopes Scopes.
	 * @return array
	 */
	public static function scope( $scopes ) {
		if ( self::wanted() ) {
			$scopes[] = self::SCOPE;
		}
		return $scopes;
	}

	/**
	 * State for screens.
	 *
	 * @return array status (off|not_connected|reconnect|connected).
	 */
	public static function status() {
		if ( ! self::wanted() ) {
			return array( 'status' => 'off' );
		}
		if ( ! Google::connected() ) {
			return array( 'status' => 'not_connected' );
		}
		return array( 'status' => Google::has_scope( self::SCOPE ) ? 'connected' : 'reconnect' );
	}

	/**
	 * Videos for a search.
	 *
	 * @param string $query   Search.
	 * @param array  $args    limit (1–10, default 5), region (ISO country), language.
	 * @return array|WP_Error List of title, url, channel, published, views, video_id; cached 7 days.
	 */
	public static function search( $query, array $args = array() ) {
		$query = trim( sanitize_text_field( (string) $query ) );
		if ( '' === $query ) {
			return new WP_Error( 'qcld_wpbot_core_youtube_query', __( 'Enter a search.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$state = self::status();
		if ( 'connected' !== $state['status'] ) {
			return new WP_Error(
				'qcld_wpbot_core_youtube_' . $state['status'],
				'reconnect' === $state['status']
					? __( 'Reconnect Google once to let WPBot read YouTube search results (read-only).', 'chatbot' )
					: __( 'Connect Google first (Settings & AI Provider > Connections) to read YouTube search results.', 'chatbot' ),
				array( 'status' => 400 )
			);
		}
		$limit  = max( 1, min( 10, isset( $args['limit'] ) ? (int) $args['limit'] : 5 ) );
		$region = isset( $args['region'] ) ? strtoupper( preg_replace( '/[^a-z]/i', '', (string) $args['region'] ) ) : '';
		$lang   = isset( $args['language'] ) ? strtolower( substr( preg_replace( '/[^a-z]/i', '', (string) $args['language'] ), 0, 2 ) ) : '';
		$key    = 'qcld_wpbot_core_yt_' . md5( strtolower( $query ) . '|' . $limit . '|' . $region . '|' . $lang );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$params = array(
			'part'       => 'snippet',
			'type'       => 'video',
			'maxResults' => $limit,
			'q'          => $query,
		);
		if ( 2 === strlen( $region ) ) {
			$params['regionCode'] = $region;
		}
		if ( 2 === strlen( $lang ) ) {
			$params['relevanceLanguage'] = $lang;
		}
		$found = Google::api( '/youtube/v3/search?' . http_build_query( $params ), null, 'api', false );
		if ( is_wp_error( $found ) ) {
			return self::explain( $found );
		}
		$videos = array();
		foreach ( isset( $found['items'] ) ? (array) $found['items'] : array() as $item ) {
			$id = isset( $item['id']['videoId'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $item['id']['videoId'] ) : '';
			if ( '' === $id ) {
				continue;
			}
			$snippet       = isset( $item['snippet'] ) ? (array) $item['snippet'] : array();
			$videos[ $id ] = array(
				'video_id'  => $id,
				'title'     => isset( $snippet['title'] ) ? sanitize_text_field( html_entity_decode( (string) $snippet['title'], ENT_QUOTES, 'UTF-8' ) ) : '',
				'url'       => 'https://www.youtube.com/watch?v=' . $id,
				'channel'   => isset( $snippet['channelTitle'] ) ? sanitize_text_field( (string) $snippet['channelTitle'] ) : '',
				'published' => isset( $snippet['publishedAt'] ) ? substr( sanitize_text_field( (string) $snippet['publishedAt'] ), 0, 10 ) : '',
				'views'     => null,
			);
		}
		if ( array() !== $videos ) {
			// Views: one cheap call for all videos (1 quota unit).
			$stats = Google::api(
				'/youtube/v3/videos?' . http_build_query(
					array(
						'part' => 'statistics',
						'id'   => implode( ',', array_keys( $videos ) ),
					)
				),
				null,
				'api',
				false
			);
			foreach ( is_wp_error( $stats ) || ! isset( $stats['items'] ) ? array() : (array) $stats['items'] as $item ) {
				$id = isset( $item['id'] ) ? (string) $item['id'] : '';
				if ( isset( $videos[ $id ] ) && isset( $item['statistics']['viewCount'] ) ) {
					$videos[ $id ]['views'] = (int) $item['statistics']['viewCount'];
				}
			}
		}
		$videos = array_values( $videos );
		set_transient( $key, $videos, 7 * DAY_IN_SECONDS );
		return $videos;
	}

	/**
	 * Google errors in words the owner can act on.
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	public static function explain( WP_Error $error ) {
		$data   = (array) $error->get_error_data();
		$reason = isset( $data['reason'] ) ? strtolower( (string) $data['reason'] ) : '';
		$text   = strtolower( $error->get_error_message() ) . ' ' . $reason;
		if ( false !== strpos( $text, 'quota' ) || false !== strpos( $text, 'ratelimit' ) ) {
			return new WP_Error( 'qcld_wpbot_core_youtube_quota', __( 'YouTube\'s free daily limit is used up for your Google Cloud project. It resets at midnight Pacific time.', 'chatbot' ), array( 'status' => 429 ) );
		}
		if ( false !== strpos( $text, 'not been used' ) || false !== strpos( $text, 'disabled' ) || false !== strpos( $text, 'accessnotconfigured' ) || false !== strpos( $text, 'service_disabled' ) ) {
			return new WP_Error( 'qcld_wpbot_core_youtube_api_off', __( 'Turn on "YouTube Data API v3" in your Google Cloud project (APIs & Services > Library), then try again.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return $error;
	}
}
