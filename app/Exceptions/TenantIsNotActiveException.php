<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TenantStatus;
use RuntimeException;

class TenantIsNotActiveException extends RuntimeException
{
    public function __construct(public readonly TenantStatus $status)
    {
        parent::__construct('The tenant is not active.');
    }
}
