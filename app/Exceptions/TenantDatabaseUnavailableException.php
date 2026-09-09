<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Wraps a driver-level failure so the tenant's connection details never reach a
 * rendered exception page or an error reporter.
 */
class TenantDatabaseUnavailableException extends RuntimeException
{
    public function __construct(public readonly ?string $tenantSlug = null, ?Throwable $previous = null)
    {
        parent::__construct('The tenant database is unavailable.', previous: $previous);
    }
}
