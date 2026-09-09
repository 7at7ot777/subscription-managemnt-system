<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class ReservedSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $reserved = (array) config('tenancy.reserved_slugs', []);

        if (in_array(Str::lower($value), $reserved, true)) {
            $fail('The :attribute is reserved and cannot be used as a tenant identifier.');
        }
    }
}
