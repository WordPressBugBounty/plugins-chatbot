<?php
/**
 * Insights' own AI settings: master switch, active provider, an API key and
 * model per provider (a server address for self-hosted ones), feature
 * switches and cost limits.
 *
 * Keys follow the host plugin's AI Settings (Host_Adapter::get_ai_import())
 * live, unless the owner gives Site Insight its own keys (round R3, owner
 * 10 November 2026): "source" is "settings" or "own". Sites that saved
 * settings before R3 follow AI Settings only when the keys here were never
 * edited (every saved key equals the host's); otherwise they keep their own.
 *
 * API keys never leave the server: the admin screen only gets the last
 * four characters.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

use QuantumCloud\Insights\Module;

defined( 'ABSPATH' ) || exit;

final class AI_Settings {

	const OPTION = 'qcin_ai';

	/**
	 * Cached settings.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $cache = null;

	/**
	 * Defaults. Limits of 0 mean "no limit"; every limit is the owner's to change.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'   => false,
			'provider'  => 'openai',
			'providers' => array(),
			'features'  => array_map(
				static function ( $def ) {
					return ! empty( $def['default'] );
				},
				self::feature_defs()
			),
			'limits'    => array(
				'daily_tokens'   => 100000,
				'monthly_tokens' => 2000000,
				'daily_requests' => 200,
			),
			'imported'  => null,
			'source'    => 'settings',
		);
	}

	/**
	 * AI features with their own switch: core features plus those added by
	 * extensions. Each runs through Insight_Service with the shared client,
	 * cache, cost limits and usage log.
	 *
	 * @return array<string,array{label:string,help:string,default:bool}>
	 */
	public static function feature_defs() {
		$defs = array(
			'summary' => array(
				'label'   => __( 'AI session summaries', 'chatbot' ),
				'help'    => __( 'Adds a Summarize button to each recording. It sends a short text description of the visit (pages, clicks and errors) to the provider. Typed values are never sent, and emails and phone numbers are removed first.', 'chatbot' ),
				'default' => true,
			),
		);
		/**
		 * Add AI features. Add this filter early (on qcin/loaded), so the
		 * switch exists whenever settings are read.
		 *
		 * @param array<string,array{label:string,help?:string,default?:bool}> $defs Features by id
		 *        (lowercase letters, digits and _ ; at most 32 characters).
		 */
		$defs = (array) apply_filters( 'qcin/ai_features', $defs );
		$out  = array();
		foreach ( $defs as $id => $def ) {
			if ( ! is_string( $id ) || ! preg_match( '/^[a-z0-9_]{1,32}$/', $id ) || 'test' === $id || ! is_array( $def ) ) {
				continue;
			}
			$out[ $id ] = array(
				'label'   => (string) ( $def['label'] ?? $id ),
				'help'    => (string) ( $def['help'] ?? '' ),
				'default' => ! empty( $def['default'] ),
			);
		}
		return $out;
	}

	/**
	 * All settings, with the one-time import from the host done first.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		if ( null !== self::$cache && array_diff_key( self::feature_defs(), self::$cache['features'] ) ) {
			self::$cache = null; // A feature was added after the first read.
		}
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, null );
			if ( is_array( $stored ) && ! isset( $stored['source'] ) ) {
				// Saved before R3: decided once, kept from then on.
				$stored['source'] = self::edited( $stored ) ? 'own' : 'settings';
				update_option( self::OPTION, $stored, false );
			}
			if ( ! is_array( $stored ) ) {
				$stored = array( 'source' => 'settings' );
			}
			$stored = self::merge( $stored );
			if ( 'own' !== $stored['source'] ) {
				$stored = self::follow( $stored );
			}
			self::$cache = $stored;
		}
		return self::$cache;
	}

	/**
	 * Clear the in-memory cache.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Saved key, model and server address of one provider.
	 *
	 * @param string $provider Provider id.
	 * @return array{key:string,model:string,url:string}
	 */
	public static function provider( $provider ) {
		$all   = self::all();
		$saved = isset( $all['providers'][ $provider ] ) ? $all['providers'][ $provider ] : array();
		$def   = Providers::get( $provider );
		$model = isset( $saved['model'] ) ? (string) $saved['model'] : '';
		$url   = isset( $saved['url'] ) ? (string) $saved['url'] : '';
		if ( '' === $url && $def && Providers::needs_url( $def ) ) {
			$url = (string) ( $def['default_url'] ?? '' );
		}
		return array(
			'key'   => isset( $saved['key'] ) ? (string) $saved['key'] : '',
			'model' => '' !== $model ? $model : ( $def ? (string) $def['model'] : '' ),
			'url'   => $url,
		);
	}

	/**
	 * Client for the active provider (whether or not AI is switched on).
	 *
	 * @return Provider_Client
	 */
	public static function client() {
		$all = self::all();
		$p   = self::provider( $all['provider'] );
		return new Provider_Client( $all['provider'], $p['key'], $p['model'], $p['url'] );
	}

	/**
	 * Whether AI is on and the active provider has a key and model.
	 *
	 * @return bool
	 */
	public static function is_ready() {
		return (bool) self::all()['enabled'] && self::client()->is_available();
	}

	/**
	 * Whether one AI feature can run (AI ready and the feature switched on).
	 *
	 * @param string $feature Feature id.
	 * @return bool
	 */
	public static function feature_on( $feature ) {
		$features = self::all()['features'];
		return self::is_ready() && ! empty( $features[ $feature ] );
	}

	/**
	 * Settings for the admin screen: keys replaced by their last 4 characters.
	 *
	 * @return array<string,mixed>
	 */
	public static function public_view() {
		$all       = self::all();
		$providers = array();
		foreach ( Providers::all() as $id => $def ) {
			$p                = self::provider( $id );
			$providers[ $id ] = array(
				'label'    => (string) $def['label'],
				'model'    => $p['model'],
				'models'   => array_values( (array) $def['models'] ),
				'keyUrl'   => (string) $def['key_url'],
				'hasKey'   => '' !== $p['key'],
				'keyHint'  => '' !== $p['key'] ? substr( $p['key'], -4 ) : '',
				'needsKey' => Providers::needs_key( $def ),
				'needsUrl' => Providers::needs_url( $def ),
				// A server address is not a secret, so it is shown in full.
				'url'      => Providers::needs_url( $def ) ? $p['url'] : '',
			);
		}
		$feature_list = array();
		foreach ( self::feature_defs() as $fid => $def ) {
			$feature_list[] = array(
				'id'    => $fid,
				'label' => $def['label'],
				'help'  => $def['help'],
			);
		}
		return array(
			'enabled'   => (bool) $all['enabled'],
			'provider'  => (string) $all['provider'],
			'providers' => $providers,
			'features'  => $all['features'],
			'featureList' => $feature_list,
			'limits'    => $all['limits'],
			'imported'  => $all['imported'],
			'source'    => (string) $all['source'],
			'hostName'  => self::host_name(),
			'hostUrl'   => self::host_url(),
			'ready'     => self::is_ready(),
			'label'     => self::client()->get_label(),
		);
	}

	/**
	 * Validate and save settings from the admin screen. A provider key is
	 * changed only when a new one is typed in, or removed with clear_key.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed> Public view.
	 */
	public static function update( array $input ) {
		$clean = self::all();
		if ( isset( $input['source'] ) && in_array( $input['source'], array( 'settings', 'own' ), true ) ) {
			if ( 'settings' === $input['source'] && 'own' === $clean['source'] ) {
				// Keep the owner's keys aside: they come back if Site Insight uses its own keys again.
				$clean['own_providers'] = array(
					'providers' => $clean['providers'],
					'provider'  => $clean['provider'],
				);
			} elseif ( 'own' === $input['source'] && 'own' !== $clean['source'] && ! empty( $clean['own_providers']['providers'] ) ) {
				$clean['providers'] = (array) $clean['own_providers']['providers'];
				$clean['provider']  = (string) $clean['own_providers']['provider'];
				unset( $clean['following'] );
			}
			$clean['source'] = (string) $input['source'];
		}
		if ( 'own' !== $clean['source'] && self::changes_keys( $input ) ) {
			$clean['source'] = 'own'; // A key, address, model or provider typed here: Site Insight keeps its own from now on.
		}

		if ( array_key_exists( 'enabled', $input ) ) {
			$clean['enabled'] = (bool) rest_sanitize_boolean( $input['enabled'] );
		}
		if ( isset( $input['provider'] ) && null !== Providers::get( (string) $input['provider'] ) ) {
			$clean['provider'] = (string) $input['provider'];
		}
		if ( isset( $input['providers'] ) && is_array( $input['providers'] ) ) {
			foreach ( $input['providers'] as $id => $p ) {
				if ( null === Providers::get( (string) $id ) || ! is_array( $p ) ) {
					continue;
				}
				$saved = isset( $clean['providers'][ $id ] ) ? $clean['providers'][ $id ] : array();
				if ( isset( $p['model'] ) ) {
					$saved['model'] = self::clean_model( $p['model'] );
				}
				if ( isset( $p['url'] ) ) {
					$saved['url'] = self::clean_url( $p['url'] );
				}
				if ( ! empty( $p['clear_key'] ) ) {
					$saved['key'] = '';
				} elseif ( isset( $p['key'] ) && '' !== self::clean_key( $p['key'] ) ) {
					$saved['key'] = self::clean_key( $p['key'] );
				}
				$clean['providers'][ $id ] = $saved;
			}
		}
		if ( isset( $input['features'] ) && is_array( $input['features'] ) ) {
			foreach ( array_keys( $clean['features'] ) as $feature ) {
				if ( array_key_exists( $feature, $input['features'] ) ) {
					$clean['features'][ $feature ] = (bool) rest_sanitize_boolean( $input['features'][ $feature ] );
				}
			}
		}
		if ( isset( $input['limits'] ) && is_array( $input['limits'] ) ) {
			foreach ( array_keys( $clean['limits'] ) as $limit ) {
				if ( array_key_exists( $limit, $input['limits'] ) ) {
					$clean['limits'][ $limit ] = max( 0, min( 1000000000, (int) $input['limits'][ $limit ] ) );
				}
			}
		}

		self::save( $clean );
		return self::public_view();
	}

	/**
	 * Copy keys and models from the host's AI settings, replacing what is
	 * saved here, and select the host's active provider.
	 *
	 * @return array{copied:string[],active:string}|null Null when the host has nothing to copy.
	 */
	public static function import_from_host() {
		$import = self::host_import();
		if ( ! $import ) {
			return null;
		}
		$clean = self::all();
		if ( 'own' !== $clean['source'] ) {
			return array(
				'copied' => array_keys( $import['providers'] ),
				'active' => $import['active'],
			); // Already live.
		}
		$copied = array();
		foreach ( $import['providers'] as $id => $p ) {
			$saved = isset( $clean['providers'][ $id ] ) ? $clean['providers'][ $id ] : array();
			if ( '' !== $p['key'] ) {
				$saved['key'] = $p['key'];
			}
			if ( '' !== $p['model'] ) {
				$saved['model'] = $p['model'];
			}
			if ( '' !== $p['url'] ) {
				$saved['url'] = $p['url'];
			}
			$clean['providers'][ $id ] = $saved;
			$copied[]                  = $id;
		}
		if ( '' !== $import['active'] ) {
			$clean['provider'] = $import['active'];
		}
		$clean['imported'] = self::import_note( $copied );
		self::save( $clean );
		return array(
			'copied' => $copied,
			'active' => $import['active'],
		);
	}

	/**
	 * Whether the host has AI settings that could be copied.
	 *
	 * @return bool
	 */
	public static function can_import() {
		return null !== self::host_import();
	}

	/**
	 * Name of the host's AI settings screen, for labels.
	 *
	 * @return string
	 */
	public static function host_name() {
		$host = Module::instance()->host();
		return $host ? $host->get_name() : '';
	}

	/**
	 * URL of the host's AI Settings screen.
	 *
	 * @return string
	 */
	public static function host_url() {
		$host = Module::instance()->host();
		return $host && method_exists( $host, 'get_ai_settings_url' ) ? (string) $host->get_ai_settings_url() : '';
	}

	/**
	 * Settings that follow AI Settings: the host's providers, keys and main
	 * provider replace what is stored here (switches, features and limits stay).
	 *
	 * @param array<string,mixed> $settings Merged settings.
	 * @return array<string,mixed>
	 */
	private static function follow( array $settings ) {
		$import                = self::host_import();
		$settings['source']    = 'settings';
		$settings['following'] = true;
		$settings['providers'] = array();
		if ( $import ) {
			foreach ( $import['providers'] as $id => $p ) {
				$settings['providers'][ $id ] = array_filter( $p, 'strlen' );
			}
			$settings['provider'] = $import['active'];
		}
		return $settings;
	}

	/**
	 * Whether saved settings (from before R3) hold keys the owner edited:
	 * any saved key, address or model that differs from the host's.
	 *
	 * @param array<string,mixed> $stored Stored settings.
	 * @return bool
	 */
	private static function edited( array $stored ) {
		$import = self::host_import();
		$host   = $import ? $import['providers'] : array();
		$saved  = isset( $stored['providers'] ) && is_array( $stored['providers'] ) ? $stored['providers'] : array();
		$chosen = isset( $stored['provider'] ) ? (string) $stored['provider'] : '';
		if ( $import && '' !== $chosen && $chosen !== $import['active'] && ! empty( $saved[ $chosen ] ) ) {
			return true; // The owner picked another provider here, with its own settings.
		}
		foreach ( $saved as $id => $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			foreach ( array( 'key', 'url', 'model' ) as $field ) {
				$value = isset( $p[ $field ] ) ? (string) $p[ $field ] : '';
				$theirs = isset( $host[ $id ][ $field ] ) ? (string) $host[ $id ][ $field ] : '';
				if ( '' !== $value && $value !== $theirs ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether an update changes keys, addresses, models or the main provider.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return bool
	 */
	private static function changes_keys( array $input ) {
		$all = self::all();
		if ( isset( $input['provider'] ) && (string) $input['provider'] !== (string) $all['provider'] ) {
			return true;
		}
		if ( empty( $input['providers'] ) || ! is_array( $input['providers'] ) ) {
			return false;
		}
		foreach ( $input['providers'] as $id => $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$now = self::provider( (string) $id );
			if ( ! empty( $p['clear_key'] ) || ( isset( $p['key'] ) && '' !== self::clean_key( $p['key'] ) ) ) {
				return true;
			}
			if ( isset( $p['url'] ) && self::clean_url( $p['url'] ) !== $now['url'] && '' !== self::clean_url( $p['url'] ) ) {
				return true;
			}
			if ( isset( $p['model'] ) && '' !== self::clean_model( $p['model'] ) && self::clean_model( $p['model'] ) !== $now['model'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Normalized import from the host, or null when there is nothing.
	 *
	 * @return array{active:string,providers:array<string,array{key:string,model:string}>}|null
	 */
	private static function host_import() {
		$host = Module::instance()->host();
		if ( ! $host || ! method_exists( $host, 'get_ai_import' ) ) {
			return null;
		}
		$raw = $host->get_ai_import();
		if ( ! is_array( $raw ) || empty( $raw['providers'] ) || ! is_array( $raw['providers'] ) ) {
			return null;
		}
		$providers = array();
		foreach ( $raw['providers'] as $id => $p ) {
			$def = Providers::get( (string) $id );
			if ( null === $def || ! is_array( $p ) ) {
				continue;
			}
			$key   = self::clean_key( isset( $p['key'] ) ? $p['key'] : '' );
			$model = self::clean_model( isset( $p['model'] ) ? $p['model'] : '' );
			$url   = self::clean_url( isset( $p['url'] ) ? $p['url'] : '' );
			// Only providers that are actually set up.
			if ( ( Providers::needs_key( $def ) && '' === $key ) || ( Providers::needs_url( $def ) && '' === $url ) ) {
				continue;
			}
			if ( '' === $key && '' === $url ) {
				continue;
			}
			$providers[ (string) $id ] = array(
				'key'   => $key,
				'model' => $model,
				'url'   => $url,
			);
		}
		if ( ! $providers ) {
			return null;
		}
		$active = isset( $raw['active'] ) ? (string) $raw['active'] : '';
		if ( ! isset( $providers[ $active ] ) ) {
			reset( $providers );
			$active = (string) key( $providers );
		}
		return array(
			'active'    => $active,
			'providers' => $providers,
		);
	}

	/**
	 * Record of the last copy.
	 *
	 * @param string[] $copied Provider ids.
	 * @return array<string,mixed>
	 */
	private static function import_note( array $copied ) {
		return array(
			'from'      => self::host_name(),
			'at'        => gmdate( 'c' ),
			'providers' => array_values( $copied ),
		);
	}

	/**
	 * Merge stored values over defaults (one level deep for groups).
	 *
	 * @param array<string,mixed> $stored Stored.
	 * @return array<string,mixed>
	 */
	private static function merge( array $stored ) {
		$out = wp_parse_args( $stored, self::defaults() );
		foreach ( array( 'features', 'limits' ) as $group ) {
			$out[ $group ] = wp_parse_args( is_array( $out[ $group ] ) ? $out[ $group ] : array(), self::defaults()[ $group ] );
		}
		if ( ! is_array( $out['providers'] ) ) {
			$out['providers'] = array();
		}
		if ( null === Providers::get( (string) $out['provider'] ) ) {
			$out['provider'] = 'openai';
		}
		return $out;
	}

	/**
	 * Save and refresh the cache.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private static function save( array $settings ) {
		if ( 'own' !== $settings['source'] ) {
			// Following AI Settings: keys are read live, never stored here.
			$settings['providers'] = array();
			$settings['imported']  = null;
		} elseif ( isset( $settings['following'] ) ) {
			// Just switched to own keys: keep the followed keys as the starting copy.
			$settings['imported'] = self::import_note( array_keys( $settings['providers'] ) );
		}
		unset( $settings['following'] );
		update_option( self::OPTION, $settings, false );
		self::$cache = null;
	}

	/**
	 * API keys: printable ASCII, no spaces.
	 *
	 * @param mixed $key Raw key.
	 * @return string
	 */
	private static function clean_key( $key ) {
		$key = is_string( $key ) ? trim( $key ) : '';
		return substr( (string) preg_replace( '/[^\x21-\x7E]/', '', $key ), 0, 400 );
	}

	/**
	 * Server address: an http(s) URL without query or fragment.
	 *
	 * @param mixed $url Raw URL.
	 * @return string
	 */
	private static function clean_url( $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		$clean = strtolower( $parts['scheme'] ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' ) . ( isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '' );
		return substr( esc_url_raw( $clean ), 0, 255 );
	}

	/**
	 * Model ids: letters, digits and . _ - : / @.
	 *
	 * @param mixed $model Raw model.
	 * @return string
	 */
	private static function clean_model( $model ) {
		$model = is_string( $model ) ? trim( $model ) : '';
		return substr( (string) preg_replace( '/[^A-Za-z0-9._\-:\/@]/', '', $model ), 0, 100 );
	}
}
