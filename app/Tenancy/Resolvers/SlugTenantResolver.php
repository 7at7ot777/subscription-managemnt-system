<?php

declare(strict_types=1);

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Routing\Route;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByPathException;
use Stancl\Tenancy\Resolvers\PathTenantResolver;

/**
 * Resolves the tenant from the URL segment by `slug`.
 *
 * Stancl's PathTenantResolver resolves via tenancy()->find(), which looks up the
 * PRIMARY KEY. Resolving by slug instead means tenant UUIDs never appear in URLs and
 * a raw UUID in the path does not resolve — that is a deliberate security property,
 * asserted by TenantResolutionTest.
 */
class SlugTenantResolver extends PathTenantResolver
{
    /**
     * Slugs must match the same shape the create rules enforce.
     *
     * This is checked BEFORE querying, for two reasons. MySQL's utf8mb4_unicode_ci
     * collation is case-insensitive, so "/ACME" would otherwise resolve the "acme"
     * tenant; and inputs such as a trailing null byte are stripped before comparison,
     * so they would resolve too. Either way one tenant becomes reachable at several
     * URLs, which fragments sessions and hides malformed input.
     */
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function resolveWithoutCache(...$args): TenantContract
    {
        /** @var Route $route */
        $route = $args[0];

        $slug = $route->parameter(static::$tenantParameterName);

        if (is_string($slug) && preg_match(self::SLUG_PATTERN, $slug) === 1) {
            $tenant = Tenant::query()->where('slug', $slug)->first();

            if ($tenant !== null && $tenant->slug === $slug) {
                return $tenant;
            }
        }

        throw new TenantCouldNotBeIdentifiedByPathException($slug);
    }

    public function getArgsForTenant(TenantContract $tenant): array
    {
        return [[$tenant->slug]];
    }
}
