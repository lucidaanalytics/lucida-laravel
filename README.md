# lucida-laravel

Laravel wrapper over `lucida/lucida` (`../php`, path repository).

1. Install from GitHub, [lucidaanalytics/lucida-laravel](https://github.com/lucidaanalytics/lucida-laravel), release `v0.1.0` (neither package is on Packagist yet, so both need a `vcs` repository entry; the Packagist install is coming): `composer config repositories.lucida vcs https://github.com/lucidaanalytics/lucida-php && composer config repositories.lucida-laravel vcs https://github.com/lucidaanalytics/lucida-laravel && composer require "lucida/lucida:^0.1" "lucida/lucida-laravel:^0.1"`; set `LUCIDA_API_KEY`, `LUCIDA_HOST`, optional `LUCIDA_SECRET_KEY` (local flag evaluation). Publish config: `php artisan vendor:publish --tag=lucida-config`.
2. Add `Lucida\Laravel\LucidaMiddleware` to the `web` group. Distinct id = auth user id, else the `lc_id` cookie, which is only written when the consent cookie (`lucida.consent`) grants analytics.
3. `Lucida::capture($id, 'event')` through the facade. `@lucidaBootstrap` in the layout head emits `window.__LUCIDA_BOOTSTRAP__ = {distinctId, flags}` (script-safe JSON) for the JS SDK `bootstrap` option.

## Request context

The middleware wraps each request in `Lucida\Client::withContext`, so `Lucida::capture(null, 'event')` needs no id and
every event carries `$session_id` and `$current_url`, `$request_method`, `$request_path`, `$user_agent`, `$ip`
(explicit arguments always win). Distinct id priority: authenticated user (`capture_user_context`), then the browser
SDK's `X-Lucida-Distinct-Id` header (`use_tracing_headers`; client-controlled, never use for auth), then the visitor id above.
`trust_proxy` takes `$ip` from the first `X-Forwarded-For` entry instead of `REMOTE_ADDR`. The context is `fresh` per
request, so it is Octane-safe; `terminate()` flushes queued events after the response is sent.

## Queue workers

With `flush_on_queue` (default on) the provider flushes the client on `JobProcessed`, `JobFailed`, `JobExceptionOccurred`
and `Looping`, so a worker never holds events. Wrap job code in `Lucida::withContext([...], fn () => ...)` to set an id.

## Config

Besides keys and consent: `use_tracing_headers`, `capture_user_context`, `trust_proxy`, `flush_on_queue`, and client options `flush_at`, `max_batch_size`, `flush_interval`, `max_retries`, `retry_delay`,
`timeout`, `debug`, `flag_poll_interval`, `only_evaluate_locally` (env `LUCIDA_*`, unset = client default).

Tests: `php tests/run.php` or `make sdk-laravel` (pure pieces: visitor, config mapping, request context; no Laravel
install needed, so the middleware, provider and queue listener are only syntax-checked).

---

This repository is a read-only mirror of the Lucida monorepo (synced by a script, never edited by hand); releases and provenance attestations are built here by GitHub Actions. Issues are welcome, changes land upstream.
