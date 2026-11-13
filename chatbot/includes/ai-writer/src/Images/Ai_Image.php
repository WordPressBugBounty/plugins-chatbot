<?php
/**
 * AI image generation with the owner's own key (OpenAI or Gemini image
 * models), saved to the Media Library.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Images;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Settings;
use QuantumCloud\WPBotCore\AI\Error_Classifier;
use QuantumCloud\WPBotCore\AI\Key_Health;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Response;
use QuantumCloud\WPBotCore\AI\Usage;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * AI images.
 */
final class Ai_Image {

	/**
	 * Estimated cost of one image in USD (shown as an estimate).
	 */
	const COST_PER_IMAGE = 0.04;

	/**
	 * The provider that makes AI images: the one chosen in the image
	 * settings, else OpenAI or Gemini (turned on first, else any saved key).
	 *
	 * @return array|null array( provider, model ).
	 */
	public static function provider() {
		$set     = Keys::get_set( Module::ID );
		$chosen  = (string) Settings::get( 'image_provider' );
		$order   = '' !== $chosen ? array( $chosen ) : array( 'openai', 'gemini' );
		$default = array(
			'openai' => 'gpt-image-2',
			'gemini' => 'gemini-2.5-flash-image',
		);
		// A provider that is turned on first; else any saved key (CR-10: images
		// do not depend on the two text providers).
		foreach ( array( true, false ) as $need_on ) {
			foreach ( $order as $provider ) {
				$entry = isset( $set['providers'][ $provider ] ) ? $set['providers'][ $provider ] : null;
				if ( ! $entry || ( $need_on && empty( $entry['enabled'] ) ) || ( ! $need_on && empty( $entry['key'] ) ) ) {
					continue;
				}
				$model = ! empty( $entry['image_model'] ) ? (string) $entry['image_model'] : $default[ $provider ];
				// A saved model the provider has retired (gpt-image-1, DALL-E) uses its replacement.
				if ( class_exists( '\\QuantumCloud\\WPBotCore\\AI\\Model_Routing' ) ) {
					$replacement = \QuantumCloud\WPBotCore\AI\Model_Routing::replacement( $provider, $model );
					$model       = '' !== $replacement ? $replacement : $model;
				}
				return array(
					'provider' => $provider,
					'model'    => $model,
				);
			}
		}
		return null;
	}


	/**
	 * Generate an image and import it.
	 *
	 * @param string $prompt  Prompt.
	 * @param string $alt     Alt text.
	 * @param int    $post_id Post id.
	 * @param int    $job_id  Job id.
	 * @return int|WP_Error Attachment id.
	 */
	public static function generate( $prompt, $alt, $post_id = 0, $job_id = 0 ) {
		$choice = self::provider();
		if ( ! $choice ) {
			return new WP_Error( 'qcld_wpbot_writer_no_image_ai', __( 'No AI provider that makes images is turned on.', 'chatbot' ) );
		}
		$credentials = Keys::credentials( Module::ID, $choice['provider'] );
		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}
		// AI spending limits (WPBot Core, AI Settings > Spending and limits): a job waits for the reset.
		if ( function_exists( 'qcld_wpbot_core_spend_allow' ) ) {
			$allowed = qcld_wpbot_core_spend_allow( Module::ID, 'image', self::COST_PER_IMAGE );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
		}
		$prompt = sanitize_textarea_field( $prompt ) . ' No text, letters, logos or watermarks in the image.';

		if ( 'openai' === $choice['provider'] ) {
			$url  = 'https://api.openai.com/v1/images/generations';
			$body = array(
				'model'  => $choice['model'],
				'prompt' => $prompt,
				'size'   => '1536x1024',
				'n'      => 1,
			);
			$head = array( 'Authorization' => 'Bearer ' . $credentials['key'] );
		} else {
			$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $choice['model'] ) . ':generateContent';
			$body = array(
				'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
				'generationConfig' => array( 'responseModalities' => array( 'IMAGE' ) ),
			);
			$head = array( 'x-goog-api-key' => $credentials['key'] );
		}

		/** This filter is documented in WPBot Core's Provider_Client (tests point it at a mock). */
		$url = (string) apply_filters( 'qcld_wpbot_core/ai_endpoint', $url, $choice['provider'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPBot Core's own filter.

		$raw = wp_remote_post(
			$url,
			array(
				'timeout'          => 90,
				'headers'          => array_merge( array( 'Content-Type' => 'application/json' ), $head ),
				'body'             => wp_json_encode( $body ),
				'data_format'      => 'body',
				// Recorded below; WPBot Core's spend safety net leaves it alone.
				'qcld_wpbot_spend' => 'metered',
			)
		);
		$status = is_wp_error( $raw ) ? 0 : (int) wp_remote_retrieve_response_code( $raw );
		$text   = is_wp_error( $raw ) ? '' : (string) wp_remote_retrieve_body( $raw );
		if ( $status < 200 || $status >= 300 ) {
			$cause = Error_Classifier::classify( $status, $text );
			Key_Health::record_failure( Module::ID, $choice['provider'], $cause );
			$explained = Error_Classifier::explain( $cause, $choice['provider'] );
			return new WP_Error( 'qcld_wpbot_writer_image_failed', $explained['cause'] . ' ' . $explained['fix'], array( 'cause' => $cause ) );
		}

		$data  = json_decode( $text, true );
		$bytes = '';
		if ( isset( $data['data'][0]['b64_json'] ) ) {
			$bytes = base64_decode( (string) $data['data'][0]['b64_json'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Image data from the provider.
		} elseif ( isset( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( (array) $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['inlineData']['data'] ) ) {
					$bytes = base64_decode( (string) $part['inlineData']['data'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Image data from the provider.
					break;
				}
			}
		}
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			return new WP_Error( 'qcld_wpbot_writer_image_empty', __( 'The AI provider returned no image.', 'chatbot' ) );
		}
		Key_Health::record_success( Module::ID, $choice['provider'] );

		self::record( $choice, is_array( $data ) ? $data : array(), (int) $job_id );

		return Image_Importer::from_bytes(
			$bytes,
			'png',
			array(
				'source'  => 'ai',
				'credit'  => '',
				'license' => __( 'AI-generated', 'chatbot' ),
			),
			$alt,
			$post_id
		);
	}

	/**
	 * Record the image in the AI usage log: through WPBot Core's spending meter when it is there
	 * (image count, tokens the provider reported, list price), else as before at a fixed price.
	 *
	 * @param array $choice Provider and model.
	 * @param array $data   Decoded answer.
	 * @param int   $job_id Job id.
	 * @return void
	 */
	private static function record( array $choice, array $data, $job_id ) {
		if ( function_exists( 'qcld_wpbot_core_spend_record' ) && class_exists( '\\QuantumCloud\\WPBotCore\\Spend\\Usage_Reader' ) ) {
			$usage = \QuantumCloud\WPBotCore\Spend\Usage_Reader::from_body( $data );
			$usage = is_array( $usage ) ? $usage : array();

			$usage['kind']   = 'image';
			$usage['images'] = max( 1, isset( $usage['images'] ) ? (int) $usage['images'] : 0 );
			$price           = \QuantumCloud\WPBotCore\Spend\Prices::estimate( $choice['provider'], $choice['model'], $usage );
			$meta            = array(
				'provider' => $choice['provider'],
				'model'    => $choice['model'],
				'job_id'   => $job_id,
			);
			if ( ! $price['price_known'] ) {
				$meta['cost'] = self::COST_PER_IMAGE; // No list price yet: the usual estimate.
			}
			qcld_wpbot_core_spend_record( Module::ID, 'image', $usage, $meta );
			return;
		}
		$usage           = new Response();
		$usage->provider = $choice['provider'];
		$usage->model    = $choice['model'];
		$usage->cost     = self::COST_PER_IMAGE;
		Usage::record( Module::ID, 'image', $usage, $job_id );
	}
}
