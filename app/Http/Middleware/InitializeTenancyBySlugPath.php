<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\Resolvers\SlugTenantResolver;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\RouteIsMissingTenantParameterException;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Stancl\Tenancy\Tenancy;

class InitializeTenancyBySlugPath extends IdentificationMiddleware
{
    public function __construct(Tenancy $tenancy, SlugTenantResolver $resolver)
    {
        $this->tenancy = $tenancy;
        $this->resolver = $resolver;
    }

    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();
        $parameter = PathTenantResolver::$tenantParameterName;

        // Stancl only treats the route as tenant-scoped when the tenant is the first
        // parameter, so that an injected {tenant} elsewhere cannot initialise tenancy.
        if (($route->parameterNames()[0] ?? null) !== $parameter) {
            throw new RouteIsMissingTenantParameterException;
        }

        // Tenancy::initialize() returns early when the same tenant key is already
        // initialised, which keeps the previously loaded tenant instance and discards
        // the one just resolved. In a process that serves more than one request —
        // Octane, a queue worker, the test suite — that means acting on a stale copy,
        // so a tenant suspended a moment ago would still be treated as active. Ending
        // first makes this middleware idempotent and always act on fresh state.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        return $this->initializeTenancy($request, $next, $route);
    }
}
