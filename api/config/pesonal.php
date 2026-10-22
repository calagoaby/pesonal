<?php

return [
    // Comma-separated. Read through App\Support\Admins, which normalizes and drops blanks.
    'admin_emails' => env('ADMIN_EMAILS', ''),
    // Shared with the Vercel proxy, which sends it as X-Pesonal-Proxy. Empty (local, tests) turns the gate off.
    'proxy_secret' => (string) env('PROXY_SECRET', ''),
    // Forwarded headers are only read on a request that passed the gate. "*" trusts every hop
    // (Vercel and Render publish no fixed egress ips), else comma-separated ips/CIDRs.
    'trusted_proxies' => env('TRUSTED_PROXIES', '*'),
    // Whose "today" the standing-order job charges on; production sets Asia/Manila. app.timezone
    // stays UTC either way, and the default keeps the local job on the UTC date it always used.
    'job_timezone' => env('JOB_TIMEZONE', 'UTC'),
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
    'trend' => [
        'default_months' => 6,
        'max_months' => 24,
    ],
];
