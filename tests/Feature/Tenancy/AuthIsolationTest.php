<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\ScopeSessions;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The regression suite for cross-tenant authentication.
 *
 * Path-based tenancy puts every tenant on one origin, so all tenants share a cookie
 * jar and a session store. These tests exist to prove that sharing cannot be turned
 * into an account takeover.
 */
class AuthIsolationTest extends TestCase
{
    use CreatesTenants;

    private function guardSessionKey(): string
    {
        return 'login_web_'.sha1(SessionGuard::class);
    }

    #[Test]
    public function credentials_are_verified_against_the_tenants_own_database(): void
    {
        $acme = $this->provisionTenant('acme', password: 'AcmeSecret!23');
        $globex = $this->provisionTenant('globex', password: 'GlobexSecret!23');

        $acme->run(function () {
            $user = User::where('email', 'admin@acme.test')->firstOrFail();
            $this->assertTrue(Hash::check('AcmeSecret!23', $user->password));
        });

        // The same address does not exist in the other tenant at all.
        $globex->run(function () {
            $this->assertNull(User::where('email', 'admin@acme.test')->first());
        });
    }

    #[Test]
    public function a_session_from_one_tenant_cannot_authenticate_against_another(): void
    {
        // THE attack this architecture has to defeat: a user logs in at /acme, then
        // simply edits the URL to /globex. The session still says "user 1 is logged in",
        // and the database connection has already swapped — so without the tenant
        // binding they would land as Globex's user #1, typically its owner.
        $acme = $this->provisionTenant('acme');
        $globex = $this->provisionTenant('globex');

        $acmeUserId = $acme->run(fn () => User::query()->firstOrFail()->getKey());

        $response = $this
            ->withSession([
                $this->guardSessionKey() => $acmeUserId,
                ScopeSessions::$tenantIdKey => $acme->getTenantKey(),
            ])
            ->get("/{$globex->slug}/app/dashboard");

        $response->assertForbidden()->assertSee('Please sign in again');
        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function the_stale_session_is_destroyed_rather_than_merely_rejected(): void
    {
        $acme = $this->provisionTenant('acme');
        $globex = $this->provisionTenant('globex');

        $acmeUserId = $acme->run(fn () => User::query()->firstOrFail()->getKey());

        $this->withSession([
            $this->guardSessionKey() => $acmeUserId,
            ScopeSessions::$tenantIdKey => $acme->getTenantKey(),
        ])->get("/{$globex->slug}/app/dashboard");

        // The credential must be gone, not just ignored for this one request.
        $this->assertNull(session($this->guardSessionKey()));
    }

    #[Test]
    public function a_fresh_session_is_bound_to_the_tenant_that_created_it(): void
    {
        $acme = $this->provisionTenant('acme');

        $this->get("/{$acme->slug}/app/login")->assertOk();

        $this->assertSame(
            $acme->getTenantKey(),
            session(ScopeSessions::$tenantIdKey),
        );
    }

    #[Test]
    public function an_authenticated_tenant_user_cannot_reach_the_central_admin_panel(): void
    {
        $acme = $this->provisionTenant('acme');
        $user = $acme->run(fn (): User => User::query()->firstOrFail());

        // Different guard, different provider, different database.
        $this->actingAs($user, 'web')
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }

    #[Test]
    public function a_tenant_user_is_rejected_by_the_admin_panel_contract(): void
    {
        $acme = $this->provisionTenant('acme');
        $user = $acme->run(fn (): User => User::query()->firstOrFail());

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('tenant')));
    }
}
