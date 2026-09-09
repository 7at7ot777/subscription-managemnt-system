<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Belt and braces on top of the model's $hidden.
     *
     * EditRecord fills the form from $record->attributesToArray(), and Livewire
     * serialises the resulting public $data into the page's wire:snapshot. If the
     * password ever stopped being hidden on the model, it would be published in the
     * page source; stripping it here means that regression still cannot leak it.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['tenancy_db_password']);

        return $data;
    }
}
