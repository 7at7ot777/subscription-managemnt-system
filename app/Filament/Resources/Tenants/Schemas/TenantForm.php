<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Schemas;

use App\Enums\ProvisioningStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Rules\TenantSlugRules;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create'
                            ? $set('slug', Str::slug((string) $state))
                            : null),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(63)
                        ->live(onBlur: true)
                        // The slug is part of the tenant's public URL contract, and the
                        // tenant database is named from the immutable id, so renaming is
                        // an operation in its own right rather than a form edit.
                        ->disabledOn('edit')
                        ->rules(fn (?Tenant $record) => TenantSlugRules::make($record?->getKey()))
                        ->helperText(fn (Get $get): string => rtrim((string) config('app.url'), '/')
                            .'/'.($get('slug') ?: '{slug}').'/app'),
                ]),

            Section::make('Database configuration')
                ->description('Leave blank to inherit the central connection. Column names map to stancl internals and must keep the tenancy_db_ prefix.')
                ->columns(2)
                ->schema([
                    TextInput::make('tenancy_db_name')
                        ->label('Database name')
                        ->maxLength(64)
                        ->helperText('Defaults to the configured prefix plus the tenant id.'),

                    TextInput::make('tenancy_db_host')->label('Host')->maxLength(255),
                    TextInput::make('tenancy_db_port')->label('Port')->numeric(),
                    TextInput::make('tenancy_db_username')->label('Username')->maxLength(255),

                    // SECURITY: never pre-filled and never dehydrated when blank.
                    // The model hides this attribute, so it is absent from the record
                    // data Filament fills the form with, and therefore absent from the
                    // Livewire snapshot that is serialised into the page HTML.
                    TextInput::make('tenancy_db_password')
                        ->label('Password')
                        ->password()
                        ->revealable(false)
                        ->autocomplete('new-password')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('Leave blank to keep the current password.'),
                ]),

            Section::make('Subscription')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('subscription_start_at')
                        ->seconds(false)
                        ->helperText('Leave blank to start immediately.'),

                    DateTimePicker::make('subscription_end_at')
                        ->seconds(false)
                        ->after('subscription_start_at')
                        ->helperText('Leave blank for a perpetual subscription.'),
                ]),

            Section::make('Status')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->options(TenantStatus::class)
                        ->default(TenantStatus::Inactive)
                        ->required()
                        ->native(false),

                    Select::make('provisioning_status')
                        ->options(ProvisioningStatus::class)
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit')
                        ->helperText('Managed by the provisioning process.'),
                ]),
        ]);
    }
}
