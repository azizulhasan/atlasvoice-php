# AtlasVoice PHP SDK

The PHP client for [AtlasVoice Cloud](https://atlasvoice.cloud) text-to-speech: turn text into
natural speech, make whole articles into one MP3 in the background, list voices, and read your
plan and usage.

- Works in plain PHP, Laravel, Symfony and WordPress. No framework is needed.
- PHP 7.4 or newer. The only requirement is the JSON extension; cURL is needed for the plain-PHP
  adapters.
- A failure is a `Result` with a stable error code, never an exception.
- Your key is sent only to the AtlasVoice service and is never logged, returned or put in an error.

## Install

```bash
composer require atlasaidev/atlasvoice-sdk
```

## Quick start

Create a project in the [AtlasVoice dashboard](https://app.atlasvoice.cloud) and copy its
**secret key** (`av_live_…`). Keep it on your server, for example in an environment variable.

```php
use AtlasVoice\Sdk\Adapters\Plain_Php;

$voice = Plain_Php::client( getenv( 'ATLASVOICE_KEY' ), 'my-app/1.0' );

$result = $voice->synthesize( 'Hello! This is AtlasVoice.', 'en' );

if ( $result->ok() ) {
    file_put_contents( 'hello.mp3', $result->data );
} else {
    echo $result->code();          // e.g. quota_exceeded
    echo $result->request_id;      // quote this to support
}
```

`'my-app/1.0'` names your app in the service's logs, so support can find your calls.

> **Never put the secret key in a web page.** For speech in visitors' browsers, use a
> publishable key (`av_pub_…`) with the
> [JavaScript SDK](https://www.npmjs.com/package/text-to-speech-tts). This client refuses a
> publishable key.

## Long texts: audio jobs

Send a whole article once (up to 200,000 characters). The service makes it in the background and
joins it into one MP3, kept for 48 hours.

```php
use AtlasVoice\Sdk\Client;

$ref = Client::job_ref( 'article:42:en' );
$job = $voice->create_job( $text, 'en', array(
    'ref'             => $ref,
    'idempotency_key' => Client::idempotency_key( $ref, $text ), // a retry is never billed twice
) );

// Later (a queue worker or a cron job, never inside a visitor's request):
$status = $voice->get_job( $job->data['job']['id'] );
if ( 'done' === $status->data['job']['status'] ) {
    $hosts = $voice->health()->data['file_hosts'];
    $mp3   = $voice->download( $status->data['job']['file']['url'], $hosts );
}
```

Ask again no sooner than the `poll_after_ms` the service returns. Check the file's `bytes` and
`sha256` before you store it.

**Natural voices (Premium):** pass `'engine' => 'cloud'` and a `'voice'` id from
`voices( $lang, 'cloud' )`, with that voice's `locale` as the language (`'en-US'`, not `'en'`).

## Voices

```php
$all     = $voice->voices();                 // every voice (no key is sent)
$english = $voice->voices( 'en' );           // one language
$natural = $voice->voices( 'en', 'cloud' );  // the Premium natural voices: id, name, locale, gender
```

Voices change rarely: keep the list for a while instead of asking on every page.

## Other calls

| Method | What it does |
|---|---|
| `register( $platform, $site_url, $email, $name )` | Asks for a project key (sent without a key) |
| `account()` | Plan, engines, allowance, limits, licence and links, in one call |
| `usage()` | This month's characters used and left |
| `attach_license( $key )` / `detach_license()` | Connect or remove a Pro licence |
| `get_jobs( $ids )` / `get_jobs_by_ref( $prefix )` / `cancel_job( $id )` | Manage jobs |
| `health()` | Service status, file hosts and job limits (no key) |

## Results and errors

Every call returns a `Result`:

- `ok()`: the call succeeded.
- `data`: the decoded answer (or MP3 bytes for audio).
- `code()`: a stable error code such as `invalid_api_key`, `quota_exceeded` or `rate_limited`.
- `is_temporary()` and `retry_after()`: whether to try again and when.
- `request_id`: follow one call through both sides' logs.

The full list of codes is in [`Error_Codes`](src/Error_Codes.php) and the
[API documentation](https://atlasvoice.cloud/api/).

## In a framework

`Plain_Php::client()` keeps the circuit breaker's state in memory. In a web app, pass a store
backed by your cache, so one request learns that the service is down for the next:

```php
$voice = Plain_Php::client( $key, 'my-app/1.0', Plain_Php::DEFAULT_URL, new MyCacheStateStore(), new MyLogger() );
```

Implement `Contracts\State_Store` (get, set, delete) and, if you want logs, `Contracts\Logger`.
For full control, build `Client` yourself with your own `Contracts\Http_Transport`.

## WordPress

The AtlasVoice WordPress plugin bundles this client in `libs/AtlasVoice/Sdk` and uses the
WordPress HTTP API. To update it from this package:

```bash
node bin/copy-to-plugin.js path/to/wp-content/plugins/text-to-audio
node bin/copy-to-plugin.js path/to/wp-content/plugins/text-to-audio --check   # CI: fail if they differ
```

`src/Adapters` is never copied: the WordPress plugin directory does not allow direct cURL calls.

## Development

```bash
composer install
composer test        # unit tests, no network
composer test:live   # also calls the real service's public routes
```

## License

MIT. See [LICENSE](LICENSE). The AtlasVoice WordPress plugin bundles this client under its own GPL licence; MIT is compatible with that.
