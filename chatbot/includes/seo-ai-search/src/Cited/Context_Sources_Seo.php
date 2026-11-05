<?php
/**
 * SEO & AI Search sources on WPBot > Business Context ("Data you already
 * have"): Search Console, the Keyword Vault, the business location and the
 * Google Business Profile, each with where to set it up when it is missing.
 * Also gives the shared Competitors source its "Add competitors" link.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Cited;

use QuantumCloud\SeoAiSearch\Local\Gbp;
use QuantumCloud\SeoAiSearch\Local\Locations;
use QuantumCloud\WPBotCore\Connections\Google;

defined( 'ABSPATH' ) || exit;

/**
 * SEO sources for the Business Context.
 */
final class Context_Sources_Seo {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/context_sources', array( __CLASS__, 'sources' ), 10, 2 );
	}

	/**
	 * A screen of SEO & AI Search.
	 *
	 * @param string $tab     Tab.
	 * @param string $section Section.
	 * @return string
	 */
	private static function url( $tab, $section = '' ) {
		return admin_url( 'admin.php?page=wpbot-seo-ai-search&tab=' . $tab . ( '' !== $section ? '&section=' . $section : '' ) );
	}

	/**
	 * Add the SEO sources.
	 *
	 * @param array[] $sources Sources.
	 * @return array[]
	 */
	public static function sources( $sources ) {
		$sources = (array) $sources;
		$item    = static function ( $id, $label, $adds, $ok, $detail, $action_label, $url ) {
			return array(
				'id'     => $id,
				'label'  => $label,
				'adds'   => $adds,
				'status' => $ok ? 'ok' : 'missing',
				'detail' => $detail,
				'action' => $ok ? null : array(
					'label' => $action_label,
					'url'   => $url,
				),
			);
		};

		// The shared competitor list is edited in SEO & AI Search.
		foreach ( $sources as $index => $source ) {
			if ( is_array( $source ) && isset( $source['id'] ) && 'competitors' === $source['id'] ) {
				$sources[ $index ]['action'] = array(
					'label' => 'ok' === $source['status'] ? __( 'Edit competitors', 'chatbot' ) : __( 'Add competitors', 'chatbot' ),
					'url'   => self::url( 'settings', 'competitors' ),
				);
			}
		}

		$gsc       = class_exists( Google::class ) && Google::connected();
		$sources[] = $item(
			'search_console',
			__( 'Google Search Console', 'chatbot' ),
			__( 'The searches that already bring people to your site, so tools write about what customers look for.', 'chatbot' ),
			$gsc,
			$gsc ? __( 'Connected', 'chatbot' ) : __( 'Not connected', 'chatbot' ),
			__( 'Connect Search Console', 'chatbot' ),
			self::url( 'settings', 'connections' )
		);

		$vault     = function_exists( 'qcld_wpbot_core_vault_query' ) ? count( (array) qcld_wpbot_core_vault_query( array( 'limit' => 500 ) ) ) : 0;
		$sources[] = $item(
			'keyword_vault',
			__( 'Keyword Vault', 'chatbot' ),
			__( 'The searches you chose to target.', 'chatbot' ),
			$vault > 0,
			/* translators: %d: keywords. */
			$vault > 0 ? sprintf( _n( '%d keyword', '%d keywords', $vault, 'chatbot' ), $vault ) : __( 'Empty', 'chatbot' ),
			__( 'Research keywords', 'chatbot' ),
			self::url( 'keywords' )
		);

		$main      = Locations::main();
		$located   = is_array( $main ) && '' !== trim( (string) $main['city'] );
		$sources[] = $item(
			'location',
			__( 'Business location', 'chatbot' ),
			__( 'Your address, opening hours and the areas you serve.', 'chatbot' ),
			$located,
			$located ? trim( $main['name'] . ', ' . $main['city'], ', ' ) : __( 'Not added', 'chatbot' ),
			__( 'Add your location', 'chatbot' ),
			self::url( 'local' )
		);

		if ( Gbp::available() ) {
			$link      = Gbp::link();
			$sources[] = $item(
				'gbp',
				__( 'Google Business Profile', 'chatbot' ),
				__( 'Your Google listing and reviews.', 'chatbot' ),
				(bool) $link,
				$link ? (string) $link['title'] : __( 'Not linked', 'chatbot' ),
				__( 'Connect Business Profile', 'chatbot' ),
				self::url( 'local' )
			);
		}
		return $sources;
	}
}
