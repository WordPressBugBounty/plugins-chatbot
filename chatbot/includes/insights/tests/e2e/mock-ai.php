<?php
/**
 * Test-only stand-in for the AI providers. Serve it from the test site's
 * web root and set QCIN_TEST_MOCK_AI in wp-config.php to its URL, e.g.
 *     define( 'QCIN_TEST_MOCK_AI', 'http://127.0.0.1:8899/mock-ai.php' );
 * Every request is appended to mock-ai.log next to this file. Put "401",
 * "429" or "bad" in mock-ai.mode to make it fail. Never put on a live site.
 */
$dir  = __DIR__;
$raw  = file_get_contents( 'php://input' );
$body = json_decode( $raw, true );
$hdr  = function_exists( 'getallheaders' ) ? getallheaders() : array();
$mode = is_readable( $dir . '/mock-ai.mode' ) ? trim( file_get_contents( $dir . '/mock-ai.mode' ) ) : 'ok';
$prov = isset( $_GET['provider'] ) ? $_GET['provider'] : 'openai';

file_put_contents(
	$dir . '/mock-ai.log',
	json_encode(
		array(
			'provider' => $prov,
			'auth'     => isset( $hdr['Authorization'] ) ? $hdr['Authorization'] : ( isset( $hdr['x-api-key'] ) ? 'x-api-key ' . $hdr['x-api-key'] : ( isset( $hdr['x-goog-api-key'] ) ? 'goog ' . $hdr['x-goog-api-key'] : '' ) ),
			'body'     => $body,
		)
	) . "\n",
	FILE_APPEND
);

header( 'Content-Type: application/json' );
if ( '401' === $mode ) {
	http_response_code( 401 );
	echo json_encode( array( 'error' => array( 'message' => 'Incorrect API key provided.' ) ) );
	exit;
}
if ( '429' === $mode ) {
	http_response_code( 429 );
	echo json_encode( array( 'error' => array( 'message' => 'Rate limit reached.' ) ) );
	exit;
}

$text = 'OK';
$all  = json_encode( $body );
if ( false !== strpos( $all, 'Visit log' ) ) {
	$text = 'bad' === $mode ? '{"oops": ' : json_encode(
		array(
			'summary'     => 'The visitor came to compare pricing plans and tried to apply a coupon. The coupon button did not respond, which led to repeated clicks. They left from the pricing page without buying.',
			'goal'        => 'Compare plans and use a coupon',
			'struggles'   => array( 'Coupon button did not respond' ),
			'outcome'     => 'Left without buying',
			'frustration' => 'high',
		)
	);
}

if ( false !== strpos( $all, 'DEMO_JSON' ) ) {
	$text = json_encode( array( 'findings' => array( 'Most clicks hit the pricing button', 'Few visitors scroll past 50%' ) ) );
}

if ( 'claude' === $prov ) {
	echo json_encode(
		array(
			'model'   => isset( $body['model'] ) ? $body['model'] : '',
			'content' => array( array( 'type' => 'text', 'text' => $text ) ),
			'usage'   => array( 'input_tokens' => 900, 'output_tokens' => 120 ),
		)
	);
} elseif ( 'gemini' === $prov ) {
	echo json_encode(
		array(
			'candidates'    => array( array( 'content' => array( 'parts' => array( array( 'text' => $text ) ) ) ) ),
			'usageMetadata' => array( 'promptTokenCount' => 900, 'candidatesTokenCount' => 120 ),
		)
	);
} else {
	echo json_encode(
		array(
			'model'   => isset( $body['model'] ) ? $body['model'] : '',
			'choices' => array( array( 'message' => array( 'content' => $text ) ) ),
			'usage'   => array( 'prompt_tokens' => 900, 'completion_tokens' => 120 ),
		)
	);
}
