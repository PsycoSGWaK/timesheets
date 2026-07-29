<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayQuantity;
use App\Domain\Day\EventQuotaOverview;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventQuotaOverviewTest extends TestCase
{
    #[Test]
    public function remaining_is_the_quota_minus_what_was_used(): void
    {
        $overview = new EventQuotaOverview(
            DayEventCode::CongePaye,
            DayQuantity::ofHalfDays(20), // 10 j utilisés
            DayQuantity::ofHalfDays(50), // 25 j de quota
        );

        self::assertSame(30, $overview->remaining()->halfDays()); // 15 j restants
    }

    #[Test]
    public function going_over_the_quota_never_shows_a_negative_remainder(): void
    {
        $overview = new EventQuotaOverview(
            DayEventCode::Teletravail,
            DayQuantity::ofHalfDays(60),
            DayQuantity::ofHalfDays(50),
        );

        self::assertSame(0, $overview->remaining()->halfDays());
    }
}
