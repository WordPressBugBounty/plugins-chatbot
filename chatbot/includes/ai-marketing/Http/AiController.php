<?php
/**
 * REST: AI email writer and AI connection test.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Support\Ai;
use QuantumCloud\WPBot\CRM\Support\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Routes ai/generate-email (contract #10) and ai/test (contract #11).
 */
class AiController extends RestController {

	const STYLES = array( 'professional', 'friendly', 'formal', 'humorous', 'minimalist' );

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/ai/generate-email',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_email' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);
		register_rest_route(
			self::NS,
			'/ai/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);
	}

	/**
	 * POST ai/generate-email → {html, subject}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_email( $request ) {
		$prompt = trim( sanitize_textarea_field( (string) $request->get_param( 'prompt' ) ) );
		if ( '' === $prompt ) {
			return $this->error( 'wpbot_crm_ai_empty_prompt', __( 'Describe the email you want the AI to write.', 'chatbot' ) );
		}
		list( $style, $layout ) = self::resolve_options( (string) $request->get_param( 'style' ), (string) $request->get_param( 'layout_mode' ) );

		$data = Ai::json(
			Prompts::email_system( $style, $layout ),
			Prompts::email_user( $prompt ),
			array(
				'max_tokens'  => 3000,
				'temperature' => 0.7,
				'timeout'     => 60,
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$html = isset( $data['html'] ) && is_string( $data['html'] ) ? $data['html'] : '';
		if ( '' === $html && isset( $data['content'] ) && is_string( $data['content'] ) ) {
			$html = $data['content'];
		}
		$html = self::clean_html( $html );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return $this->error( 'wpbot_crm_ai_failed', __( 'No content received from AI. Please try again or check your AI settings.', 'chatbot' ), 502 );
		}
		$subject = isset( $data['subject'] ) && is_scalar( $data['subject'] ) ? sanitize_text_field( (string) $data['subject'] ) : '';

		$engine = Ai::resolve();
		return new \WP_REST_Response(
			array(
				'html'     => self::ensure_merge_tags( $html ),
				'subject'  => $subject,
				'style'    => $style,
				'layout'   => $layout,
				'provider' => $engine['provider'],
				'model'    => $engine['model'],
			)
		);
	}

	/**
	 * POST ai/test → 200 {success, message, reply}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function test_connection( $request ) {
		$result = Ai::test(
			sanitize_key( (string) $request->get_param( 'provider' ) ),
			sanitize_text_field( (string) $request->get_param( 'api_key' ) ),
			sanitize_text_field( (string) $request->get_param( 'model' ) )
		);
		return new \WP_REST_Response( $result );
	}

	/**
	 * Valid style + layout; unknown values fall back to the defaults.
	 *
	 * @param string $style  Requested style.
	 * @param string $layout Requested layout_mode.
	 * @return string[] [ style, layout ]
	 */
	public static function resolve_options( $style, $layout ) {
		$style  = sanitize_key( $style );
		$layout = sanitize_key( $layout );
		if ( ! in_array( $style, self::STYLES, true ) ) {
			$style = 'professional';
		}
		if ( 'table-safe' !== $layout ) {
			$layout = 'simple-html';
		}
		return array( $style, $layout );
	}

	/**
	 * Email-safe HTML: drop document wrappers and non-content blocks, then kses
	 * with tables and inline styles allowed.
	 *
	 * @param string $html Raw HTML from the model.
	 * @return string
	 */
	public static function clean_html( $html ) {
		$html = Ai::strip_think( (string) $html );
		$html = preg_replace( '#^\s*```(?:html)?\s*|\s*```\s*$#i', '', $html );
		if ( preg_match( '#<body\b[^>]*>(.*)</body>#is', $html, $m ) ) {
			$html = $m[1];
		}
		$html = preg_replace( '#<(head|style|script|title|noscript|iframe)\b[^>]*>.*?</\1>#is', '', $html );
		$html = preg_replace( '#<!DOCTYPE[^>]*>|<!--.*?-->#is', '', $html );
		return trim( wp_kses( $html, self::allowed_html() ) );
	}

	/**
	 * Allowed tags: wp_kses_post plus email table attributes, with style on everything.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		$allowed = wp_kses_allowed_html( 'post' );
		$table   = array(
			'width'       => true,
			'height'      => true,
			'align'       => true,
			'valign'      => true,
			'bgcolor'     => true,
			'border'      => true,
			'cellpadding' => true,
			'cellspacing' => true,
			'role'        => true,
			'style'       => true,
			'class'       => true,
			'colspan'     => true,
			'rowspan'     => true,
		);
		foreach ( array( 'table', 'tbody', 'thead', 'tfoot', 'tr', 'td', 'th', 'center' ) as $tag ) {
			$allowed[ $tag ] = array_merge( isset( $allowed[ $tag ] ) ? $allowed[ $tag ] : array(), $table );
		}
		foreach ( $allowed as $tag => $attrs ) {
			$allowed[ $tag ]['style'] = true;
		}
		$allowed['a']['target'] = true;
		$allowed['a']['rel']    = true;
		return $allowed;
	}

	/**
	 * Make sure the body greets with {{first_name}} and links {{unsubscribe_url}}.
	 *
	 * @param string $html Clean HTML.
	 * @return string
	 */
	public static function ensure_merge_tags( $html ) {
		$html = preg_replace( '/\{\{\s*(first_name|last_name|email|unsubscribe_url)\s*\}\}/i', '{{$1}}', $html );
		$html = preg_replace( '/(?<!\{)\{\s*(first_name|unsubscribe_url)\s*\}(?!\})/i', '{{$1}}', $html );

		if ( false === strpos( $html, '{{first_name}}' ) ) {
			$html = '<p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#333333;">'
				. esc_html__( 'Hi', 'chatbot' ) . ' {{first_name}},</p>' . $html;
		}
		if ( false === strpos( $html, '{{unsubscribe_url}}' ) ) {
			$html .= '<p style="margin:24px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#888888;text-align:center;">'
				. '<a href="{{unsubscribe_url}}" style="color:#888888;text-decoration:underline;">' . esc_html__( 'Unsubscribe', 'chatbot' ) . '</a></p>';
		}
		return $html;
	}
}
