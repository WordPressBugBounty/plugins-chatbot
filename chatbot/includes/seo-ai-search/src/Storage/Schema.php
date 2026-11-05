<?php
/**
 * Module tables: forward-only, signature-checked, safe to run twice.
 *
 * Extensions (the Pro module) add tables through qcld_wpbot_seo/schema_tables.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the module's tables.
 */
final class Schema {

	/**
	 * Bump when a definition changes.
	 */
	const VERSION = '1';

	/**
	 * Option holding the installed signature.
	 */
	const OPTION = 'qcld_wpbot_seo_db_version';

	const URLS      = 'qcld_wpbot_seo_urls';
	const QUERIES   = 'qcld_wpbot_seo_queries';
	const GSC_DAILY = 'qcld_wpbot_seo_gsc_daily';
	const GA_DAILY  = 'qcld_wpbot_seo_ga_daily';
	const OPPS      = 'qcld_wpbot_seo_opportunities';
	const CHANGES   = 'qcld_wpbot_seo_changes';
	const SCANS     = 'qcld_wpbot_seo_scans';
	const PAGES     = 'qcld_wpbot_seo_scan_pages';
	const ISSUES    = 'qcld_wpbot_seo_issues';
	const REDIRECTS = 'qcld_wpbot_seo_redirects';
	const NOT_FOUND = 'qcld_wpbot_seo_not_found';
	const LOCATIONS = 'qcld_wpbot_seo_locations';
	const LINKS     = 'qcld_wpbot_seo_links';
	const PROSPECTS = 'qcld_wpbot_seo_prospects';
	const OUTREACH  = 'qcld_wpbot_seo_outreach';
	const STEPS     = 'qcld_wpbot_seo_outreach_steps';
	const EXCHANGES = 'qcld_wpbot_seo_exchanges';
	const RESEARCH  = 'qcld_wpbot_seo_research';
	const AUDITS    = 'qcld_wpbot_seo_audits';
	const ADS       = 'qcld_wpbot_seo_ads';
	const PLACES    = 'qcld_wpbot_seo_cited_places';
	const POSTS     = 'qcld_wpbot_seo_cited_posts';
	const REVIEWS   = 'qcld_wpbot_seo_reviews';

	/**
	 * Full table name.
	 *
	 * @param string $name Table constant value.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	/**
	 * Install or upgrade when the signature changed.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( get_option( self::OPTION ) !== self::signature() ) {
			self::install();
		}
	}

	/**
	 * Version plus a hash of the definitions.
	 *
	 * @return string
	 */
	public static function signature() {
		return self::VERSION . ':' . md5( implode( '', self::definitions() ) );
	}

	/**
	 * Run dbDelta for every table.
	 *
	 * @return void
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::definitions() as $sql ) {
			dbDelta( $sql );
		}
		update_option( self::OPTION, self::signature(), true );
	}

	/**
	 * CREATE TABLE statements, core first, then extensions'.
	 *
	 * @return string[]
	 */
	public static function definitions() {
		return array_values( (array) apply_filters( 'qcld_wpbot_seo/schema_tables', self::core_tables() ) );
	}

	/**
	 * Core module tables.
	 *
	 * @return array<string,string>
	 */
	public static function core_tables() {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		$t = array();
		foreach ( self::core_names() as $name ) {
			$t[ $name ] = self::table( $name );
		}
		$tables = array();

		// URL and query dictionaries keep the daily table small.
		$tables[ self::URLS ] = "CREATE TABLE {$t[ self::URLS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url_hash char(32) NOT NULL,
			url text NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			resolved_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url_hash (url_hash),
			KEY post_id (post_id)
		) {$c};";

		$tables[ self::QUERIES ] = "CREATE TABLE {$t[ self::QUERIES ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			query_hash char(32) NOT NULL,
			query varchar(512) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY query_hash (query_hash)
		) {$c};";

		// Daily Search Console aggregates. url_id 0 and query_id 0 mark totals:
		// (0,0) site, (url,0) page, (0,query) query, (url,query) page x query.
		// position_sum is position x impressions, so averages weight correctly.
		$tables[ self::GSC_DAILY ] = "CREATE TABLE {$t[ self::GSC_DAILY ]} (
			day date NOT NULL,
			url_id bigint(20) unsigned NOT NULL DEFAULT 0,
			query_id bigint(20) unsigned NOT NULL DEFAULT 0,
			clicks int(10) unsigned NOT NULL DEFAULT 0,
			impressions int(10) unsigned NOT NULL DEFAULT 0,
			position_sum double NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,url_id,query_id),
			KEY url_day (url_id,day),
			KEY query_day (query_id,day)
		) {$c};";

		// Daily Google Analytics 4 aggregates per landing page.
		$tables[ self::GA_DAILY ] = "CREATE TABLE {$t[ self::GA_DAILY ]} (
			day date NOT NULL,
			url_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sessions int(10) unsigned NOT NULL DEFAULT 0,
			engaged_sessions int(10) unsigned NOT NULL DEFAULT 0,
			conversions double NOT NULL DEFAULT 0,
			revenue double NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,url_id),
			KEY url_day (url_id,day)
		) {$c};";

		// Ranked opportunities; dismissed ones stay dismissed across rebuilds.
		$tables[ self::OPPS ] = "CREATE TABLE {$t[ self::OPPS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ref_hash char(32) NOT NULL,
			type varchar(40) NOT NULL,
			url_id bigint(20) unsigned NOT NULL DEFAULT 0,
			query_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			score double NOT NULL DEFAULT 0,
			potential double NOT NULL DEFAULT 0,
			effort tinyint(3) unsigned NOT NULL DEFAULT 2,
			action varchar(40) NOT NULL DEFAULT '',
			numbers text NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ref_hash (ref_hash),
			KEY status_score (status,score),
			KEY post_id (post_id)
		) {$c};";

		// Every change with before and after; results at 14/28/90 days (S6).
		$tables[ self::CHANGES ] = "CREATE TABLE {$t[ self::CHANGES ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(20) NOT NULL,
			field varchar(40) NOT NULL DEFAULT '',
			before_value longtext NULL,
			after_value longtext NULL,
			source varchar(40) NOT NULL DEFAULT '',
			plugin varchar(20) NOT NULL DEFAULT '',
			group_ref varchar(64) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			undone_at datetime DEFAULT NULL,
			results text NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY created_at (created_at),
			KEY group_ref (group_ref)
		) {$c};";

		// Technical scans: runs, page facts, issues (S4).
		$tables[ self::SCANS ] = "CREATE TABLE {$t[ self::SCANS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mode varchar(20) NOT NULL DEFAULT 'loopback',
			status varchar(20) NOT NULL DEFAULT 'running',
			pages int(10) unsigned NOT NULL DEFAULT 0,
			score tinyint(3) unsigned DEFAULT NULL,
			summary text NULL,
			started_at datetime NOT NULL,
			finished_at datetime DEFAULT NULL,
			PRIMARY KEY  (id)
		) {$c};";

		$tables[ self::PAGES ] = "CREATE TABLE {$t[ self::PAGES ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			url_hash char(32) NOT NULL,
			url text NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(12) NOT NULL DEFAULT 'fetch',
			status_code smallint(5) unsigned NOT NULL DEFAULT 0,
			redirect_to text NULL,
			facts longtext NULL,
			depth smallint(5) unsigned DEFAULT NULL,
			inbound int(10) unsigned NOT NULL DEFAULT 0,
			checked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY scan_url (scan_id,url_hash)
		) {$c};";

		$tables[ self::ISSUES ] = "CREATE TABLE {$t[ self::ISSUES ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			check_id varchar(40) NOT NULL,
			category varchar(20) NOT NULL,
			severity varchar(10) NOT NULL DEFAULT 'medium',
			url text NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			details text NULL,
			PRIMARY KEY  (id),
			KEY scan_check (scan_id,check_id)
		) {$c};";

		// Simple redirects (Pro adds pattern redirects) and the 404 log.
		$tables[ self::REDIRECTS ] = "CREATE TABLE {$t[ self::REDIRECTS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(500) NOT NULL,
			source_hash char(32) NOT NULL,
			target varchar(1000) NOT NULL DEFAULT '',
			code smallint(5) unsigned NOT NULL DEFAULT 301,
			type varchar(10) NOT NULL DEFAULT 'simple',
			hits int(10) unsigned NOT NULL DEFAULT 0,
			last_hit datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_hash (source_hash,type)
		) {$c};";

		$tables[ self::NOT_FOUND ] = "CREATE TABLE {$t[ self::NOT_FOUND ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url_hash char(32) NOT NULL,
			url varchar(500) NOT NULL,
			referrer varchar(191) NOT NULL DEFAULT '',
			hits int(10) unsigned NOT NULL DEFAULT 1,
			first_seen datetime NOT NULL,
			last_seen datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url_hash (url_hash),
			KEY last_seen (last_seen)
		) {$c};";

		// Business locations: one main location in Free; the Pro module adds more (S5, S9).
		$tables[ self::LOCATIONS ] = "CREATE TABLE {$t[ self::LOCATIONS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			is_main tinyint(1) NOT NULL DEFAULT 0,
			page_id bigint(20) unsigned NOT NULL DEFAULT 0,
			data longtext NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY is_main (is_main)
		) {$c};";

		// Known links to the site (S10): one row per linking page and target.
		$tables[ self::LINKS ] = "CREATE TABLE {$t[ self::LINKS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			hash char(32) NOT NULL,
			source_url varchar(2048) NOT NULL,
			source_host varchar(190) NOT NULL DEFAULT '',
			target_url varchar(2048) NOT NULL DEFAULT '',
			anchor varchar(255) NOT NULL DEFAULT '',
			rel varchar(20) NOT NULL DEFAULT '',
			origin varchar(20) NOT NULL DEFAULT 'manual',
			status varchar(20) NOT NULL DEFAULT 'unchecked',
			http_code smallint(5) unsigned NOT NULL DEFAULT 0,
			target_ok tinyint(1) NOT NULL DEFAULT 1,
			outreach_id bigint(20) unsigned NOT NULL DEFAULT 0,
			notes text NULL,
			first_seen datetime NOT NULL,
			last_checked datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY hash (hash),
			KEY source_host (source_host),
			KEY status (status)
		) {$c};";

		// Prospects: sites worth contacting (only what the owner enters or a published page).
		$tables[ self::PROSPECTS ] = "CREATE TABLE {$t[ self::PROSPECTS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kind varchar(20) NOT NULL DEFAULT 'other',
			name varchar(190) NOT NULL DEFAULT '',
			website varchar(255) NOT NULL DEFAULT '',
			host varchar(190) NOT NULL DEFAULT '',
			page_url varchar(2048) NOT NULL DEFAULT '',
			contact_url varchar(2048) NOT NULL DEFAULT '',
			location varchar(190) NOT NULL DEFAULT '',
			topic varchar(190) NOT NULL DEFAULT '',
			reason text NULL,
			flags text NULL,
			source varchar(20) NOT NULL DEFAULT 'manual',
			status varchar(20) NOT NULL DEFAULT 'new',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY kind (kind),
			KEY host (host)
		) {$c};";

		// Outreach pipeline: one row per contact.
		$tables[ self::OUTREACH ] = "CREATE TABLE {$t[ self::OUTREACH ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			prospect_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT 'link_request',
			status varchar(20) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			website varchar(255) NOT NULL DEFAULT '',
			host varchar(190) NOT NULL DEFAULT '',
			contact_name varchar(190) NOT NULL DEFAULT '',
			contact_email varchar(190) NOT NULL DEFAULT '',
			target_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_url varchar(2048) NOT NULL DEFAULT '',
			link_url varchar(2048) NOT NULL DEFAULT '',
			anchor varchar(255) NOT NULL DEFAULT '',
			follow_up date NULL,
			notes text NULL,
			draft longtext NULL,
			sent_count int(10) unsigned NOT NULL DEFAULT 0,
			last_sent datetime NULL,
			owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY type_status (type,status),
			KEY follow_up (follow_up),
			KEY contact_email (contact_email)
		) {$c};";

		// Outreach history: status changes, drafts, copies and sends.
		$tables[ self::STEPS ] = "CREATE TABLE {$t[ self::STEPS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			outreach_id bigint(20) unsigned NOT NULL,
			kind varchar(20) NOT NULL,
			from_status varchar(20) NOT NULL DEFAULT '',
			to_status varchar(20) NOT NULL DEFAULT '',
			detail text NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY outreach_id (outreach_id)
		) {$c};";

		// Link exchange records agreed by hand with real partners: both sides.
		$tables[ self::EXCHANGES ] = "CREATE TABLE {$t[ self::EXCHANGES ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			partner varchar(190) NOT NULL DEFAULT '',
			contact varchar(190) NOT NULL DEFAULT '',
			their_url varchar(2048) NOT NULL DEFAULT '',
			their_target varchar(2048) NOT NULL DEFAULT '',
			their_anchor varchar(255) NOT NULL DEFAULT '',
			their_rel varchar(20) NOT NULL DEFAULT '',
			our_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			our_url varchar(2048) NOT NULL DEFAULT '',
			our_target varchar(2048) NOT NULL DEFAULT '',
			our_anchor varchar(255) NOT NULL DEFAULT '',
			our_rel varchar(20) NOT NULL DEFAULT '',
			paid tinyint(1) NOT NULL DEFAULT 0,
			agreed_on date NULL,
			ends_on date NULL,
			agreement longtext NULL,
			notes text NULL,
			status varchar(20) NOT NULL DEFAULT 'proposed',
			checks longtext NULL,
			last_checked datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$c};";

		// Keyword research runs (S14): kept 30 days as a cache, reopened from the history.
		$tables[ self::RESEARCH ] = "CREATE TABLE {$t[ self::RESEARCH ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kind varchar(20) NOT NULL DEFAULT '',
			seed varchar(255) NOT NULL DEFAULT '',
			language varchar(8) NOT NULL DEFAULT '',
			country varchar(8) NOT NULL DEFAULT '',
			cache_key char(32) NOT NULL DEFAULT '',
			status varchar(12) NOT NULL DEFAULT 'done',
			job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			result longtext NULL,
			sources varchar(255) NOT NULL DEFAULT '',
			cost decimal(10,4) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY cache_key (cache_key),
			KEY created_at (created_at)
		) {$c};";

		// Page Audit history (F4): one row per audit, kept for the retention months.
		$tables[ self::AUDITS ] = "CREATE TABLE {$t[ self::AUDITS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			keyword varchar(191) NOT NULL DEFAULT '',
			score tinyint(3) unsigned NOT NULL DEFAULT 0,
			checks longtext NULL,
			trigger_kind varchar(12) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_created (post_id,created_at),
			KEY created_at (created_at)
		) {$c};";

		// Paid searches from Google Ads (S13): a 30-day snapshot per source (ga4 or ads_api).
		$tables[ self::ADS ] = "CREATE TABLE {$t[ self::ADS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(10) NOT NULL DEFAULT 'ga4',
			query varchar(191) NOT NULL DEFAULT '',
			query_hash char(32) NOT NULL DEFAULT '',
			url_id bigint(20) unsigned NOT NULL DEFAULT 0,
			clicks int(10) unsigned NOT NULL DEFAULT 0,
			impressions int(10) unsigned NOT NULL DEFAULT 0,
			cost decimal(14,2) DEFAULT NULL,
			conversions decimal(12,2) NOT NULL DEFAULT 0,
			sessions int(10) unsigned NOT NULL DEFAULT 0,
			currency varchar(3) NOT NULL DEFAULT '',
			period_start date NULL,
			period_end date NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY row_key (source,query_hash,url_id),
			KEY query_hash (query_hash)
		) {$c};";

		// Get cited by AI (round 3): places to show up, with the latest draft, and what the owner posted.
		$tables[ self::PLACES ] = "CREATE TABLE {$t[ self::PLACES ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			platform varchar(20) NOT NULL DEFAULT '',
			url varchar(500) NOT NULL DEFAULT '',
			url_hash char(32) NOT NULL DEFAULT '',
			title varchar(255) NOT NULL DEFAULT '',
			search varchar(191) NOT NULL DEFAULT '',
			why text NULL,
			source varchar(12) NOT NULL DEFAULT 'ai',
			detail longtext NULL,
			fit tinyint(3) unsigned NOT NULL DEFAULT 0,
			status varchar(12) NOT NULL DEFAULT 'new',
			draft longtext NULL,
			found_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url_hash (url_hash),
			KEY status_fit (status,fit)
		) {$c};";

		$tables[ self::POSTS ] = "CREATE TABLE {$t[ self::POSTS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			place_id bigint(20) unsigned NOT NULL DEFAULT 0,
			platform varchar(20) NOT NULL DEFAULT '',
			url varchar(500) NOT NULL DEFAULT '',
			url_hash char(32) NOT NULL DEFAULT '',
			search varchar(191) NOT NULL DEFAULT '',
			focus text NULL,
			note text NULL,
			status varchar(12) NOT NULL DEFAULT 'posted',
			checks longtext NULL,
			posted_at datetime NOT NULL,
			checked_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url_hash (url_hash),
			KEY place_id (place_id),
			KEY posted_at (posted_at)
		) {$c};";

		// Local SEO reviews (round 3, J): Business Profile reviews, the owner's reply and the AI draft.
		$tables[ self::REVIEWS ] = "CREATE TABLE {$t[ self::REVIEWS ]} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			location varchar(40) NOT NULL DEFAULT '',
			review_id varchar(191) NOT NULL DEFAULT '',
			stars tinyint(1) unsigned NOT NULL DEFAULT 0,
			text text NULL,
			reviewer varchar(191) NOT NULL DEFAULT '',
			reply text NULL,
			reply_at datetime NULL,
			draft text NULL,
			created_at datetime NULL,
			updated_at datetime NULL,
			synced_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY review (location,review_id),
			KEY created_at (created_at)
		) {$c};";

		return $tables;
	}

	/**
	 * Core table names, for uninstall.
	 *
	 * @return string[]
	 */
	public static function core_names() {
		return array( self::URLS, self::QUERIES, self::GSC_DAILY, self::GA_DAILY, self::OPPS, self::CHANGES, self::SCANS, self::PAGES, self::ISSUES, self::REDIRECTS, self::NOT_FOUND, self::LOCATIONS, self::LINKS, self::PROSPECTS, self::OUTREACH, self::STEPS, self::EXCHANGES, self::RESEARCH, self::AUDITS, self::ADS, self::PLACES, self::POSTS, self::REVIEWS );
	}
}
