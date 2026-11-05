<?php
/**
 * WPBot SEO & AI Search core module.
 *
 * Bundled by host plugins at includes/seo-ai-search/ (WPBot Pro now, WPBot
 * Free later, the same files). Needs WPBot Core (includes/wpbot-core/)
 * loaded first. The loader boots only the newest bundled copy.
 *
 * @package QuantumCloud\SeoAiSearch
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

require_once __DIR__ . '/loader.php';

qcld_wpbot_seo_register_version( '1.3.0', __DIR__ );
