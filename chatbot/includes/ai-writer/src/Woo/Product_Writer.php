<?php
/**
 * Product copy from product facts: long description (benefits, use cases,
 * care, specifications built from the data, variations summarised), short
 * description, product FAQ, variation descriptions, and category or tag
 * descriptions. Every result carries claim flags (the facts rule).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Woo;

use QuantumCloud\AiWriter\Output\Block_Builder;
use QuantumCloud\AiWriter\Output\Kses_Policy;
use QuantumCloud\AiWriter\Voice\Voices;
use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\AiWriter\Writing\Writer_AI;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Product writing.
 */
final class Product_Writer {

	/**
	 * The facts rule, sent with every product prompt.
	 */
	const FACTS_RULE = 'Use only the product facts and the owner\'s notes. Do not state guarantees, warranties, compatibility, materials, certifications, performance claims (waterproof, washable and similar), stock, delivery or "best" claims unless they are in the facts. Where such a detail would help, write a short placeholder in square brackets instead, for example [material] or [care instructions].';

	/**
	 * Long description.
	 *
	 * @param int    $product_id Product.
	 * @param string $notes      Owner's notes.
	 * @param array  $args       job_id, cache.
	 * @return array|WP_Error html, flags, unsupported.
	 */
	public static function long( $product_id, $notes = '', array $args = array() ) {
		$args['product_id'] = (int) $product_id;
		$facts              = Product_Facts::get( $product_id );
		if ( ! $facts ) {
			return self::no_product();
		}
		$text   = Product_Facts::text( $facts, $notes );
		$result = self::ask(
			'woo_description',
			'Write the full product description for the product page: an opening paragraph on the main benefit for the buyer; a short list of benefits; who it is for and when to use it; care or use tips only if the facts give them. Do not write a specifications list (it is added from the data). Also give the heading for the specifications in the site language. Answer with JSON: {"blocks": [{"type": "paragraph", "text": "..."}, {"type": "heading", "text": "...", "level": 3}, {"type": "list", "items": ["..."]}], "specs_heading": "Specifications", "options_label": "Available in", "unsupported": ["claims you left out because the facts do not support them"]}.',
			$text,
			$args,
			1800
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$items = Block_Builder::normalise( isset( $result['blocks'] ) ? $result['blocks'] : array() );
		foreach ( $items as $index => $item ) {
			if ( 'heading' === $item['type'] ) {
				$items[ $index ]['level'] = 3;
			}
		}
		$options = self::options_line( $facts, isset( $result['options_label'] ) ? (string) $result['options_label'] : '' );
		if ( $options ) {
			$items[] = $options;
		}
		$specs = self::specs( $facts );
		if ( array() !== $specs ) {
			$items[] = array(
				'type'  => 'heading',
				'text'  => Kses_Policy::plain( ! empty( $result['specs_heading'] ) ? $result['specs_heading'] : __( 'Specifications', 'chatbot' ) ),
				'level' => 3,
			);
			$items[] = array(
				'type'    => 'list',
				'items'   => $specs,
				'ordered' => false,
			);
		}
		if ( array() === $items ) {
			return self::empty_answer();
		}
		$html = Block_Builder::to_html( $items );
		return array(
			'html'        => $html,
			'flags'       => Claim_Check::flags( $html, $text ),
			'honesty'     => self::honesty( $html, $text, $product_id ),
			'unsupported' => self::strings( isset( $result['unsupported'] ) ? $result['unsupported'] : array() ),
		);
	}

	/**
	 * Short description (shown next to the price).
	 *
	 * @param int    $product_id Product.
	 * @param string $notes      Notes.
	 * @param array  $args       job_id, cache.
	 * @return array|WP_Error html, flags.
	 */
	public static function short( $product_id, $notes = '', array $args = array() ) {
		$args['product_id'] = (int) $product_id;
		$facts              = Product_Facts::get( $product_id );
		if ( ! $facts ) {
			return self::no_product();
		}
		$text   = Product_Facts::text( $facts, $notes );
		$result = self::ask(
			'woo_short',
			'Write the short description shown next to the price: one or two sentences on the main benefit, then up to four short bullet points with the key facts. Answer with JSON: {"text": "...", "bullets": ["..."], "unsupported": ["..."]}.',
			$text,
			$args,
			500
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$items = Block_Builder::normalise(
			array(
				array(
					'type' => 'paragraph',
					'text' => isset( $result['text'] ) ? (string) $result['text'] : '',
				),
				array(
					'type'  => 'list',
					'items' => isset( $result['bullets'] ) ? array_slice( (array) $result['bullets'], 0, 4 ) : array(),
				),
			)
		);
		if ( array() === $items ) {
			return self::empty_answer();
		}
		$html = Block_Builder::to_html( $items );
		return array(
			'html'        => $html,
			'flags'       => Claim_Check::flags( $html, $text ),
			'honesty'     => self::honesty( $html, $text, $product_id ),
			'unsupported' => self::strings( isset( $result['unsupported'] ) ? $result['unsupported'] : array() ),
		);
	}

	/**
	 * Product FAQ to add under the description.
	 *
	 * @param int    $product_id Product.
	 * @param string $notes      Notes.
	 * @param array  $args       job_id, cache.
	 * @return array|WP_Error html, flags.
	 */
	public static function faq( $product_id, $notes = '', array $args = array() ) {
		$args['product_id'] = (int) $product_id;
		$facts              = Product_Facts::get( $product_id );
		if ( ! $facts ) {
			return self::no_product();
		}
		$text   = Product_Facts::text( $facts, $notes );
		$result = self::ask(
			'woo_faq',
			'Write 3 to 5 questions shoppers ask about this product, each answered in one to three sentences from the facts only. Skip questions the facts cannot answer. Give the FAQ heading in the site language. Answer with JSON: {"heading": "Frequently asked questions", "items": [{"q": "...", "a": "..."}]}.',
			$text,
			$args,
			1000
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$items = array(
			array(
				'type'  => 'heading',
				'text'  => Kses_Policy::plain( ! empty( $result['heading'] ) ? $result['heading'] : __( 'Frequently asked questions', 'chatbot' ) ),
				'level' => 3,
			),
		);
		foreach ( isset( $result['items'] ) ? (array) $result['items'] : array() as $item ) {
			$q = Kses_Policy::plain( isset( $item['q'] ) ? (string) $item['q'] : '' );
			$a = Kses_Policy::inline( isset( $item['a'] ) ? (string) $item['a'] : '' );
			if ( '' !== $q && '' !== $a ) {
				$items[] = array(
					'type'  => 'heading',
					'text'  => $q,
					'level' => 4,
				);
				$items[] = array(
					'type' => 'paragraph',
					'text' => $a,
				);
			}
		}
		if ( count( $items ) < 3 ) {
			return new WP_Error( 'qcld_wpbot_writer_empty_answer', __( 'No questions could be answered from the product data. Add notes first.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$html = Block_Builder::to_html( $items );
		return array(
			'html'    => $html,
			'flags'   => Claim_Check::flags( $html, $text ),
			'honesty' => self::honesty( $html, $text, $product_id ),
		);
	}

	/**
	 * One short description per variation (optional).
	 *
	 * @param int    $product_id Variable product.
	 * @param string $notes      Notes.
	 * @return array|WP_Error variations: [{id, label, text, flags}].
	 */
	public static function variations( $product_id, $notes = '' ) {
		$facts = Product_Facts::get( $product_id );
		if ( ! $facts ) {
			return self::no_product();
		}
		if ( array() === $facts['variations'] ) {
			return new WP_Error( 'qcld_wpbot_writer_no_variations', __( 'This product has no variations.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$text = Product_Facts::text( $facts, $notes );
		$list = '';
		foreach ( $facts['variations'] as $variation ) {
			$list .= 'ID ' . $variation['id'] . ': ' . $variation['attributes'] . "\n";
		}
		$result = self::ask(
			'woo_variations',
			'Write one sentence for each variation saying what is different about it (only from the facts). Answer with JSON: {"variations": [{"id": 0, "text": "..."}]}.',
			$text . "\n\nVariations:\n" . $list,
			array( 'cache' => false ),
			1200
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$labels = wp_list_pluck( $facts['variations'], 'attributes', 'id' );
		$out    = array();
		foreach ( isset( $result['variations'] ) ? (array) $result['variations'] : array() as $entry ) {
			$id    = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
			$value = Kses_Policy::plain( isset( $entry['text'] ) ? (string) $entry['text'] : '' );
			if ( isset( $labels[ $id ] ) && '' !== $value ) {
				$out[] = array(
					'id'    => $id,
					'label' => $labels[ $id ],
					'text'  => $value,
					'flags' => Claim_Check::flags( $value, $text ),
				);
			}
		}
		return array( 'variations' => $out );
	}

	/**
	 * Description of a product category or tag.
	 *
	 * @param \WP_Term $term Term.
	 * @return array|WP_Error text, flags.
	 */
	public static function term( $term ) {
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Up to 20 products of one term.
					array(
						'taxonomy' => $term->taxonomy,
						'terms'    => (int) $term->term_id,
					),
				),
			)
		);
		$names    = array_map( 'get_the_title', $products );
		$taxonomy = get_taxonomy( $term->taxonomy );
		$parent   = $term->parent ? get_term( $term->parent, $term->taxonomy ) : null;
		$facts    = 'Shop ' . ( $taxonomy ? strtolower( $taxonomy->labels->singular_name ) : 'category' ) . ': ' . $term->name
			. ( $parent && ! is_wp_error( $parent ) ? "\nParent: " . $parent->name : '' )
			. ( $names ? "\nProducts in it: " . implode( ', ', $names ) : "\nNo products yet." )
			. ( '' !== trim( $term->description ) ? "\nCurrent description: " . wp_strip_all_tags( $term->description ) : '' );
		$result   = self::ask(
			'woo_term',
			'Write the description shown at the top of this shop category or tag page: two to four sentences that help a shopper choose, naming the kinds of products in it. Answer with JSON: {"text": "..."}.',
			$facts,
			array( 'cache' => false ),
			500
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$value = Kses_Policy::inline( isset( $result['text'] ) ? (string) $result['text'] : '' );
		if ( '' === trim( wp_strip_all_tags( $value ) ) ) {
			return self::empty_answer();
		}
		return array(
			'text'  => $value,
			'flags' => Claim_Check::flags( $value, $facts ),
		);
	}

	/**
	 * Specifications list built from the data, never from the AI.
	 *
	 * @param array $facts Facts.
	 * @return string[]
	 */
	public static function specs( array $facts ) {
		$specs = array();
		foreach ( $facts['attributes'] as $name => $values ) {
			$specs[] = '<strong>' . esc_html( $name ) . ':</strong> ' . esc_html( implode( ', ', $values ) );
		}
		foreach ( array(
			'weight'     => __( 'Weight', 'chatbot' ),
			'dimensions' => __( 'Dimensions', 'chatbot' ),
			'sku'        => __( 'SKU', 'chatbot' ),
		) as $key => $label ) {
			if ( '' !== $facts[ $key ] && 'N/A' !== $facts[ $key ] ) {
				$value   = 'weight' === $key && function_exists( 'get_option' ) ? $facts[ $key ] . ' ' . get_option( 'woocommerce_weight_unit', '' ) : $facts[ $key ];
				$specs[] = '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( trim( $value ) );
			}
		}
		return $specs;
	}

	/**
	 * "Available in" line for variable products, from the variations.
	 *
	 * @param array  $facts Facts.
	 * @param string $label Label in the site language.
	 * @return array|null Paragraph item.
	 */
	private static function options_line( array $facts, $label ) {
		if ( array() === $facts['variations'] ) {
			return null;
		}
		$options = array_unique( wp_list_pluck( $facts['variations'], 'attributes' ) );
		$label   = '' !== trim( $label ) ? Kses_Policy::plain( $label ) : __( 'Available in', 'chatbot' );
		return array(
			'type' => 'paragraph',
			'text' => '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( implode( '; ', array_slice( $options, 0, 20 ) ) ),
		);
	}

	/**
	 * Persuasion for a product (round 3): the owner's choices for it, else
	 * feature to benefit, the job and worries and real-price framing; the
	 * kit and live store data are the only proof.
	 *
	 * @param array $args product_id, persuasion.
	 * @return string
	 */
	public static function persuasion( array $args ) {
		$product_id = isset( $args['product_id'] ) ? (int) $args['product_id'] : 0;
		if ( ! $product_id || ! \QuantumCloud\AiWriter\Persuasion\Persuasion::available() ) {
			return '';
		}
		$set     = isset( $args['persuasion'] ) ? $args['persuasion'] : get_post_meta( $product_id, \QuantumCloud\AiWriter\Persuasion\Choices::META, true );
		$choices = \QuantumCloud\AiWriter\Persuasion\Choices::resolve( is_array( $set ) ? $set : array(), 'product' );
		if ( ! $choices['none'] && empty( $choices['owner'] ) && array() === $choices['techniques'] ) {
			$choices['techniques'] = array( 'job_worries', 'price_framing' );
		}
		return \QuantumCloud\AiWriter\Persuasion\Context::prompt( $choices, (string) get_the_title( $product_id ), $product_id );
	}

	/**
	 * Honesty flags for product copy (round 3).
	 *
	 * @param string $html       Copy.
	 * @param string $facts      Facts text.
	 * @param int    $product_id Product.
	 * @return array[]
	 */
	public static function honesty( $html, $facts, $product_id ) {
		if ( ! \QuantumCloud\AiWriter\Persuasion\Persuasion::available() ) {
			return array();
		}
		return qcld_wpbot_core_honesty_check( $html, \QuantumCloud\AiWriter\Persuasion\Context::honesty( $facts, (int) $product_id ) );
	}

	/**
	 * Ask with the product rules.
	 *
	 * @param string $task       Task name (feature id too).
	 * @param string $ask        What to write.
	 * @param string $facts      Facts text.
	 * @param array  $args       job_id, cache.
	 * @param int    $max_tokens Max tokens.
	 * @return array|WP_Error Data.
	 */
	private static function ask( $task, $ask, $facts, array $args, $max_tokens ) {
		$voice      = Voices::site_voice();
		$system     = 'Task: ' . $task . "\nYou write product copy for a small online shop. " . $ask . "\n" . self::FACTS_RULE . "\n" . Prompts::language() . "\n" . Prompts::voice( $voice ? $voice['profile'] : array() ) . "\n" . Prompts::writing_rules();
		$persuasion = self::persuasion( $args );
		// CR-14: Regenerate with the same or changed options.
		if ( isset( $args['regenerate'] ) && is_array( $args['regenerate'] ) ) {
			$system .= "\n" . \QuantumCloud\AiWriter\Regenerate\Options::prompt( $args['regenerate'] );
		}
		$result = Writer_AI::ask_json(
			$task,
			$system,
			"Product facts:\n" . $facts . ( '' !== $persuasion ? "\n\n" . $persuasion : '' ),
			array(
				'mode'       => 'creative',
				'max_tokens' => $max_tokens,
				'job_id'     => isset( $args['job_id'] ) ? (int) $args['job_id'] : 0,
				'cache'      => isset( $args['cache'] ) ? (bool) $args['cache'] : true,
			)
		);
		if ( is_wp_error( $result ) ) {
			$data = (array) $result->get_error_data();
			if ( ! isset( $data['retryable'] ) ) {
				$data['retryable'] = isset( $data['cause'] ) && in_array( $data['cause'], array( 'rate_limit', 'provider_down', 'network' ), true );
			}
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), $data );
		}
		$data           = (array) $result['data'];
		$data['_usage'] = Writer_AI::usage( $result['response'] );
		return $data;
	}

	/**
	 * Clean list of strings.
	 *
	 * @param mixed $raw Raw list.
	 * @return string[]
	 */
	private static function strings( $raw ) {
		return array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', is_array( $raw ) ? $raw : array() ) ) ) ), 0, 10 );
	}

	/**
	 * Error for a missing product.
	 *
	 * @return WP_Error
	 */
	private static function no_product() {
		return new WP_Error( 'qcld_wpbot_writer_no_product', __( 'This product could not be found.', 'chatbot' ), array( 'status' => 404 ) );
	}

	/**
	 * Error for an empty answer.
	 *
	 * @return WP_Error
	 */
	private static function empty_answer() {
		return new WP_Error( 'qcld_wpbot_writer_empty_answer', __( 'The AI returned nothing. Try again.', 'chatbot' ), array( 'status' => 502 ) );
	}
}
