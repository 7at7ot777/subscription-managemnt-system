<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('subscriptionWindows')]
    public function it_derives_the_subscription_state_from_the_window(
        ?string $startAt,
        ?string $endAt,
        SubscriptionStatus $expected,
    ): void {
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');

        $tenant = new Tenant;
        $tenant->subscription_start_at = $startAt !== null ? CarbonImmutable::parse($startAt) : null;
        $tenant->subscription_end_at = $endAt !== null ? CarbonImmutable::parse($endAt) : null;

        $this->assertSame($expected, $tenant->subscriptionStatus());
    }

    public static function subscriptionWindows(): array
    {
        return [
            'inside the window' => ['2026-01-01 00:00:00', '2026-12-31 23:59:59', SubscriptionStatus::Active],
            'ended yesterday' => ['2026-01-01 00:00:00', '2026-06-14 23:59:59', SubscriptionStatus::Expired],
            'starts next week' => ['2026-06-22 00:00:00', '2026-12-31 23:59:59', SubscriptionStatus::NotStarted],

            // A null end date means perpetual, NOT "expired at the epoch". Getting this
            // backwards would lock out every tenant without an end date.
            'no end date is perpetual' => ['2026-01-01 00:00:00', null, SubscriptionStatus::Active],
            'no start date has always been running' => [null, '2026-12-31 23:59:59', SubscriptionStatus::Active],
            'no dates at all' => [null, null, SubscriptionStatus::Active],

            // Boundaries: the comparison is strict, so the instant itself is still valid.
            'exactly at the end instant' => ['2026-01-01 00:00:00', '2026-06-15 12:00:00', SubscriptionStatus::Active],
            'exactly at the start instant' => ['2026-06-15 12:00:00', '2026-12-31 00:00:00', SubscriptionStatus::Active],
            'one second past the end' => ['2026-01-01 00:00:00', '2026-06-15 11:59:59', SubscriptionStatus::Expired],
        ];
    }

    #[Test]
    public function only_the_active_state_grants_access(): void
    {
        $this->assertTrue(SubscriptionStatus::Active->allowsAccess());
        $this->assertFalse(SubscriptionStatus::Expired->allowsAccess());
        $this->assertFalse(SubscriptionStatus::NotStarted->allowsAccess());
    }
}
