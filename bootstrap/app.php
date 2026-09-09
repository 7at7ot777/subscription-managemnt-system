<?php

use App\Exceptions\CrossTenantSessionException;
use App\Exceptions\SubscriptionNotActiveException;
use App\Exceptions\TenantDatabaseUnavailableException;
use App\Exceptions\TenantIsNotActiveException;
use App\Exceptions\TenantIsNotReadyException;
use App\Http\Middleware\EnsureSessionBelongsToTenant;
use App\Http\Middleware\EnsureSubscriptionIsValid;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\InitializeTenancyBySlugPath;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => InitializeTenancyBySlugPath::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.subscribed' => EnsureSubscriptionIsValid::class,
            'tenant.session' => EnsureSessionBelongsToTenant::class,
        ]);

        $middleware->group('tenant', [
            'web',
            InitializeTenancyBySlugPath::class,
            EnsureSessionBelongsToTenant::class,
            EnsureTenantIsActive::class,
            EnsureSubscriptionIsValid::class,
        ]);

        // EnsureSessionBelongsToTenant reads the session, so it can only run once
        // StartSession has built one.
        $middleware->appendToPriorityList(
            StartSession::class,
            EnsureSessionBelongsToTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Tenant id only. Never the tenant's connection config, which holds the
        // decrypted database password.
        $exceptions->context(fn () => ['tenant_id' => tenant()?->getTenantKey()]);

        $exceptions->render(fn (TenantCouldNotBeIdentifiedException $e) => response()->view(
            'errors.tenant-not-found', [], 404,
        ));

        $exceptions->render(fn (TenantIsNotReadyException $e) => response()->view(
            'errors.tenant-provisioning', ['provisioningStatus' => $e->provisioningStatus], 503,
        ));

        $exceptions->render(fn (TenantIsNotActiveException $e) => response()->view(
            'errors.tenant-inactive', ['status' => $e->status], 403,
        ));

        $exceptions->render(fn (SubscriptionNotActiveException $e) => response()->view(
            'errors.subscription-inactive', ['status' => $e->status], 402,
        ));

        $exceptions->render(fn (CrossTenantSessionException $e) => response()->view(
            'errors.cross-tenant-session', [], 403,
        ));

        $exceptions->render(fn (TenantDatabaseUnavailableException $e) => response()->view(
            'errors.tenant-database-unavailable', [], 503,
        ));

        // A driver-level failure inside tenancy would otherwise surface the tenant's
        // DSN through the debug page and any error reporter. Log the detail, show none.
        $exceptions->render(function (QueryException $e) {
            if (! tenancy()->initialized) {
                return null;
            }

            Log::critical('Tenant database unavailable.', [
                'tenant_id' => tenant()->getTenantKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->view('errors.tenant-database-unavailable', [], 503);
        });
    })->create();
