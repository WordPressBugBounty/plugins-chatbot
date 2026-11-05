<?php
/**
 * Help panel: "Using WPBot with a caching plugin".
 *
 * Variables: $module (Module), $status (array).
 *
 * @package QuantumCloud\AssetOptimizer
 */

defined( 'ABSPATH' ) || exit;

$qcld_wpbot_ao_root = wp_normalize_path( (string) $module->host()->get_root_dir() );
$qcld_wpbot_ao_abs  = wp_normalize_path( ABSPATH );
$qcld_wpbot_ao_show = static function ( $path ) use ( $qcld_wpbot_ao_abs ) {
	$path = trailingslashit( wp_normalize_path( $path ) );
	return 0 === strpos( $path, $qcld_wpbot_ao_abs ) ? substr( $path, strlen( $qcld_wpbot_ao_abs ) ) : $path;
};

/**
 * Filter the knowledge-base article linked from the help panel.
 *
 * @param string $url Article URL (a placeholder until the article is published).
 */
$qcld_wpbot_ao_kb = (string) apply_filters( 'wpbot_asset_cache_kb_url', 'https://wpbot.pro/docs/' );
?>
<div class="cxsc-settings-blocks qcld-wpbot-ao-help">
	<h4 class="qc-opt-title"><?php esc_html_e( 'Using WPBot with a caching plugin', 'chatbot' ); ?></h4>
	<ul>
		<li><strong><?php esc_html_e( 'Safe to keep on:', 'chatbot' ); ?></strong> <?php esc_html_e( 'page caching for logged-out visitors, GZIP or Brotli compression, browser caching, CDN, object cache, lazy-loading images.', 'chatbot' ); ?></li>
		<li><strong><?php esc_html_e( 'Never cache:', 'chatbot' ); ?></strong> <?php esc_html_e( 'pages for logged-in users, or admin-ajax.php and REST API responses. Chat replies and visitor details would be shared between visitors.', 'chatbot' ); ?></li>
		<li><strong><?php esc_html_e( 'Exclude WPBot from:', 'chatbot' ); ?></strong> <?php esc_html_e( 'Combine JS, Delay JavaScript until user interaction, and Remove unused CSS. These break proactive greetings, auto-open and chatbot styling.', 'chatbot' ); ?></li>
		<li><strong><?php esc_html_e( 'Pick one minifier:', 'chatbot' ); ?></strong> <?php esc_html_e( 'with WPBot\'s cache on, WPBot excludes its files from supported caching plugins automatically. If you\'d rather let your caching plugin minify, turn WPBot\'s cache off.', 'chatbot' ); ?></li>
		<li><strong><?php esc_html_e( 'After changing settings here, clear your page cache', 'chatbot' ); ?></strong> <?php esc_html_e( 'so visitors get the updated files.', 'chatbot' ); ?></li>
		<li>
			<strong><?php esc_html_e( 'Paths to exclude manually', 'chatbot' ); ?></strong> <?php esc_html_e( '(for unsupported plugins):', 'chatbot' ); ?>
			<code><?php echo esc_html( $qcld_wpbot_ao_show( $qcld_wpbot_ao_root ) ); ?></code>
			<?php esc_html_e( 'and', 'chatbot' ); ?>
			<code><?php echo esc_html( $qcld_wpbot_ao_show( dirname( $status['folder'] ) ) ); ?></code>
		</li>
	</ul>
	<?php if ( array() !== $status['detected'] ) : ?>
		<h4 class="qc-opt-title"><?php esc_html_e( 'Caching plugins on this site', 'chatbot' ); ?></h4>
		<ul class="qcld-wpbot-ao-detected">
			<?php foreach ( $status['detected'] as $qcld_wpbot_ao_slug => $qcld_wpbot_ao_name ) : ?>
				<li>
					<strong><?php echo esc_html( $qcld_wpbot_ao_name ); ?>:</strong>
					<?php
					if ( ! $status['enabled'] ) {
						esc_html_e( 'nothing added while WPBot\'s cache is off.', 'chatbot' );
					} elseif ( ! empty( $status['automatic'][ $qcld_wpbot_ao_slug ] ) ) {
						esc_html_e( 'WPBot excludes its files automatically.', 'chatbot' );
					} else {
						esc_html_e( 'exclude the two paths below by hand in its settings.', 'chatbot' );
					}
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php
	/**
	 * Print more help.
	 *
	 * @param array<string,mixed> $status Module status.
	 */
	do_action( 'qcld_wpbot_ao_render_help', $status );
	?>
	<p><a href="<?php echo esc_url( $qcld_wpbot_ao_kb ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Read the full guide', 'chatbot' ); ?></a></p>
</div>
