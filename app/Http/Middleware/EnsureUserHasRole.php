<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level defense-in-depth for admin-only route clusters. This app's ~340
 * authenticated routes previously sat in one flat ['auth','verified'] group with
 * no role gate at the route layer — every admin boundary depended entirely on
 * each controller action remembering its own abort_unless(isAdmin()). Applied
 * only to routes whose controller is uniformly admin-only across every action
 * (verified individually) so it can't accidentally lock out an editor/reader
 * action that shares a controller with admin-only ones.
 *
 * Usage: ->middleware('role:admin')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() && $request->user()->hasAnyRole($roles), 403);

        return $next($request);
    }
}
