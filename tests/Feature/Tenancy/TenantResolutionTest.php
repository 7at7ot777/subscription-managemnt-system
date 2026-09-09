<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\ProvisioningStatus;
use App\Enums\TenantStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use CreatesTenants;

    #[Test]
    public function a_valid_slug_resolves_the_tenant_and_switches_the_database(): void
    {
        $tenant = $this->provisionTenant('acme');

        $this->get("/{$tenant->slug}/app/login")->assertOk();

        $tenant->run(function () use ($tenant) {
            $this->assertSame(
                $tenant->database()->getName(),
                \DB::connection()->getDatabaseName(),
            );
        });
    }

    #[Test]
    public function an_unknown_slug_returns_a_not_found_page(): void
    {
        $this->get('/no-such-tenant/app/login')
            ->assertNotFound()
            ->assertSee('Workspace not found');
    }

    #[Test]
    public function a_raw_tenant_id_in_the_url_does_not_resolve(): void
    {
        // Resolution is by slug only. If the primary key also worked, tenant UUIDs
        // would become a second, unintended addressing scheme.
        $tenant = $this->provisionTenant('acme');

        $this->get("/{$tenant->getTenantKey()}/app/login")->assertNotFound();
    }

    #[Test]
    public function an_inactive_tenant_is_blocked(): void
    {
        $tenant = $this->provisionTenant('acme', ['status' => TenantStatus::Inactive]);

        $this->get("/{$tenant->slug}/app/login")
            ->assertForbidden()
            ->assertSee('Workspace unavailable');
    }

    #[Test]
    public function a_suspended_tenant_is_blocked(): void
    {
        $tenant = $this->provisionTenant('acme', ['status' => TenantStatus::Suspended]);

        $this->get("/{$tenant->slug}/app/login")->assertForbidden();
    }

    #[Test]
    public function a_tenant_that_has_not_finished_provisioning_is_blocked(): void
    {
        $tenant = $this->provisionTenant('acme', [
            'provisioning_status' => ProvisioningStatus::Failed,
        ]);

        $this->get("/{$tenant->slug}/app/login")
            ->assertStatus(503)
            ->assertSee('Workspace is being prepared');
    }

    #[Test]
    public function central_routes_are_not_swallowed_by_the_tenant_catch_all(): void
    {
        // The {tenant} prefix is a greedy single-segment catch-all at the root, so this
        // guards the route-registration ordering that keeps these reachable.
        $this->get('/up')->assertOk();
        $this->get('/admin/login')->assertOk();
    }

    #[Test]
    public function generated_urls_carry_the_tenant_segment_after_the_parameter_is_forgotten(): void
    {
        // The resolver calls forgetParameter('tenant'), so without the URL::defaults
        // listener every route() call inside the tenant context would throw.
        $tenant = $this->provisionTenant('acme');

        $tenant->run(function () use ($tenant) {
            $this->assertStringContainsString(
                "/{$tenant->slug}/subscription-expired",
                route('tenant.subscription.expired'),
            );
        });
    }
}
