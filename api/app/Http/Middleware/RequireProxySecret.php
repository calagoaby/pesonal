<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// In production the API answers only its own proxy (Vercel), which adds X-Pesonal-Proxy. Anyone
// else gets the router's missing-route 404 before a session starts or a query runs, so a
// stranger can't write a session row or wake the sleeping database. Prepended to the global
// stack (bootstrap/app.php): global middleware runs before routing, so no route group or
// priority list can put StartSession or Sanctum ahead of it. /up stays open for Render's
// health check. An empty PROXY_SECRET (local, tests) turns the gate off.
class RequireProxySecret
{
    public const HEADER = 'X-Pesonal-Proxy';

    // Set on a request that carried the secret; TrustProxies reads forwarded headers only then.
    public const PASSED = 'pesonal.proxied';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('pesonal.proxy_secret');

        if ($secret === '' || $request->is('up')) {
            return $next($request);
        }

        if (! hash_equals($secret, (string) $request->headers->get(self::HEADER))) {
            throw new NotFoundHttpException(sprintf('The route %s could not be found.', $request->path()));
        }

        $request->attributes->set(self::PASSED, true);

        return $next($request);
    }
}
