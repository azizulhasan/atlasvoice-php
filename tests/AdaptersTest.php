<?php
/**
 * The plain-PHP adapters: cURL transport (against a local test server),
 * memory state store, key store and the one-call factory.
 */

namespace AtlasVoice\Sdk\Tests;

use AtlasVoice\Sdk\Adapters\Curl_Transport;
use AtlasVoice\Sdk\Adapters\Memory_State_Store;
use AtlasVoice\Sdk\Adapters\Plain_Php;
use AtlasVoice\Sdk\Adapters\Static_Key_Store;
use AtlasVoice\Sdk\Client;
use AtlasVoice\Sdk\Request;
use PHPUnit\Framework\TestCase;

final class AdaptersTest extends TestCase {

	/** @var resource|null */
	private static $server;

	/** @var string */
	private static $base;

	const KEY = 'av_live_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

	public static function setUpBeforeClass(): void {
		$port = 18700 + random_int( 0, 999 );
		$cmd  = escapeshellarg( PHP_BINARY ) . ' -S 127.0.0.1:' . $port . ' ' . escapeshellarg( __DIR__ . '/fixtures/router.php' );
		self::$server = proc_open( $cmd, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
		self::$base   = 'http://127.0.0.1:' . $port;
		for ( $i = 0; $i < 50; $i++ ) {
			$socket = @fsockopen( '127.0.0.1', $port, $errno, $errstr, 0.1 );
			if ( $socket ) {
				fclose( $socket );
				return;
			}
			usleep( 100000 );
		}
		self::fail( 'the test server did not start' );
	}

	public static function tearDownAfterClass(): void {
		if ( self::$server ) {
			$status = proc_get_status( self::$server );
			// php -S runs as a child of the shell on Windows: end the whole tree.
			if ( '\\' === DIRECTORY_SEPARATOR ) {
				exec( 'taskkill /F /T /PID ' . (int) $status['pid'] . ' 2>NUL' );
			}
			proc_terminate( self::$server );
		}
	}

	private function send( $method, $path, array $headers = array(), $body = null, $timeout = 10, $follow = true ) {
		return ( new Curl_Transport() )->send( new Request( $method, self::$base . $path, $headers, $body, $timeout, $follow ) );
	}

	public function test_curl_sends_method_headers_and_body_and_reads_the_answer(): void {
		$r = $this->send( 'POST', '/echo', array( 'Content-Type' => 'application/json', 'X-AtlasVoice-Client' => 'test/1' ), '{"text":"Hi"}' );
		$this->assertSame( 200, $r->status );
		$this->assertSame( '', $r->transport_error );
		$this->assertSame( 'srv-echo-0001', $r->headers['x-request-id'], 'header names are lower case' );
		$echo = json_decode( $r->body, true );
		$this->assertSame( 'POST', $echo['method'] );
		$this->assertSame( 'test/1', $echo['headers']['x-atlasvoice-client'] );
		$this->assertSame( '{"text":"Hi"}', $echo['body'] );
	}

	public function test_curl_keeps_audio_bytes_untouched(): void {
		$r = $this->send( 'GET', '/audio' );
		$this->assertSame( "ID3\x04audio-bytes", $r->body );
		$this->assertSame( 'audio/mpeg', $r->headers['content-type'] );
	}

	public function test_an_error_status_is_an_answer_not_a_transport_failure(): void {
		$r = $this->send( 'GET', '/error' );
		$this->assertSame( 429, $r->status );
		$this->assertSame( '30', $r->headers['retry-after'] );
		$this->assertSame( '', $r->transport_error );
	}

	public function test_a_request_with_a_key_never_follows_a_redirect(): void {
		$r = $this->send( 'GET', '/redirect', array( 'Authorization' => 'Bearer ' . self::KEY ) );
		$this->assertSame( 302, $r->status, 'the key must not travel to the Location' );
	}

	public function test_a_request_without_a_key_follows_a_redirect_only_when_allowed(): void {
		$this->assertSame( 200, $this->send( 'GET', '/redirect' )->status );
		$this->assertSame( 302, $this->send( 'GET', '/redirect', array(), null, 10, false )->status );
	}

	public function test_a_timeout_and_an_unreachable_host_are_failures_not_exceptions(): void {
		$slow = $this->send( 'GET', '/slow', array(), null, 1 );
		$this->assertSame( 0, $slow->status );
		$this->assertNotSame( '', $slow->transport_error );
		$closed = ( new Curl_Transport( 1 ) )->send( new Request( 'GET', 'http://127.0.0.1:1/x', array(), null, 2 ) );
		$this->assertSame( 0, $closed->status );
		$this->assertNotSame( '', $closed->transport_error );
	}

	public function test_memory_store_keeps_values_until_they_expire(): void {
		$store = new Memory_State_Store();
		$this->assertNull( $store->get( 'x' ) );
		$store->set( 'x', array( 'failures' => 2 ), 1 );
		$this->assertSame( array( 'failures' => 2 ), $store->get( 'x' ) );
		$store->delete( 'x' );
		$this->assertNull( $store->get( 'x' ) );
		$store->set( 'y', array( 1 ), 1 );
		usleep( 1100000 );
		$this->assertNull( $store->get( 'y' ), 'expired' );
	}

	public function test_key_store_takes_a_secret_key_and_refuses_a_browser_key(): void {
		$this->assertSame( self::KEY, ( new Static_Key_Store( '  ' . self::KEY . "\n" ) )->get_key() );
		$this->assertSame( '', ( new Static_Key_Store( '' ) )->get_key() );
		foreach ( array( 'av_pub_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', 'not-a-key', 'av_live_short' ) as $bad ) {
			try {
				new Static_Key_Store( $bad );
				$this->fail( "accepted $bad" );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertStringNotContainsString( $bad, $e->getMessage(), 'a refused key is never echoed' );
			}
		}
	}

	public function test_the_key_never_shows_in_a_dump(): void {
		$dump = print_r( ( new Static_Key_Store( self::KEY ) )->__debugInfo(), true );
		$this->assertStringNotContainsString( self::KEY, $dump );
		ob_start();
		var_dump( new Static_Key_Store( self::KEY ) );
		$this->assertStringNotContainsString( self::KEY, (string) ob_get_clean() );
	}

	public function test_plain_php_wires_a_working_client(): void {
		$client = Plain_Php::client( self::KEY, 'tests/1.0', self::$base );
		$this->assertInstanceOf( Client::class, $client );
		// /v1/health does not exist on the test server: a plain 404 result, no exception.
		$result = $client->health();
		$this->assertSame( 404, $result->status );
		$this->assertFalse( $result->ok() );
	}
}
