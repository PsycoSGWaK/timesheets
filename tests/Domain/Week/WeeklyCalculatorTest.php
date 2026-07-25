<?php

declare(strict_types=1);

namespace App\Tests\Domain\Week;

use App\Domain\Day\DayFact;
use App\Domain\Time\Minutes;
use App\Domain\Week\WeeklyCalculator;
use App\Domain\Week\WeekFact;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WeeklyCalculatorTest extends TestCase
{
    #[Test]
    public function it_sums_the_worked_time_of_the_week(): void
    {
        // Cinq journées de référence à 7 h 24 = 37 h pile.
        $week = $this->aggregate([
            '2026-07-20' => 444,
            '2026-07-21' => 444,
            '2026-07-22' => 444,
            '2026-07-23' => 444,
            '2026-07-24' => 444,
        ]);

        self::assertSame(2220, $week->workedMinutes()->value());
        self::assertSame(5, $week->dayCount());
    }

    #[Test]
    public function a_week_at_or_below_thirty_five_hours_acquires_nothing(): void
    {
        // 5 × 7 h = 35 h : rien en RTT, rien en heures supplémentaires.
        $week = $this->aggregate([
            '2026-07-20' => 420,
            '2026-07-21' => 420,
            '2026-07-22' => 420,
            '2026-07-23' => 420,
            '2026-07-24' => 420,
        ]);

        self::assertSame(2100, $week->workedMinutes()->value());
        self::assertSame(0, $week->rttAcquired()->value());
        self::assertSame(0, $week->overtimeMinutes()->value());
    }

    #[Test]
    public function between_thirty_five_and_thirty_seven_hours_the_surplus_feeds_the_rtt(): void
    {
        // 36 h : 1 h au-dessus de 35 h alimente le compteur RTT, pas d'heures sup.
        $week = $this->aggregate([
            '2026-07-20' => 432,
            '2026-07-21' => 432,
            '2026-07-22' => 432,
            '2026-07-23' => 432,
            '2026-07-24' => 432,
        ]);

        self::assertSame(2160, $week->workedMinutes()->value());
        self::assertSame(60, $week->rttAcquired()->value());
        self::assertSame(0, $week->overtimeMinutes()->value());
    }

    #[Test]
    public function exactly_thirty_seven_hours_caps_the_rtt_at_two_hours(): void
    {
        $week = $this->aggregate([
            '2026-07-20' => 444,
            '2026-07-21' => 444,
            '2026-07-22' => 444,
            '2026-07-23' => 444,
            '2026-07-24' => 444,
        ]);

        self::assertSame(120, $week->rttAcquired()->value());
        self::assertSame(0, $week->overtimeMinutes()->value());
    }

    #[Test]
    public function beyond_thirty_seven_hours_the_excess_becomes_overtime(): void
    {
        // 39 h : 2 h en RTT (plafond), et 2 h au-delà de 37 h en heures sup.
        $week = $this->aggregate([
            '2026-07-20' => 468,
            '2026-07-21' => 468,
            '2026-07-22' => 468,
            '2026-07-23' => 468,
            '2026-07-24' => 468,
        ]);

        self::assertSame(2340, $week->workedMinutes()->value());
        self::assertSame(120, $week->rttAcquired()->value());
        self::assertSame(120, $week->overtimeMinutes()->value());
    }

    #[Test]
    public function an_empty_week_is_all_zeros(): void
    {
        $week = (new WeeklyCalculator())->aggregate();

        self::assertSame(0, $week->workedMinutes()->value());
        self::assertSame(0, $week->rttAcquired()->value());
        self::assertSame(0, $week->overtimeMinutes()->value());
        self::assertSame(0, $week->dayCount());
    }

    #[Test]
    public function it_carries_the_iso_week_identity(): void
    {
        $week = $this->aggregate(['2026-07-20' => 444]);

        self::assertSame((int) (new \DateTimeImmutable('2026-07-20'))->format('o'), $week->isoYear());
        self::assertSame((int) (new \DateTimeImmutable('2026-07-20'))->format('W'), $week->isoWeek());
    }

    #[Test]
    public function a_week_straddling_two_months_is_not_split(): void
    {
        // Semaine ISO 27 de 2026 : lundi 29/06 au vendredi 03/07, à cheval sur juin/juillet.
        // L'agrégat doit les réunir sans les tronquer par le mois (spec §6.8).
        $week = $this->aggregate([
            '2026-06-29' => 444,
            '2026-06-30' => 444,
            '2026-07-01' => 444,
            '2026-07-02' => 444,
            '2026-07-03' => 444,
        ]);

        self::assertSame(5, $week->dayCount());
        self::assertSame(2220, $week->workedMinutes()->value());
        self::assertSame((int) (new \DateTimeImmutable('2026-06-29'))->format('W'), $week->isoWeek());
    }

    #[Test]
    public function it_refuses_to_aggregate_days_from_different_iso_weeks(): void
    {
        // L'agrégat porte sur une seule semaine : mélanger deux semaines est un bug appelant.
        $this->expectException(\InvalidArgumentException::class);

        $this->aggregate([
            '2026-07-24' => 444, // semaine N
            '2026-07-27' => 444, // semaine N+1
        ]);
    }

    /**
     * @param array<string, int> $workedByDate date ISO => minutes travaillées
     */
    private function aggregate(array $workedByDate): WeekFact
    {
        $days = [];
        foreach ($workedByDate as $date => $worked) {
            $days[] = $this->dayFact($date, $worked);
        }

        return (new WeeklyCalculator())->aggregate(...$days);
    }

    private function dayFact(string $date, int $worked): DayFact
    {
        return new DayFact(
            new \DateTimeImmutable($date),
            Minutes::of($worked),
            null,
            Minutes::of(0),
            Minutes::of($worked),
            [],
        );
    }
}
