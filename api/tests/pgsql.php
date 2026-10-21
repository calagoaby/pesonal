<?php

// `composer test:pgsql`: the same suite as `php artisan test`, against the local Postgres.
//
// phpunit.xml pins DB_CONNECTION=sqlite / DB_DATABASE=:memory: through <env>, but <env>
// (without force) only fills a variable that isn't already a real environment variable. So
// the DB_* keys from the gitignored .env.testing.pgsql are handed to the child process as
// real env vars and win; every other phpunit.xml default (array session, sync queue…)
// stays. Only DB_* is taken: that file may be a full copy of .env.example, and its
// SESSION_DRIVER/QUEUE_CONNECTION would otherwise leak in. CI sets the same DB_* through
// the job env instead.

use Dotenv\Parser\Parser;

require __DIR__.'/../vendor/autoload.php';

$path = __DIR__.'/../.env.testing.pgsql';

if (! is_file($path)) {
    fwrite(STDERR, "Missing api/.env.testing.pgsql (DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD).\n");
    exit(1);
}

$database = [];
foreach ((new Parser)->parse((string) file_get_contents($path)) as $entry) {
    $value = $entry->getValue();
    if (str_starts_with($entry->getName(), 'DB_') && $value->isDefined()) {
        $database[$entry->getName()] = $value->get()->getChars();
    }
}

if (($database['DB_CONNECTION'] ?? null) !== 'pgsql') {
    fwrite(STDERR, "api/.env.testing.pgsql must set DB_CONNECTION=pgsql.\n");
    exit(1);
}

$command = array_merge([PHP_BINARY, 'artisan', 'test'], array_slice($argv, 1));
$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__), array_merge(getenv(), $database));

exit(is_resource($process) ? proc_close($process) : 1);
