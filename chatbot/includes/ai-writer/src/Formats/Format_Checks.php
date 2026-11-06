<?php
/**
 * CR-16: checks for each article format, run with AI Writer's usual checks
 * before the draft is finished. Each failing check gives the owner one plain
 * sentence; nothing is changed automatically.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Formats;

use QuantumCloud\AiWriter\Checks\Fact_Flags;

defined( 'ABSPATH' ) || exit;

/**
 * Format checks.
 */
final class Format_Checks {

	/**
	 * Run a format's checks.
	 *
	 * @param string  $format   Format id.
	 * @param array[] $sections Sections: heading, blocks.
	 * @param string  $title    Title.
	 * @param string  $evidence Facts the piece may use (owner facts, notes, sources, approved research).
	 * @return array[] code, message.
	 */
	public static function run( $format, array $sections, $title, $evidence ) {
		$recipe = Formats::get( $format );
		if ( null === $recipe ) {
			return array();
		}
		$flags = array();
		foreach ( (array) $recipe['checks'] as $check ) {
			$message = self::check( (string) $check, $sections, (string) $title, (string) $evidence );
			if ( '' !== $message ) {
				$flags[] = array(
					'code'    => (string) $check,
					'message' => $message,
				);
			}
		}
		return $flags;
	}

	/**
	 * One check: '' when it passes, otherwise what to fix.
	 *
	 * @param string  $check    Check id.
	 * @param array[] $sections Sections.
	 * @param string  $title    Title.
	 * @param string  $evidence Evidence text.
	 * @return string
	 */
	public static function check( $check, array $sections, $title, $evidence ) {
		$blocks   = self::blocks( $sections );
		$headings = self::headings( $sections );
		switch ( $check ) {
			case 'count_matches_title':
				if ( ! preg_match( '/\b(\d{1,2})\b/', $title, $m ) || (int) $m[1] < 2 ) {
					return '';
				}
				$items = count( preg_grep( '/^\s*\d+[.)]\s/u', $headings ) );
				/* translators: 1: number in the title, 2: numbered items found. */
				return (int) $m[1] === $items ? '' : sprintf( __( 'The title promises %1$d items but the list has %2$d. Change the title or the list.', 'chatbot' ), (int) $m[1], $items );
			case 'has_table':
				return self::has( $blocks, 'table' ) ? '' : __( 'This format needs a table (for example the summary or the side-by-side comparison). None was written.', 'chatbot' );
			case 'has_numbered_steps':
				foreach ( $blocks as $block ) {
					if ( 'list' === $block['type'] && ! empty( $block['ordered'] ) && count( $block['items'] ) >= 3 ) {
						return '';
					}
				}
				return count( preg_grep( '/^\s*step\s+\d+/iu', $headings ) ) >= 3 ? '' : __( 'No numbered steps were written. A how-to guide needs the steps as a numbered list.', 'chatbot' );
			case 'steps_numbered':
				return count( preg_grep( '/^\s*step\s+\d+/iu', $headings ) ) >= 3 ? '' : __( 'A tutorial needs at least three sections headed "Step 1", "Step 2" and so on.', 'chatbot' );
			case 'has_checklist':
				foreach ( $blocks as $block ) {
					if ( 'list' === $block['type'] && ! empty( $block['checklist'] ) ) {
						return '';
					}
				}
				return __( 'No checklist was written. Each group needs its items as a checklist.', 'chatbot' );
			case 'questions_answered':
				return count( preg_grep( '/\?\s*$/u', $headings ) ) >= 3 ? '' : __( 'An FAQ needs at least three questions, each as its own heading ending with a question mark.', 'chatbot' );
		}
		/**
		 * A check this file does not know (the AI Writer Pro module adds the
		 * checks of its formats): '' when it passes, otherwise what to fix.
		 *
		 * @param string   $message  '' so far.
		 * @param string   $check    Check id.
		 * @param array[]  $blocks   Every block, headings included (Format_Checks::blocks()).
		 * @param string[] $headings Headings (Format_Checks::headings()).
		 * @param string   $title    Title.
		 * @param string   $evidence Facts the piece may use.
		 */
		return (string) apply_filters( 'qcld_wpbot_writer/format_check', '', $check, $blocks, $headings, $title, $evidence );
	}

	/**
	 * Numbers in the text that are not in the evidence. Step and list
	 * numbers ("Step 2", "1.") and single digits are ignored.
	 *
	 * @param array[] $blocks   Blocks.
	 * @param string  $evidence Evidence text.
	 * @return string[]
	 */
	public static function unsupported_numbers( array $blocks, $evidence ) {
		$known = array();
		if ( preg_match_all( '/\d[\d,.]*/u', (string) $evidence, $m ) ) {
			foreach ( $m[0] as $number ) {
				$known[ Fact_Flags::normalise_number( $number ) ] = true;
			}
		}
		$missing = array();
		foreach ( $blocks as $block ) {
			if ( 'heading' === $block['type'] ) {
				continue;
			}
			$text = wp_strip_all_tags( self::text( $block ) );
			$text = preg_replace( '/\bstep\s+\d+/iu', '', $text );
			$text = preg_replace( '/\[[^\]]*\]/u', '', (string) $text );
			if ( ! preg_match_all( '/\d[\d,.]*\d|\d{2,}|\d+%/u', (string) $text, $found ) ) {
				continue;
			}
			foreach ( $found[0] as $number ) {
				$clean = Fact_Flags::normalise_number( rtrim( $number, '%' ) );
				if ( '' !== $clean && ! isset( $known[ $clean ] ) ) {
					$missing[ $clean ] = true;
				}
			}
		}
		return array_map( 'strval', array_keys( $missing ) );
	}

	/**
	 * Every block of the sections, headings included (as heading blocks).
	 *
	 * @param array[] $sections Sections.
	 * @return array[]
	 */
	public static function blocks( array $sections ) {
		$out = array();
		foreach ( $sections as $section ) {
			if ( ! empty( $section['heading'] ) ) {
				$out[] = array(
					'type' => 'heading',
					'text' => (string) $section['heading'],
				);
			}
			foreach ( isset( $section['blocks'] ) ? (array) $section['blocks'] : array() as $block ) {
				if ( is_array( $block ) && isset( $block['type'] ) ) {
					$out[] = $block;
				}
			}
		}
		return $out;
	}

	/**
	 * Section headings and heading blocks inside sections.
	 *
	 * @param array[] $sections Sections.
	 * @return string[]
	 */
	public static function headings( array $sections ) {
		$out = array();
		foreach ( self::blocks( $sections ) as $block ) {
			if ( 'heading' === $block['type'] ) {
				$out[] = wp_strip_all_tags( (string) $block['text'] );
			}
		}
		return $out;
	}

	/**
	 * Whether a block type is present.
	 *
	 * @param array[] $blocks Blocks.
	 * @param string  $type   Type.
	 * @return bool
	 */
	private static function has( array $blocks, $type ) {
		foreach ( $blocks as $block ) {
			if ( $type === $block['type'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Plain text of a block.
	 *
	 * @param array $block Block.
	 * @return string
	 */
	public static function text( array $block ) {
		return \QuantumCloud\AiWriter\Writing\Article_Steps::block_text( $block );
	}
}
