<?php
/**
 * Against the real service, with no key (only public routes): proves the
 * plain-PHP path end to end. Not part of the default run:
 *
 *     composer test:live
 *
 * @group live
 */

namespace AtlasVoice\Sdk\Tests;

use AtlasVoice\Sdk\Adapters\Plain_Php;
use PHPUnit\Framework\TestCase;

/**
 * @group live
 */
final class LiveTest extends TestCase {

	protected function setUp(): void {
		if ( ! getenv( 'ATLASVOICE_LIVE' ) ) {
			$this->markTestSkipped( 'Live checks run with ATLASVOICE_LIVE=1 (composer test:live).' );
		}
	}

	private function client() {
		$url = getenv( 'ATLASVOICE_URL' ) ?: Plain_Php::DEFAULT_URL;
		return Plain_Php::client( '', 'atlasvoice-sdk-tests/live', $url );
	}

	public function test_health_names_the_file_hosts_and_job_limits(): void {
		$result = $this->client()->health();
		$this->assertTrue( $result->ok(), $result->code() . ' ' . $result->message );
		$this->assertNotEmpty( $result->data['file_hosts'] );
		$this->assertArrayHasKey( 'jobs', $result->data );
	}

	public function test_voices_are_listed_and_can_be_narrowed_to_a_language(): void {
		$all = $this->client()->voices();
		$this->assertTrue( $all->ok(), $all->code() . ' ' . $all->message );
		$this->assertGreaterThan( 0, $all->data['count'] );

		$english = $this->client()->voices( 'en' );
		$this->assertTrue( $english->ok() );
		$this->assertGreaterThan( 0, $english->data['count'] );
		$this->assertLessThan( $all->data['count'], $english->data['count'] );
	}

	public function test_a_call_that_needs_a_key_is_refused_with_the_services_code(): void {
		$result = $this->client()->usage();
		$this->assertSame( 401, $result->status );
		$this->assertSame( 'missing_api_key', $result->code() );
	}
}
