<?php

return [

    // Only API routes get CORS headers; health and web are untouched.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // The Nuxt dev origin. Widen when the app is served elsewhere.
    'allowed_origins' => ['http://localhost:3000'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // No auth, no cookies (single user) — keep false; true is incompatible with a wildcard origin.
    'supports_credentials' => false,

];