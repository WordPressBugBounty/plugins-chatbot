<?php
/**
 * Native tool calling (function calling) for every provider format, in one internal format.
 *
 * Request side (AI\Request):
 * - `tools`: array of { name, description, parameters (JSON schema object) }.
 * - `tool_choice`: 'auto' (default), 'none', 'required', or a tool name.
 * - `messages` may contain, besides { role: user|assistant, content }:
 *   - { role: 'assistant', content: '', tool_calls: [ { id, name, arguments (array) } ] }
 *   - { role: 'tool', tool_call_id, name, content (string; the tool's result) }
 *
 * Response side (AI\Response): `tool_calls` [ { id, name, arguments (array) } ] and
 * `finish_reason` ('tool_calls' when the model wants tools run, otherwise 'stop', 'length', …).
 *
 * Provider_Client::send() uses this only when a request has tools or tool messages, so every
 * existing request is built exactly as before. Streaming does not carry tools: callers run the
 * tool round with send() and may stream the final answer.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Tool calling payloads and parsing.
 */
final class Tool_Calls {

	/**
	 * Whether a request needs the tool path.
	 *
	 * @param Request $request Request.
	 * @return bool
	 */
	public static function wanted( Request $request ) {
		if ( ! empty( $request->tools ) ) {
			return true;
		}
		foreach ( $request->messages as $message ) {
			if ( ( isset( $message['role'] ) && 'tool' === $message['role'] ) || ! empty( $message['tool_calls'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clean tool definitions: name [a-zA-Z0-9_-]{1,64}, description, parameters as an object schema.
	 *
	 * @param array $tools Tools.
	 * @return array[]
	 */
	public static function clean_tools( array $tools ) {
		$clean = array();
		foreach ( $tools as $tool ) {
			if ( ! is_array( $tool ) || empty( $tool['name'] ) ) {
				continue;
			}
			$name   = substr( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '_', (string) $tool['name'] ), 0, 64 );
			$schema = isset( $tool['parameters'] ) && is_array( $tool['parameters'] ) ? $tool['parameters'] : array();
			if ( empty( $schema['type'] ) ) {
				$schema['type'] = 'object';
			}
			if ( 'object' === $schema['type'] && ! isset( $schema['properties'] ) ) {
				$schema['properties'] = new \stdClass();
			}
			$clean[ $name ] = array(
				'name'        => $name,
				'description' => isset( $tool['description'] ) ? (string) $tool['description'] : '',
				'parameters'  => $schema,
			);
		}
		return array_values( $clean );
	}

	/**
	 * Add tools and tool messages to a payload body built by Provider_Client.
	 *
	 * @param string  $format  Providers::FORMAT_*.
	 * @param array   $body    Body.
	 * @param Request $request Request.
	 * @return array
	 */
	public static function apply( $format, array $body, Request $request ) {
		$tools = self::clean_tools( (array) $request->tools );
		switch ( $format ) {
			case Providers::FORMAT_ANTHROPIC:
				$body['messages'] = self::anthropic_messages( $request );
				if ( $tools ) {
					$body['tools'] = array_map(
						static function ( $tool ) {
							return array(
								'name'         => $tool['name'],
								'description'  => $tool['description'],
								'input_schema' => $tool['parameters'],
							);
						},
						$tools
					);
					$choice        = self::choice( $request );
					if ( 'none' === $choice ) {
						$body['tool_choice'] = array( 'type' => 'none' );
					} elseif ( 'required' === $choice ) {
						$body['tool_choice'] = array( 'type' => 'any' );
					} elseif ( 'auto' !== $choice ) {
						$body['tool_choice'] = array(
							'type' => 'tool',
							'name' => $choice,
						);
					}
				}
				return $body;
			case Providers::FORMAT_GEMINI:
				$body['contents'] = self::gemini_contents( $request );
				if ( $tools ) {
					$body['tools'] = array(
						array(
							'functionDeclarations' => array_map(
								static function ( $tool ) {
									return array(
										'name'        => $tool['name'],
										'description' => $tool['description'],
										'parameters'  => self::gemini_schema( $tool['parameters'] ),
									);
								},
								$tools
							),
						),
					);
					$choice        = self::choice( $request );
					$mode          = 'none' === $choice ? 'NONE' : ( 'auto' === $choice ? 'AUTO' : 'ANY' );
					$config        = array( 'mode' => $mode );
					if ( ! in_array( $choice, array( 'auto', 'none', 'required' ), true ) ) {
						$config['allowedFunctionNames'] = array( $choice );
					}
					$body['toolConfig'] = array( 'functionCallingConfig' => $config );
					unset( $body['generationConfig']['responseMimeType'] ); // JSON mode and tools do not mix.
				}
				return $body;
			default:
				$body['messages'] = self::openai_messages( $request );
				if ( $tools ) {
					$body['tools'] = array_map(
						static function ( $tool ) {
							return array(
								'type'     => 'function',
								'function' => array(
									'name'        => $tool['name'],
									'description' => $tool['description'],
									'parameters'  => $tool['parameters'],
								),
							);
						},
						$tools
					);
					$choice        = self::choice( $request );
					if ( in_array( $choice, array( 'auto', 'none', 'required' ), true ) ) {
						$body['tool_choice'] = $choice;
					} else {
						$body['tool_choice'] = array(
							'type'     => 'function',
							'function' => array( 'name' => $choice ),
						);
					}
					unset( $body['response_format'] ); // JSON mode and tools do not mix.
				}
				return $body;
		}
	}

	/**
	 * Read tool calls and the finish reason from a decoded provider answer.
	 *
	 * @param string   $format   Providers::FORMAT_*.
	 * @param array    $data     Decoded body.
	 * @param Response $response Response (filled in).
	 * @return Response
	 */
	public static function read( $format, array $data, Response $response ) {
		$calls = array();
		switch ( $format ) {
			case Providers::FORMAT_ANTHROPIC:
				foreach ( isset( $data['content'] ) ? (array) $data['content'] : array() as $block ) {
					if ( isset( $block['type'] ) && 'tool_use' === $block['type'] ) {
						$calls[] = array(
							'id'        => isset( $block['id'] ) ? (string) $block['id'] : '',
							'name'      => isset( $block['name'] ) ? (string) $block['name'] : '',
							'arguments' => isset( $block['input'] ) && is_array( $block['input'] ) ? $block['input'] : array(),
						);
					}
				}
				$reason = isset( $data['stop_reason'] ) ? (string) $data['stop_reason'] : '';
				$reason = 'tool_use' === $reason ? 'tool_calls' : ( 'max_tokens' === $reason ? 'length' : ( 'end_turn' === $reason ? 'stop' : $reason ) );
				break;
			case Providers::FORMAT_GEMINI:
				$parts = isset( $data['candidates'][0]['content']['parts'] ) ? (array) $data['candidates'][0]['content']['parts'] : array();
				foreach ( $parts as $i => $part ) {
					if ( isset( $part['functionCall']['name'] ) ) {
						$calls[] = array(
							'id'        => isset( $part['functionCall']['id'] ) ? (string) $part['functionCall']['id'] : 'call_' . $i,
							'name'      => (string) $part['functionCall']['name'],
							'arguments' => isset( $part['functionCall']['args'] ) && is_array( $part['functionCall']['args'] ) ? $part['functionCall']['args'] : array(),
						);
					}
				}
				$reason = $calls ? 'tool_calls' : strtolower( isset( $data['candidates'][0]['finishReason'] ) ? (string) $data['candidates'][0]['finishReason'] : '' );
				$reason = 'max_tokens' === $reason ? 'length' : $reason;
				break;
			default:
				$message = isset( $data['choices'][0]['message'] ) ? (array) $data['choices'][0]['message'] : array();
				foreach ( isset( $message['tool_calls'] ) ? (array) $message['tool_calls'] : array() as $call ) {
					$arguments = isset( $call['function']['arguments'] ) ? $call['function']['arguments'] : array();
					if ( is_string( $arguments ) ) {
						$decoded   = json_decode( $arguments, true );
						$arguments = is_array( $decoded ) ? $decoded : array();
					}
					$calls[] = array(
						'id'        => isset( $call['id'] ) ? (string) $call['id'] : '',
						'name'      => isset( $call['function']['name'] ) ? (string) $call['function']['name'] : '',
						'arguments' => is_array( $arguments ) ? $arguments : array(),
					);
				}
				$reason = isset( $data['choices'][0]['finish_reason'] ) ? (string) $data['choices'][0]['finish_reason'] : '';
		}
		$response->tool_calls    = array_values(
			array_filter(
				$calls,
				static function ( $call ) {
					return '' !== $call['name'];
				}
			)
		);
		$response->finish_reason = $response->tool_calls ? 'tool_calls' : $reason;
		return $response;
	}

	/**
	 * The request's tool choice.
	 *
	 * @param Request $request Request.
	 * @return string
	 */
	private static function choice( Request $request ) {
		$choice = (string) $request->tool_choice;
		return '' === $choice ? 'auto' : substr( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '_', $choice ), 0, 64 );
	}

	/**
	 * OpenAI-format messages, with tool calls and tool results.
	 *
	 * @param Request $request Request.
	 * @return array[]
	 */
	public static function openai_messages( Request $request ) {
		$messages = array();
		if ( '' !== $request->system ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $request->system,
			);
		}
		foreach ( $request->messages as $message ) {
			$role = isset( $message['role'] ) ? (string) $message['role'] : 'user';
			if ( 'tool' === $role ) {
				$messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => isset( $message['tool_call_id'] ) ? (string) $message['tool_call_id'] : '',
					'content'      => isset( $message['content'] ) ? (string) $message['content'] : '',
				);
				continue;
			}
			$item = array(
				'role'    => $role,
				'content' => isset( $message['content'] ) ? (string) $message['content'] : '',
			);
			if ( 'assistant' === $role && ! empty( $message['tool_calls'] ) ) {
				$item['content']    = '' !== $item['content'] ? $item['content'] : null;
				$item['tool_calls'] = array();
				foreach ( (array) $message['tool_calls'] as $call ) {
					$item['tool_calls'][] = array(
						'id'       => (string) $call['id'],
						'type'     => 'function',
						'function' => array(
							'name'      => (string) $call['name'],
							'arguments' => (string) wp_json_encode( isset( $call['arguments'] ) ? (object) $call['arguments'] : new \stdClass() ),
						),
					);
				}
			}
			$messages[] = $item;
		}
		return $messages;
	}

	/**
	 * Anthropic messages: tool calls as tool_use blocks; consecutive tool results merged into
	 * one user message of tool_result blocks.
	 *
	 * @param Request $request Request.
	 * @return array[]
	 */
	public static function anthropic_messages( Request $request ) {
		$messages = array();
		foreach ( $request->messages as $message ) {
			$role = isset( $message['role'] ) ? (string) $message['role'] : 'user';
			if ( 'tool' === $role ) {
				$block = array(
					'type'        => 'tool_result',
					'tool_use_id' => isset( $message['tool_call_id'] ) ? (string) $message['tool_call_id'] : '',
					'content'     => isset( $message['content'] ) ? (string) $message['content'] : '',
				);
				$last  = count( $messages ) - 1;
				if ( $last >= 0 && 'user' === $messages[ $last ]['role'] && is_array( $messages[ $last ]['content'] ) && isset( $messages[ $last ]['content'][0]['type'] ) && 'tool_result' === $messages[ $last ]['content'][0]['type'] ) {
					$messages[ $last ]['content'][] = $block;
				} else {
					$messages[] = array(
						'role'    => 'user',
						'content' => array( $block ),
					);
				}
				continue;
			}
			if ( 'assistant' === $role && ! empty( $message['tool_calls'] ) ) {
				$blocks = array();
				if ( ! empty( $message['content'] ) ) {
					$blocks[] = array(
						'type' => 'text',
						'text' => (string) $message['content'],
					);
				}
				foreach ( (array) $message['tool_calls'] as $call ) {
					$blocks[] = array(
						'type'  => 'tool_use',
						'id'    => (string) $call['id'],
						'name'  => (string) $call['name'],
						'input' => isset( $call['arguments'] ) ? (object) $call['arguments'] : new \stdClass(),
					);
				}
				$messages[] = array(
					'role'    => 'assistant',
					'content' => $blocks,
				);
				continue;
			}
			$messages[] = array(
				'role'    => 'assistant' === $role ? 'assistant' : 'user',
				'content' => isset( $message['content'] ) ? (string) $message['content'] : '',
			);
		}
		return $messages;
	}

	/**
	 * Gemini contents: tool calls as functionCall parts, results as functionResponse parts.
	 *
	 * @param Request $request Request.
	 * @return array[]
	 */
	public static function gemini_contents( Request $request ) {
		$contents = array();
		foreach ( $request->messages as $message ) {
			$role = isset( $message['role'] ) ? (string) $message['role'] : 'user';
			if ( 'tool' === $role ) {
				$part = array(
					'functionResponse' => array(
						'name'     => isset( $message['name'] ) ? (string) $message['name'] : '',
						'response' => array( 'content' => isset( $message['content'] ) ? (string) $message['content'] : '' ),
					),
				);
				$last = count( $contents ) - 1;
				if ( $last >= 0 && 'user' === $contents[ $last ]['role'] && isset( $contents[ $last ]['parts'][0]['functionResponse'] ) ) {
					$contents[ $last ]['parts'][] = $part;
				} else {
					$contents[] = array(
						'role'  => 'user',
						'parts' => array( $part ),
					);
				}
				continue;
			}
			if ( 'assistant' === $role && ! empty( $message['tool_calls'] ) ) {
				$parts = array();
				if ( ! empty( $message['content'] ) ) {
					$parts[] = array( 'text' => (string) $message['content'] );
				}
				foreach ( (array) $message['tool_calls'] as $call ) {
					$parts[] = array(
						'functionCall' => array(
							'name' => (string) $call['name'],
							'args' => isset( $call['arguments'] ) ? (object) $call['arguments'] : new \stdClass(),
						),
					);
				}
				$contents[] = array(
					'role'  => 'model',
					'parts' => $parts,
				);
				continue;
			}
			$contents[] = array(
				'role'  => 'assistant' === $role ? 'model' : 'user',
				'parts' => array( array( 'text' => isset( $message['content'] ) ? (string) $message['content'] : '' ) ),
			);
		}
		return $contents;
	}

	/**
	 * Gemini accepts an OpenAPI subset: drop keys it rejects.
	 *
	 * @param mixed $schema Schema.
	 * @return mixed
	 */
	public static function gemini_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}
		unset( $schema['additionalProperties'], $schema['$schema'], $schema['default'] );
		foreach ( $schema as $key => $value ) {
			if ( is_array( $value ) ) {
				$schema[ $key ] = self::gemini_schema( $value );
			}
		}
		if ( isset( $schema['properties'] ) && array() === $schema['properties'] ) {
			$schema['properties'] = new \stdClass();
		}
		return $schema;
	}
}
