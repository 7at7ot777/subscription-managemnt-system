<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Actions\CreateTenantAction;
use App\Data\CreateTenantData;
use App\Models\Tenant;
use Throwable;

/**
 * Creates real tenant databases and guarantees they are dropped again.
 *
 * Every tenant created through here issues a genuine CREATE DATABASE plus a migration
 * run, so tests should create only as many tenants as they actually need (most need two).
 */
trait CreatesTenants
{
    /** @var array<int, Tenant> */
    protected array $createdTenants = [];

    /**
     * Provisions a tenant the same way the application does: database, migrations and
     * an initial administrator.
     */
    protected function provisionTenant(string $slug, array $attributes = [], string $password = 'Sup3rSecret!23'): Tenant
    {
        $tenant = app(CreateTenantAction::class)->handle(new CreateTenantData(
            name: ucfirst($slug),
            slug: $slug,
            adminName: ucfirst($slug).' Admin',
            adminEmail: "admin@{$slug}.test",
            adminPassword: $password,
        ));

        if ($attributes !== []) {
            $tenant->forceFill($attributes)->save();
        }

        $this->createdTenants[] = $tenant;

        // Cleanup is registered here rather than in a tearDown() on this trait: a
        // tearDown() defined by the test class itself silently wins over a trait's,
        // which previously left orphaned tenant databases behind on every test class
        // that needed its own teardown. beforeApplicationDestroyed callbacks always run.
        $this->beforeApplicationDestroyed(fn () => $this->dropCreatedTenantDatabases());

        return $tenant->refresh();
    }

    /**
     * A tenant row WITHOUT a database, for tests that only exercise central-side
     * behaviour and never enter the tenant context.
     */
    protected function tenantRecordOnly(array $attributes = []): Tenant
    {
        $tenant = Tenant::factory()->make($attributes);

        // Bypass the TenantCreated pipeline so no database is provisioned.
        Tenant::unsetEventDispatcher();
        $tenant->save();
        Tenant::setEventDispatcher(app('events'));

        return $tenant;
    }

    protected function dropCreatedTenantDatabases(): void
    {
        foreach ($this->createdTenants as $tenant) {
            try {
                $manager = $tenant->database()->manager();
                $name = $tenant->database()->getName();

                if ($name !== null && $manager->databaseExists($name)) {
                    $manager->deleteDatabase($tenant);
                }
            } catch (Throwable) {
                // Never mask the real test failure with a cleanup failure.
            }
        }

        $this->createdTenants = [];
    }
}
