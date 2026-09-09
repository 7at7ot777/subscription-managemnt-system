<?php

declare(strict_types=1);

namespace App\Tenancy;

use Stancl\Tenancy\DatabaseConfig;

class TenantDatabaseConfig extends DatabaseConfig
{
    /**
     * Stancl collects every `tenancy_db_*` key from the model's raw attributes and
     * merges them over the template connection. A real column that exists but is NULL
     * still appears in getAttributes(), so a null tenancy_db_username would clobber
     * the template's working username and the connection would fail. Strip nulls.
     */
    public function tenantConfig(): array
    {
        return array_filter(
            parent::tenantConfig(),
            static fn ($value): bool => $value !== null,
        );
    }
}
