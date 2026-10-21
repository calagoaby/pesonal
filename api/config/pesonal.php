<?php

return [
    // Comma-separated. Read through App\Support\Admins, which normalizes and drops blanks.
    'admin_emails' => env('ADMIN_EMAILS', ''),
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
    'trend' => [
        'default_months' => 6,
        'max_months' => 24,
    ],
];
