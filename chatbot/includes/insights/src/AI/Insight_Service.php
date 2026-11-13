<?php
/**
 * Runs AI features: checks the feature switch, the cache and the cost
 * limits, calls the provider, logs usage and stores the result in
 * qcin_ai_results so the same question never costs twice.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

use QuantumCloud\Insights\Module;
use QuantumCloud\Insights\Storage\Schema;

defined( 'ABSPATH' ) || exit;

final class Insight_Service {

	/**
	 * Stored summary of a session, if any.
	 *
	 * @param object $session Session row.
	 * @return array<string,mixed>|null
	 */
	public function get_summary( $session ) {
		$row = $this->find( 'session:' . $session->uuid, 'summary' );
		return $row ? $this->format( $row, $session ) : null;
	}

	/**
	 * Summarize a session (or return the stored summary when nothing changed).
	 *
	 * @param object $session Session row.
	 * @param bool   $force   Ask again even when a summary for this data exists.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function summarize( $session, $force = false ) {
		if ( ! AI_Settings::is_ready() ) {
			return new \WP_Error( 'qcin_ai_off', __( 'Turn on AI and add an API key in Settings first.', 'chatbot' ), array( 'status' => 409 ) );
		}
		if ( ! AI_Settings::feature_on( 'summary' ) ) {
			return new \WP_Error( 'qcin_ai_feature_off', __( 'AI session summaries are turned off in Settings.', 'chatbot' ), array( 'status' => 409 ) );
		}

		$client  = Module::instance()->ai();
		$digest  = ( new Digest_Builder() )->build( $session );
		$model   = $client instanceof Provider_Client ? $client->get_provider() . ':' . $client->get_model() : $client->get_label();
		$subject = 'session:' . $session->uuid;
		$hash    = md5( $digest['hash'] . '|' . Prompt_Library::SUMMARY . '|' . $model . '|' . get_user_locale() );

		$row = $this->find( $subject, 'summary' );
		if ( $row && ! $force && $row->input_hash === $hash ) {
			return $this->format( $row, $session );
		}

		$limit = Usage::limit_reached();
		if ( $limit ) {
			return new \WP_Error( 'qcin_ai_limit', $limit['message'], array( 'status' => 429 ) );
		}

		/**
		 * Change the digest sent to the AI for a session summary.
		 *
		 * @param string $text    Digest.
		 * @param object $session Session row.
		 */
		$text = (string) apply_filters( 'qcin/ai_digest', $digest['text'], $session );

		$request  = new AI_Request(
			Prompt_Library::summary_messages( $text, self::language() ),
			array(
				'max_tokens'      => 500,
				'temperature'     => 0.2,
				'response_format' => 'json',
				'timeout'         => 30,
				'feature'         => 'summary',
			)
		);
		$response = $client->complete( $request );
		if ( $response->ok() || $response->input_tokens || $response->output_tokens ) {
			Usage::record( 'summary', $response->input_tokens, $response->output_tokens );
		}
		if ( ! $response->ok() ) {
			return new \WP_Error( 'qcin_ai_error', $response->error_message, array( 'status' => 502 ) );
		}

		$result = Prompt_Library::parse_summary( $response->text );
		if ( ! $result ) {
			return new \WP_Error( 'qcin_ai_bad_answer', __( 'The AI answer could not be read. Try again, or choose another model.', 'chatbot' ), array( 'status' => 502 ) );
		}

		$this->store( (int) $session->id, $subject, 'summary', $model, $hash, $result, $response, Prompt_Library::SUMMARY );

		/**
		 * After a session summary is created.
		 *
		 * @param array<string,mixed> $result  Summary.
		 * @param object              $session Session row.
		 */
		do_action( 'qcin/ai_summary_created', $result, $session );

		return $this->format( $this->find( $subject, 'summary' ), $session );
	}

	/**
	 * Run any AI feature registered with qcin/ai_features: checks the AI
	 * switch, the feature switch, the cache and the cost limits, calls the
	 * provider, logs usage under the feature id and stores the result in
	 * qcin_ai_results. The same subject, prompt version, input and model
	 * never cost twice unless 'force' is set.
	 *
	 * Send only redacted digests or aggregates in $messages (see
	 * Digest_Builder::build() and Digest_Builder::redact()).
	 *
	 * @param string              $feature        Feature id, e.g. 'issues'.
	 * @param string              $subject        What the result is about, e.g. 'page:12:desktop' (max 64 chars).
	 * @param string              $prompt_version Bump when the prompt changes (max 16 chars).
	 * @param array<int,array>    $messages       Chat messages: role + content.
	 * @param array<string,mixed> $opts           max_tokens, temperature, timeout, response_format
	 *                                            ('json' or 'text'), session_id, force, parse
	 *                                            (callable: answer text => array|null).
	 * @return array<string,mixed>|\WP_Error result, model, tokens, createdAt, cached.
	 */
	public function run( $feature, $subject, $prompt_version, array $messages, array $opts = array() ) {
		$feature = (string) $feature;
		$subject = substr( (string) $subject, 0, 64 );
		if ( ! AI_Settings::is_ready() ) {
			return new \WP_Error( 'qcin_ai_off', __( 'Turn on AI and add an API key in Settings first.', 'chatbot' ), array( 'status' => 409 ) );
		}
		if ( ! isset( AI_Settings::feature_defs()[ $feature ] ) || ! AI_Settings::feature_on( $feature ) ) {
			return new \WP_Error( 'qcin_ai_feature_off', __( 'This AI feature is turned off in Settings.', 'chatbot' ), array( 'status' => 409 ) );
		}
		if ( '' === $subject || ! $messages ) {
			return new \WP_Error( 'qcin_ai_bad_input', __( 'Nothing to send to the AI.', 'chatbot' ), array( 'status' => 400 ) );
		}

		$opts   = wp_parse_args(
			$opts,
			array(
				'max_tokens'      => 800,
				'temperature'     => 0.2,
				'timeout'         => 45,
				'response_format' => 'json',
				'session_id'      => 0,
				'force'           => false,
				'parse'           => null,
			)
		);
		$client = Module::instance()->ai();
		$model  = $client instanceof Provider_Client ? $client->get_provider() . ':' . $client->get_model() : $client->get_label();
		$hash   = md5( wp_json_encode( $messages ) . '|' . $prompt_version . '|' . $model . '|' . get_user_locale() );

		$row = $this->find( $subject, $feature );
		if ( $row && ! $opts['force'] && $row->input_hash === $hash ) {
			return $this->format_result( $row, true );
		}

		$limit = Usage::limit_reached();
		if ( $limit ) {
			return new \WP_Error( 'qcin_ai_limit', $limit['message'], array( 'status' => 429 ) );
		}

		$response = $client->complete(
			new AI_Request(
				array_values( $messages ),
				array(
					'max_tokens'      => (int) $opts['max_tokens'],
					'temperature'     => (float) $opts['temperature'],
					'response_format' => 'json' === $opts['response_format'] ? 'json' : 'text',
					'timeout'         => (int) $opts['timeout'],
					'feature'         => $feature,
				)
			)
		);
		if ( $response->ok() || $response->input_tokens || $response->output_tokens ) {
			Usage::record( $feature, $response->input_tokens, $response->output_tokens );
		}
		if ( ! $response->ok() ) {
			return new \WP_Error( 'qcin_ai_error', $response->error_message, array( 'status' => 502 ) );
		}

		if ( is_callable( $opts['parse'] ) ) {
			$result = call_user_func( $opts['parse'], $response->text );
		} elseif ( 'json' === $opts['response_format'] ) {
			$result = Prompt_Library::decode_json( $response->text );
		} else {
			$result = array( 'text' => trim( (string) $response->text ) );
		}
		if ( ! is_array( $result ) ) {
			return new \WP_Error( 'qcin_ai_bad_answer', __( 'The AI answer could not be read. Try again, or choose another model.', 'chatbot' ), array( 'status' => 502 ) );
		}

		$this->store( (int) $opts['session_id'], $subject, $feature, $model, $hash, $result, $response, $prompt_version );
		return $this->format_result( $this->find( $subject, $feature ), false );
	}

	/**
	 * Stored result of a feature for a subject, if any.
	 *
	 * @param string $feature Feature id.
	 * @param string $subject Subject.
	 * @return array<string,mixed>|null
	 */
	public function get_result( $feature, $subject ) {
		$row = $this->find( substr( (string) $subject, 0, 64 ), (string) $feature );
		return $row ? $this->format_result( $row, true ) : null;
	}

	/**
	 * Result row for the API.
	 *
	 * @param object|null $row    Row.
	 * @param bool        $cached Whether it came from the cache.
	 * @return array<string,mixed>|null
	 */
	private function format_result( $row, $cached ) {
		if ( ! $row ) {
			return null;
		}
		$result   = json_decode( (string) $row->result, true );
		$model    = (string) $row->model;
		$provider = strpos( $model, ':' ) ? Providers::get( strstr( $model, ':', true ) ) : null;
		return array(
			'result'    => is_array( $result ) ? $result : array(),
			'model'     => $provider ? $provider['label'] . ' · ' . substr( strstr( $model, ':' ), 1 ) : $model,
			'tokens'    => (int) $row->input_tokens + (int) $row->output_tokens,
			'createdAt' => gmdate( 'c', (int) strtotime( $row->created_at . ' UTC' ) ),
			'cached'    => (bool) $cached,
		);
	}

	/**
	 * Send a tiny request to check a key and model. Usage counts as "test".
	 *
	 * @param Provider_Client $client Client.
	 * @return array{ok:bool,message:string,model:string}
	 */
	public function test( Provider_Client $client ) {
		if ( ! $client->is_available() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Add an API key and a model first.', 'chatbot' ),
				'model'   => '',
			);
		}
		$response = $client->complete(
			new AI_Request(
				array(
					array(
						'role'    => 'user',
						'content' => 'Reply with the single word OK.',
					),
				),
				array(
					'max_tokens'  => 16,
					'temperature' => 0,
					'timeout'     => 20,
					'feature'     => 'test',
				)
			)
		);
		if ( $response->input_tokens || $response->output_tokens ) {
			Usage::record( 'test', $response->input_tokens, $response->output_tokens );
		}
		return array(
			'ok'      => $response->ok(),
			'message' => $response->ok()
				/* translators: %s: provider and model */
				? sprintf( __( 'Connected. %s answered.', 'chatbot' ), $client->get_label() )
				: $response->error_message,
			'model'   => $response->model,
		);
	}

	/**
	 * Latest stored result for a subject and feature.
	 *
	 * @param string $subject Subject.
	 * @param string $feature Feature.
	 * @return object|null
	 */
	private function find( $subject, $feature ) {
		global $wpdb;
		$t = Schema::table( 'ai_results' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE subject = %s AND feature = %s ORDER BY id DESC LIMIT 1", $subject, $feature ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Save a result, replacing the previous one for the subject.
	 *
	 * @param int                 $session_id Session id.
	 * @param string              $subject    Subject.
	 * @param string              $feature    Feature.
	 * @param string              $model      Provider:model.
	 * @param string              $hash       Input hash.
	 * @param array<string,mixed> $result     Result.
	 * @param AI_Response         $response   Response.
	 * @param string              $prompt     Prompt version.
	 */
	private function store( $session_id, $subject, $feature, $model, $hash, array $result, AI_Response $response, $prompt ) {
		global $wpdb;
		$t = Schema::table( 'ai_results' );
		$wpdb->delete( $t, array( 'subject' => $subject, 'feature' => $feature ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$t,
			array(
				'session_id'     => $session_id,
				'subject'        => $subject,
				'feature'        => $feature,
				'prompt_version' => substr( (string) $prompt, 0, 16 ),
				'model'          => substr( $model, 0, 100 ),
				'input_hash'     => $hash,
				'result'         => wp_json_encode( $result ),
				'input_tokens'   => (int) $response->input_tokens,
				'output_tokens'  => (int) $response->output_tokens,
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Result for the API.
	 *
	 * @param object|null $row     Row.
	 * @param object      $session Session row.
	 * @return array<string,mixed>|null
	 */
	private function format( $row, $session ) {
		if ( ! $row ) {
			return null;
		}
		$result   = json_decode( (string) $row->result, true );
		$model    = (string) $row->model;
		$provider = strpos( $model, ':' ) ? Providers::get( strstr( $model, ':', true ) ) : null;
		$label    = $provider ? $provider['label'] . ' · ' . substr( strstr( $model, ':' ), 1 ) : $model;
		return array(
			'result'    => is_array( $result ) ? $result : array(),
			'model'     => $label,
			'tokens'    => (int) $row->input_tokens + (int) $row->output_tokens,
			'createdAt' => gmdate( 'c', (int) strtotime( $row->created_at . ' UTC' ) ),
			// A visit still in progress may have more to say.
			'stale'     => 0 === (int) $session->status && strtotime( $session->last_seen_at . ' UTC' ) > strtotime( $row->created_at . ' UTC' ),
		);
	}

	/**
	 * Language for AI answers: the admin's own language.
	 *
	 * @return string
	 */
	private static function language() {
		$locale = get_user_locale();
		if ( 0 === strpos( $locale, 'en' ) ) {
			return 'English';
		}
		if ( class_exists( '\Locale' ) ) {
			$name = \Locale::getDisplayName( $locale, 'en' );
			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}
		/* Locale code, which models understand. */
		return 'the language of locale ' . $locale;
	}
}
