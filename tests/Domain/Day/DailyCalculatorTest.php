<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DailyAnomaly;
use App\Domain\Day\DailyCalculator;
use App\Domain\Day\DayFact;
use App\Domain\Time\Minutes;
use App\Entity\PunchEvent;
use App\Entity\Settings;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DailyCalculatorTest extends TestCase
{
    private const DAY = '2026-07-23';

    private User $user;
    private Settings $settings;

    protected function setUp(): void
    {
        $this->user = User::register('guillaume@example.com', 'hashed-password');
        $this->settings = Settings::defaults($this->user);
    }

    #[Test]
    public function a_standard_day_totals_its_two_work_spans_without_penalty(): void
    {
        // Journée de référence ADP : 08:30-12:12 / 13:00-16:42 = 7 h 24, pause de 48 min.
        $fact = $this->calculate(['08:30', '12:12', '13:00', '16:42']);

        self::assertSame(444, $fact->grossPresence()->value());
        self::assertSame(444, $fact->workedMinutes()->value());
        self::assertSame(0, $fact->breakPenalty()->value());
        self::assertSame(48, $fact->breakDuration()?->value());
        self::assertTrue($fact->isClean());
    }

    #[Test]
    public function a_break_under_thirty_minutes_is_deducted_from_the_worked_time(): void
    {
        // Règle confirmée par ADP (source-adp §3.3) : pause de 26 min → −4 min.
        $fact = $this->calculate(['08:30', '12:00', '12:26', '16:00']);

        self::assertSame(424, $fact->grossPresence()->value());
        self::assertSame(26, $fact->breakDuration()?->value());
        self::assertSame(4, $fact->breakPenalty()->value());
        self::assertSame(420, $fact->workedMinutes()->value());
        self::assertTrue($fact->hasAnomaly(DailyAnomaly::PauseTropCourte));
        self::assertFalse($fact->hasAnomaly(DailyAnomaly::PauseHorsFenetre));
    }

    #[Test]
    public function a_break_exactly_thirty_minutes_costs_nothing(): void
    {
        $fact = $this->calculate(['08:30', '12:00', '12:30', '16:00']);

        self::assertSame(0, $fact->breakPenalty()->value());
        self::assertFalse($fact->hasAnomaly(DailyAnomaly::PauseTropCourte));
    }

    #[Test]
    public function a_break_starting_before_the_window_is_a_control_anomaly(): void
    {
        // Départ en pause à 11:00, avant 11:30 : défaut de pointage côté employeur.
        // La journée reste calculée (§4.2, règle de contrôle et non de calcul).
        $fact = $this->calculate(['08:00', '11:00', '11:45', '15:00']);

        self::assertTrue($fact->hasAnomaly(DailyAnomaly::PauseHorsFenetre));
        self::assertFalse($fact->hasAnomaly(DailyAnomaly::PauseTropCourte));
        self::assertSame(375, $fact->grossPresence()->value());
        self::assertSame(375, $fact->workedMinutes()->value());
    }

    #[Test]
    public function a_break_ending_after_the_window_is_a_control_anomaly(): void
    {
        $fact = $this->calculate(['08:00', '12:00', '14:30', '17:00']);

        self::assertTrue($fact->hasAnomaly(DailyAnomaly::PauseHorsFenetre));
    }

    #[Test]
    public function the_window_boundaries_are_inclusive(): void
    {
        // Pause 11:30 → 14:00 pile : dans la fenêtre, aucune anomalie.
        $fact = $this->calculate(['08:00', '11:30', '14:00', '16:00']);

        self::assertFalse($fact->hasAnomaly(DailyAnomaly::PauseHorsFenetre));
        self::assertTrue($fact->isClean());
    }

    #[Test]
    public function an_odd_number_of_punches_flags_a_missing_badge(): void
    {
        // Trois pointages : le matin s'apparie, le dernier reste orphelin.
        $fact = $this->calculate(['08:30', '12:12', '13:00']);

        self::assertTrue($fact->hasAnomaly(DailyAnomaly::BadgeageManquant));
        self::assertSame(222, $fact->grossPresence()->value());
        self::assertNull($fact->breakDuration());
    }

    #[Test]
    public function a_day_without_any_punch_is_empty_and_clean(): void
    {
        // Ni week-end, ni congé, ni oubli ne se distinguent ici : c'est l'affaire
        // du WorkDay qui connaît l'événement du jour. Le calcul seul rend 0.
        $fact = $this->calculate([]);

        self::assertSame(0, $fact->grossPresence()->value());
        self::assertSame(0, $fact->workedMinutes()->value());
        self::assertNull($fact->breakDuration());
        self::assertTrue($fact->isClean());
    }

    #[Test]
    public function a_span_whose_end_precedes_its_start_counts_zero_and_is_flagged(): void
    {
        // Défaut #4 : fin < début (poste à cheval sur minuit, ou faute de frappe).
        // On ne laisse fuir ni total négatif, ni durée inventée : 0 + drapeau.
        $fact = $this->calculate(['22:00', '02:00']);

        self::assertTrue($fact->hasAnomaly(DailyAnomaly::IntervalleNegatif));
        self::assertSame(0, $fact->grossPresence()->value());
        self::assertSame(0, $fact->workedMinutes()->value());
    }

    #[Test]
    public function punches_are_paired_in_rank_order_whatever_the_input_order(): void
    {
        // Mêmes pointages, fournis en désordre : le rang fait foi.
        $ordered = $this->calculate(['08:30', '12:12', '13:00', '16:42']);

        $shuffled = (new DailyCalculator())->calculate(
            new \DateTimeImmutable(self::DAY),
            $this->settings,
            $this->punch('13:00', 3),
            $this->punch('08:30', 1),
            $this->punch('16:42', 4),
            $this->punch('12:12', 2),
        );

        self::assertSame($ordered->workedMinutes()->value(), $shuffled->workedMinutes()->value());
        self::assertSame($ordered->grossPresence()->value(), $shuffled->grossPresence()->value());
    }

    #[Test]
    public function the_computed_date_is_normalised_to_midnight(): void
    {
        $fact = (new DailyCalculator())->calculate(
            new \DateTimeImmutable(self::DAY.' 08:30:00'),
            $this->settings,
        );

        self::assertSame('00:00:00', $fact->date()->format('H:i:s'));
    }

    #[Test]
    public function custom_settings_change_the_break_threshold_applied(): void
    {
        // Pause minimale ramenée à 20 min : une pause de 26 min ne coûte plus rien.
        $custom = Settings::defaults($this->user);
        $custom->update(
            pauseMinimale: 20,
            fenetreDebut: $this->settings->fenetreDebut()->value(),
            fenetreFin: $this->settings->fenetreFin()->value(),
            journeeReferenceContractuelle: $this->settings->journeeReferenceContractuelle()->value(),
            journeeReferenceEffective: $this->settings->journeeReferenceEffective()->value(),
            rttMax: $this->settings->rttMax()->value(),
            finApresMidiTeletravail: $this->settings->finApresMidiTeletravail()->value(),
            joursDeRepos: $this->settings->joursDeRepos(),
            quotasAnnuels: $this->settings->quotasAnnuels(),
        );

        $fact = (new DailyCalculator())->calculate(
            new \DateTimeImmutable(self::DAY),
            $custom,
            $this->punch('08:30', 1),
            $this->punch('12:00', 2),
            $this->punch('12:26', 3),
            $this->punch('16:00', 4),
        );

        self::assertSame(0, $fact->breakPenalty()->value());
        self::assertFalse($fact->hasAnomaly(DailyAnomaly::PauseTropCourte));
    }

    /**
     * @param list<string> $clocks
     */
    private function calculate(array $clocks): DayFact
    {
        $punches = [];
        foreach ($clocks as $index => $clock) {
            $punches[] = $this->punch($clock, $index + 1);
        }

        return (new DailyCalculator())->calculate(new \DateTimeImmutable(self::DAY), $this->settings, ...$punches);
    }

    private function punch(string $clock, int $rang): PunchEvent
    {
        return PunchEvent::realFromAdp($this->user, new \DateTimeImmutable(self::DAY), Minutes::fromClock($clock), $rang);
    }
}
