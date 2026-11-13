<?php
/**
 * AI Settings shared by every provider (round R2): the answer settings that used to be saved
 * once per provider (system prompt, streaming, knowledge base, …) are one set, so the backup
 * provider answers the same way as the primary.
 *
 * - Stored in `qcld_wpbot_chat_ai_shared`. The first time it is read, the primary provider's
 *   current values are copied in once (safe to run twice; the old per-provider options stay).
 * - "Extra instructions" replace the old "prompt before/after the user query" fields: their
 *   saved text is copied in as instructions for the AI (added to the system prompt, never shown
 *   to visitors).
 * - Saving writes each value back to every provider's old option too, so WPBot's older answer
 *   handlers (file uploads, Grok collections) read the same settings.
 * - `{site_name}` and `{site_url}` in the prompt are filled in each time the AI answers.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Shared AI settings.
 */
final class Ai_Settings {

	/**
	 * Option: the shared settings.
	 */
	const OPTION = 'qcld_wpbot_chat_ai_shared';

	/**
	 * Option a host adds when it is activated on a site that never had WPBot (its own response
	 * table did not exist yet). The first shared set then turns on the knowledge base and the
	 * current page; the option is deleted once used. Existing sites keep what they have.
	 */
	const NEW_INSTALL_OPTION = 'qcld_wpbot_chat_new_install';

	/**
	 * Defaults for a new install only (owner, 13 November 2026). The answer scope keeps its own
	 * default (Business first).
	 */
	const NEW_INSTALL_DEFAULTS = array(
		'knowledge_base' => true,
		'page_context'   => true,
	);

	/**
	 * Shared settings and their type.
	 */
	const FIELDS = array(
		'system_prompt'  => 'string',
		'instructions'   => 'string',
		'temperature'    => 'float',
		'max_tokens'     => 'int',
		'stream'         => 'bool',
		'knowledge_base' => 'bool',
		'page_context'   => 'bool',
	);

	/**
	 * The shared settings (copied from the primary provider the first time).
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			$stored = self::from_primary();
			$new    = (bool) get_option( self::NEW_INSTALL_OPTION, false );
			if ( $new ) {
				$stored = self::clean( array_merge( $stored, self::NEW_INSTALL_DEFAULTS ) );
			}
			update_option( self::OPTION, $stored, false );
			// The old prepend/append texts are the instructions now (sent in the system text on the
			// server); clear them so nothing adds them to the visitor's question as well.
			self::clear_question_texts();
			if ( $new ) {
				// Older handlers read the providers' own knowledge base options.
				self::write_back( $stored );
				delete_option( self::NEW_INSTALL_OPTION );
			}
		}
		return self::clean( $stored );
	}

	/**
	 * The short prompt used when none is saved (the old screens had a one-line fallback too).
	 *
	 * @return string
	 */
	public static function default_prompt() {
		return __( 'You are the friendly assistant for {site_name} ({site_url}). Answer briefly and accurately from the website\'s information. If you don\'t know, say so and suggest contacting the business.', 'chatbot' );
	}

	/**
	 * The prompt the AI gets: the saved one, else the short default.
	 *
	 * @param string $saved Saved prompt.
	 * @return string
	 */
	public static function prompt( $saved ) {
		$saved = trim( (string) $saved );
		return '' !== $saved ? $saved : self::default_prompt();
	}

	/**
	 * Empty every provider's old prepend and append options.
	 *
	 * @return void
	 */
	public static function clear_question_texts() {
		foreach ( Provider_Settings::map() as $fields ) {
			foreach ( array( 'prepend', 'append' ) as $field ) {
				if ( ! empty( $fields[ $field ] ) && '' !== (string) get_option( $fields[ $field ], '' ) ) {
					update_option( $fields[ $field ], '' );
				}
			}
		}
	}

	/**
	 * The primary provider's current values, as the first shared set.
	 *
	 * @return array<string,mixed>
	 */
	public static function from_primary() {
		$primary = Provider_Settings::answering_provider();
		$raw     = Provider_Settings::get( '' !== $primary ? $primary : 'openai', false );
		$raw     = is_array( $raw ) ? $raw : array();
		$extra   = array_filter( array_map( 'trim', array( isset( $raw['prepend'] ) ? (string) $raw['prepend'] : '', isset( $raw['append'] ) ? (string) $raw['append'] : '' ) ), 'strlen' );
		$set     = array( 'instructions' => implode( "\n", $extra ) );
		foreach ( array( 'system_prompt', 'temperature', 'max_tokens', 'stream', 'knowledge_base', 'page_context' ) as $field ) {
			$set[ $field ] = isset( $raw[ $field ] ) ? $raw[ $field ] : null;
		}
		// Temperature and longest answer: only what the owner saved. A provider's built-in default
		// (Grok's 0, Claude's 1024 tokens) stays that provider's own and is not given to the others.
		$map = Provider_Settings::map();
		$id  = '' !== $primary ? $primary : 'openai';
		foreach ( array( 'temperature', 'max_tokens' ) as $field ) {
			$option = null !== $map[ $id ][ $field ] ? $map[ $id ][ $field ] : Provider_Settings::new_option( $id, $field );
			$saved  = get_option( $option, null );
			if ( null === $saved || '' === $saved || false === $saved ) {
				$set[ $field ] = null;
			}
		}
		if ( empty( $set['system_prompt'] ) || '' === trim( (string) $set['system_prompt'] ) ) {
			// Shown in the box, so the owner sees what the AI gets.
			$set['system_prompt'] = self::default_prompt();
		}
		return self::clean( $set );
	}

	/**
	 * Clean a set.
	 *
	 * @param array $set Values.
	 * @return array<string,mixed>
	 */
	public static function clean( array $set ) {
		$out = array();
		foreach ( self::FIELDS as $field => $type ) {
			$value = isset( $set[ $field ] ) ? $set[ $field ] : null;
			switch ( $type ) {
				case 'bool':
					$out[ $field ] = in_array( $value, array( 1, '1', true, 'true', 'yes', 'on' ), true );
					break;
				case 'int':
					$out[ $field ] = is_numeric( $value ) && (int) $value > 0 ? min( 32000, (int) $value ) : null;
					break;
				case 'float':
					$out[ $field ] = is_numeric( $value ) && '' !== (string) $value ? max( 0.0, min( 2.0, round( (float) $value, 2 ) ) ) : null;
					break;
				default:
					$out[ $field ] = is_scalar( $value ) ? trim( str_replace( "\r", '', (string) $value ) ) : '';
			}
		}
		return $out;
	}

	/**
	 * Save the shared settings and write them back to every provider's old options.
	 *
	 * @param array $values Values (missing keys keep their current value).
	 * @return array<string,mixed> Saved set.
	 */
	public static function save( array $values ) {
		$set = self::clean( array_merge( self::get(), array_intersect_key( $values, self::FIELDS ) ) );
		update_option( self::OPTION, $set, false );
		self::write_back( $set );
		return $set;
	}

	/**
	 * Write the shared settings to the providers' old options (older handlers read those).
	 *
	 * @param array $set Shared set.
	 * @return void
	 */
	public static function write_back( array $set ) {
		foreach ( Provider_Settings::map() as $fields ) {
			foreach ( array( 'system_prompt', 'stream', 'knowledge_base', 'temperature', 'max_tokens' ) as $field ) {
				if ( empty( $fields[ $field ] ) ) {
					continue;
				}
				$value = $set[ $field ];
				if ( 'bool' === self::FIELDS[ $field ] ) {
					$value = $value ? 1 : 0;
				} elseif ( null === $value ) {
					$value = '';
				}
				update_option( $fields[ $field ], $value );
			}
		}//end foreach
		// The old fields were texts added to the visitor's question (and printed in the page):
		// the instructions go only into the system text, on the server.
		self::clear_question_texts();
	}

	/**
	 * The system text the AI gets: the prompt with its placeholders filled, then the extra
	 * instructions.
	 *
	 * @param array $set Shared set (default: saved).
	 * @return string
	 */
	public static function system_text( array $set = array() ) {
		$set = array() === $set ? self::get() : self::clean( $set );
		return trim( self::fill( self::prompt( $set['system_prompt'] ) ) . ( '' !== $set['instructions'] ? "\n\n" . self::fill( $set['instructions'] ) : '' ) );
	}

	/**
	 * Fill {site_name} and {site_url}.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function fill( $text ) {
		$name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
		return strtr(
			(string) $text,
			array(
				'{site_name}' => '' !== $name ? $name : 'this website',
				'{site_url}'  => home_url(),
			)
		);
	}

	/**
	 * A website address in the prompt that is not this site's (for example a copied site's),
	 * or ''.
	 *
	 * @param string $text Prompt.
	 * @return string The address.
	 */
	public static function foreign_url( $text ) {
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$home = preg_replace( '/^www\./', '', $home );
		if ( ! preg_match_all( '#https?://[^\s"\'<>()]+#i', (string) $text, $found ) ) {
			return '';
		}
		$own_aliases = array( $home );
		foreach ( $found[0] as $url ) {
			$host = strtolower( (string) wp_parse_url( rtrim( $url, '.,;:' ), PHP_URL_HOST ) );
			$host = preg_replace( '/^www\./', '', $host );
			if ( '' !== $host && ! in_array( $host, $own_aliases, true ) && ! self::well_known( $host ) ) {
				return rtrim( $url, '.,;:' );
			}
		}
		return '';
	}

	/**
	 * Addresses that are fine in a prompt (providers' own sites and the like).
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	private static function well_known( $host ) {
		foreach ( array( 'wordpress.org', 'wordpress.com', 'google.com', 'youtube.com', 'facebook.com', 'instagram.com', 'wa.me', 'whatsapp.com' ) as $known ) {
			if ( $host === $known || substr( $host, -strlen( '.' . $known ) ) === '.' . $known ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Replace another site's address in the prompt with {site_url} (owner's click).
	 *
	 * @return bool Whether something changed.
	 */
	public static function use_own_address() {
		$set     = self::get();
		$changed = false;
		foreach ( array( 'system_prompt', 'instructions' ) as $field ) {
			$url = self::foreign_url( $set[ $field ] );
			while ( '' !== $url ) {
				$base          = preg_replace( '#^(https?://[^/]+).*$#i', '$1', $url );
				$set[ $field ] = str_ireplace( array( rtrim( $base, '/' ) . '/', rtrim( $base, '/' ) ), '{site_url}', $set[ $field ] );
				$changed       = true;
				$url           = self::foreign_url( $set[ $field ] );
			}
		}
		if ( $changed ) {
			self::save( $set );
		}
		return $changed;
	}

	/**
	 * AI Settings as WPBot Core reads them for the other features (AI Writer, SEO & AI Search,
	 * Social, …): every provider with a key (or Ollama's server), its model and address, the
	 * primary and the backup. Hosts return this from Host_Adapter::get_ai_import(); features that
	 * follow AI Settings read it on every call (round R3). Keys never leave the server.
	 *
	 * @return array{active:string,secondary:string,providers:array<string,array>}
	 */
	public static function core_import() {
		$out = array(
			'active'    => Provider_Settings::answering_provider(),
			'secondary' => Routing::backup(),
			'providers' => array(),
		);
		foreach ( array_keys( Provider_Settings::map() ) as $id ) {
			$settings = Provider_Settings::get( $id, false );
			if ( ! is_array( $settings ) ) {
				continue;
			}
			$key = (string) $settings['key'];
			$url = (string) $settings['url'];
			if ( '' === $key && ( 'ollama' !== $id || '' === $url ) ) {
				continue;
			}
			$entry = array(
				'key'     => $key,
				'model'   => (string) $settings['model'],
				'enabled' => $id === $out['active'] || $id === $out['secondary'],
			);
			if ( '' !== $url ) {
				$entry['url'] = $url;
			}
			$out['providers'][ $id ] = $entry;
		}
		return $out;
	}
}
