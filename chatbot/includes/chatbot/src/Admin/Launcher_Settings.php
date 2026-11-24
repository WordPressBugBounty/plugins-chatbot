<?php
/**
 * Settings for the chat button design (owner, 17 November 2026), shown by the hosts in their
 * Icons & Themes tab with do_action( 'qcld_wpbot_chat/launcher_settings' ), and the notice that
 * invites sites on the classic design to try the new one.
 *
 * The fields post with the host's settings form and are saved here (own nonce), so the module
 * does not depend on how a host saves its options.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

use QuantumCloud\Chatbot\Widget\Launcher_Design;
use QuantumCloud\Chatbot\Widget\Launcher_Icons;
use QuantumCloud\Chatbot\Widget\Launcher_Position;
use QuantumCloud\Chatbot\Widget\Launcher_Preview;
use QuantumCloud\Chatbot\Widget\Launcher_Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Launcher settings.
 */
final class Launcher_Settings {

	const NONCE   = 'qcld_wpbot_launcher_nonce';
	const ACTION  = 'qcld_wpbot_launcher_save';
	const FIELD   = 'qcld_wpbot_launcher';
	const SWITCH  = 'qcld_wpbot_launcher_switch';
	const DISMISS = 'qcld_wpbot_launcher_dismiss';
	const META    = 'qcld_wpbot_launcher_notice_dismissed';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'qcld_wpbot_chat/launcher_settings', array( __CLASS__, 'render' ) );
		// After the hosts' own save on admin_init (WPBot Free turns the floating box off when its form has no such field).
		add_action( 'admin_init', array( __CLASS__, 'maybe_save' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_' . self::SWITCH, array( __CLASS__, 'switch_design' ) );
		add_action( 'admin_post_' . self::DISMISS, array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * Who may change the design.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Capability for the chat button design settings.
		 *
		 * @param string $capability Capability.
		 */
		return (string) apply_filters( 'qcld_wpbot_chat/launcher_settings_capability', 'manage_options' );
	}

	/**
	 * The designer's choices: value => [ name, description ].
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'finish' => array(
				'smoked'  => array( __( 'Smoked glass', 'chatbot' ), __( 'Dark glass with a rim light in your color. Strong on dark and colorful pages.', 'chatbot' ) ),
				'clear'   => array( __( 'Clear glass', 'chatbot' ), __( 'Light frosted glass; the icon and buttons in your color.', 'chatbot' ) ),
				'sphere'  => array( __( 'Glossy sphere', 'chatbot' ), __( 'A see-through glossy sphere tinted with your color, so the icon shows; the box is clear glass.', 'chatbot' ) ),
				'marble'  => array( __( 'Glass marble', 'chatbot' ), __( 'A clear glass ball with your color only at the rim; the icon floats in the middle.', 'chatbot' ) ),
				'bubble'  => array( __( 'Soap bubble', 'chatbot' ), __( 'Almost clear, with a rainbow rim. The lightest look.', 'chatbot' ) ),
				'frosted' => array( __( 'Frosted', 'chatbot' ), __( 'Frosted glass tinted with your color, white text.', 'chatbot' ) ),
				'liquid'  => array( __( 'Liquid glass', 'chatbot' ), __( 'Thick glass with a bright highlight and a polished rim.', 'chatbot' ) ),
				'ring'    => array( __( 'Glass bezel', 'chatbot' ), __( 'A solid button inside a frosted glass ring.', 'chatbot' ) ),
				'aurora'  => array( __( 'Aurora', 'chatbot' ), __( 'Glass over a soft glow of your color and cyan; the box has a glowing edge.', 'chatbot' ) ),
				'flat'    => array( __( 'Flat', 'chatbot' ), __( 'A solid color, no glass. Clean on plain white sites.', 'chatbot' ) ),
			),
			'style'  => array(
				'solid' => array( __( 'Solid', 'chatbot' ), __( 'The button in the finish, the icon in white.', 'chatbot' ) ),
				'white' => array( __( 'White', 'chatbot' ), __( 'A white button with the icon in your color.', 'chatbot' ) ),
				'bare'  => array( __( 'No button', 'chatbot' ), __( 'Just the icon, floating on the page.', 'chatbot' ) ),
			),
			'wait'   => array(
				'none'      => array( __( 'None', 'chatbot' ), __( 'Stays still.', 'chatbot' ) ),
				'liquid'    => array( __( 'Liquid', 'chatbot' ), __( 'Slowly changes shape, like a drop of water.', 'chatbot' ) ),
				'glow'      => array( __( 'Breathing glow', 'chatbot' ), __( 'A soft halo in your color brightens and fades.', 'chatbot' ) ),
				'float'     => array( __( 'Gentle float', 'chatbot' ), __( 'Drifts up and down a few pixels.', 'chatbot' ) ),
				'orbit'     => array( __( 'Orbit', 'chatbot' ), __( 'A small dot circles the button.', 'chatbot' ) ),
				'sweep'     => array( __( 'Light sweep', 'chatbot' ), __( 'A bright edge runs around the button.', 'chatbot' ) ),
				'shine'     => array( __( 'Shine', 'chatbot' ), __( 'A glint of light passes across.', 'chatbot' ) ),
				'heartbeat' => array( __( 'Heartbeat', 'chatbot' ), __( 'Two quick beats, then rest.', 'chatbot' ) ),
				'bounce'    => array( __( 'Bounce', 'chatbot' ), __( 'A playful hop.', 'chatbot' ) ),
				'label'     => array( __( 'Chat label', 'chatbot' ), __( 'Slides out "Chat with us", then tucks back in.', 'chatbot' ) ),
				'typing'    => array( __( 'Typing', 'chatbot' ), __( 'A small bubble with typing dots pops up.', 'chatbot' ) ),
				'wave'      => array( __( 'Hello wave', 'chatbot' ), __( 'The icon turns into a waving hand for a moment.', 'chatbot' ) ),
				'hi'        => array( __( 'Flip to "Hi!"', 'chatbot' ), __( 'The icon flips over to say "Hi!".', 'chatbot' ) ),
				'alive'     => array( __( 'Alive', 'chatbot' ), __( 'The icon gives a small hop now and then.', 'chatbot' ) ),
			),
			'enter'  => array(
				'rise' => array( __( 'Rise', 'chatbot' ), __( 'Fades in while moving up a little.', 'chatbot' ) ),
				'pop'  => array( __( 'Pop', 'chatbot' ), __( 'Grows in with a small overshoot.', 'chatbot' ) ),
				'drop' => array( __( 'Drop and bounce', 'chatbot' ), __( 'Drops in from above and settles.', 'chatbot' ) ),
				'spin' => array( __( 'Spin in', 'chatbot' ), __( 'Turns and grows into place.', 'chatbot' ) ),
				'roll' => array( __( 'Roll in', 'chatbot' ), __( 'Rolls in from the edge of the screen.', 'chatbot' ) ),
				'fade' => array( __( 'Fade', 'chatbot' ), __( 'Simply fades in.', 'chatbot' ) ),
				'none' => array( __( 'None', 'chatbot' ), __( 'Appears at once.', 'chatbot' ) ),
			),
			'open'   => array(
				'turn'     => array( __( 'Turn', 'chatbot' ), __( 'The icon turns into an X.', 'chatbot' ) ),
				'zoom'     => array( __( 'Zoom', 'chatbot' ), __( 'The icon grows away as the X appears.', 'chatbot' ) ),
				'flip'     => array( __( 'Flip', 'chatbot' ), __( 'The button flips over; the X is on the back.', 'chatbot' ) ),
				'slide'    => array( __( 'Slide', 'chatbot' ), __( 'The icon slides up and out; the X slides in from below.', 'chatbot' ) ),
				'squircle' => array( __( 'Soft square', 'chatbot' ), __( 'The circle becomes a rounded square with an X.', 'chatbot' ) ),
			),
			'layout' => array(
				'preview' => array( __( 'Chat preview', 'chatbot' ), __( 'The greeting and message as chat bubbles, the quick menu under them.', 'chatbot' ) ),
				'card'    => array( __( 'Card with quick replies', 'chatbot' ), __( 'Greeting, message and quick menu in one card.', 'chatbot' ) ),
				'pills'   => array( __( 'Floating pills', 'chatbot' ), __( 'The message card; each quick menu item a pill under it.', 'chatbot' ) ),
				'ask'     => array( __( 'Ask bar', 'chatbot' ), __( 'The message, a "Type your question" bar and the quick menu.', 'chatbot' ) ),
				'side'    => array( __( 'Side menu', 'chatbot' ), __( 'The message above the button, the quick menu beside it.', 'chatbot' ) ),
				'speech'  => array( __( 'Speech bubble', 'chatbot' ), __( 'A bubble straight from the button, as if the icon is talking.', 'chatbot' ) ),
				'compact' => array( __( 'Compact', 'chatbot' ), __( 'One short line next to the button.', 'chatbot' ) ),
				'off'     => array( __( 'Off', 'chatbot' ), __( 'No floating box (the same as "Disable Floating Notification Box").', 'chatbot' ) ),
			),
		);
	}

	/**
	 * The designer (inside the host's settings form): a live preview of the site and picker cards.
	 * The values post with the host's form in hidden fields and are saved by maybe_save().
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$t      = Launcher_Theme::get();
		$f      = self::FIELD;
		$masks  = array();
		$vector = array();
		foreach ( Launcher_Icons::ids() as $id ) {
			$masks[ $id ]  = Launcher_Icons::mask( $id );
			$vector[ $id ] = Launcher_Icons::svg( $id );
		}
		$config = array(
			'theme'    => $t,
			'choices'  => self::choices(),
			'icons'    => Launcher_Icons::names(),
			'svgs'     => $vector,
			'masks'    => $masks,
			'aliases'  => Launcher_Icons::HOST_ALIASES,
			'logo'     => Launcher_Theme::logo_url(),
			'logoIcon' => Launcher_Theme::LOGO_ICON,
			'uploads'  => Launcher_Theme::UPLOADED_ICONS,
			'preview'  => Launcher_Preview::url(),
			'position' => array(
				'side'    => Launcher_Position::side(),
				'offsets' => Launcher_Position::offsets(),
				'max'     => Launcher_Position::MAX_OFFSET,
			),
			'text'     => array(
				'design'      => __( 'Design', 'chatbot' ),
				'modern'      => __( 'New design', 'chatbot' ),
				'classic'     => __( 'Classic', 'chatbot' ),
				'icon'        => __( 'Icon', 'chatbot' ),
				'iconHelp'    => __( 'Pick the chat button icon: a picture (or your own upload), or an icon drawn in your color.', 'chatbot' ),
				'yours'       => __( 'Pictures', 'chatbot' ),
				'upload'      => __( 'Upload your own icon', 'chatbot' ),
				'drawn'       => __( 'More icons drawn in your color', 'chatbot' ),
				'button'      => __( 'Button', 'chatbot' ),
				'finish'      => __( 'Finish', 'chatbot' ),
				'style'       => __( 'Style', 'chatbot' ),
				'color'       => __( 'Color', 'chatbot' ),
				'size'        => __( 'Size', 'chatbot' ),
				'motion'      => __( 'Motion', 'chatbot' ),
				'animate'     => __( 'Animate the chat button', 'chatbot' ),
				'enter'       => __( 'Page load', 'chatbot' ),
				'wait'        => __( 'While it waits', 'chatbot' ),
				'open'        => __( 'Open and close', 'chatbot' ),
				'box'         => __( 'Floating box', 'chatbot' ),
				'typing'      => __( 'Typing dots before the message', 'chatbot' ),
				'replay'      => __( 'Replay page load', 'chatbot' ),
				'toggle'      => __( 'Open or close the chat', 'chatbot' ),
				'phone'       => __( 'Phone width', 'chatbot' ),
				'preview'     => __( 'Live preview of your site', 'chatbot' ),
				'classicNote' => __( 'The classic design keeps the chat button WPBot always had. The options below are for the new design: picking one switches to it.', 'chatbot' ),
				'reduced'     => __( 'Visitors who turn off animations on their device always see a still button.', 'chatbot' ),
				'position'    => __( 'Position', 'chatbot' ),
				'posHelp'     => __( 'Where the chat button sits on the page. The chat window opens on the same side.', 'chatbot' ),
				'side'        => __( 'Side of the screen', 'chatbot' ),
				'right'       => __( 'Right', 'chatbot' ),
				'left'        => __( 'Left', 'chatbot' ),
				'fromSide'    => __( 'From the side', 'chatbot' ),
				'fromBottom'  => __( 'From the bottom', 'chatbot' ),
				'phoneNote'   => __( 'Phones use their own distances (Override Icon\'s Position for Mobile Phone).', 'chatbot' ),
			),
		);
		wp_nonce_field( self::ACTION, self::NONCE );
		?>
		<style><?php echo Launcher_Design::asset( 'assets/admin/launcher-designer.css' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the module's own file. ?></style>
		<div class="qcld-wpbot-designer" id="qcld-wpbot-designer">
			<h4 class="qc-opt-title"><?php esc_html_e( 'Chat button and floating box', 'chatbot' ); ?></h4>
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[design]" data-key="design" value="<?php echo esc_attr( $t['design'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[finish]" data-key="finish" value="<?php echo esc_attr( $t['finish'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[style]" data-key="style" value="<?php echo esc_attr( $t['style'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[icon]" data-key="icon" value="<?php echo esc_attr( $t['icon'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[color]" data-key="color" value="<?php echo esc_attr( $t['color'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[size]" data-key="size" value="<?php echo esc_attr( (string) $t['size'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[motion][enabled]" data-key="motion.enabled" value="<?php echo $t['motion']['enabled'] ? '1' : '0'; ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[motion][wait]" data-key="motion.wait" value="<?php echo esc_attr( $t['motion']['wait'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[motion][enter]" data-key="motion.enter" value="<?php echo esc_attr( $t['motion']['enter'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[motion][open]" data-key="motion.open" value="<?php echo esc_attr( $t['motion']['open'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[box][layout]" data-key="box.layout" id="qcld-wpbot-launcher-box" value="<?php echo esc_attr( $t['box']['layout'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[box][typing]" data-key="box.typing" value="<?php echo $t['box']['typing'] ? '1' : '0'; ?>">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[position][side]" data-key="position.side" value="<?php echo esc_attr( Launcher_Position::side() ); ?>">
			<div class="qwd-layout">
				<div class="qwd-preview">
					<div class="qwd-frame-wrap"><iframe class="qwd-frame" title="<?php esc_attr_e( 'Live preview of your site', 'chatbot' ); ?>" loading="lazy"></iframe></div>
					<div class="qwd-tools"></div>
				</div>
				<div class="qwd-panel"></div>
			</div>
			<script type="application/json" class="qwd-config"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < and & escaped. ?></script>
		</div>
		<script><?php echo Launcher_Design::asset( 'assets/admin/launcher-designer.js' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the module's own file. ?></script>
		<?php
	}

	/**
	 * Save the fields when the host's settings form posts them.
	 *
	 * @return void
	 */
	public static function maybe_save() {
		if ( empty( $_POST[ self::NONCE ] ) || empty( $_POST[ self::FIELD ] ) || ! is_array( $_POST[ self::FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::ACTION ) || ! current_user_can( self::capability() ) ) {
			return;
		}
		Launcher_Theme::save( self::from_post( wp_unslash( $_POST[ self::FIELD ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is checked by from_post() and Launcher_Theme::sanitize().
		// The side is its own option; the distances are the hosts' fields (wp_chatbot_position_x / _y), saved by the hosts.
		$post = wp_unslash( $_POST[ self::FIELD ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only position.side is read, and save_side() accepts right or left.
		if ( isset( $post['position']['side'] ) && is_scalar( $post['position']['side'] ) ) {
			Launcher_Position::save_side( sanitize_key( (string) $post['position']['side'] ) );
		}
	}

	/**
	 * The posted fields as a theme (only the keys the form has).
	 *
	 * @param array $post Posted fields.
	 * @return array
	 */
	public static function from_post( array $post ) {
		$theme = array();
		foreach ( array( 'design', 'finish', 'style', 'color', 'size' ) as $key ) {
			if ( isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ) {
				$theme[ $key ] = sanitize_text_field( (string) $post[ $key ] );
			}
		}
		if ( isset( $post['icon'] ) && is_scalar( $post['icon'] ) ) {
			$theme['icon'] = sanitize_key( (string) $post['icon'] );
		}
		if ( isset( $post['motion'] ) && is_array( $post['motion'] ) ) {
			$theme['motion'] = array();
			if ( isset( $post['motion']['enabled'] ) ) {
				$theme['motion']['enabled'] = '1' === (string) $post['motion']['enabled'];
			}
			foreach ( array( 'wait', 'enter', 'open' ) as $key ) {
				if ( isset( $post['motion'][ $key ] ) && is_scalar( $post['motion'][ $key ] ) ) {
					$theme['motion'][ $key ] = sanitize_key( (string) $post['motion'][ $key ] );
				}
			}
		}
		if ( isset( $post['box'] ) && is_array( $post['box'] ) ) {
			$theme['box'] = array();
			if ( isset( $post['box']['layout'] ) && is_scalar( $post['box']['layout'] ) ) {
				$theme['box']['layout'] = sanitize_key( (string) $post['box']['layout'] );
			}
			if ( isset( $post['box']['typing'] ) ) {
				$theme['box']['typing'] = '1' === (string) $post['box']['typing'];
			}
		}
		return $theme;
	}

	/**
	 * On WPBot screens: invite sites on the classic design to try the new one.
	 *
	 * @return void
	 */
	public static function notice() {
		if ( ! current_user_can( self::capability() ) || Launcher_Theme::modern() || get_user_meta( get_current_user_id(), self::META, true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen this is.
		if ( false === strpos( $page, 'wpbot' ) && false === strpos( $page, 'chatbot' ) && ! ( $screen && false !== strpos( (string) $screen->id, 'wpbot' ) ) ) {
			return;
		}
		$switch  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::SWITCH ), self::SWITCH );
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS ), self::DISMISS );
		echo '<div class="notice notice-info qcld-wpbot-launcher-notice"><p><strong>' . esc_html__( 'Try the new chat button', 'chatbot' ) . '</strong> ' .
			esc_html__( 'WPBot has a new chat button design: your icon on a glass button in your color, gentle motion, and a floating box that previews the chat. The icon you selected or uploaded is kept, and you can switch back any time in Icons & Themes.', 'chatbot' ) .
			'</p><p><a class="button button-primary" href="' . esc_url( $switch ) . '">' . esc_html__( 'Switch to the new design', 'chatbot' ) . '</a> <a class="button" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Not now', 'chatbot' ) . '</a></p></div>';
	}

	/**
	 * "Switch to the new design".
	 *
	 * @return void
	 */
	public static function switch_design() {
		check_admin_referer( self::SWITCH );
		if ( current_user_can( self::capability() ) ) {
			Launcher_Theme::save( array( 'design' => 'modern' ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * "Not now".
	 *
	 * @return void
	 */
	public static function dismiss() {
		check_admin_referer( self::DISMISS );
		update_user_meta( get_current_user_id(), self::META, 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
