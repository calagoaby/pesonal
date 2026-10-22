<?php

return [
    // Comma-separated. Read through App\Support\Admins, which normalizes and drops blanks.
    'admin_emails' => env('ADMIN_EMAILS', ''),
    // Shared with the Vercel proxy, which sends it as X-Pesonal-Proxy. Empty (local, tests) turns the gate off.
    'proxy_secret' => (string) env('PROXY_SECRET', ''),
    // Whose "today" the standing-order job charges on: ledger dates are Manila dates. app.timezone stays UTC.
    'job_timezone' => env('JOB_TIMEZONE', 'Asia/Manila'),
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
    'trend' => [
        'default_months' => 6,
        'max_months' => 24,
    ],
];
