<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class CrossTenantSessionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This session belongs to a different tenant.');
    }
}
