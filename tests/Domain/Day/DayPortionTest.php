<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DayPortion;
use App\Domain\Time\Minutes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DayPortionTest extends TestCase
{
    #[Test]
    public function a_full_portion_values_the_whole_reference_day(): void
    {
        self::assertSame(444, DayPortion::Full->of(Minutes::fromHoursAndMinutes(7, 24))->value());
    }

    #[Test]
    public function a_half_portion_values_half_the_reference_day(): void
    {
        self::assertSame(222, DayPortion::Half->of(Minutes::fromHoursAndMinutes(7, 24))->value());
    }

    #[Test]
    public function its_backing_values_are_stable_for_persistence(): void
    {
        self::assertSame('full', DayPortion::Full->value);
        self::assertSame('half', DayPortion::Half->value);
    }

    #[Test]
    public function a_full_portion_is_two_half_days(): void
    {
        self::assertSame(2, DayPortion::Full->toDayQuantity()->halfDays());
    }

    #[Test]
    public function a_half_portion_is_one_half_day(): void
    {
        self::assertSame(1, DayPortion::Half->toDayQuantity()->halfDays());
    }
}
