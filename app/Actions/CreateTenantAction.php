<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\CreateTenantData;
use App\Enums\ProvisioningStatus;
use App\Enums\TenantStatus;
use App\Exceptions\TenantProvisioningFailedException;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Throwable;

/**
 * Provisions a tenant end to end.
 *
 * Deliberately NOT wrapped in a database transaction: MySQL performs an implicit
 * commit on DDL, and stancl's TenantCreated pipeline issues CREATE DATABASE. A
 * surrounding transaction would be silently committed mid-flight, so the rollback
 * would be a no-op while appearing to guarantee atomicity.
 *
 * On failure the tenant row is KEPT and marked Failed rather than deleted, because
 * $tenant->delete() fires DeleteDatabase, which throws when the database was never
 * created — the most common failure mode. Keeping the row also leaves evidence for
 * the operator and surfaces a Retry action in the admin panel.
 */
final class CreateTenantAction
{
    public function __construct(private readonly CreateTenantAdminAction $createAdmin) {}

    public function handle(CreateTenantData $data): Tenant
    {
        $tenant = new Tenant([
            'name' => $data->name,
            'slug' => $data->slug,
            'subscription_start_at' => $data->subscriptionStartAt,
            // An end date is an instant, not a day: normalise here so "expires on the
            // 31st" means end of that day rather than midnight at its start.
            'subscription_end_at' => $data->subscriptionEndAt?->endOfDay(),
        ]);

        $tenant->status = TenantStatus::Inactive;
        $tenant->provisioning_status = ProvisioningStatus::Provisioning;

        try {
            // Fires TenantCreated -> JobPipeline(CreateDatabase, MigrateDatabase).
            $tenant->save();

            // tenants:migrate ends tenancy when it finishes, so re-enter explicitly
            // rather than assuming we are still in the tenant's context.
            $tenant->run(fn () => ($this->createAdmin)(
                $data->adminName,
                $data->adminEmail,
                $data->adminPassword,
            ));

            $tenant->forceFill([
                'provisioning_status' => ProvisioningStatus::Ready,
                'status' => TenantStatus::Active,
            ])->save();

            return $tenant->refresh();
        } catch (Throwable $e) {
            $this->markFailed($tenant, $e);

            throw new TenantProvisioningFailedException($tenant, $e);
        }
    }

    private function markFailed(Tenant $tenant, Throwable $e): void
    {
        report($e);

        if (! $tenant->exists) {
            return;
        }

        $tenant->forceFill([
            'provisioning_status' => ProvisioningStatus::Failed,
            'status' => TenantStatus::Inactive,
            // Message only. The trace can hold the connection array, including the
            // decrypted database password.
            'provisioning_error' => Str::limit($e->getMessage(), 500),
        ])->save();
    }
}
