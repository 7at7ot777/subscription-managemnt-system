<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProvisioningStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 *
 * Note: creating a Tenant fires stancl's TenantCreated pipeline, which really does
 * CREATE DATABASE and run the tenant migrations. Tests that only need a central row
 * should be aware they are provisioning a real database, and must drop it afterwards
 * (see Tests\Concerns\CreatesTenants).
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'status' => TenantStatus::Active,
            'provisioning_status' => ProvisioningStatus::Ready,
            'subscription_start_at' => now()->subDay(),
            'subscription_end_at' => now()->addYear(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Inactive]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }

    public function provisioningFailed(): static
    {
        return $this->state(fn () => [
            'status' => TenantStatus::Inactive,
            'provisioning_status' => ProvisioningStatus::Failed,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'subscription_start_at' => now()->subYear(),
            'subscription_end_at' => now()->subDay(),
        ]);
    }

    public function notStarted(): static
    {
        return $this->state(fn () => [
            'subscription_start_at' => now()->addWeek(),
            'subscription_end_at' => now()->addYear(),
        ]);
    }

    /** A subscription with no end date never expires. */
    public function perpetual(): static
    {
        return $this->state(fn () => [
            'subscription_start_at' => now()->subDay(),
            'subscription_end_at' => null,
        ]);
    }
}
