<?php
/**
 * SEO adapter: writes the meta title and description to the one active SEO
 * plugin (Yoast SEO, Rank Math or All in One SEO) through WPBot Core's
 * shared adapter, and to AI Writer's own underscore-prefixed meta only when
 * no SEO plugin is active at all.
 *
 * It never writes another plugin's scores or analysis fields. In the block
 * editor the values travel as a pending meta field, so nothing is stored
 * until the owner saves the post.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Seo;

use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\WPBotCore\Seo\Seo_Adapter as Core_Seo;

defined( 'ABSPATH' ) || exit;

/**
 * SEO plugin detection and meta writes.
 */
final class Seo_Adapter {

	const OWN_TITLE       = '_qcld_wpbot_writer_meta_title';
	const OWN_DESCRIPTION = '_qcld_wpbot_writer_meta_description';
	const PENDING         = '_qcld_wpbot_writer_seo_pending';
	const RECENT          = Core_Seo::RECENT;

	/**
	 * Meta keys per plugin (title, description).
	 *
	 * @var array<string,string[]>
	 */
	const KEYS = array(
		'yoast'    => array( Core_Seo::KEYS['yoast']['title'], Core_Seo::KEYS['yoast']['description'] ),
		'rankmath' => array( Core_Seo::KEYS['rankmath']['title'], Core_Seo::KEYS['rankmath']['description'] ),
		'aioseo'   => array( Core_Seo::KEYS['aioseo']['title'], Core_Seo::KEYS['aioseo']['description'] ),
	);

	/**
	 * Hook the adapter.
	 *
	 * @return void
	 */
	public static function hook() {
		/**
		 * Change the detected SEO plugin (kept for compatibility; feeds
		 * WPBot Core's qcld_wpbot_core/seo_plugin).
		 *
		 * @param string $active yoast, rankmath, aioseo or ''.
		 */
		add_filter(
			'qcld_wpbot_core/seo_plugin',
			static function ( $found ) {
				return (string) apply_filters( 'qcld_wpbot_writer/seo_plugin', $found );
			}
		);
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'qcld_wpbot_writer/meta_ready', array( __CLASS__, 'from_job' ), 10, 2 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'apply_pending' ), 20, 1 );
		add_action( 'save_post', array( __CLASS__, 'classic_save' ), 20, 1 );
		// All in One SEO saves its own editor fields after the post; keep fresh AI Writer values.
		add_filter( 'aioseo_save_post', array( __CLASS__, 'aioseo_keep' ), 10, 2 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- All in One SEO's own filter.
		if ( ! is_admin() ) {
			add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
			add_action( 'wp_head', array( __CLASS__, 'head_description' ), 1 );
		}
	}

	/**
	 * Active SEO plugin id ('' when none).
	 *
	 * @return string yoast, rankmath, aioseo or ''.
	 */
	public static function active() {
		return Core_Seo::active();
	}

	/**
	 * Owner-facing name of the active SEO plugin.
	 *
	 * @return string
	 */
	public static function active_label() {
		$labels = array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'aioseo'   => 'All in One SEO',
		);
		$active = self::active();
		if ( isset( $labels[ $active ] ) ) {
			return $labels[ $active ];
		}
		$other = Core_Seo::label();
		/* translators: %s: SEO plugin name. */
		return '' !== $other ? sprintf( __( '%s (AI Writer cannot write to it; copy the values)', 'chatbot' ), $other ) : __( 'AI Writer (no SEO plugin active)', 'chatbot' );
	}

	/**
	 * Current meta title and description of a post, from where they live.
	 *
	 * @param int $post_id Post id.
	 * @return array{title: string, description: string, plugin: string}
	 */
	public static function read( $post_id ) {
		$active = self::active();
		$keys   = '' === $active ? array( self::OWN_TITLE, self::OWN_DESCRIPTION ) : self::KEYS[ $active ];
		return array(
			'title'       => (string) get_post_meta( $post_id, $keys[0], true ),
			'description' => (string) get_post_meta( $post_id, $keys[1], true ),
			'plugin'      => $active,
		);
	}

	/**
	 * Write meta to the active SEO plugin, or to AI Writer's own meta.
	 *
	 * @param int    $post_id     Post id.
	 * @param string $title       Meta title ('' leaves it unchanged).
	 * @param string $description Meta description ('' leaves it unchanged).
	 * @return string Where it was written.
	 */
	public static function write( $post_id, $title, $description ) {
		$active = self::active();
		if ( '' !== $active && '' !== Core_Seo::write_meta( $post_id, $title, $description ) ) {
			return $active;
		}
		if ( '' !== Core_Seo::detect() ) {
			// Another SEO plugin prints the meta: never add a second set.
			return 'none';
		}
		$title       = sanitize_text_field( (string) $title );
		$description = sanitize_text_field( (string) $description );
		if ( '' !== $title ) {
			update_post_meta( $post_id, self::OWN_TITLE, wp_slash( $title ) );
		}
		if ( '' !== $description ) {
			update_post_meta( $post_id, self::OWN_DESCRIPTION, wp_slash( $description ) );
		}
		return 'own';
	}

	/**
	 * All in One SEO's editor sends its own title and description after the
	 * post is saved. When AI Writer wrote them moments before and the editor
	 * sends nothing new (empty, its default smart-tag template, or the value
	 * from before), keep AI Writer's.
	 * Values the owner typed into All in One SEO's fields still win.
	 *
	 * @param array  $data Data All in One SEO is about to save.
	 * @param object $post Its post record.
	 * @return array
	 */
	public static function aioseo_keep( $data, $post ) {
		$post_id = is_object( $post ) && ! empty( $post->post_id ) ? (int) $post->post_id : 0;
		$recent  = $post_id ? get_transient( self::RECENT . $post_id ) : false;
		if ( ! is_array( $recent ) || ! is_array( $data ) ) {
			return $data;
		}
		foreach ( array( 'title', 'description' ) as $field ) {
			$incoming = isset( $data[ $field ] ) ? (string) $data[ $field ] : '';
			// Only smart tags (for example "#post_title #separator_sa #site_title") is the plugin's default, not owner text.
			$template = '' !== trim( $incoming ) && ! preg_match( '/(^|\s)[^#\s]/', trim( $incoming ) );
			if ( '' !== $recent[ $field ] && ( '' === $incoming || $template || $incoming === $recent['before'][ $field ] ) ) {
				$data[ $field ] = $recent[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Meta from a finished "Write something new" job.
	 *
	 * @param int   $post_id Post id.
	 * @param array $meta    meta_title, meta_description.
	 * @return void
	 */
	public static function from_job( $post_id, $meta ) {
		$meta = (array) $meta;
		self::write(
			(int) $post_id,
			isset( $meta['meta_title'] ) ? $meta['meta_title'] : '',
			isset( $meta['meta_description'] ) ? $meta['meta_description'] : ''
		);
	}

	/**
	 * The pending field lets the block editor hold meta until the owner
	 * saves; only users who can edit the post may set it.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_meta(
			'post',
			self::PENDING,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_pending' ),
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);
	}

	/**
	 * Keep only a small JSON object with a title and description.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_pending( $value ) {
		$data = json_decode( (string) $value, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		return (string) wp_json_encode(
			array(
				'title'       => isset( $data['title'] ) ? Prompts::cut( sanitize_text_field( (string) $data['title'] ), 300 ) : '',
				'description' => isset( $data['description'] ) ? Prompts::cut( sanitize_text_field( (string) $data['description'] ), 600 ) : '',
			)
		);
	}

	/**
	 * After the owner saves: move pending meta to its real place.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function apply_pending( $post_id ) {
		$raw = (string) get_post_meta( $post_id, self::PENDING, true );
		if ( '' === $raw ) {
			return;
		}
		delete_post_meta( $post_id, self::PENDING );
		$data = json_decode( $raw, true );
		if ( is_array( $data ) && current_user_can( 'edit_post', $post_id ) ) {
			self::write( $post_id, isset( $data['title'] ) ? $data['title'] : '', isset( $data['description'] ) ? $data['description'] : '' );
		}
	}

	/**
	 * Classic Editor: the AI Writer box sends chosen meta with the form.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function classic_save( $post_id ) {
		if ( ! isset( $_POST['qcwr_seo_pending'], $_POST['qcwr_seo_nonce'] ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['qcwr_seo_nonce'] ) ), 'qcwr_seo_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw  = self::sanitize_pending( wp_unslash( $_POST['qcwr_seo_pending'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by sanitize_pending().
		$data = json_decode( $raw, true );
		if ( is_array( $data ) && ( '' !== $data['title'] || '' !== $data['description'] ) ) {
			self::write( $post_id, $data['title'], $data['description'] );
		}
	}

	/**
	 * Front end, no SEO plugin: use AI Writer's meta title.
	 *
	 * @param string $title Title from earlier filters.
	 * @return string
	 */
	public static function document_title( $title ) {
		if ( '' !== Core_Seo::detect() || ! is_singular() ) {
			return $title;
		}
		$own = (string) get_post_meta( (int) get_queried_object_id(), self::OWN_TITLE, true );
		// WordPress prints a pre_get_document_title value as is, so escape it here.
		return '' !== $own ? esc_html( $own ) : $title;
	}

	/**
	 * Front end, no SEO plugin: print AI Writer's meta description.
	 *
	 * @return void
	 */
	public static function head_description() {
		if ( '' !== Core_Seo::detect() || ! is_singular() ) {
			return;
		}
		$own = (string) get_post_meta( (int) get_queried_object_id(), self::OWN_DESCRIPTION, true );
		if ( '' !== $own ) {
			echo '<meta name="description" content="' . esc_attr( $own ) . '" />' . "\n";
		}
	}
}
