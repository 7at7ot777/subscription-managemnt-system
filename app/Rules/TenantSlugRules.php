<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Validation\Rule;

/**
 * One definition of a valid slug, shared by the Filament form, the console command and
 * the form requests, so the three can never drift apart.
 *
 * The regex bans dots and slashes outright, which removes path-traversal slugs at source.
 */
final class TenantSlugRules
{
    /**
     * @return array<int, mixed>
     */
    public static function make(int|string|null $ignoreTenantId = null): array
    {
        return [
            'required',
            'string',
            'lowercase',
            'min:'.config('tenancy.slug.min', 3),
            'max:'.config('tenancy.slug.max', 63),
            // lowercase alphanumerics separated by single hyphens; no leading,
            // trailing or doubled hyphens.
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            new ReservedSlug,
            Rule::unique('tenants', 'slug')->ignore($ignoreTenantId),
        ];
    }
}
