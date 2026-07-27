<?php

declare(strict_types=1);

namespace App\Tests\Domain\Week;

use App\Domain\Week\IsoWeek;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsoWeekTest extends TestCase
{
    #[Test]
    public function it_returns_the_seven_dates_of_the_week_starting_monday(): void
    {
        $dates = IsoWeek::dates(2026, 30);

        self::assertCount(7, $dates);
        self::assertSame('2026-07-20', $dates[0]->format('Y-m-d')); // lundi
        self::assertSame('2026-07-26', $dates[6]->format('Y-m-d')); // dimanche
    }

    #[Test]
    public function the_dates_are_normalised_to_midnight(): void
    {
        $dates = IsoWeek::dates(2026, 30);

        self::assertSame('00:00:00', $dates[0]->format('H:i:s'));
    }
}
