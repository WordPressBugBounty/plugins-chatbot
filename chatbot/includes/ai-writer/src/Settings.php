<?php
/**
 * AI Writer settings (qcld_wpbot_writer_settings).
 *
 * Every limit is an owner setting with a default; nothing is capped by us.
 * Keys are not stored here: they live in WPBot Core's per-module key set.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter;

defined( 'ABSPATH' ) || exit;

/**
 * Settings store with sanitising.
 */
final class Settings {

	const OPTION = 'qcld_wpbot_writer_settings';

	/**
	 * Allowed values for enum fields.
	 *
	 * @var array<string,string[]>
	 */
	const ENUMS = array(
		'default_type'        => array( 'blog_post', 'help_article', 'faq', 'page_copy', 'product' ),
		'default_length'      => array( 'short', 'standard', 'long' ),
		'spelling'            => array( 'auto', 'us', 'uk' ),
		// CR-10: library_stock_ai = Media Library, then stock, then AI if nothing is found.
		'image_fill'          => array( 'library_stock_ai', 'auto', 'ai', 'placeholder', 'none' ),
		// The header (featured) image: 'same' as the in-article images, or its own choice.
		'image_fill_featured' => array( 'same', 'library_stock_ai', 'auto', 'ai', 'placeholder', 'none' ),
		// Which saved key makes AI images ('' = the first OpenAI or Gemini key).
		'image_provider'      => array( '', 'openai', 'gemini' ),
		'reading_level'       => array( 'any', 'simple', 'standard', 'advanced' ),
		'topic_refresh'       => array( 'weekly', 'manual' ),
	);

	/**
	 * Style guide lists (one entry per line in the settings screen).
	 *
	 * @var string[]
	 */
	const STYLE_LISTS = array( 'style_use_words', 'style_avoid_words', 'style_product_names' );

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'default_type'           => 'blog_post',
			'default_length'         => 'standard',
			'lengths'                => array(
				'short'    => 600,
				'standard' => 1200,
				'long'     => 2000,
			),
			'spelling'               => 'auto',
			'ask_questions'          => true,
			// Per step type: provider and model overrides ('' = use the active provider and its model).
			'step_models'            => array(
				'creative' => array(
					'provider' => '',
					'model'    => '',
				),
				'strict'   => array(
					'provider' => '',
					'model'    => '',
				),
			),
			'runner_interval'        => 3,
			// New sites: free images first, AI only when nothing fits (sites that saved a choice keep it).
			'image_fill'             => 'library_stock_ai',
			'image_fill_featured'    => 'same',
			'image_provider'         => '',
			'image_max_width'        => 1600,
			'image_webp'             => true,
			'upload_limit_mb'        => 10,
			'chatbot_source'         => true,
			'cliches'                => \QuantumCloud\AiWriter\Checks\Ai_Pattern_Check::default_cliches(),
			// Basic style guide: applied to every prompt and to the editor checks.
			'style_use_words'        => array(),
			'style_avoid_words'      => array(),
			'style_product_names'    => array(),
			'reading_level'          => 'any',
			'show_estimates'         => true,
			'delete_on_uninstall'    => false,
			// Topic Finder.
			'topic_refresh'          => 'weekly',
			'topic_keywords'         => array(),
			'rss_feeds'              => array(),
			'seasonal_country'       => '',
			'store_events'           => array(),
			'search_logger'          => true,
			'weekly_email'           => false,
			'email_recipients'       => array(),
			'hide_email_footer'      => false,
			// Free: hide every inactive "Pro" preview (menu pages and Pro badges).
			'hide_pro_previews'      => false,
			// Pages read per "Fill from your content" import; 0 = the default (5, or more in Pro).
			'import_max_pages'       => 0,
			// CR-14: versions kept per piece, and the cost above which Regenerate asks first.
			'versions_keep'          => 5,
			'regenerate_confirm_usd' => 0.05,
			// CR-16: article formats.
			'format_suggest'         => true,
			'research_min_group'     => 10,
			'format_downloads'       => true,
			'download_gate'          => false,
		);
	}

	/**
	 * Settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved    = get_option( self::OPTION, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$defaults = self::defaults();
		$all      = array_merge( $defaults, $saved );
		// Maps merge key by key; lists (clichés) replace the default list.
		foreach ( array( 'lengths', 'step_models' ) as $map ) {
			$all[ $map ] = array_replace_recursive( $defaults[ $map ], is_array( $all[ $map ] ) ? $all[ $map ] : array() );
		}
		return $all;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitise and save a partial update.
	 *
	 * @param array $changes Changes.
	 * @return array Saved settings.
	 */
	public static function update( array $changes ) {
		$all = self::all();

		foreach ( self::ENUMS as $key => $allowed ) {
			if ( isset( $changes[ $key ] ) && in_array( $changes[ $key ], $allowed, true ) ) {
				$all[ $key ] = $changes[ $key ];
			}
		}
		if ( isset( $changes['lengths'] ) && is_array( $changes['lengths'] ) ) {
			foreach ( array( 'short', 'standard', 'long' ) as $preset ) {
				if ( isset( $changes['lengths'][ $preset ] ) ) {
					$all['lengths'][ $preset ] = min( 10000, max( 100, absint( $changes['lengths'][ $preset ] ) ) );
				}
			}
		}
		if ( isset( $changes['step_models'] ) && is_array( $changes['step_models'] ) ) {
			foreach ( array( 'creative', 'strict' ) as $mode ) {
				if ( ! isset( $changes['step_models'][ $mode ] ) || ! is_array( $changes['step_models'][ $mode ] ) ) {
					continue;
				}
				$entry = $changes['step_models'][ $mode ];
				if ( isset( $entry['provider'] ) ) {
					$all['step_models'][ $mode ]['provider'] = sanitize_key( $entry['provider'] );
				}
				if ( isset( $entry['model'] ) ) {
					$all['step_models'][ $mode ]['model'] = substr( (string) preg_replace( '/[^A-Za-z0-9._:\/@-]/', '', (string) $entry['model'] ), 0, 120 );
				}
			}
		}
		if ( isset( $changes['image_max_width'] ) ) {
			$all['image_max_width'] = min( 4000, max( 400, absint( $changes['image_max_width'] ) ) );
		}
		if ( isset( $changes['import_max_pages'] ) ) {
			$all['import_max_pages'] = min( 100, absint( $changes['import_max_pages'] ) );
		}
		if ( isset( $changes['upload_limit_mb'] ) ) {
			$all['upload_limit_mb'] = min( 100, max( 1, absint( $changes['upload_limit_mb'] ) ) );
		}
		if ( isset( $changes['cliches'] ) ) {
			$list           = is_array( $changes['cliches'] ) ? $changes['cliches'] : explode( "\n", (string) $changes['cliches'] );
			$all['cliches'] = array_slice( array_values( array_unique( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', array_map( 'strval', $list ) ) ) ) ) ), 0, 200 );
		}
		foreach ( self::STYLE_LISTS as $key ) {
			if ( isset( $changes[ $key ] ) ) {
				$list        = is_array( $changes[ $key ] ) ? $changes[ $key ] : explode( "\n", (string) $changes[ $key ] );
				$all[ $key ] = array_slice( array_values( array_unique( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', array_map( 'strval', $list ) ) ) ) ) ), 0, 100 );
			}
		}
		if ( isset( $changes['topic_keywords'] ) ) {
			$all['topic_keywords'] = self::text_list( $changes['topic_keywords'], 20, 80 );
		}
		if ( isset( $changes['rss_feeds'] ) ) {
			$list  = is_array( $changes['rss_feeds'] ) ? $changes['rss_feeds'] : explode( "\n", (string) $changes['rss_feeds'] );
			$feeds = array();
			foreach ( $list as $url ) {
				$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
				if ( '' !== $url && false !== filter_var( $url, FILTER_VALIDATE_URL ) ) {
					$feeds[] = $url;
				}
			}
			$all['rss_feeds'] = array_slice( array_values( array_unique( $feeds ) ), 0, 10 );
		}
		if ( isset( $changes['seasonal_country'] ) ) {
			$country                 = strtoupper( (string) preg_replace( '/[^A-Za-z]/', '', (string) $changes['seasonal_country'] ) );
			$all['seasonal_country'] = 2 === strlen( $country ) ? $country : '';
		}
		if ( isset( $changes['store_events'] ) && is_array( $changes['store_events'] ) ) {
			$events = array();
			foreach ( array_slice( $changes['store_events'], 0, 30 ) as $event ) {
				$title = is_array( $event ) && isset( $event['title'] ) ? substr( sanitize_text_field( (string) $event['title'] ), 0, 100 ) : '';
				$date  = is_array( $event ) && isset( $event['date'] ) ? (string) $event['date'] : '';
				if ( '' !== $title && \QuantumCloud\AiWriter\Calendar\Calendar::valid_date( $date ) ) {
					$events[] = array(
						'title' => $title,
						'date'  => $date,
					);
				}
			}
			$all['store_events'] = $events;
		}
		if ( isset( $changes['email_recipients'] ) ) {
			$list                    = is_array( $changes['email_recipients'] ) ? $changes['email_recipients'] : preg_split( '/[\s,;]+/', (string) $changes['email_recipients'] );
			$all['email_recipients'] = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_email', array_map( 'strval', (array) $list ) ), 'is_email' ) ) ), 0, 10 );
		}
		foreach ( array( 'ask_questions', 'show_estimates', 'delete_on_uninstall', 'image_webp', 'chatbot_source', 'search_logger', 'weekly_email', 'hide_email_footer', 'hide_pro_previews', 'format_suggest', 'format_downloads', 'download_gate' ) as $flag ) {
			if ( isset( $changes[ $flag ] ) ) {
				$all[ $flag ] = (bool) $changes[ $flag ];
			}
		}
		if ( isset( $changes['versions_keep'] ) ) {
			$all['versions_keep'] = min( 20, max( 1, absint( $changes['versions_keep'] ) ) );
		}
		if ( isset( $changes['regenerate_confirm_usd'] ) ) {
			$all['regenerate_confirm_usd'] = round( min( 100, max( 0, (float) $changes['regenerate_confirm_usd'] ) ), 2 );
		}
		if ( isset( $changes['research_min_group'] ) ) {
			$all['research_min_group'] = min( 1000, max( 5, absint( $changes['research_min_group'] ) ) );
		}
		if ( isset( $changes['runner_interval'] ) ) {
			$all['runner_interval'] = min( 30, max( 2, absint( $changes['runner_interval'] ) ) );
		}

		update_option( self::OPTION, $all, false );
		return $all;
	}

	/**
	 * Clean a list of short texts (array or one per line).
	 *
	 * @param mixed $value Value.
	 * @param int   $max   Most items.
	 * @param int   $chars Most characters per item.
	 * @return string[]
	 */
	private static function text_list( $value, $max, $chars ) {
		$list = is_array( $value ) ? $value : explode( "\n", (string) $value );
		$out  = array();
		foreach ( $list as $item ) {
			$item = substr( trim( sanitize_text_field( (string) $item ) ), 0, $chars );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, $max );
	}
}
