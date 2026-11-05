<?php
/**
 * Links area: link monitor, prospects, local citations, outreach, exchange
 * records and results.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Links\Bing_Client;
use QuantumCloud\SeoAiSearch\Links\Citations;
use QuantumCloud\SeoAiSearch\Links\Csv_Import;
use QuantumCloud\SeoAiSearch\Links\Exchanges;
use QuantumCloud\SeoAiSearch\Links\Link_Checker;
use QuantumCloud\SeoAiSearch\Links\Link_Store;
use QuantumCloud\SeoAiSearch\Links\Links_Report;
use QuantumCloud\SeoAiSearch\Links\Outreach;
use QuantumCloud\SeoAiSearch\Links\Outreach_Drafts;
use QuantumCloud\SeoAiSearch\Links\Prospects;
use QuantumCloud\SeoAiSearch\Links\Referrals;
use QuantumCloud\SeoAiSearch\Secrets;
use QuantumCloud\SeoAiSearch\Writer\Writer_Bridge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /links, /prospects, /citations, /outreach, /exchanges.
 */
final class Links_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/links'                          => array( WP_REST_Server::READABLE, 'links', 'can_view' ),
			'/links/known/add'                => array( WP_REST_Server::CREATABLE, 'add_link', 'can_use' ),
			'/links/known/(?P<id>\d+)/delete' => array( WP_REST_Server::CREATABLE, 'delete_link', 'can_use' ),
			'/links/import'                   => array( WP_REST_Server::CREATABLE, 'import', 'can_use' ),
			'/links/bing'                     => array( WP_REST_Server::CREATABLE, 'bing', 'can_use' ),
			'/links/check'                    => array( WP_REST_Server::CREATABLE, 'check', 'can_use' ),
			'/links/report'                   => array( WP_REST_Server::READABLE, 'report', 'can_view' ),
			'/prospects'                      => array( WP_REST_Server::READABLE, 'prospects', 'can_view' ),
			'/prospects/add'                  => array( WP_REST_Server::CREATABLE, 'add_prospect', 'can_use' ),
			'/prospects/linkable'             => array( WP_REST_Server::READABLE, 'linkable', 'can_view' ),
			'/prospects/linkable/write'       => array( WP_REST_Server::CREATABLE, 'write_linkable', 'can_use' ),
			'/prospects/(?P<id>\d+)'          => array( WP_REST_Server::CREATABLE, 'update_prospect', 'can_use' ),
			'/prospects/(?P<id>\d+)/delete'   => array( WP_REST_Server::CREATABLE, 'delete_prospect', 'can_use' ),
			'/prospects/(?P<id>\d+)/outreach' => array( WP_REST_Server::CREATABLE, 'start_outreach', 'can_use' ),
			'/citations'                      => array( WP_REST_Server::READABLE, 'citations', 'can_view' ),
			'/citations/(?P<dir>[a-z0-9_-]+)' => array( WP_REST_Server::CREATABLE, 'save_citation', 'can_use' ),
			'/outreach'                       => array( WP_REST_Server::READABLE, 'outreach', 'can_view' ),
			'/outreach/add'                   => array( WP_REST_Server::CREATABLE, 'add_outreach', 'can_use' ),
			'/outreach/(?P<id>\d+)'           => array( WP_REST_Server::READABLE, 'get_outreach', 'can_view' ),
			'/outreach/(?P<id>\d+)/save'      => array( WP_REST_Server::CREATABLE, 'update_outreach', 'can_use' ),
			'/outreach/(?P<id>\d+)/delete'    => array( WP_REST_Server::CREATABLE, 'delete_outreach', 'can_use' ),
			'/outreach/(?P<id>\d+)/draft'     => array( WP_REST_Server::CREATABLE, 'draft', 'can_use' ),
			'/outreach/(?P<id>\d+)/log'       => array( WP_REST_Server::CREATABLE, 'log', 'can_use' ),
			'/outreach/(?P<id>\d+)/write'     => array( WP_REST_Server::CREATABLE, 'write', 'can_use' ),
			'/exchanges'                      => array( WP_REST_Server::READABLE, 'exchanges', 'can_view' ),
			'/exchanges/save'                 => array( WP_REST_Server::CREATABLE, 'save_exchange', 'can_use' ),
			'/exchanges/(?P<id>\d+)/check'    => array( WP_REST_Server::CREATABLE, 'check_exchange', 'can_use' ),
			'/exchanges/(?P<id>\d+)/delete'   => array( WP_REST_Server::CREATABLE, 'delete_exchange', 'can_use' ),
		);
		foreach ( $routes as $route => $def ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => array( $this, $def[2] ),
				)
			);
		}
	}

	/**
	 * Link monitor.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function links( WP_REST_Request $request ) {
		$list    = Link_Store::query(
			array(
				'status' => sanitize_key( (string) $request['status'] ),
				'origin' => sanitize_key( (string) $request['origin'] ),
				'search' => sanitize_text_field( (string) $request['search'] ),
				'limit'  => absint( $request['limit'] ? $request['limit'] : 100 ),
				'offset' => absint( $request['offset'] ),
			)
		);
		$summary = Csv_Import::summary();
		$bing    = Bing_Client::saved();
		return rest_ensure_response(
			array_merge(
				$list,
				array(
					'csv'       => array(
						'sites'    => array_slice( $summary['sites'], 0, 50, true ),
						'anchors'  => array_slice( $summary['anchors'], 0, 30, true ),
						'targets'  => array_slice( $summary['targets'], 0, 30, true ),
						'imported' => $summary['imported'],
					),
					'bing'      => array(
						'key'    => '' !== Secrets::get( 'bing' ),
						'pages'  => array_slice( $bing['pages'], 0, 30, true ),
						'pulled' => $bing['pulled'],
					),
					'referrals' => array(
						'available' => Referrals::available(),
						'sites'     => array_slice( Referrals::sources( 28 ), 0, 30 ),
					),
				)
			)
		);
	}

	/**
	 * Add a known link.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function add_link( WP_REST_Request $request ) {
		$id = Link_Store::add(
			array(
				'source_url' => (string) $request['source_url'],
				'target_url' => (string) $request['target_url'],
				'anchor'     => (string) $request['anchor'],
				'rel'        => sanitize_key( (string) $request['rel'] ),
				'notes'      => (string) $request['notes'],
				'origin'     => 'manual',
			)
		);
		return $this->respond( is_wp_error( $id ) ? $id : Link_Store::get( $id ) );
	}

	/**
	 * Delete a link.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_link( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'deleted' => Link_Store::delete( (int) $request['id'] ) ) );
	}

	/**
	 * Import a Search Console Links CSV (text read in the browser).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$text = (string) $request['text'];
		if ( strlen( $text ) > 5000000 ) {
			return new WP_Error( 'qcld_wpbot_seo_too_big', __( 'The file is too large (5 MB at most).', 'chatbot' ), array( 'status' => 400 ) );
		}
		$result = Csv_Import::import( $text );
		if ( 'unknown' === $result['kind'] ) {
			return new WP_Error( 'qcld_wpbot_seo_csv_unknown', __( 'This does not look like a Search Console Links export. In Search Console open Links, then "Export external links" and choose "More sample links" or "Latest links".', 'chatbot' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Pull Bing link counts and linking pages.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function bing() {
		return $this->respond( Bing_Client::pull( 10 ) );
	}

	/**
	 * Start a link check job.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function check( WP_REST_Request $request ) {
		$job = Link_Checker::start( array_map( 'absint', (array) $request['ids'] ) );
		return $this->respond( is_wp_error( $job ) ? $job : array( 'job_id' => $job ) );
	}

	/**
	 * Results.
	 *
	 * @return \WP_REST_Response
	 */
	public function report() {
		return rest_ensure_response( Links_Report::build() );
	}

	/**
	 * Prospects.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function prospects( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'items' => Prospects::query( sanitize_key( (string) $request['kind'] ), sanitize_key( (string) $request['status'] ) ),
				'kinds' => array_keys( Prospects::KINDS ),
			)
		);
	}

	/**
	 * Add a prospect by hand.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function add_prospect( WP_REST_Request $request ) {
		$data           = (array) $request->get_json_params();
		$data['source'] = 'manual';
		unset( $data['flags'] );
		$id = Prospects::add( $data );
		return $this->respond( is_wp_error( $id ) ? $id : Prospects::get( $id ) );
	}

	/**
	 * Update a prospect.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_prospect( WP_REST_Request $request ) {
		$data = (array) $request->get_json_params();
		unset( $data['id'], $data['source'], $data['flags'] );
		return $this->respond( Prospects::update( (int) $request['id'], $data ) );
	}

	/**
	 * Delete a prospect.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_prospect( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'deleted' => Prospects::delete( (int) $request['id'] ) ) );
	}

	/**
	 * Start outreach for a prospect.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function start_outreach( WP_REST_Request $request ) {
		$id = Prospects::start_outreach( (int) $request['id'] );
		return $this->respond( is_wp_error( $id ) ? $id : Outreach::get( $id ) );
	}

	/**
	 * Linkable content.
	 *
	 * @return \WP_REST_Response
	 */
	public function linkable() {
		return rest_ensure_response(
			array(
				'pages'  => Prospects::linkable( 20 ),
				'writer' => Writer_Bridge::available(),
			)
		);
	}

	/**
	 * Plan a new linkable page in AI Writer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function write_linkable( WP_REST_Request $request ) {
		$topic = sanitize_text_field( (string) $request['topic'] );
		if ( '' === $topic ) {
			return $this->invalid( __( 'topic', 'chatbot' ) );
		}
		$url = Writer_Bridge::new_content_url( $topic, __( 'A page other sites will want to link to: original data, a complete guide, a checklist or a free tool. Cite sources; include real experience.', 'chatbot' ) );
		return $this->respond( is_wp_error( $url ) ? $url : array( 'url' => $url ) );
	}

	/**
	 * Local citations checklist.
	 *
	 * @return \WP_REST_Response
	 */
	public function citations() {
		return rest_ensure_response( Citations::view() );
	}

	/**
	 * Save a directory entry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_citation( WP_REST_Request $request ) {
		return rest_ensure_response( Citations::save( (string) $request['dir'], (array) $request->get_json_params() ) );
	}

	/**
	 * Outreach list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function outreach( WP_REST_Request $request ) {
		$list          = Outreach::query( sanitize_key( (string) $request['type'] ), sanitize_key( (string) $request['status'] ) );
		$list['types'] = Outreach::TYPES;
		/**
		 * Outreach sending options shown in the app (Pro adds "send").
		 *
		 * @param array $sending can_send, daily_limit, sent_today.
		 */
		$list['sending'] = (array) apply_filters(
			'qcld_wpbot_seo/outreach_sending',
			array(
				'can_send'    => false,
				'daily_limit' => 0,
				'sent_today'  => 0,
			)
		);
		return rest_ensure_response( $list );
	}

	/**
	 * Create a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function add_outreach( WP_REST_Request $request ) {
		$data = (array) $request->get_json_params();
		unset( $data['draft'] );
		$id = Outreach::create( $data );
		return $this->respond( is_wp_error( $id ) ? $id : Outreach::get( $id ) );
	}

	/**
	 * One contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_outreach( WP_REST_Request $request ) {
		$item = Outreach::get( (int) $request['id'] );
		return $item ? rest_ensure_response( $item ) : new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This contact was not found.', 'chatbot' ), array( 'status' => 404 ) );
	}

	/**
	 * Update a contact (fields, status, note, edited draft).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_outreach( WP_REST_Request $request ) {
		$data = (array) $request->get_json_params();
		unset( $data['id'], $data['type'], $data['prospect_id'], $data['sent_count'], $data['last_sent'], $data['owner_id'] );
		return $this->respond( Outreach::update( (int) $request['id'], $data ) );
	}

	/**
	 * Delete a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_outreach( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'deleted' => Outreach::delete( (int) $request['id'] ) ) );
	}

	/**
	 * AI draft.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function draft( WP_REST_Request $request ) {
		$args = array();
		if ( null !== $request['answer'] ) {
			$args['answer'] = (string) $request['answer'];
		}
		if ( null !== $request['idea'] && '' !== (string) $request['idea'] ) {
			$args['idea'] = (int) $request['idea'];
		}
		return $this->respond( Outreach_Drafts::draft( (int) $request['id'], $args ) );
	}

	/**
	 * Record that the owner copied the message or opened it in their email.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function log( WP_REST_Request $request ) {
		$kind = sanitize_key( (string) $request['kind'] );
		if ( ! in_array( $kind, array( 'copied', 'opened' ), true ) || ! Outreach::get( (int) $request['id'] ) ) {
			return $this->invalid( __( 'action', 'chatbot' ) );
		}
		Outreach::log( (int) $request['id'], $kind );
		return rest_ensure_response( Outreach::get( (int) $request['id'] ) );
	}

	/**
	 * Open AI Writer for a guest article or partner content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function write( WP_REST_Request $request ) {
		$item = Outreach::get( (int) $request['id'] );
		if ( ! $item || 'link_request' === $item['type'] ) {
			return $this->invalid( __( 'contact', 'chatbot' ) );
		}
		$ideas = is_array( $item['draft'] ) && ! empty( $item['draft']['ideas'] ) ? $item['draft']['ideas'] : array();
		$index = (int) $request['idea'];
		$topic = isset( $ideas[ $index ] ) ? $ideas[ $index ]['title'] : (string) $request['topic'];
		if ( '' === trim( $topic ) ) {
			return $this->invalid( __( 'topic', 'chatbot' ) );
		}
		$notes = 'guest_post' === $item['type']
			/* translators: %s: publication name or website. */
			? sprintf( __( 'Guest article for %s. Write it fresh (never copied from our site), follow the publication\'s guidelines, use the owner\'s "Make it yours" answers for real experience, and end with a short author bio. At most one or two links to our site, using our brand name or the page title, not keyword-rich anchors.', 'chatbot' ), '' !== $item['name'] ? $item['name'] : $item['website'] )
			/* translators: %s: partner name or website. */
			: sprintf( __( 'Content made with our partner %s. Credit both sides; a link to each other comes naturally where it helps readers.', 'chatbot' ), '' !== $item['name'] ? $item['name'] : $item['website'] );
		$url = Writer_Bridge::new_content_url( $topic, $notes );
		if ( ! is_wp_error( $url ) ) {
			Outreach::log( (int) $request['id'], 'note', '', '', __( 'Opened AI Writer for the article.', 'chatbot' ) );
		}
		return $this->respond( is_wp_error( $url ) ? $url : array( 'url' => $url ) );
	}

	/**
	 * Exchange records.
	 *
	 * @return \WP_REST_Response
	 */
	public function exchanges() {
		return rest_ensure_response( Exchanges::all() );
	}

	/**
	 * Add or update an exchange.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save_exchange( WP_REST_Request $request ) {
		$data = (array) $request->get_json_params();
		unset( $data['checks'], $data['last_checked'], $data['created_at'] );
		$id = Exchanges::save( $data );
		return $this->respond( is_wp_error( $id ) ? $id : Exchanges::get( $id ) );
	}

	/**
	 * Check both sides of an exchange now.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function check_exchange( WP_REST_Request $request ) {
		return $this->respond( Exchanges::check( (int) $request['id'] ) );
	}

	/**
	 * Delete an exchange record.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_exchange( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'deleted' => Exchanges::delete( (int) $request['id'] ) ) );
	}
}
