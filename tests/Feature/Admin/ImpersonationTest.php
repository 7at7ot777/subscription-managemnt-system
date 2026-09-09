<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Stancl\Tenancy\Features\UserImpersonation;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use CreatesTenants;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function mintToken($tenant, ?string $userId = null): ImpersonationToken
    {
        $userId ??= (string) $tenant->run(fn () => User::query()->firstOrFail()->getKey());

        return tenancy()->impersonate(
            $tenant,
            $userId,
            "/{$tenant->slug}/app/dashboard",
            'web',
        );
    }

    #[Test]
    public function a_token_signs_the_super_admin_in_as_the_tenant_user(): void
    {
        $tenant = $this->provisionTenant('acme');
        $token = $this->mintToken($tenant);

        $this->get("/{$tenant->slug}/impersonate/{$token->token}")
            ->assertRedirect("/{$tenant->slug}/app/dashboard");

        $this->assertTrue(Auth::guard('web')->check());
    }

    #[Test]
    public function no_tenant_password_is_read_or_modified(): void
    {
        $tenant = $this->provisionTenant('acme');
        $before = $tenant->run(fn (): string => User::query()->firstOrFail()->password);

        $token = $this->mintToken($tenant);
        $this->get("/{$tenant->slug}/impersonate/{$token->token}");

        $after = $tenant->run(fn (): string => User::query()->firstOrFail()->password);

        $this->assertSame($before, $after);
        // The token record itself never stores a credential.
        $this->assertArrayNotHasKey('password', $token->getAttributes());
    }

    #[Test]
    public function a_token_is_single_use(): void
    {
        $tenant = $this->provisionTenant('acme');
        $token = $this->mintToken($tenant);

        $this->get("/{$tenant->slug}/impersonate/{$token->token}")->assertRedirect();

        // Pinned to the central connection: the request leaves tenancy initialised,
        // so the default connection is the tenant's, but tokens are stored centrally.
        $this->assertDatabaseMissing(
            'tenant_user_impersonation_tokens',
            ['token' => $token->token],
            config('tenancy.database.central_connection'),
        );

        $this->get("/{$tenant->slug}/impersonate/{$token->token}")->assertNotFound();
    }

    #[Test]
    public function a_token_minted_for_one_tenant_is_rejected_by_another(): void
    {
        $acme = $this->provisionTenant('acme');
        $globex = $this->provisionTenant('globex');

        $token = $this->mintToken($acme);

        $this->get("/{$globex->slug}/impersonate/{$token->token}")->assertForbidden();
        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function a_token_expires(): void
    {
        $tenant = $this->provisionTenant('acme');
        $token = $this->mintToken($tenant);

        Carbon::setTestNow(now()->addSeconds(UserImpersonation::$ttl + 5));

        $this->get("/{$tenant->slug}/impersonate/{$token->token}")->assertForbidden();
        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function impersonation_is_permitted_into_a_suspended_or_expired_tenant(): void
    {
        // A super admin must be able to enter a broken tenant precisely in order to
        // investigate why it is broken.
        $tenant = $this->provisionTenant('acme', [
            'subscription_end_at' => now()->subDay(),
        ]);

        $token = $this->mintToken($tenant);

        $this->get("/{$tenant->slug}/impersonate/{$token->token}")->assertRedirect();
        $this->assertTrue(Auth::guard('web')->check());
    }

    #[Test]
    public function leaving_impersonation_ends_the_tenant_session_and_closes_the_audit_entry(): void
    {
        $tenant = $this->provisionTenant('acme');
        $tenantUser = $tenant->run(fn (): User => User::query()->firstOrFail());

        $log = ImpersonationLog::create([
            'super_admin_id' => null,
            'tenant_id' => $tenant->getTenantKey(),
            'tenant_user_id' => (string) $tenantUser->getKey(),
            'tenant_user_email' => $tenantUser->email,
            'ip_address' => '127.0.0.1',
            'started_at' => now(),
        ]);

        $token = $this->mintToken($tenant, (string) $tenantUser->getKey());

        $this->get("/{$tenant->slug}/impersonate/{$token->token}?log={$log->getKey()}")
            ->assertRedirect();

        $this->post("/{$tenant->slug}/impersonate/leave")->assertRedirect('/admin');

        $this->assertFalse(Auth::guard('web')->check());
        $this->assertNotNull($log->refresh()->ended_at);
    }

    #[Test]
    public function there_is_no_tenant_facing_route_that_mints_a_token(): void
    {
        // Minting happens only in the central admin panel behind the super_admin guard.
        $tenant = $this->provisionTenant('acme');

        foreach (['impersonate', 'impersonate/create', 'impersonate/token'] as $path) {
            $response = $this->get("/{$tenant->slug}/{$path}");

            $this->assertNotSame(200, $response->getStatusCode());
        }

        $this->assertDatabaseCount(
            'tenant_user_impersonation_tokens',
            0,
            config('tenancy.database.central_connection'),
        );
    }
}
