<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A TENANT user. This model deliberately declares no connection: stancl swaps the
 * default connection to the tenant's database, so every query here is automatically
 * scoped to the current tenant. There is no central `users` table, so if tenancy is
 * ever not initialised these queries fail loudly rather than hitting the wrong data.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Without implementing FilamentUser, Filament only enforces panel access outside
     * the `local` environment — meaning a tenant user would silently gain access to
     * the central super-admin panel during local development.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'tenant';
    }
}
