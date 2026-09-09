<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ProvisioningStatus;
use RuntimeException;

class TenantIsNotReadyException extends RuntimeException
{
    public function __construct(public readonly ProvisioningStatus $provisioningStatus)
    {
        parent::__construct('The tenant has not finished provisioning.');
    }
}
