<?php
/**
 * Tables — visual database table manager
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tables
 */
class Tables {

	const TABLE_META = 'wpbot_automator_tables';

	// ── Bootstrap ────────────────────────────────────────────────────────────

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_shortcode( 'wpbot_table', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function get_meta_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_META;
	}

	/** Prefixed name of the user-data table for a given slug. */
	public static function get_user_table( $slug ) {
		global $wpdb;
		return $wpdb->prefix . 'wpbot_ut_' . $slug;
	}

	// ── REST routes ──────────────────────────────────────────────────────────

	public static function register_routes() {
		$perm        = array( Plugin::class, 'check_permission' );
		$perm_public = '__return_true'; // nonce verified inside callback

		// Table list + create.
		register_rest_route( 'wpbot-automator/v1', '/tables', array(
			array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'list_tables' ),  'permission_callback' => $perm ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create_table' ), 'permission_callback' => $perm ),
		) );

		// Single table delete.
		register_rest_route( 'wpbot-automator/v1', '/tables/(?P<id>\d+)', array(
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_table' ), 'permission_callback' => $perm ),
		) );

		// Records list + create.
		register_rest_route( 'wpbot-automator/v1', '/tables/(?P<id>\d+)/records', array(
			array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'get_records' ),    'permission_callback' => $perm ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create_record' ),  'permission_callback' => $perm ),
		) );

		// Single record update + delete.
		register_rest_route( 'wpbot-automator/v1', '/tables/(?P<id>\d+)/records/(?P<row_id>\d+)', array(
			array( 'methods' => 'PUT',    'callback' => array( __CLASS__, 'update_record' ), 'permission_callback' => $perm ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_record' ), 'permission_callback' => $perm ),
		) );

		// CSV import.
		register_rest_route( 'wpbot-automator/v1', '/tables/(?P<id>\d+)/import', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'import_csv' ),
			'permission_callback' => $perm,
		) );

		// CSV export.
		register_rest_route( 'wpbot-automator/v1', '/tables/(?P<id>\d+)/export', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'export_csv' ),
			'permission_callback' => $perm,
		) );

		// Trigger-workflow button (public — nonce verified inside).
		register_rest_route( 'wpbot-automator/v1', '/tables/(?P<id>\d+)/records/(?P<row_id>\d+)/trigger-workflow', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_trigger_button' ),
			'permission_callback' => $perm_public,
		) );
	}

	// ── REST handlers ────────────────────────────────────────────────────────

	/** GET /tables — list all user-defined tables with record counts. */
	public static function list_tables() {
		global $wpdb;
		$meta = self::get_meta_table();
		$rows = $wpdb->get_results( "SELECT * FROM {$meta} ORDER BY created_at DESC", ARRAY_A );

		foreach ( $rows as &$row ) {
			$row['columns']      = json_decode( $row['columns_json'], true ) ?: array();
			$ut                  = self::get_user_table( $row['slug'] );
			$row['record_count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$ut}`" );
			unset( $row['columns_json'] );
		}
		unset( $row );

		return rest_ensure_response( $rows );
	}

	/** POST /tables — create a new table definition + MySQL table. */
	public static function create_table( \WP_REST_Request $request ) {
		global $wpdb;

		$name    = sanitize_text_field( $request->get_param( 'name' ) );
		$columns = $request->get_param( 'columns' );

		if ( empty( $name ) ) {
			return new \WP_Error( 'missing_name', 'Table name is required.', array( 'status' => 400 ) );
		}
		if ( empty( $columns ) || ! is_array( $columns ) ) {
			return new \WP_Error( 'missing_columns', 'At least one column is required.', array( 'status' => 400 ) );
		}

		// Sanitize and deduplicate column slugs.
		$clean_cols = array();
		$seen_slugs = array();
		foreach ( $columns as $col ) {
			$col_name = sanitize_text_field( $col['name'] ?? '' );
			if ( empty( $col_name ) ) {
				continue;
			}
			$slug = self::to_column_slug( $col_name );
			$base = $slug;
			$n    = 1;
			while ( in_array( $slug, $seen_slugs, true ) ) {
				$slug = $base . '_' . ( ++$n );
			}
			$seen_slugs[] = $slug;
			$clean_cols[] = array(
				'slug'     => $slug,
				'name'     => $col_name,
				'type'     => self::sanitize_col_type( $col['type'] ?? 'text' ),
				'required' => ! empty( $col['required'] ),
			);
		}

		if ( empty( $clean_cols ) ) {
			return new \WP_Error( 'invalid_columns', 'No valid columns provided.', array( 'status' => 400 ) );
		}

		// Generate unique table slug.
		$base_slug  = self::to_table_slug( $name );
		$table_slug = $base_slug;
		$n          = 1;
		$meta       = self::get_meta_table();
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$meta} WHERE slug = %s", $table_slug ) ) ) {
			$table_slug = $base_slug . '_' . ( ++$n );
		}

		// Create the MySQL data table.
		$result = self::create_mysql_table( $table_slug, $clean_cols );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Store meta.
		$inserted = $wpdb->insert( $meta, array(
			'name'         => $name,
			'slug'         => $table_slug,
			'columns_json' => wp_json_encode( $clean_cols ),
			'created_at'   => current_time( 'mysql' ),
		) );

		if ( ! $inserted ) {
			self::drop_mysql_table( $table_slug );
			return new \WP_Error( 'db_error', 'Failed to save table definition.', array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'id'      => $wpdb->insert_id,
			'slug'    => $table_slug,
			'columns' => $clean_cols,
		) );
	}

	/** DELETE /tables/{id} — remove table definition + MySQL table. */
	public static function delete_table( \WP_REST_Request $request ) {
		global $wpdb;
		$id   = absint( $request->get_param( 'id' ) );
		$meta = self::get_meta_table();
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$meta} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Table not found.', array( 'status' => 404 ) );
		}

		self::drop_mysql_table( $row['slug'] );
		$wpdb->delete( $meta, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** GET /tables/{id}/records — paginated record list with optional column filter. */
	public static function get_records( \WP_REST_Request $request ) {
		global $wpdb;
		$table = self::get_table_or_error( absint( $request->get_param( 'id' ) ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$ut         = self::get_user_table( $table['slug'] );
		$per_page   = min( max( absint( $request->get_param( 'per_page' ) ?: 50 ), 1 ), 500 );
		$page       = max( absint( $request->get_param( 'page' ) ?: 1 ), 1 );
		$offset     = ( $page - 1 ) * $per_page;
		$filter_col = sanitize_key( $request->get_param( 'filter_col' ) ?: '' );
		$filter_val = sanitize_text_field( $request->get_param( 'filter_val' ) ?: '' );

		$where        = '';
		$query_params = array();

		if ( $filter_col && $filter_val !== '' ) {
			$valid_cols = wp_list_pluck( $table['columns'], 'slug' );
			if ( in_array( $filter_col, $valid_cols, true ) ) {
				$where          = 'WHERE `' . esc_sql( $filter_col ) . '` LIKE %s';
				$query_params[] = '%' . $wpdb->esc_like( $filter_val ) . '%';
			}
		}

		$count_sql = "SELECT COUNT(*) FROM `{$ut}` {$where}";
		$total     = (int) ( $query_params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$query_params ) )
			: $wpdb->get_var( $count_sql ) );

		$list_params = array_merge( $query_params, array( $per_page, $offset ) );
		$list_sql    = "SELECT * FROM `{$ut}` {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$records     = $wpdb->get_results( $wpdb->prepare( $list_sql, ...$list_params ), ARRAY_A );

		return rest_ensure_response( array(
			'records'     => $records ?: array(),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
		) );
	}

	/** POST /tables/{id}/records — insert a new record. */
	public static function create_record( \WP_REST_Request $request ) {
		global $wpdb;
		$table = self::get_table_or_error( absint( $request->get_param( 'id' ) ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$ut   = self::get_user_table( $table['slug'] );
		$data = self::extract_record_data( $request->get_param( 'data' ) ?: array(), $table['columns'] );

		if ( empty( $data ) ) {
			return new \WP_Error( 'empty_data', 'No valid fields provided.', array( 'status' => 400 ) );
		}

		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		$wpdb->insert( $ut, $data );
		$new_id = (int) $wpdb->insert_id;

		do_action( 'wpbot_table_new_record', absint( $request->get_param( 'id' ) ), $table, $data, $new_id );

		return rest_ensure_response( array( 'success' => true, 'id' => $new_id ) );
	}

	/** PUT /tables/{id}/records/{row_id} — update an existing record. */
	public static function update_record( \WP_REST_Request $request ) {
		global $wpdb;
		$table = self::get_table_or_error( absint( $request->get_param( 'id' ) ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$ut     = self::get_user_table( $table['slug'] );
		$row_id = absint( $request->get_param( 'row_id' ) );
		$data   = self::extract_record_data( $request->get_param( 'data' ) ?: array(), $table['columns'] );

		if ( empty( $data ) ) {
			return new \WP_Error( 'empty_data', 'No valid fields provided.', array( 'status' => 400 ) );
		}

		$data['updated_at'] = current_time( 'mysql' );

		$old_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$ut}` WHERE id = %d", $row_id ), ARRAY_A );
		$wpdb->update( $ut, $data, array( 'id' => $row_id ) );

		$table_id = absint( $request->get_param( 'id' ) );

		// Fire per-cell triggers for each changed column.
		if ( is_array( $old_record ) ) {
			foreach ( $data as $col_slug => $new_val ) {
				if ( in_array( $col_slug, array( 'updated_at' ), true ) ) {
					continue;
				}
				$old_val = isset( $old_record[ $col_slug ] ) ? $old_record[ $col_slug ] : null;
				if ( (string) $old_val !== (string) $new_val ) {
					do_action( 'wpbot_table_updated_cell', $table_id, $table, $row_id, $col_slug, $old_val, $new_val );
				}
			}
		}

		do_action( 'wpbot_table_updated_record', $table_id, $table, $data, $row_id, $old_record );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** DELETE /tables/{id}/records/{row_id} — delete a record. */
	public static function delete_record( \WP_REST_Request $request ) {
		global $wpdb;
		$table = self::get_table_or_error( absint( $request->get_param( 'id' ) ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$ut       = self::get_user_table( $table['slug'] );
		$row_id   = absint( $request->get_param( 'row_id' ) );
		$table_id = absint( $request->get_param( 'id' ) );

		$old_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$ut}` WHERE id = %d", $row_id ), ARRAY_A );
		$wpdb->delete( $ut, array( 'id' => $row_id ) );

		do_action( 'wpbot_table_deleted_record', $table_id, $table, $row_id, $old_record );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** POST /tables/{id}/records/{row_id}/trigger-workflow — fire from a shortcode button. */
	public static function handle_trigger_button( \WP_REST_Request $request ) {
		$table_id = absint( $request->get_param( 'id' ) );
		$row_id   = absint( $request->get_param( 'row_id' ) );

		// Verify nonce (embedded by the shortcode; valid for both logged-in and guest visitors).
		$nonce = $request->get_param( 'nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wpbot_table_trigger_' . $table_id ) ) {
			return new \WP_Error( 'invalid_nonce', 'Security check failed.', array( 'status' => 403 ) );
		}

		$table = self::get_table_or_error( $table_id );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		global $wpdb;
		$ut       = self::get_user_table( $table['slug'] );
		$row_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$ut}` WHERE id = %d", $row_id ), ARRAY_A );

		if ( ! $row_data ) {
			return new \WP_Error( 'not_found', 'Record not found.', array( 'status' => 404 ) );
		}

		do_action( 'wpbot_table_trigger_button', $table_id, $table, $row_id, $row_data );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * POST /tables/{id}/import — bulk insert rows from a CSV.
	 *
	 * Expects JSON body: { rows: [["val1","val2",...], ...], headers: ["col1","col2",...] }
	 * Headers are matched to column slugs by name (case-insensitive).
	 */
	public static function import_csv( \WP_REST_Request $request ) {
		global $wpdb;
		$table = self::get_table_or_error( absint( $request->get_param( 'id' ) ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$headers = $request->get_param( 'headers' );
		$rows    = $request->get_param( 'rows' );

		if ( ! is_array( $headers ) || ! is_array( $rows ) || empty( $rows ) ) {
			return new \WP_Error( 'invalid_data', 'headers and rows arrays are required.', array( 'status' => 400 ) );
		}

		// Build header → column slug mapping (case-insensitive, also match on slug).
		$col_map = array();
		foreach ( $table['columns'] as $col ) {
			$col_map[ strtolower( $col['name'] ) ] = $col['slug'];
			$col_map[ strtolower( $col['slug'] ) ] = $col['slug'];
		}

		$header_to_slug = array();
		foreach ( $headers as $idx => $h ) {
			$key = strtolower( trim( $h ) );
			if ( isset( $col_map[ $key ] ) ) {
				$header_to_slug[ $idx ] = $col_map[ $key ];
			}
		}

		if ( empty( $header_to_slug ) ) {
			return new \WP_Error( 'no_match', 'No CSV headers matched table columns.', array( 'status' => 400 ) );
		}

		$ut      = self::get_user_table( $table['slug'] );
		$now     = current_time( 'mysql' );
		$count   = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$data = array( 'created_at' => $now, 'updated_at' => $now );
			foreach ( $header_to_slug as $idx => $slug ) {
				$data[ $slug ] = isset( $row[ $idx ] ) ? sanitize_text_field( $row[ $idx ] ) : '';
			}
			if ( $wpdb->insert( $ut, $data ) ) {
				++$count;
			}
		}

		return rest_ensure_response( array( 'success' => true, 'imported' => $count ) );
	}

	/** GET /tables/{id}/export — return CSV as a download. */
	public static function export_csv( \WP_REST_Request $request ) {
		global $wpdb;
		$table = self::get_table_or_error( absint( $request->get_param( 'id' ) ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$ut      = self::get_user_table( $table['slug'] );
		$records = $wpdb->get_results( "SELECT * FROM `{$ut}` ORDER BY id ASC", ARRAY_A );

		// Build headers from column definitions.
		$headers = array( 'id' );
		foreach ( $table['columns'] as $col ) {
			$headers[] = $col['name'];
		}
		$headers[] = 'created_at';

		// Build CSV rows.
		$csv_rows = array( $headers );
		foreach ( $records as $rec ) {
			$row = array( $rec['id'] ?? '' );
			foreach ( $table['columns'] as $col ) {
				$row[] = $rec[ $col['slug'] ] ?? '';
			}
			$row[]     = $rec['created_at'] ?? '';
			$csv_rows[] = $row;
		}

		// Encode to CSV string.
		ob_start();
		$fh = fopen( 'php://output', 'w' );
		foreach ( $csv_rows as $r ) {
			fputcsv( $fh, $r );
		}
		fclose( $fh );
		$csv = ob_get_clean();

		return rest_ensure_response( array( 'csv' => $csv, 'filename' => sanitize_title( $table['name'] ) . '.csv' ) );
	}

	// ── Shortcode ────────────────────────────────────────────────────────────

	/**
	 * [wpbot_table id="1" filter_col="status" filter_val="active" limit="50"]
	 */
	public static function render_shortcode( $atts ) {
		global $wpdb;

		$atts = shortcode_atts( array(
			'id'             => 0,
			'filter_col'     => '',
			'filter_val'     => '',
			'limit'          => 100,
			'title'          => 'yes',
			'trigger_button' => 'no',
			'button_label'   => 'Trigger Workflow',
		), $atts, 'wpbot_table' );

		$id = absint( $atts['id'] );
		if ( ! $id ) {
			return '<p style="color:red;">[wpbot_table] error: id is required.</p>';
		}

		$table = self::get_table_or_error( $id );
		if ( is_wp_error( $table ) ) {
			return '<p style="color:red;">[wpbot_table] error: Table not found.</p>';
		}

		$ut          = self::get_user_table( $table['slug'] );
		$filter_col  = sanitize_key( $atts['filter_col'] );
		$filter_val  = sanitize_text_field( $atts['filter_val'] );
		$limit       = min( max( absint( $atts['limit'] ), 1 ), 1000 );
		$valid_slugs = wp_list_pluck( $table['columns'], 'slug' );

		$where        = '';
		$query_params = array();
		if ( $filter_col && $filter_val !== '' && in_array( $filter_col, $valid_slugs, true ) ) {
			$where          = 'WHERE `' . esc_sql( $filter_col ) . '` LIKE %s';
			$query_params[] = '%' . $wpdb->esc_like( $filter_val ) . '%';
		}

		$sql     = "SELECT * FROM `{$ut}` {$where} ORDER BY id DESC LIMIT %d";
		$params  = array_merge( $query_params, array( $limit ) );
		$records = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );

		$show_btn    = ( 'yes' === $atts['trigger_button'] );
		$btn_label   = esc_html( $atts['button_label'] );
		$rest_base   = esc_url( rest_url( 'wpbot-automator/v1/tables/' . $id . '/records' ) );
		$trigger_nonce = $show_btn ? wp_create_nonce( 'wpbot_table_trigger_' . $id ) : '';

		// Build HTML.
		ob_start();
		?>
		<div class="wpbot-table-wrap" style="overflow-x:auto;font-family:inherit;">
			<?php if ( 'yes' === $atts['title'] ) : ?>
				<h3 style="margin-bottom:10px;"><?php echo esc_html( $table['name'] ); ?></h3>
			<?php endif; ?>
			<?php if ( $show_btn ) : ?>
			<script>
			function wpbotTrigger(rowId, btn) {
				btn.disabled = true;
				var orig = btn.textContent;
				btn.textContent = '…';
				fetch('<?php echo $rest_base; ?>/' + rowId + '/trigger-workflow', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ nonce: '<?php echo esc_js( $trigger_nonce ); ?>' })
				}).then(function(r){ return r.json(); }).then(function(d){
					btn.textContent = d.success ? '✓' : '✗';
					setTimeout(function(){ btn.disabled = false; btn.textContent = orig; }, 2500);
				}).catch(function(){
					btn.textContent = '✗';
					setTimeout(function(){ btn.disabled = false; btn.textContent = orig; }, 2500);
				});
			}
			</script>
			<?php endif; ?>
			<?php if ( empty( $records ) ) : ?>
				<p style="color:#666;">No records found.</p>
			<?php else : ?>
				<table style="width:100%;border-collapse:collapse;font-size:14px;">
					<thead>
						<tr>
							<?php foreach ( $table['columns'] as $col ) : ?>
								<th style="text-align:left;padding:8px 12px;background:#f5f5f5;border:1px solid #ddd;">
									<?php echo esc_html( $col['name'] ); ?>
								</th>
							<?php endforeach; ?>
							<?php if ( $show_btn ) : ?>
								<th style="text-align:left;padding:8px 12px;background:#f5f5f5;border:1px solid #ddd;"></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $records as $rec ) : ?>
							<tr>
								<?php foreach ( $table['columns'] as $col ) : ?>
									<td style="padding:8px 12px;border:1px solid #eee;vertical-align:top;">
										<?php echo esc_html( $rec[ $col['slug'] ] ?? '' ); ?>
									</td>
								<?php endforeach; ?>
								<?php if ( $show_btn ) : ?>
									<td style="padding:6px 12px;border:1px solid #eee;text-align:center;">
										<button
											onclick="wpbotTrigger(<?php echo (int) $rec['id']; ?>, this)"
											style="background:#3b82f6;color:#fff;border:none;padding:5px 12px;border-radius:4px;cursor:pointer;font-size:12px;">
											<?php echo $btn_label; ?>
										</button>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// ── Public helpers (used by Tables_Actions) ─────────────────────────────

	/**
	 * Load table meta by ID, returning decoded array or null.
	 * Used by Tables_Actions and shortcode.
	 */
	public static function get_table_meta_by_id( $id ) {
		global $wpdb;
		$meta = self::get_meta_table();
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$meta} WHERE id = %d", absint( $id ) ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$row['columns'] = json_decode( $row['columns_json'], true ) ?: array();
		unset( $row['columns_json'] );
		return $row;
	}

	/**
	 * Public alias for extract_record_data — called by Tables_Actions.
	 */
	public static function extract_record_data_public( array $raw, array $columns ) {
		return self::extract_record_data( $raw, $columns );
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	/** Load table meta (with decoded columns) or return WP_Error. */
	private static function get_table_or_error( $id ) {
		global $wpdb;
		$meta = self::get_meta_table();
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$meta} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Table not found.', array( 'status' => 404 ) );
		}

		$row['columns'] = json_decode( $row['columns_json'], true ) ?: array();
		unset( $row['columns_json'] );
		return $row;
	}

	/** Filter incoming record data to only valid column slugs, sanitizing values. */
	private static function extract_record_data( array $raw, array $columns ) {
		$valid = array();
		foreach ( $columns as $col ) {
			if ( array_key_exists( $col['slug'], $raw ) ) {
				$val = $raw[ $col['slug'] ];
				switch ( $col['type'] ) {
					case 'number':
						$valid[ $col['slug'] ] = is_numeric( $val ) ? (float) $val : null;
						break;
					case 'boolean':
						$valid[ $col['slug'] ] = ( $val === true || $val === '1' || $val === 1 ) ? 1 : 0;
						break;
					default:
						$valid[ $col['slug'] ] = sanitize_textarea_field( (string) $val );
				}
			}
		}
		return $valid;
	}

	/** Create the MySQL data table using dbDelta. */
	private static function create_mysql_table( $slug, array $columns ) {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::get_user_table( $slug );

		$col_defs = array();
		foreach ( $columns as $col ) {
			$sql_type  = self::column_type_to_sql( $col['type'] );
			$not_null  = $col['required'] ? 'NOT NULL' : 'NULL';
			$default   = ( 'boolean' === $col['type'] ) ? '' : "DEFAULT NULL";
			$col_defs[] = "`{$col['slug']}` {$sql_type} {$not_null} {$default}";
		}

		$cols_sql = implode( ",\n\t\t\t", $col_defs );
		$sql      = "CREATE TABLE IF NOT EXISTS `{$table}` (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			{$cols_sql},
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		// Verify the table was created.
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
			return new \WP_Error( 'create_failed', 'Could not create database table: ' . $wpdb->last_error, array( 'status' => 500 ) );
		}

		return true;
	}

	/** DROP the MySQL data table. */
	private static function drop_mysql_table( $slug ) {
		global $wpdb;
		$table = self::get_user_table( $slug );
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	/** Map a UI column type to a MySQL column type. */
	private static function column_type_to_sql( $type ) {
		$map = array(
			'text'     => 'VARCHAR(500)',
			'textarea' => 'TEXT',
			'number'   => 'DECIMAL(18,4)',
			'email'    => 'VARCHAR(255)',
			'url'      => 'VARCHAR(500)',
			'date'     => 'DATE',
			'datetime' => 'DATETIME',
			'boolean'  => 'TINYINT(1) NOT NULL DEFAULT 0',
		);
		return $map[ $type ] ?? 'VARCHAR(500)';
	}

	/** Sanitize column type to one of the known values. */
	private static function sanitize_col_type( $type ) {
		$valid = array( 'text', 'textarea', 'number', 'email', 'url', 'date', 'datetime', 'boolean' );
		return in_array( $type, $valid, true ) ? $type : 'text';
	}

	/** Convert a human name to a safe SQL column slug. */
	private static function to_column_slug( $name ) {
		$slug = strtolower( trim( $name ) );
		$slug = preg_replace( '/[^a-z0-9]+/', '_', $slug );
		$slug = trim( $slug, '_' );
		$slug = substr( $slug, 0, 50 );
		if ( empty( $slug ) || is_numeric( $slug[0] ) ) {
			$slug = 'col_' . $slug;
		}
		return $slug ?: 'col_' . uniqid();
	}

	/** Convert a human name to a safe SQL table slug. */
	private static function to_table_slug( $name ) {
		$slug = strtolower( trim( $name ) );
		$slug = preg_replace( '/[^a-z0-9]+/', '_', $slug );
		$slug = trim( $slug, '_' );
		$slug = substr( $slug, 0, 30 );
		return $slug ?: 'table_' . time();
	}
}
