<?php

declare(strict_types=1);

namespace App\Tests\Domain\Projection;

use App\Domain\Projection\LeaveTimeCalculator;
use App\Domain\Time\Minutes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LeaveTimeCalculatorTest extends TestCase
{
    #[Test]
    public function it_reproduces_the_specification_example(): void
    {
        // Spec §4.6 : matin 2 h 59, pause de 26 min → sortie à 16h42, alignée sur ADP.
        // Matin 08:48 → 11:47 (2 h 59), retour de pause 12:13 (26 min de pause).
        $estimate = (new LeaveTimeCalculator())->estimate(
            Minutes::fromClock('08:48'),
            Minutes::fromClock('11:47'),
            Minutes::fromClock('12:13'),
        );

        self::assertSame('16:42', $estimate->expectedLeave()->toClock());
        self::assertSame(179, $estimate->morningWorked()->value());
        self::assertSame(26, $estimate->breakDuration()->value());
        self::assertSame(4, $estimate->breakPenalty()->value());
    }

    #[Test]
    public function a_sufficient_break_carries_no_penalty(): void
    {
        // Matin 08:30 → 12:00 (3 h 30), pause 45 min, retour 12:45, objectif 7 h 24.
        $estimate = (new LeaveTimeCalculator())->estimate(
            Minutes::fromClock('08:30'),
            Minutes::fromClock('12:00'),
            Minutes::fromClock('12:45'),
        );

        self::assertSame(0, $estimate->breakPenalty()->value());
        // 12:45 + 7h24 + 0 − 3h30 = 16:39.
        self::assertSame('16:39', $estimate->expectedLeave()->toClock());
    }

    #[Test]
    public function a_break_of_exactly_thirty_minutes_costs_nothing(): void
    {
        $estimate = (new LeaveTimeCalculator())->estimate(
            Minutes::fromClock('08:30'),
            Minutes::fromClock('12:00'),
            Minutes::fromClock('12:30'),
        );

        self::assertSame(0, $estimate->breakPenalty()->value());
    }

    #[Test]
    public function a_short_break_pushes_the_leave_time_back(): void
    {
        // Pause de 20 min : 10 min de pénalité repoussent la sortie d'autant.
        $estimate = (new LeaveTimeCalculator())->estimate(
            Minutes::fromClock('08:30'),
            Minutes::fromClock('12:00'),
            Minutes::fromClock('12:20'),
        );

        self::assertSame(10, $estimate->breakPenalty()->value());
        // 12:20 + 7h24 + 0h10 − 3h30 = 16:24.
        self::assertSame('16:24', $estimate->expectedLeave()->toClock());
    }

    #[Test]
    public function the_objective_is_adjustable(): void
    {
        // Objectif ramené à 7 h : la sortie avance de 24 min par rapport au défaut.
        $estimate = (new LeaveTimeCalculator())->estimate(
            Minutes::fromClock('08:30'),
            Minutes::fromClock('12:00'),
            Minutes::fromClock('12:45'),
            Minutes::fromHoursAndMinutes(7, 0),
        );

        // 12:45 + 7h00 + 0 − 3h30 = 16:15.
        self::assertSame('16:15', $estimate->expectedLeave()->toClock());
        self::assertSame(420, $estimate->objective()->value());
    }

    #[Test]
    public function the_default_objective_is_seven_hours_twenty_four(): void
    {
        $estimate = (new LeaveTimeCalculator())->estimate(
            Minutes::fromClock('08:30'),
            Minutes::fromClock('12:00'),
            Minutes::fromClock('12:45'),
        );

        self::assertSame(444, $estimate->objective()->value());
    }
}
