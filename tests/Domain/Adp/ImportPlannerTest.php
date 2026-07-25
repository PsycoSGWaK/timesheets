<?php

declare(strict_types=1);

namespace App\Tests\Domain\Adp;

use App\Domain\Adp\ImportPlan;
use App\Domain\Adp\ImportPlanner;
use App\Domain\Adp\ParsedDay;
use App\Domain\Adp\ParsedWeek;
use App\Domain\Punch\PunchNature;
use App\Domain\Punch\PunchOrigin;
use App\Domain\Time\Minutes;
use App\Entity\PunchEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportPlannerTest extends TestCase
{
    private const OBSERVED = '2026-07-24 03:12:00';

    #[Test]
    public function a_fresh_day_yields_one_reading_and_all_its_punches(): void
    {
        $plan = $this->plan(
            $this->week($this->day('2026-07-23', 444, ['08:30', '12:12', '13:00', '16:42'])),
        );

        self::assertCount(1, $plan->readingsToRecord());
        self::assertCount(4, $plan->punchesToCreate());
        self::assertCount(0, $plan->provisionalToSupersede());
    }

    #[Test]
    public function created_punches_are_real_adp_punches_ranked_in_order(): void
    {
        $plan = $this->plan(
            $this->week($this->day('2026-07-23', 444, ['08:30', '12:12', '16:42'])),
        );

        $ranks = array_map(static fn (PunchEvent $p): int => $p->rang(), $plan->punchesToCreate());
        self::assertSame([1, 2, 3], $ranks);

        foreach ($plan->punchesToCreate() as $punch) {
            self::assertSame(PunchNature::Reel, $punch->nature());
            self::assertSame(PunchOrigin::Adp, $punch->origin());
        }

        self::assertSame(510, $plan->punchesToCreate()[0]->time()->value());
    }

    #[Test]
    public function a_zero_total_is_still_recorded_as_a_reading(): void
    {
        $plan = $this->plan(
            $this->week($this->day('2026-07-23', 0, ['08:30', '12:00', '12:30', '16:00'])),
        );

        self::assertCount(1, $plan->readingsToRecord());
        self::assertSame(0, $plan->readingsToRecord()[0]->employerMinutes()->value());
    }

    #[Test]
    public function a_rest_day_without_total_nor_punch_plans_nothing(): void
    {
        $plan = $this->plan(
            $this->week($this->day('2026-07-26', null, [])),
        );

        self::assertCount(0, $plan->readingsToRecord());
        self::assertCount(0, $plan->punchesToCreate());
        self::assertCount(0, $plan->provisionalToSupersede());
    }

    #[Test]
    public function real_data_supersedes_the_days_provisional_punches(): void
    {
        // Le jour portait 4 pointages prévisionnels ; le réel arrive et les efface tous.
        $existing = [
            '2026-07-23' => [
                $this->provisional('2026-07-23', '08:35', 1),
                $this->provisional('2026-07-23', '12:00', 2),
                $this->provisional('2026-07-23', '13:00', 3),
                $this->provisional('2026-07-23', '16:30', 4),
            ],
        ];

        $plan = $this->plan(
            $this->week($this->day('2026-07-23', 444, ['08:30', '12:12', '13:00', '16:42'])),
            $existing,
        );

        self::assertCount(4, $plan->provisionalToSupersede());
        self::assertCount(4, $plan->punchesToCreate());
    }

    #[Test]
    public function re_importing_the_same_punches_creates_none_but_still_records_the_reading(): void
    {
        // Idempotence : les pointages sont dédupliqués sur (date, heure, rang).
        // Le relevé, lui, est une observation horodatée : il s'ajoute à chaque import.
        $existing = [
            '2026-07-23' => [
                $this->real('2026-07-23', '08:30', 1),
                $this->real('2026-07-23', '12:12', 2),
                $this->real('2026-07-23', '13:00', 3),
                $this->real('2026-07-23', '16:42', 4),
            ],
        ];

        $plan = $this->plan(
            $this->week($this->day('2026-07-23', 444, ['08:30', '12:12', '13:00', '16:42'])),
            $existing,
        );

        self::assertCount(0, $plan->punchesToCreate());
        self::assertCount(0, $plan->provisionalToSupersede());
        self::assertCount(1, $plan->readingsToRecord());
    }

    #[Test]
    public function only_the_genuinely_new_punches_are_created(): void
    {
        // Deux pointages déjà présents (rangs 1 et 2) ; l'import en apporte quatre.
        $existing = [
            '2026-07-23' => [
                $this->real('2026-07-23', '08:30', 1),
                $this->real('2026-07-23', '12:12', 2),
            ],
        ];

        $plan = $this->plan(
            $this->week($this->day('2026-07-23', 444, ['08:30', '12:12', '13:00', '16:42'])),
            $existing,
        );

        $ranks = array_map(static fn (PunchEvent $p): int => $p->rang(), $plan->punchesToCreate());
        self::assertSame([3, 4], $ranks);
    }

    #[Test]
    public function it_aggregates_a_multi_day_week(): void
    {
        $plan = $this->plan(
            $this->week(
                $this->day('2026-07-20', 444, ['08:30', '16:42']),
                $this->day('2026-07-21', 444, ['08:00', '16:12']),
                $this->day('2026-07-22', null, []),
            ),
        );

        self::assertCount(2, $plan->readingsToRecord());
        self::assertCount(4, $plan->punchesToCreate());
    }

    /**
     * @param array<string, list<PunchEvent>> $existingPunchesByDate
     */
    private function plan(ParsedWeek $week, array $existingPunchesByDate = []): ImportPlan
    {
        return (new ImportPlanner())->plan(
            $week,
            new \DateTimeImmutable(self::OBSERVED),
            $existingPunchesByDate,
        );
    }

    private function week(ParsedDay ...$days): ParsedWeek
    {
        return new ParsedWeek(2026, $days);
    }

    /**
     * @param list<string> $punchClocks
     */
    private function day(string $date, ?int $totalMinutes, array $punchClocks): ParsedDay
    {
        return new ParsedDay(
            new \DateTimeImmutable($date),
            null === $totalMinutes ? null : Minutes::of($totalMinutes),
            null,
            array_map(static fn (string $c): Minutes => Minutes::fromClock($c), $punchClocks),
        );
    }

    private function real(string $date, string $clock, int $rang): PunchEvent
    {
        return PunchEvent::realFromAdp(new \DateTimeImmutable($date), Minutes::fromClock($clock), $rang);
    }

    private function provisional(string $date, string $clock, int $rang): PunchEvent
    {
        return PunchEvent::provisional(new \DateTimeImmutable($date), Minutes::fromClock($clock), $rang);
    }
}
