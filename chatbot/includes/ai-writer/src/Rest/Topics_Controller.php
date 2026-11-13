<?php
/**
 * REST routes of Topic Finder, the weekly email test and "Add to WPBot
 * knowledge".
 *
 * Seeing ideas needs the AI Writer View level; refreshing and acting on
 * ideas needs Use; the email test needs Settings. Adding a page to the
 * chatbot's knowledge needs Use plus edit rights on that published page.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Calendar\Calendar;
use QuantumCloud\AiWriter\Knowledge\Chatbot_Knowledge;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Topics\Competitor_Feeds;
use QuantumCloud\AiWriter\Topics\Ideas;
use QuantumCloud\AiWriter\Topics\Map_Sync;
use QuantumCloud\AiWriter\Topics\Coverage;
use QuantumCloud\AiWriter\Topics\Topic_Finder;
use QuantumCloud\AiWriter\Topics\Weekly_Email;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/topics/*, /knowledge/add
 */
final class Topics_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns   = Settings_Controller::NAMESPACE_V1;
		$id   = array(
			'type'              => 'integer',
			'required'          => true,
			'sanitize_callback' => 'absint',
		);
		$also = array(
			'type'    => 'array',
			'items'   => array( 'type' => 'integer' ),
			'default' => array(),
		);
		register_rest_route(
			$ns,
			'/topics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
		register_rest_route(
			$ns,
			'/topics/coverage',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'coverage' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
		register_rest_route(
			$ns,
			'/topics/coverage/(?P<id>\d+)/write',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'coverage_write' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array( 'id' => $id ),
			)
		);
		register_rest_route(
			$ns,
			'/topics/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'refresh' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'sources' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/topics/(?P<id>\d+)/dismiss',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dismiss' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'id'   => $id,
					'also' => $also,
				),
			)
		);
		register_rest_route(
			$ns,
			'/topics/(?P<id>\d+)/calendar',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'calendar' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'id'   => $id,
					'also' => $also,
					'date' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( Calendar::class, 'valid_date' ),
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/topics/(?P<id>\d+)/write',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'write' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array( 'id' => $id ),
			)
		);
		register_rest_route(
			$ns,
			'/topics/email/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'email_test' ),
				'permission_callback' => array( $this, 'can_settings' ),
			)
		);
		// Competitors for news feeds (CR-6 W3): shared list, AI suggestions, adding to the list.
		register_rest_route(
			$ns,
			'/topics/competitors',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'competitors' ),
				'permission_callback' => array( $this, 'can_settings' ),
			)
		);
		register_rest_route(
			$ns,
			'/topics/competitors/suggest',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'suggest_competitors' ),
				'permission_callback' => array( $this, 'can_settings' ),
			)
		);
		register_rest_route(
			$ns,
			'/topics/competitors/add',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_competitors' ),
				'permission_callback' => array( $this, 'can_settings' ),
				'args'                => array(
					'items' => array(
						'type'              => 'array',
						'required'          => true,
						'items'             => array(
							'type'       => 'object',
							'properties' => array(
								'domain' => array( 'type' => 'string' ),
								'reason' => array( 'type' => 'string' ),
								'source' => array( 'type' => 'string' ),
							),
						),
						'validate_callback' => static function ( $value ) {
							return is_array( $value ) && count( $value ) <= 20;
						},
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/knowledge/add',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_knowledge' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array( 'post_id' => $id ),
			)
		);
	}

	/**
	 * View level.
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Module::cap( 'view' ) );
	}

	/**
	 * Use level.
	 *
	 * @return bool
	 */
	public function can_use() {
		return current_user_can( Module::cap( 'use' ) );
	}

	/**
	 * Settings level.
	 *
	 * @return bool
	 */
	public function can_settings() {
		return current_user_can( Module::cap( 'settings' ) );
	}

	/**
	 * GET the shared competitor list with each site's feed.
	 *
	 * @return \WP_REST_Response
	 */
	public function competitors() {
		return rest_ensure_response(
			array(
				'available'   => Competitor_Feeds::available(),
				'can_suggest' => Competitor_Feeds::can_suggest(),
				'list'        => Competitor_Feeds::shared_with_feeds(),
			)
		);
	}

	/**
	 * POST AI competitor suggestions (uses AI credits; the owner clicked).
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function suggest_competitors() {
		$result = Competitor_Feeds::suggest();
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * POST add the ticked sites to the shared competitor list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function add_competitors( WP_REST_Request $request ) {
		$result = Competitor_Feeds::add_to_shared( (array) $request['items'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'list' => Competitor_Feeds::shared_with_feeds() ) );
	}

	/**
	 * GET sources, lists and the combined list.
	 *
	 * @return \WP_REST_Response
	 */
	public function overview() {
		return rest_ensure_response( Topic_Finder::overview() );
	}

	/**
	 * GET the Coverage view.
	 *
	 * @return \WP_REST_Response
	 */
	public function coverage() {
		return rest_ensure_response( Coverage::items() );
	}

	/**
	 * POST a brief for a topic ("Write next"): the Write screen address.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function coverage_write( WP_REST_Request $request ) {
		$url = Coverage::write_url( (int) $request['id'] );
		return is_wp_error( $url ) ? $url : rest_ensure_response( array( 'url' => $url ) );
	}

	/**
	 * POST start a refresh.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function refresh( WP_REST_Request $request ) {
		$job_id = Topic_Finder::refresh( array_map( 'sanitize_key', (array) $request['sources'] ) );
		return is_wp_error( $job_id ) ? $job_id : rest_ensure_response( array( 'job_id' => $job_id ) );
	}

	/**
	 * POST dismiss an idea.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function dismiss( WP_REST_Request $request ) {
		$idea = Ideas::get( (int) $request['id'] );
		if ( ! $idea ) {
			return $this->missing();
		}
		Ideas::set_status( $idea['id'], 'dismissed' );
		Map_Sync::unplan_idea( $idea['id'] );
		foreach ( $this->also( $request ) as $other ) {
			Ideas::set_status( $other, 'dismissed' );
			Map_Sync::unplan_idea( $other );
		}
		return rest_ensure_response( array( 'dismissed' => true ) );
	}

	/**
	 * POST add an idea to the calendar (a draft dated that day).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function calendar( WP_REST_Request $request ) {
		$result = Calendar::place_idea( (int) $request['id'], (string) $request['date'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		foreach ( $this->also( $request ) as $other ) {
			Ideas::set_status( $other, 'planned', $result['post_id'] );
		}
		return rest_ensure_response( $result );
	}

	/**
	 * POST open a pre-filled brief for an idea. Visitor questions open the
	 * "Answer visitor questions" flow.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function write( WP_REST_Request $request ) {
		$idea = Ideas::get( (int) $request['id'] );
		if ( ! $idea ) {
			return $this->missing();
		}
		$sources  = Topic_Finder::sources();
		$question = isset( $sources[ $idea['source'] ] ) && $sources[ $idea['source'] ]['questions'];
		$notes    = $idea['reason'];
		if ( array() !== $idea['questions'] ) {
			$notes .= "\n" . __( 'Visitors asked, for example:', 'chatbot' ) . "\n- " . implode( "\n- ", $idea['questions'] );
		}
		$brief = array(
			'mode'          => $question ? 'answer' : 'new',
			'topic'         => $idea['title'],
			'type'          => $question ? 'faq' : '',
			'notes'         => trim( $notes ),
			'source_module' => 'topic_finder',
			// Pre-filled from the shared keyword and topic map.
			'main_query'    => '' !== $idea['main_query'] ? $idea['main_query'] : ( $question ? '' : $idea['title'] ),
			'map_topic'     => $idea['topic'],
			'idea_id'       => $idea['id'],
			// CR-16: the suggested format (the owner can change it in the brief).
			'format'        => ! $question && ! empty( $idea['format']['id'] ) ? $idea['format']['id'] : '',
			// The idea's search numbers travel with the brief (CR-6 W2).
			'numbers'       => self::demand_numbers( isset( $idea['demand'] ) ? (array) $idea['demand'] : array() ),
		);
		// An idea from the Keyword Vault: its intent, questions, supporting keywords and notes (CR-4).
		$vault = \QuantumCloud\AiWriter\Topics\Vault_Link::get( \QuantumCloud\AiWriter\Topics\Vault_Link::id_from_ref( (string) $idea['source_ref'] ) );
		if ( $vault ) {
			$fields          = \QuantumCloud\AiWriter\Topics\Vault_Link::brief_fields( $vault );
			$fields['notes'] = trim( $brief['notes'] . "\n" . $fields['notes'] );
			$brief           = array_merge( $brief, array_filter( $fields ) );
		}
		$url = qcld_wpbot_writer_open_brief_url( $brief );
		return rest_ensure_response( array( 'url' => $url ) );
	}

	/**
	 * POST send the weekly email now to the current user (a test).
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function email_test() {
		$user   = wp_get_current_user();
		$result = Weekly_Email::send( array( $user->user_email ), true );
		if ( ! $result['sent'] ) {
			return new WP_Error( 'qcld_wpbot_writer_mail_failed', __( 'WordPress could not send the email. Check that this site can send mail (an SMTP plugin usually fixes this).', 'chatbot' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array_merge( $result, array( 'to' => $user->user_email ) ) );
	}

	/**
	 * POST add a published page to the chatbot's knowledge.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function add_knowledge( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$post    = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_edit', __( 'You are not allowed to change this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'qcld_wpbot_writer_not_published', __( 'Publish the page first, then add it to the chatbot knowledge.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$result = Chatbot_Knowledge::add_post( $post_id );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		update_post_meta( $post_id, '_qcld_wpbot_writer_in_knowledge', gmdate( 'c' ) );
		return rest_ensure_response( array( 'added' => true ) );
	}

	/**
	 * Other ideas merged into the same topic (combined ranking), at most 20.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int[]
	 */
	private function also( WP_REST_Request $request ) {
		$ids = array_slice( array_filter( array_map( 'absint', (array) $request['also'] ) ), 0, 20 );
		return array_values( array_diff( $ids, array( (int) $request['id'] ) ) );
	}

	/**
	 * Idea not found.
	 *
	 * @return WP_Error
	 */
	private function missing() {
		return new WP_Error( 'qcld_wpbot_writer_no_idea', __( 'This idea no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
	}

	/**
	 * Labelled numbers for a brief from an idea's demand.
	 *
	 * @param array $demand Demand (Ideas::demand()).
	 * @return array label => value.
	 */
	private static function demand_numbers( array $demand ) {
		$out = array();
		if ( array() === $demand ) {
			return $out;
		}
		if ( isset( $demand['volume'] ) && null !== $demand['volume'] ) {
			$out[ __( 'Monthly searches', 'chatbot' ) ] = number_format_i18n( (int) $demand['volume'] );
		} elseif ( ! empty( $demand['volume_range'] ) ) {
			$out[ __( 'Monthly searches', 'chatbot' ) ] = number_format_i18n( (int) $demand['volume_range'][0] ) . '–' . number_format_i18n( (int) $demand['volume_range'][1] );
		} elseif ( ! empty( $demand['estimate'] ) ) {
			$out[ __( 'Demand', 'chatbot' ) ] = sprintf(
				/* translators: %s: high, medium or low. */
				__( '%s (AI estimate)', 'chatbot' ),
				\QuantumCloud\AiWriter\Topics\Vault_Link::level_label( (string) $demand['estimate'] )
			);
		}
		if ( ! empty( $demand['trend_direction'] ) ) {
			$out[ __( 'Trend', 'chatbot' ) ] = \QuantumCloud\AiWriter\Topics\Vault_Link::trend_label( (string) $demand['trend_direction'] );
		}
		if ( ! empty( $demand['competition'] ) ) {
			$out[ __( 'Competition', 'chatbot' ) ] = \QuantumCloud\AiWriter\Topics\Vault_Link::level_label( (string) $demand['competition'] );
		}
		if ( array() !== $out && ! empty( $demand['source'] ) && empty( $demand['estimate'] ) ) {
			$out[ __( 'Source', 'chatbot' ) ] = (string) $demand['source'];
		}
		foreach ( isset( $demand['numbers'] ) ? (array) $demand['numbers'] : array() as $label => $value ) {
			if ( ! isset( $out[ $label ] ) && count( $out ) < 8 ) {
				$out[ $label ] = (string) $value;
			}
		}
		return $out;
	}
}
