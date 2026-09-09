<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantIsNotActiveException;
use App\Exceptions\TenantIsNotReadyException;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;

/**
 * Throws rather than redirects: Livewire aborts when persistent middleware returns a
 * RedirectResponse, which would turn a clear "tenant suspended" page into an opaque
 * failure on every AJAX request. The exceptions are mapped to responses in bootstrap/app.php.
 */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (! tenancy()->initialized) {
            throw new TenancyNotInitializedException(
                'Tenancy must be initialized before the tenant status can be checked.'
            );
        }

        $tenant = tenant();

        if (! $tenant->provisioning_status->isUsable()) {
            throw new TenantIsNotReadyException($tenant->provisioning_status);
        }

        if (! $tenant->status->allowsAccess()) {
            throw new TenantIsNotActiveException($tenant->status);
        }

        return $next($request);
    }
}
