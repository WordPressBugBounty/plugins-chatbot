<?php
/**
 * Chat window designer (owner, 20 November 2026; answer D): one "Chat window" designer under
 * Appearance, beside the chat button designer, with a live preview of the site. It replaces the
 * old theme grid and the Custom Color and Fonts sub-tab.
 *
 * The hosts print it with `do_action( 'qcld_wpbot_chat/window_settings' )` inside their settings
 * form; it saves with that form (one hidden field holding the whole theme as JSON, checked against
 * the window schema). The preview frame is the chat button designer's (Launcher_Preview): the
 * edited theme goes in the frame's address, nonce-checked, for users who may change the design.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

use QuantumCloud\Chatbot\Widget\Launcher_Design;
use QuantumCloud\Chatbot\Widget\Launcher_Preview;
use QuantumCloud\Chatbot\Window\Window_Designs;
use QuantumCloud\Chatbot\Window\Window_Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Chat window designer.
 */
final class Window_Settings {

	const NONCE  = 'qcld_wpbot_window_nonce';
	const ACTION = 'qcld_wpbot_window_save';
	const FIELD  = 'qcld_wpbot_window';
	const ARG    = 'qcld_wpbot_window';
	const OPEN   = 'qcld_wpbot_open';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'qcld_wpbot_chat/window_settings', array( __CLASS__, 'render' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_save' ), 20 );
	}

	/**
	 * Hooks for the preview frame (the front end, so outside the admin).
	 *
	 * @return void
	 */
	public static function preview_init() {
		add_filter( 'qcld_wpbot_chat/window_theme', array( __CLASS__, 'preview_theme' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'preview_frame' ), 20 );
	}

	/**
	 * Who may change the chat window (the chat button designer's capability).
	 *
	 * @return string
	 */
	public static function capability() {
		return Launcher_Settings::capability();
	}

	/**
	 * In the preview frame: the edited window theme instead of the saved one.
	 *
	 * @param array $theme Saved theme.
	 * @return array
	 */
	public static function preview_theme( $theme ) {
		if ( ! Launcher_Preview::active() || empty( $_GET[ self::ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in Launcher_Preview::active().
			return $theme;
		}
		$edited = json_decode( wp_unslash( (string) $_GET[ self::ARG ] ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked in active(); every value is checked by Window_Theme::sanitize().
		return is_array( $edited ) ? Window_Theme::sanitize( $edited ) : $theme;
	}

	/**
	 * In the preview frame: open the chat and take colour changes from the designer at once.
	 *
	 * @return void
	 */
	public static function preview_frame() {
		if ( ! Launcher_Preview::active() || empty( $_GET[ self::OPEN ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in Launcher_Preview::active().
			return;
		}
		add_action(
			'wp_footer',
			static function () {
				echo '<script>(function(){'
					// Open the chat once the conversation script is ready (it binds its handlers a moment after loading).
					// Then past the start screen (Pro home screen add-on), so the preview shows the window itself.
					. 'var tries=0;function past(){var s=document.querySelector(".wp-chatbot-start-container");var go=document.querySelector(".qcld_new_start_button");if(s&&s.offsetParent&&go){go.click();}}'
					. 'function open(){var b=document.getElementById("wp-chatbot-board-container");if(b&&b.classList.contains("active-chat-board")){past();return;}var f=document.querySelector("#wp-chatbot-ball .wp-chatbot-ball");if(f){f.click();}if(++tries<8){setTimeout(open,700);}else{past();}}'
					. 'window.addEventListener("load",function(){if(window.qcldWpbotChatLoad){window.qcldWpbotChatLoad(function(){setTimeout(open,400);});}else{setTimeout(open,700);}});'
					. 'window.addEventListener("message",function(e){var d=e.data;if(e.origin!==window.location.origin||!d||"qcld-wpbot-window"!==d.type){return;}'
					. 'var r=document.querySelector(".qcw");if(!r){return;}'
					. 'if(d.vars){Object.keys(d.vars).forEach(function(k){if(/^--qcw-[a-z-]+$/.test(k)&&/^(#[0-9a-f]{6}|[0-9]+(px|%)?)$/i.test(String(d.vars[k]))){r.style.setProperty(k,d.vars[k]);}});}'
					. 'if(d.mode&&/^(light|dark|auto)$/.test(d.mode)){r.setAttribute("data-qcw-mode",d.mode);}'
					. '});'
					. '})();</script>' . "\n";
			},
			101
		);
	}

	/**
	 * Labels for every choice in the designer.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function labels() {
		return array(
			'layout'           => array(
				'floating' => __( 'Floating', 'chatbot' ),
				'compact'  => __( 'Compact', 'chatbot' ),
				'side'     => __( 'Side panel', 'chatbot' ),
			),
			'side'             => array(
				'right' => __( 'Right', 'chatbot' ),
				'left'  => __( 'Left', 'chatbot' ),
			),
			'mode'             => array(
				'light' => __( 'Light', 'chatbot' ),
				'dark'  => __( 'Dark', 'chatbot' ),
				'auto'  => __( 'Follow the visitor', 'chatbot' ),
			),
			'material.type'    => array(
				'solid'  => __( 'Solid', 'chatbot' ),
				'glass'  => __( 'Glass', 'chatbot' ),
				'liquid' => __( 'Liquid glass', 'chatbot' ),
			),
			'material.inside'  => array(
				'none'   => __( 'None', 'chatbot' ),
				'blobs'  => __( 'Soft blobs', 'chatbot' ),
				'aurora' => __( 'Flowing colour', 'chatbot' ),
			),
			'material.header'  => array(
				'solid'       => __( 'Solid', 'chatbot' ),
				'gradient'    => __( 'Gradient', 'chatbot' ),
				'transparent' => __( 'Transparent', 'chatbot' ),
			),
			'motion.open'      => array(
				'morph'   => __( 'Morph from the button', 'chatbot' ),
				'fade'    => __( 'Fade', 'chatbot' ),
				'genie'   => __( 'Genie', 'chatbot' ),
				'liquid'  => __( 'Liquid', 'chatbot' ),
				'unfold'  => __( 'Unfold', 'chatbot' ),
				'spring'  => __( 'Spring', 'chatbot' ),
				'curtain' => __( 'Curtain', 'chatbot' ),
			),
			'motion.feel'      => array(
				'subtle'     => __( 'Subtle', 'chatbot' ),
				'expressive' => __( 'Expressive', 'chatbot' ),
				'off'        => __( 'Off', 'chatbot' ),
			),
			'motion.messages'  => array(
				'rise' => __( 'Rise', 'chatbot' ),
				'pop'  => __( 'Pop', 'chatbot' ),
				'blur' => __( 'Blur in', 'chatbot' ),
				'type' => __( 'Typewriter', 'chatbot' ),
				'none' => __( 'Instant', 'chatbot' ),
			),
			'motion.typing'    => array(
				'dots'    => __( 'Dots', 'chatbot' ),
				'wave'    => __( 'Wave', 'chatbot' ),
				'shimmer' => __( 'Shimmer', 'chatbot' ),
				'orb'     => __( 'Orb', 'chatbot' ),
				'orbit'   => __( 'Molecules', 'chatbot' ),
			),
			'motion.send'      => array(
				'ripple' => __( 'Ripple', 'chatbot' ),
				'fly'    => __( 'Fly', 'chatbot' ),
			),
			'suggestionsStyle' => array(
				'default'  => __( 'Buttons', 'chatbot' ),
				'cards'    => __( 'Cards', 'chatbot' ),
				'bubbles'  => __( 'Quick replies', 'chatbot' ),
				'numbered' => __( 'Numbered', 'chatbot' ),
				'links'    => __( 'Text links', 'chatbot' ),
			),
			'startMenuStyle'   => array(
				'default' => __( 'Buttons', 'chatbot' ),
				'tiles'   => __( 'Icon tiles', 'chatbot' ),
				'pills'   => __( 'Icon pills', 'chatbot' ),
				'tabs'    => __( 'Tabs', 'chatbot' ),
				'grid'    => __( 'Grid', 'chatbot' ),
			),
			'holo.header'      => array(
				'line'    => __( 'Line', 'chatbot' ),
				'panel'   => __( 'Panel', 'chatbot' ),
				'capsule' => __( 'Capsule', 'chatbot' ),
				'split'   => __( 'Split', 'chatbot' ),
			),
			'holo.replies'     => array(
				'cut'     => __( 'Cut corners', 'chatbot' ),
				'glass'   => __( 'Glass', 'chatbot' ),
				'outline' => __( 'Outline', 'chatbot' ),
				'minimal' => __( 'Minimal', 'chatbot' ),
			),
			'holo.send'        => array(
				'cut'    => __( 'Cut corners', 'chatbot' ),
				'circle' => __( 'Circle', 'chatbot' ),
				'arrow'  => __( 'Arrow', 'chatbot' ),
				'pill'   => __( 'Pill', 'chatbot' ),
			),
			'flags'            => array(
				'push'           => __( 'Push the page aside (Side panel)', 'chatbot' ),
				'customFonts'    => __( 'Use the fonts from the old Custom Fonts settings', 'chatbot' ),
				'motion.breathe' => __( 'Breathing agent picture', 'chatbot' ),
				'motion.scan'    => __( 'Scan line', 'chatbot' ),
				'motion.stagger' => __( 'Buttons come in one after another', 'chatbot' ),
				'menu.new'       => __( 'Start a new conversation', 'chatbot' ),
				'menu.email'     => __( 'Email me this chat', 'chatbot' ),
				'menu.download'  => __( 'Download the chat', 'chatbot' ),
				'menu.sound'     => __( 'Sound on or off', 'chatbot' ),
				'menu.text'      => __( 'Text size', 'chatbot' ),
				'menu.lang'      => __( 'Language', 'chatbot' ),
				'menu.person'    => __( 'Talk to a person', 'chatbot' ),
				'guide.bottom'   => __( 'Start menu bar above the message box (when the bottom start menu is on)', 'chatbot' ),
				'guide.page'     => __( '"Popular on this page" after the first question', 'chatbot' ),
				'guide.tryask'   => __( '"Try asking" in the message box (only on pages with good suggestions)', 'chatbot' ),
				'guide.composer' => __( 'Suggestions while the visitor types', 'chatbot' ),
				'guide.ghost'    => __( 'Finish my question (grey text, Tab to take it)', 'chatbot' ),
				'guide.follow'   => __( 'Follow-up buttons after answers', 'chatbot' ),
				'guide.nudge'    => __( 'A gentle nudge after 25 seconds with no message', 'chatbot' ),
				'guide.tip'      => __( 'First-time tip', 'chatbot' ),
			),
			'tokens'           => array(
				'accent'   => __( 'Accent', 'chatbot' ),
				'accentFg' => __( 'Text on accent', 'chatbot' ),
				'head'     => __( 'Header', 'chatbot' ),
				'headFg'   => __( 'Header text', 'chatbot' ),
				'bg'       => __( 'Window', 'chatbot' ),
				'fg'       => __( 'Text', 'chatbot' ),
				'bot'      => __( 'Bot bubble', 'chatbot' ),
				'botFg'    => __( 'Bot text', 'chatbot' ),
				'me'       => __( 'Visitor bubble', 'chatbot' ),
				'meFg'     => __( 'Visitor text', 'chatbot' ),
				'chip'     => __( 'Buttons and links', 'chatbot' ),
				'composer' => __( 'Message box', 'chatbot' ),
				'border'   => __( 'Borders', 'chatbot' ),
			),
		);
	}

	/**
	 * Interface texts.
	 *
	 * @return array<string,string>
	 */
	public static function texts() {
		return array(
			'title'       => __( 'Chat window', 'chatbot' ),
			'intro'       => __( 'Pick a design, then fine-tune it. The preview shows your own site; Save Settings to publish.', 'chatbot' ),
			'tabDesign'   => __( 'Design', 'chatbot' ),
			'tabLayout'   => __( 'Layout', 'chatbot' ),
			'tabColours'  => __( 'Colours', 'chatbot' ),
			'tabMaterial' => __( 'Material', 'chatbot' ),
			'tabMotion'   => __( 'Motion', 'chatbot' ),
			'tabGuidance' => __( 'Guidance', 'chatbot' ),
			'pro'         => __( 'Pro', 'chatbot' ),
			'proNote'     => __( 'Available in WPBot Pro.', 'chatbot' ),
			'layout'      => __( 'Layout', 'chatbot' ),
			'side'        => __( 'Side of the screen', 'chatbot' ),
			'width'       => __( 'Width', 'chatbot' ),
			'height'      => __( 'Height', 'chatbot' ),
			'sideWidth'   => __( 'Side panel width', 'chatbot' ),
			'radius'      => __( 'Corner radius', 'chatbot' ),
			'mode'        => __( 'Light or dark', 'chatbot' ),
			'brand'       => __( 'Brand colour', 'chatbot' ),
			'brandHelp'   => __( 'Builds the palette from one colour, with readable text on every part.', 'chatbot' ),
			'apply'       => __( 'Apply', 'chatbot' ),
			'light'       => __( 'Light palette', 'chatbot' ),
			'dark'        => __( 'Dark palette', 'chatbot' ),
			'resetColors' => __( 'Back to the design\'s colours', 'chatbot' ),
			'material'    => __( 'Material', 'chatbot' ),
			'blur'        => __( 'Blur', 'chatbot' ),
			'opacity'     => __( 'Opacity', 'chatbot' ),
			'header'      => __( 'Header', 'chatbot' ),
			'inside'      => __( 'Inside the window', 'chatbot' ),
			'open'        => __( 'Open and close', 'chatbot' ),
			'feel'        => __( 'Feel', 'chatbot' ),
			'messages'    => __( 'Messages appear', 'chatbot' ),
			'typing'      => __( 'While the bot thinks', 'chatbot' ),
			'send'        => __( 'Send button', 'chatbot' ),
			'extras'      => __( 'Extras', 'chatbot' ),
			'guidance'    => __( 'Helping visitors', 'chatbot' ),
			'menu'        => __( 'Three-dot menu', 'chatbot' ),
			'sgStyle'     => __( 'Suggestions style', 'chatbot' ),
			'smStyle'     => __( 'Start menu bar style', 'chatbot' ),
			'holo'        => __( 'Lightcycle', 'chatbot' ),
			'holoHeader'  => __( 'Header', 'chatbot' ),
			'holoReplies' => __( 'Replies', 'chatbot' ),
			'holoSend'    => __( 'Send button', 'chatbot' ),
			'fonts'       => __( 'Fonts', 'chatbot' ),
			'preview'     => __( 'Live preview of your site', 'chatbot' ),
			'phone'       => __( 'Phone width', 'chatbot' ),
			'previewDark' => __( 'Preview dark', 'chatbot' ),
			'reload'      => __( 'Reload the preview', 'chatbot' ),
			'designHelp'  => __( 'Choosing a design loads its colours, material and motion. Your brand colour is kept where the design uses it.', 'chatbot' ),
			'shotNote'    => __( 'A WPBot Pro design. This is a picture of it: your chat window does not change.', 'chatbot' ),
			/* translators: %s: design name, for example "Lightcycle". */
			'shotAlt'     => __( 'The %s chat window design', 'chatbot' ),
			'shotCta'     => __( 'See WPBot Pro', 'chatbot' ),
			'shotClose'   => __( 'Close', 'chatbot' ),
		);
	}

	/**
	 * The designer's data for the script.
	 *
	 * @return array
	 */
	public static function config() {
		$designs  = array();
		$defaults = array();
		foreach ( Window_Designs::all() as $id => $meta ) {
			$designs[ $id ]  = array(
				'name'        => $meta['name'],
				'description' => $meta['description'],
				'available'   => ! empty( $meta['available'] ),
				'pro'         => ! empty( $meta['pro'] ),
				'brand'       => isset( $meta['brand'] ) ? $meta['brand'] : array(),
				'own'         => ! empty( $meta['own'] ),
			);
			$defaults[ $id ] = Window_Theme::defaults( $id );
		}
		return array(
			'theme'    => Window_Theme::get(),
			'designs'  => $designs,
			'defaults' => $defaults,
			'choices'  => Window_Theme::CHOICES,
			'ranges'   => Window_Theme::RANGES,
			'pro'      => Window_Theme::pro(),
			'proOnly'  => self::pro_only(),
			'labels'   => self::labels(),
			'text'     => self::texts(),
			'preview'  => add_query_arg( self::OPEN, '1', Launcher_Preview::url() ),
			'arg'      => self::ARG,
			'shots'    => self::shots(),
			'proUrl'   => self::pro_url(),
		);
	}

	/**
	 * Pictures of the designs this site can't use yet, shown in a pop-up instead of the live preview
	 * (owner, 25 November 2026: "In Free can you show a screenshot of the different Pro themes when user
	 * clicks on them? don't load it in the preview window. Show a popup with the preview").
	 *
	 * @return array<string,string> Design id => image URL, for locked designs that have a picture.
	 */
	public static function shots() {
		$file  = defined( 'QCLD_WPBOT_CHAT_FILE' ) ? QCLD_WPBOT_CHAT_FILE : dirname( __DIR__, 2 ) . '/chatbot.php';
		$dir   = dirname( $file ) . '/assets/window/previews/';
		$shots = array();
		foreach ( Window_Designs::all() as $id => $meta ) {
			if ( ! empty( $meta['available'] ) || ! is_readable( $dir . $id . '.webp' ) ) {
				continue;
			}
			$shots[ $id ] = add_query_arg( 'ver', defined( 'QCLD_WPBOT_CHAT_VERSION' ) ? QCLD_WPBOT_CHAT_VERSION : '1', plugins_url( 'assets/window/previews/' . $id . '.webp', $file ) );
		}
		return $shots;
	}

	/**
	 * Where "See WPBot Pro" leads in the design pop-up.
	 *
	 * @return string
	 */
	public static function pro_url() {
		/**
		 * The WPBot Pro page linked from the design pop-up ('' hides the link).
		 *
		 * @param string $url URL.
		 */
		return esc_url_raw( (string) apply_filters( 'qcld_wpbot_chat/pro_url', 'https://www.wpbot.pro/pricing/' ) );
	}

	/**
	 * Values that need WPBot Pro, as "key" => list of values (or true for "anything but the free
	 * value"), for the designer to grey out.
	 *
	 * @return array
	 */
	public static function pro_only() {
		$out = array();
		foreach ( Window_Theme::PRO_VALUES as $key => $rule ) {
			$out[ $key ] = is_array( $rule ) ? array_keys( $rule ) : array( 'free' => $rule );
		}
		return $out;
	}

	/**
	 * Print the designer (inside the host's settings form).
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		wp_nonce_field( self::ACTION, self::NONCE );
		?>
		<style><?php echo Launcher_Design::asset( 'assets/window/designer.css' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the module's own file. ?></style>
		<div class="qcw-designer" id="qcw-designer">
			<input type="hidden" name="<?php echo esc_attr( self::FIELD ); ?>" class="qcwd-field" value="<?php echo esc_attr( wp_json_encode( Window_Theme::get() ) ); ?>">
			<div class="qcwd-head">
				<h4 class="qc-opt-title"><?php esc_html_e( 'Chat window', 'chatbot' ); ?></h4>
				<p class="qcwd-intro"><?php esc_html_e( 'Pick a design, then fine-tune it. The preview shows your own site; Save Settings to publish.', 'chatbot' ); ?></p>
			</div>
			<div class="qcwd-layout">
				<div class="qcwd-panel">
					<div class="qcwd-tabs" role="tablist"></div>
					<div class="qcwd-body"></div>
				</div>
				<div class="qcwd-preview">
					<div class="qcwd-tools"></div>
					<div class="qcwd-frame-wrap"><iframe class="qcwd-frame" title="<?php esc_attr_e( 'Live preview of your site', 'chatbot' ); ?>" loading="lazy"></iframe></div>
				</div>
			</div>
			<script type="application/json" class="qcwd-config"><?php echo wp_json_encode( self::config(), JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < and & escaped. ?></script>
		</div>
		<script><?php echo Launcher_Design::asset( 'assets/window/designer.js' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the module's own file. ?></script>
		<?php
	}

	/**
	 * Save with the host's settings form.
	 *
	 * @return void
	 */
	public static function maybe_save() {
		if ( empty( $_POST[ self::NONCE ] ) || empty( $_POST[ self::FIELD ] ) || ! is_string( $_POST[ self::FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::ACTION ) || ! current_user_can( self::capability() ) ) {
			return;
		}
		$theme = json_decode( wp_unslash( $_POST[ self::FIELD ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is checked by Window_Theme::sanitize().
		if ( is_array( $theme ) ) {
			self::save( $theme );
		}
	}

	/**
	 * Store a theme from the designer: checked against the schema, replacing the stored one.
	 *
	 * @param array $theme Theme as the designer sends it.
	 * @return array The stored theme.
	 */
	public static function save( array $theme ) {
		$clean = Window_Theme::sanitize( $theme );
		\QuantumCloud\Chatbot\Widget\Launcher_Theme::save( array( 'window' => $clean ) );
		Window_Theme::reset();
		/** This action is documented in src/Window/Window_Theme.php (save()). */
		do_action( 'qcld_wpbot_chat/window_theme_saved', $clean );
		return $clean;
	}
}
