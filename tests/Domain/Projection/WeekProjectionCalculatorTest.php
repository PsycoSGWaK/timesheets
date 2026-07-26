<?php

declare(strict_types=1);

namespace App\Tests\Domain\Projection;

use App\Domain\Projection\WeekProjectionCalculator;
use App\Domain\Time\Minutes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WeekProjectionCalculatorTest extends TestCase
{
    #[Test]
    public function it_splits_the_remaining_time_equally_over_the_remaining_days(): void
    {
        // 15 h faites, 3 jours ouvrés restants, objectif 37 h : reste 22 h à répartir.
        $projection = (new WeekProjectionCalculator())->project(Minutes::of(900), 3);

        self::assertSame(1320, $projection->remainingToObjective()->value());
        self::assertSame(440, $projection->targetPerRemainingDay()->value()); // 22 h / 3
        self::assertSame(0, $projection->overtimeSoFar()->value());
        self::assertFalse($projection->objectiveReached());
    }

    #[Test]
    public function reaching_the_objective_leaves_nothing_to_do(): void
    {
        $projection = (new WeekProjectionCalculator())->project(Minutes::of(2220), 2);

        self::assertSame(0, $projection->remainingToObjective()->value());
        self::assertSame(0, $projection->targetPerRemainingDay()->value());
        self::assertTrue($projection->objectiveReached());
    }

    #[Test]
    public function beyond_the_objective_the_surplus_is_counted_as_overtime(): void
    {
        // 39 h faites, plus de jour restant : 2 h au-delà de 37 h sont des heures sup.
        $projection = (new WeekProjectionCalculator())->project(Minutes::of(2340), 0);

        self::assertSame(0, $projection->remainingToObjective()->value());
        self::assertSame(120, $projection->overtimeSoFar()->value());
        self::assertTrue($projection->objectiveReached());
    }

    #[Test]
    public function being_behind_with_no_day_left_shows_the_gap_but_no_daily_target(): void
    {
        $projection = (new WeekProjectionCalculator())->project(Minutes::of(2000), 0);

        self::assertSame(220, $projection->remainingToObjective()->value());
        self::assertSame(0, $projection->targetPerRemainingDay()->value());
        self::assertFalse($projection->objectiveReached());
    }

    #[Test]
    public function the_weekly_objective_is_adjustable(): void
    {
        // Objectif ramené à 35 h : reste 5 h sur 5 jours = 1 h par jour.
        $projection = (new WeekProjectionCalculator())->project(Minutes::of(1800), 5, Minutes::of(35 * 60));

        self::assertSame(300, $projection->remainingToObjective()->value());
        self::assertSame(60, $projection->targetPerRemainingDay()->value());
    }

    #[Test]
    public function it_rejects_a_negative_count_of_remaining_days(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new WeekProjectionCalculator())->project(Minutes::of(900), -1);
    }
}
