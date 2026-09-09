<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class CreateTenantData
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,
        public ?CarbonImmutable $subscriptionStartAt = null,
        public ?CarbonImmutable $subscriptionEndAt = null,
    ) {}
}
