<?php
/**
 * Shared admin shell for every WPBot Growth Suite module (brief 2.4): the
 * host's page header, WordPress's notices marker, a left menu inside the
 * page and the content area, in the look of WPBot's AI Marketing screen.
 *
 * - Every menu item is a real link with its own URL, so links, new tabs
 *   and the back button work. A single-page app can take over a click
 *   (assets/js/shell.js fires a cancelable "wpbot-shell:navigate" event)
 *   and the address still changes.
 * - Items can show a count badge, and "Pro" items can be shown inactive
 *   (they open a short preview page the module renders).
 * - On narrow screens the menu becomes a select menu.
 * - Colours come from the host's brand values (no new hard-coded colours).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Admin;

use QuantumCloud\WPBotCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Admin shell.
 */
final class Shell {

	const HANDLE = 'qcld-wpbot-core-shell';

	/**
	 * Load the shell's style and script (call from admin_enqueue_scripts).
	 *
	 * @return void
	 */
	public static function enqueue() {
		$version = defined( 'QCLD_WPBOT_CORE_VERSION' ) ? QCLD_WPBOT_CORE_VERSION : false;
		wp_enqueue_style( 'dashicons' );
		Controls::enqueue();
		wp_enqueue_style( self::HANDLE, Core::url( 'assets/css/shell.css' ), array( 'dashicons', Controls::HANDLE ), $version );
		wp_enqueue_script( self::HANDLE, Core::url( 'assets/js/shell.js' ), array(), $version, true );
	}

	/**
	 * Clean menu items.
	 *
	 * Each item: id, label, url, icon (dashicons class), badge (number or
	 * short text; empty for none), pro (bool: an inactive Pro preview),
	 * hidden (bool).
	 *
	 * @param array $items Items.
	 * @return array[]
	 */
	public static function items( array $items ) {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || empty( $item['label'] ) || ! empty( $item['hidden'] ) ) {
				continue;
			}
			$out[] = array(
				'id'    => sanitize_key( $item['id'] ),
				'label' => (string) $item['label'],
				'url'   => isset( $item['url'] ) ? (string) $item['url'] : '',
				'icon'  => isset( $item['icon'] ) ? sanitize_html_class( $item['icon'] ) : 'dashicons-admin-page',
				'badge' => isset( $item['badge'] ) && '' !== (string) $item['badge'] && '0' !== (string) $item['badge'] ? substr( (string) $item['badge'], 0, 6 ) : '',
				'pro'   => ! empty( $item['pro'] ),
			);
		}
		return $out;
	}

	/**
	 * Print the shell.
	 *
	 * @param array $args module (id), label (menu name for screen readers),
	 *                    items, current (item id), header (callable that
	 *                    prints the host header), brand (host brand values),
	 *                    content (callable that prints the content area).
	 * @return void
	 */
	public static function render( array $args ) {
		$module  = isset( $args['module'] ) ? sanitize_key( $args['module'] ) : 'module';
		$items   = self::items( isset( $args['items'] ) ? (array) $args['items'] : array() );
		$current = isset( $args['current'] ) ? sanitize_key( $args['current'] ) : '';
		$label   = isset( $args['label'] ) ? (string) $args['label'] : '';
		$pro     = __( 'Pro', 'chatbot' );
		?>
		<div class="wrap qcwpbot-shell" data-module="<?php echo esc_attr( $module ); ?>" style="<?php echo esc_attr( self::brand_vars( isset( $args['brand'] ) ? (array) $args['brand'] : array() ) ); ?>">
			<?php
			if ( isset( $args['header'] ) && is_callable( $args['header'] ) ) {
				call_user_func( $args['header'] );
			}
			?>
			<hr class="wp-header-end">
			<div class="qcwpbot-shell__layout">
				<nav class="qcwpbot-shell__nav" aria-label="<?php echo esc_attr( $label ); ?>">
					<label class="screen-reader-text" for="qcwpbot-shell-select-<?php echo esc_attr( $module ); ?>"><?php echo esc_html( $label ); ?></label>
					<select class="qcwpbot-shell__select" id="qcwpbot-shell-select-<?php echo esc_attr( $module ); ?>">
						<?php foreach ( $items as $item ) : ?>
							<option value="<?php echo esc_attr( $item['id'] ); ?>" data-url="<?php echo esc_url( $item['url'] ); ?>" <?php selected( $item['id'], $current ); ?>>
								<?php echo esc_html( $item['label'] . ( $item['pro'] ? ' (' . $pro . ')' : '' ) . ( '' !== $item['badge'] ? ' (' . $item['badge'] . ')' : '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<ul class="qcwpbot-shell__menu">
						<?php foreach ( $items as $item ) : ?>
							<li>
								<a
									class="qcwpbot-shell__item<?php echo $item['id'] === $current ? ' is-active' : ''; ?><?php echo $item['pro'] ? ' is-pro' : ''; ?>"
									href="<?php echo esc_url( $item['url'] ); ?>"
									data-item="<?php echo esc_attr( $item['id'] ); ?>"
									<?php echo $item['id'] === $current ? 'aria-current="page"' : ''; ?>
								>
									<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
									<span class="qcwpbot-shell__label"><?php echo esc_html( $item['label'] ); ?></span>
									<?php if ( $item['pro'] ) : ?>
										<span class="qcwpbot-shell__pro"><?php echo esc_html( $pro ); ?></span>
									<?php elseif ( '' !== $item['badge'] ) : ?>
										<span class="qcwpbot-shell__badge"><?php echo esc_html( $item['badge'] ); ?></span>
									<?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
				<div class="qcwpbot-shell__content">
					<?php
					if ( isset( $args['content'] ) && is_callable( $args['content'] ) ) {
						call_user_func( $args['content'] );
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Brand values as CSS variables for the shell.
	 *
	 * @param array $brand primary, primary_dark, primary_soft, text, muted, background, radius.
	 * @return string
	 */
	private static function brand_vars( array $brand ) {
		$map  = array(
			'primary'      => '--wpbot-shell-primary',
			'primary_dark' => '--wpbot-shell-primary-dark',
			'primary_soft' => '--wpbot-shell-primary-soft',
			'text'         => '--wpbot-shell-text',
			'muted'        => '--wpbot-shell-muted',
			'background'   => '--wpbot-shell-bg',
			'radius'       => '--wpbot-shell-radius',
		);
		$vars = '';
		foreach ( $map as $key => $var ) {
			if ( isset( $brand[ $key ] ) && preg_match( '/^(#[0-9a-fA-F]{3,8}|\d{1,3}px)$/', (string) $brand[ $key ] ) ) {
				$vars .= $var . ':' . $brand[ $key ] . ';';
			}
		}
		return $vars;
	}
}
