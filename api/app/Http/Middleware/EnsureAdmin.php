<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Admins;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Stands in for auth:sanctum on the admin routes. Anyone who isn't an admin, signed in or
// not, gets exactly what a route that doesn't exist answers — not a 401 or 403 that would
// say there's something here. Runs ahead of throttling and route-model binding (priority
// list in bootstrap/app.php), so neither a 429 nor a binding's 404 can tell them apart.
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if (! $user instanceof User || ! Admins::isAdmin($user)) {
            // The router's own wording for a missing route (AbstractRouteCollection).
            throw new NotFoundHttpException(sprintf('The route %s could not be found.', $request->path()));
        }

        // Past here $request->user() is the admin, as auth:sanctum would have left it.
        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
