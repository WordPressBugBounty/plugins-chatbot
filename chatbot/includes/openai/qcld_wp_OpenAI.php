<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.
include_once(ABSPATH . 'wp-includes/pluggable.php');
if(!class_exists('qcld_wp_OpenAI')){
    class qcld_wp_OpenAI{
        public $baseURL = "https://api.openai.com/v1/";
        private $defaultEngine = "davinci";
        public function setDefaultEngine($defaultEngine){
            $this->defaultEngine = $defaultEngine;
        }

        public function get_response($postFields){
            $url = "https://api.openai.com/v1/completions";
            $api_key = get_option('open_ai_api_key');

            $response = wp_remote_post(
                $url,
                array(
                    'headers' => array(
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $api_key,
                    ),
                    'body'    => $postFields,
                    'timeout' => 60,
                )
            );

            if ( is_wp_error( $response ) ) {
                return null;
            }

            return json_decode( wp_remote_retrieve_body( $response ) );
        }
        public function complete($prompt) {
            $max_tokens =  (int)get_option( 'openai_max_tokens');
            $temp = (float)get_option( 'openai_temperature');
            $frequency_penalty = (float)get_option( 'frequency_penalty');
            $presence_penalty = (float)get_option( 'presence_penalty');
            $request_body = [
                "prompt" => $prompt,
                "model" => get_option( 'openai_engines'),
                "max_tokens" => $max_tokens,
                "temperature" => $temp,
                "top_p" => 1,
                "presence_penalty" => $presence_penalty,
                "frequency_penalty"=> $frequency_penalty,
                "best_of"=> 1,
                "stream" => false,
            ];
            $postFields = wp_json_encode($request_body);
            $result = self::get_response($postFields);
            return wp_json_encode($result);
        }
        public function gptcomplete($keyword){
            $url = 'https://api.openai.com/v1/responses';
            $api_key = get_option('open_ai_api_key');

            $response = wp_remote_post(
                $url,
                array(
                    'headers' => array(
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $api_key,
                    ),
                    'body'    => wp_json_encode( array(
                        'model'       => get_option( 'openai_engines'),
                        'input'       => $keyword,
                        'temperature' => 0,
                    ) ),
                    'timeout' => 60,
                )
            );

            if ( is_wp_error( $response ) ) {
                return '';
            }

            return wp_remote_retrieve_body( $response );
        }
    }
}
