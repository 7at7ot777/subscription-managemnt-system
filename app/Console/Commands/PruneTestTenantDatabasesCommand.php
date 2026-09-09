<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sweeps up tenant databases left behind by an interrupted test run.
 *
 * Safe by construction: it only ever matches the test prefix, which phpunit.xml sets
 * to something that cannot collide with the production prefix.
 */
class PruneTestTenantDatabasesCommand extends Command
{
    protected $signature = 'tenant:prune-test-databases {--force : Skip the confirmation prompt}';

    protected $description = 'Drop tenant databases left over from interrupted test runs';

    public function handle(): int
    {
        $prefix = 'testtenant';

        if (config('tenancy.database.prefix') === $prefix && ! app()->runningUnitTests()) {
            $this->components->warn('The configured tenant prefix is the test prefix; refusing to run.');

            return self::FAILURE;
        }

        // MySQL cannot bind a placeholder in SHOW DATABASES LIKE, and the prefix is a
        // hardcoded literal above rather than user input, so inlining it is safe here.
        $databases = collect(DB::select("SHOW DATABASES LIKE '{$prefix}%'"))
            ->map(fn (object $row): string => (string) reset($row))
            ->all();

        if ($databases === []) {
            $this->components->info('No leftover test tenant databases found.');

            return self::SUCCESS;
        }

        $this->components->warn(count($databases).' leftover test tenant database(s) found.');

        if (! $this->option('force') && ! $this->confirm('Drop them all?', true)) {
            return self::SUCCESS;
        }

        foreach ($databases as $database) {
            DB::statement("DROP DATABASE IF EXISTS `{$database}`");
            $this->line("  dropped {$database}");
        }

        $this->components->info('Done.');

        return self::SUCCESS;
    }
}
