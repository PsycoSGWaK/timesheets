<?php

declare(strict_types=1);

namespace App\Tests\Domain\Work;

use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayHalf;
use App\Domain\Day\DayPortion;
use App\Domain\Reconciliation\ReconciliationStatus;
use App\Domain\Time\Minutes;
use App\Domain\Work\WorkWeek;
use App\Domain\Work\WorkWeekAssembler;
use App\Entity\DayEvent;
use App\Entity\PunchEvent;
use App\Entity\Settings;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkWeekAssemblerTest extends TestCase
{
    private const TODAY = '2026-07-25'; // samedi ; lun-ven de la semaine sont passés

    private User $user;
    private Settings $settings;

    protected function setUp(): void
    {
        $this->user = User::register('guillaume@example.com', 'hashed-password');
        $this->settings = Settings::defaults($this->user);
    }

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
    public function a_provisional_punch_counts_toward_nous_until_the_real_one_arrives(): void
    {
        // Sans ça, "Nous" resterait à 0h00 tant qu'ADP n'a rien livré et la
        // comparaison avec la colonne ADP n'aurait aucun sens.
        $punches = [
            PunchEvent::provisional($this->user, new \DateTimeImmutable('2026-07-22'), Minutes::fromClock('08:00'), 1),
            PunchEvent::provisional($this->user, new \DateTimeImmutable('2026-07-22'), Minutes::fromClock('16:00'), 2),
        ];

        $week = $this->assemble($punches, []);

        $wednesday = $week->days()[2];
        self::assertSame(480, $wednesday->dayFact()->workedMinutes()->value());
        self::assertSame(ReconciliationStatus::NoReading, $wednesday->reconciliation()->status());
    }

    #[Test]
    public function a_real_punch_takes_precedence_over_a_lingering_provisional_one(): void
    {
        // Ne devrait pas arriver en pratique (DayController bascule tout complément
        // en correction réelle dès qu'un pointage réel existe), mais si les deux
        // cohabitaient, le réel doit l'emporter sans se mélanger au prévisionnel.
        $punches = [
            PunchEvent::provisional($this->user, new \DateTimeImmutable('2026-07-22'), Minutes::fromClock('07:00'), 1),
            PunchEvent::realFromAdp($this->user, new \DateTimeImmutable('2026-07-22'), Minutes::fromClock('08:00'), 1),
            PunchEvent::realFromAdp($this->user, new \DateTimeImmutable('2026-07-22'), Minutes::fromClock('16:00'), 2),
        ];

        $week = $this->assemble($punches, []);

        $wednesday = $week->days()[2];
        self::assertSame(480, $wednesday->dayFact()->workedMinutes()->value());
    }

    #[Test]
    public function a_teletravail_day_without_any_punch_is_valued_and_labelled(): void
    {
        // Vendredi 24/07 : télétravail sans badge, spec §6.5.
        $events = [
            '2026-07-24' => DayEvent::declare($this->user, new \DateTimeImmutable('2026-07-24'), DayEventCode::Teletravail),
        ];

        $week = $this->assemble([], [], $events);

        $friday = $week->days()[4];
        self::assertSame(444, $friday->dayFact()->workedMinutes()->value());
        self::assertSame(DayEventCode::Teletravail, $friday->event()?->code());
    }

    #[Test]
    public function a_teletravail_half_day_uses_the_provisional_times_entered_for_that_day(): void
    {
        // Vendredi 24/07 : TT demi-journée matin, horaires saisis via /jour/{date}
        // avant tout pointage réel (règle précise du 28/07/2026).
        $events = [
            '2026-07-24' => DayEvent::declare(
                $this->user,
                new \DateTimeImmutable('2026-07-24'),
                DayEventCode::Teletravail,
                DayPortion::Half,
                DayHalf::Matin,
            ),
        ];
        $punches = [
            PunchEvent::provisional($this->user, new \DateTimeImmutable('2026-07-24'), Minutes::fromClock('08:30'), 1),
            PunchEvent::provisional($this->user, new \DateTimeImmutable('2026-07-24'), Minutes::fromClock('12:30'), 3),
        ];

        $week = $this->assemble($punches, [], $events);

        $friday = $week->days()[4];
        self::assertSame(240, $friday->dayFact()->workedMinutes()->value()); // 08:30 -> 12:30
    }

    #[Test]
    public function a_rest_day_is_reconciled_as_repos_regardless_of_reading(): void
    {
        // Samedi 25/07, jour de repos par défaut : rien n'est attendu.
        $readings = ['2026-07-25' => Minutes::of(0)];

        $week = $this->assemble([], $readings);

        $saturday = $week->days()[5];
        self::assertSame(ReconciliationStatus::Repos, $saturday->reconciliation()->status());
    }

    #[Test]
    public function a_rest_day_is_never_valued_even_with_a_lingering_event(): void
    {
        // Dimanche 26/07, jour de repos par défaut : un événement resté en base
        // (déclaré avant que ce jour devienne repos) ne doit plus compter.
        $events = [
            '2026-07-26' => DayEvent::declare($this->user, new \DateTimeImmutable('2026-07-26'), DayEventCode::Teletravail),
        ];

        $week = $this->assemble([], [], $events);

        $sunday = $week->days()[6];
        self::assertSame(0, $sunday->dayFact()->workedMinutes()->value());
        self::assertSame(ReconciliationStatus::Repos, $sunday->reconciliation()->status());
    }

    #[Test]
    public function a_day_without_any_event_exposes_none(): void
    {
        $week = $this->assemble([], []);

        self::assertNull($week->days()[0]->event());
    }

    /**
     * @param list<PunchEvent>       $punches
     * @param array<string, Minutes> $readings
     * @param array<string, DayEvent> $events
     */
    private function assemble(array $punches, array $readings, array $events = []): WorkWeek
    {
        $dates = [];
        for ($i = 0; $i < 7; ++$i) {
            $dates[] = new \DateTimeImmutable(sprintf('2026-07-%02d', 20 + $i));
        }

        return (new WorkWeekAssembler(
            new \App\Domain\Day\DailyCalculator(),
            new \App\Domain\Week\WeeklyCalculator(),
            new \App\Domain\Reconciliation\ReconciliationDetector(),
            new \App\Domain\Day\DayEventValorizer(),
        ))->assemble($dates, $punches, $readings, $events, new \DateTimeImmutable(self::TODAY), $this->settings);
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
            $punches[] = PunchEvent::realFromAdp($this->user, new \DateTimeImmutable($date), Minutes::fromClock($clock), $rang);
            ++$rang;
        }

        return $punches;
    }
}
