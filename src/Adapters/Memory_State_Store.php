<?php
/**
 * AtlasVoice SDK: a state store that lives as long as the PHP process.
 *
 * @package AtlasVoice\Sdk
 */

namespace AtlasVoice\Sdk\Adapters;

use AtlasVoice\Sdk\Contracts\State_Store;

/**
 * Keeps the circuit breaker's state in memory. Good for a worker or a long
 * running process; a web app whose requests do not share memory should give
 * the client a store backed by its cache (Redis, APCu, the framework cache)
 * so one request learns that the service is down for the next.
 */
final class Memory_State_Store implements State_Store {

	/**
	 * Name => [ value, expires_at ].
	 *
	 * @var array
	 */
	private $values = array();

	/**
	 * Reads a value.
	 *
	 * @param string $name Name of the value.
	 *
	 * @return array|null
	 */
	public function get( $name ) {
		$name = (string) $name;
		if ( ! isset( $this->values[ $name ] ) ) {
			return null;
		}
		if ( $this->values[ $name ][1] <= microtime( true ) ) {
			unset( $this->values[ $name ] );
			return null;
		}
		return $this->values[ $name ][0];
	}

	/**
	 * Stores a value.
	 *
	 * @param string $name    Name of the value.
	 * @param array  $value   What to keep.
	 * @param int    $seconds How long to keep it.
	 */
	public function set( $name, array $value, $seconds ) {
		$this->values[ (string) $name ] = array( $value, microtime( true ) + max( 1, (int) $seconds ) );
	}

	/**
	 * Forgets a value.
	 *
	 * @param string $name Name of the value.
	 */
	public function delete( $name ) {
		unset( $this->values[ (string) $name ] );
	}
}
