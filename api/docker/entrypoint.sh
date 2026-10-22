#!/usr/bin/env bash
# The container's only process at start. Render has no shell and no pre-deploy command, so
# the one-off steps run here, before the server listens: /up answering 200 means migrated,
# cached and ready. Any failure exits non-zero and Render restarts the container.
set -euo pipefail
cd /app

log() { echo "[entrypoint] $*" >&2; }

boot=""
web=""
scheduler=""

# A no-op until there is something to stop. Set before anything slow: bash as PID 1 ignores
# SIGTERM without a trap, so a stop during migrations would wait for Render's SIGKILL.
stop() {
    [ -n "$boot$web$scheduler" ] || return 0
    # Unquoted on purpose: an empty PID drops out instead of becoming a "" argument.
    kill -TERM $boot $web $scheduler 2>/dev/null || true
    wait || true
}

# Render stops a container with SIGTERM: pass it on and exit cleanly.
trap 'log "stopping"; stop; exit 0' TERM INT

# Bash runs a trap only between commands, so a foreground artisan call would delay it.
# In the background with a wait, a TERM interrupts the wait at once. Under set -e a failed
# step still exits with its own status.
step() {
    "$@" &
    boot=$!
    wait "$boot"
    boot=""
}

log "migrating"
step php artisan migrate --force --no-interaction

# Cached at start, not at build: config:cache freezes the environment, which only exists
# here. optimize caches config, events, routes and views in one PHP boot (they'd be four at
# ~0.1 CPU). route:cache is why the route bindings live in AppServiceProvider.
step php artisan optimize

log "starting the web server and the scheduler"
frankenphp run --config /etc/frankenphp/Caddyfile &
web=$!
php artisan schedule:work &
scheduler=$!

# Whichever exits first, the other goes too, so the container exits and gets restarted
# instead of running half an app.
status=0
wait -n "$web" "$scheduler" || status=$?
log "a process exited (status $status); stopping the container"
stop
# Neither process is meant to end, so even a clean exit is a failure here.
exit $(( status == 0 ? 1 : status ))