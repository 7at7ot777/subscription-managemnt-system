<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'super-admin:create
                            {--name= : The super admin display name}
                            {--email= : The super admin email address}
                            {--password= : The super admin password (prompted if omitted)}';

    protected $description = 'Create a central super admin who can manage tenants at /admin';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email address', required: true);
        // Prompting keeps the password out of shell history and CI logs.
        $plain = $this->option('password') ?: password('Password', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $plain],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:super_admins,email'],
                'password' => ['required', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        // The 'password' => 'hashed' cast on the model does the hashing.
        $admin = SuperAdmin::create([
            'name' => $name,
            'email' => $email,
            'password' => $plain,
            'is_active' => true,
        ]);

        $this->components->info("Super admin [{$admin->email}] created.");
        $this->components->twoColumnDetail('Sign in at', rtrim((string) config('app.url'), '/').'/admin');

        return self::SUCCESS;
    }
}
