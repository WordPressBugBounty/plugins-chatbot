<?php
/**
 * [wpbot_subscribe] sign-up form.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Integrations;

use QuantumCloud\WPBot\CRM\Domain\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * [wpbot_subscribe list="slug-or-name" tag="…" button="Subscribe" placeholder="Your email" show_name="no"]
 *
 * Renders a small accessible form that posts JSON to POST /wpbot-crm/v1/subscribe.
 * A tiny inline script is printed once per page, only where the shortcode is used.
 */
final class Shortcode {

	const TAG = 'wpbot_subscribe';

	/**
	 * Whether the submit script was already printed on this page.
	 *
	 * @var bool
	 */
	private static $script_done = false;

	/**
	 * Form counter for unique ids.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Register the shortcode.
	 */
	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Signature proving list/tag came from a shortcode written by the site owner.
	 *
	 * @param string $list List reference.
	 * @param string $tag  Tag reference.
	 * @return string
	 */
	public static function sign( $list, $tag ) {
		return substr( hash_hmac( 'sha256', 'subscribe|' . $list . '|' . $tag, Schema::secret() ), 0, 20 );
	}

	/**
	 * Render the form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'list'        => '',
				'tag'         => '',
				'button'      => __( 'Subscribe', 'chatbot' ),
				'placeholder' => __( 'Your email', 'chatbot' ),
				'show_name'   => 'no',
			),
			$atts,
			self::TAG
		);
		$list = sanitize_text_field( (string) $atts['list'] );
		$tag  = sanitize_text_field( (string) $atts['tag'] );
		$id   = 'wpbot-subscribe-' . ( ++self::$count );
		$sr   = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';

		ob_start();
		?>
		<form class="wpbot-subscribe" id="<?php echo esc_attr( $id ); ?>" method="post" novalidate
			data-endpoint="<?php echo esc_url( rest_url( 'wpbot-crm/v1/subscribe' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
			<input type="hidden" name="list" value="<?php echo esc_attr( $list ); ?>">
			<input type="hidden" name="tag" value="<?php echo esc_attr( $tag ); ?>">
			<input type="hidden" name="sig" value="<?php echo esc_attr( self::sign( $list, $tag ) ); ?>">
			<?php if ( in_array( strtolower( (string) $atts['show_name'] ), array( 'yes', '1', 'true', 'on' ), true ) ) : ?>
				<label for="<?php echo esc_attr( $id ); ?>-name" class="screen-reader-text" style="<?php echo esc_attr( $sr ); ?>"><?php esc_html_e( 'First name', 'chatbot' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $id ); ?>-name" name="first_name" autocomplete="given-name" placeholder="<?php esc_attr_e( 'First name', 'chatbot' ); ?>">
			<?php endif; ?>
			<label for="<?php echo esc_attr( $id ); ?>-email" class="screen-reader-text" style="<?php echo esc_attr( $sr ); ?>"><?php esc_html_e( 'Email address', 'chatbot' ); ?></label>
			<input type="email" id="<?php echo esc_attr( $id ); ?>-email" name="email" required autocomplete="email" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>">
			<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
				<label for="<?php echo esc_attr( $id ); ?>-website"><?php esc_html_e( 'Leave this field empty', 'chatbot' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $id ); ?>-website" name="website_url" value="" tabindex="-1" autocomplete="off">
			</div>
			<button type="submit"><?php echo esc_html( $atts['button'] ); ?></button>
			<p class="wpbot-subscribe__message" role="status" aria-live="polite"></p>
		</form>
		<?php
		$html = (string) ob_get_clean();
		return $html . self::script();
	}

	/**
	 * Inline submit handler, once per page.
	 *
	 * @return string
	 */
	private static function script() {
		if ( self::$script_done ) {
			return '';
		}
		self::$script_done = true;
		$error             = wp_json_encode( __( 'Something went wrong. Please try again.', 'chatbot' ) );
		$sending           = wp_json_encode( __( 'Sending…', 'chatbot' ) );
		$js                = '(function(){document.addEventListener("submit",function(e){var f=e.target;'
			. 'if(!f.classList||!f.classList.contains("wpbot-subscribe")){return;}e.preventDefault();'
			. 'var m=f.querySelector(".wpbot-subscribe__message"),b=f.querySelector("button[type=submit]"),d={};'
			. 'new FormData(f).forEach(function(v,k){d[k]=v;});b.disabled=true;m.textContent=' . $sending . ';'
			. 'fetch(f.getAttribute("data-endpoint"),{method:"POST",credentials:"same-origin",'
			. 'headers:{"Content-Type":"application/json","X-WP-Nonce":f.getAttribute("data-nonce")},body:JSON.stringify(d)})'
			. '.then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j};});})'
			. '.then(function(x){m.textContent=(x.j&&x.j.message)||' . $error . ';if(x.ok){f.reset();}})'
			. '.catch(function(){m.textContent=' . $error . ';})'
			. '.then(function(){b.disabled=false;});});})();';

		if ( function_exists( 'wp_get_inline_script_tag' ) ) {
			return wp_get_inline_script_tag( $js );
		}
		return '<script>' . $js . '</script>';
	}
}
