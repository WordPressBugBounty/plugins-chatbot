<?php
/**
 * Keyword research and the Keyword Vault (S14): /research/* and /vault/*.
 *
 * View reads; Use runs research and saves or edits Vault keywords
 * (assigning a page also needs edit rights on that page).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Keywords\Keyword_Ideas;
use QuantumCloud\SeoAiSearch\Research\Research;
use QuantumCloud\SeoAiSearch\Research\Research_Store;
use QuantumCloud\SeoAiSearch\Research\Research_View;
use QuantumCloud\SeoAiSearch\Research\Vault_Service;
use QuantumCloud\SeoAiSearch\Writer\Writer_Bridge;
use QuantumCloud\WPBotCore\Vault\Vault;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Research and Vault routes.
 */
final class Research_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/research/status'          => array( WP_REST_Server::READABLE, 'status', 'can_view' ),
			'/research/history'         => array( WP_REST_Server::READABLE, 'history', 'can_view' ),
			'/research/estimate'        => array( WP_REST_Server::CREATABLE, 'estimate', 'can_use' ),
			'/research/run'             => array( WP_REST_Server::CREATABLE, 'run', 'can_use' ),
			'/research/(?P<id>\d+)'     => array( WP_REST_Server::READABLE, 'get_run', 'can_view' ),
			'/research/save'            => array( WP_REST_Server::CREATABLE, 'save', 'can_use' ),
			'/research/ideas'           => array( WP_REST_Server::CREATABLE, 'ideas', 'can_use' ),
			'/research/brief'           => array( WP_REST_Server::CREATABLE, 'brief', 'can_use' ),
			'/vault'                    => array( WP_REST_Server::READABLE, 'vault', 'can_view' ),
			'/vault/add'                => array( WP_REST_Server::CREATABLE, 'vault_add', 'can_use' ),
			'/vault/(?P<id>\d+)'        => array( WP_REST_Server::CREATABLE, 'vault_update', 'can_use' ),
			'/vault/(?P<id>\d+)/delete' => array( WP_REST_Server::CREATABLE, 'vault_delete', 'can_use' ),
			'/vault/(?P<id>\d+)/assign' => array( WP_REST_Server::CREATABLE, 'vault_assign', 'can_use' ),
			'/vault/(?P<id>\d+)/write'  => array( WP_REST_Server::CREATABLE, 'vault_write', 'can_use' ),
			'/vault/bulk'               => array( WP_REST_Server::CREATABLE, 'vault_bulk', 'can_use' ),
			'/vault/ideas'              => array( WP_REST_Server::CREATABLE, 'vault_ideas', 'can_use' ),
			'/vault/import'             => array( WP_REST_Server::CREATABLE, 'vault_import', 'can_use' ),
			'/vault/export'             => array( WP_REST_Server::READABLE, 'vault_export', 'can_view' ),
			'/vault/pages'              => array( WP_REST_Server::READABLE, 'pages', 'can_use' ),
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
	 * What the Research tab needs to know.
	 *
	 * @return \WP_REST_Response
	 */
	public function status() {
		$competitors = array();
		/** This filter is documented in src/Links/Prospects.php. */
		foreach ( (array) apply_filters( 'qcld_wpbot_seo/competitor_hosts', array() ) as $host ) {
			$competitors[] = (string) $host;
		}
		if ( class_exists( '\QuantumCloud\AiWriterPro\Competitors\Competitors' ) ) {
			foreach ( (array) \QuantumCloud\AiWriterPro\Competitors\Competitors::all() as $competitor ) {
				$host = (string) wp_parse_url( isset( $competitor['url'] ) ? (string) $competitor['url'] : '', PHP_URL_HOST );
				if ( '' !== $host ) {
					$competitors[] = preg_replace( '#^www\.#', '', strtolower( $host ) );
				}
			}
		}
		// R2: one shared list everywhere once it has entries.
		$shared = \QuantumCloud\SeoAiSearch\Research\Competitor_List::domains();
		if ( array() !== $shared ) {
			$competitors = $shared;
		}
		$provider = Research::has_provider() ? qcld_wpbot_core_keyword_provider() : null;
		return rest_ensure_response(
			array(
				'vault'       => Vault_Service::available(),
				'provider'    => $provider,
				'web_search'  => Research::web_search_available(),
				'place'       => Research::place( array() ),
				'competitors' => array_values( array_unique( array_filter( $competitors ) ) ),
				'writer'      => Writer_Bridge::available(),
				'ideas'       => class_exists( '\QuantumCloud\AiWriter\Topics\Ideas' ),
				'own_domain'  => Research::own_domain(),
			)
		);
	}

	/**
	 * Research history.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function history( WP_REST_Request $request ) {
		return rest_ensure_response( Research_Store::history( 30, sanitize_key( (string) $request['kind'] ) ) );
	}

	/**
	 * Cost estimate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function estimate( WP_REST_Request $request ) {
		return $this->respond( Research::estimate( sanitize_key( (string) $request['kind'] ), $this->run_args( $request ) ) );
	}

	/**
	 * Run research.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		$kind = sanitize_key( (string) $request['kind'] );
		$args = $this->run_args( $request );
		if ( ! rest_sanitize_boolean( $request['confirmed'] ) ) {
			$estimate = Research::estimate( $kind, $args );
			if ( ! is_wp_error( $estimate ) && $estimate['confirm'] ) {
				// Above the owner's limit: the screen asks first, then sends confirmed.
				return new WP_Error(
					'qcld_wpbot_seo_research_confirm',
					__( 'This research costs more than your limit. Confirm to run it.', 'chatbot' ),
					array(
						'status'   => 409,
						'estimate' => $estimate,
					)
				);
			}
		}
		$run = Research::run( $kind, $args );
		return $this->respond( is_wp_error( $run ) ? $run : Research_View::decorate( $run ) );
	}

	/**
	 * One run (reopen from the history, or poll a gap job).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_run( WP_REST_Request $request ) {
		$run = Research_Store::get( (int) $request['id'] );
		if ( ! $run ) {
			return new WP_Error( 'qcld_wpbot_seo_research_missing', __( 'This research run is no longer saved.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( Research_View::decorate( $run ) );
	}

	/**
	 * Save keywords to the Vault.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		if ( ! Vault_Service::available() ) {
			return $this->no_vault();
		}
		$items = $request->get_param( 'items' );
		if ( ! is_array( $items ) || array() === $items ) {
			return new WP_Error( 'qcld_wpbot_seo_vault_items', __( 'Choose keywords to save.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response(
			Vault_Service::save_items(
				$items,
				array(
					'source'   => sanitize_key( (string) $request['source'] ),
					'language' => (string) $request['language'],
					'country'  => (string) $request['country'],
				)
			)
		);
	}

	/**
	 * Add keywords to AI Writer's Topic Finder.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function ideas( WP_REST_Request $request ) {
		$added = 0;
		$last  = null;
		foreach ( array_slice( (array) $request->get_param( 'keywords' ), 0, 100 ) as $keyword ) {
			$last = Keyword_Ideas::add_to_topic_finder( sanitize_text_field( (string) $keyword ) );
			if ( is_wp_error( $last ) ) {
				return $this->respond( $last );
			}
			++$added;
		}
		return rest_ensure_response(
			array(
				'added' => $added,
				'url'   => is_array( $last ) && isset( $last['url'] ) ? $last['url'] : '',
			)
		);
	}

	/**
	 * Open AI Writer with a pre-filled brief.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function brief( WP_REST_Request $request ) {
		$keyword = sanitize_text_field( (string) $request['keyword'] );
		if ( '' === $keyword ) {
			return new WP_Error( 'qcld_wpbot_seo_brief_keyword', __( 'Choose a keyword.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$url = Vault_Service::brief_url(
			$keyword,
			array(
				'intent'  => sanitize_key( (string) $request['intent'] ),
				'context' => array(
					'supporting'       => array_map( 'sanitize_text_field', array_map( 'strval', (array) $request->get_param( 'supporting' ) ) ),
					'questions'        => array_map( 'sanitize_text_field', array_map( 'strval', (array) $request->get_param( 'questions' ) ) ),
					'competitor_notes' => sanitize_textarea_field( (string) $request['competitor_notes'] ),
					'reason'           => sanitize_text_field( (string) $request['reason'] ),
				),
			)
		);
		return $this->respond( is_wp_error( $url ) ? $url : array( 'url' => $url ) );
	}

	/**
	 * Vault list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function vault( WP_REST_Request $request ) {
		if ( ! Vault_Service::available() ) {
			return $this->no_vault();
		}
		$per_page = max( 1, min( 200, (int) ( $request['per_page'] ? $request['per_page'] : 50 ) ) );
		$page     = max( 1, (int) $request['page'] );
		return rest_ensure_response(
			Vault_Service::listing(
				array_merge(
					$this->vault_filters( $request ),
					array(
						'limit'  => $per_page,
						'offset' => ( $page - 1 ) * $per_page,
					)
				)
			)
		);
	}

	/**
	 * Add a keyword by hand.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function vault_add( WP_REST_Request $request ) {
		if ( ! Vault_Service::available() ) {
			return $this->no_vault();
		}
		$place = Research::place(
			array(
				'language' => (string) $request['language'],
				'country'  => (string) $request['country'],
			)
		);
		$id    = Vault::add(
			array(
				'keyword'  => (string) $request['keyword'],
				'language' => $place['language'],
				'country'  => $place['country'],
				'notes'    => (string) $request['notes'],
				'source'   => 'manual',
				'module'   => 'seo',
			)
		);
		return $this->respond( is_wp_error( $id ) ? $id : array( 'id' => $id ) );
	}

	/**
	 * Change status, intent, topic or notes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function vault_update( WP_REST_Request $request ) {
		$fields = array();
		foreach ( array( 'status', 'intent', 'notes' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$fields[ $field ] = (string) $request->get_param( $field );
			}
		}
		if ( null !== $request->get_param( 'topic' ) ) {
			$name               = sanitize_text_field( (string) $request['topic'] );
			$fields['topic_id'] = '' === $name ? 0 : \QuantumCloud\WPBotCore\Map\Topic_Map::ensure( $name, 0, 'manual' );
		}
		$done = Vault::update( (int) $request['id'], $fields );
		return $this->respond( is_wp_error( $done ) ? $done : array( 'item' => Vault::get( (int) $request['id'] ) ) );
	}

	/**
	 * Delete a keyword.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function vault_delete( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'deleted' => Vault::delete( (int) $request['id'] ) ) );
	}

	/**
	 * Assign to a page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function vault_assign( WP_REST_Request $request ) {
		$done = Vault_Service::assign( (int) $request['id'], absint( $request['post_id'] ), 'supporting' === $request['role'] ? 'supporting' : 'main' );
		return $this->respond( is_wp_error( $done ) ? $done : array( 'item' => Vault::get( (int) $request['id'] ) ) );
	}

	/**
	 * AI Writer brief for a Vault keyword.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function vault_write( WP_REST_Request $request ) {
		$url = Vault_Service::write_url( (int) $request['id'] );
		return $this->respond( is_wp_error( $url ) ? $url : array( 'url' => $url ) );
	}

	/**
	 * Bulk actions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function vault_bulk( WP_REST_Request $request ) {
		return $this->respond( Vault_Service::bulk( (array) $request->get_param( 'ids' ), sanitize_key( (string) $request['action'] ), $request->get_param( 'value' ) ) );
	}

	/**
	 * Add Vault keywords to AI Writer's ideas.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function vault_ideas( WP_REST_Request $request ) {
		return $this->respond( Vault_Service::to_ideas( (array) $request->get_param( 'ids' ) ) );
	}

	/**
	 * CSV import (text sent by the browser; 2 MB and 5,000 rows at most).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function vault_import( WP_REST_Request $request ) {
		if ( ! Vault_Service::available() ) {
			return $this->no_vault();
		}
		return $this->respond( Vault_Service::import_csv( (string) $request->get_param( 'csv' ) ) );
	}

	/**
	 * CSV export (text; the browser saves it).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function vault_export( WP_REST_Request $request ) {
		if ( ! Vault_Service::available() ) {
			return $this->no_vault();
		}
		return rest_ensure_response(
			array(
				'filename' => 'keyword-vault-' . gmdate( 'Y-m-d' ) . '.csv',
				'csv'      => Vault_Service::export_csv( $this->vault_filters( $request ) ),
			)
		);
	}

	/**
	 * Pages the user can edit, to assign a keyword to.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function pages( WP_REST_Request $request ) {
		$query = new \WP_Query(
			array(
				'post_type'      => array_values( array_intersect( get_post_types( array( 'public' => true ) ), array( 'post', 'page', 'product' ) ) ),
				'post_status'    => array( 'publish', 'draft', 'future' ),
				's'              => sanitize_text_field( (string) $request['search'] ),
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$out   = array();
		foreach ( $query->posts as $post_id ) {
			if ( current_user_can( 'edit_post', $post_id ) ) {
				$out[] = array(
					'id'    => (int) $post_id,
					'title' => get_the_title( $post_id ),
					'type'  => get_post_type( $post_id ),
				);
			}
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Research arguments from a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private function run_args( WP_REST_Request $request ) {
		return array(
			'seed'        => (string) $request['seed'],
			'post_id'     => absint( $request['post_id'] ),
			'language'    => (string) $request['language'],
			'country'     => (string) $request['country'],
			'competitors' => $request->get_param( 'competitors' ),
			'refresh'     => (bool) $request['refresh'],
		);
	}

	/**
	 * Vault filters from a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private function vault_filters( WP_REST_Request $request ) {
		$filters = array();
		foreach ( array( 'status', 'intent', 'source', 'search', 'orderby', 'order', 'trend', 'competition', 'min_volume' ) as $field ) {
			if ( '' !== (string) $request[ $field ] ) {
				$filters[ $field ] = (string) $request[ $field ];
			}
		}
		if ( $request['topic_id'] ) {
			$filters['topic_id'] = absint( $request['topic_id'] );
		}
		if ( 'archived' !== (string) $request['status'] && '' === (string) $request['status'] ) {
			$filters['status'] = array( 'researched', 'targeted', 'ranking' );
		}
		return $filters;
	}

	/**
	 * Error for an older WPBot Core without the Vault.
	 *
	 * @return WP_Error
	 */
	private function no_vault() {
		return new WP_Error( 'qcld_wpbot_seo_no_vault', __( 'The Keyword Vault needs a newer WPBot Core. Update the plugin.', 'chatbot' ), array( 'status' => 400 ) );
	}
}
