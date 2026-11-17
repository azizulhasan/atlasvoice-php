<?php
/**
 * AtlasVoice SDK: a key given once, for example from an environment variable.
 *
 * @package AtlasVoice\Sdk
 */

namespace AtlasVoice\Sdk\Adapters;

use AtlasVoice\Sdk\Contracts\Key_Store;

/**
 * Holds the project's secret key (av_live_…). Read it from the environment
 * or a secrets manager, never from code in git.
 *
 * A publishable key (av_pub_…) is refused here: it is made for browsers and
 * cannot use the server routes this client calls.
 */
final class Static_Key_Store implements Key_Store {

	/**
	 * The key, or '' before the project has one.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Keeps the key.
	 *
	 * @param string $key The secret key, or '' (only register() works without one).
	 *
	 * @throws \InvalidArgumentException For a publishable key or a value that is not a key.
	 */
	public function __construct( $key = '' ) {
		$key = trim( (string) $key );
		if ( 0 === strpos( $key, 'av_pub_' ) ) {
			throw new \InvalidArgumentException( 'This is a publishable key (av_pub_): use the project\'s secret key on a server.' );
		}
		if ( '' !== $key && ! preg_match( '/^av_live_[A-Za-z0-9]{16,128}$/', $key ) ) {
			throw new \InvalidArgumentException( 'This is not an AtlasVoice secret key (av_live_…).' );
		}
		$this->key = $key;
	}

	/**
	 * The key.
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Never prints the key, even in a var_dump or a stack trace.
	 *
	 * @return array
	 */
	public function __debugInfo() {
		return array( 'key' => '' === $this->key ? '' : substr( $this->key, 0, 12 ) . '…' );
	}
}
