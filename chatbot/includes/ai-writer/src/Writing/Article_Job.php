<?php
/**
 * The "Write something new" job: questions, cost estimate, start, outline
 * approval. The steps themselves are in Article_Steps.
 *
 * Flow: start → writer_sources → writer_outline (job pauses, awaiting the
 * owner) → approve_outline() adds one writer_section step per section plus
 * writer_faq, writer_stitch, writer_checks, writer_meta, writer_images and
 * writer_assemble → resume.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Voice\Voices;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Article job orchestration.
 */
final class Article_Job {

	const TYPE = 'article';

	/**
	 * Two to four questions that make the piece the owner's own.
	 *
	 * @param array $brief Sanitised brief.
	 * @return string[]|WP_Error
	 */
	public static function questions( array $brief ) {
		$system = "Task: questions\nYou help a small business owner add real knowledge to a piece before it is written. Ask 2 to 4 short questions specific to the topic: a real example from their work, their price or process, what people get wrong, their opinion. No generic questions. Answer with one JSON object: {\"questions\": [\"...\"]}.\n" . Prompts::language();
		$result = Writer_AI::ask_json( 'questions', $system, Brief::describe( $brief ), array( 'max_tokens' => 400 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$questions = isset( $result['data']['questions'] ) ? (array) $result['data']['questions'] : array();
		return array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $questions ) ) ) ), 0, 4 );
	}

	/**
	 * Cost estimate before starting.
	 *
	 * @param array $brief Sanitised brief.
	 * @return array{usd: float, calls: int, words: int, model: string}
	 */
	public static function estimate( array $brief ) {
		$words    = Brief::target_words( $brief );
		$sections = max( 2, (int) ceil( $words / 300 ) );
		$calls    = 1 + $sections + 4; // Outline, sections, FAQ, stitch, metadata, sources.
		$in       = 1500 + $sections * 3000 + 3 * 2500;
		$out      = 900 + (int) ( $words * 1.6 ) + 3 * 500;
		$order    = Keys::call_order( Module::ID );
		$provider = isset( $order[0] ) ? $order[0] : '';
		$model    = '';
		$usd      = 0.0;
		if ( '' !== $provider ) {
			$set   = Keys::get_set( Module::ID );
			$model = ! empty( $set['providers'][ $provider ]['model'] ) ? $set['providers'][ $provider ]['model'] : Providers::default_text_model( $provider );
			$info  = Providers::model_info( $provider, $model );
			$usd   = ( $in * $info['price_in'] + $out * $info['price_out'] ) / 1000000;
		}
		return array(
			'usd'   => round( $usd, 3 ),
			'calls' => $calls,
			'words' => $words,
			'model' => $model,
		);
	}

	/**
	 * Create the job.
	 *
	 * @param array $brief Sanitised brief.
	 * @param array $args  budget (US dollars), parent_id (a bulk job).
	 * @return int|WP_Error Job id.
	 */
	public static function start( array $brief, array $args = array() ) {
		if ( '' === $brief['topic'] ) {
			return new WP_Error( 'qcld_wpbot_writer_no_topic', __( 'Write the topic in one sentence first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		/**
		 * The voice an article is written in (Pro: the brief's chosen voice).
		 *
		 * @param array|null $voice Site voice (id, name, profile) or null.
		 * @param array      $brief Sanitised brief.
		 */
		$voice    = apply_filters( 'qcld_wpbot_writer/brief_voice', Voices::site_voice(), $brief );
		$estimate = self::estimate( $brief );
		$job_id   = Job_Store::create(
			Module::ID,
			self::TYPE,
			array(
				'title'         => $brief['topic'],
				'object_type'   => $brief['post_type'],
				'payload'       => array(
					'brief'         => $brief,
					'voice'         => $voice ? $voice['profile'] : array(),
					// CR-16 F3: approved research numbers (the format checks compare the text with them).
					'research_text' => ! empty( $brief['research'] ) ? \QuantumCloud\AiWriter\Formats\Research_Data::text( $brief['research'] ) : '',
				),
				'estimate_cost' => $estimate['usd'],
				'budget'        => isset( $args['budget'] ) ? (float) $args['budget'] : 0,
				'parent_id'     => isset( $args['parent_id'] ) ? absint( $args['parent_id'] ) : 0,
			),
			array(
				array(
					'kind'  => 'writer_sources',
					'label' => __( 'Reading your sources', 'chatbot' ),
				),
				array(
					'kind'  => 'writer_outline',
					'label' => __( 'Planning the outline', 'chatbot' ),
				),
			)
		);
		if ( ! $job_id ) {
			return new WP_Error( 'qcld_wpbot_writer_job_failed', __( 'The writing job could not be created.', 'chatbot' ), array( 'status' => 500 ) );
		}
		return $job_id;
	}

	/**
	 * CR-14: write the outline again (same or changed options) while it waits
	 * for approval. Earlier outlines are kept so the owner can go back.
	 *
	 * @param int   $job_id  Job.
	 * @param array $options Clean regenerate options.
	 * @return array|WP_Error outline, versions (outline and label each).
	 */
	public static function regenerate_outline( $job_id, array $options ) {
		$job = Job_Store::get( $job_id );
		if ( ! $job || self::TYPE !== $job['type'] || 'outline' !== ( isset( $job['payload']['awaiting'] ) ? $job['payload']['awaiting'] : '' ) ) {
			return new WP_Error( 'qcld_wpbot_writer_outline_gone', __( 'The outline can be regenerated only before writing starts.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$versions                              = isset( $job['payload']['outline_versions'] ) ? (array) $job['payload']['outline_versions'] : array(
			array(
				'outline' => $job['payload']['outline'],
				'label'   => __( 'First outline', 'chatbot' ),
			),
		);
		$job['payload']['brief']['regenerate'] = array(
			'mode'     => 'new_outline',
			'options'  => $options,
			'sections' => array(),
		);
		$result                                = Article_Steps::outline( array(), $job );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$fresh      = Job_Store::get( $job_id );
		$versions[] = array(
			'outline' => $fresh['payload']['outline'],
			'label'   => \QuantumCloud\AiWriter\Regenerate\Options::summary( $options ),
		);
		$versions   = array_slice( $versions, -\QuantumCloud\AiWriter\Regenerate\History::keep() );
		Job_Store::merge_payload( $job_id, array( 'outline_versions' => $versions ) );
		return array(
			'outline'  => $fresh['payload']['outline'],
			'versions' => $versions,
		);
	}

	/**
	 * Save the owner's edited outline and queue the writing steps.
	 *
	 * @param int   $job_id  Job id.
	 * @param array $edited  Outline from the screen (title, sections[{heading, purpose, words, image}], faq[], summary).
	 * @return true|WP_Error
	 */
	public static function approve_outline( $job_id, array $edited ) {
		$job = Job_Store::get( $job_id );
		if ( ! $job || self::TYPE !== $job['type'] || empty( $job['payload']['awaiting'] ) || 'outline' !== $job['payload']['awaiting'] ) {
			return new WP_Error( 'qcld_wpbot_writer_not_waiting', __( 'This job is not waiting for an outline.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$outline = Outline::sanitise( $edited, Brief::target_words( $job['payload']['brief'] ), ! empty( $job['payload']['brief']['persuasion']['none'] ) );
		if ( array() === $outline['sections'] ) {
			return new WP_Error( 'qcld_wpbot_writer_empty_outline', __( 'Keep at least one section.', 'chatbot' ), array( 'status' => 400 ) );
		}

		$steps = array();
		foreach ( $outline['sections'] as $index => $section ) {
			$steps[] = array(
				'kind'  => 'writer_section',
				/* translators: 1: section number, 2: section heading. */
				'label' => sprintf( __( 'Writing section %1$d: %2$s', 'chatbot' ), $index + 1, $section['heading'] ),
				'input' => array( 'index' => $index ),
			);
		}
		if ( array() !== $outline['faq'] ) {
			$steps[] = array(
				'kind'  => 'writer_faq',
				'label' => __( 'Answering the frequently asked questions', 'chatbot' ),
			);
		}
		$steps[] = array(
			'kind'  => 'writer_stitch',
			'label' => __( 'Smoothing the transitions', 'chatbot' ),
		);
		$steps[] = array(
			'kind'  => 'writer_checks',
			'label' => __( 'Checking for AI-sounding writing and claims to verify', 'chatbot' ),
		);
		$steps[] = array(
			'kind'  => 'writer_meta',
			'label' => __( 'Writing the title, meta description and excerpt', 'chatbot' ),
		);
		$steps[] = array(
			'kind'  => 'writer_images',
			'label' => __( 'Adding images', 'chatbot' ),
		);
		$steps[] = array(
			'kind'  => 'writer_assemble',
			'label' => __( 'Creating the draft in WordPress', 'chatbot' ),
		);

		/**
		 * Steps of an article after its outline (extensions add steps, for
		 * example a deeper humanize pass, before "Creating the draft").
		 *
		 * @param array[] $steps kind, label, input.
		 * @param array   $job   Job.
		 */
		$steps = (array) apply_filters( 'qcld_wpbot_writer/article_steps', $steps, $job );

		Job_Store::merge_payload(
			$job_id,
			array(
				'outline'  => $outline,
				'awaiting' => '',
			)
		);
		Job_Store::add_steps( $job_id, $steps );
		Job_Store::resume( $job_id );
		return true;
	}
}
