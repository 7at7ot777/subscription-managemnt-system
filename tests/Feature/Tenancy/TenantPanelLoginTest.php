<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Drives Filament's real login component inside a tenant context, so the whole
 * chain is exercised: tenancy initialisation, the `web` guard resolving against the
 * tenant database, and the FilamentUser panel check.
 */
class TenantPanelLoginTest extends TestCase
{
    use CreatesTenants;

    #[Test]
    public function a_tenant_user_can_sign_in_to_their_own_panel(): void
    {
        $tenant = $this->provisionTenant('acme', password: 'AcmeSecret!23');

        tenancy()->initialize($tenant);
        Filament::setCurrentPanel('tenant');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@acme.test',
                'password' => 'AcmeSecret!23',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertTrue(Auth::guard('web')->check());
        $this->assertSame('admin@acme.test', Auth::guard('web')->user()->email);
    }

    #[Test]
    public function credentials_from_one_tenant_are_rejected_by_another(): void
    {
        // The same person, the same password, a different workspace: the credentials
        // simply do not exist in the other tenant's database.
        $this->provisionTenant('acme', password: 'AcmeSecret!23');
        $globex = $this->provisionTenant('globex', password: 'GlobexSecret!23');

        tenancy()->initialize($globex);
        Filament::setCurrentPanel('tenant');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@acme.test',
                'password' => 'AcmeSecret!23',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function a_wrong_password_is_rejected_within_the_correct_tenant(): void
    {
        $tenant = $this->provisionTenant('acme', password: 'AcmeSecret!23');

        tenancy()->initialize($tenant);
        Filament::setCurrentPanel('tenant');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@acme.test',
                'password' => 'not-the-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function the_signed_in_user_comes_from_the_tenant_database(): void
    {
        $tenant = $this->provisionTenant('acme', password: 'AcmeSecret!23');

        tenancy()->initialize($tenant);
        Filament::setCurrentPanel('tenant');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@acme.test', 'password' => 'AcmeSecret!23'])
            ->call('authenticate');

        /** @var User $user */
        $user = Auth::guard('web')->user();

        $this->assertSame(
            $tenant->database()->getName(),
            $user->getConnection()->getDatabaseName(),
        );
    }
}
