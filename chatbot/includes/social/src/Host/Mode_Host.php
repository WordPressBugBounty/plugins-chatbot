<?php
/**
 * Optional host capability: the host states how Social runs on it.
 *
 * A host that implements this interface decides the mode itself (WPBot Free
 * reports "free"; a host that knows its license reports "master" or "off").
 * Hosts that don't implement it keep the original rule: Social runs only
 * when get_license() is "master" (build-brief A1). Modules never check which
 * edition is installed; they read the mode from the host.
 *
 * @package wpbot-social
 */

namespace QuantumCloud\Social\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Host that reports Social's run mode.
 */
interface Mode_Host {

	/**
	 * How Social runs on this host.
	 *
	 * - "free": the core features run (no Master module expected).
	 * - "master": the core features run and the Master module may start.
	 * - "off": Social is paused; data is kept, menus, REST and jobs are off.
	 *
	 * Any other value counts as "off".
	 *
	 * @return string
	 */
	public function get_social_mode();
}
