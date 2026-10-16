<?php

return [

    // API routes plus Sanctum's CSRF cookie endpoint; health and web are untouched.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // The Nuxt origin (same key and default as app.frontend_url). Must be explicit (no wildcard)
    // because credentials are sent.
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Cookie sessions: the browser must send and accept cookies cross-origin (:3000 -> :8080).
    'supports_credentials' => true,

];
