<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Used next to "signed": refuses signed links that have no expiry, so the old
 * never-expiring auto-login links (issued before they became temporary) stop working.
 */
class RequireExpiringSignature
{
    public function handle($request, Closure $next)
    {
        if (empty($request->query('expires'))) {
            abort(403);
        }

        return $next($request);
    }
}
