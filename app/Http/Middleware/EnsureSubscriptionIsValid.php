<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\SubscriptionNotActiveException;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;

class EnsureSubscriptionIsValid
{
    public function handle(Request $request, Closure $next)
    {
        if (! tenancy()->initialized) {
            throw new TenancyNotInitializedException(
                'Tenancy must be initialized before the subscription can be checked.'
            );
        }

        // Login, logout and the expired page itself must stay reachable, otherwise the
        // user is locked out by the very middleware that is meant to explain why.
        if ($this->isExempt($request)) {
            return $next($request);
        }

        $status = tenant()->subscriptionStatus();

        if (! $status->allowsAccess()) {
            throw new SubscriptionNotActiveException($status);
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        $exempt = config('tenancy.subscription_exempt_routes', []);

        return $exempt !== [] && $request->routeIs(...$exempt);
    }
}
