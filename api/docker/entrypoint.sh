#!/usr/bin/env bash
# The container's only process at start. Render has no shell and no pre-deploy command, so
# the one-off steps run here, before the server listens: /up answering 200 means migrated,
# cached and ready. Any failure exits non-zero and Render restarts the container.
set -euo pipefail
cd /app

log() { echo "[entrypoint] $*" >&2; }

log "migrating"
php artisan migrate --force --no-interaction

# Cached at start, not at build: config:cache freezes the environment, which only exists
# here. route:cache is why the route bindings live in AppServiceProvider.
php artisan config:cache
php artisan route:cache
php artisan event:cache

log "starting the web server and the scheduler"
frankenphp run --config /etc/frankenphp/Caddyfile &
web=$!
php artisan schedule:work &
scheduler=$!

stop() {
    kill -TERM "$web" "$scheduler" 2>/dev/null || true
    wait || true
}

# Render stops a container with SIGTERM: pass it on and exit cleanly.
trap 'log "stopping"; stop; exit 0' TERM INT

# Whichever exits first, the other goes too, so the container exits and gets restarted
# instead of running half an app.
status=0
wait -n "$web" "$scheduler" || status=$?
log "a process exited (status $status); stopping the container"
stop
# Neither process is meant to end, so even a clean exit is a failure here.
exit $(( status == 0 ? 1 : status ))