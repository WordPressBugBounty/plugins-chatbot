<?php
/**
 * Data Transformer Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// File: includes/class-dts-transformer.php

class Data_Transformer  extends Action {

    public function __construct() {
        $this->id    = 'data_transformer';
        $this->group = __( 'Data Transformer', 'wpbot-automator' );
    }

    public function execute( $action_data, $trigger_data ) {
        $action_id = $action_data['actionId'] ?? '';

        if ( empty( $action_id ) ) {
            return false;
        }

        $config_values = $action_data['config'] ?? [];
        $value         = $this->parse_tokens( $config_values['value'] ?? '', $trigger_data );
        $secret        = $this->parse_tokens( $config_values['secret'] ?? '', $trigger_data );

        return Data_Transformer::run( $action_id, $value, [ 'secret' => $secret ] );
    }

    /**
     * Run a transformation action on a value.
     *
     * @param string $action  One of the supported action slugs.
     * @param string $value   The input string.
     * @param array  $options Extra options (e.g. secret key for HMAC).
     * @return array { success: bool, output: string, error: string }
     */
    public static function run( string $action, string $value, array $options = [] ): array {
        try {
            $output = match( $action ) {
                'html_encode'    => htmlspecialchars( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
                'html_decode'    => htmlspecialchars_decode( $value, ENT_QUOTES | ENT_HTML5 ),
                'base64_encode'  => base64_encode( $value ),
                'base64_decode'  => self::safe_base64_decode( $value ),
                'sha256'         => hash( 'sha256', $value ),
                'sha1'           => hash( 'sha1', $value ),
                'sha512'         => hash( 'sha512', $value ),
                'hmac_sha256'    => hash_hmac( 'sha256', $value, $options['secret'] ?? '' ),
                'md5'            => md5( $value ),
                'strip_tags'     => wp_strip_all_tags( $value ),
                default          => throw new InvalidArgumentException( "Unknown action: {$action}" ),
            };

            return [ 'success' => true, 'output' => $output, 'error' => '' ];

        } catch ( Throwable $e ) {
            return [ 'success' => false, 'output' => '', 'error' => $e->getMessage() ];
        }
    }

    private static function safe_base64_decode( string $value ): string {
        $decoded = base64_decode( $value, true );
        if ( $decoded === false ) {
            throw new RuntimeException( 'Invalid Base64 string.' );
        }
        return $decoded;
    }

    // File: qcld_wpbot_autometor 

    public static function register(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'qwautomator/v1', '/transform', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle' ],
            'permission_callback' => [ __CLASS__, 'auth' ],
            'args' => [
                'action' => [ 'required' => true,  'type' => 'string' ],
                'value'  => [ 'required' => true,  'type' => 'string' ],
                'secret' => [ 'required' => false, 'type' => 'string', 'default' => '' ],
            ],
        ] );
    }

    public static function handle( WP_REST_Request $req ): WP_REST_Response {
        $result = Data_Transformer::run(
            $req->get_param( 'action' ),
            $req->get_param( 'value' ),
            [ 'secret' => $req->get_param( 'secret' ) ]
        );

        $status = $result['success'] ? 200 : 422;
        return new WP_REST_Response( $result, $status );
    }

    public static function auth(): bool {
        return current_user_can( 'manage_options' )
            || self::valid_api_key();
    }

    private static function valid_api_key(): bool {
        $key    = get_option( 'dts_api_key' );
        $header = sanitize_text_field( $_SERVER['HTTP_X_DTS_KEY'] ?? '' );
        return $key && hash_equals( $key, $header );
    }
}
// File: includes/class-dts-workflow-step.php

// Use this when your workflow engine fires an action with step data.

add_filter( 'wpbot_automator_execute_step', function( array $step_result, array $step_config ) {
    if ( ( $step_config['type'] ?? '' ) !== 'data_transformer' ) {
        return $step_result;
    }

    $action = $step_config['action'] ?? '';
    // Resolve dynamic variable from previous step output
    $value  = dts_resolve_variable( $step_config['value'], $step_result['context'] );
    $secret = dts_resolve_variable( $step_config['secret'] ?? '', $step_result['context'] );

    $result = Data_Transformer::run( $action, $value, compact( 'secret' ) );

    $step_result['output']  = $result['output'];
    $step_result['success'] = $result['success'];
    $step_result['error']   = $result['error'];

    return $step_result;
}, 10, 2 );