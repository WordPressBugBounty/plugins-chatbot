<?php
/**
 * Typed Action fields (Store plan step 7b, "the AI Actions extension"): answer options, ranges and
 * conditions for any Action, not only the product finder.
 *
 * A field is still { name, label, required }; these keys are optional and additive:
 * - type: 'text' (default, a free-text string as before), 'choice' or 'range'.
 * - choice: options [ { value, label } ] (a plain string is both), multiple (bool: several answers,
 *   stored as "a, b").
 * - range: min, max, step (a number, clamped and rounded), or bands [ { value, label, min, max } ]
 *   (preset buttons such as budget bands; a number given instead picks the band it falls in).
 * - condition: { field, values[] }: the field is asked and kept only when an earlier field's answer
 *   is one of the values (any answer when values is empty).
 *
 * Choice and range fields reach the AI as `enum` / `minimum` / `maximum` in the tool schema, and the
 * answer options are shown as quick-reply buttons in the widget (Legacy\Ai_Bridge::choices_html()).
 * Actions with no typed field and no condition behave exactly as before.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Typed fields.
 */
final class Fields {

	/**
	 * Most answer options per field.
	 */
	const MAX_OPTIONS = 20;

	/**
	 * A field with every key, normalized.
	 *
	 * @param array $field Field.
	 * @return array name, label, required, type, options, multiple, min, max, step, bands, condition,
	 *               normalized (true).
	 */
	public static function normalize( array $field ) {
		$type = isset( $field['type'] ) ? strtolower( (string) $field['type'] ) : 'text';
		$type = in_array( $type, array( 'choice', 'range' ), true ) ? $type : 'text';
		$out  = array(
			'name'       => isset( $field['name'] ) ? (string) $field['name'] : '',
			'label'      => isset( $field['label'] ) ? (string) $field['label'] : ( isset( $field['name'] ) ? (string) $field['name'] : '' ),
			'required'   => ! empty( $field['required'] ),
			'type'       => $type,
			'options'    => array(),
			'multiple'   => 'choice' === $type && ! empty( $field['multiple'] ),
			'min'        => isset( $field['min'] ) && is_numeric( $field['min'] ) ? (float) $field['min'] : null,
			'max'        => isset( $field['max'] ) && is_numeric( $field['max'] ) ? (float) $field['max'] : null,
			'step'       => isset( $field['step'] ) && is_numeric( $field['step'] ) && (float) $field['step'] > 0 ? (float) $field['step'] : null,
			'bands'      => array(),
			'condition'  => null,
			// Marks a normalized field, so helpers given one do not normalize it again.
			'normalized' => true,
		);
		if ( 'choice' === $type ) {
			foreach ( isset( $field['options'] ) ? (array) $field['options'] : array() as $option ) {
				$option = is_array( $option ) ? $option : array( 'value' => (string) $option );
				$value  = isset( $option['value'] ) ? trim( sanitize_text_field( (string) $option['value'] ) ) : '';
				$label  = isset( $option['label'] ) ? trim( sanitize_text_field( (string) $option['label'] ) ) : '';
				$value  = '' !== $value ? $value : $label;
				if ( '' === $value || count( $out['options'] ) >= self::MAX_OPTIONS ) {
					continue;
				}
				$out['options'][] = array(
					'value' => $value,
					'label' => '' !== $label ? $label : $value,
				);
			}
			if ( ! $out['options'] ) {
				// A choice with nothing to choose stays a free-text field.
				$out['type']     = 'text';
				$out['multiple'] = false;
			}
		}
		if ( 'range' === $type ) {
			foreach ( isset( $field['bands'] ) ? (array) $field['bands'] : array() as $band ) {
				if ( ! is_array( $band ) || count( $out['bands'] ) >= self::MAX_OPTIONS ) {
					continue;
				}
				$min   = isset( $band['min'] ) && is_numeric( $band['min'] ) ? (float) $band['min'] : null;
				$max   = isset( $band['max'] ) && is_numeric( $band['max'] ) ? (float) $band['max'] : null;
				$label = isset( $band['label'] ) ? trim( sanitize_text_field( (string) $band['label'] ) ) : '';
				$value = isset( $band['value'] ) ? trim( sanitize_text_field( (string) $band['value'] ) ) : '';
				$value = '' !== $value ? $value : $label;
				if ( '' === $value || ( null === $min && null === $max ) ) {
					continue;
				}
				$out['bands'][] = array(
					'value' => $value,
					'label' => '' !== $label ? $label : $value,
					'min'   => $min,
					'max'   => $max,
				);
			}
			if ( null !== $out['min'] && null !== $out['max'] && $out['min'] > $out['max'] ) {
				list( $out['min'], $out['max'] ) = array( $out['max'], $out['min'] );
			}
		}//end if
		if ( isset( $field['condition'] ) && is_array( $field['condition'] ) && ! empty( $field['condition']['field'] ) ) {
			$out['condition'] = array(
				'field'  => (string) $field['condition']['field'],
				'values' => array_values(
					array_filter(
						array_map(
							static function ( $value ) {
								return trim( sanitize_text_field( (string) $value ) );
							},
							isset( $field['condition']['values'] ) ? (array) $field['condition']['values'] : array()
						),
						'strlen'
					)
				),
			);
		}
		return $out;
	}

	/**
	 * Whether an Action uses typed fields or conditions (then the new rules apply to it).
	 *
	 * @param array $action Action.
	 * @return bool
	 */
	public static function typed( array $action ) {
		foreach ( isset( $action['fields'] ) ? (array) $action['fields'] : array() as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$field = self::normalize( $field );
			if ( 'text' !== $field['type'] || null !== $field['condition'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a field is shown, given the answers so far (its condition).
	 *
	 * @param array $field Field (normalized or raw).
	 * @param array $data  Answers: name => value.
	 * @return bool
	 */
	public static function visible( array $field, array $data ) {
		$field = ! empty( $field['normalized'] ) ? $field : self::normalize( $field );
		if ( null === $field['condition'] ) {
			return true;
		}
		$name = $field['condition']['field'];
		if ( ! isset( $data[ $name ] ) || '' === (string) $data[ $name ] ) {
			return false;
		}
		if ( ! $field['condition']['values'] ) {
			return true;
		}
		$given   = array_map( 'strtolower', array_map( 'trim', explode( ',', (string) $data[ $name ] ) ) );
		$given[] = strtolower( trim( (string) $data[ $name ] ) );
		foreach ( $field['condition']['values'] as $value ) {
			if ( in_array( strtolower( $value ), $given, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A valid answer for a field, or '' when the answer is not one it accepts.
	 *
	 * @param array $field Field (normalized or raw).
	 * @param mixed $raw   Answer from the AI (string, number or list).
	 * @return string
	 */
	public static function value( array $field, $raw ) {
		$field = ! empty( $field['normalized'] ) ? $field : self::normalize( $field );
		if ( 'choice' === $field['type'] ) {
			return self::choice_value( $field, $raw );
		}
		if ( is_array( $raw ) ) {
			$raw = reset( $raw );
		}
		if ( ! is_scalar( $raw ) ) {
			return '';
		}
		$raw = trim( sanitize_text_field( (string) $raw ) );
		if ( 'text' === $field['type'] || '' === $raw ) {
			return $raw;
		}
		if ( $field['bands'] ) {
			$band = self::option_match( $field['bands'], $raw );
			if ( null !== $band ) {
				return $band['value'];
			}
			$number = self::number( $raw );
			if ( null === $number ) {
				return '';
			}
			foreach ( $field['bands'] as $band ) {
				if ( ( null === $band['min'] || $number >= $band['min'] ) && ( null === $band['max'] || $number <= $band['max'] ) ) {
					return $band['value'];
				}
			}
			return '';
		}
		$number = self::number( $raw );
		if ( null === $number ) {
			return '';
		}
		if ( null !== $field['min'] ) {
			$number = max( $field['min'], $number );
		}
		if ( null !== $field['max'] ) {
			$number = min( $field['max'], $number );
		}
		if ( null !== $field['step'] ) {
			$base   = null !== $field['min'] ? $field['min'] : 0.0;
			$number = $base + round( ( $number - $base ) / $field['step'] ) * $field['step'];
			if ( null !== $field['max'] && $number > $field['max'] ) {
				$number -= $field['step'];
			}
		}
		return self::format( $number );
	}

	/**
	 * The answer buttons of a field: choice options or range bands.
	 *
	 * @param array $field Field (normalized or raw).
	 * @return array[] value, label.
	 */
	public static function choices( array $field ) {
		$field = ! empty( $field['normalized'] ) ? $field : self::normalize( $field );
		$list  = 'choice' === $field['type'] ? $field['options'] : ( 'range' === $field['type'] ? $field['bands'] : array() );
		return array_map(
			static function ( $option ) {
				return array(
					'value' => (string) $option['value'],
					'label' => (string) $option['label'],
				);
			},
			$list
		);
	}

	/**
	 * Clean answers of a typed Action, in the order of its fields: every value checked, fields whose
	 * condition is not met left out.
	 *
	 * @param array $action Action.
	 * @param array $raw    Answers from the AI (declared fields only).
	 * @return array<string,string>
	 */
	public static function clean( array $action, array $raw ) {
		$out = array();
		foreach ( isset( $action['fields'] ) ? (array) $action['fields'] : array() as $field ) {
			if ( ! is_array( $field ) || empty( $field['name'] ) ) {
				continue;
			}
			$field = self::normalize( $field );
			if ( ! array_key_exists( $field['name'], $raw ) || ! self::visible( $field, $out ) ) {
				continue;
			}
			$value = self::value( $field, $raw[ $field['name'] ] );
			if ( '' !== $value ) {
				$out[ $field['name'] ] = substr( $value, 0, 1000 );
			}
		}
		return $out;
	}

	/**
	 * The first shown, required field without an answer, or null when every one is answered.
	 *
	 * @param array $action Action.
	 * @param array $data   Clean answers.
	 * @return array|null Normalized field.
	 */
	public static function next( array $action, array $data ) {
		foreach ( isset( $action['fields'] ) ? (array) $action['fields'] : array() as $field ) {
			if ( ! is_array( $field ) || empty( $field['name'] ) ) {
				continue;
			}
			$field = self::normalize( $field );
			if ( $field['required'] && ! isset( $data[ $field['name'] ] ) && self::visible( $field, $data ) ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * The tool-schema property of a field.
	 *
	 * @param array $field Field (normalized or raw).
	 * @return array
	 */
	public static function property( array $field ) {
		$field       = ! empty( $field['normalized'] ) ? $field : self::normalize( $field );
		$description = '' !== $field['label'] ? $field['label'] : $field['name'];
		if ( null !== $field['condition'] ) {
			$description .= ' Only when ' . $field['condition']['field'] . ( $field['condition']['values'] ? ' is ' . implode( ' or ', $field['condition']['values'] ) : ' is answered' ) . '.';
		}
		if ( 'choice' === $field['type'] ) {
			$values = wp_list_pluck( $field['options'], 'value' );
			if ( $field['multiple'] ) {
				return array(
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
						'enum' => $values,
					),
					'description' => $description . ' One or more of the listed values.',
				);
			}
			return array(
				'type'        => 'string',
				'enum'        => $values,
				'description' => $description,
			);
		}
		if ( 'range' === $field['type'] && $field['bands'] ) {
			return array(
				'type'        => 'string',
				'enum'        => wp_list_pluck( $field['bands'], 'value' ),
				'description' => $description . ' Pick the band that fits the user\'s answer.',
			);
		}
		if ( 'range' === $field['type'] ) {
			$property = array(
				'type'        => 'number',
				'description' => $description,
			);
			if ( null !== $field['min'] ) {
				$property['minimum'] = $field['min'];
			}
			if ( null !== $field['max'] ) {
				$property['maximum'] = $field['max'];
			}
			return $property;
		}
		return array(
			'type'        => 'string',
			'description' => $description,
		);
	}

	/**
	 * A choice answer: option values (or labels), several for a multiple choice.
	 *
	 * @param array $field Normalized field.
	 * @param mixed $raw   Answer.
	 * @return string
	 */
	private static function choice_value( array $field, $raw ) {
		$parts = array();
		if ( is_array( $raw ) ) {
			$parts = $raw;
		} elseif ( is_scalar( $raw ) ) {
			$whole = self::option_match( $field['options'], (string) $raw );
			if ( null !== $whole ) {
				return $whole['value'];
			}
			$parts = $field['multiple'] ? preg_split( '/\s*(?:,|;|\||\band\b)\s*/i', (string) $raw ) : array();
		}
		$chosen = array();
		foreach ( (array) $parts as $part ) {
			if ( ! is_scalar( $part ) ) {
				continue;
			}
			$match = self::option_match( $field['options'], (string) $part );
			if ( null !== $match ) {
				$chosen[ $match['value'] ] = true;
			}
		}
		if ( ! $chosen ) {
			return '';
		}
		if ( ! $field['multiple'] ) {
			return (string) key( $chosen );
		}
		// In the order of the options.
		$out = array();
		foreach ( $field['options'] as $option ) {
			if ( isset( $chosen[ $option['value'] ] ) ) {
				$out[] = $option['value'];
			}
		}
		return implode( ', ', $out );
	}

	/**
	 * The option whose value or label is this text (case-insensitive), or null.
	 *
	 * @param array[] $options Options or bands.
	 * @param string  $text    Text.
	 * @return array|null
	 */
	private static function option_match( array $options, $text ) {
		$text = strtolower( trim( sanitize_text_field( (string) $text ) ) );
		if ( '' === $text ) {
			return null;
		}
		foreach ( $options as $option ) {
			if ( strtolower( (string) $option['value'] ) === $text || strtolower( (string) $option['label'] ) === $text ) {
				return $option;
			}
		}
		return null;
	}

	/**
	 * The first number in a text ("$1,200" = 1200, "under 80" = 80), or null.
	 *
	 * @param string $text Text.
	 * @return float|null
	 */
	public static function number( $text ) {
		$text = preg_replace( '/(?<=\d),(?=\d{3}\b)/', '', (string) $text );
		if ( ! preg_match( '/-?\d+(?:[.,]\d+)?/', (string) $text, $match ) ) {
			return null;
		}
		return (float) str_replace( ',', '.', $match[0] );
	}

	/**
	 * A number as short text ("80", "12.5").
	 *
	 * @param float $number Number.
	 * @return string
	 */
	private static function format( $number ) {
		$text = rtrim( rtrim( number_format( (float) $number, 4, '.', '' ), '0' ), '.' );
		return '-0' === $text ? '0' : $text;
	}
}
