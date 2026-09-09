<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Middleware\EnsureSubscriptionIsValid;
use App\Http\Middleware\EnsureTenantIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Path-identified tenant routes. The tenant is resolved from the first URL
| segment by its slug (see App\Tenancy\Resolvers\SlugTenantResolver).
|
| The tenant Filament panel registers its own routes from TenantPanelProvider
| and is not declared here.
|
| These routes are loaded from TenancyServiceProvider::mapRoutes() inside
| $this->app->booted(), so they are registered AFTER routes/web.php and after
| Filament's panel routes. That ordering is what stops the greedy single-segment
| {tenant} catch-all from swallowing /admin, /up or /livewire/*.
|
*/

Route::middleware('tenant')
    ->prefix('{tenant}')
    ->name('tenant.')
    ->group(function () {
        // A super admin must be able to enter a suspended or expired tenant in order
        // to investigate it, so these are exempt from the tenant/subscription gates.
        // The token itself is the credential: 128 random chars, single use, 60s TTL,
        // and rejected outright if it was minted for a different tenant.
        Route::get('impersonate/{token}', [ImpersonationController::class, 'enter'])
            ->withoutMiddleware([EnsureTenantIsActive::class, EnsureSubscriptionIsValid::class])
            ->middleware('throttle:10,1')
            ->name('impersonation.enter');

        Route::post('impersonate/leave', [ImpersonationController::class, 'leave'])
            ->withoutMiddleware([EnsureTenantIsActive::class, EnsureSubscriptionIsValid::class])
            ->name('impersonation.leave');

        Route::view('subscription-expired', 'tenant.subscription-expired')
            ->withoutMiddleware([EnsureSubscriptionIsValid::class])
            ->name('subscription.expired');
    });
