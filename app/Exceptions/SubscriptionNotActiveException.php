<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\SubscriptionStatus;
use RuntimeException;

class SubscriptionNotActiveException extends RuntimeException
{
    public function __construct(public readonly SubscriptionStatus $status)
    {
        parent::__construct('The tenant subscription is not active.');
    }
}
