<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DayQuantity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DayQuantityTest extends TestCase
{
    #[Test]
    public function it_adds_and_subtracts_in_half_days(): void
    {
        $sum = DayQuantity::ofHalfDays(3)->plus(DayQuantity::ofHalfDays(2));
        self::assertSame(5, $sum->halfDays());

        $diff = DayQuantity::ofHalfDays(5)->minus(DayQuantity::ofHalfDays(2));
        self::assertSame(3, $diff->halfDays());
    }

    #[Test]
    public function subtracting_more_than_available_goes_negative_until_clamped(): void
    {
        $diff = DayQuantity::ofHalfDays(2)->minus(DayQuantity::ofHalfDays(5));

        self::assertTrue($diff->isNegative());
        self::assertSame(0, $diff->clampToZero()->halfDays());
    }

    #[Test]
    public function whole_and_half_days_are_labelled_in_french(): void
    {
        self::assertSame('25 j', DayQuantity::ofHalfDays(50)->toLabel());
        self::assertSame('24,5 j', DayQuantity::ofHalfDays(49)->toLabel());
        self::assertSame('0 j', DayQuantity::zero()->toLabel());
    }

    #[Test]
    public function it_parses_a_whole_day_string(): void
    {
        self::assertSame(50, DayQuantity::fromDayString('25')->halfDays());
    }

    #[Test]
    public function it_parses_a_half_day_string(): void
    {
        self::assertSame(49, DayQuantity::fromDayString('24,5')->halfDays());
    }

    #[Test]
    public function it_rejects_a_dot_decimal_separator(): void
    {
        // Format français uniquement : la virgule, jamais le point.
        $this->expectException(\InvalidArgumentException::class);

        DayQuantity::fromDayString('24.5');
    }

    #[Test]
    public function it_rejects_unreadable_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DayQuantity::fromDayString('vingt-cinq');
    }
}
