<?php
/**
 * The sign-up form: [wpbot_subscribe] (same name as the old module, so pages
 * keep working) with optional attributes list="3,5" tag="7" button="…"
 * title="…" consent="…".
 *
 * The lists and tags a form adds to are signed into the form (HMAC), so a
 * visitor cannot add people to other, private lists. Spam protection without
 * a nonce (pages may be cached for days): a hidden honeypot field, a minimum
 * fill time, and a per-IP limit.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Capture;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Audience\Lists;
use QuantumCloud\AiMarketing\Rest\Rest_Controller;
use QuantumCloud\AiMarketing\Storage\Schema;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Sign-up form.
 */
final class Signup_Form {

	const SYSTEM_KEY  = 'signup';
	const MIN_SECONDS = 2;
	const PER_HOUR    = 20;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hook() {
		add_shortcode( 'wpbot_subscribe', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Public route.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			Rest_Controller::NS,
			'/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit' ),
				'permission_callback' => '__return_true', // Public sign-up; protected by signature, honeypot, timing and rate limit.
			)
		);
	}

	/**
	 * Sign the groups a form adds to (pure; unit tested through sign()).
	 *
	 * @param int[]  $groups List and tag ids.
	 * @param string $secret Secret.
	 * @return string
	 */
	public static function sign( array $groups, $secret ) {
		$groups = array_values( array_unique( array_filter( array_map( 'intval', $groups ) ) ) );
		sort( $groups );
		return substr( hash_hmac( 'sha256', 'signup|' . implode( ',', $groups ), (string) $secret ), 0, 24 );
	}

	/**
	 * Hidden fields for a form that adds to these lists and tags (used by
	 * the shortcode and by other forms, such as Master popups).
	 *
	 * @param int[] $groups List and tag ids.
	 * @return array{groups:string,sig:string,t:string,endpoint:string}
	 */
	public static function signed_fields( array $groups ) {
		$groups = array_values( array_unique( array_filter( array_map( 'intval', $groups ) ) ) );
		if ( ! $groups ) {
			$groups = array( Lists::ensure_system( self::SYSTEM_KEY, __( 'Website sign-ups', 'chatbot' ) ) );
		}
		return array(
			'groups'   => implode( ',', $groups ),
			'sig'      => self::sign( $groups, Schema::secret() ),
			't'        => (string) time(),
			'endpoint' => rest_url( Rest_Controller::NS . '/subscribe' ),
		);
	}

	/**
	 * Parse "3, 5,x" into ints (pure).
	 *
	 * @param string $value Attribute.
	 * @return int[]
	 */
	public static function ids( $value ) {
		$ids = array();
		foreach ( explode( ',', (string) $value ) as $part ) {
			$id = (int) trim( $part );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Render the form.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts   = shortcode_atts(
			array(
				'list'    => '',
				'tag'     => '',
				'title'   => __( 'Get our newsletter', 'chatbot' ),
				'button'  => __( 'Subscribe', 'chatbot' ),
				'consent' => __( 'I agree to receive emails. I can unsubscribe at any time.', 'chatbot' ),
				'name'    => 'yes',
			),
			is_array( $atts ) ? $atts : array(),
			'wpbot_subscribe'
		);
		$fields = self::signed_fields( array_merge( self::ids( $atts['list'] ), self::ids( $atts['tag'] ) ) );
		$id     = 'qcld-mk-form-' . wp_rand( 1000, 999999 );
		$url    = $fields['endpoint'];
		ob_start();
		?>
		<form class="qcld-mk-signup" id="<?php echo esc_attr( $id ); ?>" data-endpoint="<?php echo esc_url( $url ); ?>" novalidate>
			<?php if ( '' !== $atts['title'] ) : ?>
				<p class="qcld-mk-signup__title"><strong><?php echo esc_html( $atts['title'] ); ?></strong></p>
			<?php endif; ?>
			<?php if ( 'no' !== $atts['name'] ) : ?>
				<p><label><?php esc_html_e( 'First name', 'chatbot' ); ?><br><input type="text" name="first_name" autocomplete="given-name"></label></p>
			<?php endif; ?>
			<p><label><?php esc_html_e( 'Email', 'chatbot' ); ?><br><input type="email" name="email" required autocomplete="email"></label></p>
			<p class="qcld-mk-signup__hp" aria-hidden="true" style="position:absolute;left:-9999px;"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
			<p><label><input type="checkbox" name="consent" value="1" required> <?php echo esc_html( $atts['consent'] ); ?></label></p>
			<input type="hidden" name="groups" value="<?php echo esc_attr( $fields['groups'] ); ?>">
			<input type="hidden" name="sig" value="<?php echo esc_attr( $fields['sig'] ); ?>">
			<input type="hidden" name="t" value="<?php echo esc_attr( $fields['t'] ); ?>">
			<input type="hidden" name="consent_text" value="<?php echo esc_attr( $atts['consent'] ); ?>">
			<p><button type="submit"><?php echo esc_html( $atts['button'] ); ?></button></p>
			<p class="qcld-mk-signup__message" role="status" aria-live="polite"></p>
		</form>
		<script>
		( function () {
			var form = document.getElementById( <?php echo wp_json_encode( $id ); ?> );
			if ( ! form || ! window.fetch ) { return; }
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var msg = form.querySelector( '.qcld-mk-signup__message' );
				var data = {};
				new FormData( form ).forEach( function ( v, k ) { data[ k ] = v; } );
				data.page = window.location.href;
				form.querySelector( 'button' ).disabled = true;
				fetch( form.getAttribute( 'data-endpoint' ), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify( data ) } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) { msg.textContent = res.message || ''; if ( res.ok ) { form.reset(); } } )
					.catch( function () { msg.textContent = <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'chatbot' ) ); ?>; } )
					.finally( function () { form.querySelector( 'button' ).disabled = false; } );
			} );
		} )();
		</script>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * POST /subscribe.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function submit( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : (array) $request->get_body_params();
		$ok   = __( 'Thank you! Please check your inbox.', 'chatbot' );

		// Bots fill the honeypot or submit instantly: answer as if it worked, save nothing.
		if ( ! empty( $body['website'] ) || ( time() - (int) ( isset( $body['t'] ) ? $body['t'] : 0 ) ) < self::MIN_SECONDS ) {
			return array(
				'ok'      => true,
				'message' => $ok,
			);
		}
		if ( ! self::within_rate_limit() ) {
			return new WP_Error( 'qcld_mk_rate', __( 'Too many sign-ups from your network. Please try again later.', 'chatbot' ), array( 'status' => 429 ) );
		}
		$groups = self::ids( isset( $body['groups'] ) ? (string) $body['groups'] : '' );
		$sig    = isset( $body['sig'] ) ? (string) $body['sig'] : '';
		if ( ! $groups || ! hash_equals( self::sign( $groups, Schema::secret() ), $sig ) ) {
			return new WP_Error( 'qcld_mk_form', __( 'This form is out of date. Please reload the page.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( empty( $body['consent'] ) ) {
			return new WP_Error( 'qcld_mk_consent', __( 'Please tick the box to agree to receive emails.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$page = isset( $body['page'] ) ? esc_url_raw( (string) $body['page'] ) : '';
		$form = isset( $body['form'] ) ? substr( sanitize_key( (string) $body['form'] ), 0, 40 ) : '';
		/* translators: %s: page address. */
		$reason  = sprintf( __( 'Signed up on %s', 'chatbot' ), $page );
		$reasons = array_fill_keys( $groups, $reason );
		$result  = Contacts::capture(
			array(
				'email'        => isset( $body['email'] ) ? (string) $body['email'] : '',
				'first_name'   => isset( $body['first_name'] ) ? (string) $body['first_name'] : '',
				'phone'        => isset( $body['phone'] ) ? substr( sanitize_text_field( (string) $body['phone'] ), 0, 40 ) : '',
				'consent_text' => isset( $body['consent_text'] ) ? (string) $body['consent_text'] : '',
				'attributes'   => array_filter(
					array(
						'signup_page' => $page,
						'signup_form' => $form,
					)
				),
			),
			'marketing',
			'signup',
			$reasons,
			true
		);
		if ( is_wp_error( $result ) ) {
			return 'qcld_mk_suppressed' === $result->get_error_code()
				? array(
					'ok'      => true,
					'message' => $ok,
				)
				: $result;
		}
		/**
		 * Someone used a sign-up form (journey trigger "form submitted").
		 *
		 * @param int   $contact_id Contact id.
		 * @param array $groups     List/tag ids.
		 * @param string $page      Page address.
		 * @param string $form      Form id ('' for the shortcode).
		 * @param array  $extra     phone, sms_consent (bool), sms_consent_text: a separate opt-in for text messages.
		 */
		do_action(
			'qcld_wpbot_marketing/form_submitted',
			(int) $result['id'],
			$groups,
			$page,
			$form,
			array(
				'phone'            => isset( $body['phone'] ) ? substr( sanitize_text_field( (string) $body['phone'] ), 0, 40 ) : '',
				'sms_consent'      => ! empty( $body['sms_consent'] ),
				'sms_consent_text' => isset( $body['sms_consent_text'] ) ? substr( sanitize_text_field( (string) $body['sms_consent_text'] ), 0, 300 ) : '',
			)
		);
		$response = array(
			'ok'      => true,
			'message' => 'pending' === $result['status'] ? __( 'Thank you! Please confirm your subscription with the link we just emailed you.', 'chatbot' ) : ( 'unsubscribed' === $result['status'] ? $ok : __( 'Thank you for subscribing!', 'chatbot' ) ),
		);
		/**
		 * The answer to a sign-up (a form may add a download link).
		 *
		 * @param array  $response   ok, message.
		 * @param array  $result     Capture result (id, status).
		 * @param string $form       Form id.
		 */
		return (array) apply_filters( 'qcld_wpbot_marketing/subscribe_response', $response, $result, $form );
	}

	/**
	 * Per-IP limit: PER_HOUR sign-ups an hour.
	 *
	 * @return bool
	 */
	private static function within_rate_limit() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'qcld_mk_signup_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= self::PER_HOUR ) {
			return false;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return true;
	}
}
