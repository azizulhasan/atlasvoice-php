# Changelog

## 0.4.0 (2026-11-17)

First release as its own package. Until now the client lived only inside the AtlasVoice
WordPress plugin (libs/AtlasVoice/Sdk); the plugin now copies it from here.

- Speech, audio jobs (create, read, list, cancel, safe download), voices, account, usage,
  licences and health.
- Circuit breaker: a service that is down is not called again on every request.
- New plain-PHP adapters: a cURL transport (TLS always checked, a request with a key never
  follows a redirect), a memory state store, a key store that refuses browser keys, and
  `Plain_Php::client()` to get a working client in one call.
- Tests run without WordPress (PHPUnit, PHP 7.4 to 8.4), plus live checks of the public routes.
- MIT licence, so any app (open or closed source) can use it.
