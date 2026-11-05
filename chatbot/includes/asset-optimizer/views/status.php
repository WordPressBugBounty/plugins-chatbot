<?php
/**
 * Status panel of the Performance tab.
 *
 * Variables: $module (Module), $status (array).
 *
 * @package QuantumCloud\AssetOptimizer
 */

defined( 'ABSPATH' ) || exit;

$qcld_wpbot_ao_reasons = array(
	''             => __( 'On', 'chatbot' ),
	'setting'      => __( 'Off', 'chatbot' ),
	'constant'     => __( 'Off (WPBOT_ASSET_CACHE_DISABLE)', 'chatbot' ),
	'script_debug' => __( 'Off (SCRIPT_DEBUG)', 'chatbot' ),
);
?>
<div class="cxsc-settings-blocks qcld-wpbot-ao-status">
	<h4 class="qc-opt-title"><?php esc_html_e( 'Status', 'chatbot' ); ?></h4>
	<table class="widefat striped">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Asset cache', 'chatbot' ); ?></th>
				<td><?php echo esc_html( isset( $qcld_wpbot_ao_reasons[ $status['kill_switch'] ] ) ? $qcld_wpbot_ao_reasons[ $status['kill_switch'] ] : '' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cache folder', 'chatbot' ); ?></th>
				<td>
					<code><?php echo esc_html( $status['folder'] ); ?></code>
					<?php if ( $status['writable'] ) : ?>
						<span class="qcld-wpbot-ao-ok"><?php esc_html_e( 'Writable', 'chatbot' ); ?></span>
					<?php else : ?>
						<span class="qcld-wpbot-ao-bad"><?php esc_html_e( 'Not writable: the original files are served', 'chatbot' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cached files', 'chatbot' ); ?></th>
				<td class="qcld-wpbot-ao-count">
					<?php
					/* translators: 1: number of files, 2: total size such as "120 KB". */
					echo esc_html( sprintf( _n( '%1$d file, %2$s', '%1$d files, %2$s', (int) $status['count'], 'chatbot' ), (int) $status['count'], size_format( (int) $status['bytes'] ) ? size_format( (int) $status['bytes'] ) : '0 B' ) );
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Caching plugins', 'chatbot' ); ?></th>
				<td><?php echo esc_html( array() === $status['detected'] ? __( 'None detected', 'chatbot' ) : implode( ', ', $status['detected'] ) ); ?></td>
			</tr>
			<?php if ( $status['nginx'] ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Browser caching', 'chatbot' ); ?></th>
					<td>
						<?php esc_html_e( 'Your server runs nginx, which ignores .htaccess. To let browsers keep the cached files for 30 days, add this to your nginx site configuration (or ask your host):', 'chatbot' ); ?>
						<pre class="qcld-wpbot-ao-snippet"><?php echo esc_html( 'location ~* ^' . str_replace( '.', '\\.', $status['folder_url_path'] ) . '/.+\.(js|css)$ {' . "\n\texpires 30d;\n\tadd_header Cache-Control \"public\";\n}" ); ?></pre>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ( '' !== $status['last_error'] ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Last problem', 'chatbot' ); ?></th>
					<td><?php echo esc_html( $status['last_error'] ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( array() !== $status['files'] ) : ?>
		<h4 class="qc-opt-title"><?php esc_html_e( 'Savings per file', 'chatbot' ); ?></h4>
		<table class="widefat striped qcld-wpbot-ao-files">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'File', 'chatbot' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Original', 'chatbot' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Cached', 'chatbot' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Saved', 'chatbot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $status['files'] as $qcld_wpbot_ao_file ) : ?>
					<?php
					$qcld_wpbot_ao_original = isset( $qcld_wpbot_ao_file['original'] ) ? (int) $qcld_wpbot_ao_file['original'] : 0;
					$qcld_wpbot_ao_size     = isset( $qcld_wpbot_ao_file['size'] ) ? (int) $qcld_wpbot_ao_file['size'] : 0;
					?>
					<tr>
						<td><code><?php echo esc_html( isset( $qcld_wpbot_ao_file['path'] ) ? $qcld_wpbot_ao_file['path'] : '' ); ?></code></td>
						<td><?php echo esc_html( size_format( $qcld_wpbot_ao_original, 1 ) ); ?></td>
						<td><?php echo esc_html( size_format( $qcld_wpbot_ao_size, 1 ) ); ?></td>
						<td>
							<?php
							$qcld_wpbot_ao_saved = $qcld_wpbot_ao_original > 0 ? (int) round( 100 - ( 100 * $qcld_wpbot_ao_size / $qcld_wpbot_ao_original ) ) : 0;
							// Already-minified files are copied as they are (plus the guard's one-line marker).
							echo esc_html( $qcld_wpbot_ao_saved > 0 ? $qcld_wpbot_ao_saved . '%' : __( 'already minified', 'chatbot' ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php
	foreach ( array(
		'repaired' => __( 'Repaired files (serving the original)', 'chatbot' ),
		'skipped'  => __( 'Files kept as they are', 'chatbot' ),
	) as $qcld_wpbot_ao_list => $qcld_wpbot_ao_title ) :
		?>
		<?php if ( array() !== $status[ $qcld_wpbot_ao_list ] ) : ?>
			<h4 class="qc-opt-title"><?php echo esc_html( $qcld_wpbot_ao_title ); ?></h4>
			<ul class="qcld-wpbot-ao-log">
				<?php foreach ( $status[ $qcld_wpbot_ao_list ] as $qcld_wpbot_ao_id => $qcld_wpbot_ao_entry ) : ?>
					<li><code><?php echo esc_html( $qcld_wpbot_ao_id ); ?></code> <?php echo esc_html( isset( $qcld_wpbot_ao_entry['reason'] ) ? $qcld_wpbot_ao_entry['reason'] : '' ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endforeach; ?>

	<p class="qcld-wpbot-ao-actions">
		<button type="button" class="button button-secondary" data-qcld-wpbot-ao-action="clear"><?php esc_html_e( 'Clear WPBot asset cache', 'chatbot' ); ?></button>
		<button type="button" class="button button-secondary" data-qcld-wpbot-ao-action="rebuild"><?php esc_html_e( 'Rebuild now', 'chatbot' ); ?></button>
		<span class="qcld-wpbot-ao-result" role="status" aria-live="polite"></span>
	</p>
</div>
