<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\CrossTenantSessionException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;
use Stancl\Tenancy\Middleware\ScopeSessions;

/**
 * Binds the session to the tenant that created it.
 *
 * Path-based tenancy puts every tenant on one origin, so all tenants share a cookie
 * jar and a session store. Without this check, a user authenticated at /acme who
 * visits /company-b still carries `login_web_<hash> = 1` in the session, and because
 * the database connection has already swapped they would be silently authenticated
 * as Company B's user #1 — full account takeover by editing the URL.
 *
 * Reuses stancl's ScopeSessions session key so the two can never disagree, but logs
 * the attempt and clears the stale session instead of a bare abort.
 */
class EnsureSessionBelongsToTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (! tenancy()->initialized) {
            throw new TenancyNotInitializedException(
                'Tenancy must be initialized before the session can be scoped.'
            );
        }

        $key = ScopeSessions::$tenantIdKey;
        $current = tenant()->getTenantKey();
        $session = $request->session();

        if (! $session->has($key)) {
            $session->put($key, $current);

            return $next($request);
        }

        if ($session->get($key) !== $current) {
            Log::warning('Cross-tenant session reuse blocked.', [
                'session_tenant_id' => $session->get($key),
                'request_tenant_id' => $current,
                'ip' => $request->ip(),
            ]);

            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            throw new CrossTenantSessionException;
        }

        return $next($request);
    }
}
