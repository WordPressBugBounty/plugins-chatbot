<?php
/**
 * Function qcld_wpbot_seo_get_page_data(): numbers for one page.
 *
 * Later milestones fill main_query, open_issues, audit_score and
 * ai_readiness through the qcld_wpbot_seo/page_data filter.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Api;

use QuantumCloud\SeoAiSearch\Google\Data_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Builds page data.
 */
final class Page_Data {

	/**
	 * Data for one post.
	 *
	 * @param int $post_id Post id.
	 * @return array|null
	 */
	public static function get( $post_id ) {
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return null;
		}
		$period = Period::last( 28 );
		$url_id = Data_Store::url_for_post( $post_id );
		$totals = $url_id ? Data_Store::totals( $period['start'], $period['end'], $url_id, 0 ) : Data_Store::shape( array() );
		$data   = array_merge(
			$totals,
			array(
				'main_query'   => '',
				'open_issues'  => 0,
				'audit_score'  => null,
				'ai_readiness' => null,
				'period'       => array(
					'start' => $period['start'],
					'end'   => $period['end'],
				),
			)
		);
		/**
		 * Page data for other modules.
		 *
		 * @param array $data    Data.
		 * @param int   $post_id Post id.
		 */
		return (array) apply_filters( 'qcld_wpbot_seo/page_data', $data, $post_id );
	}
}
