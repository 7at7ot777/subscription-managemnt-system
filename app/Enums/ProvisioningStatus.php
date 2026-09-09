<?php

declare(strict_types=1);

namespace App\Enums;

enum ProvisioningStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Provisioning => 'Provisioning',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ready => 'success',
            self::Provisioning => 'warning',
            self::Pending => 'gray',
            self::Failed => 'danger',
        };
    }

    /** Only a fully provisioned tenant may serve requests. */
    public function isUsable(): bool
    {
        return $this === self::Ready;
    }
}
