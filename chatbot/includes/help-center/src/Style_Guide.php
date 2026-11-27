<?php
/**
 * The Help Center style guide (HC3-C, plan 6.3, owner's go 26 November 2026).
 *
 * One house style for help articles: audience, tone, words to use and avoid, how settings paths
 * and screenshots are written, and a short template per article type. Claude reads it over MCP
 * (helpcenter_get_style_guide, and the server's instructions), and AI Writer's "Help article"
 * content type gets it as extra instructions. Prefilled once from WPBot Core's Business Context
 * (setting style_prefill, on by default).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

defined( 'ABSPATH' ) || exit;

/**
 * Style guide.
 */
final class Style_Guide {

	/**
	 * Option.
	 */
	const OPTION = 'qcld_wpbot_help_center_style_guide';

	/**
	 * Article types (the six of plan 6.3).
	 */
	const TYPES = array( 'how-to', 'concept', 'troubleshooting', 'reference', 'faq', 'release-notes' );

	/**
	 * Hook in (only while the Help Center is on).
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_writer/content_types', array( self::class, 'writer_types' ) );
	}

	/**
	 * Type labels.
	 *
	 * @return array<string,string>
	 */
	public static function type_labels() {
		return array(
			'how-to'          => __( 'How-to', 'chatbot' ),
			'concept'         => __( 'Concept', 'chatbot' ),
			'troubleshooting' => __( 'Troubleshooting', 'chatbot' ),
			'reference'       => __( 'Reference', 'chatbot' ),
			'faq'             => __( 'FAQ', 'chatbot' ),
			'release-notes'   => __( 'Release notes', 'chatbot' ),
		);
	}

	/**
	 * Defaults (also what a reset brings back).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'audience'    => '',
			'tone'        => 'Friendly and direct. Short sentences, plain words, "you" for the reader. No marketing language.',
			'words_use'   => '',
			'words_avoid' => '',
			'paths'       => 'Write menu paths with arrows and the exact labels, in bold: **Settings → General**.',
			'screenshots' => 'Mark where a screenshot helps with a line on its own: [Screenshot: what it shows].',
			'templates'   => array(
				'how-to'          => "One sentence: what the reader will be able to do.\n## Before you start (only when something is needed)\n## Steps (numbered, one action each)\n## Check that it worked\n## Related articles",
				'concept'         => "One paragraph: what it is and why it matters.\n## How it works\n## When to use it\n## Related articles",
				'troubleshooting' => "The symptom in the reader's words.\n## Quick checks (most likely cause first)\n## If that did not help\n## Still stuck? (how to contact support)",
				'reference'       => "One sentence: what this lists.\nA table or list: name, what it does, default.\n## Notes",
				'faq'             => 'The question as the title. The answer in the first sentence, then details. Link to the full article when there is one.',
				'release-notes'   => "Version and date.\n## New\n## Improved\n## Fixed\nEach item: what changed for the reader, in one line.",
			),
			'prefilled'   => '',
		);
	}

	/**
	 * Whether the style guide was saved or prefilled.
	 *
	 * @return bool
	 */
	public static function exists() {
		return is_array( get_option( self::OPTION, null ) );
	}

	/**
	 * The style guide (prefilled once from Business Context when allowed).
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			$stored = self::prefill();
		}
		$guide              = array_merge( self::defaults(), $stored );
		$guide['templates'] = array_merge( self::defaults()['templates'], is_array( $guide['templates'] ) ? $guide['templates'] : array() );
		return $guide;
	}

	/**
	 * Prefill from WPBot Core's Business Context (once; Settings: style_prefill, on by default).
	 * Saves what it found, so the owner's later edits are never replaced.
	 *
	 * @return array Stored values.
	 */
	public static function prefill() {
		$values = array();
		if ( '1' === (string) Settings::all()['style_prefill'] ) {
			$context = self::business_context();
			foreach ( array(
				'audience'    => 'audience',
				'words_use'   => 'words',
				'words_avoid' => 'avoid',
			) as $key => $from ) {
				if ( '' !== $context[ $from ] ) {
					$values[ $key ] = $context[ $from ];
				}
			}
			if ( $values ) {
				$values['prefilled'] = gmdate( 'Y-m-d' );
				add_option( self::OPTION, self::clean( array_merge( self::defaults(), $values ) ), '', false );
			}
		}
		return $values ? self::clean( array_merge( self::defaults(), $values ) ) : array();
	}

	/**
	 * Fill in the audience and word lists from Business Context again (the owner asked; the tone
	 * and templates stay).
	 *
	 * @return bool Whether Business Context had anything.
	 */
	public static function refill() {
		$guide   = self::get();
		$context = self::business_context();
		$found   = false;
		foreach ( array(
			'audience'    => 'audience',
			'words_use'   => 'words',
			'words_avoid' => 'avoid',
		) as $key => $from ) {
			if ( '' !== $context[ $from ] ) {
				$guide[ $key ] = $context[ $from ];
				$found         = true;
			}
		}
		if ( $found ) {
			$guide['prefilled'] = gmdate( 'Y-m-d' );
			update_option( self::OPTION, self::clean( $guide ), false );
		}
		return $found;
	}

	/**
	 * Audience, words to use and never say from Business Context ('' when there is none).
	 *
	 * @return array{audience:string,words:string,avoid:string}
	 */
	public static function business_context() {
		$out = array(
			'audience' => '',
			'words'    => '',
			'avoid'    => '',
		);
		if ( ! class_exists( '\QuantumCloud\WPBotCore\Context\Context' ) || ! method_exists( '\QuantumCloud\WPBotCore\Context\Context', 'get' ) ) {
			return $out;
		}
		$data    = (array) \QuantumCloud\WPBotCore\Context\Context::get();
		$summary = isset( $data['summary'] ) && is_array( $data['summary'] ) ? $data['summary'] : array();
		foreach ( array_keys( $out ) as $key ) {
			$value       = isset( $summary[ $key ] ) ? $summary[ $key ] : '';
			$out[ $key ] = trim( is_array( $value ) ? implode( '; ', array_map( 'strval', $value ) ) : (string) $value );
		}
		return $out;
	}

	/**
	 * Sanitize a style guide.
	 *
	 * @param array $raw Raw values.
	 * @return array
	 */
	public static function clean( array $raw ) {
		$out = array();
		foreach ( array( 'audience', 'tone', 'words_use', 'words_avoid', 'paths', 'screenshots' ) as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) ? substr( sanitize_textarea_field( (string) $raw[ $key ] ), 0, 2000 ) : '';
		}
		$out['templates'] = array();
		$templates        = isset( $raw['templates'] ) && is_array( $raw['templates'] ) ? $raw['templates'] : array();
		foreach ( self::TYPES as $type ) {
			$out['templates'][ $type ] = isset( $templates[ $type ] ) ? substr( sanitize_textarea_field( (string) $templates[ $type ] ), 0, 2000 ) : '';
		}
		$out['prefilled'] = isset( $raw['prefilled'] ) ? substr( sanitize_text_field( (string) $raw['prefilled'] ), 0, 10 ) : '';
		return $out;
	}

	/**
	 * Save.
	 *
	 * @param array $raw Raw values.
	 * @return void
	 */
	public static function save( array $raw ) {
		$raw['prefilled'] = self::get()['prefilled'];
		update_option( self::OPTION, self::clean( $raw ), false );
	}

	/**
	 * The style guide as instructions for an AI.
	 *
	 * @param string $type One of TYPES for that template only; '' for all.
	 * @return string
	 */
	public static function text( $type = '' ) {
		$guide  = self::get();
		$labels = array(
			'audience'    => 'Audience',
			'tone'        => 'Tone',
			'words_use'   => 'Words to use',
			'words_avoid' => 'Words to avoid',
			'paths'       => 'Settings paths',
			'screenshots' => 'Screenshots',
		);
		$lines  = array( 'Help center style guide. Follow it in every article.' );
		foreach ( $labels as $key => $label ) {
			if ( '' !== trim( (string) $guide[ $key ] ) ) {
				$lines[] = $label . ': ' . trim( preg_replace( '/\s+/', ' ', (string) $guide[ $key ] ) );
			}
		}
		$types = in_array( $type, self::TYPES, true ) ? array( $type ) : self::TYPES;
		foreach ( $types as $one ) {
			if ( '' !== trim( (string) $guide['templates'][ $one ] ) ) {
				$lines[] = 'Template for ' . $one . ' articles:' . "\n" . trim( (string) $guide['templates'][ $one ] );
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * AI Writer: its "Help article" content type follows the style guide.
	 *
	 * @param array $types Content types.
	 * @return array
	 */
	public static function writer_types( $types ) {
		$types = (array) $types;
		if ( isset( $types['help_article'] ) && is_array( $types['help_article'] ) ) {
			$before                                = isset( $types['help_article']['instructions'] ) ? trim( (string) $types['help_article']['instructions'] ) : '';
			$types['help_article']['instructions'] = trim( $before . "\n" . self::text() );
		}
		return $types;
	}
}
