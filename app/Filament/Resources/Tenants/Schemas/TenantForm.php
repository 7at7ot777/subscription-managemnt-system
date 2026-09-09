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
                        ->maxLength((int) config('tenancy.slug.max', 50))
                        ->live(onBlur: true)
                        // The slug is the tenant's public URL and the source of its
                        // database name, so renaming it is an operation in its own right
                        // rather than a form edit.
                        ->disabledOn('edit')
                        ->rules(fn (?Tenant $record) => TenantSlugRules::make($record?->getKey()))
                        ->helperText(fn (Get $get): string => rtrim((string) config('app.url'), '/')
                            .'/'.($get('slug') ?: '{slug}').'/app'),

                    // Shown for debugging only, never written.
                    //
                    // There are no host/port/username/password fields: every tenant lives
                    // on the same server as the central database, so those would always
                    // duplicate the central connection. The columns still exist (and are
                    // still encrypted and hidden) as an escape hatch for moving a tenant
                    // to its own server later, but exposing them in the UI bought nothing
                    // and put the decrypted password into Livewire's page payload.
                    TextInput::make('tenancy_db_name')
                        ->label('Database')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder(fn (Get $get): string => config('tenancy.database.prefix')
                            .($get('slug') ?: '{slug}')
                            .config('tenancy.database.suffix'))
                        ->helperText('Created from the slug when the tenant is provisioned.'),
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
