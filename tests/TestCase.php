<?php

declare(strict_types=1);

namespace Tests;

use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase is deliberately NOT used anywhere in this suite.
     *
     * It wraps each test in a transaction, but provisioning a tenant issues
     * CREATE DATABASE, and MySQL performs an implicit commit on DDL. The transaction
     * would be silently committed mid-test and the rollback would be a no-op, while
     * still appearing to guarantee isolation. Worse, tenancy rebinds the default
     * connection mid-test, so the transaction would no longer even refer to the
     * connection under test.
     *
     * Instead: migrate the central database once per process, then truncate between
     * tests. Tenant databases are dropped explicitly (see Concerns\CreatesTenants).
     */
    private static bool $centralMigrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$centralMigrated) {
            $this->artisan('migrate:fresh', ['--force' => true]);
            self::$centralMigrated = true;
        } else {
            $this->truncateCentralTables();
        }
    }

    protected function tearDown(): void
    {
        // The Tenancy singleton holds state that would otherwise survive into the next
        // test in the same process.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /**
     * Transaction-free reset, so it is safe alongside the DDL that tenant
     * provisioning performs.
     */
    protected function truncateCentralTables(): void
    {
        $connection = DB::connection(config('tenancy.database.central_connection'));
        $database = $connection->getDatabaseName();
        $keep = ['migrations'];

        // Scoped to this connection's own schema on purpose: getTableListing() can
        // return schema-qualified tables from every database on the server.
        $tables = $connection->select(
            'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = ? AND table_type = ?',
            [$database, 'BASE TABLE'],
        );

        $connection->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            if (in_array($table->name, $keep, true)) {
                continue;
            }

            $connection->table($table->name)->truncate();
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function actingAsSuperAdmin(?SuperAdmin $admin = null): SuperAdmin
    {
        $admin ??= SuperAdmin::factory()->create();

        $this->actingAs($admin, 'super_admin');

        return $admin;
    }
}
