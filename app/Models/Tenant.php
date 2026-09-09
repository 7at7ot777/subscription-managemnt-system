<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProvisioningStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Tenancy\TenantDatabaseConfig;
use Carbon\CarbonImmutable;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    /** @use HasFactory<TenantFactory> */
    use HasDatabase, HasFactory;

    /**
     * Keeping the database password out of attributesToArray() is the single
     * load-bearing control that stops it reaching a Filament/Livewire form state,
     * an API response, or a serialised log line.
     */
    protected $hidden = ['tenancy_db_password', 'data'];

    /**
     * Every real column must be listed here. VirtualColumn silently serialises any
     * attribute NOT listed into the `data` JSON column, which would break unique
     * indexes, sorting and filtering on these fields.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'status',
            'provisioning_status',
            'subscription_start_at',
            'subscription_end_at',
            'tenancy_db_name',
            'tenancy_db_host',
            'tenancy_db_port',
            'tenancy_db_username',
            'tenancy_db_password',
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'provisioning_status' => ProvisioningStatus::class,
            'subscription_start_at' => 'immutable_datetime',
            'subscription_end_at' => 'immutable_datetime',
            'tenancy_db_password' => 'encrypted',
        ];
    }

    /**
     * Swap in the config class that filters NULL tenancy_db_* attributes, which would
     * otherwise overwrite the template connection's working credentials with null.
     */
    public function database(): TenantDatabaseConfig
    {
        return new TenantDatabaseConfig($this);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The single source of truth for subscription state. Nothing else in the codebase
     * should compare subscription dates.
     *
     * A null start date means "started forever ago"; a null end date means the
     * subscription is perpetual, NOT that it expired at the epoch.
     */
    public function subscriptionStatus(): SubscriptionStatus
    {
        $now = CarbonImmutable::now();

        if ($this->subscription_start_at !== null && $now->lessThan($this->subscription_start_at)) {
            return SubscriptionStatus::NotStarted;
        }

        if ($this->subscription_end_at !== null && $now->greaterThan($this->subscription_end_at)) {
            return SubscriptionStatus::Expired;
        }

        return SubscriptionStatus::Active;
    }

    public function isActive(): bool
    {
        return $this->status->allowsAccess() && $this->provisioning_status->isUsable();
    }

    /** A tenant can only be impersonated once its database actually exists. */
    public function isImpersonable(): bool
    {
        return $this->provisioning_status->isUsable();
    }
}
