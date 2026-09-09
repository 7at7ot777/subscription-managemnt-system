<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateTenantAction;
use App\Data\CreateTenantData;
use App\Exceptions\TenantProvisioningFailedException;
use App\Rules\TenantSlugRules;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Stancl already ships tenants:migrate, tenants:migrate-fresh, tenants:rollback,
 * tenants:seed, tenants:run and tenants:list, so this is the only tenancy command
 * this application needs to add.
 */
class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
                            {name? : The tenant display name}
                            {slug? : The URL slug, e.g. acme}
                            {--admin-name= : Name of the initial tenant administrator}
                            {--admin-email= : Email of the initial tenant administrator}
                            {--admin-password= : Password for the initial administrator (prompted if omitted)}
                            {--starts-at= : Subscription start date (blank = immediately)}
                            {--ends-at= : Subscription end date (blank = perpetual)}';

    protected $description = 'Provision a tenant: create its database, migrate it and create its first administrator';

    public function handle(CreateTenantAction $createTenant): int
    {
        $name = $this->argument('name') ?: text('Tenant name', required: true);
        $slug = $this->argument('slug') ?: text('Slug', default: Str::slug($name), required: true);
        $adminName = $this->option('admin-name') ?: text('Administrator name', required: true);
        $adminEmail = $this->option('admin-email') ?: text('Administrator email', required: true);
        $adminPassword = $this->option('admin-password') ?: password('Administrator password', required: true);

        $validator = Validator::make([
            'name' => $name,
            'slug' => $slug,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'slug' => TenantSlugRules::make(),
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        try {
            $tenant = $createTenant->handle(new CreateTenantData(
                name: $name,
                slug: $slug,
                adminName: $adminName,
                adminEmail: $adminEmail,
                adminPassword: $adminPassword,
                subscriptionStartAt: $this->dateOption('starts-at'),
                subscriptionEndAt: $this->dateOption('ends-at'),
            ));
        } catch (TenantProvisioningFailedException $e) {
            $this->components->error($e->getMessage());
            $this->components->warn(
                'The tenant record was kept and marked as failed so you can inspect it. '
                .'Nothing was deleted automatically.'
            );

            return self::FAILURE;
        }

        $this->components->info("Tenant [{$tenant->slug}] provisioned.");
        $this->components->twoColumnDetail('Database', (string) $tenant->database()->getName());
        $this->components->twoColumnDetail('Sign in at', rtrim((string) config('app.url'), '/')."/{$tenant->slug}/app");

        return self::SUCCESS;
    }

    private function dateOption(string $option): ?CarbonImmutable
    {
        $value = $this->option($option);

        return filled($value) ? CarbonImmutable::parse($value) : null;
    }
}
