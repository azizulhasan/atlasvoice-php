<?php
/**
 * AtlasVoice SDK: send requests with PHP's cURL extension.
 *
 * @package AtlasVoice\Sdk
 */

namespace AtlasVoice\Sdk\Adapters;

use AtlasVoice\Sdk\Contracts\Http_Transport;
use AtlasVoice\Sdk\Request;
use AtlasVoice\Sdk\Response;

/**
 * The transport for plain PHP apps (Laravel, Symfony, a script). WordPress
 * sites use the plugin's WP_Http_Transport instead: the WordPress plugin
 * directory asks plugins to use the WordPress HTTP API, so this file is never
 * copied into a plugin (bin/copy-to-plugin leaves Adapters/ out).
 *
 * Safety rules it adds to the client's own:
 *  - TLS certificates are always checked;
 *  - only http and https are allowed, also after a redirect;
 *  - a request that carries a key never follows a redirect, so the key can
 *    never be sent to another address.
 */
final class Curl_Transport implements Http_Transport {

	/**
	 * Most redirects followed for a request without a key.
	 */
	const MAX_REDIRECTS = 3;

	/**
	 * Seconds to wait for the connection itself.
	 *
	 * @var int
	 */
	private $connect_timeout;

	/**
	 * Builds the transport.
	 *
	 * @param int $connect_timeout Seconds to wait for the connection (the whole call has its own limit).
	 *
	 * @throws \RuntimeException When the cURL extension is missing.
	 */
	public function __construct( $connect_timeout = 10 ) {
		if ( ! function_exists( 'curl_init' ) ) {
			throw new \RuntimeException( 'The AtlasVoice SDK needs the PHP cURL extension for Curl_Transport.' );
		}
		$this->connect_timeout = max( 1, (int) $connect_timeout );
	}

	/**
	 * Sends one request.
	 *
	 * @param Request $request What to send.
	 *
	 * @return Response Never throws: a failure is Response::failed().
	 */
	public function send( Request $request ) {
		$headers  = array();
		$has_auth = false;
		foreach ( $request->headers as $name => $value ) {
			$headers[] = $name . ': ' . $value;
			if ( 0 === strcasecmp( (string) $name, 'Authorization' ) ) {
				$has_auth = true;
			}
		}

		$received = array();
		$handle   = curl_init( $request->url );
		$options  = array(
			CURLOPT_CUSTOMREQUEST  => $request->method,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => max( 1, (int) $request->timeout ),
			CURLOPT_CONNECTTIMEOUT => min( $this->connect_timeout, max( 1, (int) $request->timeout ) ),
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FOLLOWLOCATION => $request->follow_redirects && ! $has_auth,
			CURLOPT_MAXREDIRS      => self::MAX_REDIRECTS,
			CURLOPT_HEADERFUNCTION => function ( $curl, $line ) use ( &$received ) {
				$parts = explode( ':', $line, 2 );
				if ( 2 === count( $parts ) ) {
					// After a redirect the last response's headers win.
					$received[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
				} elseif ( 0 === stripos( $line, 'HTTP/' ) ) {
					$received = array();
				}
				return strlen( $line );
			},
		);
		if ( defined( 'CURLOPT_PROTOCOLS' ) ) {
			$options[ CURLOPT_PROTOCOLS ]       = CURLPROTO_HTTP | CURLPROTO_HTTPS;
			$options[ CURLOPT_REDIR_PROTOCOLS ] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
		}
		if ( null !== $request->body ) {
			$options[ CURLOPT_POSTFIELDS ] = $request->body;
		}
		curl_setopt_array( $handle, $options );

		$body   = curl_exec( $handle );
		$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		$error  = false === $body ? curl_error( $handle ) : '';
		if ( PHP_VERSION_ID < 80000 ) {
			curl_close( $handle ); // From PHP 8 the handle is freed with the object.
		}

		if ( false === $body || 0 === $status ) {
			return Response::failed( '' !== $error ? $error : 'The AtlasVoice service could not be reached.' );
		}

		return new Response( $status, $received, (string) $body );
	}
}
