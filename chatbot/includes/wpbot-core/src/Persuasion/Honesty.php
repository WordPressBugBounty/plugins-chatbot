<?php
/**
 * Honesty checks (round 3, CR-13): the red lines, enforced in code.
 *
 * Every AI-written piece is checked against the Persuasion Kit, live
 * WooCommerce data and the facts the writer was given:
 * - invented_proof: customer counts, ratings, review counts, awards,
 *   certifications, media mentions, years in business or "number one"
 *   claims that the kit or facts do not back;
 * - fake_urgency: deadlines, "only N left", "limited time" and similar with
 *   no live sale end, real stock or kit offer behind them;
 * - expired_deadline: an offer from the kit that has ended;
 * - price_mismatch: an amount that is not a real price (or derived from
 *   one: per day, per week, per month, the real saving);
 * - quote_without_permission: an attributed quote not marked as allowed;
 * - engagement_bait: "comment YES", "tag a friend" and similar.
 *
 * Pure PHP (no database), so the rules are unit-tested. Flags explain the
 * matched words; callers record them and never let them be hidden.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Persuasion;

defined( 'ABSPATH' ) || exit;

/**
 * Honesty checks.
 */
final class Honesty {

	/** Flag codes. */
	const CODES = array( 'invented_proof', 'fake_urgency', 'expired_deadline', 'price_mismatch', 'quote_without_permission', 'engagement_bait' );

	/**
	 * Check a text.
	 *
	 * @param string $text    Copy (HTML or plain).
	 * @param array  $context kit (entries usable now), expired (expired kit entries), woo (live product data or null), prices (real amounts), facts (other source text the writer was given), today (Y-m-d, for tests).
	 * @return array[] Flags: code, label, message, words (matched text).
	 */
	public static function check( $text, array $context = array() ) {
		$plain = self::plain( (string) $text );
		if ( '' === $plain ) {
			return array();
		}
		$kit     = isset( $context['kit'] ) ? (array) $context['kit'] : array();
		$expired = isset( $context['expired'] ) ? (array) $context['expired'] : array();
		$woo     = isset( $context['woo'] ) && is_array( $context['woo'] ) ? $context['woo'] : null;
		$facts   = isset( $context['facts'] ) ? self::plain( (string) $context['facts'] ) : '';
		$backing = strtolower( $facts . "\n" . self::kit_text( $kit ) );

		$flags = array();
		$found = self::invented_proof( $plain, $kit, $backing );
		if ( $found ) {
			$flags[] = self::flag( 'invented_proof', $found );
		}
		$found = self::fake_urgency( $plain, $kit, $woo );
		if ( $found ) {
			$flags[] = self::flag( 'fake_urgency', $found );
		}
		$found = self::expired_deadline( $plain, $expired );
		if ( $found ) {
			$flags[] = self::flag( 'expired_deadline', $found );
		}
		$found = self::price_mismatch( $plain, $kit, $woo, isset( $context['prices'] ) ? (array) $context['prices'] : array(), $facts );
		if ( $found ) {
			$flags[] = self::flag( 'price_mismatch', $found );
		}
		$found = self::quotes( $plain, $kit );
		if ( $found ) {
			$flags[] = self::flag( 'quote_without_permission', $found );
		}
		$found = self::engagement_bait( $plain );
		if ( $found ) {
			$flags[] = self::flag( 'engagement_bait', $found );
		}
		return $flags;
	}

	/**
	 * Labels and explanations of each flag.
	 *
	 * @return array<string,array{label: string, message: string}>
	 */
	public static function labels() {
		return array(
			'invented_proof'           => array(
				'label'   => __( 'Proof not in your Persuasion Kit', 'chatbot' ),
				'message' => __( 'These claims (numbers, ratings, awards, mentions) are not backed by your Persuasion Kit or the facts given. Add them to the kit if they are true, or remove them.', 'chatbot' ),
			),
			'fake_urgency'             => array(
				'label'   => __( 'Urgency without a real deadline or stock', 'chatbot' ),
				'message' => __( 'This urgency is not backed by a live sale date, real stock or an offer in your kit. Add the real deadline, or remove it.', 'chatbot' ),
			),
			'expired_deadline'         => array(
				'label'   => __( 'Offer that has ended', 'chatbot' ),
				'message' => __( 'This mentions an offer from your kit whose end date has passed.', 'chatbot' ),
			),
			'price_mismatch'           => array(
				'label'   => __( 'Price that does not match', 'chatbot' ),
				'message' => __( 'These amounts do not match a real price from your store or your kit.', 'chatbot' ),
			),
			'quote_without_permission' => array(
				'label'   => __( 'Quote without permission', 'chatbot' ),
				'message' => __( 'This quotes a customer, but no review or testimonial in your kit with "Permission to quote" matches it.', 'chatbot' ),
			),
			'engagement_bait'          => array(
				'label'   => __( 'Engagement bait', 'chatbot' ),
				'message' => __( 'Asks like "comment YES" or "tag a friend" are demoted by social networks. Ask a real question instead.', 'chatbot' ),
			),
		);
	}

	/**
	 * A flag.
	 *
	 * @param string   $code  Code.
	 * @param string[] $words Matched text.
	 * @return array
	 */
	private static function flag( $code, array $words ) {
		$labels = self::labels();
		return array(
			'code'    => $code,
			'label'   => $labels[ $code ]['label'],
			'message' => $labels[ $code ]['message'],
			'words'   => array_values( array_unique( array_slice( $words, 0, 10 ) ) ),
		);
	}

	/**
	 * Claims of proof the kit or facts do not back.
	 *
	 * @param string $text    Plain text.
	 * @param array  $kit     Kit entries.
	 * @param string $backing Lowercase kit and facts text.
	 * @return string[]
	 */
	private static function invented_proof( $text, array $kit, $backing ) {
		$found   = array();
		$numbers = self::numbers( $backing );
		$types   = array_unique( self::column( $kit, 'type' ) );

		// Counts of customers, reviews, orders and the like.
		if ( preg_match_all( '/\b(?:over |more than |nearly |almost |trusted by |loved by |join )?(\d[\d,.\s]*\+?\s*(?:k|m|thousand|million)?)\s+(?:happy |satisfied |loyal |local )?(customers|clients|users|riders|members|people|families|businesses|companies|orders|reviews|ratings|downloads|installs|students|patients|subscribers)\b/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				// Small plain counts ("3 riders") are not proof claims; "trusted by", "happy" or 50+ are.
				$framed = (bool) preg_match( '/\b(over|more than|nearly|almost|trusted|loved|join|happy|satisfied|loyal)\b|\+/i', $match[0] );
				if ( ! $framed && self::count_value( $match[1] ) < 50 ) {
					continue;
				}
				if ( ! self::number_backed( $match[1], $numbers ) ) {
					$found[] = trim( $match[0] );
				}
			}
		}
		// Ratings: 4.8/5, 4.9 out of 5, 5 stars, rated 4.7.
		if ( preg_match_all( '/\b(?:rated\s+)?(\d(?:[.,]\d)?)\s*(?:\/\s*5|out of (?:5|five)|stars?)\b|\brated\s+(\d(?:[.,]\d)?)\b/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$value = '' !== $match[1] ? $match[1] : ( isset( $match[2] ) ? $match[2] : '' );
				if ( ! self::number_backed( $value, $numbers ) ) {
					$found[] = trim( $match[0] );
				}
			}
		}
		// Years in business, written as a number of years or a founding year.
		if ( preg_match_all( '/\b(\d{1,3})\+?\s+years?\s+(?:of\s+experience|in\s+business|of\s+service|serving|experience)\b|\b(?:since|established in|founded in|est\.?)\s+(1[89]\d\d|20\d\d)\b/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$value = '' !== $match[1] ? $match[1] : $match[2];
				if ( ! self::number_backed( $value, $numbers ) && ! self::years_backed( (int) $value, $kit ) ) {
					$found[] = trim( $match[0] );
				}
			}
		}
		// Awards, certifications, media and superlatives that are proof claims.
		$claims = array(
			'certification' => '/\b(award[- ]winning|won (?:an?|the) [\w\s]{0,30}award|certified|accredited|licensed and insured|officially approved)\b/i',
			'media_mention' => '/\b(as seen (?:in|on)|featured (?:in|on|by)|in the press|press coverage)\b/i',
			'superlative'   => '/(#\s?1\b|\bnumber one\b|\bno\.\s?1\b|\bbest[- ]selling\b|\btop[- ]rated\b|\bmost trusted\b|\bindustry[- ]leading\b|\bworld[- ]class\b)/i',
		);
		foreach ( $claims as $type => $pattern ) {
			if ( preg_match_all( $pattern, $text, $matches ) ) {
				foreach ( $matches[0] as $word ) {
					$backed = ( 'superlative' !== $type && in_array( $type, $types, true ) ) || false !== strpos( $backing, strtolower( trim( $word ) ) );
					if ( ! $backed ) {
						$found[] = trim( $word );
					}
				}
			}
		}
		return $found;
	}

	/**
	 * Urgency or scarcity with nothing real behind it.
	 *
	 * @param string     $text Plain text.
	 * @param array      $kit  Kit entries (usable now).
	 * @param array|null $woo  Live product data.
	 * @return string[]
	 */
	private static function fake_urgency( $text, array $kit, $woo ) {
		$found     = array();
		$has_offer = in_array( 'offer', self::column( $kit, 'type' ), true );
		$has_sale  = is_array( $woo ) && ! empty( $woo['sale_end'] );
		$stock     = is_array( $woo ) && isset( $woo['stock_quantity'] ) && null !== $woo['stock_quantity'] ? (int) $woo['stock_quantity'] : null;

		// Stock claims must match live stock exactly.
		if ( preg_match_all( '/\b(?:only|just)\s+(\d+)\s+(?:\w+\s+)?(?:left|remaining|in stock)\b|\b(\d+)\s+(?:left|remaining)\s+in\s+stock\b/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$count = (int) ( '' !== $match[1] ? $match[1] : $match[2] );
				if ( null === $stock || $stock !== $count ) {
					$found[] = trim( $match[0] );
				}
			}
		}
		// Countdowns are never allowed.
		if ( preg_match_all( '/\b(?:countdown|ends in \d+\s*(?:minutes?|hours?|seconds?)|\d{1,2}:\d{2}:\d{2} left)\b/i', $text, $matches ) ) {
			$found = array_merge( $found, $matches[0] );
		}
		// Deadline words need a real deadline.
		if ( ! $has_offer && ! $has_sale ) {
			if ( preg_match_all( '/\b(limited[- ]time(?: offer)?|offer ends|sale ends|ends (?:today|tonight|tomorrow|soon|this week|on \w+|at midnight)|today only|last chance|hurry|act now|don\'t miss out|before it\'s too late|while (?:stocks|supplies) last|selling fast|almost gone|going fast|only a few left)\b/i', $text, $matches ) ) {
				$found = array_merge( $found, $matches[0] );
			}
		}
		return $found;
	}

	/**
	 * Mentions of kit offers that have ended.
	 *
	 * @param string $text    Plain text.
	 * @param array  $expired Expired kit entries.
	 * @return string[]
	 */
	private static function expired_deadline( $text, array $expired ) {
		$found = array();
		$lower = strtolower( $text );
		foreach ( $expired as $entry ) {
			if ( ! is_array( $entry ) || ( isset( $entry['type'] ) && 'offer' !== $entry['type'] ) ) {
				continue;
			}
			foreach ( array( 'value', 'label' ) as $key ) {
				$needle = isset( $entry[ $key ] ) ? strtolower( trim( (string) $entry[ $key ] ) ) : '';
				if ( strlen( $needle ) >= 4 && false !== strpos( $lower, $needle ) ) {
					$found[] = $entry[ $key ] . ( ! empty( $entry['expires_on'] ) ? ' (ended ' . $entry['expires_on'] . ')' : '' );
					break;
				}
			}
		}
		return $found;
	}

	/**
	 * Amounts that are not a real price or derived from one.
	 *
	 * @param string     $text   Plain text.
	 * @param array      $kit    Kit entries.
	 * @param array|null $woo    Live product data.
	 * @param array      $prices Real amounts given by the caller.
	 * @param string     $facts  Facts text.
	 * @return string[]
	 */
	private static function price_mismatch( $text, array $kit, $woo, array $prices, $facts ) {
		$real = array();
		foreach ( $prices as $price ) {
			$real[] = self::amount( (string) $price );
		}
		if ( is_array( $woo ) ) {
			foreach ( array( 'price', 'regular_price', 'sale_price' ) as $key ) {
				if ( isset( $woo[ $key ] ) && '' !== (string) $woo[ $key ] ) {
					$real[] = (float) $woo[ $key ];
				}
			}
		}
		foreach ( $kit as $entry ) {
			foreach ( self::money( (string) ( ( isset( $entry['value'] ) ? $entry['value'] : '' ) . ' ' . ( isset( $entry['body'] ) ? $entry['body'] : '' ) . ' ' . ( isset( $entry['label'] ) ? $entry['label'] : '' ) ) ) as $amount ) {
				$real[] = $amount['value'];
			}
		}
		foreach ( self::money( $facts ) as $amount ) {
			$real[] = $amount['value'];
		}
		$real = array_values(
			array_filter(
				array_unique( $real ),
				static function ( $v ) {
					return $v > 0;
				}
			)
		);

		// Allowed: real prices, per day/week/month/year framing, real savings.
		$allowed = $real;
		foreach ( $real as $a ) {
			foreach ( array( 7, 12, 30, 52, 365 ) as $divisor ) {
				$allowed[] = $a / $divisor;
			}
			foreach ( $real as $b ) {
				if ( $a > $b ) {
					$allowed[] = $a - $b;
				}
			}
		}
		$found = array();
		foreach ( self::money( $text ) as $amount ) {
			$ok = false;
			foreach ( $allowed as $value ) {
				// Within a cent, or the same amount shown rounded (charm prices stay as given).
				if ( abs( $value - $amount['value'] ) < 0.011 || ( $amount['value'] >= 1 && abs( round( $value ) - $amount['value'] ) < 0.001 && abs( $value - $amount['value'] ) < 1 ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				$found[] = $amount['text'];
			}
		}
		return $found;
	}

	/**
	 * Attributed quotes not backed by a quotable kit entry.
	 *
	 * @param string $text Plain text.
	 * @param array  $kit  Kit entries.
	 * @return string[]
	 */
	private static function quotes( $text, array $kit ) {
		$allowed = array();
		foreach ( $kit as $entry ) {
			if ( isset( $entry['type'] ) && in_array( $entry['type'], Kit::QUOTES, true ) && ! empty( $entry['permission'] ) ) {
				$allowed[] = self::words_of( ( isset( $entry['body'] ) ? $entry['body'] : '' ) . ' ' . ( isset( $entry['label'] ) ? $entry['label'] : '' ) );
			}
		}
		$found = array();
		// "Quote" — Name, or "Quote", says Name.
		if ( preg_match_all( '/["\x{201C}]([^"\x{201D}]{12,400})["\x{201D}]\s*(?:[\x{2014}\x{2013}-]\s*|,?\s*(?:says|said|writes|wrote)\s+)([A-Z][\w.\'-]+)/u', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$words  = self::words_of( $match[1] );
				$backed = false;
				foreach ( $allowed as $source ) {
					if ( count( $words ) > 0 && count( array_intersect( $words, $source ) ) / count( $words ) >= 0.6 ) {
						$backed = true;
						break;
					}
				}
				if ( ! $backed ) {
					$found[] = '"' . self::short( $match[1] ) . '" — ' . $match[2];
				}
			}
		}
		return $found;
	}

	/**
	 * Engagement bait phrases.
	 *
	 * @param string $text Plain text.
	 * @return string[]
	 */
	private static function engagement_bait( $text ) {
		$pattern = '/\b(comment\s+["\x{201C}]?(?:yes|no|below|if|with|\d+|[A-Z]{2,})\b|tag (?:a|your|\d+) (?:friends?|buddy|mates?|someone)|share (?:this )?if you|like (?:this|if)\b(?: post)?(?: if)?|type ["\x{201C}]?\w+["\x{201D}]? (?:below|in the comments)|vote (?:with|by) (?:a |an )?(?:like|reaction|comment)|react with|smash (?:the|that) like|double[- ]tap if|follow for more|drop an? (?:emoji|\S) (?:below|if))/iu';
		return preg_match_all( $pattern, $text, $matches ) ? $matches[0] : array();
	}

	/**
	 * Money amounts in a text.
	 *
	 * @param string $text Text.
	 * @return array[] text, value.
	 */
	public static function money( $text ) {
		$out = array();
		if ( preg_match_all( '/(?:[$\x{20AC}\x{00A3}\x{00A5}]|\b(?:usd|eur|gbp|aud|cad|inr|bdt|tk)\b\.?)\s?(\d[\d,.\s]*\d|\d)|(\d[\d,.]*\d|\d)\s?(?:[\x{20AC}\x{00A3}$]|\b(?:usd|eur|gbp|euros?|dollars?|pounds?|taka)\b)/iu', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$number = '' !== $match[1] ? $match[1] : ( isset( $match[2] ) ? $match[2] : '' );
				$value  = self::amount( $number );
				if ( $value > 0 ) {
					$out[] = array(
						'text'  => trim( $match[0] ),
						'value' => $value,
					);
				}
			}
		}
		return $out;
	}

	/**
	 * A written amount as a number (1,299.00 or 1.299,00 or 49).
	 *
	 * @param string $number Number text.
	 * @return float
	 */
	public static function amount( $number ) {
		$number = preg_replace( '/[^\d.,]/', '', (string) $number );
		if ( '' === $number ) {
			return 0.0;
		}
		// The last separator followed by exactly two digits is the decimal mark.
		if ( preg_match( '/^(.*)[.,](\d{2})$/', $number, $m ) ) {
			return (float) ( preg_replace( '/[.,]/', '', $m[1] ) . '.' . $m[2] );
		}
		return (float) preg_replace( '/[.,]/', '', $number );
	}

	/**
	 * Numbers found in a text (normalised), for backing claims.
	 *
	 * @param string $text Text.
	 * @return float[]
	 */
	private static function numbers( $text ) {
		$out = array();
		if ( preg_match_all( '/\d[\d,.]*\s*(?:k|m|thousand|million)?/i', $text, $matches ) ) {
			foreach ( $matches[0] as $raw ) {
				$out[] = self::count_value( $raw );
			}
		}
		return array_unique( $out );
	}

	/**
	 * A count as written ("1,200", "1.2k", "3 million", "500+").
	 *
	 * @param string $raw Text.
	 * @return float
	 */
	private static function count_value( $raw ) {
		$raw   = strtolower( trim( (string) $raw ) );
		$scale = 1;
		if ( preg_match( '/(k|thousand)$/', $raw ) ) {
			$scale = 1000;
		} elseif ( preg_match( '/(m|million)$/', $raw ) ) {
			$scale = 1000000;
		}
		$digits = preg_replace( '/[^\d.,]/', '', $raw );
		if ( 1 !== $scale ) {
			return (float) str_replace( ',', '.', $digits ) * $scale;
		}
		if ( preg_match( '/^\d+[.,]\d$/', $digits ) ) {
			return (float) str_replace( ',', '.', $digits );
		}
		return (float) preg_replace( '/[.,]/', '', $digits );
	}

	/**
	 * Whether a number is backed by the kit or facts (or rounds down from one:
	 * "over 1,000" is backed by 1,240).
	 *
	 * @param string  $raw     Number as written.
	 * @param float[] $numbers Backing numbers.
	 * @return bool
	 */
	private static function number_backed( $raw, array $numbers ) {
		$value = self::count_value( $raw );
		foreach ( $numbers as $number ) {
			if ( abs( $number - $value ) < 0.001 ) {
				return true;
			}
			// A rounded-down claim ("1,000+" from 1,240) is fine, a rounded-up one is not.
			if ( $value >= 10 && $number >= $value && $number < $value * 1.5 && 0.0 === fmod( $value, $value >= 1000 ? 100 : 10 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Years in business backed by the kit's founding year.
	 *
	 * @param int   $value Years or a year.
	 * @param array $kit   Kit entries.
	 * @return bool
	 */
	private static function years_backed( $value, array $kit ) {
		foreach ( $kit as $entry ) {
			if ( isset( $entry['type'] ) && 'years_in_business' === $entry['type'] && ! empty( $entry['value'] ) ) {
				$founded = (int) $entry['value'];
				$years   = (int) gmdate( 'Y' ) - $founded;
				if ( $value === $founded || ( $value <= $years && $value >= $years - 1 ) || ( $value < $years && 0 === $value % 5 ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Kit entries as one text.
	 *
	 * @param array $kit Entries.
	 * @return string
	 */
	private static function kit_text( array $kit ) {
		$out = array();
		foreach ( $kit as $entry ) {
			if ( is_array( $entry ) ) {
				$out[] = implode( ' ', array( isset( $entry['label'] ) ? $entry['label'] : '', isset( $entry['value'] ) ? $entry['value'] : '', isset( $entry['body'] ) ? $entry['body'] : '', isset( $entry['source'] ) ? $entry['source'] : '' ) );
			}
		}
		return implode( "\n", $out );
	}

	/**
	 * Lowercase words (3+ letters) of a text.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private static function words_of( $text ) {
		preg_match_all( '/[\p{L}\p{N}]{3,}/u', function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text ), $matches );
		return array_values( array_unique( $matches[0] ) );
	}

	/**
	 * Plain text from HTML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function plain( $text ) {
		$text = preg_replace( '/<(script|style)\b[\s\S]*?<\/\1>/i', ' ', $text );
		$text = html_entity_decode( wp_strip_all_tags( str_replace( array( '<br', '</p>', '</li>', '</h' ), array( "\n<br", "</p>\n", "</li>\n", "\n</h" ), (string) $text ) ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/[ \t]+/', ' ', $text ) );
	}

	/**
	 * A short excerpt.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function short( $text ) {
		$text = trim( (string) $text );
		return strlen( $text ) > 60 ? substr( $text, 0, 57 ) . '...' : $text;
	}

	/**
	 * A column from a list of arrays.
	 *
	 * @param array  $rows Rows.
	 * @param string $key  Key.
	 * @return array
	 */
	private static function column( array $rows, $key ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row[ $key ] ) ) {
				$out[] = $row[ $key ];
			}
		}
		return $out;
	}
}
