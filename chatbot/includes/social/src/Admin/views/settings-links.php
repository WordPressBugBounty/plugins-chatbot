<?php
/**
 * Settings › Links & tracking (screen 23).
 *
 * @package QuantumCloud\Social
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="qcsoc-grid" data-qcsoc="settings">
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'Tracking tags', 'chatbot' ); ?></h3>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Add tracking tags to links to this site', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php echo esc_html( \QuantumCloud\Social\Module::MODE_MASTER === \QuantumCloud\Social\Module::instance()->mode() ? __( 'So Analytics can show which posts brought visitors, leads and sales. Links to other sites are left alone.', 'chatbot' ) : __( 'So your analytics tool, for example Google Analytics, can show which posts brought visitors. Links to other sites are left alone.', 'chatbot' ) ); ?></p></div>
			<label class="qcsoc-switch"><input type="checkbox" name="utm_enabled" data-qcsoc-setting <?php checked( $qcsoc_settings['utm_enabled'] ); ?>><span class="screen-reader-text"><?php esc_html_e( 'Add tracking tags', 'chatbot' ); ?></span></label>
		</div>
		<div class="qcsoc-row">
			<label for="qcsoc-medium"><strong><?php esc_html_e( 'Medium (utm_medium)', 'chatbot' ); ?></strong></label>
			<input type="text" id="qcsoc-medium" name="utm_medium" data-qcsoc-setting value="<?php echo esc_attr( $qcsoc_settings['utm_medium'] ); ?>" maxlength="30">
		</div>
	</section>
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'How links look', 'chatbot' ); ?></h3>
		<pre class="qcsoc-code">
		<?php
		echo esc_html(
			add_query_arg(
				array(
					'utm_source'   => 'facebook',
					'utm_medium'   => $qcsoc_settings['utm_medium'],
					'utm_campaign' => 'autumn-menu',
				),
				home_url( '/menu/' )
			)
		);
		?>
		</pre>
		<p class="qcsoc-muted"><?php esc_html_e( 'utm_source is the network, utm_campaign comes from the post title.', 'chatbot' ); ?></p>
	</section>
	<span class="qcsoc-status" data-qcsoc-status role="status"></span>
</div>
