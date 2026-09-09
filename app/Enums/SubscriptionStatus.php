<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case NotStarted = 'not_started';
    case Active = 'active';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::Active => 'Active',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::NotStarted => 'warning',
            self::Expired => 'danger',
        };
    }

    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }
}
