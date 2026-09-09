<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    use CreatesTenants;

    #[Test]
    public function each_tenant_sees_only_its_own_records(): void
    {
        $acme = $this->provisionTenant('acme');
        $globex = $this->provisionTenant('globex');

        $acme->run(fn () => User::create([
            'name' => 'Acme Person',
            'email' => 'person@acme.test',
            'password' => 'Sup3rSecret!23',
        ]));

        $globex->run(fn () => User::create([
            'name' => 'Globex Person',
            'email' => 'person@globex.test',
            'password' => 'Sup3rSecret!23',
        ]));

        $acmeEmails = $acme->run(fn (): array => User::query()->pluck('email')->all());
        $globexEmails = $globex->run(fn (): array => User::query()->pluck('email')->all());

        $this->assertContains('person@acme.test', $acmeEmails);
        $this->assertNotContains('person@globex.test', $acmeEmails);

        $this->assertContains('person@globex.test', $globexEmails);
        $this->assertNotContains('person@acme.test', $globexEmails);
    }

    #[Test]
    public function identical_primary_keys_in_two_tenants_are_different_records(): void
    {
        // The sharpest form of the isolation question: if scoping were broken, user #1
        // in one tenant would resolve to user #1 in the other.
        $acme = $this->provisionTenant('acme');
        $globex = $this->provisionTenant('globex');

        $acmeFirst = $acme->run(fn (): User => User::query()->firstOrFail());
        $globexFirst = $globex->run(fn (): User => User::query()->firstOrFail());

        $this->assertSame($acmeFirst->getKey(), $globexFirst->getKey());
        $this->assertNotSame($acmeFirst->email, $globexFirst->email);
    }

    #[Test]
    public function each_tenant_uses_a_distinct_database_connection(): void
    {
        $acme = $this->provisionTenant('acme');
        $globex = $this->provisionTenant('globex');

        $acmeDb = $acme->run(fn (): string => DB::connection()->getDatabaseName());
        $globexDb = $globex->run(fn (): string => DB::connection()->getDatabaseName());

        $this->assertNotSame($acmeDb, $globexDb);
        $this->assertSame($acme->database()->getName(), $acmeDb);
        $this->assertSame($globex->database()->getName(), $globexDb);
    }

    #[Test]
    public function tenant_context_does_not_leak_after_it_ends(): void
    {
        $acme = $this->provisionTenant('acme');

        $acme->run(fn () => $this->assertTrue(tenancy()->initialized));

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame(
            config('database.connections.'.config('tenancy.database.central_connection').'.database'),
            DB::connection()->getDatabaseName(),
        );
    }

    #[Test]
    public function there_is_no_central_users_table_to_fall_back_to(): void
    {
        // If tenancy ever fails to initialise, queries must fail loudly rather than
        // silently authenticating against a central users table.
        $this->assertFalse(
            DB::connection(config('tenancy.database.central_connection'))
                ->getSchemaBuilder()
                ->hasTable('users'),
        );
    }
}
