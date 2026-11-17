<?php
/**
 * A tiny HTTP server for the transport tests (php -S 127.0.0.1:PORT router.php).
 */
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

$headers = array();
foreach ( $_SERVER as $name => $value ) {
	if ( 0 === strpos( $name, 'HTTP_' ) ) {
		$headers[ strtolower( str_replace( '_', '-', substr( $name, 5 ) ) ) ] = $value;
	}
}
if ( isset( $_SERVER['CONTENT_TYPE'] ) ) {
	$headers['content-type'] = $_SERVER['CONTENT_TYPE'];
}

switch ( $path ) {
	case '/echo':
		header( 'Content-Type: application/json' );
		header( 'X-Request-Id: srv-echo-0001' );
		echo json_encode( array( 'method' => $_SERVER['REQUEST_METHOD'], 'headers' => $headers, 'body' => file_get_contents( 'php://input' ) ) );
		break;
	case '/redirect':
		header( 'Location: /echo', true, 302 );
		break;
	case '/audio':
		header( 'Content-Type: audio/mpeg' );
		echo "ID3\x04audio-bytes";
		break;
	case '/error':
		http_response_code( 429 );
		header( 'Content-Type: application/json' );
		header( 'Retry-After: 30' );
		echo json_encode( array( 'error' => array( 'code' => 'rate_limited', 'message' => 'Slow down.' ) ) );
		break;
	case '/slow':
		sleep( 3 );
		echo 'late';
		break;
	default:
		http_response_code( 404 );
		echo 'not found';
}
