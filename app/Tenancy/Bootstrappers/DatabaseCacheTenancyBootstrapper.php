<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Replaces stancl's CacheTenancyBootstrapper.
 *
 * Stancl's version isolates the cache with tags: CacheManager::__call() does
 * $this->store()->tags($tags)->$method(...) unconditionally. Illuminate\Cache\DatabaseStore
 * implements Store but does NOT extend TaggableStore, so with CACHE_STORE=database every
 * Cache:: call inside a tenant context throws BadMethodCallException.
 *
 * Instead, isolation comes from the `cache` table living in the tenant's own database
 * (the store uses the default connection, which tenancy has already swapped). That is
 * stronger than tag prefixes and works with any store.
 *
 * Forgetting the resolved instances is required because CacheManager memoises stores and
 * DatabaseStore holds a resolved Connection — without it the next tenant in the same
 * process would write to the previous tenant's database.
 */
class DatabaseCacheTenancyBootstrapper implements TenancyBootstrapper
{
    public function __construct(protected Application $app) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->purge();
    }

    public function revert(): void
    {
        $this->purge();
    }

    private function purge(): void
    {
        Cache::clearResolvedInstances();
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
    }
}
