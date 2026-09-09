<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Rules\TenantSlugRules;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SlugManipulationTest extends TestCase
{
    use CreatesTenants;

    #[Test]
    #[DataProvider('hostileSlugs')]
    public function hostile_slugs_never_resolve_a_tenant(string $slug): void
    {
        $this->provisionTenant('acme');

        $response = $this->get('/'.$slug.'/app/dashboard');

        // Anything but a 2xx: the request must never reach a tenant context.
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertFalse(tenancy()->initialized);
    }

    public static function hostileSlugs(): array
    {
        return [
            'traversal' => ['..%2F..%2Fetc'],
            'sql injection attempt' => ["acme'%20OR%201=1--"],
            'null byte' => ['acme%00'],
            'unknown tenant' => ['not-a-tenant'],
            'overlong slug' => [str_repeat('a', 120)],
            'uppercase variant' => ['ACME'],
        ];
    }

    #[Test]
    #[DataProvider('reservedSlugs')]
    public function reserved_slugs_are_rejected_by_validation(string $slug): void
    {
        $validator = Validator::make(['slug' => $slug], ['slug' => TenantSlugRules::make()]);

        $this->assertTrue($validator->fails(), "Expected [{$slug}] to be rejected as reserved.");
    }

    public static function reservedSlugs(): array
    {
        return array_map(
            static fn (string $slug): array => [$slug],
            ['admin', 'api', 'livewire', 'storage', 'tenancy', 'up', 'filament', 'login'],
        );
    }

    #[Test]
    #[DataProvider('malformedSlugs')]
    public function malformed_slugs_are_rejected_by_validation(string $slug): void
    {
        $validator = Validator::make(['slug' => $slug], ['slug' => TenantSlugRules::make()]);

        $this->assertTrue($validator->fails(), "Expected [{$slug}] to be rejected as malformed.");
    }

    public static function malformedSlugs(): array
    {
        return [
            'contains a slash' => ['acme/globex'],
            'contains a dot' => ['acme.globex'],
            'traversal' => ['../etc'],
            'leading hyphen' => ['-acme'],
            'trailing hyphen' => ['acme-'],
            'double hyphen' => ['acme--corp'],
            'uppercase' => ['Acme'],
            'too short' => ['ab'],
            'too long' => [str_repeat('a', 64)],
            'spaces' => ['acme corp'],
        ];
    }

    #[Test]
    public function a_well_formed_slug_is_accepted(): void
    {
        $validator = Validator::make(['slug' => 'acme-corp'], ['slug' => TenantSlugRules::make()]);

        $this->assertFalse($validator->fails(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    public function a_slug_must_be_unique(): void
    {
        $this->provisionTenant('acme');

        $validator = Validator::make(['slug' => 'acme'], ['slug' => TenantSlugRules::make()]);

        $this->assertTrue($validator->fails());
    }
}
