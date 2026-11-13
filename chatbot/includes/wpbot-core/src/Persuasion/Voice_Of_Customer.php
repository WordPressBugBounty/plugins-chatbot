<?php
/**
 * Voice of customer bank (round 3, CR-13): the words customers actually
 * use, grouped by topic, with personal data removed.
 *
 * Sources register on qcld_wpbot_core/voc_sources. WPBot Core brings
 * WooCommerce product reviews and the kit's reviews and testimonials; AI
 * Writer adds the site search log (Free) and AI Writer Pro saved chat
 * sessions. Every text is redacted with Privacy\Redactor (plus names the
 * source knows) before it is stored, and the grouping prompt redacts again,
 * so no personal data reaches an AI call.
 *
 * A refresh is a background job: "voc_collect" steps read each source in
 * small batches; "voc_group" steps (only when the owner asks, after a cost
 * estimate) group 40 phrases per AI call into topics.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Persuasion;

use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use QuantumCloud\WPBotCore\Privacy\Redactor;
use QuantumCloud\WPBotCore\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Voice of customer bank.
 */
final class Voice_Of_Customer {

	const TYPE         = 'core_voc_refresh';
	const STEP_COLLECT = 'core_voc_collect';
	const STEP_GROUP   = 'core_voc_group';
	const STATE        = 'qcld_wpbot_core_voc_state';
	const BATCH        = 50;
	const GROUP_BATCH  = 40;
	const MAX_PHRASES  = 5000;

	/**
	 * Job step handlers.
	 *
	 * @param array $handlers Handlers.
	 * @return array
	 */
	public static function handlers( $handlers ) {
		$handlers[ self::STEP_COLLECT ] = array( __CLASS__, 'collect_step' );
		$handlers[ self::STEP_GROUP ]   = array( __CLASS__, 'group_step' );
		return $handlers;
	}

	/**
	 * Registered sources.
	 *
	 * Each: label, edition (free|pro), callback( int $cursor, int $limit ) returning
	 * array( 'items' => array( array( 'ref' => string, 'text' => string, 'names' => string[], 'min_words' => int ) ), 'cursor' => int, 'done' => bool ).
	 *
	 * @return array<string,array>
	 */
	public static function sources() {
		$sources = array(
			'review' => array(
				'label'    => __( 'Product reviews', 'chatbot' ),
				'edition'  => 'free',
				'callback' => array( __CLASS__, 'review_items' ),
			),
			'kit'    => array(
				'label'    => __( 'Reviews in your Persuasion Kit', 'chatbot' ),
				'edition'  => 'free',
				'callback' => array( __CLASS__, 'kit_items' ),
			),
			// Phrases the owner approved from their own pages and documents (add()); nothing to collect.
			'import' => array(
				'label'    => __( 'Imported from your content', 'chatbot' ),
				'edition'  => 'free',
				'callback' => array( __CLASS__, 'no_items' ),
			),
		);
		/**
		 * Voice of customer sources.
		 *
		 * @param array<string,array> $sources id => label, edition, callback.
		 */
		$sources = (array) apply_filters( 'qcld_wpbot_core/voc_sources', $sources );
		$out     = array();
		foreach ( $sources as $id => $source ) {
			if ( is_array( $source ) && ! empty( $source['label'] ) && isset( $source['callback'] ) && is_callable( $source['callback'] ) ) {
				$out[ substr( sanitize_key( $id ), 0, 30 ) ] = $source + array( 'edition' => 'free' );
			}
		}
		return $out;
	}

	/**
	 * Add phrases the owner approved (for example from an import of their own
	 * pages or documents). Each is redacted and kept like collected phrases;
	 * repeats only raise the weight.
	 *
	 * @param string[] $phrases Phrases.
	 * @param string   $source  Source id (default 'import').
	 * @return int Phrases added.
	 */
	public static function add( array $phrases, $source = 'import' ) {
		$source = substr( sanitize_key( (string) $source ), 0, 30 );
		$source = '' !== $source ? $source : 'import';
		$added  = 0;
		foreach ( array_slice( $phrases, 0, 200 ) as $phrase ) {
			foreach ( self::phrases( is_scalar( $phrase ) ? (string) $phrase : '', array(), 2 ) as $clean ) {
				$added += self::store( $clean, $source );
			}
		}
		return $added;
	}

	/**
	 * A source with nothing to collect (its phrases come from add()).
	 *
	 * @return array
	 */
	public static function no_items() {
		return array(
			'items'  => array(),
			'cursor' => 0,
			'done'   => true,
		);
	}

	/**
	 * Phrases from a text: sentences of 3 to 40 words, redacted, without the
	 * parts that are only personal data.
	 *
	 * @param string   $text      Raw text.
	 * @param string[] $names     Names to remove (from the source's metadata).
	 * @param int      $min_words Shortest phrase kept (search terms use 2).
	 * @return string[]
	 */
	public static function phrases( $text, array $names = array(), $min_words = 3 ) {
		$text = Redactor::redact( wp_strip_all_tags( (string) $text ), $names );
		$out  = array();
		foreach ( (array) preg_split( '/(?<=[.!?])\s+|\n+/u', $text ) as $sentence ) {
			$sentence = trim( (string) preg_replace( '/^[\s"\'\x{201C}\x{201D}]+|[\s"\'\x{201C}\x{201D}]+$/u', '', (string) $sentence ) );
			$words    = preg_split( '/\s+/u', $sentence );
			if ( count( $words ) < max( 1, (int) $min_words ) || count( $words ) > 40 ) {
				continue;
			}
			// Sentences that are mostly redaction markers say nothing useful.
			$markers = preg_match_all( '/\[(?:email|link|card|phone|address|postcode|name)\]/', $sentence );
			if ( $markers > 0 && $markers * 3 >= count( $words ) ) {
				continue;
			}
			$out[] = self::cut( $sentence, 300 );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Start a refresh: collect from every source (no cost), then group with
	 * AI when $group is true (the caller showed the estimate first).
	 *
	 * @param string $module Module whose AI key and budget pay for grouping.
	 * @param bool   $group  Group phrases into topics with AI.
	 * @param array  $only   Source ids (empty: all).
	 * @return int|WP_Error Job id.
	 */
	public static function refresh( $module, $group = false, array $only = array() ) {
		$state = self::state();
		if ( ! empty( $state['job_id'] ) ) {
			$job = Job_Store::get( (int) $state['job_id'] );
			if ( $job && in_array( $job['status'], array( 'queued', 'running', 'paused' ), true ) ) {
				// Grouping asked for while reading: group when the reading ends.
				if ( $group && empty( $job['payload']['group'] ) ) {
					Job_Store::merge_payload( (int) $state['job_id'], array( 'group' => true ) );
				}
				return (int) $state['job_id'];
			}
		}
		$steps = array();
		foreach ( self::sources() as $id => $source ) {
			if ( $only && ! in_array( $id, $only, true ) ) {
				continue;
			}
			$steps[] = array(
				'kind'  => self::STEP_COLLECT,
				/* translators: %s: source name. */
				'label' => sprintf( __( 'Reading %s', 'chatbot' ), $source['label'] ),
				'input' => array(
					'source' => $id,
					'cursor' => isset( $state['cursors'][ $id ] ) ? (int) $state['cursors'][ $id ] : 0,
				),
			);
		}
		if ( array() === $steps ) {
			return new WP_Error( 'qcld_wpbot_core_voc_none', __( 'There is nothing to read yet: add reviews to your Persuasion Kit or your products first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$job_id = Job_Store::create(
			sanitize_key( $module ),
			self::TYPE,
			array(
				'title'   => __( 'Customer words', 'chatbot' ),
				'payload' => array(
					'group'  => (bool) $group,
					'module' => sanitize_key( $module ),
				),
			),
			$steps
		);
		if ( ! $job_id ) {
			return new WP_Error( 'qcld_wpbot_core_job_failed', __( 'The job could not be created.', 'chatbot' ), array( 'status' => 500 ) );
		}
		$state['job_id'] = (int) $job_id;
		update_option( self::STATE, $state, false );
		return (int) $job_id;
	}

	/**
	 * Step: read one batch of a source; queue the next batch, then grouping.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function collect_step( array $step, array $job ) {
		$input   = (array) $step['input'];
		$id      = isset( $input['source'] ) ? (string) $input['source'] : '';
		$sources = self::sources();
		if ( ! isset( $sources[ $id ] ) ) {
			return array( 'skipped' => $id );
		}
		$page = call_user_func( $sources[ $id ]['callback'], (int) $input['cursor'], self::BATCH );
		$page = is_array( $page ) ? $page : array();
		$kept = 0;
		foreach ( isset( $page['items'] ) ? (array) $page['items'] : array() as $item ) {
			$names = isset( $item['names'] ) ? array_map( 'strval', (array) $item['names'] ) : array();
			$min   = isset( $item['min_words'] ) ? (int) $item['min_words'] : 3;
			foreach ( self::phrases( isset( $item['text'] ) ? (string) $item['text'] : '', $names, $min ) as $phrase ) {
				$kept += self::store( $phrase, $id );
			}
		}
		$cursor                   = isset( $page['cursor'] ) ? (int) $page['cursor'] : (int) $input['cursor'];
		$state                    = self::state();
		$state['cursors'][ $id ]  = $cursor;
		$state['last_run'][ $id ] = gmdate( 'c' );
		update_option( self::STATE, $state, false );

		$more = array();
		if ( empty( $page['done'] ) && ! empty( $page['items'] ) ) {
			$more[] = array(
				'kind'  => self::STEP_COLLECT,
				'label' => $step['label'],
				'input' => array(
					'source' => $id,
					'cursor' => $cursor,
				),
			);
		} elseif ( ! empty( $job['payload']['group'] ) && ! self::collect_pending( (int) $job['id'], (int) $step['id'] ) ) {
			$calls = min( 25, (int) ceil( self::ungrouped_count() / self::GROUP_BATCH ) );
			for ( $i = 0; $i < $calls; $i++ ) {
				$more[] = array(
					'kind'  => self::STEP_GROUP,
					'label' => __( 'Grouping customer words by topic', 'chatbot' ),
					'input' => array(),
				);
			}
		}
		if ( $more ) {
			Job_Store::add_steps( (int) $job['id'], $more );
		}
		return array(
			'source' => $id,
			'kept'   => $kept,
		);
	}

	/**
	 * Whether other collect steps of the job are still waiting.
	 *
	 * @param int $job_id  Job.
	 * @param int $step_id This step.
	 * @return bool
	 */
	private static function collect_pending( $job_id, $step_id ) {
		foreach ( Job_Store::steps( $job_id ) as $other ) {
			if ( (int) $other['id'] !== $step_id && self::STEP_COLLECT === $other['kind'] && in_array( $other['status'], array( 'pending', 'running' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Step: group up to 40 ungrouped phrases into topics with AI.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array|WP_Error
	 */
	public static function group_step( array $step, array $job ) {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, phrase FROM {$table} WHERE topic = '' ORDER BY weight DESC, id ASC LIMIT %d", self::GROUP_BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		if ( ! $rows ) {
			return array( 'grouped' => 0 );
		}
		$request  = self::grouping_request( wp_list_pluck( $rows, 'phrase' ), self::known_topics() );
		$module   = ! empty( $job['payload']['module'] ) ? (string) $job['payload']['module'] : (string) $job['module'];
		$response = AI_Service::generate( $module, 'voice_of_customer', $request, array( 'job_id' => (int) $job['id'] ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data    = $response->json();
		$grouped = 0;
		foreach ( isset( $data['groups'] ) && is_array( $data['groups'] ) ? $data['groups'] : array() as $group ) {
			$topic = isset( $group['topic'] ) ? self::cut( sanitize_text_field( (string) $group['topic'] ), 100 ) : '';
			if ( '' === $topic ) {
				continue;
			}
			foreach ( isset( $group['phrases'] ) ? (array) $group['phrases'] : array() as $index ) {
				$index = (int) $index - 1;
				if ( isset( $rows[ $index ] ) ) {
					$wpdb->update( $table, array( 'topic' => $topic ), array( 'id' => (int) $rows[ $index ]['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
					++$grouped;
				}
			}
		}
		// Phrases the AI left out go to "Other" so the next step moves on.
		$ids = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET topic = %s WHERE topic = '' AND id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( __( 'Other', 'chatbot' ) ), $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; placeholders built from a count.
		return array(
			'grouped' => $grouped,
			'_usage'  => array(
				'tokens_in'  => $response->tokens_in,
				'tokens_out' => $response->tokens_out,
				'cost'       => $response->cost,
			),
		);
	}

	/**
	 * The grouping request. Every phrase is redacted again here, so even a
	 * phrase stored by an older version or another module cannot carry
	 * personal data into the AI call.
	 *
	 * @param string[] $phrases Phrases.
	 * @param string[] $topics  Topic names to prefer.
	 * @return Request
	 */
	public static function grouping_request( array $phrases, array $topics = array() ) {
		$lines = array();
		foreach ( array_values( $phrases ) as $i => $phrase ) {
			$lines[] = ( $i + 1 ) . '. ' . Redactor::redact( (string) $phrase );
		}
		$system = "Task: voice_of_customer\nGroup these customer phrases by the topic or problem they are about. Use short topic names (2 to 4 words) in the language of the phrases"
			. ( $topics ? '; prefer these existing topics when they fit: ' . implode( ', ', array_slice( array_map( 'strval', $topics ), 0, 40 ) ) : '' )
			. ". Do not rewrite the phrases.\nAnswer with one JSON object: {\"groups\":[{\"topic\":\"\",\"phrases\":[1,2]}]}.";
		return Request::make(
			$system,
			implode( "\n", $lines ),
			array(
				'json'       => true,
				'max_tokens' => 900,
			)
		);
	}

	/**
	 * Cost estimate for grouping the phrases waiting now.
	 *
	 * @param string $module Module.
	 * @return array usd, calls, phrases, model.
	 */
	public static function estimate( $module ) {
		$count = self::ungrouped_count();
		$calls = (int) min( 25, ceil( $count / self::GROUP_BATCH ) );
		$order = Keys::call_order( sanitize_key( $module ) );
		$usd   = 0.0;
		$model = '';
		if ( isset( $order[0] ) && $calls > 0 ) {
			$set   = Keys::get_set( sanitize_key( $module ) );
			$model = ! empty( $set['providers'][ $order[0] ]['model'] ) ? $set['providers'][ $order[0] ]['model'] : Providers::default_text_model( $order[0] );
			$info  = Providers::model_info( $order[0], $model );
			$usd   = $calls * ( 1500 * $info['price_in'] + 700 * $info['price_out'] ) / 1000000;
		}
		return array(
			'usd'     => round( $usd, 3 ),
			'calls'   => $calls,
			'phrases' => $count,
			'model'   => $model,
		);
	}

	/**
	 * Store one phrase (or count it again).
	 *
	 * @param string $phrase Redacted phrase.
	 * @param string $source Source id.
	 * @return int 1 when new.
	 */
	private static function store( $phrase, $source ) {
		global $wpdb;
		$table = self::table();
		$ref   = md5( strtolower( $phrase ) );
		$found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE source = %s AND source_ref = %s", $source, $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		if ( $found ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET weight = weight + 1 WHERE id = %d", $found ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
			return 0;
		}
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) >= self::MAX_PHRASES ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
			return 0;
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, write.
			$table,
			array(
				'topic'      => '',
				'phrase'     => $phrase,
				'source'     => substr( sanitize_key( $source ), 0, 30 ),
				'source_ref' => $ref,
				'weight'     => 1,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		return 1;
	}

	/**
	 * Phrases, most used first.
	 *
	 * @param array $args topic, source (string|string[]), search, limit (default 50, max 500).
	 * @return array[] id, topic, phrase, source, weight.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table  = self::table();
		$where  = array( '1=1' );
		$values = array();
		if ( isset( $args['topic'] ) && '' !== (string) $args['topic'] ) {
			$where[]  = 'topic = %s';
			$values[] = (string) $args['topic'];
		}
		if ( ! empty( $args['source'] ) ) {
			$sources = array_map( 'sanitize_key', (array) $args['source'] );
			$where[] = 'source IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')';
			$values  = array_merge( $values, $sources );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'phrase LIKE %s';
			$values[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}
		$limit    = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 50;
		$values[] = $limit;
		$sql      = "SELECT id, topic, phrase, source, weight FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY weight DESC, id DESC LIMIT %d';
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; built from placeholders.
		return array_map(
			static function ( $row ) {
				return array(
					'id'     => (int) $row['id'],
					'topic'  => (string) $row['topic'],
					'phrase' => (string) $row['phrase'],
					'source' => (string) $row['source'],
					'weight' => (int) $row['weight'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Topics with their phrase counts.
	 *
	 * @return array[] topic, phrases, weight.
	 */
	public static function topics() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT topic, COUNT(*) AS phrases, SUM(weight) AS weight FROM {$table} GROUP BY topic ORDER BY weight DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		return array_map(
			static function ( $row ) {
				return array(
					'topic'   => (string) $row['topic'],
					'phrases' => (int) $row['phrases'],
					'weight'  => (int) $row['weight'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Delete a phrase (the owner removes one they do not want used).
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
	}

	/**
	 * Phrases not grouped yet.
	 *
	 * @return int
	 */
	public static function ungrouped_count() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE topic = ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
	}

	/**
	 * Refresh state: cursors per source, last run per source, job id.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::STATE, array() );
		$state = is_array( $state ) ? $state : array();
		return $state + array(
			'cursors'  => array(),
			'last_run' => array(),
			'job_id'   => 0,
		);
	}

	/**
	 * Source: approved WooCommerce product reviews, oldest first.
	 *
	 * @param int $cursor Last comment id read.
	 * @param int $limit  Batch size.
	 * @return array
	 */
	public static function review_items( $cursor, $limit ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array(
				'items'  => array(),
				'cursor' => (int) $cursor,
				'done'   => true,
			);
		}
		global $wpdb;
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT comment_ID, comment_content, comment_author FROM {$wpdb->comments} WHERE comment_type = 'review' AND comment_approved = '1' AND comment_ID > %d ORDER BY comment_ID ASC LIMIT %d", (int) $cursor, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Batch read by cursor; nothing to cache.
		$items = array();
		$last  = (int) $cursor;
		foreach ( (array) $rows as $row ) {
			$last    = (int) $row['comment_ID'];
			$items[] = array(
				'ref'   => 'review-' . $last,
				'text'  => (string) $row['comment_content'],
				'names' => array( (string) $row['comment_author'] ),
			);
		}
		return array(
			'items'  => $items,
			'cursor' => $last,
			'done'   => count( $items ) < (int) $limit,
		);
	}

	/**
	 * Source: reviews and testimonials in the Persuasion Kit (wording only).
	 *
	 * @param int $cursor Unused (the kit is small; read in one go).
	 * @param int $limit  Unused.
	 * @return array
	 */
	public static function kit_items( $cursor, $limit ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Source callback signature.
		$items = array();
		foreach ( Kit::query( array( 'types' => Kit::QUOTES ) )['entries'] as $entry ) {
			$items[] = array(
				'ref'   => 'kit-' . $entry['id'],
				'text'  => $entry['body'],
				'names' => array_filter( array( $entry['value'], $entry['source'] ) ),
			);
		}
		return array(
			'items'  => $items,
			'cursor' => 0,
			'done'   => true,
		);
	}

	/**
	 * Topic names from the shared topic map, to group into.
	 *
	 * @return string[]
	 */
	private static function known_topics() {
		if ( ! class_exists( '\QuantumCloud\WPBotCore\Map\Topic_Map' ) || ! method_exists( '\QuantumCloud\WPBotCore\Map\Topic_Map', 'topics' ) ) {
			return array();
		}
		$names = array();
		foreach ( (array) \QuantumCloud\WPBotCore\Map\Topic_Map::topics() as $topic ) {
			if ( isset( $topic['name'] ) && empty( $topic['parent_id'] ) ) {
				$names[] = (string) $topic['name'];
			}
		}
		return array_slice( $names, 0, 40 );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		return Schema::table( Schema::VOC );
	}

	/**
	 * Cut a string (multibyte safe).
	 *
	 * @param string $text   Text.
	 * @param int    $length Length.
	 * @return string
	 */
	private static function cut( $text, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length, 'UTF-8' ) : substr( $text, 0, $length );
	}
}
