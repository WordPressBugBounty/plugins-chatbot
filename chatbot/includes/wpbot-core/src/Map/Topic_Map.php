<?php
/**
 * Shared topic map (brief 7.10): topics with a name, an optional parent and
 * pillar page, and a coverage status (covered, thin, missing, planned).
 * Pages belong to topics through the keyword map. AI Writer and SEO & AI
 * Search read and write the same data through this class.
 *
 * Locked or manual topics are never changed by automatic updates.
 *
 * Read API: get(), find(), topics(), all(), pages(), children(),
 * coverage_for(), missing_links(), links_to().
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Map;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Topic map.
 */
final class Topic_Map {

	const COVERAGE = array( '', 'covered', 'thin', 'missing', 'planned' );

	/**
	 * Find or create a topic by name.
	 *
	 * @param string $name      Topic name.
	 * @param int    $parent_id Parent topic.
	 * @param string $source    ai, manual, search_console.
	 * @return int Topic id (0 for an empty name).
	 */
	public static function ensure( $name, $parent_id = 0, $source = 'ai' ) {
		global $wpdb;
		$name = trim( sanitize_text_field( (string) $name ) );
		if ( '' === $name ) {
			return 0;
		}
		$name  = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 191 ) : substr( $name, 0, 191 );
		$slug  = self::slug( $name );
		$table = Schema::table( Schema::TOPICS );
		$found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		if ( $found ) {
			return $found;
		}
		$now = current_time( 'mysql', true );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
			$table,
			array(
				'name'       => $name,
				'slug'       => $slug,
				'parent_id'  => absint( $parent_id ),
				'source'     => sanitize_key( $source ),
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		do_action( 'qcld_wpbot_core/topic_map_changed', (int) $wpdb->insert_id );
		return (int) $wpdb->insert_id;
	}

	/**
	 * One topic, or null.
	 *
	 * @param int $id Topic id.
	 * @return array|null id, name, slug, parent_id, pillar_post_id, coverage, source, locked.
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::TOPICS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Find a topic by name ('' when unknown).
	 *
	 * @param string $name Name.
	 * @return array|null
	 */
	public static function find( $name ) {
		global $wpdb;
		$table = Schema::table( Schema::TOPICS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", self::slug( (string) $name ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Every topic with page counts (published, drafts, planned).
	 *
	 * @return array[]
	 */
	public static function topics() {
		global $wpdb;
		$topics = Schema::table( Schema::TOPICS );
		$rows   = $wpdb->get_results( "SELECT * FROM {$topics} ORDER BY name ASC LIMIT 1000", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		$out    = array();
		foreach ( (array) $rows as $row ) {
			$topic            = self::shape( $row );
			$pages            = self::pages( $topic['id'] );
			$topic['pages']   = count( $pages );
			$topic['live']    = count(
				array_filter(
					$pages,
					static function ( $page ) {
						return 'publish' === $page['status'];
					}
				)
			);
			$topic['planned'] = count(
				array_filter(
					$pages,
					static function ( $page ) {
						return 'planned' === $page['status'];
					}
				)
			);
			$out[]            = $topic;
		}
		return $out;
	}

	/**
	 * Pages of a topic (main entries only; planned pages included).
	 *
	 * @param int $topic_id Topic.
	 * @return array[] Keyword map entries.
	 */
	public static function pages( $topic_id ) {
		return Keyword_Map::entries(
			array(
				'topic_id' => (int) $topic_id,
				'role'     => 'main',
			)
		);
	}

	/**
	 * Change a topic. Automatic updates never change a locked topic.
	 *
	 * @param int   $id      Topic.
	 * @param array $changes name, parent_id, pillar_post_id, coverage, locked.
	 * @param bool  $manual  Owner change (may change locked topics).
	 * @return bool
	 */
	public static function update( $id, array $changes, $manual = false ) {
		global $wpdb;
		$topic = self::get( $id );
		if ( ! $topic || ( $topic['locked'] && ! $manual ) ) {
			return false;
		}
		$data = array( 'updated_at' => current_time( 'mysql', true ) );
		if ( isset( $changes['name'] ) && '' !== trim( (string) $changes['name'] ) ) {
			$data['name'] = sanitize_text_field( (string) $changes['name'] );
		}
		foreach ( array( 'parent_id', 'pillar_post_id' ) as $key ) {
			if ( isset( $changes[ $key ] ) ) {
				$data[ $key ] = absint( $changes[ $key ] );
			}
		}
		if ( isset( $changes['coverage'] ) && in_array( $changes['coverage'], self::COVERAGE, true ) ) {
			$data['coverage'] = $changes['coverage'];
		}
		if ( isset( $changes['locked'] ) ) {
			$data['locked'] = $changes['locked'] ? 1 : 0;
		}
		if ( $manual ) {
			$data['source'] = 'manual';
		}
		$ok = false !== $wpdb->update( Schema::table( Schema::TOPICS ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		do_action( 'qcld_wpbot_core/topic_map_changed', (int) $id );
		return $ok;
	}

	/**
	 * Subtopics of a topic.
	 *
	 * @param int $id Topic.
	 * @return array[]
	 */
	public static function children( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::TOPICS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE parent_id = %d ORDER BY name ASC LIMIT 200", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	/**
	 * Delete a topic (owner action): its pages lose the topic, its
	 * subtopics move to its parent.
	 *
	 * @param int $id Topic.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$topic = self::get( $id );
		if ( ! $topic ) {
			return false;
		}
		$wpdb->update( Schema::table( Schema::KEYWORDS ), array( 'topic_id' => 0 ), array( 'topic_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		$wpdb->update( Schema::table( Schema::TOPICS ), array( 'parent_id' => $topic['parent_id'] ), array( 'parent_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		$wpdb->delete( Schema::table( Schema::TOPICS ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
		do_action( 'qcld_wpbot_core/topic_map_changed', (int) $id );
		return true;
	}

	/**
	 * Coverage a topic has now, from its pages: covered (3 or more live
	 * pages, or a live pillar and one more), thin (1 or 2 live pages),
	 * planned (only drafts or planned pages), missing (nothing).
	 *
	 * @param int $id Topic.
	 * @return string
	 */
	public static function coverage_for( $id ) {
		$topic = self::get( $id );
		if ( ! $topic ) {
			return '';
		}
		$live  = 0;
		$other = 0;
		foreach ( self::pages( $id ) as $page ) {
			if ( 'publish' === $page['status'] ) {
				++$live;
			} else {
				++$other;
			}
		}
		$pillar = $topic['pillar_post_id'] && 'publish' === get_post_status( $topic['pillar_post_id'] );
		if ( $live >= 3 || ( $pillar && $live >= 2 ) ) {
			return 'covered';
		}
		if ( $live > 0 ) {
			return 'thin';
		}
		return $other > 0 ? 'planned' : 'missing';
	}

	/**
	 * Recalculate the coverage of every topic that is not locked.
	 *
	 * @return int Topics changed.
	 */
	public static function refresh_coverage() {
		$changed = 0;
		foreach ( self::all() as $topic ) {
			if ( $topic['locked'] ) {
				continue;
			}
			$coverage = self::coverage_for( $topic['id'] );
			if ( $coverage !== $topic['coverage'] && self::update( $topic['id'], array( 'coverage' => $coverage ) ) ) {
				++$changed;
			}
		}
		return $changed;
	}

	/**
	 * Every topic, without page counts (cheap).
	 *
	 * @return array[]
	 */
	public static function all() {
		global $wpdb;
		$table = Schema::table( Schema::TOPICS );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC LIMIT 1000", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; name from code.
		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	/**
	 * Whether a post's content links to another post (its permalink, or a
	 * ?p= / ?page_id= link).
	 *
	 * @param int $from Post that should link.
	 * @param int $to   Post it should link to.
	 * @return bool
	 */
	public static function links_to( $from, $to ) {
		$content = (string) get_post_field( 'post_content', $from, 'raw' );
		if ( '' === $content || ! preg_match_all( '/href\s*=\s*["\']([^"\']+)["\']/i', $content, $found ) ) {
			return false;
		}
		$target = untrailingslashit( (string) wp_parse_url( (string) get_permalink( $to ), PHP_URL_PATH ) );
		foreach ( $found[1] as $href ) {
			$href = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
			$path = untrailingslashit( (string) wp_parse_url( $href, PHP_URL_PATH ) );
			if ( '' !== $target && $path === $target ) {
				return true;
			}
			if ( preg_match( '/[?&](?:p|page_id)=(\d+)/', $href, $id ) && (int) $id[1] === (int) $to ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Links a topic cluster is missing: the pillar should link to every
	 * live page of the topic, and each of them back to the pillar.
	 *
	 * @param int $id Topic.
	 * @return array[] from, to (post ids). Empty without a live pillar.
	 */
	public static function missing_links( $id ) {
		$topic = self::get( $id );
		if ( ! $topic || ! $topic['pillar_post_id'] || 'publish' !== get_post_status( $topic['pillar_post_id'] ) ) {
			return array();
		}
		$pillar  = (int) $topic['pillar_post_id'];
		$missing = array();
		foreach ( self::pages( $id ) as $page ) {
			if ( 'publish' !== $page['status'] || $page['post_id'] === $pillar ) {
				continue;
			}
			if ( ! self::links_to( $pillar, $page['post_id'] ) ) {
				$missing[] = array(
					'from' => $pillar,
					'to'   => $page['post_id'],
				);
			}
			if ( ! self::links_to( $page['post_id'], $pillar ) ) {
				$missing[] = array(
					'from' => $page['post_id'],
					'to'   => $pillar,
				);
			}
		}
		return $missing;
	}

	/**
	 * Slug of a topic name (case and punctuation ignored).
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function slug( $name ) {
		return substr( sanitize_title( Keyword_Map::normalise( $name ) ), 0, 191 );
	}

	/**
	 * Public shape.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		return array(
			'id'             => (int) $row['id'],
			'name'           => (string) $row['name'],
			'slug'           => (string) $row['slug'],
			'parent_id'      => (int) $row['parent_id'],
			'pillar_post_id' => (int) $row['pillar_post_id'],
			'coverage'       => (string) $row['coverage'],
			'source'         => (string) $row['source'],
			'locked'         => (bool) $row['locked'],
		);
	}
}
