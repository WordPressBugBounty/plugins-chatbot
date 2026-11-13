<?php
/**
 * Per-module AI keys.
 *
 * Each module (AI Writer, SEO & AI Search, Social…) has a key set with a
 * source (round R3, owner 10 November 2026):
 *
 * - "settings" (the default, also for sets saved before R3): the set follows
 *   the host's AI Settings live. Keys, models, the primary and the backup are
 *   read from the host on every request and never copied. Only the image
 *   model can be chosen per module.
 * - "own": the module keeps its own copy of the keys, edited on its screen.
 *   Switching to "own" starts from a copy of the host's AI Settings; any key,
 *   switch or provider change saved in "settings" mode switches to "own".
 *
 * Keys are encrypted with Secret_Box and never leave the server: the public
 * view returns a masked hint only.
 *
 * At most two providers are on at a time (CR-2): a primary ("active") and an
 * optional secondary that is used only when the primary fails. Every other
 * provider keeps its key and model but stays off. The "enabled" flag of each
 * provider and the "fallback" flag are derived from those two choices.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

use QuantumCloud\WPBotCore\Core;
use QuantumCloud\WPBotCore\Crypto\Secret_Box;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and reads module key sets.
 */
final class Keys {

	const OPTION = 'qcld_wpbot_core_keys';

	/**
	 * Set once the one-time repair of copied, never-saved sets has run.
	 */
	const REPAIRED = 'qcld_wpbot_core_ai_setup_repaired';

	/**
	 * Key set sources.
	 */
	const SOURCE_SETTINGS = 'settings';
	const SOURCE_OWN      = 'own';

	/**
	 * The followed set for this request (built from the host once per request).
	 *
	 * @var array|null
	 */
	private static $followed = null;

	/**
	 * Whether a module's set follows the host's AI Settings.
	 *
	 * @param string $module Module id.
	 * @return bool
	 */
	public static function follows_settings( $module ) {
		// No host with AI Settings (for example SEO Help on its own): nothing to follow.
		if ( null === Core::instance()->ai_host() ) {
			return false;
		}
		$all    = self::load();
		$module = sanitize_key( $module );
		return ! isset( $all[ $module ]['source'] ) || self::SOURCE_OWN !== $all[ $module ]['source'];
	}

	/**
	 * Forget the followed set of this request (after the host's AI Settings change).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$followed = null;
	}

	/**
	 * Raw key set of a module (keys still encrypted). Imports on first read.
	 *
	 * @param string $module Module id.
	 * @return array
	 */
	public static function get_set( $module ) {
		$module = sanitize_key( $module );
		$all    = self::load();
		if ( self::follows_settings( $module ) ) {
			return self::followed_set( isset( $all[ $module ] ) && is_array( $all[ $module ] ) ? $all[ $module ] : array() );
		}
		if ( ! isset( $all[ $module ] ) ) {
			$all[ $module ] = self::import_set( self::empty_set() );
			self::save( $all );
		} elseif ( ! is_array( $all[ $module ] ) || ! array_key_exists( 'secondary', $all[ $module ] ) ) {
			// Sets saved before CR-2: keep the main provider, turn the first other enabled one into the backup if fallback was on, switch the rest off (keys kept).
			$all[ $module ] = self::from_enabled( array_merge( self::empty_set(), (array) $all[ $module ] ) );
			self::save( $all );
		}
		return array_merge( self::empty_set(), $all[ $module ] );
	}

	/**
	 * The set that follows the host's AI Settings, with the module's image models.
	 *
	 * @param array $stored The module's stored record (source and image models).
	 * @return array
	 */
	private static function followed_set( array $stored ) {
		if ( null === self::$followed ) {
			self::$followed = self::import_set( self::empty_set(), true );
		}
		$set           = self::$followed;
		$set['source'] = self::SOURCE_SETTINGS;
		$images        = isset( $stored['image_models'] ) && is_array( $stored['image_models'] ) ? $stored['image_models'] : array();
		foreach ( $images as $id => $model ) {
			if ( ! isset( $set['providers'][ $id ] ) ) {
				$set['providers'][ $id ] = self::empty_provider();
			}
			$set['providers'][ $id ]['image_model'] = (string) $model;
		}
		return $set;
	}

	/**
	 * Copy keys and models from the host again (explicit owner action).
	 * Providers the host has no key for keep their current values.
	 *
	 * @param string $module Module id.
	 * @return array Public view of the new set.
	 */
	public static function reimport( $module ) {
		$module = sanitize_key( $module );
		if ( self::follows_settings( $module ) ) {
			return self::public_view( $module ); // Already live.
		}
		$all            = self::load();
		$current        = isset( $all[ $module ] ) ? array_merge( self::empty_set(), $all[ $module ] ) : self::empty_set();
		$all[ $module ] = self::import_set( $current );
		self::save( $all );
		return self::public_view( $module );
	}

	/**
	 * Save owner changes for one module.
	 *
	 * Accepted keys: source ("settings" or "own"), active (primary), secondary,
	 * fallback, providers => id => [ key, model, image_model, enabled, url,
	 * clear_key ]. A missing or empty "key" keeps the stored key; "clear_key"
	 * removes it.
	 *
	 * "source" => "settings" makes the set follow the host's AI Settings again
	 * (the module's own keys stay stored, unused). "source" => "own" copies the
	 * host's AI Settings into the module's own set. In "settings" mode, an image
	 * model change is kept per module; any other change switches to "own" first.
	 *
	 * Turning a provider on makes it the primary when there is none, else the
	 * secondary when there is none; a third is refused. Turning the primary
	 * off promotes the secondary. "fallback" => false removes the secondary.
	 *
	 * @param string $module  Module id.
	 * @param array  $changes Changes.
	 * @return array|WP_Error Public view.
	 */
	public static function update( $module, array $changes ) {
		$module = sanitize_key( $module );
		$source = isset( $changes['source'] ) ? sanitize_key( (string) $changes['source'] ) : '';
		unset( $changes['source'] );
		if ( self::SOURCE_SETTINGS === $source ) {
			self::use_settings( $module );
		} elseif ( self::SOURCE_OWN === $source && self::follows_settings( $module ) ) {
			self::use_own( $module );
		}
		if ( self::follows_settings( $module ) ) {
			if ( self::only_image_models( $changes ) ) {
				self::save_image_models( $module, $changes );
				return self::public_view( $module );
			}
			self::use_own( $module );
		}
		$set = self::get_set( $module );

		$switch = array();
		if ( isset( $changes['providers'] ) && is_array( $changes['providers'] ) ) {
			foreach ( $changes['providers'] as $id => $values ) {
				$id = sanitize_key( $id );
				if ( ! Providers::exists( $id ) || ! is_array( $values ) ) {
					continue;
				}
				$entry = isset( $set['providers'][ $id ] ) ? $set['providers'][ $id ] : self::empty_provider();

				if ( ! empty( $values['clear_key'] ) ) {
					$entry['key'] = '';
				} elseif ( isset( $values['key'] ) && '' !== trim( (string) $values['key'] ) ) {
					$plain = self::clean_key( $values['key'] );
					if ( ! Secret_Box::is_available() ) {
						return new WP_Error( 'qcld_wpbot_core_no_crypto', __( 'This server cannot encrypt API keys (the sodium extension is missing). Ask your host to enable it.', 'chatbot' ), array( 'status' => 500 ) );
					}
					$entry['key'] = Secret_Box::encrypt( $plain );
				}
				foreach ( array( 'model', 'image_model' ) as $field ) {
					if ( isset( $values[ $field ] ) ) {
						$entry[ $field ] = self::clean_model( $values[ $field ] );
					}
				}
				if ( isset( $values['enabled'] ) ) {
					$switch[ $id ] = (bool) $values['enabled'];
				}
				if ( isset( $values['url'] ) ) {
					$entry['url'] = esc_url_raw( (string) $values['url'], array( 'http', 'https' ) );
				}
				$set['providers'][ $id ] = $entry;
			}
		}

		// Switches first (off before on), then explicit primary and secondary choices.
		asort( $switch );
		foreach ( $switch as $id => $on ) {
			$id = (string) $id;
			if ( ! $on ) {
				if ( $set['active'] === $id ) {
					$set['active'] = '';
				} elseif ( $set['secondary'] === $id ) {
					$set['secondary'] = '';
				}
				$set = self::settle( $set );
				continue;
			}
			if ( $set['active'] === $id || $set['secondary'] === $id ) {
				continue;
			}
			if ( '' === $set['active'] ) {
				$set['active'] = $id;
			} elseif ( '' === $set['secondary'] ) {
				$set['secondary'] = $id;
			} elseif ( ! isset( $changes['active'] ) && ! isset( $changes['secondary'] ) ) {
				return self::too_many();
			}
		}

		// A provider switched off in the same change is never made primary or secondary.
		$switched_off = static function ( $id ) use ( $switch ) {
			$id = sanitize_key( (string) $id );
			return isset( $switch[ $id ] ) && ! $switch[ $id ];
		};
		if ( isset( $changes['active'] ) && ! $switched_off( $changes['active'] ) ) {
			$active        = sanitize_key( $changes['active'] );
			$set['active'] = Providers::exists( $active ) ? $active : '';
			if ( $set['active'] === $set['secondary'] && ! isset( $changes['secondary'] ) ) {
				$set['secondary'] = '';
			}
		}
		if ( isset( $changes['secondary'] ) && ! $switched_off( $changes['secondary'] ) ) {
			$secondary        = sanitize_key( $changes['secondary'] );
			$set['secondary'] = Providers::exists( $secondary ) && $secondary !== $set['active'] ? $secondary : '';
		} elseif ( isset( $changes['fallback'] ) && ! $changes['fallback'] ) {
			$set['secondary'] = '';
		}
		$set                = self::settle( $set );
		$set['owner_saved'] = true;

		$all            = self::load();
		$all[ $module ] = $set;
		self::save( $all );

		return self::public_view( $module );
	}

	/**
	 * Follow the host's AI Settings. The module's own keys stay stored for a switch back.
	 *
	 * @param string $module Module id.
	 * @return void
	 */
	private static function use_settings( $module ) {
		$all = self::load();
		$set = isset( $all[ $module ] ) && is_array( $all[ $module ] ) ? $all[ $module ] : array();
		if ( isset( $set['providers'] ) ) {
			$set['own'] = array_diff_key( $set, array_flip( array( 'source', 'image_models', 'own' ) ) );
			$set        = array_intersect_key( $set, array_flip( array( 'image_models', 'own' ) ) );
		}
		$set['source']  = self::SOURCE_SETTINGS;
		$all[ $module ] = $set;
		self::save( $all );
	}

	/**
	 * Use the module's own keys: the ones it had before it followed AI Settings, else a copy
	 * of the host's AI Settings.
	 *
	 * @param string $module Module id.
	 * @return void
	 */
	private static function use_own( $module ) {
		$all = self::load();
		if ( isset( $all[ $module ]['own'] ) && is_array( $all[ $module ]['own'] ) && ! empty( $all[ $module ]['own']['providers'] ) ) {
			// The module had its own keys before it followed AI Settings: they come back.
			$set            = array_merge( self::empty_set(), $all[ $module ]['own'] );
			$set['source']  = self::SOURCE_OWN;
			$all[ $module ] = $set;
			self::save( $all );
			return;
		}
		$set                = self::get_set( $module ); // The followed set: keys already encrypted.
		$set['source']      = self::SOURCE_OWN;
		$set['owner_saved'] = true;
		$all[ $module ]     = $set;
		self::save( $all );
	}

	/**
	 * Whether a change only picks image models.
	 *
	 * @param array $changes Changes.
	 * @return bool
	 */
	private static function only_image_models( array $changes ) {
		if ( array() === $changes ) {
			return true;
		}
		if ( array( 'providers' ) !== array_keys( $changes ) || ! is_array( $changes['providers'] ) ) {
			return false;
		}
		foreach ( $changes['providers'] as $values ) {
			if ( ! is_array( $values ) || array( 'image_model' ) !== array_keys( $values ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Store image models of a set that follows AI Settings.
	 *
	 * @param string $module  Module id.
	 * @param array  $changes Changes (image models only).
	 * @return void
	 */
	private static function save_image_models( $module, array $changes ) {
		if ( empty( $changes['providers'] ) ) {
			return;
		}
		$all    = self::load();
		$set    = isset( $all[ $module ] ) && is_array( $all[ $module ] ) ? $all[ $module ] : array();
		$images = isset( $set['image_models'] ) && is_array( $set['image_models'] ) ? $set['image_models'] : array();
		foreach ( $changes['providers'] as $id => $values ) {
			$id = sanitize_key( $id );
			if ( Providers::exists( $id ) ) {
				$images[ $id ] = self::clean_model( $values['image_model'] );
			}
		}
		$set['image_models'] = $images;
		$set['source']       = isset( $set['source'] ) ? $set['source'] : self::SOURCE_SETTINGS;
		$all[ $module ]      = $set;
		self::save( $all );
	}

	/**
	 * Settings view safe to send to the browser: keys are masked.
	 *
	 * @param string $module Module id.
	 * @return array
	 */
	public static function public_view( $module ) {
		$set       = self::get_set( $module );
		$providers = array();
		foreach ( Providers::all() as $id => $definition ) {
			$entry = isset( $set['providers'][ $id ] ) ? $set['providers'][ $id ] : self::empty_provider();
			$plain = Secret_Box::decrypt( $entry['key'] );

			$providers[ $id ] = array(
				'label'       => $definition['label'],
				'needs_key'   => ! empty( $definition['needs_key'] ),
				'needs_url'   => ! empty( $definition['needs_url'] ),
				'has_key'     => is_string( $plain ) && '' !== $plain,
				'key_hint'    => is_string( $plain ) ? Secret_Box::mask( $plain ) : '',
				'unreadable'  => null === $plain,
				'model'       => $entry['model'],
				'image_model' => $entry['image_model'],
				'enabled'     => (bool) $entry['enabled'],
				'url'         => $entry['url'],
				'models'      => $definition['models'],
			);
		}
		$health = array();
		foreach ( Key_Health::notices( $module ) as $notice ) {
			$health[ $notice['provider'] ] = $notice;
		}
		foreach ( $providers as $id => $entry ) {
			$providers[ $id ]['status']        = self::provider_status( $id, $entry, $set, $health );
			$providers[ $id ]['status_reason'] = isset( $health[ $id ] ) ? $health[ $id ]['cause'] : '';
		}
		$host = Core::instance()->ai_host();
		return array(
			'source'           => self::follows_settings( $module ) ? self::SOURCE_SETTINGS : self::SOURCE_OWN,
			'source_name'      => $host ? (string) $host->get_name() : '',
			'source_url'       => $host ? (string) $host->get_ai_settings_url() : '',
			'active'           => $set['active'],
			'secondary'        => $set['secondary'],
			'fallback'         => (bool) $set['fallback'],
			'imported'         => $set['imported'],
			'imported_summary' => self::imported_summary( $set ),
			'owner_saved'      => ! empty( $set['owner_saved'] ),
			'providers'        => $providers,
		);
	}

	/**
	 * Provider settings ready for a call, including the decrypted key.
	 * For server-side use only.
	 *
	 * @param string $module   Module id.
	 * @param string $provider Provider id.
	 * @return array|WP_Error array( key, model, image_model, url, enabled ).
	 */
	public static function credentials( $module, $provider ) {
		$set        = self::get_set( $module );
		$definition = Providers::get( $provider );
		if ( ! $definition ) {
			return new WP_Error( 'qcld_wpbot_core_ai_' . Error_Classifier::BAD_REQUEST );
		}
		$entry = isset( $set['providers'][ $provider ] ) ? $set['providers'][ $provider ] : self::empty_provider();
		$plain = Secret_Box::decrypt( $entry['key'] );
		if ( null === $plain ) {
			return new WP_Error( 'qcld_wpbot_core_ai_' . Error_Classifier::UNREADABLE, '', array( 'cause' => Error_Classifier::UNREADABLE ) );
		}
		if ( ! empty( $definition['needs_key'] ) && '' === $plain ) {
			return new WP_Error( 'qcld_wpbot_core_ai_' . Error_Classifier::NO_KEY, '', array( 'cause' => Error_Classifier::NO_KEY ) );
		}
		$entry['key']   = $plain;
		$entry['model'] = '' !== $entry['model'] ? $entry['model'] : Providers::default_text_model( $provider );
		return $entry;
	}

	/**
	 * Providers that may be called for a module, in order: the primary, then
	 * the secondary (failover) when one is chosen. Never more than two.
	 *
	 * @param string $module Module id.
	 * @return string[]
	 */
	public static function call_order( $module ) {
		$set   = self::get_set( $module );
		$order = array();
		foreach ( array( $set['active'], $set['secondary'] ) as $id ) {
			if ( '' !== $id && Providers::exists( $id ) && ! empty( $set['providers'][ $id ]['enabled'] ) && ! in_array( $id, $order, true ) ) {
				$order[] = $id;
			}
		}
		return $order;
	}

	/**
	 * One-time repair (follow-up 3.0): sets whose keys were copied from the
	 * host but never saved by the owner, and that ended up with no provider
	 * on, get one provider turned on, as a fresh import now does. Sets the
	 * owner saved are never changed. Runs once per site.
	 *
	 * @return string[] Modules repaired.
	 */
	public static function repair_unsaved() {
		if ( get_option( self::REPAIRED ) ) {
			return array();
		}
		update_option( self::REPAIRED, time(), false );

		$all      = self::load();
		$repaired = array();
		$host     = Core::instance()->ai_host();
		$import   = $host ? (array) $host->get_ai_import() : array();
		$prefer   = isset( $import['active'] ) ? sanitize_key( (string) $import['active'] ) : '';
		foreach ( $all as $module => $set ) {
			if ( ! is_array( $set ) || self::SOURCE_OWN !== ( isset( $set['source'] ) ? $set['source'] : '' ) || ! empty( $set['owner_saved'] ) || ! empty( $set['active'] ) ) {
				continue;
			}
			$set    = array_merge( self::empty_set(), $set );
			$copied = isset( $set['imported']['providers'] ) ? array_map( 'strval', (array) $set['imported']['providers'] ) : array();
			if ( array() === $copied ) {
				continue;
			}
			// Only keys that came from the host: a key the owner typed means the owner was here.
			foreach ( $set['providers'] as $id => $entry ) {
				if ( ( '' !== (string) $entry['key'] || '' !== (string) $entry['url'] ) && ! in_array( (string) $id, $copied, true ) ) {
					continue 2;
				}
			}
			$set = self::promote( $set, $prefer, $copied );
			if ( '' !== $set['active'] ) {
				$all[ $module ] = $set;
				$repaired[]     = (string) $module;
			}
		}
		if ( $repaired ) {
			self::save( $all );
		}
		return $repaired;
	}

	/**
	 * Make one copied provider the primary: the host's choice when it was
	 * copied, else the first copied provider that has a key (or an address,
	 * for providers without keys). Its model is the host's model or the
	 * provider's default, so it works straight away.
	 *
	 * @param array    $set    Set.
	 * @param string   $prefer Host's active provider.
	 * @param string[] $copied Providers copied from the host.
	 * @return array
	 */
	private static function promote( array $set, $prefer, array $copied ) {
		$usable = array();
		foreach ( $copied as $id ) {
			$id    = (string) $id;
			$entry = isset( $set['providers'][ $id ] ) ? array_merge( self::empty_provider(), $set['providers'][ $id ] ) : null;
			$def   = Providers::get( $id );
			if ( ! $entry || ! $def ) {
				continue;
			}
			$has_key = '' !== (string) $entry['key'];
			if ( $has_key || ( empty( $def['needs_key'] ) && '' !== (string) $entry['url'] ) ) {
				$usable[] = $id;
			}
		}
		if ( array() === $usable ) {
			return $set;
		}
		$primary = in_array( (string) $prefer, $usable, true ) ? (string) $prefer : $usable[0];
		if ( '' === (string) $set['providers'][ $primary ]['model'] ) {
			$set['providers'][ $primary ]['model'] = (string) Providers::default_text_model( $primary );
		}
		$set['active']    = $primary;
		$set['secondary'] = '';
		return self::settle( $set );
	}

	/**
	 * Status of one provider for the settings screen: ready, off (key saved,
	 * turned off), failing (with a reason), or no_key.
	 *
	 * @param string $id     Provider id.
	 * @param array  $entry  Public provider entry.
	 * @param array  $set    Raw set.
	 * @param array  $health provider => Key_Health notice.
	 * @return string
	 */
	private static function provider_status( $id, array $entry, array $set, array $health ) {
		$usable = $entry['has_key'] || ( ! $entry['needs_key'] && '' !== (string) $entry['url'] );
		if ( ! $usable ) {
			return $entry['unreadable'] ? 'failing' : 'no_key';
		}
		if ( isset( $health[ $id ] ) ) {
			return 'failing';
		}
		$on = in_array( (string) $id, array( (string) $set['active'], (string) $set['secondary'] ), true ) && ! empty( $entry['enabled'] );
		return $on ? 'ready' : 'off';
	}

	/**
	 * Line for the settings screen: where the keys came from and which
	 * provider is on.
	 *
	 * @param array $set Raw set.
	 * @return string
	 */
	private static function imported_summary( array $set ) {
		if ( empty( $set['imported']['at'] ) || empty( $set['imported']['providers'] ) ) {
			return '';
		}
		$date = wp_date( get_option( 'date_format' ), (int) $set['imported']['at'] );
		$from = (string) $set['imported']['from'];
		$def  = '' !== (string) $set['active'] ? Providers::get( $set['active'] ) : null;
		if ( $def ) {
			/* translators: 1: host plugin name, 2: date, 3: AI provider name. */
			return sprintf( __( 'Copied from %1$s > AI Settings on %2$s. %3$s is on as your main AI.', 'chatbot' ), $from, $date, $def['label'] );
		}
		/* translators: 1: host plugin name, 2: date. */
		return sprintf( __( 'Copied from %1$s > AI Settings on %2$s. No provider is turned on yet.', 'chatbot' ), $from, $date );
	}

	/**
	 * Error for a third provider being turned on.
	 *
	 * @return WP_Error
	 */
	private static function too_many() {
		return new WP_Error(
			'qcld_wpbot_core_two_providers',
			__( 'Only two AI providers can be on: a primary and a secondary (backup). Turn one off or change the secondary first.', 'chatbot' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Choose primary and secondary from the enabled flags (sets saved before
	 * CR-2, and first imports): the active provider (or the first enabled one)
	 * is primary; when fallback was on, the first other enabled provider is
	 * secondary. Every other provider is switched off with its key kept.
	 *
	 * @param array $set Set.
	 * @return array
	 */
	private static function from_enabled( array $set ) {
		$enabled = array();
		foreach ( (array) $set['providers'] as $id => $entry ) {
			if ( ! empty( $entry['enabled'] ) && Providers::exists( (string) $id ) ) {
				$enabled[] = (string) $id;
			}
		}
		$primary = in_array( (string) $set['active'], $enabled, true ) ? (string) $set['active'] : ( $enabled ? $enabled[0] : '' );
		$backup  = '';
		if ( ! empty( $set['fallback'] ) ) {
			foreach ( $enabled as $id ) {
				if ( $id !== $primary ) {
					$backup = $id;
					break;
				}
			}
		}
		$set['active']    = $primary;
		$set['secondary'] = $backup;
		return self::settle( $set );
	}

	/**
	 * Make a set consistent: a missing primary is replaced by the secondary,
	 * the secondary differs from the primary, only those two are enabled and
	 * "fallback" says whether a secondary exists.
	 *
	 * @param array $set Set.
	 * @return array
	 */
	private static function settle( array $set ) {
		$primary   = sanitize_key( (string) $set['active'] );
		$secondary = sanitize_key( (string) $set['secondary'] );
		$primary   = Providers::exists( $primary ) ? $primary : '';
		$secondary = Providers::exists( $secondary ) && $secondary !== $primary ? $secondary : '';
		if ( '' === $primary && '' !== $secondary ) {
			$primary   = $secondary;
			$secondary = '';
		}
		foreach ( array( $primary, $secondary ) as $id ) {
			if ( '' !== $id && ! isset( $set['providers'][ $id ] ) ) {
				$set['providers'][ $id ] = self::empty_provider();
			}
		}
		foreach ( (array) $set['providers'] as $id => $entry ) {
			$set['providers'][ $id ]['enabled'] = in_array( (string) $id, array( $primary, $secondary ), true ) && '' !== (string) $id;
		}
		$set['active']    = $primary;
		$set['secondary'] = $secondary;
		$set['fallback']  = '' !== $secondary;
		return $set;
	}

	/**
	 * Fill a set from the host's main AI settings.
	 *
	 * @param array $set    Current set.
	 * @param bool  $follow Build the followed set: the host's on/off choices and
	 *                      backup as they are, nothing turned on that the host has off.
	 * @return array
	 */
	private static function import_set( array $set, $follow = false ) {
		$host = Core::instance()->ai_host();
		if ( ! $host || ! Secret_Box::is_available() ) {
			return $set;
		}
		$import   = (array) $host->get_ai_import();
		$copied   = array();
		$incoming = isset( $import['providers'] ) && is_array( $import['providers'] ) ? $import['providers'] : array();

		foreach ( $incoming as $id => $values ) {
			$id = sanitize_key( $id );
			if ( ! Providers::exists( $id ) || ! is_array( $values ) ) {
				continue;
			}
			$key = isset( $values['key'] ) ? self::clean_key( $values['key'] ) : '';
			$url = isset( $values['url'] ) ? esc_url_raw( (string) $values['url'], array( 'http', 'https' ) ) : '';
			if ( '' === $key && '' === $url ) {
				continue;
			}
			$entry = isset( $set['providers'][ $id ] ) ? $set['providers'][ $id ] : self::empty_provider();
			if ( '' !== $key ) {
				$entry['key'] = Secret_Box::encrypt( $key );
			}
			if ( '' !== $url ) {
				$entry['url'] = $url;
			}
			if ( ! empty( $values['model'] ) ) {
				$entry['model'] = self::clean_model( $values['model'] );
			}
			$entry['enabled']        = ! empty( $values['enabled'] );
			$set['providers'][ $id ] = $entry;
			$copied[]                = $id;
		}

		$active = isset( $import['active'] ) ? sanitize_key( $import['active'] ) : '';
		if ( $follow ) {
			$secondary        = isset( $import['secondary'] ) ? sanitize_key( (string) $import['secondary'] ) : '';
			$set['active']    = in_array( $active, $copied, true ) ? $active : '';
			$set['secondary'] = in_array( $secondary, $copied, true ) ? $secondary : '';
			$set              = '' === $set['active'] ? self::from_enabled( array_merge( $set, array( 'fallback' => '' !== $set['secondary'] ) ) ) : self::settle( $set );
			if ( '' === $set['active'] ) {
				// AI is off in the host (e.g. the chatbot answers without AI): a saved key still works for the other features.
				$set = self::promote( $set, $active, $copied );
			}
		} elseif ( '' === $set['active'] ) {
			// No primary yet: take the host's choice (or its first enabled provider), no secondary.
			if ( in_array( $active, $copied, true ) ) {
				$set['active'] = $active;
			}
			$set['fallback'] = false;
			$set             = self::from_enabled( $set );
			if ( '' === $set['active'] && empty( $set['owner_saved'] ) ) {
				// The host reported every copied provider as off: a copied key should still work, so turn one on
				// (never when the owner saved this set: "Copy again" keeps their choices).
				$set = self::promote( $set, $active, $copied );
			}
		} else {
			// Re-import keeps the owner's primary and secondary.
			$set = self::settle( $set );
		}
		$set['imported'] = array(
			'from'      => (string) $host->get_name(),
			'at'        => time(),
			'providers' => $copied,
		);
		return $set;
	}

	/**
	 * Empty key set.
	 *
	 * @return array
	 */
	private static function empty_set() {
		return array(
			'active'      => '',
			'secondary'   => '',
			'fallback'    => false,
			'providers'   => array(),
			'imported'    => array(),
			'owner_saved' => false,
		);
	}

	/**
	 * Empty provider entry.
	 *
	 * @return array
	 */
	private static function empty_provider() {
		return array(
			'key'         => '',
			'model'       => '',
			'image_model' => '',
			'enabled'     => false,
			'url'         => '',
		);
	}

	/**
	 * Strip whitespace and control characters from a pasted key.
	 *
	 * @param mixed $key Raw key.
	 * @return string
	 */
	private static function clean_key( $key ) {
		return (string) preg_replace( '/[^\x21-\x7E]/', '', (string) $key );
	}

	/**
	 * Allow only characters that appear in model ids.
	 *
	 * @param mixed $model Raw model id.
	 * @return string
	 */
	private static function clean_model( $model ) {
		return substr( (string) preg_replace( '/[^A-Za-z0-9._:\/@-]/', '', (string) $model ), 0, 120 );
	}

	/**
	 * Load the option.
	 *
	 * @return array
	 */
	private static function load() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}

	/**
	 * Save the option (never autoloaded).
	 *
	 * @param array $all All sets.
	 * @return void
	 */
	private static function save( array $all ) {
		update_option( self::OPTION, $all, false );
	}
}
