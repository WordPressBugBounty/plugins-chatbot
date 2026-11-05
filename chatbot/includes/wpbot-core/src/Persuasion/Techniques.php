<?php
/**
 * Technique library (round 3, CR-13): one data file per technique in
 * techniques/, read by every module. Each entry has a plain label, the
 * method name for tooltips, a "why this works" note, where it fits and
 * must not be used, AI instructions, examples, the proof it needs and the
 * edition. Modules add or tune entries with qcld_wpbot_core/techniques.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Persuasion;

defined( 'ABSPATH' ) || exit;

/**
 * Technique library.
 */
final class Techniques {

	/** Kinds of entry. */
	const KINDS = array( 'none', 'framework', 'technique', 'angle' );

	/** Section jobs an outline can give a section. */
	const JOBS = array( 'hook', 'problem', 'explain', 'proof', 'objection', 'cta' );

	/** Words that must never appear in AI instructions (brief: no NLP, hypnosis, subliminal, fake FOMO). */
	const BANNED = array( 'neuro-linguistic', 'nlp', 'hypnotic', 'hypnosis', 'subliminal', 'fomo', 'fear of missing out', 'countdown' );

	/** Commercial method names never used as plain labels. */
	const TRADEMARKS = array( 'spin selling', 'challenger sale', 'storybrand' );

	/**
	 * Loaded entries (per request).
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Every technique, keyed by id.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$entries = array();
			foreach ( (array) glob( __DIR__ . '/techniques/*.php' ) as $file ) {
				$entry = include $file;
				if ( is_array( $entry ) && ! empty( $entry['id'] ) ) {
					$entries[ $entry['id'] ] = $entry;
				}
			}
			/**
			 * The technique library. Add or tune entries (same shape as the
			 * files in src/Persuasion/techniques/).
			 *
			 * @param array<string,array> $entries Entries by id.
			 */
			$entries = (array) apply_filters( 'qcld_wpbot_core/techniques', $entries );
			$clean   = array();
			foreach ( $entries as $id => $entry ) {
				if ( is_array( $entry ) && array() === self::problems( $entry ) ) {
					$clean[ (string) $entry['id'] ] = $entry;
				}
			}
			self::$cache = $clean;
		}
		return self::$cache;
	}

	/**
	 * Forget the loaded entries (after a filter changed, and in tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$cache = null;
	}

	/**
	 * One technique, or null.
	 *
	 * @param string $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Techniques that fit.
	 *
	 * @param array $args kind, type (content type or template), stage, job, channel, edition ('free' returns only free entries).
	 * @return array[] Entries, "No specific technique" first.
	 */
	public static function query( array $args = array() ) {
		$out = array();
		foreach ( self::all() as $entry ) {
			if ( ! empty( $args['kind'] ) && ! in_array( $entry['kind'], (array) $args['kind'], true ) ) {
				continue;
			}
			if ( 'none' !== $entry['kind'] ) {
				if ( ! empty( $args['type'] ) && ( ! in_array( $args['type'], $entry['fits']['types'], true ) || in_array( $args['type'], $entry['avoid']['types'], true ) ) ) {
					continue;
				}
				if ( ! empty( $args['stage'] ) && ( ! in_array( $args['stage'], $entry['fits']['stages'], true ) || in_array( $args['stage'], $entry['avoid']['stages'], true ) ) ) {
					continue;
				}
				if ( ! empty( $args['job'] ) && array() !== $entry['fits']['jobs'] && ! in_array( $args['job'], $entry['fits']['jobs'], true ) ) {
					continue;
				}
			}
			if ( isset( $args['edition'] ) && 'free' === $args['edition'] && 'free' !== $entry['edition'] ) {
				continue;
			}
			$out[] = $entry;
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return (int) ( 'none' !== $a['kind'] ) <=> (int) ( 'none' !== $b['kind'] );
			}
		);
		return $out;
	}

	/**
	 * What a technique shows the owner (no AI instructions).
	 *
	 * @param array $entry Entry.
	 * @return array id, kind, group, label, method, why, needs, edition.
	 */
	public static function public_view( array $entry ) {
		return array(
			'id'      => $entry['id'],
			'kind'    => $entry['kind'],
			'group'   => $entry['group'],
			'label'   => $entry['label'],
			'method'  => $entry['method'],
			'why'     => $entry['why'],
			'avoid'   => $entry['avoid']['text'],
			'fits'    => $entry['fits'],
			'needs'   => $entry['needs'],
			'edition' => $entry['edition'],
		);
	}

	/**
	 * Schema problems of an entry (empty when valid).
	 *
	 * @param array $entry Entry.
	 * @return string[]
	 */
	public static function problems( array $entry ) {
		$problems = array();
		foreach ( array( 'id', 'kind', 'group', 'label', 'method', 'why', 'fits', 'avoid', 'instructions', 'examples', 'needs', 'edition' ) as $key ) {
			if ( ! array_key_exists( $key, $entry ) ) {
				$problems[] = 'missing ' . $key;
			}
		}
		if ( $problems ) {
			return $problems;
		}
		if ( ! preg_match( '/^[a-z][a-z0-9_]{1,40}$/', (string) $entry['id'] ) ) {
			$problems[] = 'bad id';
		}
		if ( ! in_array( $entry['kind'], self::KINDS, true ) ) {
			$problems[] = 'bad kind';
		}
		if ( ! in_array( $entry['edition'], array( 'free', 'pro' ), true ) ) {
			$problems[] = 'bad edition';
		}
		if ( '' === trim( (string) $entry['label'] ) || '' === trim( (string) $entry['why'] ) || '' === trim( (string) $entry['instructions'] ) ) {
			$problems[] = 'empty label, why or instructions';
		}
		foreach ( array( 'types', 'stages', 'jobs', 'channels' ) as $key ) {
			if ( ! isset( $entry['fits'][ $key ] ) || ! is_array( $entry['fits'][ $key ] ) ) {
				$problems[] = 'fits.' . $key;
			}
		}
		foreach ( array( 'stages', 'types', 'text' ) as $key ) {
			if ( ! isset( $entry['avoid'][ $key ] ) ) {
				$problems[] = 'avoid.' . $key;
			}
		}
		if ( ! isset( $entry['examples']['good'], $entry['examples']['bad'] ) ) {
			$problems[] = 'examples';
		}
		foreach ( array_merge( isset( $entry['fits']['stages'] ) ? (array) $entry['fits']['stages'] : array(), isset( $entry['avoid']['stages'] ) ? (array) $entry['avoid']['stages'] : array() ) as $stage ) {
			if ( ! in_array( $stage, Stages::IDS, true ) ) {
				$problems[] = 'unknown stage ' . $stage;
			}
		}
		foreach ( isset( $entry['fits']['jobs'] ) ? (array) $entry['fits']['jobs'] : array() as $job ) {
			if ( ! in_array( $job, self::JOBS, true ) ) {
				$problems[] = 'unknown job ' . $job;
			}
		}
		// Prohibitions ("never add countdowns") are allowed; anything else is not.
		$instructions = str_replace( array( 'never add countdowns', 'no countdowns', 'no fake fear of missing out' ), '', strtolower( (string) $entry['instructions'] ) );
		foreach ( self::BANNED as $word ) {
			if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/', $instructions ) ) {
				$problems[] = 'banned word in instructions: ' . $word;
			}
		}
		$label = strtolower( (string) $entry['label'] );
		foreach ( self::TRADEMARKS as $name ) {
			if ( false !== strpos( $label, $name ) ) {
				$problems[] = 'trademark in label: ' . $name;
			}
		}
		return $problems;
	}

	/**
	 * AI instructions for one or more techniques (ids unknown or "none" add
	 * nothing but the plain-writing line). The honesty rules always apply.
	 *
	 * @param string[] $ids Technique ids.
	 * @return string
	 */
	public static function instructions( array $ids ) {
		$lines = array();
		foreach ( array_unique( array_map( 'strval', $ids ) ) as $id ) {
			$entry = self::get( $id );
			if ( $entry ) {
				$lines[] = '- ' . $entry['label'] . ': ' . $entry['instructions'];
			}
		}
		$lines[] = self::honesty_rule();
		return implode( "\n", $lines );
	}

	/**
	 * The rule every persuasive prompt carries.
	 *
	 * @return string
	 */
	public static function honesty_rule() {
		return 'Honesty rules: use only proof, numbers, prices, deadlines and stock given in the facts or the Persuasion Kit; never invent reviews, customer counts, awards, media mentions or quotes; quote customers only when marked as allowed; no countdowns or invented urgency; never change a price; no engagement bait.';
	}
}
