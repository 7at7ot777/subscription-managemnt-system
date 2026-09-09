<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Email address')
                ->email()
                ->required()
                ->maxLength(255)
                // Unique within this tenant's database only, which is exactly the
                // desired scope: the same address may exist in another tenant.
                ->unique(ignoreRecord: true),

            TextInput::make('password')
                ->password()
                ->revealable(false)
                ->autocomplete('new-password')
                ->rule(Password::default())
                ->required(fn (string $operation): bool => $operation === 'create')
                // Only written when actually filled, so editing a user without
                // touching this field keeps their existing password.
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(fn (string $operation): ?string => $operation === 'edit'
                    ? 'Leave blank to keep the current password.'
                    : null),
        ]);
    }
}
