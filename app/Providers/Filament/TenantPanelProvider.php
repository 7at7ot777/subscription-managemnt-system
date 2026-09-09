<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Tenant\Pages\Dashboard;
use App\Http\Middleware\EnsureSessionBelongsToTenant;
use App\Http\Middleware\EnsureSubscriptionIsValid;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\InitializeTenancyBySlugPath;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The per-tenant panel, served at /{tenant}/app.
 *
 * The `isPersistent: true` argument is the single most important line here. Livewire
 * posts to one global /livewire/update route that carries no tenant segment, so without
 * it tenancy would not be initialised for any AJAX request and every table sort, form
 * validation and modal would silently run against the central database.
 *
 * With it, Filament forwards this stack to Livewire::addPersistentMiddleware(). Livewire
 * memoises the originating path into the component snapshot, rebuilds that route on the
 * update request (binding {tenant}) and replays this middleware — so tenancy is live
 * before Authenticate resolves the user.
 *
 * This is also why the tenant gate middleware throws instead of redirecting: Livewire
 * aborts when persistent middleware returns a RedirectResponse.
 */
class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenant')
            ->path('{tenant}/app')
            ->login()
            ->authGuard('web')
            ->colors(['primary' => Color::Emerald])
            ->databaseTransactions()
            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\Filament\Tenant\Resources')
            ->discoverPages(in: app_path('Filament/Tenant/Pages'), for: 'App\Filament\Tenant\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Tenant/Widgets'), for: 'App\Filament\Tenant\Widgets')
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View|string => session()->has('impersonation')
                    ? view('filament.tenant.impersonation-banner')
                    : '',
            )
            ->middleware([
                // Tenant context first, before anything touches the database.
                InitializeTenancyBySlugPath::class,
                EnsureTenantIsActive::class,
                EnsureSubscriptionIsValid::class,

                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,

                // Needs a live session, so it runs after StartSession.
                EnsureSessionBelongsToTenant::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
