<?php
/**
 * CR-16 F2: the "Real data" step. For a piece written in a format, AI
 * Writer lists the sources that format can draw on, collects facts from
 * the ones the owner picks, and proposes them with their source. The owner
 * ticks what to use; only ticked facts reach the writing prompts, and a
 * fact the format needs but nobody has becomes an [add: …] note.
 *
 * Structured sources (products, Persuasion Kit, Customer words, Site
 * Context) are read directly, with no AI call. Free text (chatbot
 * knowledge, pages of the site, the brief's documents and notes) goes to
 * the AI once, and a fact is kept only when the sentence it quotes is
 * really in that text. WPBot Pro adds its sources through filters.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Formats;

use QuantumCloud\AiWriter\Business\Importer;
use QuantumCloud\AiWriter\Knowledge\Chatbot_Knowledge;
use QuantumCloud\AiWriter\Knowledge\Documents;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Regenerate\Regenerate;
use QuantumCloud\AiWriter\Woo\Woo;
use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\AiWriter\Writing\Writer_AI;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Gather real data for a format.
 */
final class Gather {

	/**
	 * Post meta: the facts the piece was written from.
	 */
	const META = '_qcld_wpbot_writer_facts';

	/**
	 * Most facts proposed at once.
	 */
	const MAX_FACTS = 40;

	/**
	 * Sources read through one AI call.
	 */
	const TEXT_SOURCES = array( 'knowledge', 'pages', 'uploads' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_writer/content_finalized', array( __CLASS__, 'remember' ), 10, 2 );
	}

	/**
	 * Keep the ticked facts with the post (for downloads, Improve and audits).
	 *
	 * @param int   $post_id Post.
	 * @param array $brief   Brief.
	 * @return void
	 */
	public static function remember( $post_id, $brief ) {
		if ( is_array( $brief ) && ! empty( $brief['facts'] ) ) {
			update_post_meta( (int) $post_id, self::META, array_values( (array) $brief['facts'] ) );
		}
	}

	/**
	 * Every source AI Writer knows, with its label and what it gives.
	 *
	 * @return array[] id => label, help, ai.
	 */
	public static function catalogue() {
		return array(
			'products'  => array(
				'label' => __( 'Your products', 'chatbot' ),
				'help'  => __( 'Names, prices, ratings and categories from WooCommerce.', 'chatbot' ),
				'ai'    => false,
			),
			'kit'       => array(
				'label' => __( 'Persuasion Kit', 'chatbot' ),
				'help'  => __( 'Your reviews, results, numbers, guarantees and awards.', 'chatbot' ),
				'ai'    => false,
			),
			'words'     => array(
				'label' => __( 'Customer words', 'chatbot' ),
				'help'  => __( 'Phrases your customers use.', 'chatbot' ),
				'ai'    => false,
			),
			'context'   => array(
				'label' => __( 'Site Context', 'chatbot' ),
				'help'  => __( 'What the business offers, where and for whom.', 'chatbot' ),
				'ai'    => false,
			),
			'knowledge' => array(
				'label' => __( 'Chatbot knowledge', 'chatbot' ),
				'help'  => __( 'What your chatbot knows about this topic.', 'chatbot' ),
				'ai'    => true,
			),
			'pages'     => array(
				'label' => __( 'Pages of your site', 'chatbot' ),
				'help'  => __( 'Your three pages closest to the topic.', 'chatbot' ),
				'ai'    => true,
			),
			'uploads'   => array(
				'label' => __( 'Your documents and notes', 'chatbot' ),
				'help'  => __( 'The documents and notes chosen in the brief.', 'chatbot' ),
				'ai'    => true,
			),
		);
	}

	/**
	 * Sources for a brief's format: the Free ones that have data on this
	 * site, then the ones other modules add (WPBot Pro).
	 *
	 * @param array $brief Sanitised brief.
	 * @return array sources (id, label, help, available, reason, ai, usd, ticked), previews (ids the format lists that nothing offers), estimate.
	 */
	public static function sources( array $brief ) {
		$format = Formats::get( isset( $brief['format'] ) ? $brief['format'] : '' );
		if ( null === $format ) {
			return array(
				'sources'  => array(),
				'previews' => array(),
				'estimate' => self::estimate( array() ),
			);
		}
		$wanted  = array_merge( (array) $format['sources'], array( 'context', 'uploads' ) );
		$sources = array();
		foreach ( self::catalogue() as $id => $source ) {
			if ( ! in_array( $id, $wanted, true ) && ! ( 'words' === $id && in_array( 'kit', $wanted, true ) ) ) {
				continue;
			}
			$reason         = self::unavailable( $id, $brief );
			$sources[ $id ] = array(
				'id'        => $id,
				'label'     => $source['label'],
				'help'      => $source['help'],
				'available' => '' === $reason,
				'reason'    => $reason,
				'ai'        => (bool) $source['ai'],
				'ticked'    => '' === $reason,
				'pro'       => false,
			);
		}
		/**
		 * Sources the "Real data" step offers for a format. WPBot Pro adds web
		 * research, Who ranks, Interview me and research from activity here.
		 * Each source: id, label, help, available, reason, ai, ticked, pro.
		 * Facts come from the qcld_wpbot_writer/gather_source filter.
		 *
		 * @param array[] $sources Sources by id.
		 * @param array   $format  Format recipe.
		 * @param array   $brief   Brief.
		 */
		$sources  = (array) apply_filters( 'qcld_wpbot_writer/format_sources', $sources, $format, $brief );
		$known    = array_keys( self::catalogue() );
		$previews = array();
		foreach ( (array) $format['sources'] as $id ) {
			if ( ! isset( $sources[ $id ] ) && ! in_array( $id, $known, true ) ) {
				$previews[] = (string) $id;
			}
		}
		$ticked = array();
		foreach ( $sources as $id => $source ) {
			if ( ! empty( $source['ticked'] ) ) {
				$ticked[] = $id;
			}
		}
		return array(
			'sources'  => array_values( $sources ),
			'previews' => $previews,
			'estimate' => self::estimate( $ticked, $sources ),
		);
	}

	/**
	 * Why a Free source can't be used here ('' when it can).
	 *
	 * @param string $id    Source id.
	 * @param array  $brief Brief.
	 * @return string
	 */
	private static function unavailable( $id, array $brief ) {
		switch ( $id ) {
			case 'products':
				return Woo::active() ? '' : __( 'WooCommerce is not active.', 'chatbot' );
			case 'kit':
				return function_exists( 'qcld_wpbot_core_persuasion_kit' ) && array() !== self::kit_entries() ? '' : __( 'Your Persuasion Kit is empty. Fill it under Brand & Business.', 'chatbot' );
			case 'words':
				return function_exists( 'qcld_wpbot_core_voice_of_customer' ) && array() !== (array) qcld_wpbot_core_voice_of_customer( array( 'limit' => 1 ) ) ? '' : __( 'No customer words yet.', 'chatbot' );
			case 'context':
				return '' !== self::context_text() ? '' : __( 'Site Context is empty.', 'chatbot' );
			case 'knowledge':
				return Chatbot_Knowledge::available() ? '' : __( 'Chatbot knowledge is not available on this site.', 'chatbot' );
			case 'pages':
				return '';
			case 'uploads':
				return array() !== $brief['sources']['document_ids'] || '' !== trim( (string) $brief['sources']['notes'] ) ? '' : __( 'No documents or notes in the brief.', 'chatbot' );
		}
		return '';
	}

	/**
	 * Cost of gathering: one AI call when a text source is ticked, plus what
	 * other sources declare.
	 *
	 * @param string[] $ids     Ticked source ids.
	 * @param array[]  $sources Sources by id (for declared costs).
	 * @return array usd, usd_one (one AI read), calls, model.
	 */
	public static function estimate( array $ids, array $sources = array() ) {
		$calls = array() !== array_intersect( $ids, self::TEXT_SOURCES ) ? 1 : 0;
		$one   = Regenerate::estimate( 'gather', 1 );
		$usd   = $calls * (float) $one['usd'];
		foreach ( $ids as $id ) {
			if ( isset( $sources[ $id ]['usd'] ) ) {
				$usd += (float) $sources[ $id ]['usd'];
				++$calls;
			}
		}
		return array(
			'usd'     => round( $usd, 4 ),
			'usd_one' => (float) $one['usd'],
			'calls'   => $calls,
			'model'   => $one['model'],
		);
	}

	/**
	 * Collect facts from the ticked sources.
	 *
	 * @param array    $brief Sanitised brief.
	 * @param string[] $ids   Source ids.
	 * @return array facts (id, text, source, label, url, quote), missing (string[]), dropped (int), errors (string[]).
	 */
	public static function collect( array $brief, array $ids ) {
		$ids     = array_values( array_unique( array_map( 'sanitize_key', $ids ) ) );
		$offered = wp_list_pluck( self::sources( $brief )['sources'], 'available', 'id' );
		$facts   = array();
		$missing = array();
		$errors  = array();
		$dropped = 0;
		$texts   = array();
		foreach ( $ids as $id ) {
			if ( empty( $offered[ $id ] ) ) {
				continue;
			}
			switch ( $id ) {
				case 'products':
					$facts = array_merge( $facts, self::from_products( $brief['topic'] ) );
					break;
				case 'kit':
					$facts = array_merge( $facts, self::from_kit() );
					break;
				case 'words':
					$facts = array_merge( $facts, self::from_words( $brief['topic'] ) );
					break;
				case 'context':
					$facts = array_merge( $facts, self::from_context() );
					break;
				case 'knowledge':
				case 'pages':
				case 'uploads':
					$texts = array_merge( $texts, self::texts( $id, $brief ) );
					break;
				default:
					/**
					 * Facts from a source another module added (WPBot Pro).
					 *
					 * @param array[]|WP_Error|null $found Facts (text, label, url, quote) or an error; null when the source is unknown.
					 * @param string                $id    Source id.
					 * @param array                 $brief Brief.
					 */
					$found = apply_filters( 'qcld_wpbot_writer/gather_source', null, $id, $brief );
					if ( is_wp_error( $found ) ) {
						$errors[] = $found->get_error_message();
					} elseif ( is_array( $found ) ) {
						foreach ( $found as $fact ) {
							if ( is_array( $fact ) ) {
								$facts[] = array_merge( $fact, array( 'source' => $id ) );
							}
						}
					}
			}
		}
		if ( array() !== $texts ) {
			$read = self::from_texts( $brief, $texts );
			if ( is_wp_error( $read ) ) {
				$errors[] = $read->get_error_message();
			} else {
				$facts    = array_merge( $facts, $read['facts'] );
				$missing  = $read['missing'];
				$dropped += $read['dropped'];
			}
		}
		return array(
			'facts'   => self::finish( $facts ),
			'missing' => $missing,
			'dropped' => $dropped,
			'errors'  => $errors,
		);
	}

	/**
	 * Sanitise, de-duplicate, cap and give each fact an id.
	 *
	 * @param array[] $facts Raw facts.
	 * @return array[]
	 */
	public static function finish( array $facts ) {
		$out  = array();
		$seen = array();
		foreach ( $facts as $fact ) {
			$clean = self::clean_fact( $fact );
			if ( null === $clean ) {
				continue;
			}
			$key = strtolower( (string) preg_replace( '/\W+/u', '', $clean['text'] ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$clean['id']  = substr( md5( $clean['source'] . '|' . $clean['text'] ), 0, 12 );
			$out[]        = $clean;
			if ( count( $out ) >= self::MAX_FACTS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * One fact, sanitised (null when empty).
	 *
	 * @param mixed $fact Raw fact.
	 * @return array|null text, source, label, url, quote.
	 */
	public static function clean_fact( $fact ) {
		if ( ! is_array( $fact ) ) {
			return null;
		}
		$text = isset( $fact['text'] ) ? trim( sanitize_text_field( (string) $fact['text'] ) ) : '';
		if ( '' === $text ) {
			return null;
		}
		return array(
			'text'   => function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 400 ) : substr( $text, 0, 400 ),
			'source' => isset( $fact['source'] ) ? sanitize_key( (string) $fact['source'] ) : '',
			'label'  => isset( $fact['label'] ) ? substr( sanitize_text_field( (string) $fact['label'] ), 0, 160 ) : '',
			'url'    => isset( $fact['url'] ) ? esc_url_raw( (string) $fact['url'] ) : '',
			'quote'  => ! empty( $fact['quote'] ),
		);
	}

	/**
	 * Facts from WooCommerce products that match the topic (best sellers otherwise).
	 *
	 * @param string $topic Topic.
	 * @return array[]
	 */
	private static function from_products( $topic ) {
		if ( ! Woo::active() || ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$products = array();
		foreach ( self::search_words( $topic ) as $word ) {
			foreach ( (array) wc_get_products(
				array(
					'status' => 'publish',
					'limit'  => 8,
					's'      => $word,
				)
			) as $product ) {
				$products[ $product->get_id() ] = $product;
			}
			if ( count( $products ) >= 8 ) {
				break;
			}
		}
		if ( array() === $products ) {
			foreach ( (array) wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => 8,
					'orderby' => 'popularity',
				)
			) as $product ) {
				$products[ $product->get_id() ] = $product;
			}
		}
		$facts = array();
		foreach ( array_slice( $products, 0, 8 ) as $product ) {
			$parts = array();
			$price = wp_strip_all_tags( html_entity_decode( (string) wc_price( (float) $product->get_price() ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== (string) $product->get_price() ) {
				/* translators: %s: price. */
				$parts[] = sprintf( __( 'price %s', 'chatbot' ), $price );
			}
			if ( (int) $product->get_review_count() > 0 ) {
				/* translators: 1: average rating, 2: number of reviews. */
				$parts[] = sprintf( __( 'rated %1$s out of 5 from %2$d reviews', 'chatbot' ), number_format_i18n( (float) $product->get_average_rating(), 1 ), (int) $product->get_review_count() );
			}
			$short = trim( wp_strip_all_tags( (string) $product->get_short_description() ) );
			if ( '' !== $short ) {
				$parts[] = Prompts::clip( $short, 160 );
			}
			$facts[] = array(
				'text'   => $product->get_name() . ( array() !== $parts ? ': ' . implode( '; ', $parts ) : '' ),
				'source' => 'products',
				'label'  => $product->get_name(),
				'url'    => (string) get_permalink( $product->get_id() ),
			);
		}
		return $facts;
	}

	/**
	 * Persuasion Kit entries in use now.
	 *
	 * @return array[]
	 */
	private static function kit_entries() {
		if ( ! function_exists( 'qcld_wpbot_core_persuasion_kit' ) ) {
			return array();
		}
		$kit = (array) qcld_wpbot_core_persuasion_kit();
		return isset( $kit['entries'] ) ? (array) $kit['entries'] : array();
	}

	/**
	 * Facts from the Persuasion Kit. A review or testimonial is a quote
	 * only when the owner marked it "may be quoted".
	 *
	 * @return array[]
	 */
	private static function from_kit() {
		$facts = array();
		foreach ( array_slice( self::kit_entries(), 0, 20 ) as $entry ) {
			$quote = in_array( $entry['type'], array( 'review', 'testimonial' ), true );
			$body  = trim( (string) $entry['body'] );
			$value = trim( (string) $entry['value'] );
			if ( $quote ) {
				$text = '“' . $body . '”' . ( '' !== $entry['source'] ? ' — ' . $entry['source'] : '' );
			} else {
				$text = trim( $entry['label'] . ( '' !== $value ? ': ' . $value : '' ) . ( '' !== $body && $body !== $entry['label'] ? '. ' . $body : '' ) );
			}
			$facts[] = array(
				'text'   => $text,
				'source' => 'kit',
				'label'  => (string) $entry['type_label'],
				'url'    => (string) $entry['url'],
				'quote'  => $quote && ! empty( $entry['permission'] ),
			);
		}
		return $facts;
	}

	/**
	 * Customer phrases (already redacted by WPBot Core).
	 *
	 * @param string $topic Topic.
	 * @return array[]
	 */
	private static function from_words( $topic ) {
		if ( ! function_exists( 'qcld_wpbot_core_voice_of_customer' ) ) {
			return array();
		}
		$rows = (array) qcld_wpbot_core_voice_of_customer(
			array(
				'search' => implode( ' ', array_slice( self::search_words( $topic ), 0, 1 ) ),
				'limit'  => 8,
			)
		);
		if ( array() === $rows ) {
			$rows = (array) qcld_wpbot_core_voice_of_customer( array( 'limit' => 8 ) );
		}
		$facts = array();
		foreach ( $rows as $row ) {
			$facts[] = array(
				/* translators: %s: a customer phrase. */
				'text'   => sprintf( __( 'Customers say: “%s”', 'chatbot' ), (string) $row['phrase'] ),
				'source' => 'words',
				'label'  => __( 'Customer words', 'chatbot' ),
			);
		}
		return $facts;
	}

	/**
	 * Site Context as text.
	 *
	 * @return string
	 */
	private static function context_text() {
		return function_exists( 'qcld_wpbot_core_context_text' ) ? trim( (string) qcld_wpbot_core_context_text() ) : '';
	}

	/**
	 * One fact per line of the Site Context.
	 *
	 * @return array[]
	 */
	private static function from_context() {
		$facts = array();
		foreach ( preg_split( '/\r\n|\r|\n/', self::context_text() ) as $line ) {
			$line = trim( (string) $line, " \t-•*" );
			if ( strlen( $line ) < 12 ) {
				continue;
			}
			$facts[] = array(
				'text'   => $line,
				'source' => 'context',
				'label'  => __( 'Site Context', 'chatbot' ),
			);
			if ( count( $facts ) >= 12 ) {
				break;
			}
		}
		return $facts;
	}

	/**
	 * Free text of a text source, as labelled parts.
	 *
	 * @param string $id    knowledge, pages or uploads.
	 * @param array  $brief Brief.
	 * @return array[] source, label, url, text.
	 */
	private static function texts( $id, array $brief ) {
		$parts = array();
		if ( 'knowledge' === $id ) {
			$found = Chatbot_Knowledge::search( $brief['topic'], 6 );
			if ( is_string( $found ) && '' !== trim( $found ) ) {
				$parts[] = array(
					'source' => 'knowledge',
					'label'  => __( 'Chatbot knowledge', 'chatbot' ),
					'url'    => '',
					'text'   => Prompts::clip( $found, 12000 ),
				);
			}
		} elseif ( 'pages' === $id ) {
			$query = new \WP_Query(
				array(
					's'                   => $brief['topic'],
					'post_type'           => array( 'post', 'page', 'product' ),
					'post_status'         => 'publish',
					'posts_per_page'      => 3,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);
			$posts = $query->posts;
			if ( array() === $posts ) {
				foreach ( self::search_words( $brief['topic'] ) as $word ) {
					$posts = get_posts(
						array(
							's'              => $word,
							'post_type'      => array( 'post', 'page', 'product' ),
							'post_status'    => 'publish',
							'posts_per_page' => 3,
						)
					);
					if ( array() !== $posts ) {
						break;
					}
				}
			}
			foreach ( $posts as $post ) {
				$read = Importer::read_source(
					array(
						'type' => 'post',
						'id'   => $post->ID,
					)
				);
				if ( ! is_wp_error( $read ) && '' !== trim( $read['text'] ) ) {
					$parts[] = array(
						'source' => 'pages',
						'label'  => $read['label'],
						'url'    => $read['url'],
						'text'   => Prompts::clip( $read['text'], 8000 ),
					);
				}
			}
		} elseif ( 'uploads' === $id ) {
			foreach ( $brief['sources']['document_ids'] as $document_id ) {
				$document = Documents::get( $document_id );
				if ( $document && ( get_current_user_id() === (int) $document['created_by'] || current_user_can( Module::cap( 'settings' ) ) ) ) {
					$parts[] = array(
						'source' => 'uploads',
						'label'  => (string) $document['title'],
						'url'    => '',
						'text'   => Prompts::clip( (string) $document['text'], 12000 ),
					);
				}
			}
			if ( '' !== trim( (string) $brief['sources']['notes'] ) ) {
				$parts[] = array(
					'source' => 'uploads',
					'label'  => __( 'Your notes', 'chatbot' ),
					'url'    => '',
					'text'   => (string) $brief['sources']['notes'],
				);
			}
		}
		return $parts;
	}

	/**
	 * One AI call over the text sources: facts that quote a sentence really
	 * in the text, plus what the format needs that the text does not give.
	 *
	 * @param array   $brief Brief.
	 * @param array[] $parts Labelled texts.
	 * @return array|WP_Error facts, missing, dropped.
	 */
	private static function from_texts( array $brief, array $parts ) {
		$format = Formats::get( $brief['format'] );
		$blocks = array();
		foreach ( $parts as $i => $part ) {
			$blocks[] = '[Source ' . ( $i + 1 ) . ': ' . $part['label'] . "]\n" . $part['text'];
		}
		$system = 'Task: gather facts. You collect facts for an article from the owner\'s own material. '
			. 'Return JSON: {"facts":[{"text":"","source":1,"evidence":""}],"missing":[""]}. '
			. 'Rules: give up to 25 facts useful for the topic and the format: numbers, prices, times, results, steps, features, names, dates, quotes. '
			. '"text" is the fact in one short plain sentence. "source" is the source number. "evidence" is the exact sentence from that source that states the fact, copied word for word. '
			. 'Never add a fact the sources do not state. "missing" lists up to 6 facts this format needs that the sources do not give (short, e.g. "your warranty period").';
		$prompt = 'Topic: ' . $brief['topic'] . "\n"
			. ( null !== $format ? 'Format: ' . $format['label'] . '. ' . $format['outline'] . "\n" : '' )
			. "\n" . implode( "\n\n", $blocks );
		$result = Writer_AI::ask_json(
			'gather',
			$system,
			$prompt,
			array(
				'max_tokens' => 2000,
				'cache'      => false,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$data    = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		$facts   = array();
		$dropped = 0;
		foreach ( array_slice( isset( $data['facts'] ) ? (array) $data['facts'] : array(), 0, 30 ) as $fact ) {
			$index = is_array( $fact ) && isset( $fact['source'] ) ? (int) $fact['source'] - 1 : -1;
			if ( ! isset( $parts[ $index ] ) ) {
				++$dropped;
				continue;
			}
			$evidence = isset( $fact['evidence'] ) ? (string) $fact['evidence'] : '';
			if ( ! Importer::found( $evidence, $parts[ $index ]['text'] ) ) {
				++$dropped;
				continue;
			}
			$facts[] = array(
				'text'   => isset( $fact['text'] ) ? (string) $fact['text'] : '',
				'source' => $parts[ $index ]['source'],
				'label'  => $parts[ $index ]['label'],
				'url'    => $parts[ $index ]['url'],
			);
		}
		$missing = array();
		foreach ( array_slice( isset( $data['missing'] ) ? (array) $data['missing'] : array(), 0, 6 ) as $item ) {
			$item = trim( sanitize_text_field( (string) $item ) );
			if ( '' !== $item ) {
				$missing[] = substr( $item, 0, 120 );
			}
		}
		return array(
			'facts'   => $facts,
			'missing' => $missing,
			'dropped' => $dropped,
		);
	}

	/**
	 * The longest words of a topic, for site searches.
	 *
	 * @param string $topic Topic.
	 * @return string[]
	 */
	private static function search_words( $topic ) {
		$stop  = array( 'about', 'their', 'there', 'which', 'these', 'those', 'with', 'from', 'your', 'what', 'when', 'where', 'best', 'guide', 'checklist', 'versus', 'compared' );
		$words = array_filter(
			preg_split( '/[^\p{L}\p{N}]+/u', strtolower( (string) $topic ) ),
			static function ( $word ) use ( $stop ) {
				return strlen( $word ) >= 4 && ! in_array( $word, $stop, true ) && ! ctype_digit( $word );
			}
		);
		usort(
			$words,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		return array_slice( array_values( array_unique( $words ) ), 0, 3 );
	}
}
