<?php
/**
 * Shared SEO plugin adapter for every WPBot module: detects the active SEO
 * plugin and reads or writes the fields modules may touch, in that
 * plugin's own storage — meta title and description, focus keyword,
 * "noindex" and canonical URL — for Yoast SEO, Rank Math and All in One
 * SEO. SEOPress, Slim SEO and The SEO Framework are detected (so no module
 * prints a second description tag) but not written to.
 *
 * It never writes a plugin's scores or analysis fields.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * SEO adapter.
 */
final class Seo_Adapter {

	const RECENT = 'qcld_wpbot_writer_seo_recent_';

	/**
	 * Meta keys per writable plugin.
	 *
	 * @var array<string,array<string,string>>
	 */
	const KEYS = array(
		'yoast'    => array(
			'title'          => '_yoast_wpseo_title',
			'description'    => '_yoast_wpseo_metadesc',
			'focus_keyword'  => '_yoast_wpseo_focuskw',
			'robots_noindex' => '_yoast_wpseo_meta-robots-noindex',
			'canonical'      => '_yoast_wpseo_canonical',
		),
		'rankmath' => array(
			'title'          => 'rank_math_title',
			'description'    => 'rank_math_description',
			'focus_keyword'  => 'rank_math_focus_keyword',
			'robots_noindex' => 'rank_math_robots',
			'canonical'      => 'rank_math_canonical_url',
		),
		'aioseo'   => array(
			'title'          => '_aioseo_title',
			'description'    => '_aioseo_description',
			'focus_keyword'  => '_aioseo_keywords',
			'robots_noindex' => '_aioseo_robots_noindex',
			'canonical'      => '_aioseo_canonical_url',
		),
	);

	/**
	 * Any active SEO plugin, including ones that are only detected.
	 *
	 * @return string yoast, rankmath, aioseo, seopress, slimseo, tsf or ''.
	 */
	public static function detect() {
		$found = '';
		if ( defined( 'WPSEO_VERSION' ) ) {
			$found = 'yoast';
		} elseif ( defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath' ) ) {
			$found = 'rankmath';
		} elseif ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			$found = 'aioseo';
		} elseif ( defined( 'SEOPRESS_VERSION' ) ) {
			$found = 'seopress';
		} elseif ( defined( 'SLIM_SEO_VER' ) || class_exists( '\SlimSEO\Plugin' ) ) {
			$found = 'slimseo';
		} elseif ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ) ) {
			$found = 'tsf';
		}
		/**
		 * Change the detected SEO plugin.
		 *
		 * @param string $found Plugin id or ''.
		 */
		return (string) apply_filters( 'qcld_wpbot_core/seo_plugin', $found );
	}

	/**
	 * The active plugin modules can write to ('' when none or only detected).
	 *
	 * @return string yoast, rankmath, aioseo or ''.
	 */
	public static function active() {
		$found = self::detect();
		return isset( self::KEYS[ $found ] ) ? $found : '';
	}

	/**
	 * Owner-facing name of a plugin.
	 *
	 * @param string $plugin Plugin id ('' = the detected one).
	 * @return string '' when none.
	 */
	public static function label( $plugin = '' ) {
		$labels = array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'aioseo'   => 'All in One SEO',
			'seopress' => 'SEOPress',
			'slimseo'  => 'Slim SEO',
			'tsf'      => 'The SEO Framework',
		);
		$plugin = '' !== $plugin ? $plugin : self::detect();
		return isset( $labels[ $plugin ] ) ? $labels[ $plugin ] : '';
	}

	/**
	 * Whether the active SEO plugin prints schema (structured data).
	 *
	 * @return bool
	 */
	public static function outputs_schema() {
		return in_array( self::detect(), array( 'yoast', 'rankmath', 'aioseo', 'seopress', 'slimseo', 'tsf' ), true );
	}

	/**
	 * Read a field from the active plugin.
	 *
	 * @param int    $post_id Post.
	 * @param string $field   title, description, focus_keyword, robots_noindex (bool), canonical.
	 * @return string|bool|null Null when no writable plugin is active.
	 */
	public static function read_field( $post_id, $field ) {
		$active = self::active();
		if ( '' === $active || ! isset( self::KEYS[ $active ][ $field ] ) ) {
			return null;
		}
		$value = get_post_meta( $post_id, self::KEYS[ $active ][ $field ], true );
		if ( 'robots_noindex' === $field ) {
			if ( 'rankmath' === $active ) {
				return in_array( 'noindex', (array) $value, true );
			}
			return '1' === (string) $value || 'true' === (string) $value;
		}
		if ( 'focus_keyword' === $field && 'rankmath' === $active ) {
			// Rank Math stores several keywords separated by commas; the first is the main one.
			$value = trim( (string) strtok( (string) $value, ',' ) );
		}
		return (string) $value;
	}

	/**
	 * Write a field to the active plugin.
	 *
	 * @param int         $post_id Post.
	 * @param string      $field   title, description, focus_keyword, robots_noindex, canonical.
	 * @param string|bool $value   Value.
	 * @return string Plugin written to, or '' (nothing written).
	 */
	public static function write_field( $post_id, $field, $value ) {
		$active = self::active();
		if ( '' === $active || ! isset( self::KEYS[ $active ][ $field ] ) ) {
			return '';
		}
		$key = self::KEYS[ $active ][ $field ];
		switch ( $field ) {
			case 'robots_noindex':
				if ( 'rankmath' === $active ) {
					$robots   = array_values( array_diff( (array) get_post_meta( $post_id, $key, true ), array( 'noindex', 'index' ) ) );
					$robots[] = $value ? 'noindex' : 'index';
					update_post_meta( $post_id, $key, $robots );
				} else {
					update_post_meta( $post_id, $key, $value ? '1' : ( 'yoast' === $active ? '2' : '0' ) );
				}
				break;
			case 'canonical':
				update_post_meta( $post_id, $key, esc_url_raw( (string) $value ) );
				break;
			case 'focus_keyword':
				$value = sanitize_text_field( (string) $value );
				if ( 'rankmath' === $active ) {
					$rest  = array_slice( array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post_id, $key, true ) ) ) ), 1 );
					$value = implode( ',', array_merge( array( $value ), $rest ) );
				}
				update_post_meta( $post_id, $key, wp_slash( $value ) );
				break;
			default:
				$value = sanitize_text_field( (string) $value );
				if ( 'aioseo' === $active && in_array( $field, array( 'title', 'description' ), true ) ) {
					self::write_aioseo( $post_id, 'title' === $field ? $value : '', 'description' === $field ? $value : '' );
				}
				update_post_meta( $post_id, $key, wp_slash( $value ) );
		}
		if ( 'aioseo' === $active && in_array( $field, array( 'focus_keyword', 'robots_noindex', 'canonical' ), true ) ) {
			self::sync_aioseo( $post_id, $field, $value );
		}
		return $active;
	}

	/**
	 * Write title and description together (one model save for AIOSEO).
	 *
	 * @param int    $post_id     Post.
	 * @param string $title       Title ('' leaves it).
	 * @param string $description Description ('' leaves it).
	 * @return string Plugin written to, or ''.
	 */
	public static function write_meta( $post_id, $title, $description ) {
		$active      = self::active();
		$title       = sanitize_text_field( (string) $title );
		$description = sanitize_text_field( (string) $description );
		if ( '' === $active ) {
			return '';
		}
		if ( 'aioseo' === $active ) {
			self::write_aioseo( $post_id, $title, $description );
		}
		if ( '' !== $title ) {
			update_post_meta( $post_id, self::KEYS[ $active ]['title'], wp_slash( $title ) );
		}
		if ( '' !== $description ) {
			update_post_meta( $post_id, self::KEYS[ $active ]['description'], wp_slash( $description ) );
		}
		return $active;
	}

	/**
	 * The All in One SEO post model, when available.
	 *
	 * @param int $post_id Post.
	 * @return object|null
	 */
	private static function aioseo_record( $post_id ) {
		$model = '\AIOSEO\Plugin\Common\Models\Post';
		if ( ! class_exists( $model ) || ! method_exists( $model, 'getPost' ) ) {
			return null;
		}
		$record = call_user_func( array( $model, 'getPost' ), $post_id );
		if ( ! is_object( $record ) || ! method_exists( $record, 'save' ) ) {
			return null;
		}
		if ( empty( $record->post_id ) ) {
			$record->post_id = $post_id;
		}
		return $record;
	}

	/**
	 * All in One SEO keeps title and description in its table; use its
	 * model so its screens show the new values.
	 *
	 * @param int    $post_id     Post.
	 * @param string $title       Title.
	 * @param string $description Description.
	 * @return bool
	 */
	private static function write_aioseo( $post_id, $title, $description ) {
		$record = self::aioseo_record( $post_id );
		if ( ! $record ) {
			return false;
		}
		// Remember what was written (and what was there) so its editor save does not undo it.
		set_transient(
			self::RECENT . $post_id,
			array(
				'title'       => $title,
				'description' => $description,
				'before'      => array(
					'title'       => (string) $record->title,
					'description' => (string) $record->description,
				),
			),
			5 * MINUTE_IN_SECONDS
		);
		if ( '' !== $title ) {
			$record->title = $title;
		}
		if ( '' !== $description ) {
			$record->description = $description;
		}
		$record->save();
		return true;
	}

	/**
	 * Mirror focus keyword, noindex and canonical into the AIOSEO model.
	 *
	 * @param int    $post_id Post.
	 * @param string $field   Field.
	 * @param mixed  $value   Value.
	 * @return void
	 */
	private static function sync_aioseo( $post_id, $field, $value ) {
		$record = self::aioseo_record( $post_id );
		if ( ! $record ) {
			return;
		}
		if ( 'canonical' === $field ) {
			$record->canonical_url = esc_url_raw( (string) $value );
		} elseif ( 'robots_noindex' === $field ) {
			$record->robots_default = false;
			$record->robots_noindex = (bool) $value;
		} elseif ( 'focus_keyword' === $field ) {
			$record->keyphrases = wp_json_encode(
				array(
					'focus'      => array( 'keyphrase' => sanitize_text_field( (string) $value ) ),
					'additional' => array(),
				)
			);
		}
		$record->save();
	}
}
