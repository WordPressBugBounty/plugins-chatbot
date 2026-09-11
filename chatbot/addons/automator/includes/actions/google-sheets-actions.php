<?php
/**
 * Google Sheets Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Google_Sheets_Actions
 *
 * Integrates with the Google Sheets API v4 using a Service Account (JWT auth).
 * No OAuth redirect flow required — users paste their Service Account JSON key directly.
 */
class Google_Sheets_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'google_sheets';
		$this->group = __( 'Google Sheets', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 *
	 * @param array $action_data Configuration for this action from the workflow.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return bool|array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'gs_add_row':
			case 'add_row':
				return $this->add_row( $action_data, $trigger_data );
			case 'gs_update_row':
			case 'update_row':
				return $this->update_row( $action_data, $trigger_data );
			case 'gs_clear_range':
			case 'clear_range':
				return $this->clear_range( $action_data, $trigger_data );
			case 'gs_get_values':
			case 'get_values':
				return $this->get_values( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Action Handlers
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Append a new row to a Google Sheet.
	 */
	private function add_row( $action_data, $trigger_data ) {

		$cfg            = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$spreadsheet_id = trim( $this->parse_tokens( $cfg['spreadsheet_id'] ?? $cfg['sheet_id'] ?? '', $trigger_data ) );
		$sheet_name_raw = trim( $this->parse_tokens( $cfg['sheet_name'] ?? $cfg['worksheet'] ?? '', $trigger_data ) );
		$sheet_name     = ! empty( $sheet_name_raw ) ? $sheet_name_raw : 'Sheet1';
		$row_data_temp  = $cfg['row_data'] ?? '';
		$sa_json        = trim( $cfg['service_account_json'] ?? '' );

		if ( empty( $sa_json ) ) {
			$sa_json = get_option( 'wpbot_automator_gs_sa_json', '' );
		}

		if ( empty( $spreadsheet_id ) || empty( $sa_json ) ) {
			return array(
				'success' => false,
				'message' => 'Spreadsheet ID and Service Account JSON are required.',
			);
		}

		$token = $this->get_access_token( $sa_json );
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'message' => $token->get_error_message() );
		}

		// ✅ Validate Sheet Exists First
		$sheet_check = wp_remote_get(
			"https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}?fields=sheets.properties.title",
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $sheet_check ) ) {
			return array( 'success' => false, 'message' => $sheet_check->get_error_message() );
		}

		$sheet_body = json_decode( wp_remote_retrieve_body( $sheet_check ), true );

		if ( empty( $sheet_body['sheets'] ) ) {
			return array( 'success' => false, 'message' => 'Unable to retrieve sheets from spreadsheet.' );
		}

		$available_sheets = array_map(
			function ( $s ) {
				return $s['properties']['title'];
			},
			$sheet_body['sheets']
		);

		if ( ! in_array( $sheet_name, $available_sheets, true ) ) {
			$create_missing = $cfg['create_missing_sheet'] ?? true; // Default to true if not set
			if ( $create_missing ) {
				$created = $this->create_sheet( $spreadsheet_id, $sheet_name, $sa_json );
				if ( is_wp_error( $created ) ) {
					return array( 'success' => false, 'message' => 'Sheet "' . esc_html( $sheet_name ) . '" not found and auto-creation failed: ' . $created->get_error_message() );
				}
			} else {
				return array(
					'success' => false,
					'message' => 'Sheet "' . esc_html( $sheet_name ) . '" not found. Available sheets: ' . implode( ', ', $available_sheets ),
				);
			}
		}

		// ✅ Parse row data by splitting the template first to prevent inline commas/newlines from breaking columns
		if ( ! empty( $row_data_temp ) ) {
			$values_template = is_array( $row_data_temp ) ? $row_data_temp : preg_split( '/[\n,]+/', $row_data_temp );
			$values_template = array_map( 'trim', $values_template );
			$values = array();
			foreach ( $values_template as $tpl ) {
				if ( $tpl !== '' ) {
					$values[] = $this->parse_tokens( $tpl, $trigger_data );
				}
			}
		} else {
			$values = $this->extract_fields_from_trigger( $trigger_data );
		}

		if ( empty( $values ) ) {
			return array(
				'success' => false,
				'message' => 'No row data provided.',
			);
		}

		// ✅ Properly quote sheet name if it contains spaces or special characters
		$sheet_name = $this->quote_sheet_name( $sheet_name );

		// ✅ Use safe append range
		$range = $sheet_name . '!A:Z';

		$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/" . rawurlencode( $range ) . ":append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'values' => array( $values ),
					)
				),
				'timeout' => 20,
			)
		);

		$result = $this->handle_api_response( $response, 'Add Row', $spreadsheet_id, $sa_json );

		if ( $result['success'] && ! empty( $cfg['service_account_json'] ) ) {
			update_option( 'wpbot_automator_gs_sa_json', $cfg['service_account_json'] );
		}

		return $result;
	}

	/**
	 * Update a specific row in a Google Sheet.
	 */
	private function update_row( $action_data, $trigger_data ) {
		$cfg            = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$spreadsheet_id = trim( $this->parse_tokens( $cfg['spreadsheet_id'] ?? $cfg['sheet_id'] ?? '', $trigger_data ) );
		$sheet_name_raw = trim( $this->parse_tokens( $cfg['sheet_name'] ?? $cfg['worksheet'] ?? '', $trigger_data ) );
		$sheet_name     = ! empty( $sheet_name_raw ) ? $sheet_name_raw : 'Sheet1';
		$row_number     = absint( $this->parse_tokens( $cfg['row_number'] ?? '', $trigger_data ) );
		$row_data_temp  = $cfg['row_data'] ?? '';
		$sa_json        = trim( $cfg['service_account_json'] ?? '' );

		if ( empty( $sa_json ) ) {
			$sa_json = get_option( 'wpbot_automator_gs_sa_json', '' );
		}

		if ( empty( $spreadsheet_id ) || empty( $sa_json ) || empty( $row_data_temp ) || empty( $row_number ) ) {
			return array(
				'success' => false,
				'message' => 'Google Sheets Update Row: spreadsheet_id, service_account_json, row_data, and row_number are required.',
			);
		}

		$token = $this->get_access_token( $sa_json );
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'message' => $token->get_error_message() );
		}

		$values_template = is_array( $row_data_temp ) ? $row_data_temp : preg_split( '/[\n,]+/', $row_data_temp );
		$values_template = array_map( 'trim', $values_template );
		$values = array();
		foreach ( $values_template as $tpl ) {
			if ( $tpl !== '' ) {
				$values[] = $this->parse_tokens( $tpl, $trigger_data );
			}
		}
		
		$range  = rawurlencode( $this->quote_sheet_name( "{$sheet_name}!A{$row_number}" ) );
		$url    = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}?valueInputOption=USER_ENTERED";

		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'range'  => $this->quote_sheet_name( "{$sheet_name}!A{$row_number}" ),
						'values' => array( $values ),
					)
				),
				'timeout' => 20,
			)
		);

		return $this->handle_api_response( $response, 'Update Row', $spreadsheet_id, $sa_json );
	}

	/**
	 * Clear a range in a Google Sheet.
	 */
	private function clear_range( $action_data, $trigger_data ) {
		$cfg            = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$spreadsheet_id = trim( $this->parse_tokens( isset( $cfg['spreadsheet_id'] ) ? $cfg['spreadsheet_id'] : '', $trigger_data  ) );
		$range          = trim( $this->parse_tokens( isset( $cfg['range'] ) ? $cfg['range'] : '', $trigger_data  ) );
		$sa_json        = trim( isset( $cfg['service_account_json'] ) ? $cfg['service_account_json'] : '' );

		if ( empty( $sa_json ) ) {
			$sa_json = get_option( 'wpbot_automator_gs_sa_json', '' );
		}

		if ( empty( $spreadsheet_id ) || empty( $sa_json ) || empty( $range ) ) {
			return array(
				'success' => false,
				'message' => 'Google Sheets Clear Range: spreadsheet_id, service_account_json, and range are required.',
			);
		}

		$token = $this->get_access_token( $sa_json );
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'message' => $token->get_error_message() );
		}

		$encoded_range = rawurlencode( $this->quote_sheet_name( $range ) );
		$url           = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$encoded_range}:clear";

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => '{}',
				'timeout' => 20,
			)
		);

		return $this->handle_api_response( $response, 'Clear Range', $spreadsheet_id, $sa_json );
	}

	/**
	 * Get values from a range in a Google Sheet.
	 */
	private function get_values( $action_data, $trigger_data ) {
		$cfg            = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$spreadsheet_id = trim( $this->parse_tokens( isset( $cfg['spreadsheet_id'] ) ? $cfg['spreadsheet_id'] : '', $trigger_data  ) );
		$range          = trim( $this->parse_tokens( isset( $cfg['range'] ) ? $cfg['range'] : '', $trigger_data  ) );
		$sa_json        = trim( isset( $cfg['service_account_json'] ) ? $cfg['service_account_json'] : '' );

		if ( empty( $spreadsheet_id ) || empty( $sa_json ) || empty( $range ) ) {
			$hint = '';
			if ( ! empty( $trigger_data['fields'] ) || ( isset( $trigger_data['form_id'] ) ) ) {
				$hint = ' Hint: If you want to SAVE form data to a sheet, please use the "Add Row (Save Form Data)" action instead.';
			}
			return array(
				'success' => false,
				'message' => 'Google Sheets Get Values: spreadsheet_id, service_account_json, and range are required.' . $hint,
			);
		}

		$token = $this->get_access_token( $sa_json );
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'message' => $token->get_error_message() );
		}

		$encoded_range = rawurlencode( $range );
		$url           = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$encoded_range}";

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'success' => true,
				'values'  => isset( $body['values'] ) ? $body['values'] : array(),
				'message' => 'Values retrieved successfully.',
			);
		}

		$error = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown API error.';
		if ( $code === 400 && strpos( $error, 'Unable to parse range' ) !== false ) {
			$available_sheets = $this->get_spreadsheet_sheets( $spreadsheet_id, $sa_json );
			$sheet_list = ! empty( $available_sheets ) ? ' Available sheets: ' . implode( ', ', $available_sheets ) : ' (Could not retrieve list of available sheets).';
			$error .= ' Tip: Ensure the Sheet Name matches exactly (case-sensitive). ' . $sheet_list;
		}
		return array( 'success' => false, 'message' => "Google Sheets Get Values failed (HTTP {$code}): {$error}" );
	}

	/**
	 * Extract fields from trigger data for automated mapping.
	 *
	 * @param array $trigger_data Data passed from the trigger.
	 * @return array List of values.
	 */
	private function extract_fields_from_trigger( $trigger_data ) {
		$values = array();

		// Case 1: Fields array (WPForms, Fluent Forms, etc.)
		if ( ! empty( $trigger_data['fields'] ) && is_array( $trigger_data['fields'] ) ) {
			foreach ( $trigger_data['fields'] as $field ) {
				// WPForms structure: fields[id][value] or fields[id] = value
				if ( is_array( $field ) ) {
					if ( isset( $field['value'] ) ) {
						$values[] = $field['value'];
					} else {
						// Flatten any other nested arrays or just skip
						$values[] = wp_json_encode( $field );
					}
				} 
				// CF7 / Fluent Forms often just have key => value
				elseif ( is_scalar( $field ) ) {
					$values[] = $field;
				}
			}
		}

		// Fallback: If no fields, but we have some core data.
		if ( empty( $values ) ) {
			// Some triggers might pass data directly.
			$exclude_keys = array( 'form_id', 'form_name', 'entry_id', 'sub_id' );
			foreach ( $trigger_data as $key => $val ) {
				if ( ! in_array( $key, $exclude_keys ) && ( is_string( $val ) || is_numeric( $val ) ) ) {
					$values[] = $val;
				}
			}
		}

		return $values;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// JWT / Auth Helpers
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Obtain an OAuth2 access token from a Service Account JSON string.
	 *
	 * Builds and signs a JWT, then exchanges it for an access token via
	 * https://oauth2.googleapis.com/token — no external library needed.
	 *
	 * @param string $sa_json Raw service account JSON string.
	 * @return string|\WP_Error  Access token string or WP_Error on failure.
	 */
	private function get_access_token( $sa_json ) {
		// Attempt to decode the service account JSON.
		$sa = json_decode( $sa_json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || empty( $sa['private_key'] ) || empty( $sa['client_email'] ) ) {
			return new \WP_Error(
				'invalid_sa_json',
				__( 'Google Sheets: Invalid service account JSON. Check the credentials field.', 'wpbot-automator' )
			);
		}

		$now    = time();
		$header = $this->base64url_encode( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claim  = $this->base64url_encode(
			wp_json_encode(
				array(
					'iss'   => $sa['client_email'],
					'scope' => 'https://www.googleapis.com/auth/spreadsheets',
					'aud'   => 'https://oauth2.googleapis.com/token',
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);

		$to_sign = $header . '.' . $claim;
		$key     = openssl_pkey_get_private( $sa['private_key'] );

		if ( false === $key ) {
			return new \WP_Error(
				'invalid_private_key',
				__( 'Google Sheets: Could not parse private key from service account JSON.', 'wpbot-automator' )
			);
		}

		$signature = '';
		if ( ! openssl_sign( $to_sign, $signature, $key, 'SHA256' ) ) {
			return new \WP_Error(
				'sign_failed',
				__( 'Google Sheets: Failed to sign JWT. Ensure OpenSSL is enabled on your server.', 'wpbot-automator' )
			);
		}

		$jwt = $to_sign . '.' . $this->base64url_encode( $signature );

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$token_data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $token_data['access_token'] ) ) {
			$err = isset( $token_data['error_description'] ) ? $token_data['error_description'] : 'Unknown token error.';
			return new \WP_Error( 'token_error', "Google Sheets auth failed: {$err}" );
		}

		return $token_data['access_token'];
	}


	/**
	 * Ensure a sheet name or range is properly quoted for the Google Sheets API.
	 *
	 * @param string $range The range or sheet name.
	 * @return string
	 */
	private function quote_sheet_name( $range ) {
		if ( empty( $range ) ) {
			return $range;
		}

		// If it contains a '!', we need to quote the part before it.
		if ( strpos( $range, '!' ) !== false ) {
			$parts = explode( '!', $range, 2 );
			$sheet = $parts[0];
			$cells = $parts[1];
			// Only quote if not already quoted AND if it needs quoting (contains non-alphanumeric).
			if ( strpos( $sheet, "'" ) !== 0 && preg_match( '/[^A-Za-z0-9_]/', $sheet ) ) {
				$sheet = "'" . str_replace( "'", "''", $sheet ) . "'";
			}
			return $sheet . '!' . $cells;
		}

		// If no '!', assume it's a sheet name.
		// Only quote if not already quoted AND if it needs quoting.
		if ( strpos( $range, "'" ) !== 0 && preg_match( '/[^A-Za-z0-9_]/', $range ) ) {
			return "'" . str_replace( "'", "''", $range ) . "'";
		}

		return $range;
	}

	/**
	 * Base64URL encode (RFC 4648 §5, no padding).
	 *
	 * @param string $data Data to encode.
	 * @return string
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Handle a Google API response uniformly.
	 *
	 * @param array|\WP_Error $response  wp_remote_* response.
	 * @param string          $context   Human-readable action name for error messages.
	 * @return array { success: bool, message: string }
	 */
	private function handle_api_response( $response, $context, $spreadsheet_id = '', $sa_json = '' ) {
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			return array( 'success' => true, 'message' => "Google Sheets {$context} completed successfully." );
		}

		$error = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown API error.';
		
		// Advanced diagnostics for range parsing errors.
		if ( $code === 400 && strpos( $error, 'Unable to parse range' ) !== false ) {
			$available_sheets = $this->get_spreadsheet_sheets( $spreadsheet_id, $sa_json );
			$sheet_list = ! empty( $available_sheets ) ? ' Available sheets in this spreadsheet: ' . implode( ', ', $available_sheets ) : ' (Could not retrieve list of available sheets).';
			$error .= ' Tip: Ensure the Sheet Name matches exactly (case-sensitive). ' . $sheet_list;
		}

		return array( 'success' => false, 'message' => "Google Sheets {$context} failed (HTTP {$code}): {$error}" );
	}

	/**
	 * Get a list of all sheet titles in a spreadsheet.
	 *
	 * @param string $spreadsheet_id Spreadsheet ID.
	 * @param string $sa_json        Service Account JSON.
	 * @return array List of sheet titles.
	 */
	private function get_spreadsheet_sheets( $spreadsheet_id, $sa_json ) {
		if ( empty( $spreadsheet_id ) || empty( $sa_json ) ) {
			return array();
		}

		$token = $this->get_access_token( $sa_json );
		if ( is_wp_error( $token ) ) {
			return array();
		}

		$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}?fields=sheets.properties.title";
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$titles = array();
		if ( ! empty( $body['sheets'] ) ) {
			foreach ( $body['sheets'] as $sheet ) {
				if ( isset( $sheet['properties']['title'] ) ) {
					$titles[] = $sheet['properties']['title'];
				}
			}
		}
		return $titles;
	}

	/**
	 * Create a new sheet in a spreadsheet.
	 *
	 * @param string $spreadsheet_id Spreadsheet ID.
	 * @param string $sheet_name     Sheet Name to create.
	 * @param string $sa_json        Service Account JSON.
	 * @return bool|\WP_Error
	 */
	private function create_sheet( $spreadsheet_id, $sheet_name, $sa_json ) {
		$token = $this->get_access_token( $sa_json );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}:batchUpdate";
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'requests' => array(
							array(
								'addSheet' => array(
									'properties' => array(
										'title' => $sheet_name,
									),
								),
							),
						),
					)
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$error = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown error creating sheet.';
			return new \WP_Error( 'gs_create_sheet_failed', "HTTP {$code}: {$error}" );
		}

		return true;
	}
}
