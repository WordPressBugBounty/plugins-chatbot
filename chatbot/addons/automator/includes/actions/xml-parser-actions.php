<?php
/**
 * XML Parser Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class XML_Parser_Actions
 */
class XML_Parser_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'xml_parser';
		$this->group = __( 'XML Parser', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'parse_xml_file':
				return $this->parse_xml_file( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	/**
	 * Parse XML file URL into JSON data
	 */
	private function parse_xml_file( $config, $trigger_data ) {
		$config_values = isset( $config['config'] ) ? $config['config'] : array();
		
		$xml_url = isset( $config_values['xml_url'] ) ? $this->parse_tokens( $config_values['xml_url'], $trigger_data ) : '';
		
		if ( empty( $xml_url ) ) {
			return array(
				'success' => false,
				'message' => 'XML URL is required.',
			);
		}

		$response = wp_remote_get( $xml_url, array( 'timeout' => 45 ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$xml_content   = wp_remote_retrieve_body( $response );

		if ( $response_code < 200 || $response_code >= 300 ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Failed to fetch XML. Response Code: %d', $response_code ),
			);
		}

		if ( empty( $xml_content ) ) {
			return array(
				'success' => false,
				'message' => 'The XML file is empty.',
			);
		}

		// Parse XML
		// Use LIBXML_NOCDATA to merge CDATA as text nodes.
        libxml_use_internal_errors(true);
		$xml = simplexml_load_string( $xml_content, 'SimpleXMLElement', LIBXML_NOCDATA );

		if ( false === $xml ) {
            $errors = libxml_get_errors();
            $message = 'Failed to parse XML content.';
            if ( ! empty( $errors ) ) {
                $message .= ' Error: ' . $errors[0]->message;
            }
            libxml_clear_errors();
			return array(
				'success' => false,
				'message' => $message,
			);
		}

		// Convert SimpleXMLElement to associative array
		$json = wp_json_encode( $xml );
		$data = json_decode( $json, true );

		return array(
			'success' => true,
			'data'    => $data,
			'message' => 'XML parsed successfully.',
		);
	}
}
