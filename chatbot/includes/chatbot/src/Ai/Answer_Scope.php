<?php
/**
 * Answer scope: what the AI may answer, one setting for every AI provider.
 *
 * - Business only: questions about the business, its products and services,
 *   plus greetings and small talk; anything else gets the owner's decline
 *   message, which steers back to the business.
 * - Business first (default, for new and existing sites): the business, plus
 *   general questions in the business's field.
 * - Anything: no limit.
 *
 * In every scope, a question about the business that the knowledge base does
 * not answer gets "not sure" plus a way to reach the business (contact details,
 * live chat), never a guess and never a refusal.
 *
 * The rules are added after the owner's saved system prompt (never instead of
 * it). The business is described from WPBot Core's Business Profile
 * (`qcld_wpbot_core_profile`), then the Business Context summary, then the
 * site title and tagline.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Answer scope rules.
 */
final class Answer_Scope {

	/** Option: business_only, business_first or anything. */
	const OPTION = 'qcld_wpbot_ai_answer_scope';

	/** Option: contact details the AI gives when it is not sure (email, phone, contact page). */
	const CONTACT_OPTION = 'qcld_wpbot_ai_scope_contact';

	/** Option: the message for questions outside the business (Business only). */
	const DECLINE_OPTION = 'qcld_wpbot_ai_scope_decline_text';

	const BUSINESS_ONLY  = 'business_only';
	const BUSINESS_FIRST = 'business_first';
	const ANYTHING       = 'anything';

	/** Default for new and existing sites (owner, 7 Nov 2026). */
	const DEFAULT_SCOPE = self::BUSINESS_FIRST;

	/** First line of the rules: marks a prompt that already has them. */
	const MARKER = '### ANSWER SCOPE';

	/**
	 * The three choices and their labels.
	 *
	 * @return array<string,string>
	 */
	public static function choices() {
		return array(
			self::BUSINESS_ONLY  => __( 'Business only', 'chatbot' ),
			self::BUSINESS_FIRST => __( 'Business first', 'chatbot' ),
			self::ANYTHING       => __( 'Anything', 'chatbot' ),
		);
	}

	/**
	 * The saved scope (the default when none is saved or the value is unknown).
	 *
	 * @return string
	 */
	public static function scope() {
		$scope = (string) get_option( self::OPTION, '' );
		if ( ! array_key_exists( $scope, self::choices() ) ) {
			$scope = self::DEFAULT_SCOPE;
		}
		/**
		 * Answer scope for this reply.
		 *
		 * @param string $scope business_only, business_first or anything.
		 */
		$scope = (string) apply_filters( 'qcld_wpbot_chat/answer_scope', $scope );
		return array_key_exists( $scope, self::choices() ) ? $scope : self::DEFAULT_SCOPE;
	}

	/**
	 * Default decline message, with the business name.
	 *
	 * @return string
	 */
	public static function default_decline() {
		$name = self::business()['name'];
		/* translators: %s: business name. */
		return sprintf( __( 'I can only help with questions about %s. What would you like to know about our products or services?', 'chatbot' ), '' !== $name ? $name : __( 'this website', 'chatbot' ) );
	}

	/**
	 * Decline message for questions outside the business (Business only).
	 *
	 * @return string
	 */
	public static function decline_text() {
		$text = trim( (string) get_option( self::DECLINE_OPTION, '' ) );
		return '' !== $text ? $text : self::default_decline();
	}

	/**
	 * What the AI is told about the business.
	 *
	 * @return array{name:string,about:string,field:string,contact:string}
	 */
	public static function business() {
		$profile = get_option( 'qcld_wpbot_core_profile' );
		$profile = is_array( $profile ) ? $profile : array();
		$pick    = static function ( array $keys ) use ( $profile ) {
			foreach ( $keys as $key ) {
				if ( isset( $profile[ $key ] ) ) {
					$value = $profile[ $key ];
					$value = is_array( $value ) ? implode( ', ', array_filter( $value, 'is_scalar' ) ) : $value;
					$value = trim( wp_strip_all_tags( (string) $value ) );
					if ( '' !== $value ) {
						return $value;
					}
				}
			}
			return '';
		};

		$name  = $pick( array( 'business_name', 'name', 'company', 'brand' ) );
		$about = $pick( array( 'description', 'about', 'summary', 'tagline', 'what_we_do' ) );
		$field = $pick( array( 'industry', 'field', 'category', 'categories', 'niche', 'services', 'offers', 'products' ) );

		if ( '' === $about ) {
			$about = self::context_line( 'Business' );
		}
		if ( '' === $field ) {
			$field = self::context_line( 'Offers' );
		}
		if ( '' === $name ) {
			$name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
		}
		if ( '' === $about ) {
			$about = trim( wp_strip_all_tags( (string) get_bloginfo( 'description' ) ) );
		}

		$contact = array();
		foreach ( array(
			'email'       => array( 'email', 'contact_email', 'support_email' ),
			'phone'       => array( 'phone', 'telephone', 'contact_phone' ),
			'contact_url' => array( 'contact_url', 'contact_page', 'contact' ),
		) as $keys ) {
			$value = $pick( $keys );
			if ( '' !== $value ) {
				$contact[] = $value;
			}
		}

		$business = array(
			'name'    => self::cut( $name, 120 ),
			'about'   => self::cut( $about, 600 ),
			'field'   => self::cut( $field, 300 ),
			'contact' => self::cut( implode( ', ', $contact ), 300 ),
		);
		/**
		 * What the AI is told about the business.
		 *
		 * @param array $business name, about, field, contact.
		 */
		return (array) apply_filters( 'qcld_wpbot_chat/answer_scope_business', $business ) + $business;
	}

	/**
	 * The rules for the saved scope.
	 *
	 * @return string
	 */
	public static function rules() {
		$scope    = self::scope();
		$business = self::business();
		$name     = '' !== $business['name'] ? $business['name'] : 'this website';

		$lines   = array( self::MARKER, 'These rules decide which questions you answer; they take priority over any earlier instruction about that.' );
		$profile = 'You answer for ' . $name . '.';
		if ( '' !== $business['about'] ) {
			$profile .= ' About the business: ' . $business['about'];
		}
		if ( '' !== $business['field'] ) {
			$profile .= ' Field: ' . $business['field'] . '.';
		}
		$lines[] = $profile;

		if ( self::BUSINESS_ONLY === $scope ) {
			$lines[] = '- Answer only questions about ' . $name . ', its products, services, orders, policies and website. Greetings, thanks and small talk are fine.';
			$lines[] = '- General knowledge questions count as other topics, even when they are about the business\'s field.';
			$lines[] = '- For any other topic, do not answer it. Reply with exactly this message: "' . str_replace( '"', "'", self::decline_text() ) . '"';
		} elseif ( self::BUSINESS_FIRST === $scope ) {
			$lines[] = '- Put questions about ' . $name . ', its products and services first.';
			$lines[] = '- You may also answer general questions in the business\'s field' . ( '' !== $business['field'] ? ' (' . $business['field'] . ')' : '' ) . ', briefly, from general knowledge, and relate them to what ' . $name . ' offers when it fits. Do this even if earlier instructions say to answer only from the documentation or knowledge base.';
			$lines[] = '- For topics unrelated to the business and its field, say politely that you are here to help with ' . $name . ' and steer back to how you can help.';
		} else {
			$lines[] = '- Answer questions on any topic, including general knowledge, from what you know. Do this even if earlier instructions say to answer only from the documentation, the knowledge base or this website, or to say you have no information on other topics.';
		}

		/**
		 * How visitors can reach a person (contact details, live chat), told to the AI.
		 *
		 * @param string $contact Contact text ('' when none is known).
		 */
		$contact = trim( (string) get_option( self::CONTACT_OPTION, '' ) );
		$contact = trim( (string) apply_filters( 'qcld_wpbot_chat/answer_scope_contact', '' !== $contact ? self::cut( wp_strip_all_tags( $contact ), 300 ) : $business['contact'] ) );
		$reach   = '' !== $contact ? 'suggest contacting the business: ' . $contact : 'suggest contacting the business through the website';
		/**
		 * Whether visitors can ask for a person in this chat (live chat).
		 *
		 * @param bool $live_chat Live chat is available.
		 */
		if ( apply_filters( 'qcld_wpbot_chat/answer_scope_live_chat', false ) ) {
			$reach .= ', or offer to connect them with a person through live chat';
		}
		$lines[] = '- When a question is about ' . $name . ' (prices, availability, policies, details) and the provided context or knowledge base does not answer it, say you are not sure and ' . $reach . '. Never guess or invent business details, and never refuse such a question.';

		/**
		 * The answer scope rules added to every AI provider's system prompt.
		 *
		 * @param string $rules Rules.
		 * @param string $scope Scope.
		 */
		return (string) apply_filters( 'qcld_wpbot_chat/answer_scope_rules', implode( "\n", $lines ), $scope );
	}

	/**
	 * The owner's system prompt with the scope rules added after it (once).
	 *
	 * @param string $system Saved system prompt (may be empty).
	 * @return string
	 */
	public static function apply( $system ) {
		$system = trim( (string) $system );
		if ( false !== strpos( $system, self::MARKER ) ) {
			return $system;
		}
		$rules = trim( self::rules() );
		if ( '' === $rules ) {
			return $system;
		}
		return '' === $system ? $rules : $system . "\n\n" . $rules;
	}

	/**
	 * One line of the Business Context summary ('' when there is none).
	 *
	 * @param string $label Line label (Business, Offers...).
	 * @return string
	 */
	private static function context_line( $label ) {
		if ( ! class_exists( '\QuantumCloud\WPBotCore\Context\Context' ) ) {
			return '';
		}
		$text = (string) \QuantumCloud\WPBotCore\Context\Context::text();
		foreach ( preg_split( '/\r?\n/', $text ) as $line ) {
			if ( 0 === strpos( $line, $label . ': ' ) ) {
				return trim( substr( $line, strlen( $label ) + 2 ) );
			}
		}
		return '';
	}

	/**
	 * Shorten a text to a length (on a word when possible).
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function cut( $text, $max ) {
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		$short = substr( $text, 0, $max );
		$space = strrpos( $short, ' ' );
		return rtrim( false !== $space && $space > $max / 2 ? substr( $short, 0, $space ) : $short, ' ,.;' ) . '…';
	}
}
