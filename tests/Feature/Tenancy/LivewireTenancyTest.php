<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\EnsureSessionBelongsToTenant;
use App\Http\Middleware\EnsureSubscriptionIsValid;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\InitializeTenancyBySlugPath;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Guards the highest-risk part of the whole integration.
 *
 * Livewire posts every AJAX interaction to one global /livewire/update route that
 * carries no tenant segment. If the tenancy middleware were not registered as
 * persistent, Livewire would replay the request without it and every table sort,
 * form validation and modal in the tenant panel would silently execute against the
 * CENTRAL database. Nothing would visibly break in a smoke test — which is exactly
 * why this needs an explicit assertion.
 */
class LivewireTenancyTest extends TestCase
{
    use CreatesTenants;

    #[Test]
    public function the_tenancy_middleware_is_registered_as_livewire_persistent_middleware(): void
    {
        $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();

        $this->assertContains(InitializeTenancyBySlugPath::class, $persistent);
        $this->assertContains(EnsureTenantIsActive::class, $persistent);
        $this->assertContains(EnsureSubscriptionIsValid::class, $persistent);
        $this->assertContains(EnsureSessionBelongsToTenant::class, $persistent);
    }

    #[Test]
    public function filament_assets_are_not_rewritten_to_the_tenant_asset_route(): void
    {
        // stancl's FilesystemTenancyBootstrapper repoints asset() at /tenancy/assets/*
        // unless asset_helper_tenancy is disabled. Filament builds every CSS and JS URL
        // with asset(), so leaving it enabled renders the panel with no styles or
        // scripts at all.
        $tenant = $this->provisionTenant('acme');

        $response = $this->get("/{$tenant->slug}/app/login");

        $response->assertOk()
            ->assertSee('/css/filament/filament/app.css', escape: false)
            ->assertDontSee('/tenancy/assets/', escape: false);
    }

    #[Test]
    public function generated_panel_urls_never_contain_an_unsubstituted_tenant_placeholder(): void
    {
        // Panel::getUrl() concatenates the raw path rather than calling route(), so if
        // the panel root were occupied the home route would be missing and every
        // post-login redirect would contain a literal "{tenant}".
        $tenant = $this->provisionTenant('acme');

        $this->get("/{$tenant->slug}/app/login")
            ->assertOk()
            ->assertDontSee('{tenant}', escape: false)
            ->assertDontSee('%7Btenant%7D', escape: false);
    }

    #[Test]
    public function the_panel_home_route_exists_so_redirects_resolve(): void
    {
        $this->assertTrue(\Route::has('filament.tenant.home'));
    }
}
