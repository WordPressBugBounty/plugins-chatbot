<?php
/**
 * Database tables of WPBot Core.
 *
 * The stored version is a signature (version + hash of the definitions), so
 * any change to a definition re-runs dbDelta(). Changes only add; data is
 * never dropped here. Running install() twice is safe.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the module's tables.
 */
final class Schema {

	const VERSION = '3';
	const OPTION  = 'qcld_wpbot_core_db_version';

	/**
	 * Table names without the WordPress prefix.
	 */
	const JOBS        = 'qcld_wpbot_core_jobs';
	const STEPS       = 'qcld_wpbot_core_job_steps';
	const USAGE       = 'qcld_wpbot_core_usage';
	const CACHE       = 'qcld_wpbot_core_ai_cache';
	const PACKAGES    = 'qcld_wpbot_core_packages';
	const FACTS       = 'qcld_wpbot_core_facts';
	const KEYWORDS    = 'qcld_wpbot_core_keyword_map';
	const TOPICS      = 'qcld_wpbot_core_topics';
	const KIT         = 'qcld_wpbot_core_kit';
	const VOC         = 'qcld_wpbot_core_voc';
	const EXPERIMENTS = 'qcld_wpbot_core_experiments';
	const VARIANTS    = 'qcld_wpbot_core_experiment_variants';
	const STATS       = 'qcld_wpbot_core_experiment_stats';
	const RESULTS     = 'qcld_wpbot_core_technique_results';

	/**
	 * Full table name with the site prefix.
	 *
	 * @param string $name One of the constants above.
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
	 * Current signature.
	 *
	 * @return string
	 */
	public static function signature() {
		return self::VERSION . ':' . md5( implode( '', self::definitions() ) );
	}

	/**
	 * Run dbDelta() for every table.
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
	 * CREATE TABLE statements in dbDelta() format.
	 *
	 * @return string[]
	 */
	public static function definitions() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$jobs     = self::table( self::JOBS );
		$steps    = self::table( self::STEPS );
		$usage    = self::table( self::USAGE );
		$cache    = self::table( self::CACHE );
		$packages = self::table( self::PACKAGES );
		$keywords = self::table( self::KEYWORDS );
		$topics   = self::table( self::TOPICS );
		$facts    = self::table( self::FACTS );
		$kit      = self::table( self::KIT );
		$voc      = self::table( self::VOC );
		$exps     = self::table( self::EXPERIMENTS );
		$variants = self::table( self::VARIANTS );
		$stats    = self::table( self::STATS );
		$results  = self::table( self::RESULTS );

		return array(
			"CREATE TABLE {$jobs} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  module varchar(40) NOT NULL DEFAULT '',
  type varchar(60) NOT NULL DEFAULT '',
  title varchar(255) NOT NULL DEFAULT '',
  object_type varchar(20) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'queued',
  payload longtext NULL,
  estimate_cost decimal(10,4) NOT NULL DEFAULT 0,
  budget decimal(10,4) NOT NULL DEFAULT 0,
  error_message text NULL,
  browser_seen_at datetime NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  last_completed_at datetime NULL,
  PRIMARY KEY  (id),
  KEY module_status (module,status),
  KEY parent_id (parent_id),
  KEY object (object_type,object_id),
  KEY user_id (user_id)
) {$charset};",
			"CREATE TABLE {$steps} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  job_id bigint(20) unsigned NOT NULL,
  seq int(10) unsigned NOT NULL,
  kind varchar(60) NOT NULL DEFAULT '',
  label varchar(255) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'pending',
  attempt_id char(32) NOT NULL DEFAULT '',
  lock_until datetime NULL,
  attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
  input longtext NULL,
  output longtext NULL,
  tokens_in int(10) unsigned NOT NULL DEFAULT 0,
  tokens_out int(10) unsigned NOT NULL DEFAULT 0,
  cost decimal(10,4) NOT NULL DEFAULT 0,
  error_code varchar(60) NOT NULL DEFAULT '',
  error_message text NULL,
  started_at datetime NULL,
  finished_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY job_seq (job_id,seq),
  KEY status (status)
) {$charset};",
			"CREATE TABLE {$usage} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  day date NOT NULL,
  module varchar(40) NOT NULL DEFAULT '',
  feature varchar(60) NOT NULL DEFAULT '',
  provider varchar(40) NOT NULL DEFAULT '',
  model varchar(120) NOT NULL DEFAULT '',
  job_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  tokens_in int(10) unsigned NOT NULL DEFAULT 0,
  tokens_out int(10) unsigned NOT NULL DEFAULT 0,
  images smallint(5) unsigned NOT NULL DEFAULT 0,
  web_searches smallint(5) unsigned NOT NULL DEFAULT 0,
  cost_estimate decimal(10,6) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY module_day (module,day),
  KEY job_id (job_id)
) {$charset};",
			"CREATE TABLE {$cache} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  hash char(64) NOT NULL,
  module varchar(40) NOT NULL DEFAULT '',
  feature varchar(60) NOT NULL DEFAULT '',
  model varchar(120) NOT NULL DEFAULT '',
  result longtext NULL,
  tokens int(10) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY hash (hash),
  KEY created_at (created_at)
) {$charset};",
			"CREATE TABLE {$packages} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  module varchar(40) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'draft',
  title text NULL,
  summary text NULL,
  data longtext NULL,
  channel_versions longtext NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  finalized_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY post_id (post_id),
  KEY status (status)
) {$charset};",
			"CREATE TABLE {$facts} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  scope varchar(20) NOT NULL DEFAULT 'business',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  fact text NOT NULL,
  source varchar(255) NOT NULL DEFAULT '',
  review_by date NULL,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY scope_object (scope,object_id)
) {$charset};",
			// Shared keyword map (brief 7.10): which page (or planned page) targets which search.
			"CREATE TABLE {$keywords} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  plan_ref varchar(100) NOT NULL DEFAULT '',
  query varchar(191) NOT NULL DEFAULT '',
  query_hash char(32) NOT NULL DEFAULT '',
  role varchar(12) NOT NULL DEFAULT 'main',
  topic_id bigint(20) unsigned NOT NULL DEFAULT 0,
  source varchar(20) NOT NULL DEFAULT 'ai',
  locked tinyint(1) unsigned NOT NULL DEFAULT 0,
  module varchar(40) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY entry (post_id,plan_ref,query_hash),
  KEY query_hash (query_hash),
  KEY topic_id (topic_id)
) {$charset};",
			// Shared topic map (brief 7.10): topics, parents, pillar pages and coverage.
			"CREATE TABLE {$topics} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(191) NOT NULL DEFAULT '',
  slug varchar(191) NOT NULL DEFAULT '',
  parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
  pillar_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  coverage varchar(20) NOT NULL DEFAULT '',
  source varchar(20) NOT NULL DEFAULT 'ai',
  locked tinyint(1) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug),
  KEY parent_id (parent_id)
) {$charset};",
			// Persuasion Kit (round 3): the owner's real proof by type.
			"CREATE TABLE {$kit} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  type varchar(30) NOT NULL DEFAULT '',
  label varchar(191) NOT NULL DEFAULT '',
  body text NOT NULL,
  value varchar(100) NOT NULL DEFAULT '',
  source varchar(255) NOT NULL DEFAULT '',
  url varchar(255) NOT NULL DEFAULT '',
  permission tinyint(1) NOT NULL DEFAULT 0,
  expires_on date NULL,
  sort int(11) NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY type (type)
) {$charset};",
			// Voice of customer bank (round 3): redacted phrases grouped by topic.
			"CREATE TABLE {$voc} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  topic varchar(191) NOT NULL DEFAULT '',
  phrase varchar(300) NOT NULL DEFAULT '',
  source varchar(30) NOT NULL DEFAULT '',
  source_ref char(32) NOT NULL DEFAULT '',
  weight int(10) unsigned NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY source_ref (source,source_ref),
  KEY topic (topic)
) {$charset};",
			// Experiments (round 3, P4): one row per test.
			"CREATE TABLE {$exps} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  module varchar(40) NOT NULL DEFAULT '',
  kind varchar(30) NOT NULL DEFAULT '',
  object_type varchar(20) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(255) NOT NULL DEFAULT '',
  method varchar(20) NOT NULL DEFAULT 'split',
  metric varchar(30) NOT NULL DEFAULT 'clicks',
  status varchar(20) NOT NULL DEFAULT 'draft',
  winner_mode varchar(10) NOT NULL DEFAULT 'click',
  winner_variant bigint(20) unsigned NOT NULL DEFAULT 0,
  stage varchar(30) NOT NULL DEFAULT '',
  min_per_variant int(10) unsigned NOT NULL DEFAULT 100,
  max_days smallint(5) unsigned NOT NULL DEFAULT 30,
  settings longtext NULL,
  result longtext NULL,
  legacy varchar(100) NOT NULL DEFAULT '',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  started_at datetime NULL,
  ends_at datetime NULL,
  finished_at datetime NULL,
  PRIMARY KEY  (id),
  KEY module_status (module,status),
  KEY object (object_type,object_id),
  KEY legacy (legacy)
) {$charset};",
			"CREATE TABLE {$variants} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  experiment_id bigint(20) unsigned NOT NULL,
  label varchar(20) NOT NULL DEFAULT '',
  technique varchar(60) NOT NULL DEFAULT '',
  content longtext NULL,
  is_control tinyint(1) unsigned NOT NULL DEFAULT 0,
  sort smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY experiment_id (experiment_id)
) {$charset};",
			// Daily counts per variant and metric; no personal data.
			"CREATE TABLE {$stats} (
  experiment_id bigint(20) unsigned NOT NULL,
  variant_id bigint(20) unsigned NOT NULL,
  day date NOT NULL,
  metric varchar(30) NOT NULL DEFAULT '',
  count bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (experiment_id,variant_id,day,metric)
) {$charset};",
			"CREATE TABLE {$results} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  module varchar(40) NOT NULL DEFAULT '',
  kind varchar(30) NOT NULL DEFAULT '',
  technique varchar(60) NOT NULL DEFAULT '',
  stage varchar(30) NOT NULL DEFAULT '',
  wins int(10) unsigned NOT NULL DEFAULT 0,
  losses int(10) unsigned NOT NULL DEFAULT 0,
  ties int(10) unsigned NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY technique_key (module,kind,technique,stage)
) {$charset};",
		);
	}

	/**
	 * Every table name, for uninstall.
	 *
	 * @return string[]
	 */
	public static function all_tables() {
		return array(
			self::table( self::JOBS ),
			self::table( self::STEPS ),
			self::table( self::USAGE ),
			self::table( self::CACHE ),
			self::table( self::PACKAGES ),
			self::table( self::FACTS ),
			self::table( self::KEYWORDS ),
			self::table( self::TOPICS ),
			self::table( self::KIT ),
			self::table( self::VOC ),
			self::table( self::EXPERIMENTS ),
			self::table( self::VARIANTS ),
			self::table( self::STATS ),
			self::table( self::RESULTS ),
		);
	}
}
