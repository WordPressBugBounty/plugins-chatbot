<?php
/**
 * Step handlers of the article job. Each runs one short AI call (or none),
 * returns its output, and never repeats once done (WPBot Core's job queue).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Formats\Formats;
use QuantumCloud\AiWriter\Persuasion\Choices;
use QuantumCloud\AiWriter\Persuasion\Context;

use QuantumCloud\AiWriter\Checks\Ai_Pattern_Check;
use QuantumCloud\AiWriter\Checks\Fact_Flags;
use QuantumCloud\AiWriter\Images\Image_Filler;
use QuantumCloud\AiWriter\Knowledge\Chatbot_Knowledge;
use QuantumCloud\AiWriter\Knowledge\Documents;
use QuantumCloud\AiWriter\Output\Block_Builder;
use QuantumCloud\AiWriter\Output\Kses_Policy;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Article step handlers.
 */
final class Article_Steps {

	/**
	 * Handlers by step kind.
	 *
	 * @return array<string,callable>
	 */
	public static function handlers() {
		return array(
			'writer_sources'  => array( __CLASS__, 'sources' ),
			'writer_outline'  => array( __CLASS__, 'outline' ),
			'writer_section'  => array( __CLASS__, 'section' ),
			'writer_faq'      => array( __CLASS__, 'faq' ),
			'writer_stitch'   => array( __CLASS__, 'stitch' ),
			'writer_checks'   => array( __CLASS__, 'checks' ),
			'writer_meta'     => array( __CLASS__, 'meta' ),
			'writer_images'   => array( Image_Filler::class, 'step' ),
			'writer_assemble' => array( Assembler::class, 'step' ),
		);
	}

	/**
	 * Gather the sources and summarise them once.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function sources( array $step, array $job ) {
		$brief  = $job['payload']['brief'];
		$parts  = array();
		$used   = array();
		$errors = array();

		foreach ( $brief['sources']['post_ids'] as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || ! user_can( (int) $job['user_id'], 'read_post', $post_id ) ) {
				continue;
			}
			$text = 'Page "' . $post->post_title . '" (' . get_permalink( $post ) . "):\n" . Prompts::clip( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 3000 );

			/**
			 * Change the text a source post gives the writer (WooCommerce adds product facts).
			 *
			 * @param string   $text Source text.
			 * @param \WP_Post $post Source post.
			 */
			$parts[] = (string) apply_filters( 'qcld_wpbot_writer/source_text', $text, $post );
			$used[]  = array(
				'type'  => 'post',
				'id'    => (int) $post_id,
				'title' => $post->post_title,
				'url'   => get_permalink( $post ),
			);
		}
		foreach ( $brief['sources']['document_ids'] as $document_id ) {
			$document = Documents::get( $document_id );
			if ( ! $document ) {
				continue;
			}
			$parts[] = 'Document "' . $document['title'] . "\":\n" . Prompts::clip( (string) $document['text'], 12000 );
			$used[]  = array(
				'type'  => 'document',
				'id'    => (int) $document_id,
				'title' => $document['title'],
			);
		}
		if ( '' !== $brief['sources']['notes'] ) {
			$parts[] = "Owner's notes:\n" . $brief['sources']['notes'];
			$used[]  = array(
				'type'  => 'notes',
				'title' => __( 'Your notes', 'chatbot' ),
			);
		}
		if ( $brief['sources']['chatbot'] ) {
			$knowledge = Chatbot_Knowledge::search( $brief['topic'], 6 );
			if ( is_wp_error( $knowledge ) ) {
				$errors[] = $knowledge->get_error_message();
			} elseif ( '' !== trim( $knowledge ) ) {
				$parts[] = "ChatBot knowledge:\n" . Prompts::clip( $knowledge, 6000 );
				$used[]  = array(
					'type'  => 'chatbot',
					'title' => __( 'ChatBot knowledge', 'chatbot' ),
				);
			}
		}

		$digest = implode( "\n\n", $parts );
		$usage  = array();
		// Summarise once when the sources are long; short sources are used as they are.
		if ( Prompts::word_count( $digest ) > 1500 ) {
			$system = "Task: summarise_sources\nSummarise these sources for a writer. Keep every fact, number, name, price, step and quote that could be useful. Drop repetition and navigation text. Plain text, at most 900 words.";
			$result = Writer_AI::ask(
				'sources',
				$system,
				$digest,
				array(
					'max_tokens' => 1500,
					'job_id'     => $job['id'],
				)
			);
			if ( is_wp_error( $result ) ) {
				return self::retryable( $result );
			}
			$digest = trim( $result->text );
			$usage  = Writer_AI::usage( $result );
		}

		Job_Store::merge_payload( $job['id'], array( 'digest' => $digest ) );
		return array(
			'used'   => $used,
			'words'  => Prompts::word_count( $digest ),
			'notes'  => $errors,
			'_usage' => $usage,
		);
	}

	/**
	 * Plan the outline, then pause for the owner's approval.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function outline( array $step, array $job ) {
		$brief  = $job['payload']['brief'];
		$target = Brief::target_words( $brief );
		// CR-14: "Keep the outline, rewrite the sections": the article's own headings, no AI call.
		if ( ! empty( $brief['regenerate'] ) && 'keep_outline' === $brief['regenerate']['mode'] && ! empty( $brief['regenerate']['sections'] ) ) {
			$outline = Outline::sanitise(
				array(
					'titles'   => array( $brief['topic'] ),
					'angle'    => '',
					'sections' => $brief['regenerate']['sections'],
					'faq'      => array(),
					'summary'  => false,
				),
				$target,
				! empty( self::choices( $brief )['none'] )
			);
			Job_Store::merge_payload(
				$job['id'],
				array(
					'outline'  => $outline,
					'awaiting' => 'outline',
				)
			);
			Article_Job::approve_outline( $job['id'], $outline );
			return array( 'sections' => count( $outline['sections'] ) );
		}
		$system = "Task: outline\nYou plan a " . Prompts::type_label( $brief['type'] ) . ' for a small business website. Plan sections with a one-line purpose and a word budget each; budgets add up to about ' . $target . " words. Suggest three title options and the angle (what makes this piece useful and different). Plan a featured image and an image for some sections (a stock photo search query, an AI image prompt and alt text). List 0-4 frequently asked questions worth answering, whether a short summary helps, and facts the owner should add.\n"
			. Prompts::language() . "\n" . Choices::outline_prompt( self::choices( $brief ), $brief['type'] ) . "\nAnswer with one JSON object: {\"titles\":[3 strings],\"angle\":\"\",\"sections\":[{\"heading\":\"\",\"purpose\":\"\",\"words\":0,\"job\":\"\",\"technique\":\"\",\"image\":{\"kind\":\"photo|illustration|diagram\",\"query\":\"\",\"prompt\":\"\",\"alt\":\"\"} or null}],\"faq\":[\"question\"],\"summary\":true,\"featured_image\":{\"query\":\"\",\"prompt\":\"\",\"alt\":\"\"},\"missing_facts\":[\"\"]}";
		$prompt = Brief::describe( $brief ) . "\n\n" . Prompts::facts( Brief::facts( $brief ) )
			. self::persuasion( $brief )
			. ( ! empty( $job['payload']['digest'] ) ? "\n\nSources:\n" . Prompts::clip( $job['payload']['digest'], 6000 ) : '' );

		$result = Writer_AI::ask_json(
			'outline',
			$system,
			$prompt,
			array(
				'max_tokens' => 1600,
				'job_id'     => $job['id'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return self::retryable( $result );
		}
		$outline = Outline::sanitise( $result['data'], $target, ! empty( self::choices( $brief )['none'] ) );
		// Questions from the brief (SEO & AI Search, or typed) lead the FAQ; the AI's own follow.
		$asked = isset( $job['payload']['brief']['questions'] ) ? (array) $job['payload']['brief']['questions'] : array();
		if ( array() !== $asked ) {
			$outline['faq'] = array_slice( array_values( array_unique( array_merge( $asked, $outline['faq'] ) ) ), 0, 10 );
		}
		if ( array() === $outline['sections'] ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_outline', __( 'The outline came back empty. Trying again usually helps.', 'chatbot' ), array( 'retryable' => true ) );
		}

		Job_Store::merge_payload(
			$job['id'],
			array(
				'outline'  => $outline,
				'awaiting' => 'outline',
			)
		);
		if ( ! empty( $job['payload']['brief']['auto_outline'] ) ) {
			// Bulk writing: the outline is used as planned and writing goes on.
			Article_Job::approve_outline( $job['id'], $outline );
		} else {
			Job_Store::pause( $job['id'] );
		}
		return array(
			'sections' => count( $outline['sections'] ),
			'_usage'   => Writer_AI::usage( $result['response'] ),
		);
	}

	/**
	 * Write one section.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function section( array $step, array $job ) {
		$brief   = $job['payload']['brief'];
		$outline = $job['payload']['outline'];
		$index   = isset( $step['input']['index'] ) ? (int) $step['input']['index'] : 0;
		if ( ! isset( $outline['sections'][ $index ] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_section', __( 'This section is no longer in the outline.', 'chatbot' ) );
		}
		$section  = $outline['sections'][ $index ];
		$previous = self::previous_summary( $job['id'], $index );

		$plan = array();
		foreach ( $outline['sections'] as $i => $item ) {
			$plan[] = ( $i + 1 ) . '. ' . $item['heading'] . ( $i === $index ? '  <- write this one' : '' );
		}

		$system = "Task: section\nYou write one section of a " . Prompts::type_label( $brief['type'] ) . ' for a small business website. Write about ' . $section['words'] . " words (stay within 15%). Do not repeat the section heading. Do not write an introduction to the whole piece unless this is section 1, and no conclusion unless it is the last section.\n"
			. Prompts::voice( isset( $job['payload']['voice'] ) ? $job['payload']['voice'] : array() ) . "\n" . Prompts::writing_rules() . "\n" . Prompts::language()
			. "\nAnswer with one JSON object: {\"blocks\":[{\"type\":\"paragraph\",\"text\":\"\"},{\"type\":\"heading\",\"text\":\"\"},{\"type\":\"list\",\"ordered\":false,\"items\":[\"\"]},{\"type\":\"quote\",\"text\":\"\"}"
			// CR-16: blocks the format adds (a table, a checklist).
			. ( '' !== Formats::block_shapes( isset( $brief['format'] ) ? $brief['format'] : '' ) ? ',' . Formats::block_shapes( $brief['format'] ) : '' )
			. '],"summary":"one sentence on what this section said"}. Text may use <strong>, <em> and <a href>.';
		$prompt = Brief::describe( $brief ) . "\nTitle: " . $outline['title'] . "\nAngle: " . $outline['angle']
			. "\n\nOutline:\n" . implode( "\n", $plan )
			. "\n\nThis section: " . $section['heading'] . "\nPurpose: " . $section['purpose']
			. ( '' !== Choices::section_prompt( $section ) ? "\n" . Choices::section_prompt( $section ) : '' )
			. ( '' !== $previous ? "\nThe previous section said: " . $previous : '' )
			. "\n\n" . Prompts::facts( Brief::facts( $brief ) )
			. self::persuasion( $brief )
			. ( ! empty( $job['payload']['digest'] ) ? "\n\nSources:\n" . Prompts::clip( $job['payload']['digest'], 5000 ) : '' );

		$result = Writer_AI::ask_json(
			'section',
			$system,
			$prompt,
			array(
				'mode'       => 'creative',
				'max_tokens' => (int) max( 600, $section['words'] * 2.4 ),
				'job_id'     => $job['id'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return self::retryable( $result );
		}
		$blocks = Block_Builder::normalise( isset( $result['data']['blocks'] ) ? $result['data']['blocks'] : array() );
		if ( array() === $blocks ) {
			return new WP_Error( 'qcld_wpbot_writer_empty_section', __( 'The section came back empty. Trying again usually helps.', 'chatbot' ), array( 'retryable' => true ) );
		}
		$blocks = self::trim_to_budget( $blocks, $section['words'] );

		return array(
			'index'   => $index,
			'heading' => $section['heading'],
			'blocks'  => $blocks,
			'words'   => self::words( $blocks ),
			'summary' => Kses_Policy::plain( isset( $result['data']['summary'] ) ? $result['data']['summary'] : '' ),
			'_usage'  => Writer_AI::usage( $result['response'] ),
		);
	}

	/**
	 * The piece's persuasion choices (older jobs: resolved now).
	 *
	 * @param array $brief Brief.
	 * @return array
	 */
	private static function choices( array $brief ) {
		return Choices::resolve( isset( $brief['persuasion'] ) ? $brief['persuasion'] : array(), $brief['type'], array( 'intent' => isset( $brief['intent'] ) ? $brief['intent'] : '' ) );
	}

	/**
	 * Persuasion block for a prompt: the plan, the kit, customer words ('' when nothing).
	 *
	 * @param array $brief Brief.
	 * @return string
	 */
	private static function persuasion( array $brief ) {
		$text = Context::prompt( self::choices( $brief ), '' !== (string) $brief['main_query'] ? $brief['main_query'] : $brief['topic'] );
		// CR-14: a regenerated article carries the owner's changes.
		if ( ! empty( $brief['regenerate']['options'] ) ) {
			$text .= ( '' !== $text ? "\n\n" : '' ) . \QuantumCloud\AiWriter\Regenerate\Options::prompt( $brief['regenerate']['options'] );
		}
		return '' !== $text ? "\n\n" . $text : '';
	}

	/**
	 * Answer the planned questions.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function faq( array $step, array $job ) {
		$brief   = $job['payload']['brief'];
		$outline = $job['payload']['outline'];
		$system  = "Task: faq\nAnswer each question in 40-70 words, directly, for the business's website. Use only the facts and sources given; where a fact is missing, say what the reader should check instead of inventing it.\n"
			. Prompts::voice( isset( $job['payload']['voice'] ) ? $job['payload']['voice'] : array() ) . "\n" . Prompts::language()
			. "\nAnswer with one JSON object: {\"items\":[{\"q\":\"\",\"a\":\"\"}]}.";
		$prompt  = Brief::describe( $brief ) . "\n\nQuestions:\n- " . implode( "\n- ", $outline['faq'] ) . "\n\n" . Prompts::facts( Brief::facts( $brief ) )
			. ( ! empty( $job['payload']['digest'] ) ? "\n\nSources:\n" . Prompts::clip( $job['payload']['digest'], 4000 ) : '' );

		$result = Writer_AI::ask_json(
			'faq',
			$system,
			$prompt,
			array(
				'max_tokens' => 1200,
				'job_id'     => $job['id'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return self::retryable( $result );
		}
		$items = array();
		foreach ( isset( $result['data']['items'] ) ? (array) $result['data']['items'] : array() as $item ) {
			if ( is_array( $item ) && ! empty( $item['q'] ) && ! empty( $item['a'] ) ) {
				$items[] = array(
					'q' => Kses_Policy::plain( $item['q'] ),
					'a' => Kses_Policy::inline( $item['a'] ),
				);
			}
		}
		return array(
			'items'  => $items,
			'_usage' => Writer_AI::usage( $result['response'] ),
		);
	}

	/**
	 * Smooth the joins between sections and remove repetition there.
	 *
	 * Sends only the last paragraph of each section and the first paragraph
	 * of the next, so the call stays short.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function stitch( array $step, array $job ) {
		$sections = self::section_outputs( $job['id'] );
		$joins    = array();
		$count    = count( $sections );
		for ( $i = 0; $i < $count - 1; $i++ ) {
			$last  = self::last_paragraph( $sections[ $i ]['blocks'] );
			$first = self::first_paragraph( $sections[ $i + 1 ]['blocks'] );
			if ( null !== $last && null !== $first ) {
				$joins[] = array(
					'join'  => $i,
					'last'  => $sections[ $i ]['blocks'][ $last ]['text'],
					'first' => $sections[ $i + 1 ]['blocks'][ $first ]['text'],
				);
			}
		}
		if ( array() === $joins ) {
			return array( 'joins' => array() );
		}

		$system = "Task: stitch\nYou edit the joins between sections of one article. For each join you get the last paragraph of a section and the first paragraph of the next. Make them flow, remove repeated points and repeated openings, keep facts, links and meaning. Do not add new claims.\n" . Prompts::language()
			. "\nAnswer with one JSON object: {\"joins\":[{\"join\":0,\"last\":\"\",\"first\":\"\"}]}.";
		$result = Writer_AI::ask_json(
			'stitch',
			$system,
			wp_json_encode( $joins ),
			array(
				'max_tokens' => 400 + 250 * count( $joins ),
				'job_id'     => $job['id'],
			)
		);
		if ( is_wp_error( $result ) ) {
			// Stitching is polish: keep the draft as written rather than fail the job.
			return array(
				'joins' => array(),
				'note'  => $result->get_error_message(),
			);
		}
		$out = array();
		foreach ( isset( $result['data']['joins'] ) ? (array) $result['data']['joins'] : array() as $join ) {
			if ( isset( $join['join'], $join['last'], $join['first'] ) ) {
				$out[] = array(
					'join'  => (int) $join['join'],
					'last'  => Kses_Policy::inline( $join['last'] ),
					'first' => Kses_Policy::inline( $join['first'] ),
				);
			}
		}
		return array(
			'joins'  => $out,
			'_usage' => Writer_AI::usage( $result['response'] ),
		);
	}

	/**
	 * Rule-based checks: AI patterns and claims to verify. No AI cost.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array
	 */
	public static function checks( array $step, array $job ) {
		$brief      = $job['payload']['brief'];
		$sections   = Assembler::stitched_sections( $job['id'] );
		$paragraphs = array();
		$lists      = 0;
		$headings   = count( $sections );
		foreach ( $sections as $section ) {
			foreach ( $section['blocks'] as $block ) {
				if ( 'list' === $block['type'] ) {
					++$lists;
					$paragraphs[] = wp_strip_all_tags( implode( '. ', $block['items'] ) );
				} elseif ( 'table' === $block['type'] ) {
					$paragraphs[] = wp_strip_all_tags( self::block_text( $block ) );
				} elseif ( 'heading' === $block['type'] ) {
					++$headings;
				} else {
					$paragraphs[] = wp_strip_all_tags( $block['text'] );
				}
			}
		}
		$cliches  = (array) \QuantumCloud\AiWriter\Settings::get( 'cliches' );
		$patterns = Ai_Pattern_Check::analyse(
			$paragraphs,
			array(
				'lists'    => $lists,
				'headings' => $headings,
				'words'    => Prompts::word_count( implode( ' ', $paragraphs ) ),
			),
			$cliches
		);
		$evidence = implode( "\n", Brief::facts( $brief ) ) . "\n" . $brief['sources']['notes'] . "\n" . ( isset( $job['payload']['digest'] ) ? $job['payload']['digest'] : '' ) . "\n" . $brief['topic'];
		$claims   = Fact_Flags::find( $paragraphs, $evidence );

		$placeholders = 0;
		foreach ( $paragraphs as $paragraph ) {
			$placeholders += preg_match_all( '/\[[^\]]{3,80}\]/u', $paragraph );
		}
		// CR-16: the format's own checks (a table where one is needed, numbers only from facts…).
		$format = ! empty( $brief['format'] ) ? \QuantumCloud\AiWriter\Formats\Format_Checks::run(
			$brief['format'],
			$sections,
			isset( $job['payload']['outline']['title'] ) ? (string) $job['payload']['outline']['title'] : '',
			$evidence . "\n" . ( isset( $job['payload']['research_text'] ) ? (string) $job['payload']['research_text'] : '' )
		) : array();
		return array(
			'ai_patterns'  => $patterns['flags'],
			'score'        => $patterns['score'],
			'claims'       => $claims,
			'placeholders' => $placeholders,
			'format'       => $format,
		);
	}

	/**
	 * One metadata call: title, meta title and description, excerpt, tags,
	 * summary and key points.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function meta( array $step, array $job ) {
		$brief    = $job['payload']['brief'];
		$outline  = $job['payload']['outline'];
		$sections = Assembler::stitched_sections( $job['id'] );
		$text     = '';
		foreach ( $sections as $section ) {
			$text .= $section['heading'] . "\n" . wp_strip_all_tags( implode( "\n", array_map( array( __CLASS__, 'block_text' ), $section['blocks'] ) ) ) . "\n\n";
		}
		$system = "Task: meta\nWrite metadata for this piece. Answer with one JSON object: {\"title\":\"best title, max 65 characters\",\"meta_title\":\"max 60 characters\",\"meta_description\":\"max 155 characters\",\"excerpt\":\"1-2 sentences\",\"tags\":[\"up to 5\"],\"summary\":\"2-3 sentences\",\"key_points\":[\"3-5 short points\"]}. Use only what the piece says.\n" . Prompts::language();
		$prompt = 'Title options: ' . implode( ' | ', array_merge( array( $outline['title'] ), $outline['titles'] ) ) . "\n\n" . Prompts::clip( $text, 9000 );
		$result = Writer_AI::ask_json(
			'meta',
			$system,
			$prompt,
			array(
				'max_tokens' => 700,
				'job_id'     => $job['id'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return self::retryable( $result );
		}
		$data = $result['data'];
		return array(
			'title'            => Kses_Policy::plain( ! empty( $data['title'] ) ? $data['title'] : $outline['title'] ),
			'meta_title'       => substr( Kses_Policy::plain( isset( $data['meta_title'] ) ? $data['meta_title'] : '' ), 0, 70 ),
			'meta_description' => substr( Kses_Policy::plain( isset( $data['meta_description'] ) ? $data['meta_description'] : '' ), 0, 170 ),
			'excerpt'          => Kses_Policy::plain( isset( $data['excerpt'] ) ? $data['excerpt'] : '' ),
			'tags'             => array_slice( array_values( array_filter( array_map( array( Kses_Policy::class, 'plain' ), isset( $data['tags'] ) ? (array) $data['tags'] : array() ) ) ), 0, 5 ),
			'summary'          => Kses_Policy::plain( isset( $data['summary'] ) ? $data['summary'] : '' ),
			'key_points'       => array_slice( array_values( array_filter( array_map( array( Kses_Policy::class, 'plain' ), isset( $data['key_points'] ) ? (array) $data['key_points'] : array() ) ) ), 0, 6 ),
			'_usage'           => Writer_AI::usage( $result['response'] ),
			'brief_type'       => $brief['type'],
		);
	}

	/**
	 * Section step outputs, in order.
	 *
	 * @param int $job_id Job id.
	 * @return array[]
	 */
	public static function section_outputs( $job_id ) {
		$sections = array();
		foreach ( Job_Store::steps( $job_id ) as $step ) {
			if ( 'writer_section' === $step['kind'] && 'done' === $step['status'] && is_array( $step['output'] ) ) {
				$sections[ (int) $step['output']['index'] ] = $step['output'];
			}
		}
		ksort( $sections );
		return array_values( $sections );
	}

	/**
	 * Output of the first done step of a kind.
	 *
	 * @param int    $job_id Job id.
	 * @param string $kind   Step kind.
	 * @return array|null
	 */
	public static function output_of( $job_id, $kind ) {
		foreach ( Job_Store::steps( $job_id ) as $step ) {
			if ( $kind === $step['kind'] && 'done' === $step['status'] ) {
				return is_array( $step['output'] ) ? $step['output'] : array();
			}
		}
		return null;
	}

	/**
	 * Plain text of a block.
	 *
	 * @param array $block Block.
	 * @return string
	 */
	public static function block_text( array $block ) {
		if ( 'table' === $block['type'] ) {
			$cells = isset( $block['head'] ) ? (array) $block['head'] : array();
			foreach ( isset( $block['rows'] ) ? (array) $block['rows'] : array() as $row ) {
				$cells = array_merge( $cells, (array) $row );
			}
			return implode( '; ', $cells );
		}
		return 'list' === $block['type'] ? implode( '; ', $block['items'] ) : ( isset( $block['text'] ) ? $block['text'] : '' );
	}

	/**
	 * Word count of blocks.
	 *
	 * @param array[] $blocks Blocks.
	 * @return int
	 */
	public static function words( array $blocks ) {
		return Prompts::word_count( implode( ' ', array_map( array( __CLASS__, 'block_text' ), $blocks ) ) );
	}

	/**
	 * Drop trailing blocks while a section runs far over its budget, keeping
	 * at least two blocks. Prompts and token ceilings keep this rare.
	 *
	 * @param array[] $blocks Blocks.
	 * @param int     $budget Word budget.
	 * @return array[]
	 */
	private static function trim_to_budget( array $blocks, $budget ) {
		$remaining = count( $blocks );
		while ( $remaining > 2 && self::words( $blocks ) > $budget * 1.3 ) {
			array_pop( $blocks );
			--$remaining;
		}
		return $blocks;
	}

	/**
	 * Summary written by the previous section.
	 *
	 * @param int $job_id Job id.
	 * @param int $index  Current index.
	 * @return string
	 */
	private static function previous_summary( $job_id, $index ) {
		foreach ( self::section_outputs( $job_id ) as $output ) {
			if ( (int) $output['index'] === $index - 1 ) {
				return (string) $output['summary'];
			}
		}
		return '';
	}

	/**
	 * Index of the first paragraph block.
	 *
	 * @param array[] $blocks Blocks.
	 * @return int|null
	 */
	public static function first_paragraph( array $blocks ) {
		foreach ( $blocks as $i => $block ) {
			if ( 'paragraph' === $block['type'] ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Index of the last paragraph block.
	 *
	 * @param array[] $blocks Blocks.
	 * @return int|null
	 */
	public static function last_paragraph( array $blocks ) {
		for ( $i = count( $blocks ) - 1; $i >= 0; $i-- ) {
			if ( 'paragraph' === $blocks[ $i ]['type'] ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Mark AI errors as retryable when trying again may help.
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	private static function retryable( WP_Error $error ) {
		$data = (array) $error->get_error_data();
		if ( ! isset( $data['retryable'] ) ) {
			$data['retryable'] = isset( $data['cause'] ) && in_array( $data['cause'], array( 'rate_limit', 'provider_down', 'network' ), true );
		}
		return new WP_Error( $error->get_error_code(), $error->get_error_message(), $data );
	}
}
