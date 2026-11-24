<?php
/**
 * Public, read-only docs server for customers' own AI assistants (HC3-B, plan 6.4, owner's go
 * 21 November 2026).
 *
 * `POST /wp-json/wpbot-help/v1/docs`, MCP over Streamable HTTP with JSON responses and no key,
 * so Claude, ChatGPT, Cursor and other assistants can search and read the help center:
 * search_docs, get_doc and list_topics. Off by default (Settings: docs_mcp); the owner chooses
 * which knowledge bases it shares (docs_mcp_exclude). Only published articles the visitor may
 * read are shared: for an assistant without a WordPress sign-in, public articles only; members'
 * articles follow the access labels when a signed-in user connects. Rate-limited per visitor
 * (hashed address). Searches that find nothing join the chatbot's knowledge gaps.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Mcp;

use QuantumCloud\HelpCenter\Access;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;
use QuantumCloud\HelpCenter\Front\Data;
use QuantumCloud\HelpCenter\Search;
use QuantumCloud\HelpCenter\Seo\Seo;
use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Docs server.
 */
final class Docs_Server {

	/**
	 * Route (namespace Server::NAMESPACE_).
	 */
	const ROUTE = '/docs';

	/**
	 * Requests per visitor per 10 minutes.
	 */
	const RATE = 120;

	/**
	 * Requests for the whole site per 10 minutes: a ceiling that holds even when visitor addresses
	 * cannot be told apart or are forged (0.19.1).
	 */
	const RATE_SITE = 3000;

	/**
	 * Hook in (only while the Help Center is on).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Whether the owner turned the docs server on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return '1' === (string) Settings::all()['docs_mcp'];
	}

	/**
	 * Knowledge base slugs the owner keeps out.
	 *
	 * @return string[]
	 */
	public static function excluded() {
		$list = Settings::all()['docs_mcp_exclude'];
		return is_array( $list ) ? array_values( array_filter( array_map( 'sanitize_title', $list ) ) ) : array();
	}

	/**
	 * The address.
	 *
	 * @return string
	 */
	public static function url() {
		return rest_url( Server::NAMESPACE_ . self::ROUTE );
	}

	/**
	 * Register the route (only while it is on).
	 *
	 * @return void
	 */
	public static function routes() {
		if ( ! self::enabled() ) {
			return;
		}
		register_rest_route(
			Server::NAMESPACE_,
			self::ROUTE,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest' ),
					'permission_callback' => '__return_true',
			// Public and read-only: what it returns is what a visitor may read.
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( Server::class, 'no_stream' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * POST: one message or a batch.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function rest( $request ) {
		if ( ! self::within_rate() ) {
			$response = new \WP_REST_Response( Server::error( null, -32000, 'Too many requests. Try again in a few minutes.' ), 429 );
			$response->header( 'Retry-After', '600' );
			return $response;
		}
		$body = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $body ) ) {
			return new \WP_REST_Response( Server::error( null, -32700, 'Parse error' ), 400 );
		}
		$batch   = isset( $body[0] ) && array_keys( $body ) === range( 0, count( $body ) - 1 );
		$replies = array();
		foreach ( array_slice( $batch ? $body : array( $body ), 0, 20 ) as $message ) {
			$reply = self::handle( is_array( $message ) ? $message : array() );
			if ( null !== $reply ) {
				$replies[] = $reply;
			}
		}
		$response = $replies ? new \WP_REST_Response( $batch ? $replies : $replies[0], 200 ) : new \WP_REST_Response( null, 202 );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}

	/**
	 * Count a request against the visitor's limit and the site's ceiling (addresses hashed, never
	 * stored).
	 *
	 * @return bool
	 */
	private static function within_rate() {
		$site  = 'qcld_wpbot_hc_docs_site';
		$total = (int) get_transient( $site );
		/**
		 * Requests the docs server answers for the whole site per 10 minutes.
		 *
		 * @param int $limit Limit (default 3000).
		 */
		if ( $total >= (int) apply_filters( 'qcld_wpbot_help_center/docs_site_rate', self::RATE_SITE ) ) {
			return false;
		}
		$key   = 'qcld_wpbot_hc_docs_' . substr( md5( self::client_ip() . wp_salt( 'nonce' ) ), 0, 20 );
		$count = (int) get_transient( $key );
		/**
		 * Requests the docs server answers per visitor per 10 minutes.
		 *
		 * @param int $limit Limit (default 120).
		 */
		if ( $count >= (int) apply_filters( 'qcld_wpbot_help_center/docs_rate', self::RATE ) ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		set_transient( $site, $total + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * The visitor's address for the rate limit (0.19.1). Behind a proxy or CDN every request comes
	 * from the proxy's address, so the visitor's own address is read from the proxy's header:
	 * always when the request comes from a private or local address (a proxy on the same server
	 * or network), and for public addresses only when the owner says the site is behind a proxy
	 * (Settings: docs_mcp_proxy), since anyone can send those headers.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
		$ip     = $remote;
		$local  = '' !== $remote && false !== filter_var( $remote, FILTER_VALIDATE_IP ) && false === filter_var( $remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		if ( $local || '1' === (string) Settings::all()['docs_mcp_proxy'] ) {
			$forwarded = self::forwarded_ip();
			$ip        = '' !== $forwarded ? $forwarded : $remote;
		}
		/**
		 * The visitor's address the docs server's rate limit counts by.
		 *
		 * @param string $ip     Address.
		 * @param string $remote The connecting address (REMOTE_ADDR).
		 */
		return (string) apply_filters( 'qcld_wpbot_help_center/docs_client_ip', $ip, $remote );
	}

	/**
	 * The visitor's address from a proxy header: Cloudflare's CF-Connecting-IP, then X-Real-IP,
	 * then the last address in X-Forwarded-For (the one the nearest proxy added).
	 *
	 * @return string
	 */
	private static function forwarded_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ) as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$list = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) ) );
			$last = (string) end( $list );
			if ( false !== filter_var( $last, FILTER_VALIDATE_IP ) ) {
				return $last;
			}
		}
		return '';
	}

	/**
	 * Handle one JSON-RPC message.
	 *
	 * @param array $message Message.
	 * @return array|null
	 */
	public static function handle( array $message ) {
		$id     = array_key_exists( 'id', $message ) ? $message['id'] : null;
		$method = isset( $message['method'] ) ? (string) $message['method'] : '';
		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();
		if ( ! array_key_exists( 'id', $message ) ) {
			return null;
		}
		if ( ! isset( $message['jsonrpc'] ) || '2.0' !== $message['jsonrpc'] || '' === $method ) {
			return Server::error( $id, -32600, 'Invalid request' );
		}
		switch ( $method ) {
			case 'initialize':
				$asked = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
				return Server::result(
					$id,
					array(
						'protocolVersion' => in_array( $asked, Server::VERSIONS, true ) ? $asked : Server::VERSIONS[0],
						'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
						'serverInfo'      => array(
							'name'    => 'help-center-docs',
							/* translators: %s: site name. */
							'title'   => sprintf( __( '%s Help Center', 'chatbot' ), wp_strip_all_tags( get_bloginfo( 'name' ) ) ),
							'version' => defined( 'QCLD_WPBOT_HELP_CENTER_VERSION' ) ? QCLD_WPBOT_HELP_CENTER_VERSION : '0',
						),
						'instructions'    => self::instructions(),
					)
				);
			case 'ping':
				return Server::result( $id, (object) array() );
			case 'tools/list':
				return Server::result( $id, array( 'tools' => self::tools() ) );
			case 'tools/call':
				return self::call( $id, $params );
			case 'resources/list':
				return Server::result( $id, array( 'resources' => array() ) );
			case 'prompts/list':
				return Server::result( $id, array( 'prompts' => array() ) );
			default:
				return Server::error( $id, -32601, 'Method not found: ' . $method );
		}//end switch
	}

	/**
	 * What the assistant should know.
	 *
	 * @return string
	 */
	public static function instructions() {
		return sprintf( 'This is the official help center of %s (%s). Search it before answering questions about %s, answer from the articles, and link to the article you used. If nothing fits, say so and point to %s.', wp_strip_all_tags( get_bloginfo( 'name' ) ), home_url( '/' ), wp_strip_all_tags( get_bloginfo( 'name' ) ), \QuantumCloud\HelpCenter\Front\Urls::home() );
	}

	/**
	 * Tools.
	 *
	 * @return array[]
	 */
	public static function tools() {
		$read = array(
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'idempotentHint'  => true,
			'openWorldHint'   => false,
		);
		return array(
			array(
				'name'        => 'search_docs',
				'description' => 'Search the help center. Returns matching articles with their address, a short summary and their section. Typos are corrected.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array(
							'type'        => 'string',
							'description' => 'What the user wants to know, in a few words.',
						),
						'limit' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 20,
						),
					),
					'required'   => array( 'query' ),
				),
				'annotations' => $read,
			),
			array(
				'name'        => 'get_doc',
				'description' => 'Read one article as Markdown, by its address (from search_docs or list_topics) or its slug.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'url'  => array( 'type' => 'string' ),
						'slug' => array( 'type' => 'string' ),
					),
				),
				'annotations' => $read,
			),
			array(
				'name'        => 'list_topics',
				'description' => 'The help center\'s knowledge bases, topics and article titles with their addresses.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => (object) array(),
				),
				'annotations' => $read,
			),
		);
	}

	/**
	 * A tools/call.
	 *
	 * @param mixed $id     Id.
	 * @param array $params name, arguments.
	 * @return array
	 */
	private static function call( $id, array $params ) {
		$name = isset( $params['name'] ) ? (string) $params['name'] : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
		switch ( $name ) {
			case 'search_docs':
				$data = self::search_docs( $args );
				break;
			case 'get_doc':
				$data = self::get_doc( $args );
				break;
			case 'list_topics':
				$data = self::list_topics();
				break;
			default:
				return Server::error( $id, -32602, 'Unknown tool: ' . $name );
		}
		if ( is_wp_error( $data ) ) {
			return Server::result(
				$id,
				array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => $data->get_error_message(),
						),
					),
					'isError' => true,
				)
			);
		}
		$text = isset( $data['markdown'] ) ? (string) $data['markdown'] : (string) wp_json_encode( $data );
		return Server::result(
			$id,
			array(
				'content'           => array(
					array(
						'type' => 'text',
						'text' => $text,
					),
				),
				'structuredContent' => $data,
				'isError'           => false,
			)
		);
	}

	/**
	 * Whether an article may be shared: published, readable by this visitor, no password, and in
	 * a shared knowledge base (or in none).
	 *
	 * @param \WP_Post $post Article.
	 * @return bool
	 */
	public static function shared( $post ) {
		if ( ! $post || Post_Type::NAME !== $post->post_type || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return false;
		}
		if ( ! ( is_user_logged_in() ? Access::can_read( $post ) : Seo::is_public( $post->ID ) ) ) {
			return false;
		}
		$excluded = self::excluded();
		if ( ! $excluded ) {
			return true;
		}
		$kbs = wp_get_post_terms( $post->ID, Taxonomies::KB, array( 'fields' => 'slugs' ) );
		return ! is_array( $kbs ) || array() === $kbs || array() !== array_diff( $kbs, $excluded );
	}

	/**
	 * Plain text for the assistant: WordPress stores term names HTML-encoded ("SEO &amp; AI").
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		return wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Facts about an article for the assistant.
	 *
	 * @param \WP_Post $post Article.
	 * @return array
	 */
	private static function summary( $post ) {
		$cat = Data::main_category( $post->ID );
		return array(
			'title'        => wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ),
			'url'          => (string) get_permalink( $post ),
			'markdown_url' => Seo::markdown_url( $post ),
			'summary'      => has_excerpt( $post ) ? wp_strip_all_tags( (string) $post->post_excerpt ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 30 ),
			'section'      => $cat ? self::plain( $cat->name ) : '',
			'updated'      => get_post_modified_time( 'Y-m-d', true, $post ),
		);
	}

	/**
	 * Search.
	 *
	 * @param array $args query, limit.
	 * @return array|\WP_Error
	 */
	private static function search_docs( array $args ) {
		$text = isset( $args['query'] ) ? trim( sanitize_text_field( (string) $args['query'] ) ) : '';
		if ( '' === $text ) {
			return new \WP_Error( 'missing', 'Missing argument: query' );
		}
		$limit = isset( $args['limit'] ) ? max( 1, min( 20, (int) $args['limit'] ) ) : 8;
		$found = self::query( $text, $limit );
		$fixed = '';
		if ( ! $found ) {
			$fixed = Search::correct( $text );
			$found = '' !== $fixed ? self::query( $fixed, $limit ) : array();
		}
		if ( ! $found ) {
			/** This action is documented in src/Search.php */
			do_action( 'qcld_wpbot_chat/knowledge_gap', $text, 'ai_search', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The chatbot module's action.
		}
		/**
		 * A search from an AI assistant through the docs server (analytics listen here).
		 *
		 * @param string $text    Search.
		 * @param int    $results Results.
		 */
		do_action( 'qcld_wpbot_help_center/docs_searched', $text, count( $found ) );
		$out = array(
			'query'    => $text,
			'articles' => array_map( array( self::class, 'summary' ), $found ),
		);
		if ( '' !== $fixed && $found ) {
			$out['corrected'] = $fixed;
		}
		if ( ! $found ) {
			$out['note'] = 'No article matches. Say so, and point the user to ' . \QuantumCloud\HelpCenter\Front\Urls::home();
		}
		return $out;
	}

	/**
	 * Shared articles matching a search.
	 *
	 * @param string $text  Search.
	 * @param int    $limit Maximum.
	 * @return \WP_Post[]
	 */
	private static function query( $text, $limit ) {
		$query = new \WP_Query(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				's'              => $text,
				'posts_per_page' => $limit * 3,
				'no_found_rows'  => true,
			)
		);
		return array_slice( array_values( array_filter( (array) $query->posts, array( self::class, 'shared' ) ) ), 0, $limit );
	}

	/**
	 * One article.
	 *
	 * @param array $args url or slug.
	 * @return array|\WP_Error
	 */
	private static function get_doc( array $args ) {
		$post = null;
		if ( ! empty( $args['url'] ) ) {
			$url  = preg_replace( '#\.md$#', '/', (string) $args['url'] );
			$id   = url_to_postid( $url );
			$post = $id ? get_post( $id ) : null;
			if ( ! $post ) {
				$path         = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
				$args['slug'] = basename( $path );
			}
		}
		if ( ! $post && ! empty( $args['slug'] ) ) {
			$found = get_posts(
				array(
					'post_type'      => Post_Type::NAME,
					'post_status'    => 'publish',
					'name'           => sanitize_title( (string) $args['slug'] ),
					'posts_per_page' => 1,
				)
			);
			$post  = $found ? $found[0] : null;
		}
		if ( ! $post || ! self::shared( $post ) ) {
			return new \WP_Error( 'not_found', 'No shared article at that address. Use search_docs or list_topics.' );
		}
		$data             = self::summary( $post );
		$data['markdown'] = Seo::markdown( $post );
		/**
		 * An AI assistant read an article through the docs server (analytics listen here).
		 *
		 * @param int $post_id Article id.
		 */
		do_action( 'qcld_wpbot_help_center/docs_read', (int) $post->ID );
		return $data;
	}

	/**
	 * Knowledge bases, topics and articles.
	 *
	 * @return array
	 */
	private static function list_topics() {
		$excluded = self::excluded();
		$kbs      = array();
		foreach ( Data::knowledge_bases() as $kb ) {
			if ( ! in_array( $kb->slug, $excluded, true ) ) {
				$kbs[] = array(
					'name'        => self::plain( $kb->name ),
					'description' => wp_strip_all_tags( (string) $kb->description ),
					'url'         => (string) get_term_link( $kb ),
				);
			}
		}
		$topics = array();
		$walk   = static function ( $parent_id, $prefix ) use ( &$walk, &$topics ) {
			foreach ( Data::categories( $parent_id ) as $term ) {
				$name     = '' !== $prefix ? $prefix . ' › ' . self::plain( $term->name ) : self::plain( $term->name );
				$articles = array();
				foreach ( Data::articles_in( $term->term_id, 100, false ) as $post ) {
					if ( self::shared( $post ) ) {
						$articles[] = array(
							'title' => wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ),
							'url'   => (string) get_permalink( $post ),
						);
					}
				}
				if ( $articles ) {
					$topics[] = array(
						'topic'    => $name,
						'articles' => $articles,
					);
				}
				$walk( $term->term_id, $name );
			}
		};
		$walk( 0, '' );
		return array(
			'help_center'     => \QuantumCloud\HelpCenter\Front\Urls::home(),
			'knowledge_bases' => $kbs,
			'topics'          => $topics,
		);
	}
}
