<?php
/**
 * Compare techniques (round 3, CR-13 P3): the same piece of copy written
 * with two to four techniques side by side, each with its label, "why this
 * works", reader-stage fit, length, reading level, the kit proof it used
 * and honesty flags. The owner then uses one, or (Pro) mixes parts.
 *
 * Versions are kept server-side for a day so "use" and "mix" only ever
 * apply text that was generated and checked here.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Persuasion;

defined( 'ABSPATH' ) || exit;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Voice\Voices;
use QuantumCloud\AiWriter\Writing\Prompts;
use QuantumCloud\AiWriter\Writing\Writer_AI;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;
use QuantumCloud\WPBotCore\Persuasion\Stages;
use QuantumCloud\WPBotCore\Persuasion\Techniques;
use WP_Error;

/**
 * Compare techniques service.
 */
final class Compare {

	/** Transient prefix for generated versions (per user). */
	const STORE = 'qcwr_compare_';

	/** Post meta: what the owner chose from comparisons (for learning). */
	const LOG_META = '_qcld_wpbot_writer_compare_log';

	/** Techniques of the headline lab. */
	const LAB = array( 'be_specific', 'curiosity', 'how_to', 'gain', 'loss' );

	/** Part roles of multi-part copy (mixing picks a role from a version). */
	const ROLES = array( 'opening', 'middle', 'close' );

	/**
	 * Kinds of copy that can be compared.
	 *
	 * Each has: label, ask (what to write), chars (soft limit; 0 for none), parts (mixable), type (piece type for fit).
	 *
	 * @return array[]
	 */
	public static function kinds() {
		$kinds = array(
			'headline'            => array(
				'label' => __( 'Headline', 'chatbot' ),
				'ask'   => 'a headline (under 65 characters)',
				'chars' => 70,
				'parts' => false,
				'type'  => 'blog_post',
			),
			'intro'               => array(
				'label' => __( 'Introduction', 'chatbot' ),
				'ask'   => 'an opening paragraph of two to four sentences',
				'chars' => 0,
				'parts' => false,
				'type'  => 'blog_post',
			),
			'section'             => array(
				'label' => __( 'Section', 'chatbot' ),
				'ask'   => 'a rewrite of the given section, keeping every fact, number and quote',
				'chars' => 0,
				'parts' => true,
				'type'  => 'blog_post',
			),
			'cta'                 => array(
				'label' => __( 'Call to action', 'chatbot' ),
				'ask'   => 'a call to action of one or two sentences, without pressure tactics',
				'chars' => 0,
				'parts' => false,
				'type'  => 'page_copy',
			),
			'product_description' => array(
				'label' => __( 'Product description', 'chatbot' ),
				'ask'   => 'a product description of two to four short paragraphs, using only the product facts given',
				'chars' => 0,
				'parts' => true,
				'type'  => 'product',
			),
			'email_subject'       => array(
				'label' => __( 'Email subject line', 'chatbot' ),
				'ask'   => 'an email subject line (under 60 characters)',
				'chars' => 60,
				'parts' => false,
				'type'  => 'email',
			),
			'meta_title'          => array(
				'label' => __( 'Meta title', 'chatbot' ),
				'ask'   => 'a search result title (at most 60 characters)',
				'chars' => 60,
				'parts' => false,
				'type'  => 'blog_post',
			),
			'social_post'         => array(
				'label' => __( 'Social post', 'chatbot' ),
				'ask'   => 'a social media post for the given network',
				'chars' => 0,
				'parts' => true,
				'type'  => 'facebook',
			),
		);
		/**
		 * Kinds of copy Compare techniques offers (add one for a new place copy is written).
		 *
		 * @param array[] $kinds Kinds.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/compare_kinds', $kinds );
	}

	/**
	 * How many versions can be compared at once (Free: 2; Pro raises it to 4).
	 *
	 * @return int 2 to 4.
	 */
	public static function max_versions() {
		/**
		 * Versions per comparison.
		 *
		 * @param int $max Versions (2 to 4).
		 */
		return max( 2, min( 4, (int) apply_filters( 'qcld_wpbot_writer/compare_max', 2 ) ) );
	}

	/**
	 * Whether parts of versions can be mixed (Pro).
	 *
	 * @return bool
	 */
	public static function can_mix() {
		/**
		 * Mixing parts of compared versions ("opening from B, close from A").
		 *
		 * @param bool $mix Allowed.
		 */
		return (bool) apply_filters( 'qcld_wpbot_writer/compare_mix', false );
	}

	/**
	 * Headline lab variations per technique (Free: 2; Pro: more).
	 *
	 * @return int 1 to 5.
	 */
	public static function lab_per() {
		/**
		 * Headline lab variations per technique.
		 *
		 * @param int $per Variations (1 to 5).
		 */
		return max( 1, min( 5, (int) apply_filters( 'qcld_wpbot_writer/headline_lab_per', 2 ) ) );
	}

	/**
	 * Settings for the screens.
	 *
	 * @return array
	 */
	public static function app_data() {
		$kinds = array();
		foreach ( self::kinds() as $id => $kind ) {
			$kinds[ $id ] = array(
				'label' => $kind['label'],
				'parts' => ! empty( $kind['parts'] ),
				'chars' => (int) $kind['chars'],
			);
		}
		$lab = array();
		foreach ( self::LAB as $id ) {
			$view = Choices::view( Techniques::get( $id ) );
			if ( $view ) {
				$lab[] = $view;
			}
		}
		return array(
			'kinds'   => $kinds,
			'max'     => self::max_versions(),
			'mix'     => self::can_mix(),
			'lab'     => $lab,
			'lab_per' => self::lab_per(),
			'roles'   => array(
				'opening' => __( 'Opening', 'chatbot' ),
				'middle'  => __( 'Middle', 'chatbot' ),
				'close'   => __( 'Close', 'chatbot' ),
			),
		);
	}

	/**
	 * Clean a comparison request.
	 *
	 * @param array $raw kind, techniques (ids, or "suggest"), source (text being rewritten), title, content,
	 *                   post_id, product_id, network, stage, lab (bool: headline lab), per.
	 * @return array|WP_Error
	 */
	public static function sanitise( array $raw ) {
		if ( ! Persuasion::available() ) {
			return new WP_Error( 'qcld_wpbot_writer_no_persuasion', sprintf( /* translators: %s: product name, for example WPBot. */ __( 'Compare techniques needs a newer version of %s.', 'chatbot' ), Module::product() ), array( 'status' => 501 ) );
		}
		$kinds = self::kinds();
		$kind  = isset( $raw['kind'] ) ? sanitize_key( (string) $raw['kind'] ) : '';
		if ( ! isset( $kinds[ $kind ] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_kind', __( 'Choose what to compare.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$lab    = 'headline' === $kind && ! empty( $raw['lab'] );
		$stage  = isset( $raw['stage'] ) ? sanitize_key( (string) $raw['stage'] ) : '';
		$out    = array(
			'kind'       => $kind,
			'lab'        => $lab,
			'source'     => isset( $raw['source'] ) ? Prompts::cut( sanitize_textarea_field( (string) $raw['source'] ), 6000 ) : '',
			'title'      => isset( $raw['title'] ) ? Prompts::cut( sanitize_text_field( (string) $raw['title'] ), 300 ) : '',
			'content'    => isset( $raw['content'] ) ? (string) $raw['content'] : '',
			'post_id'    => isset( $raw['post_id'] ) ? absint( $raw['post_id'] ) : 0,
			'product_id' => isset( $raw['product_id'] ) ? absint( $raw['product_id'] ) : 0,
			'network'    => isset( $raw['network'] ) ? sanitize_key( (string) $raw['network'] ) : '',
			'notes'      => isset( $raw['notes'] ) ? Prompts::cut( sanitize_textarea_field( (string) $raw['notes'] ), 2000 ) : '',
			'stage'      => in_array( $stage, Stages::IDS, true ) ? $stage : '',
			'suggested'  => false,
			'techniques' => array(),
			'per'        => 1,
		);
		$wanted = isset( $raw['techniques'] ) ? $raw['techniques'] : array();
		if ( 'suggest' === $wanted || array() === $wanted || null === $wanted ) {
			$out['suggested']  = true;
			$out['techniques'] = $lab ? self::LAB : self::suggest( $out );
		} else {
			foreach ( (array) $wanted as $id ) {
				$id    = sanitize_key( (string) $id );
				$entry = Techniques::get( $id );
				if ( $entry && 'framework' !== $entry['kind'] && ! in_array( $id, $out['techniques'], true ) ) {
					$out['techniques'][] = $id;
				} elseif ( $entry && 'framework' === $entry['kind'] && in_array( $kind, array( 'section', 'product_description', 'social_post', 'intro' ), true ) && ! in_array( $id, $out['techniques'], true ) ) {
					// Frameworks shape longer copy only.
					$out['techniques'][] = $id;
				}
			}
		}
		// Red lines: proof needs the kit, urgency real stock or dates, price framing a real price.
		$out['skipped'] = array();
		foreach ( $out['techniques'] as $i => $id ) {
			$entry = Techniques::get( $id );
			if ( $entry && ! self::needs_met( $entry, $out ) ) {
				$out['skipped'][] = Choices::view( $entry );
				unset( $out['techniques'][ $i ] );
			}
		}
		$out['techniques'] = array_values( $out['techniques'] );
		if ( $lab ) {
			$out['techniques'] = array_values( array_intersect( $out['techniques'], array_merge( self::LAB, array( 'none' ) ) ) );
			$out['per']        = ! empty( $raw['per'] ) ? max( 1, min( self::lab_per(), absint( $raw['per'] ) ) ) : self::lab_per();
		} else {
			$out['techniques'] = array_slice( $out['techniques'], 0, self::max_versions() );
		}
		if ( count( $out['techniques'] ) < ( $lab ? 1 : 2 ) ) {
			$message = array() !== $out['skipped']
				? sprintf(
					/* translators: %s: technique names. */
					__( 'These techniques need real data first (Persuasion Kit entries, a product price, stock or a sale date): %s. Choose others.', 'chatbot' ),
					implode( ', ', wp_list_pluck( $out['skipped'], 'label' ) )
				)
				: __( 'Choose at least two techniques to compare, or "Suggest the best".', 'chatbot' );
			return new WP_Error( 'qcld_wpbot_writer_compare_few', $message, array( 'status' => 400 ) );
		}
		if ( in_array( $kind, array( 'section', 'cta', 'intro' ), true ) && '' === trim( $out['source'] ) && '' === trim( wp_strip_all_tags( $out['content'] ) ) ) {
			return new WP_Error( 'qcld_wpbot_writer_compare_empty', __( 'Select the text to compare first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return $out;
	}

	/**
	 * "Suggest the best": what has won on this site first (P4 learning, via
	 * the filter), then the library's fit for this kind of copy and reader stage.
	 *
	 * @param array $req Sanitised request.
	 * @return string[]
	 */
	public static function suggest( array $req ) {
		$type   = self::piece_type( $req );
		$stage  = '' !== $req['stage'] ? $req['stage'] : self::stage( $req );
		$job    = self::job( $req['kind'] );
		$scored = array();
		foreach ( Techniques::all() as $entry ) {
			if ( 'none' === $entry['kind'] || ( 'framework' === $entry['kind'] && ! in_array( $req['kind'], array( 'section', 'product_description', 'social_post' ), true ) ) ) {
				continue;
			}
			if ( in_array( $type, (array) $entry['avoid']['types'], true ) || in_array( $stage, (array) $entry['avoid']['stages'], true ) ) {
				continue;
			}
			if ( ! self::needs_met( $entry, $req ) ) {
				continue;
			}
			$fits   = (array) $entry['fits'];
			$score  = 0;
			$score += in_array( $type, isset( $fits['types'] ) ? (array) $fits['types'] : array(), true ) ? 2 : 0;
			$score += '' !== $stage && in_array( $stage, isset( $fits['stages'] ) ? (array) $fits['stages'] : array(), true ) ? 2 : 0;
			$score += '' !== $job && in_array( $job, isset( $fits['jobs'] ) ? (array) $fits['jobs'] : array(), true ) ? 3 : 0;
			$score += '' !== $req['network'] && in_array( $req['network'], isset( $fits['channels'] ) ? (array) $fits['channels'] : array(), true ) ? 2 : 0;
			$score += 'headline' === $req['kind'] && in_array( $entry['id'], self::LAB, true ) ? 3 : 0;
			if ( $score > 0 ) {
				$scored[ $entry['id'] ] = $score;
			}
		}
		arsort( $scored );
		$ids = array_keys( $scored );
		/**
		 * Suggested techniques for a comparison, best first (P4 puts this
		 * site's past winners first).
		 *
		 * @param string[] $ids   Technique ids.
		 * @param array    $req   Request (kind, network, post_id, product_id).
		 * @param string   $stage Reader stage.
		 */
		$ids = (array) apply_filters( 'qcld_wpbot_writer/compare_suggest', $ids, $req, $stage );
		$ids = array_values( array_unique( array_filter( array_map( 'sanitize_key', $ids ), array( Techniques::class, 'get' ) ) ) );
		return array_slice( $ids, 0, self::max_versions() );
	}

	/**
	 * Cost estimate before generating.
	 *
	 * @param array $req Sanitised request.
	 * @return array{usd: float, calls: int, versions: int, model: string}
	 */
	public static function estimate( array $req ) {
		$versions = count( $req['techniques'] ) * max( 1, (int) $req['per'] );
		$kind     = self::kinds()[ $req['kind'] ];
		$each     = $kind['chars'] > 0 ? 40 : ( ! empty( $kind['parts'] ) ? 450 : 160 );
		$in       = 2200 + (int) ( strlen( $req['source'] . wp_strip_all_tags( $req['content'] ) ) / 4 ) + 300 * count( $req['techniques'] );
		$out      = 120 + $versions * ( $each + 60 );
		$order    = Keys::call_order( Module::ID );
		$provider = isset( $order[0] ) ? $order[0] : '';
		$model    = '';
		$usd      = 0.0;
		if ( '' !== $provider ) {
			$set   = Keys::get_set( Module::ID );
			$model = ! empty( $set['providers'][ $provider ]['model'] ) ? $set['providers'][ $provider ]['model'] : Providers::default_text_model( $provider );
			$info  = Providers::model_info( $provider, $model );
			$usd   = ( min( $in, 9000 ) * $info['price_in'] + $out * $info['price_out'] ) / 1000000;
		}
		return array(
			'usd'      => round( $usd, 4 ),
			'calls'    => 1,
			'versions' => $versions,
			'model'    => $model,
		);
	}

	/**
	 * Generate the versions (one AI call) and keep them for "use" and "mix".
	 *
	 * @param array $req Sanitised request.
	 * @return array|WP_Error id, kind, versions, mix, stage.
	 */
	public static function generate( array $req ) {
		$kinds = self::kinds();
		$kind  = $kinds[ $req['kind'] ];
		$stage = '' !== $req['stage'] ? $req['stage'] : self::stage( $req );
		$kit   = Context::kit( $req['product_id'] );
		$parts = ! empty( $kind['parts'] ) && ! $req['lab'];
		$per   = max( 1, (int) $req['per'] );

		$lines = array();
		foreach ( $req['techniques'] as $id ) {
			if ( 'none' === $id ) {
				$lines[] = '- none: plain, clear copy in the brand voice with no persuasion technique.';
				continue;
			}
			$entry   = Techniques::get( $id );
			$lines[] = '- ' . $id . ': ' . $entry['label'] . '. ' . $entry['instructions'];
		}
		$stage_entry = '' !== $stage ? Stages::get( $stage ) : null;
		$shape       = $parts
			? '{"versions": [{"technique": "<id>", "parts": {"opening": "...", "middle": "...", "close": "..."}, "proof": ["<kit entry id used>"]}]}'
			: '{"versions": [{"technique": "<id>", "text": "...", "proof": ["<kit entry id used>"]}]}';
		$role        = 'Write ' . $kind['ask'] . ' once per technique below'
			. ( $per > 1 ? ', ' . $per . ' different variations per technique' : '' )
			. '. Each version uses only its own technique. Keep the meaning, facts and brand voice the same across versions so only the technique differs.'
			. ( $parts ? ' Split each version into three parts (opening, middle, close) that read naturally when parts from different versions are combined.' : '' )
			. "\nTechniques:\n" . implode( "\n", $lines )
			. ( $stage_entry ? "\nReader stage: " . $stage_entry['instructions'] : '' )
			. "\n" . Techniques::honesty_rule()
			. "\nList in \"proof\" the ids of the Persuasion Kit entries a version uses (empty when none)."
			. "\nAnswer with JSON: " . $shape . '.';
		$voice       = Voices::site_voice();
		$system      = 'Task: compare_techniques' . "\n" . $role . "\n" . Prompts::language() . "\n" . Prompts::voice( $voice ? (array) $voice['profile'] : array() ) . "\n" . Prompts::writing_rules();
		// CR-14: one version written again with the same or changed options.
		if ( ! empty( $req['regen'] ) && is_array( $req['regen'] ) ) {
			$system .= "\n" . \QuantumCloud\AiWriter\Regenerate\Options::prompt( $req['regen'] );
		}
		$input  = self::describe( $req ) . "\n\n" . self::kit_prompt( $kit );
		$phrase = Context::phrases_prompt( '' !== $req['title'] ? $req['title'] : $req['source'] );
		if ( '' !== $phrase ) {
			$input .= "\n\n" . $phrase;
		}
		$result = Writer_AI::ask_json(
			'compare_' . $req['kind'],
			$system,
			$input,
			array(
				'mode'       => 'creative',
				'max_tokens' => min( 4000, 300 + count( $req['techniques'] ) * $per * ( $parts ? 600 : 200 ) ),
				'cache'      => false,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$honesty  = Context::honesty( self::facts( $req ), $req['product_id'] );
		$versions = array();
		$counts   = array();
		foreach ( isset( $result['data']['versions'] ) ? (array) $result['data']['versions'] : array() as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$tech = isset( $raw['technique'] ) ? sanitize_key( (string) $raw['technique'] ) : '';
			if ( ! in_array( $tech, $req['techniques'], true ) ) {
				continue;
			}
			$counts[ $tech ] = isset( $counts[ $tech ] ) ? $counts[ $tech ] + 1 : 1;
			if ( $counts[ $tech ] > $per ) {
				continue;
			}
			$version_parts = array();
			if ( $parts ) {
				$given = isset( $raw['parts'] ) && is_array( $raw['parts'] ) ? $raw['parts'] : array();
				foreach ( self::ROLES as $role_id ) {
					$version_parts[ $role_id ] = isset( $given[ $role_id ] ) && is_scalar( $given[ $role_id ] ) ? self::clean( (string) $given[ $role_id ] ) : '';
				}
				$text = trim( implode( "\n\n", array_filter( $version_parts ) ) );
			} else {
				$text = isset( $raw['text'] ) && is_scalar( $raw['text'] ) ? self::clean( (string) $raw['text'] ) : '';
			}
			if ( '' === $text ) {
				continue;
			}
			$proof      = self::proof_used( $text, isset( $raw['proof'] ) ? (array) $raw['proof'] : array(), $kit['entries'] );
			$versions[] = self::describe_version( $tech, $text, $version_parts, $proof, $kit['entries'], $stage, $kind, $honesty );
		}
		if ( array() === $versions ) {
			return new WP_Error( 'qcld_wpbot_writer_empty_answer', __( 'The AI returned nothing. Try again.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$id = wp_generate_password( 12, false );
		set_transient(
			self::STORE . get_current_user_id() . '_' . $id,
			array(
				'req'      => $req,
				'stage'    => $stage,
				'versions' => $versions,
			),
			DAY_IN_SECONDS
		);
		$stage_view = $stage_entry ? array(
			'id'    => $stage_entry['id'],
			'label' => $stage_entry['label'],
		) : null;
		return array(
			'id'        => $id,
			'kind'      => $req['kind'],
			'lab'       => $req['lab'],
			'suggested' => $req['suggested'],
			'skipped'   => $req['skipped'],
			'stage'     => $stage_view,
			'versions'  => $versions,
			'mix'       => $parts && self::can_mix(),
		);
	}

	/**
	 * Use one version, or (Pro) mix parts of several. Only text generated
	 * and checked by generate() can be applied.
	 *
	 * @param string $id   Comparison id.
	 * @param array  $pick version (index) to use one; or parts: role => version index to mix.
	 * @return array|WP_Error text, technique(s), honesty, mixed.
	 */
	public static function apply( $id, array $pick ) {
		$id    = preg_replace( '/[^A-Za-z0-9]/', '', (string) $id );
		$saved = '' !== $id ? get_transient( self::STORE . get_current_user_id() . '_' . $id ) : false;
		if ( ! is_array( $saved ) || empty( $saved['versions'] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_compare_gone', __( 'These versions have expired. Compare again.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$versions = $saved['versions'];
		$mixed    = false;
		if ( isset( $pick['parts'] ) && is_array( $pick['parts'] ) && array() !== $pick['parts'] ) {
			if ( ! self::can_mix() ) {
				return new WP_Error( 'qcld_wpbot_writer_compare_no_mix', sprintf( /* translators: %s: paid edition name, for example WPBot Pro. */ __( 'Mixing parts is part of %s.', 'chatbot' ), Module::pro_product() ), array( 'status' => 403 ) );
			}
			$out        = array();
			$techniques = array();
			$used       = array();
			foreach ( self::ROLES as $role ) {
				$index = isset( $pick['parts'][ $role ] ) ? absint( $pick['parts'][ $role ] ) : 0;
				if ( ! isset( $versions[ $index ] ) || empty( $versions[ $index ]['parts'] ) ) {
					return new WP_Error( 'qcld_wpbot_writer_compare_bad_pick', __( 'That part is not available.', 'chatbot' ), array( 'status' => 400 ) );
				}
				if ( '' !== $versions[ $index ]['parts'][ $role ] ) {
					$out[] = $versions[ $index ]['parts'][ $role ];
				}
				$techniques[ $role ] = $versions[ $index ]['technique']['id'];
				$used[]              = $index;
			}
			$text  = implode( "\n\n", $out );
			$mixed = count( array_unique( $used ) ) > 1;
		} else {
			$index = isset( $pick['version'] ) ? absint( $pick['version'] ) : 0;
			if ( ! isset( $versions[ $index ] ) ) {
				return new WP_Error( 'qcld_wpbot_writer_compare_bad_pick', __( 'That version is not available.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$text       = $versions[ $index ]['text'];
			$techniques = array( 'all' => $versions[ $index ]['technique']['id'] );
		}
		$req     = $saved['req'];
		$honesty = Persuasion::available() ? qcld_wpbot_core_honesty_check( $text, Context::honesty( self::facts( $req ), $req['product_id'] ) ) : array();
		if ( $req['post_id'] && current_user_can( 'edit_post', $req['post_id'] ) ) {
			self::log( $req['post_id'], $req['kind'], $techniques, $saved['stage'], $honesty );
		}
		return array(
			'text'       => $text,
			'html'       => wpautop( esc_html( $text ) ),
			'kind'       => $req['kind'],
			'techniques' => $techniques,
			'mixed'      => $mixed,
			'honesty'    => $honesty,
			'compare_id' => $id,
		);
	}

	/**
	 * Write one compared version again (CR-14), keeping its technique, and
	 * put it in place of the old one in the stored comparison.
	 *
	 * @param string $id      Comparison id.
	 * @param int    $index   Version index.
	 * @param array  $options Clean regenerate options.
	 * @return array|WP_Error The new version.
	 */
	public static function regenerate_one( $id, $index, array $options ) {
		$id    = preg_replace( '/[^A-Za-z0-9]/', '', (string) $id );
		$key   = self::STORE . get_current_user_id() . '_' . $id;
		$saved = '' !== $id ? get_transient( $key ) : false;
		if ( ! is_array( $saved ) || ! isset( $saved['versions'][ $index ] ) ) {
			return new WP_Error( 'qcld_wpbot_writer_compare_gone', __( 'These versions have expired. Compare again.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$old               = $saved['versions'][ $index ];
		$req               = $saved['req'];
		$req['techniques'] = array( $old['technique'] ? (string) $old['technique']['id'] : 'none' );
		$req['per']        = 1;
		$req['lab']        = false;
		$req['regen']      = $options;
		$result            = self::generate( $req );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$fresh                       = $result['versions'][0];
		$saved['versions'][ $index ] = $fresh;
		set_transient( $key, $saved, DAY_IN_SECONDS );
		return $fresh;
	}

	/**
	 * A stored comparison (P4 starts a test from it).
	 *
	 * @param string $id Comparison id.
	 * @return array|null req, stage, versions.
	 */
	public static function get( $id ) {
		$id    = preg_replace( '/[^A-Za-z0-9]/', '', (string) $id );
		$saved = '' !== $id ? get_transient( self::STORE . get_current_user_id() . '_' . $id ) : false;
		return is_array( $saved ) ? $saved : null;
	}

	/**
	 * Reading level of text: an approximate school grade from sentence and
	 * word length (syllables counted by vowel groups), shown as a band.
	 *
	 * @param string $text Text.
	 * @return array{grade: float, band: string, label: string}
	 */
	public static function reading_level( $text ) {
		$text      = trim( wp_strip_all_tags( (string) $text ) );
		$sentences = max( 1, count( array_filter( preg_split( '/[.!?]+(?:\s|$)|\n+/u', $text ) ) ) );
		preg_match_all( '/[\p{L}\']+/u', $text, $m );
		$words = max( 1, count( $m[0] ) );
		$syl   = 0;
		foreach ( $m[0] as $word ) {
			$syl += self::syllables( $word );
		}
		$grade = 0.39 * ( $words / $sentences ) + 11.8 * ( $syl / $words ) - 15.59;
		$grade = round( max( 0, min( 18, $grade ) ), 1 );
		if ( $grade <= 6 ) {
			$band  = 'simple';
			$label = __( 'Easy to read', 'chatbot' );
		} elseif ( $grade <= 10 ) {
			$band  = 'standard';
			$label = __( 'Standard', 'chatbot' );
		} else {
			$band  = 'advanced';
			$label = __( 'Advanced', 'chatbot' );
		}
		return array(
			'grade' => $grade,
			'band'  => $band,
			'label' => $label,
		);
	}

	/**
	 * Everything shown for one version.
	 *
	 * @param string $tech    Technique id.
	 * @param string $text    Text.
	 * @param array  $parts   Parts (role => text) or empty.
	 * @param int[]  $proof   Kit entry ids used.
	 * @param array  $entries Kit entries.
	 * @param string $stage   Reader stage.
	 * @param array  $kind    Kind.
	 * @param array  $honesty Honesty context (empty without core persuasion).
	 * @return array
	 */
	private static function describe_version( $tech, $text, array $parts, array $proof, array $entries, $stage, array $kind, array $honesty ) {
		$entry = Techniques::get( $tech );
		$fit   = 'unknown';
		if ( '' !== $stage && $entry && 'none' !== $entry['kind'] ) {
			if ( in_array( $stage, (array) $entry['avoid']['stages'], true ) ) {
				$fit = 'poor';
			} elseif ( in_array( $stage, isset( $entry['fits']['stages'] ) ? (array) $entry['fits']['stages'] : array(), true ) ) {
				$fit = 'good';
			} else {
				$fit = 'okay';
			}
		}
		$fit_labels = array(
			'good'    => __( 'Good fit for this reader stage', 'chatbot' ),
			'okay'    => __( 'Works, but not the usual fit for this stage', 'chatbot' ),
			'poor'    => __( 'Usually a poor fit for this stage', 'chatbot' ),
			'unknown' => '',
		);
		$used       = array();
		foreach ( $entries as $kit_entry ) {
			if ( in_array( (int) $kit_entry['id'], $proof, true ) ) {
				$used[] = array(
					'id'    => (int) $kit_entry['id'],
					'type'  => $kit_entry['type'],
					'label' => wp_trim_words( (string) ( '' !== (string) $kit_entry['label'] ? $kit_entry['label'] : $kit_entry['body'] ), 8 ),
				);
			}
		}
		$chars = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		return array(
			'technique' => Choices::view( $entry ),
			'text'      => $text,
			'parts'     => $parts,
			'fit'       => array(
				'level' => $fit,
				'label' => $fit_labels[ $fit ],
			),
			'length'    => array(
				'words' => str_word_count( wp_strip_all_tags( $text ) ),
				'chars' => $chars,
				'over'  => $kind['chars'] > 0 && $chars > $kind['chars'],
				'limit' => (int) $kind['chars'],
			),
			'reading'   => self::reading_level( $text ),
			'proof'     => $used,
			'honesty'   => array() !== $honesty ? qcld_wpbot_core_honesty_check( $text, $honesty ) : array(),
		);
	}

	/**
	 * Record what the owner chose (learning reads it in P4).
	 *
	 * @param int    $post_id    Post.
	 * @param string $kind       Kind.
	 * @param array  $techniques Role => technique id.
	 * @param string $stage      Reader stage.
	 * @param array  $honesty    Flags of the applied text.
	 * @return void
	 */
	private static function log( $post_id, $kind, array $techniques, $stage, array $honesty ) {
		$log   = get_post_meta( $post_id, self::LOG_META, true );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'kind'       => $kind,
			'techniques' => $techniques,
			'stage'      => $stage,
			'flags'      => count( $honesty ),
			'user'       => get_current_user_id(),
			'at'         => time(),
		);
		update_post_meta( $post_id, self::LOG_META, array_slice( $log, -30 ) );
	}

	/**
	 * Piece type used for fit (product, email, a social network, or the post's type).
	 *
	 * @param array $req Request.
	 * @return string
	 */
	private static function piece_type( array $req ) {
		if ( 'social_post' === $req['kind'] && in_array( $req['network'], array( 'linkedin', 'facebook', 'instagram', 'x', 'pinterest' ), true ) ) {
			return $req['network'];
		}
		if ( $req['product_id'] || ( $req['post_id'] && 'product' === get_post_type( $req['post_id'] ) ) ) {
			return 'product';
		}
		if ( $req['post_id'] && 'page' === get_post_type( $req['post_id'] ) && in_array( $req['kind'], array( 'cta', 'intro', 'section', 'headline' ), true ) ) {
			return 'page_copy';
		}
		$kinds = self::kinds();
		return $kinds[ $req['kind'] ]['type'];
	}

	/**
	 * Section job a kind of copy does.
	 *
	 * @param string $kind Kind.
	 * @return string
	 */
	private static function job( $kind ) {
		$jobs = array(
			'headline'      => 'hook',
			'intro'         => 'hook',
			'email_subject' => 'hook',
			'meta_title'    => 'hook',
			'cta'           => 'cta',
		);
		return isset( $jobs[ $kind ] ) ? $jobs[ $kind ] : '';
	}

	/**
	 * Reader stage of the piece: the post's stored choices, otherwise detected from its type.
	 *
	 * @param array $req Request.
	 * @return string
	 */
	private static function stage( array $req ) {
		if ( $req['post_id'] ) {
			$stored = get_post_meta( $req['post_id'], Choices::META, true );
			if ( is_array( $stored ) && ! empty( $stored['stage'] ) && in_array( $stored['stage'], Stages::IDS, true ) ) {
				return $stored['stage'];
			}
		}
		return Choices::detect_stage( self::piece_type( $req ), array() )[0];
	}

	/**
	 * Whether a technique's needs are met (proof needs the kit, price framing a real price).
	 *
	 * @param array $entry Technique.
	 * @param array $req   Request.
	 * @return bool
	 */
	private static function needs_met( array $entry, array $req ) {
		$needs = (array) $entry['needs'];
		if ( array() === $needs ) {
			return true;
		}
		$kit   = Context::kit( $req['product_id'] );
		$types = wp_list_pluck( $kit['entries'], 'type' );
		$woo   = is_array( $kit['woo'] ) ? $kit['woo'] : array();
		foreach ( $needs as $need ) {
			list( $source, $what ) = array_pad( explode( ':', (string) $need, 2 ), 2, '' );
			if ( 'kit' === $source && in_array( $what, $types, true ) ) {
				return true;
			}
			if ( 'woo' === $source && 'price' === $what && ! empty( $woo['price'] ) ) {
				return true;
			}
			if ( 'woo' === $source && 'sale_end' === $what && ! empty( $woo['sale_end'] ) ) {
				return true;
			}
			if ( 'woo' === $source && 'stock' === $what && isset( $woo['stock_quantity'] ) && null !== $woo['stock_quantity'] ) {
				return true;
			}
			if ( 'voc' === $source ) {
				return true; // Customer words help but are not a red line; the prompt says to use them when given.
			}
		}
		return false; // Any one need is enough; none met.
	}

	/**
	 * The piece for the prompt.
	 *
	 * @param array $req Request.
	 * @return string
	 */
	private static function describe( array $req ) {
		$text  = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( function_exists( 'excerpt_remove_blocks' ) ? excerpt_remove_blocks( $req['content'] ) : $req['content'] ), ENT_QUOTES, 'UTF-8' ) ) );
		$lines = array();
		if ( '' !== $req['network'] ) {
			$lines[] = 'Network: ' . $req['network'];
		}
		if ( $req['product_id'] ) {
			$lines[] = 'Product: ' . get_the_title( $req['product_id'] );
			if ( class_exists( \QuantumCloud\AiWriter\Woo\Product_Facts::class ) && function_exists( 'wc_get_product' ) ) {
				$facts = \QuantumCloud\AiWriter\Woo\Product_Facts::get( $req['product_id'] );
				if ( is_array( $facts ) ) {
					$lines[] = "Product facts (the only facts you may state):\n" . \QuantumCloud\AiWriter\Woo\Product_Facts::text( $facts );
				}
			}
		}
		$lines[] = 'Title: ' . ( '' !== $req['title'] ? $req['title'] : '(no title yet)' );
		if ( '' !== $req['notes'] ) {
			$lines[] = "Owner's brief:\n" . $req['notes'];
		}
		if ( '' !== $req['source'] ) {
			$lines[] = "Text to rewrite:\n" . $req['source'];
		}
		if ( '' !== $text ) {
			$lines[] = "Rest of the piece (context only):\n" . Prompts::clip( $text, 4000 );
		}
		return implode( "\n", $lines );
	}

	/**
	 * The kit for the prompt, each entry with its id so a version can say
	 * which proof it used ('' when the kit is empty).
	 *
	 * @param array $kit entries, woo.
	 * @return string
	 */
	private static function kit_prompt( array $kit ) {
		$lines = array();
		foreach ( $kit['entries'] as $entry ) {
			$one = \QuantumCloud\WPBotCore\Persuasion\Kit::prompt( array( $entry ) );
			$pos = strpos( $one, "\n- " );
			if ( false !== $pos ) {
				$lines[] = '- (id ' . (int) $entry['id'] . ') ' . substr( $one, $pos + 3 );
			}
		}
		$woo = \QuantumCloud\WPBotCore\Persuasion\Kit::prompt( array(), $kit['woo'] );
		$pos = strpos( $woo, "\n- " );
		if ( false !== $pos ) {
			$lines[] = substr( $woo, $pos + 1 );
		}
		return array() === $lines ? '' : "Persuasion Kit (the only proof you may use):\n" . implode( "\n", $lines );
	}

	/**
	 * Kit entries a version used: the ids it reported (only real ones) plus
	 * any entry whose value or label appears in the text.
	 *
	 * @param string $text     Version text.
	 * @param array  $reported Ids the AI reported.
	 * @param array  $entries  Kit entries.
	 * @return int[]
	 */
	public static function proof_used( $text, array $reported, array $entries ) {
		$reported = array_map( 'absint', $reported );
		$lower    = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
		$used     = array();
		foreach ( $entries as $entry ) {
			$id    = (int) $entry['id'];
			$found = in_array( $id, $reported, true );
			foreach ( array( $entry['value'], $entry['label'] ) as $needle ) {
				$needle = trim( function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $needle, 'UTF-8' ) : strtolower( (string) $needle ) );
				if ( ! $found && strlen( $needle ) >= 3 && false !== strpos( $lower, $needle ) ) {
					$found = true;
				}
			}
			if ( $found ) {
				$used[] = $id;
			}
		}
		return $used;
	}

	/**
	 * Facts the writer was given (the honesty check accepts claims found here).
	 *
	 * @param array $req Request.
	 * @return string
	 */
	private static function facts( array $req ) {
		$facts = $req['source'] . "\n" . $req['notes'] . "\n" . wp_strip_all_tags( $req['content'] );
		if ( $req['product_id'] && class_exists( \QuantumCloud\AiWriter\Woo\Product_Facts::class ) && function_exists( 'wc_get_product' ) ) {
			$product = \QuantumCloud\AiWriter\Woo\Product_Facts::get( $req['product_id'] );
			$facts  .= is_array( $product ) ? "\n" . \QuantumCloud\AiWriter\Woo\Product_Facts::text( $product ) : '';
		}
		return $facts;
	}

	/**
	 * One line or paragraph of AI text, cleaned.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function clean( $text ) {
		return trim( sanitize_textarea_field( str_replace( '\n', "\n", $text ) ) );
	}

	/**
	 * Syllables of a word (vowel groups; a silent final e dropped).
	 *
	 * @param string $word Word.
	 * @return int
	 */
	private static function syllables( $word ) {
		$word = strtolower( $word );
		if ( strlen( $word ) <= 3 ) {
			return 1;
		}
		$word = preg_replace( '/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word );
		$word = preg_replace( '/^y/', '', $word );
		return max( 1, preg_match_all( '/[aeiouy]{1,2}/', $word ) );
	}
}
