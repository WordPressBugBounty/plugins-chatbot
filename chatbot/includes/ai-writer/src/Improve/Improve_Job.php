<?php
/**
 * "Improve existing content": a job that reads a post, decides per section
 * what would help (clearer, shorter, updated, or nothing), writes proposals,
 * and suggests a FAQ, a summary and better meta. The owner accepts or
 * rejects each proposal; nothing changes in the post until then.
 *
 * Steps: improve_analyse → improve_section × sections that need work →
 * improve_extras.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Improve;

use QuantumCloud\AiWriter\Editor\Editor_Actions;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Seo\Seo_Adapter;
use QuantumCloud\AiWriter\Voice\Voices;
use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\AiWriter\Writing\Writer_AI;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Improve job orchestration and step handlers.
 */
final class Improve_Job {

	const TYPE = 'improve';

	/**
	 * Changes the AI may propose for a section.
	 *
	 * @var string[]
	 */
	const CHANGES = array( 'clearer', 'shorter', 'updated', 'reangle' );

	/**
	 * Step handlers.
	 *
	 * @return array<string,callable>
	 */
	public static function handlers() {
		return array(
			'improve_analyse' => array( __CLASS__, 'analyse' ),
			'improve_section' => array( __CLASS__, 'section' ),
			'improve_extras'  => array( __CLASS__, 'extras' ),
		);
	}

	/**
	 * Start improving a post.
	 *
	 * @param int    $post_id    Post.
	 * @param string $fix        What the owner (or another module) wants fixed.
	 * @param array  $persuasion Re-angle choices (round 3; empty for none).
	 * @return int|WP_Error Job id.
	 */
	public static function start( $post_id, $fix = '', array $persuasion = array() ) {
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_edit', __( 'You are not allowed to edit this content.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$parts = Content_Units::extract( $post->post_content );
		if ( array() === $parts['units'] ) {
			return new WP_Error( 'qcld_wpbot_writer_nothing_to_improve', __( 'This content has no text AI Writer can improve.', 'chatbot' ), array( 'status' => 422 ) );
		}
		$voice  = Voices::site_voice();
		$job_id = Job_Store::create(
			Module::ID,
			self::TYPE,
			array(
				'title'       => get_the_title( $post ),
				'object_type' => $post->post_type,
				'object_id'   => $post->ID,
				'payload'     => array(
					'post_id'  => (int) $post->ID,
					'fix'      => Prompts::cut( sanitize_textarea_field( $fix ), 1000 ),
					'hash'     => md5( $post->post_content ),
					'title'    => $post->post_title,
					'sections' => $parts['sections'],
					'units'    => $parts['units'],
					'voice'    => $voice ? $voice['profile'] : array(),
					// Round 3: re-angle with another reader stage or technique (only when the owner chose).
					'reangle'  => self::reangle( $post, $persuasion ),
				),
			),
			array(
				array(
					'kind'  => 'improve_analyse',
					'label' => __( 'Reading the content', 'chatbot' ),
				),
			)
		);
		if ( ! $job_id ) {
			return new WP_Error( 'qcld_wpbot_writer_job_failed', __( 'The job could not be created.', 'chatbot' ), array( 'status' => 500 ) );
		}
		return $job_id;
	}

	/**
	 * Re-angle choices for a job (empty unless the owner chose them).
	 *
	 * @param \WP_Post $post       Post.
	 * @param array    $persuasion Raw choices.
	 * @return array
	 */
	private static function reangle( $post, array $persuasion ) {
		if ( array() === $persuasion || ! \QuantumCloud\AiWriter\Persuasion\Persuasion::available() ) {
			return array();
		}
		$choices = \QuantumCloud\AiWriter\Persuasion\Choices::sanitise( $persuasion );
		if ( empty( $choices['owner'] ) ) {
			return array();
		}
		$type = 'product' === $post->post_type ? 'product' : ( 'page' === $post->post_type ? 'page_copy' : 'blog_post' );
		return \QuantumCloud\AiWriter\Persuasion\Choices::resolve( $choices, $type );
	}

	/**
	 * Step: decide what each section needs.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function analyse( array $step, array $job ) {
		$payload = $job['payload'];
		$text    = array();
		foreach ( $payload['sections'] as $section ) {
			$text[] = sprintf( 'Section %d: %s', $section['index'], '' !== $section['heading'] ? $section['heading'] : '(introduction)' ) . "\n" . Prompts::clip( self::section_text( $payload, $section['index'] ), 900 );
		}
		$system = "Task: improve_analyse\nYou review an existing page of a small business website and decide, per section, the one change that would help readers most: \"clearer\" (hard to follow), \"shorter\" (padded or repetitive), \"updated\" (likely out of date: old years, prices, \"this year\", outdated advice), or \"none\". Prefer \"none\" when a section is fine. Also say whether the page would gain from a FAQ, a short summary at the top, and better search meta. Answer with JSON: {\"sections\": [{\"index\": 0, \"change\": \"none\", \"reason\": \"one short sentence\"}], \"faq\": false, \"summary\": false, \"meta\": true, \"meta_reason\": \"\"}.\n" . Prompts::language();
		if ( ! empty( $payload['reangle'] ) ) {
			$system .= "\nThe owner wants the page re-angled: \"reangle\" is also allowed (rewrite the section for a new reader stage and technique). Use \"reangle\" for every section whose angle should change.";
		}
		$prompt = 'Title: ' . $payload['title'] . "\nToday: " . wp_date( 'F Y' ) . ( '' !== $payload['fix'] ? "\nThe owner asks for: " . $payload['fix'] : '' ) . "\n\n" . implode( "\n\n", $text );

		$result = Writer_AI::ask_json(
			'improve_analyse',
			$system,
			$prompt,
			array(
				'max_tokens' => 900,
				'job_id'     => $job['id'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return self::retryable( $result );
		}
		$plan = array();
		foreach ( isset( $result['data']['sections'] ) ? (array) $result['data']['sections'] : array() as $entry ) {
			$index  = isset( $entry['index'] ) ? (int) $entry['index'] : -1;
			$change = isset( $entry['change'] ) ? sanitize_key( $entry['change'] ) : 'none';
			if ( 'reangle' === $change && empty( $payload['reangle'] ) ) {
				$change = 'clearer';
			}
			if ( isset( $payload['sections'][ $index ] ) && in_array( $change, self::CHANGES, true ) && self::rewritable( $payload, $index ) ) {
				$plan[ $index ] = array(
					'change' => $change,
					'reason' => sanitize_text_field( isset( $entry['reason'] ) ? (string) $entry['reason'] : '' ),
				);
			}
		}
		$extras = array(
			'faq'         => ! empty( $result['data']['faq'] ),
			'summary'     => ! empty( $result['data']['summary'] ),
			'meta'        => ! empty( $result['data']['meta'] ),
			'meta_reason' => sanitize_text_field( isset( $result['data']['meta_reason'] ) ? (string) $result['data']['meta_reason'] : '' ),
		);

		$steps = array();
		foreach ( $plan as $index => $entry ) {
			$heading = $payload['sections'][ $index ]['heading'];
			$steps[] = array(
				'kind'  => 'improve_section',
				/* translators: %s: section heading. */
				'label' => sprintf( __( 'Proposing changes: %s', 'chatbot' ), '' !== $heading ? $heading : __( 'introduction', 'chatbot' ) ),
				'input' => array(
					'index'  => $index,
					'change' => $entry['change'],
					'reason' => $entry['reason'],
				),
			);
		}
		$steps[] = array(
			'kind'  => 'improve_extras',
			'label' => __( 'Suggesting a FAQ, summary and search meta', 'chatbot' ),
		);
		Job_Store::add_steps( $job['id'], $steps );
		Job_Store::merge_payload( $job['id'], array( 'extras_plan' => $extras ) );

		return array(
			'plan'   => $plan,
			'extras' => $extras,
			'_usage' => Writer_AI::usage( $result['response'] ),
		);
	}

	/**
	 * Step: proposals for one section's text units.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function section( array $step, array $job ) {
		$payload = $job['payload'];
		$index   = (int) $step['input']['index'];
		$change  = in_array( $step['input']['change'], self::CHANGES, true ) ? $step['input']['change'] : 'clearer';
		$units   = self::section_units( $payload, $index, true );
		if ( array() === $units ) {
			return array(
				'index'     => $index,
				'proposals' => array(),
			);
		}
		$asks = array(
			'clearer' => 'Make each text clearer: plain words, one idea per sentence, the point first.',
			'shorter' => 'Make each text shorter: remove padding and repetition, keep every fact.',
			'reangle' => 'Re-angle each text for the reader stage and techniques below. Keep the facts; change the angle, the opening and the call to action as the plan says.',
			'updated' => 'Bring each text up to date for today. You do not know current facts: where a date, price or claim is probably outdated, rewrite around it and add a short placeholder in square brackets for the owner to fill, for example [check the 2026 price]. Never invent new facts.',
		);
		$list = '';
		foreach ( array_values( $units ) as $n => $unit ) {
			$list .= ( $n + 1 ) . '. ' . $unit['html'] . "\n";
		}
		$system = "Task: improve_section\n" . $asks[ $change ] . ' Keep each text\'s meaning, every link (<a href> tags exactly), every shortcode in square brackets exactly, every number and every name. Rewrite only texts that need it; skip the rest. Headings stay headings (short). Inline HTML allowed: <strong>, <em>, <a href>. Answer with JSON: {"units": [{"n": 1, "html": "..."}]}.' . "\n" . Prompts::voice( isset( $payload['voice'] ) ? $payload['voice'] : array() ) . "\n" . Prompts::writing_rules() . "\n" . Prompts::language();
		// CR-14: this section's proposals written again with the same or changed options.
		if ( ! empty( $payload['regen'] ) && is_array( $payload['regen'] ) ) {
			$system .= "\n" . \QuantumCloud\AiWriter\Regenerate\Options::prompt( $payload['regen'] );
		}
		$prompt = 'Page title: ' . $payload['title'] . ( '' !== $payload['fix'] ? "\nThe owner asks for: " . $payload['fix'] : '' ) . "\nWhy this section: " . $step['input']['reason'] . "\n\nTexts:\n" . $list;
		if ( 'reangle' === $change && ! empty( $payload['reangle'] ) ) {
			$prompt .= "\n\n" . \QuantumCloud\AiWriter\Persuasion\Context::prompt( $payload['reangle'], $payload['title'], self::product_id( $payload ) );
		}

		$result = Writer_AI::ask_json(
			'improve_section',
			$system,
			$prompt,
			array(
				'mode'       => 'creative',
				'max_tokens' => 2400,
				'job_id'     => $job['id'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return self::retryable( $result );
		}
		$ordered   = array_values( $units );
		$proposals = array();
		foreach ( isset( $result['data']['units'] ) ? (array) $result['data']['units'] : array() as $entry ) {
			$n = isset( $entry['n'] ) ? (int) $entry['n'] - 1 : -1;
			if ( ! isset( $ordered[ $n ] ) ) {
				continue;
			}
			$unit = $ordered[ $n ];
			$html = Content_Units::clean( isset( $entry['html'] ) ? (string) $entry['html'] : '' );
			if ( 'heading' === $unit['type'] ) {
				$html = wp_strip_all_tags( $html );
			}
			if ( trim( wp_strip_all_tags( $html ) ) === trim( wp_strip_all_tags( $unit['html'] ) ) ) {
				continue;
			}
			$problem     = Content_Units::unsafe( $unit['html'], $html );
			$proposals[] = array(
				'id'      => $index . '-' . $unit['id'],
				'unit'    => $unit['id'],
				'change'  => $change,
				'before'  => $unit['html'],
				'after'   => $html,
				'blocked' => $problem,
			);
		}
		return array(
			'index'     => $index,
			'change'    => $change,
			'reason'    => $step['input']['reason'],
			'proposals' => $proposals,
			'_usage'    => Writer_AI::usage( $result['response'] ),
		);
	}

	/**
	 * Step: FAQ, summary and meta proposals (only what the analysis asked for).
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function extras( array $step, array $job ) {
		$payload = $job['payload'];
		$plan    = isset( $payload['extras_plan'] ) ? (array) $payload['extras_plan'] : array();
		$post    = get_post( (int) $payload['post_id'] );
		if ( ! $post ) {
			return new WP_Error( 'qcld_wpbot_writer_post_gone', __( 'The content was deleted.', 'chatbot' ) );
		}
		$ctx             = Editor_Actions::context( $post->ID, $post->post_title, $post->post_content );
		$ctx['voice']    = isset( $payload['voice'] ) ? (array) $payload['voice'] : array();
		$ctx['cache']    = false;
		$block_content   = has_blocks( $post->post_content ) || ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post ) && '' === trim( $post->post_content ) );
		$out             = array(
			'faq'     => null,
			'summary' => null,
			'meta'    => null,
		);
		$errors          = array();
		$has_faq         = (bool) preg_match( '/frequently asked|>\s*faq\s*</i', $post->post_content );
		$plan['faq']     = ! empty( $plan['faq'] ) && ! $has_faq;
		$plan['summary'] = ! empty( $plan['summary'] ) && false === stripos( $post->post_content, 'In short:' );

		foreach ( array( 'faq', 'summary' ) as $kind ) {
			if ( empty( $plan[ $kind ] ) ) {
				continue;
			}
			$result = 'faq' === $kind ? Editor_Actions::faq( $ctx ) : Editor_Actions::summary( $ctx );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				continue;
			}
			$out[ $kind ] = array(
				'id'      => $kind,
				'content' => $block_content ? $result['content'] : $result['html'],
				'html'    => $result['html'],
			);
		}
		$current = Seo_Adapter::read( $post->ID );
		if ( ! empty( $plan['meta'] ) || '' === $current['title'] || '' === $current['description'] ) {
			$meta = Editor_Actions::meta( $ctx );
			if ( is_wp_error( $meta ) ) {
				$errors[] = $meta->get_error_message();
			} else {
				$out['meta'] = array(
					'id'          => 'meta',
					'reason'      => isset( $plan['meta_reason'] ) ? $plan['meta_reason'] : '',
					'before'      => array(
						'title'       => $current['title'],
						'description' => $current['description'],
					),
					'title'       => $meta['meta_title'],
					'description' => $meta['meta_description'],
				);
			}
		}
		// Extras are optional: report problems, never fail the job for them.
		$out['errors'] = $errors;
		return $out;
	}

	/**
	 * The review view: sections with their proposals, extras and status.
	 *
	 * @param int $job_id Job id.
	 * @return array|null
	 */
	public static function review( $job_id ) {
		$job = Job_Store::get( $job_id );
		if ( ! $job || self::TYPE !== $job['type'] ) {
			return null;
		}
		$payload  = $job['payload'];
		$sections = array();
		$extras   = null;
		foreach ( Job_Store::steps( $job_id ) as $step ) {
			if ( 'done' !== $step['status'] || ! is_array( $step['output'] ) ) {
				continue;
			}
			if ( 'improve_section' === $step['kind'] && isset( $payload['regenerated'][ (int) $step['output']['index'] ] ) ) {
				// CR-14: the section's proposals were written again.
				$step['output'] = $payload['regenerated'][ (int) $step['output']['index'] ];
			}
			if ( 'improve_section' === $step['kind'] && ! empty( $step['output']['proposals'] ) ) {
				$index      = (int) $step['output']['index'];
				$sections[] = array(
					'index'     => $index,
					'heading'   => isset( $payload['sections'][ $index ] ) ? $payload['sections'][ $index ]['heading'] : '',
					'change'    => $step['output']['change'],
					'reason'    => $step['output']['reason'],
					'proposals' => $step['output']['proposals'],
				);
			}
			if ( 'improve_extras' === $step['kind'] ) {
				$extras = $step['output'];
			}
		}
		$post = get_post( (int) $payload['post_id'] );
		return array(
			'post_id'  => (int) $payload['post_id'],
			'title'    => $post ? get_the_title( $post ) : '',
			'status'   => $post ? $post->post_status : '',
			'live'     => $post ? self::is_live( $post ) : false,
			'changed'  => $post ? md5( $post->post_content ) !== $payload['hash'] : true,
			'sections' => $sections,
			'extras'   => $extras,
			'applied'  => isset( $payload['applied'] ) ? $payload['applied'] : null,
		);
	}

	/**
	 * The product being improved (0 when the page is not a product).
	 *
	 * @param array $payload Job payload.
	 * @return int
	 */
	private static function product_id( array $payload ) {
		$post_id = (int) $payload['post_id'];
		return 'product' === get_post_type( $post_id ) ? $post_id : 0;
	}

	/**
	 * CR-14: write one section's proposals again (same or changed options).
	 * The new proposals replace the old ones in the review.
	 *
	 * @param int   $job_id  Job.
	 * @param int   $index   Section index.
	 * @param array $options Clean regenerate options.
	 * @return array|WP_Error The section's review entry.
	 */
	public static function regenerate_section( $job_id, $index, array $options ) {
		$job = Job_Store::get( $job_id );
		if ( ! $job || self::TYPE !== $job['type'] ) {
			return new WP_Error( 'qcld_wpbot_writer_not_found', __( 'That improvement was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$found = null;
		foreach ( Job_Store::steps( $job_id ) as $step ) {
			if ( 'improve_section' === $step['kind'] && (int) $step['input']['index'] === (int) $index ) {
				$found = $step;
			}
		}
		if ( ! $found ) {
			return new WP_Error( 'qcld_wpbot_writer_no_section', __( 'That section was not part of this improvement.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$job['payload']['regen'] = $options;
		$output                  = self::section( $found, $job );
		if ( is_wp_error( $output ) ) {
			return $output;
		}
		unset( $output['_usage'] );
		$regenerated           = isset( $job['payload']['regenerated'] ) ? (array) $job['payload']['regenerated'] : array();
		$regenerated[ $index ] = $output;
		Job_Store::merge_payload( $job_id, array( 'regenerated' => $regenerated ) );
		foreach ( self::review( $job_id )['sections'] as $section ) {
			if ( (int) $section['index'] === (int) $index ) {
				return $section;
			}
		}
		return array(
			'index'     => (int) $index,
			'proposals' => array(),
		);
	}

	/**
	 * Apply the accepted proposals. Drafts change directly (with a revision);
	 * live content gets an update copy the owner reviews and publishes.
	 *
	 * @param int      $job_id   Job id.
	 * @param string[] $accepted Proposal ids (and "faq", "summary", "meta").
	 * @param array    $edits    The owner's own wording (CR-7): proposal id => text,
	 *                            summary/faq => block markup, meta => title, description.
	 * @return array|WP_Error
	 */
	public static function apply( $job_id, array $accepted, array $edits = array() ) {
		$job    = Job_Store::get( $job_id );
		$review = self::review( $job_id );
		if ( ! $job || ! $review ) {
			return new WP_Error( 'qcld_wpbot_writer_no_job', __( 'This job no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! empty( $job['payload']['applied'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_applied', __( 'These changes were already applied.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$post = get_post( $review['post_id'] );
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_edit', __( 'You are not allowed to edit this content.', 'chatbot' ), array( 'status' => 403 ) );
		}
		if ( $review['changed'] ) {
			return new WP_Error( 'qcld_wpbot_writer_changed', __( 'The content was edited after AI Writer read it. Start again so nothing you wrote is lost.', 'chatbot' ), array( 'status' => 409 ) );
		}

		$accepted = array_map( 'strval', $accepted );
		$changes  = array();
		foreach ( $review['sections'] as $section ) {
			foreach ( $section['proposals'] as $proposal ) {
				if ( in_array( $proposal['id'], $accepted, true ) && '' === $proposal['blocked'] ) {
					$changes[ $proposal['unit'] ] = $proposal['after'];
					// The owner edited the proposal: their wording, cleaned the same way.
					if ( isset( $edits[ $proposal['id'] ] ) && is_string( $edits[ $proposal['id'] ] ) && '' !== trim( $edits[ $proposal['id'] ] ) ) {
						$typed                        = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $edits[ $proposal['id'] ] );
						$own                          = Content_Units::clean( preg_replace( '/\s*\n\s*/', ' ', trim( $typed ) ) );
						$changes[ $proposal['unit'] ] = 'heading' === self::unit_type( $job, $proposal['unit'] ) ? wp_strip_all_tags( $own ) : $own;
					}
				}
			}
		}
		$content = Content_Units::splice( $post->post_content, $job['payload']['units'], $changes );
		$extras  = (array) $review['extras'];
		if ( in_array( 'summary', $accepted, true ) && ! empty( $extras['summary'] ) ) {
			$content = self::extra_content( $extras['summary']['content'], $edits, 'summary' ) . "\n\n" . $content;
		}
		if ( in_array( 'faq', $accepted, true ) && ! empty( $extras['faq'] ) ) {
			$content = rtrim( $content ) . "\n\n" . self::extra_content( $extras['faq']['content'], $edits, 'faq' );
		}
		$meta = in_array( 'meta', $accepted, true ) && ! empty( $extras['meta'] ) ? array(
			'title'       => $extras['meta']['title'],
			'description' => $extras['meta']['description'],
		) : null;
		if ( $meta && isset( $edits['meta'] ) && is_array( $edits['meta'] ) ) {
			foreach ( array( 'title', 'description' ) as $field ) {
				if ( isset( $edits['meta'][ $field ] ) && '' !== trim( (string) $edits['meta'][ $field ] ) ) {
					$meta[ $field ] = sanitize_text_field( (string) $edits['meta'][ $field ] );
				}
			}
		}

		if ( $content === $post->post_content && ! $meta ) {
			return new WP_Error( 'qcld_wpbot_writer_nothing_accepted', __( 'Accept at least one change first.', 'chatbot' ), array( 'status' => 400 ) );
		}

		if ( self::is_live( $post ) ) {
			$update_id = Updates::create( $post, $content, $meta );
			if ( is_wp_error( $update_id ) ) {
				return $update_id;
			}
			$result = array(
				'mode'      => 'update',
				'update_id' => $update_id,
				'edit_url'  => (string) get_edit_post_link( $update_id, 'raw' ),
			);
		} else {
			// wp_update_post() unslashes its input: slash it so backslashes survive.
			$saved = wp_update_post(
				wp_slash(
					array(
						'ID'           => $post->ID,
						'post_content' => $content,
					)
				),
				true
			);
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			if ( $meta ) {
				Seo_Adapter::write( $post->ID, $meta['title'], $meta['description'] );
			}
			$result = array(
				'mode'     => 'draft',
				'edit_url' => (string) get_edit_post_link( $post->ID, 'raw' ),
			);
		}
		$result['changes'] = count( $changes );
		Job_Store::merge_payload( $job_id, array( 'applied' => $result ) );
		Decay_Alerts::resolve( $post->ID );
		return $result;
	}

	/**
	 * Whether visitors can see the post now (published, private or scheduled).
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function is_live( $post ) {
		return in_array( $post->post_status, array( 'publish', 'private', 'future' ), true );
	}

	/**
	 * Plain text of a section.
	 *
	 * @param array $payload Payload.
	 * @param int   $index   Section index.
	 * @return string
	 */
	private static function section_text( array $payload, $index ) {
		return implode( ' ', array_map( 'wp_strip_all_tags', wp_list_pluck( self::section_units( $payload, $index, false ), 'html' ) ) );
	}

	/**
	 * Units of a section.
	 *
	 * @param array $payload       Payload.
	 * @param int   $index         Section index.
	 * @param bool  $editable_only Leave out locked units.
	 * @return array[] Keyed by unit id.
	 */
	private static function section_units( array $payload, $index, $editable_only ) {
		$out = array();
		foreach ( $payload['units'] as $unit ) {
			if ( (int) $unit['section'] === (int) $index && ( ! $editable_only || empty( $unit['locked'] ) ) ) {
				$out[ $unit['id'] ] = $unit;
			}
		}
		return $out;
	}

	/**
	 * Whether a section has any unit that may be rewritten.
	 *
	 * @param array $payload Payload.
	 * @param int   $index   Section.
	 * @return bool
	 */
	private static function rewritable( array $payload, $index ) {
		return array() !== self::section_units( $payload, $index, true );
	}

	/**
	 * Mark rate limits and outages as worth retrying.
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

	/**
	 * A summary or FAQ to add: the owner's edited version (block markup made
	 * in the browser, cleaned like post content) or the AI's.
	 *
	 * @param string $content AI version.
	 * @param array  $edits   Edits.
	 * @param string $key     summary or faq.
	 * @return string
	 */
	private static function extra_content( $content, array $edits, $key ) {
		if ( isset( $edits[ $key ] ) && is_string( $edits[ $key ] ) && '' !== trim( wp_strip_all_tags( $edits[ $key ] ) ) ) {
			return wp_kses_post( preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $edits[ $key ] ) );
		}
		return (string) $content;
	}

	/**
	 * The type of a content unit (paragraph, heading, item …) in a job.
	 *
	 * @param array  $job  Job.
	 * @param string $unit Unit id.
	 * @return string
	 */
	private static function unit_type( array $job, $unit ) {
		foreach ( isset( $job['payload']['units'] ) ? (array) $job['payload']['units'] : array() as $entry ) {
			if ( isset( $entry['id'] ) && (string) $entry['id'] === (string) $unit ) {
				return isset( $entry['type'] ) ? (string) $entry['type'] : '';
			}
		}
		return '';
	}
}
