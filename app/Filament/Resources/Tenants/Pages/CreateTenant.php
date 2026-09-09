<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Pages;

use App\Actions\CreateTenantAction;
use App\Data\CreateTenantData;
use App\Filament\Resources\Tenants\TenantResource;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Creating a tenant provisions a database and an initial administrator, so the
     * create form asks for that administrator on top of the shared tenant fields.
     */
    public function form(Schema $schema): Schema
    {
        $schema = TenantResource::form($schema);

        return $schema->components([
            ...$schema->getComponents(),
            Section::make('Initial administrator')
                ->description('Created inside the new tenant database once it has been migrated.')
                ->columns(2)
                ->schema([
                    TextInput::make('admin_name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('admin_email')
                        ->label('Email address')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    TextInput::make('admin_password')
                        ->label('Password')
                        ->password()
                        ->revealable(false)
                        ->required()
                        ->rule(Password::default()),
                ]),
        ]);
    }

    /**
     * Delegates to the provisioning action rather than a plain create, so database
     * creation, tenant migrations, the initial admin and the provisioning-state
     * transitions all happen under one failure policy.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateTenantAction::class)->handle(new CreateTenantData(
            name: $data['name'],
            slug: $data['slug'],
            adminName: $data['admin_name'],
            adminEmail: $data['admin_email'],
            adminPassword: $data['admin_password'],
            subscriptionStartAt: filled($data['subscription_start_at'] ?? null)
                ? CarbonImmutable::parse($data['subscription_start_at'])
                : null,
            subscriptionEndAt: filled($data['subscription_end_at'] ?? null)
                ? CarbonImmutable::parse($data['subscription_end_at'])
                : null,
        ));
    }
}
