<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Tenant;
use RuntimeException;
use Throwable;

class TenantProvisioningFailedException extends RuntimeException
{
    public function __construct(public readonly Tenant $tenant, ?Throwable $previous = null)
    {
        parent::__construct(
            "Provisioning failed for tenant [{$tenant->slug}].",
            previous: $previous,
        );
    }
}
