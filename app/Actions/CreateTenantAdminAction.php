<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use LogicException;

final class CreateTenantAdminAction
{
    /**
     * Must run inside tenancy: creating the "tenant admin" in the central database
     * would be a silent catastrophe, so this fails loudly rather than guessing.
     */
    public function __invoke(string $name, string $email, string $password): User
    {
        if (! tenancy()->initialized) {
            throw new LogicException('The initial tenant admin must be created inside a tenant context.');
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            // The 'password' => 'hashed' cast does the hashing. Hashing here as well
            // would double-hash and make the credentials unusable.
            'password' => $password,
        ]);

        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
