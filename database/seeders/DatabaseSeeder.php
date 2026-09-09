<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the CENTRAL database only.
 *
 * Tenants are deliberately not seeded here: creating one provisions a real database
 * and runs migrations, which is not something a seeder should do implicitly. Use
 * `php artisan tenant:create` instead.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Development convenience only. Production should use `super-admin:create`,
        // which prompts for the password rather than using a known one.
        SuperAdmin::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'is_active' => true,
            ],
        );
    }
}
