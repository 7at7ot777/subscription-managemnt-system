<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProvisioningStatus;
use App\Enums\TenantStatus;
use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();

        // Livewire::test bypasses the panel:{id} route middleware, so the current panel
        // has to be set explicitly or Filament falls back to the default one.
        Filament::setCurrentPanel('admin');
    }

    #[Test]
    public function the_admin_panel_requires_a_super_admin(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    #[Test]
    public function an_inactive_super_admin_cannot_access_the_panel(): void
    {
        $admin = SuperAdmin::factory()->inactive()->create();

        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    #[Test]
    public function a_super_admin_can_list_tenants(): void
    {
        $this->actingAsSuperAdmin();
        $tenants = collect(['acme', 'globex'])->map(fn (string $slug) => $this->provisionTenant($slug));

        Livewire::test(ListTenants::class)
            ->assertCanSeeTableRecords($tenants)
            ->assertCanRenderTableColumn('slug')
            ->assertCanRenderTableColumn('status')
            ->assertCanRenderTableColumn('subscription_status');
    }

    #[Test]
    public function the_tenants_table_never_exposes_the_database_password(): void
    {
        $this->actingAsSuperAdmin();

        $tenant = $this->provisionTenant('acme');
        $tenant->forceFill(['tenancy_db_password' => 'leaky-plaintext-value'])->save();

        Livewire::test(ListTenants::class)
            ->assertDontSee('leaky-plaintext-value', escape: false);
    }

    #[Test]
    public function the_edit_form_never_ships_the_database_password_to_the_browser(): void
    {
        // Filament fills the form from $record->attributesToArray(), and Livewire
        // serialises the resulting public $data into the page's wire:snapshot. This is
        // the assertion that catches a regression in the model's $hidden.
        $this->actingAsSuperAdmin();

        $tenant = $this->provisionTenant('acme');
        $tenant->forceFill(['tenancy_db_password' => 'leaky-plaintext-value'])->save();

        // Filament resolves the record by its route key, which is the slug.
        Livewire::test(EditTenant::class, ['record' => $tenant->slug])
            ->assertDontSee('leaky-plaintext-value', escape: false)
            ->assertSet('data.tenancy_db_password', null);
    }

    #[Test]
    public function the_password_is_encrypted_at_rest(): void
    {
        $tenant = $this->provisionTenant('acme');
        $tenant->forceFill(['tenancy_db_password' => 'plaintext-secret'])->save();

        $raw = \DB::connection(config('tenancy.database.central_connection'))
            ->table('tenants')
            ->where('id', $tenant->getTenantKey())
            ->value('tenancy_db_password');

        $this->assertNotSame('plaintext-secret', $raw);
        $this->assertSame('plaintext-secret', $tenant->fresh()->tenancy_db_password);
    }

    #[Test]
    public function the_password_is_absent_from_the_serialised_model(): void
    {
        $tenant = $this->provisionTenant('acme');
        $tenant->forceFill(['tenancy_db_password' => 'plaintext-secret'])->save();

        $this->assertArrayNotHasKey('tenancy_db_password', $tenant->fresh()->toArray());
        $this->assertStringNotContainsString('plaintext-secret', $tenant->fresh()->toJson());
    }

    #[Test]
    public function a_super_admin_can_deactivate_and_reactivate_a_tenant(): void
    {
        $this->actingAsSuperAdmin();
        $tenant = $this->provisionTenant('acme');

        $this->assertSame(TenantStatus::Active, $tenant->status);

        $tenant->update(['status' => TenantStatus::Suspended]);
        $this->get("/{$tenant->slug}/app/login")->assertForbidden();

        $tenant->update(['status' => TenantStatus::Active]);
        $this->get("/{$tenant->slug}/app/login")->assertOk();
    }

    #[Test]
    public function provisioning_creates_the_database_migrations_and_first_administrator(): void
    {
        $tenant = $this->provisionTenant('acme');

        $this->assertSame(ProvisioningStatus::Ready, $tenant->provisioning_status);
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertTrue($tenant->database()->manager()->databaseExists($tenant->database()->getName()));

        $tenant->run(function () {
            $this->assertTrue(\Schema::hasTable('users'));
            $this->assertDatabaseHas('users', ['email' => 'admin@acme.test']);
        });
    }

    #[Test]
    public function the_tenant_database_is_named_after_the_slug(): void
    {
        // Readable names matter operationally: they are what shows up in
        // SHOW DATABASES, backups, slow-query logs and monitoring.
        $tenant = $this->provisionTenant('acme');

        $expected = config('tenancy.database.prefix').'acme'.config('tenancy.database.suffix');

        $this->assertSame($expected, $tenant->database()->getName());
        $this->assertSame($expected, $tenant->tenancy_db_name);
    }

    #[Test]
    public function the_database_name_stays_within_the_mysql_identifier_limit(): void
    {
        // The name is prefix + slug, and MySQL caps identifiers at 64 characters, so
        // the slug length rule has to leave room for the longest prefix.
        $longest = config('tenancy.database.prefix')
            .str_repeat('a', (int) config('tenancy.slug.max'))
            .config('tenancy.database.suffix');

        $this->assertLessThanOrEqual(64, strlen($longest));
    }

    #[Test]
    public function a_failed_provisioning_never_leaves_the_tenant_active(): void
    {
        // The tenant row is deliberately kept so the operator can see what failed,
        // but it must not be usable.
        $tenant = Tenant::factory()->provisioningFailed()->make();

        $this->assertFalse($tenant->isActive());
        $this->assertFalse($tenant->provisioning_status->isUsable());
    }
}
