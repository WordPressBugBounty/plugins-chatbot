<?php
/**
 * Reads and writes search fields through the active SEO plugin.
 *
 * Title and description go through AI Writer's SEO adapter when it is
 * loaded (one adapter for both modules; request R-1 moves it to WPBot
 * Core). Focus keyword, noindex and canonical are written here with the
 * same detection. With no SEO plugin, values go to WPBot's own fields and
 * Head_Output prints them. Every write is logged in Change_Log.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Seo;

use QuantumCloud\SeoAiSearch\Results\Change_Log;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * SEO plugin bridge.
 */
final class Seo_Bridge {

	/**
	 * WPBot's own fields (shared with AI Writer, so nothing prints twice).
	 */
	const OWN_TITLE       = '_qcld_wpbot_writer_meta_title';
	const OWN_DESCRIPTION = '_qcld_wpbot_writer_meta_description';
	const OWN_NOINDEX     = '_qcld_wpbot_seo_noindex';
	const OWN_CANONICAL   = '_qcld_wpbot_seo_canonical';
	const OWN_FOCUS       = '_qcld_wpbot_seo_focus_keyword';

	/**
	 * Post meta keys per plugin and field.
	 */
	const KEYS = array(
		'yoast'    => array(
			'title'         => '_yoast_wpseo_title',
			'description'   => '_yoast_wpseo_metadesc',
			'focus_keyword' => '_yoast_wpseo_focuskw',
			'noindex'       => '_yoast_wpseo_meta-robots-noindex',
			'canonical'     => '_yoast_wpseo_canonical',
		),
		'rankmath' => array(
			'title'         => 'rank_math_title',
			'description'   => 'rank_math_description',
			'focus_keyword' => 'rank_math_focus_keyword',
			'noindex'       => 'rank_math_robots',
			'canonical'     => 'rank_math_canonical_url',
		),
		'aioseo'   => array(
			'title'       => '_aioseo_title',
			'description' => '_aioseo_description',
		),
	);

	/**
	 * Fields this bridge knows.
	 */
	const FIELDS = array( 'title', 'description', 'focus_keyword', 'noindex', 'canonical' );

	/**
	 * Current values for a post.
	 *
	 * @param int $post_id Post id.
	 * @return array title, description, focus_keyword, noindex (bool), canonical, plugin.
	 */
	public static function read( $post_id ) {
		$out = array( 'plugin' => Seo_Plugins::active() );
		foreach ( self::FIELDS as $field ) {
			$out[ $field ] = self::get( $post_id, $field );
		}
		return $out;
	}

	/**
	 * One field's value.
	 *
	 * @param int    $post_id Post id.
	 * @param string $field   Field.
	 * @return string|bool
	 */
	public static function get( $post_id, $field ) {
		$shared = self::shared();
		if ( $shared ) {
			$value = call_user_func( array( $shared, 'read_field' ), $post_id, 'noindex' === $field ? 'robots_noindex' : $field );
			if ( null !== $value ) {
				return 'noindex' === $field ? (bool) $value : (string) $value;
			}
		}
		$plugin = Seo_Plugins::active();
		if ( in_array( $field, array( 'title', 'description' ), true ) && self::writer_adapter() ) {
			$read = call_user_func( array( self::writer_adapter(), 'read' ), $post_id );
			return isset( $read[ $field ] ) ? (string) $read[ $field ] : '';
		}
		if ( 'aioseo' === $plugin && ! isset( self::KEYS['aioseo'][ $field ] ) ) {
			return self::aioseo_get( $post_id, $field );
		}
		if ( isset( self::KEYS[ $plugin ][ $field ] ) ) {
			$value = get_post_meta( $post_id, self::KEYS[ $plugin ][ $field ], true );
			if ( 'noindex' === $field ) {
				return 'rankmath' === $plugin ? in_array( 'noindex', (array) $value, true ) : '1' === (string) $value;
			}
			if ( 'focus_keyword' === $field && 'rankmath' === $plugin ) {
				$parts = explode( ',', (string) $value );
				return trim( (string) $parts[0] );
			}
			return (string) $value;
		}
		$own   = array(
			'title'         => self::OWN_TITLE,
			'description'   => self::OWN_DESCRIPTION,
			'focus_keyword' => self::OWN_FOCUS,
			'noindex'       => self::OWN_NOINDEX,
			'canonical'     => self::OWN_CANONICAL,
		);
		$value = get_post_meta( $post_id, $own[ $field ], true );
		return 'noindex' === $field ? '1' === (string) $value : (string) $value;
	}

	/**
	 * Write one field and log the change.
	 *
	 * @param int    $post_id Post id.
	 * @param string $field   title|description|focus_keyword|noindex|canonical.
	 * @param mixed  $value   New value.
	 * @param string $source  fix|auto_meta|bulk|undo|map.
	 * @param array  $args    only_if_empty (bool), group (change group, e.g. a bulk run).
	 * @return int|WP_Error Change id (0 when nothing changed).
	 */
	public static function set( $post_id, $field, $value, $source = 'fix', array $args = array() ) {
		if ( ! in_array( $field, self::FIELDS, true ) || ! get_post( $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_seo_bad_field', __( 'This field cannot be changed.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$value  = self::clean( $field, $value );
		$before = self::get( $post_id, $field );
		if ( ! empty( $args['only_if_empty'] ) && '' !== (string) $before && false !== $before ) {
			return 0;
		}
		if ( $before === $value ) {
			return 0;
		}
		self::put( $post_id, $field, $value );
		return Change_Log::record(
			array(
				'post_id'   => $post_id,
				'kind'      => 'meta',
				'field'     => $field,
				'before'    => is_bool( $before ) ? ( $before ? '1' : '' ) : (string) $before,
				'after'     => is_bool( $value ) ? ( $value ? '1' : '' ) : (string) $value,
				'source'    => $source,
				'plugin'    => Seo_Plugins::active(),
				'group_ref' => isset( $args['group'] ) ? (string) $args['group'] : '',
			)
		);
	}

	/**
	 * Store a value without logging (used by undo too).
	 *
	 * @param int    $post_id Post id.
	 * @param string $field   Field.
	 * @param mixed  $value   Clean value.
	 * @return void
	 */
	public static function put( $post_id, $field, $value ) {
		$shared = self::shared();
		if ( $shared && '' !== (string) call_user_func( array( $shared, 'write_field' ), $post_id, 'noindex' === $field ? 'robots_noindex' : $field, $value ) ) {
			return;
		}
		$plugin  = Seo_Plugins::active();
		$adapter = self::writer_adapter();
		if ( in_array( $field, array( 'title', 'description' ), true ) && $adapter ) {
			$read = call_user_func( array( $adapter, 'read' ), $post_id );
			call_user_func(
				array( $adapter, 'write' ),
				$post_id,
				'title' === $field ? (string) $value : (string) $read['title'],
				'description' === $field ? (string) $value : (string) $read['description']
			);
			// AI Writer's write() skips empty strings; clearing goes through the meta key.
			if ( '' === (string) $value ) {
				self::delete_field( $post_id, $field );
			}
			return;
		}
		if ( 'aioseo' === $plugin && ! isset( self::KEYS['aioseo'][ $field ] ) ) {
			self::aioseo_put( $post_id, $field, $value );
			return;
		}
		if ( isset( self::KEYS[ $plugin ][ $field ] ) ) {
			$key = self::KEYS[ $plugin ][ $field ];
			if ( 'noindex' === $field ) {
				if ( 'rankmath' === $plugin ) {
					$robots   = array_values( array_diff( (array) get_post_meta( $post_id, $key, true ), array( 'noindex', 'index', '' ) ) );
					$robots[] = $value ? 'noindex' : 'index';
					update_post_meta( $post_id, $key, $robots );
				} else {
					// Yoast: 1 = noindex, 2 = index (0/empty = default).
					update_post_meta( $post_id, $key, $value ? '1' : '2' );
				}
				return;
			}
			update_post_meta( $post_id, $key, wp_slash( (string) $value ) );
			return;
		}
		$own = array(
			'title'         => self::OWN_TITLE,
			'description'   => self::OWN_DESCRIPTION,
			'focus_keyword' => self::OWN_FOCUS,
			'noindex'       => self::OWN_NOINDEX,
			'canonical'     => self::OWN_CANONICAL,
		);
		if ( 'noindex' === $field ) {
			if ( $value ) {
				update_post_meta( $post_id, $own[ $field ], '1' );
			} else {
				delete_post_meta( $post_id, $own[ $field ] );
			}
			return;
		}
		if ( '' === (string) $value ) {
			delete_post_meta( $post_id, $own[ $field ] );
		} else {
			update_post_meta( $post_id, $own[ $field ], wp_slash( (string) $value ) );
		}
	}

	/**
	 * Clean a value for a field.
	 *
	 * @param string $field Field.
	 * @param mixed  $value Value.
	 * @return string|bool
	 */
	public static function clean( $field, $value ) {
		switch ( $field ) {
			case 'noindex':
				return (bool) rest_sanitize_boolean( $value );
			case 'canonical':
				return '' === (string) $value ? '' : esc_url_raw( (string) $value, array( 'http', 'https' ) );
			case 'description':
				return trim( sanitize_textarea_field( (string) $value ) );
			default:
				return trim( sanitize_text_field( (string) $value ) );
		}
	}

	/**
	 * WPBot Core's shared SEO adapter (request R-1), when an SEO plugin it
	 * supports is active: one place for every module's SEO field writes.
	 *
	 * @return string Class name or ''.
	 */
	private static function shared() {
		$class = '\\QuantumCloud\\WPBotCore\\Seo\\Seo_Adapter';
		return class_exists( $class ) && method_exists( $class, 'read_field' ) && method_exists( $class, 'write_field' ) && '' !== (string) call_user_func( array( $class, 'active' ) ) ? $class : '';
	}

	/**
	 * AI Writer's older adapter (title and description), used when WPBot
	 * Core has no shared adapter.
	 *
	 * @return string Class name or ''.
	 */
	private static function writer_adapter() {
		$class = '\QuantumCloud\AiWriter\Seo\Seo_Adapter';
		return class_exists( $class ) && method_exists( $class, 'write' ) && method_exists( $class, 'read' ) ? $class : '';
	}

	/**
	 * Remove a title or description from where the active plugin keeps it.
	 *
	 * @param int    $post_id Post id.
	 * @param string $field   title|description.
	 * @return void
	 */
	private static function delete_field( $post_id, $field ) {
		$plugin = Seo_Plugins::active();
		$key    = isset( self::KEYS[ $plugin ][ $field ] ) ? self::KEYS[ $plugin ][ $field ] : ( 'title' === $field ? self::OWN_TITLE : self::OWN_DESCRIPTION );
		delete_post_meta( $post_id, $key );
	}

	/**
	 * AIOSEO model record, or null.
	 *
	 * @param int $post_id Post id.
	 * @return object|null
	 */
	private static function aioseo_record( $post_id ) {
		$model = '\AIOSEO\Plugin\Common\Models\Post';
		if ( ! class_exists( $model ) || ! method_exists( $model, 'getPost' ) ) {
			return null;
		}
		$record = call_user_func( array( $model, 'getPost' ), $post_id );
		return is_object( $record ) && method_exists( $record, 'save' ) ? $record : null;
	}

	/**
	 * Read focus keyword, noindex or canonical from AIOSEO.
	 *
	 * @param int    $post_id Post id.
	 * @param string $field   Field.
	 * @return string|bool
	 */
	private static function aioseo_get( $post_id, $field ) {
		$record = self::aioseo_record( $post_id );
		if ( ! $record ) {
			return 'noindex' === $field ? false : '';
		}
		if ( 'noindex' === $field ) {
			return ! empty( $record->robots_noindex ) && empty( $record->robots_default );
		}
		if ( 'canonical' === $field ) {
			return isset( $record->canonical_url ) ? (string) $record->canonical_url : '';
		}
		$phrases = isset( $record->keyphrases ) ? json_decode( (string) $record->keyphrases, true ) : null;
		return isset( $phrases['focus']['keyphrase'] ) ? (string) $phrases['focus']['keyphrase'] : '';
	}

	/**
	 * Write focus keyword, noindex or canonical to AIOSEO.
	 *
	 * @param int    $post_id Post id.
	 * @param string $field   Field.
	 * @param mixed  $value   Value.
	 * @return void
	 */
	private static function aioseo_put( $post_id, $field, $value ) {
		$record = self::aioseo_record( $post_id );
		if ( ! $record ) {
			return;
		}
		if ( 'noindex' === $field ) {
			$record->robots_default = $value ? 0 : 1;
			$record->robots_noindex = $value ? 1 : 0;
		} elseif ( 'canonical' === $field ) {
			$record->canonical_url = (string) $value;
		} else {
			$phrases            = isset( $record->keyphrases ) ? json_decode( (string) $record->keyphrases, true ) : array();
			$phrases            = is_array( $phrases ) ? $phrases : array();
			$phrases['focus']   = array_merge( isset( $phrases['focus'] ) ? (array) $phrases['focus'] : array(), array( 'keyphrase' => (string) $value ) );
			$record->keyphrases = wp_json_encode( $phrases );
		}
		$record->save();
	}
}
