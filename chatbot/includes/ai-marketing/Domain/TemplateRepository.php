<?php
/**
 * Email templates table access.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * SQL for wpbot_crm_templates. `design` is JSON; is_builtin is a boolean.
 */
class TemplateRepository extends Repository {

	/**
	 * Table suffix.
	 *
	 * @var string
	 */
	protected $table_name = 'templates';

	/**
	 * Writable columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'name', 'category', 'subject', 'content_html', 'design', 'is_builtin' );

	/**
	 * JSON columns.
	 *
	 * @var string[]
	 */
	protected $json = array( 'design' );

	/**
	 * Boolean columns.
	 *
	 * @var string[]
	 */
	protected $bools = array( 'is_builtin' );

	/**
	 * All templates, built-ins first.
	 *
	 * @param array $args search (name/subject), category (case-insensitive exact).
	 * @return array[]
	 */
	public function all( array $args = array() ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR subject LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['category'] ) ) {
			$where[]  = 'LOWER(category) = %s';
			$params[] = strtolower( (string) $args['category'] );
		}
		$result = $this->paginate( 'WHERE ' . implode( ' AND ', $where ), $params, 'is_builtin DESC, id ASC', 1, 0 );
		return $result['items'];
	}

	/**
	 * A built-in template by exact name (used by the seeder to stay idempotent).
	 *
	 * @param string $name Name.
	 * @return array|null
	 */
	public function find_builtin_by_name( $name ) {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE is_builtin = 1 AND name = %s LIMIT 1", (string) $name ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Number of built-in templates.
	 *
	 * @return int
	 */
	public function count_builtin() {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_builtin = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
