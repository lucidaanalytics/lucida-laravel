<?php

return [
    'api_key' => env('LUCIDA_API_KEY', ''),
    'secret_key' => env('LUCIDA_SECRET_KEY'),
    'host' => env('LUCIDA_HOST', 'https://app.lucida.dev'),
    'disabled' => (bool) env('LUCIDA_DISABLED', false),
    // Anonymous id cookie; never written unless consent is granted.
    'cookie' => ['name' => 'lc_id', 'max_age_minutes' => 60 * 24 * 365],
    // required=false means the host app already gates this middleware behind its own consent flow.
    'consent' => ['required' => true, 'cookie' => 'lc_consent', 'granted' => ['analytics', 'granted', 'true', '1']],

    // Request context set by the middleware for every capture() inside the request.
    'use_tracing_headers' => true,   // adopt X-Lucida-Distinct-Id / X-Lucida-Session-Id from the browser SDK (client-controlled: never for auth)
    'capture_user_context' => true,  // the authenticated user id becomes the distinct id
    'trust_proxy' => false,          // true: $ip is the first X-Forwarded-For entry, else REMOTE_ADDR
    'flush_on_queue' => true,        // flush after each queue job / worker loop (JobProcessed, JobFailed, Looping)

    // Delivery and flag options passed to Lucida\Client (null/unset = client default).
    'flush_at' => env('LUCIDA_FLUSH_AT'),
    'max_batch_size' => env('LUCIDA_MAX_BATCH_SIZE'),
    'flush_interval' => env('LUCIDA_FLUSH_INTERVAL'),
    'max_retries' => env('LUCIDA_MAX_RETRIES'),
    'retry_delay' => env('LUCIDA_RETRY_DELAY'),
    'timeout' => env('LUCIDA_TIMEOUT'),
    'debug' => env('LUCIDA_DEBUG'),
    'flag_poll_interval' => env('LUCIDA_FLAG_POLL_INTERVAL'),
    'only_evaluate_locally' => env('LUCIDA_ONLY_EVALUATE_LOCALLY'),
    'fallbacks' => [], // ['flag-key' => value] served (reason "fallback") when a flag read fails
];
