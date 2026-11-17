<?php
/**
 * AtlasVoice SDK: one call to get a working client in a plain PHP app.
 *
 * @package AtlasVoice\Sdk
 */

namespace AtlasVoice\Sdk\Adapters;

use AtlasVoice\Sdk\Circuit_Breaker;
use AtlasVoice\Sdk\Client;
use AtlasVoice\Sdk\Config;
use AtlasVoice\Sdk\Contracts\Logger;
use AtlasVoice\Sdk\Contracts\State_Store;

/**
 * Wires the client with the plain-PHP adapters:
 *
 *     $voice = \AtlasVoice\Sdk\Adapters\Plain_Php::client( getenv( 'ATLASVOICE_KEY' ), 'my-app/1.0' );
 *     $result = $voice->synthesize( 'Hello world.', 'en' );
 *
 * Frameworks can pass their own cache as the state store so every request
 * shares the circuit breaker, and their own logger.
 */
final class Plain_Php {

	const DEFAULT_URL = 'https://api.atlasvoice.cloud';

	/**
	 * Builds a client.
	 *
	 * @param string           $api_key  The project's secret key (av_live_…), or '' to register first.
	 * @param string           $app_name Your app and version, e.g. "my-app/1.0"; it appears in the service's logs.
	 * @param string           $base_url The service address.
	 * @param State_Store|null $state    Where the circuit breaker keeps its state; memory by default.
	 * @param Logger|null      $logger   Where calls are reported; none by default.
	 *
	 * @return Client
	 */
	public static function client( $api_key, $app_name, $base_url = self::DEFAULT_URL, $state = null, $logger = null ) {
		$store = $state instanceof State_Store ? $state : new Memory_State_Store();

		return new Client(
			new Config( $base_url, (string) $app_name ),
			new Curl_Transport(),
			new Static_Key_Store( $api_key ),
			$logger instanceof Logger ? $logger : null,
			new Circuit_Breaker( $store, 'atlasvoice_api' )
		);
	}
}
