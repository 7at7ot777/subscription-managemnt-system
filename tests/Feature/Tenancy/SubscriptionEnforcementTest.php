<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\SubscriptionStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SubscriptionEnforcementTest extends TestCase
{
    use CreatesTenants;

    #[Test]
    public function an_active_subscription_grants_access(): void
    {
        $tenant = $this->provisionTenant('acme', [
            'subscription_start_at' => now()->subMonth(),
            'subscription_end_at' => now()->addMonth(),
        ]);

        $this->assertSame(SubscriptionStatus::Active, $tenant->subscriptionStatus());
        $this->get("/{$tenant->slug}/app/login")->assertOk();
    }

    #[Test]
    public function an_expired_subscription_blocks_the_application(): void
    {
        $tenant = $this->provisionTenant('acme', [
            'subscription_start_at' => now()->subYear(),
            'subscription_end_at' => now()->subDay(),
        ]);

        $this->assertSame(SubscriptionStatus::Expired, $tenant->subscriptionStatus());

        $this->get("/{$tenant->slug}/app/dashboard")
            ->assertStatus(402)
            ->assertSee('Subscription expired');
    }

    #[Test]
    public function a_subscription_that_has_not_started_blocks_the_application(): void
    {
        $tenant = $this->provisionTenant('acme', [
            'subscription_start_at' => now()->addWeek(),
            'subscription_end_at' => now()->addYear(),
        ]);

        $this->assertSame(SubscriptionStatus::NotStarted, $tenant->subscriptionStatus());

        $this->get("/{$tenant->slug}/app/dashboard")
            ->assertStatus(402)
            ->assertSee('Subscription has not started');
    }

    #[Test]
    public function a_subscription_without_an_end_date_never_expires(): void
    {
        $tenant = $this->provisionTenant('acme', [
            'subscription_start_at' => now()->subYear(),
            'subscription_end_at' => null,
        ]);

        $this->assertSame(SubscriptionStatus::Active, $tenant->subscriptionStatus());
        $this->get("/{$tenant->slug}/app/login")->assertOk();
    }

    #[Test]
    public function the_expired_page_itself_stays_reachable_while_expired(): void
    {
        // Otherwise the middleware that explains the problem is blocked by itself,
        // and the user gets an infinite redirect instead of an explanation.
        $tenant = $this->provisionTenant('acme', [
            'subscription_end_at' => now()->subDay(),
        ]);

        $this->get("/{$tenant->slug}/subscription-expired")
            ->assertOk()
            ->assertSee('Subscription expired');
    }

    #[Test]
    public function login_stays_reachable_while_expired_so_users_can_see_the_account_state(): void
    {
        $tenant = $this->provisionTenant('acme', [
            'subscription_end_at' => now()->subDay(),
        ]);

        $this->get("/{$tenant->slug}/app/login")->assertOk();
    }

    #[Test]
    public function enforcement_is_server_side_and_survives_a_direct_request(): void
    {
        $tenant = $this->provisionTenant('acme', [
            'subscription_end_at' => now()->subDay(),
        ]);

        // No amount of client-side manipulation helps: the gate is middleware.
        $this->get("/{$tenant->slug}/app/users")->assertStatus(402);
    }
}
