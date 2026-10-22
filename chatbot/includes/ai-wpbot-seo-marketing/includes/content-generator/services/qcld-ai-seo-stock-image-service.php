<?php
/**
 * Stock Image Service — free stock photo search (Pexels / Pixabay) and
 * import into the WordPress media library.
 *
 * API keys are stored encrypted in the content-generator settings option,
 * which is deliberately excluded from settings export (same rule as the
 * webhook API key and AI connections — secrets never leave the site).
 *
 * @package QCLD_Wpbot_Ai_Seo
 */



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qcld_Wpbot_Ai_Seo_Content_Stock_Image_Service {

	private const OPTION_KEY = 'qcld_wpbot_ai_seo_content-generator_settings';

	public const PROVIDERS = array( 'pexels', 'pixabay' );

	/**
	 * Hosts we allow images to be sideloaded from. Import requests carry a
	 * user-supplied URL, so this allowlist prevents fetching arbitrary hosts.
	 */
	private const ALLOWED_IMAGE_HOSTS = array(
		'images.pexels.com',
		'pixabay.com',
		'cdn.pixabay.com',
	);

	/* ── Configuration ───────────────────────────────── */

	public static function get_provider(): string {
		$global   = get_option( 'qcld_wpbot_ai_seo_settings', array() );
		$settings = get_option( self::OPTION_KEY, array() );
		$raw      = ! empty( $global['stock_provider'] ) ? $global['stock_provider'] : ( $settings['stock_provider'] ?? 'pexels' );
		$provider = sanitize_key( (string) $raw );

		return in_array( $provider, self::PROVIDERS, true ) ? $provider : 'pexels';
	}

	/**
	 * Decrypted API key for a provider ('' when not set).
	 * Checks global plugin settings first, falling back to Content Generator settings.
	 */
	public static function get_api_key( string $provider = '' ): string {
		$provider = $provider ?: self::get_provider();
		$global   = get_option( 'qcld_wpbot_ai_seo_settings', array() );
		$settings = get_option( self::OPTION_KEY, array() );
		$field    = $provider . '_api_key';
		$stored   = ! empty( $global[ $field ] ) ? (string) $global[ $field ] : (string) ( $settings[ $field ] ?? '' );

		return '' !== $stored ? Qcld_Wpbot_Ai_Seo_Encryption::decrypt( $stored ) : '';
	}

	public static function is_configured( string $provider = '' ): bool {
		return '' !== self::get_api_key( $provider );
	}

	/**
	 * Preferred photo orientation for auto-picked images.
	 *
	 * Tall portrait photos render as huge vertical blocks inside article
	 * content, so auto-pick defaults to landscape. Manual search uses the
	 * same default; users who want portraits can switch to "any".
	 *
	 * @return string 'landscape'|'any'.
	 */
	public static function get_orientation(): string {
		$settings    = get_option( self::OPTION_KEY, array() );
		$orientation = sanitize_key( (string) ( $settings['image_orientation'] ?? 'landscape' ) );
		return 'any' === $orientation ? 'any' : 'landscape';
	}

	/**
	 * Whether a normalized image result is landscape (or square).
	 * Portrait photos (height clearly greater than width) are skipped by
	 * auto-pick so in-body images never become huge vertical blocks.
	 */
	private static function is_landscape( array $image ): bool {
		$w = (int) ( $image['width'] ?? 0 );
		$h = (int) ( $image['height'] ?? 0 );
		if ( $w <= 0 || $h <= 0 ) {
			return true; // Unknown dimensions — don't reject blindly.
		}
		return $h <= $w;
	}

	/**
	 * Reuse window in days: provider images used within this window are
	 * skipped by auto-pick so daily posts stop repeating the same photo.
	 * 0 = allow repeats (feature off).
	 */
	public static function get_reuse_days(): int {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) || ! isset( $settings['image_reuse_days'] ) ) {
			return 60;
		}
		return max( 0, min( 365, (int) $settings['image_reuse_days'] ) );
	}

	/**
	 * Provider image keys (provider:id) used within the reuse window.
	 * Fail-soft: returns empty array when the log table is missing.
	 *
	 * @return string[]
	 */
	public static function recently_used_keys(): array {
		$days = self::get_reuse_days();
		if ( $days <= 0 ) {
			return array();
		}
		global $wpdb;
		$table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_images';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT CONCAT(provider, ':', provider_image_id) FROM {$table}
			 WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) LIMIT 500",
			$days
		) );
		return is_array( $rows ) ? array_values( array_filter( array_map( 'strval', $rows ) ) ) : array();
	}

	/**
	 * Log an auto-picked image (insert-or-touch timestamp on re-pick).
	 * Never throws; logging must never break publishing.
	 */
	public static function record_use( array $image, int $attachment_id = 0, string $query = '' ): void {
		try {
			global $wpdb;
			$table = $wpdb->prefix . 'qcld_wpbot_ai_seo_content_images';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				return;
			}
			$provider = sanitize_key( (string) ( $image['provider'] ?? '' ) );
			$pid      = sanitize_text_field( (string) ( $image['id'] ?? '' ) );
			if ( '' === $provider || '' === $pid ) {
				return;
			}
			$wpdb->replace(
				$table,
				array(
					'provider'          => $provider,
					'provider_image_id' => $pid,
					'attachment_id'     => $attachment_id > 0 ? $attachment_id : null,
					'query'             => mb_substr( sanitize_text_field( $query ), 0, 255 ),
					'created_at'        => current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%d', '%s', '%s' )
			);
			// Opportunistic prune: keep the log bounded (1-in-20 chance per record).
			if ( 1 === wp_rand( 1, 20 ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$table} WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
						366
					)
				);
			}
		} catch ( \Throwable $e ) {
			qcld_wpbot_ai_seo_log( 'Image usage log failed: ' . $e->getMessage(), 'warning', 'content-generator' );
		}
	}

	/* ── Search ──────────────────────────────────────── */

	/**
	 * Search the configured provider. Returns a normalized result:
	 * array{success:bool, provider:string, images:array<array{id,provider,thumb,preview,full,width,height,photographer,source_url,alt}>, error?:string}
	 *
	 * @param string $orientation '' = site setting, 'landscape'|'any' to override.
	 */
	public function search( string $query, int $per_page = 12, int $page = 1, string $orientation = '' ): array {
		$query    = trim( $query );
		$per_page = max( 1, min( 30, $per_page ) );
		$page     = max( 1, $page );
		if ( '' === $orientation ) {
			$orientation = self::get_orientation();
		} else {
			$orientation = 'any' === sanitize_key( $orientation ) ? 'any' : 'landscape';
		}

		if ( '' === $query ) {
			return array( 'success' => false, 'error' => __( 'Search query is empty.', 'chatbot' ) );
		}

		$provider = self::get_provider();
		$key      = self::get_api_key( $provider );
		if ( '' === $key ) {
			// Auto-fallback: if preferred provider has no key, check if the other provider is configured.
			$other = 'pexels' === $provider ? 'pixabay' : 'pexels';
			$other_key = self::get_api_key( $other );
			if ( '' !== $other_key ) {
				$provider = $other;
				$key      = $other_key;
			} else {
				return array(
					'success'        => false,
					'not_configured' => true,
					'error'          => __( 'No stock image API key configured. Add one under Settings → Stock Photos.', 'chatbot' ),
				);
			}
		}

		return 'pixabay' === $provider
			? $this->search_pixabay( $query, $per_page, $page, $key, $orientation )
			: $this->search_pexels( $query, $per_page, $page, $key, $orientation );
	}

	private function search_pexels( string $query, int $per_page, int $page, string $key, string $orientation = 'landscape' ): array {
		$args = array(
			'query'    => rawurlencode( $query ),
			'per_page' => $per_page,
			'page'     => $page,
		);
		if ( 'landscape' === $orientation ) {
			$args['orientation'] = 'landscape';
		}
		$url = add_query_arg(
			$args,
			'https://api.pexels.com/v1/search'
		);

		$response = wp_remote_get( $url, array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => $key ),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			return array(
				'success' => false,
				'error'   => 401 === $code || 403 === $code
					? __( 'Pexels rejected the API key. Check it under Settings → Stock Photos.', 'chatbot' )
					/* translators: %d: HTTP status code */
					: sprintf( __( 'Pexels API error (HTTP %d).', 'chatbot' ), $code ),
			);
		}

		$images = array();
		foreach ( (array) ( $body['photos'] ?? array() ) as $photo ) {
			$src      = (array) ( $photo['src'] ?? array() );
			$images[] = array(
				'id'           => (string) ( $photo['id'] ?? '' ),
				'provider'     => 'pexels',
				'thumb'        => esc_url_raw( (string) ( $src['medium'] ?? '' ) ),
				'preview'      => esc_url_raw( (string) ( $src['large'] ?? '' ) ),
				'full'         => esc_url_raw( (string) ( $src['large2x'] ?? $src['large'] ?? $src['original'] ?? '' ) ),
				'width'        => (int) ( $photo['width'] ?? 0 ),
				'height'       => (int) ( $photo['height'] ?? 0 ),
				'photographer' => sanitize_text_field( (string) ( $photo['photographer'] ?? '' ) ),
				'source_url'   => esc_url_raw( (string) ( $photo['url'] ?? '' ) ),
				'alt'          => sanitize_text_field( (string) ( $photo['alt'] ?? '' ) ),
			);
		}

		return array(
			'success'  => true,
			'provider' => 'pexels',
			'total'    => (int) ( $body['total_results'] ?? count( $images ) ),
			'images'   => array_values( array_filter( $images, static fn ( array $i ): bool => '' !== $i['full'] ) ),
		);
	}

	private function search_pixabay( string $query, int $per_page, int $page, string $key, string $orientation = 'landscape' ): array {
		$args = array(
			'key'        => rawurlencode( $key ),
			'q'          => rawurlencode( $query ),
			'image_type' => 'photo',
			'safesearch' => 'true',
			'per_page'   => max( 3, $per_page ), // Pixabay minimum is 3.
			'page'       => $page,
		);
		if ( 'landscape' === $orientation ) {
			$args['orientation'] = 'horizontal';
		}
		$url = add_query_arg(
			$args,
			'https://pixabay.com/api/'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			return array(
				'success' => false,
				'error'   => 400 === $code || 401 === $code || 403 === $code
					? __( 'Pixabay rejected the API key. Check it under Settings → Stock Photos.', 'chatbot' )
					/* translators: %d: HTTP status code */
					: sprintf( __( 'Pixabay API error (HTTP %d).', 'chatbot' ), $code ),
			);
		}

		$images = array();
		foreach ( (array) ( $body['hits'] ?? array() ) as $hit ) {
			$images[] = array(
				'id'           => (string) ( $hit['id'] ?? '' ),
				'provider'     => 'pixabay',
				'thumb'        => esc_url_raw( (string) ( $hit['webformatURL'] ?? '' ) ),
				'preview'      => esc_url_raw( (string) ( $hit['webformatURL'] ?? '' ) ),
				'full'         => esc_url_raw( (string) ( $hit['largeImageURL'] ?? $hit['webformatURL'] ?? '' ) ),
				'width'        => (int) ( $hit['imageWidth'] ?? 0 ),
				'height'       => (int) ( $hit['imageHeight'] ?? 0 ),
				'photographer' => sanitize_text_field( (string) ( $hit['user'] ?? '' ) ),
				'source_url'   => esc_url_raw( (string) ( $hit['pageURL'] ?? '' ) ),
				'alt'          => sanitize_text_field( (string) ( $hit['tags'] ?? '' ) ),
			);
		}

		return array(
			'success'  => true,
			'provider' => 'pixabay',
			'total'    => (int) ( $body['totalHits'] ?? count( $images ) ),
			'images'   => array_values( array_filter( $images, static fn ( array $i ): bool => '' !== $i['full'] ) ),
		);
	}

	/* ── In-body images ──────────────────────────────── */

	/**
	 * Placeholder the AI embeds in article bodies: <!--qcld_wpbot_ai_seo-img:search query-->
	 */
	private const PLACEHOLDER_REGEX = '/<!--\s*qcld_wpbot_ai_seo-img:\s*([^>]{1,120}?)\s*-->/i';

	/**
	 * Replace AI-inserted image placeholders with imported stock photos.
	 *
	 * Fail-soft: if the provider is not configured, a search comes up empty,
	 * or an import fails, the placeholder is simply removed — the article
	 * text is never harmed. Never throws.
	 *
	 * @param string $body           Article body HTML containing placeholders.
	 * @param int    $max            Maximum number of images to embed (0 = strip all placeholders).
	 * @param string $fallback_query Used when a placeholder has an empty query.
	 * @return string Body with placeholders replaced (or stripped).
	 */
	public function embed_inline_images( string $body, int $max = 3, string $fallback_query = '' ): string {
		if ( ! preg_match_all( self::PLACEHOLDER_REGEX, $body, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $body;
		}

		$configured = self::is_configured();
		$used_ids   = array();
		$embedded   = 0;

		// Replace from the end so offsets stay valid.
		for ( $i = count( $matches[0] ) - 1; $i >= 0; $i-- ) {
			$placeholder = $matches[0][ $i ][0];
			$offset      = (int) $matches[0][ $i ][1];
			$query       = sanitize_text_field( $matches[1][ $i ][0] );
			$replacement = '';

			// Count from the top of the article: only the first $max
			// placeholders (in document order) become images.
			$rank = $i + 1;

			if ( $configured && $rank <= $max && $embedded < $max ) {
				try {
					$replacement = $this->placeholder_to_figure( '' !== $query ? $query : $fallback_query, $used_ids );
					if ( '' !== $replacement ) {
						$embedded++;
					}
				} catch ( \Throwable $e ) {
					qcld_wpbot_ai_seo_log( 'Inline stock image failed: ' . $e->getMessage(), 'warning', 'content-generator' );
					$replacement = '';
				}
			}

			$body = substr_replace( $body, $replacement, $offset, strlen( $placeholder ) );
		}

		return $body;
	}

	/**
	 * Search + import one image for a placeholder and return <figure> markup
	 * ('' when nothing suitable was found).
	 *
	 * @param string $query    Stock search query.
	 * @param array  $used_ids Attachment ids already embedded (by reference — avoids repeats).
	 */
	private function placeholder_to_figure( string $query, array &$used_ids ): string {
		$query = trim( $query );
		if ( '' === $query ) {
			return '';
		}

		$result = $this->search( $query, 5, 1 );
		if ( empty( $result['success'] ) || empty( $result['images'] ) ) {
			return '';
		}

		// Landscape first so in-body photos never become huge vertical
		// blocks; portraits stay as last-resort fallback (fail-soft).
		// Recently-used provider images sort last so daily posts vary.
		$images = $result['images'];
		$used   = array_flip( self::recently_used_keys() );
		usort( $images, static function ( array $a, array $b ) use ( $used ): int {
			$ka = sanitize_key( (string) ( $a['provider'] ?? '' ) ) . ':' . sanitize_text_field( (string) ( $a['id'] ?? '' ) );
			$kb = sanitize_key( (string) ( $b['provider'] ?? '' ) ) . ':' . sanitize_text_field( (string) ( $b['id'] ?? '' ) );
			$score_a = (int) ! self::is_landscape( $a ) * 10 + ( isset( $used[ $ka ] ) ? 5 : 0 );
			$score_b = (int) ! self::is_landscape( $b ) * 10 + ( isset( $used[ $kb ] ) ? 5 : 0 );
			return $score_a - $score_b;
		} );

		foreach ( $images as $image ) {
			$import = $this->import_to_media_library( $image['full'], '' !== $image['alt'] ? $image['alt'] : $query );
			if ( empty( $import['success'] ) ) {
				continue;
			}
			$attachment_id = (int) $import['attachment_id'];
			if ( in_array( $attachment_id, $used_ids, true ) ) {
				continue;
			}
			$used_ids[] = $attachment_id;
			self::record_use( $image, $attachment_id, $query );

			// Embed size is a site setting (Content → Settings → Images).
			$settings = get_option( self::OPTION_KEY, array() );
			$size     = sanitize_key( (string) ( $settings['inline_image_size'] ?? 'large' ) );
			if ( ! in_array( $size, array( 'medium', 'medium_large', 'large', 'full' ), true ) ) {
				$size = 'large';
			}

			$img = wp_get_attachment_image( $attachment_id, $size, false, array(
				'class'    => 'qcld_wpbot_ai_seo-inline-image',
				'loading'  => 'lazy',
				'decoding' => 'async',
				'style'    => 'max-width:100%;height:auto;',
			) );
			if ( '' === $img ) {
				continue;
			}

			return '<figure class="wp-block-image size-' . esc_attr( $size ) . ' qcld_wpbot_ai_seo-inline-figure" style="max-width:100%;">' . $img . '</figure>';
		}

		return '';
	}

	/* ── Import ──────────────────────────────────────── */

	/**
	 * Only stock-CDN hosts may be sideloaded (the URL arrives from the client).
	 */
	public static function is_allowed_image_url( string $url ): bool {
		$url = esc_url_raw( $url );
		if ( ! $url || ! preg_match( '#^https://#i', $url ) ) {
			return false;
		}

		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		return in_array( $host, self::ALLOWED_IMAGE_HOSTS, true );
	}

	/**
	 * Download a stock image into the media library.
	 *
	 * @return array{success:bool, attachment_id?:int, url?:string, error?:string}
	 */
	public function import_to_media_library( string $image_url, string $alt = '', int $post_id = 0 ): array {
		if ( ! self::is_allowed_image_url( $image_url ) ) {
			return array( 'success' => false, 'error' => __( 'Image URL is not from a supported stock provider.', 'chatbot' ) );
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_sideload_image( esc_url_raw( $image_url ), $post_id, sanitize_text_field( $alt ), 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			return array( 'success' => false, 'error' => $attachment_id->get_error_message() );
		}

		if ( '' !== $alt ) {
			update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		return array(
			'success'       => true,
			'attachment_id' => (int) $attachment_id,
			'url'           => (string) wp_get_attachment_url( (int) $attachment_id ),
		);
	}
}
