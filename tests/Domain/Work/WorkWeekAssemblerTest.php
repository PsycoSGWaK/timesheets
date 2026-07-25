<?php

declare(strict_types=1);

namespace App\Tests\Domain\Work;

use App\Domain\Reconciliation\ReconciliationStatus;
use App\Domain\Time\Minutes;
use App\Domain\Work\WorkWeek;
use App\Domain\Work\WorkWeekAssembler;
use App\Entity\PunchEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkWeekAssemblerTest extends TestCase
{
    private const TODAY = '2026-07-25'; // samedi ; lun-ven de la semaine sont passés

    #[Test]
    public function it_builds_one_work_day_per_date_of_the_week(): void
    {
        $week = $this->assemble([], []);

        self::assertCount(7, $week->days());
    }

    #[Test]
    public function it_recomputes_each_day_and_reconciles_it_with_adp(): void
    {
        $punches = [
            // Lundi : journée pleine, alignée avec ADP.
            ...$this->punches('2026-07-20', ['08:30', '12:12', '13:00', '16:42']),
            // Mardi : 7 h travaillées mais ADP affiche 0:00 — journée perdue.
            ...$this->punches('2026-07-21', ['08:30', '12:00', '12:30', '16:00']),
        ];
        $readings = [
            '2026-07-20' => Minutes::of(444),
            '2026-07-21' => Minutes::of(0),
        ];

        $week = $this->assemble($punches, $readings);

        $monday = $week->days()[0];
        self::assertSame(444, $monday->dayFact()->workedMinutes()->value());
        self::assertSame(ReconciliationStatus::Aligned, $monday->reconciliation()->status());

        $tuesday = $week->days()[1];
        self::assertSame(420, $tuesday->dayFact()->workedMinutes()->value());
        self::assertSame(ReconciliationStatus::EmployerZero, $tuesday->reconciliation()->status());
        self::assertTrue($tuesday->reconciliation()->needsAttention());
    }

    #[Test]
    public function it_aggregates_the_week_total_from_the_recomputed_days(): void
    {
        $punches = [
            ...$this->punches('2026-07-20', ['08:30', '12:12', '13:00', '16:42']), // 444
            ...$this->punches('2026-07-21', ['08:30', '12:00', '12:30', '16:00']), // 420
        ];

        $week = $this->assemble($punches, []);

        self::assertSame(864, $week->weekFact()->workedMinutes()->value());
        self::assertSame(0, $week->weekFact()->overtimeMinutes()->value());
    }

    #[Test]
    public function provisional_punches_are_ignored_in_the_official_recalculation(): void
    {
        // Un pointage prévisionnel n'entre pas dans le décompte officiel.
        $punches = [
            PunchEvent::provisional(new \DateTimeImmutable('2026-07-22'), Minutes::fromClock('08:00'), 1),
            PunchEvent::provisional(new \DateTimeImmutable('2026-07-22'), Minutes::fromClock('16:00'), 2),
        ];

        $week = $this->assemble($punches, []);

        $wednesday = $week->days()[2];
        self::assertSame(0, $wednesday->dayFact()->workedMinutes()->value());
        self::assertSame(ReconciliationStatus::NoReading, $wednesday->reconciliation()->status());
    }

    /**
     * @param list<PunchEvent>      $punches
     * @param array<string, Minutes> $readings
     */
    private function assemble(array $punches, array $readings): WorkWeek
    {
        $dates = [];
        for ($i = 0; $i < 7; ++$i) {
            $dates[] = new \DateTimeImmutable(sprintf('2026-07-%02d', 20 + $i));
        }

        return (new WorkWeekAssembler(
            new \App\Domain\Day\DailyCalculator(),
            new \App\Domain\Week\WeeklyCalculator(),
            new \App\Domain\Reconciliation\ReconciliationDetector(),
        ))->assemble($dates, $punches, $readings, new \DateTimeImmutable(self::TODAY));
    }

    /**
     * @param list<string> $clocks
     *
     * @return list<PunchEvent>
     */
    private function punches(string $date, array $clocks): array
    {
        $punches = [];
        $rang = 1;
        foreach ($clocks as $clock) {
            $punches[] = PunchEvent::realFromAdp(new \DateTimeImmutable($date), Minutes::fromClock($clock), $rang);
            ++$rang;
        }

        return $punches;
    }
}
