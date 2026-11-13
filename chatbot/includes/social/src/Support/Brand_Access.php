<?php
/**
 * Brand access: which brands the current person may see and use. The Master
 * module answers through qcld_wpbot_social/user_brands (brand members);
 * without it, or with no logged-in person (cron), everything is visible.
 * Accounts and posts without a brand are visible to everyone with access.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Brand access.
 */
final class Brand_Access {

	/**
	 * Brand ids the current person may use, or null for all.
	 *
	 * @return int[]|null
	 */
	public static function allowed() {
		$user = get_current_user_id();
		if ( ! $user ) {
			return null;
		}
		/**
		 * Brands a person may see and use (null = all brands).
		 *
		 * @param int[]|null $brands Brand ids.
		 * @param int        $user   User id.
		 */
		$brands = apply_filters( 'qcld_wpbot_social/user_brands', null, $user );
		return null === $brands ? null : array_values( array_map( 'intval', (array) $brands ) );
	}

	/**
	 * Whether the current person may use something of this brand.
	 *
	 * @param int $brand_id Brand id (0 = no brand).
	 * @return bool
	 */
	public static function can( $brand_id ) {
		$allowed = self::allowed();
		return null === $allowed || 0 === (int) $brand_id || in_array( (int) $brand_id, $allowed, true );
	}
}
