<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DayHalf;
use App\Domain\Day\TeletravailHalfDayCalculator;
use App\Domain\Time\Minutes;
use App\Entity\PunchEvent;
use App\Entity\Settings;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TeletravailHalfDayCalculatorTest extends TestCase
{
    private Settings $settings;
    private User $user;

    protected function setUp(): void
    {
        $this->user = User::register('guillaume@example.com', 'hashed-password');
        // fenêtre de pause 11h30-14h00, fin d'après-midi TT 16h00 (défauts).
        $this->settings = Settings::defaults($this->user);
    }

    #[Test]
    public function a_morning_half_day_counts_from_arrival_to_the_break_return(): void
    {
        $punches = $this->byRang([1 => '08:30', 3 => '12:30']);

        $worked = (new TeletravailHalfDayCalculator())->compute(DayHalf::Matin, $punches, $this->settings);

        self::assertSame(240, $worked->value()); // 08:30 -> 12:30
    }

    #[Test]
    public function a_morning_half_day_is_capped_at_the_break_window_start(): void
    {
        // Retour de pause à 15h00 : plafonné à 14h00 (fenêtreFin par défaut).
        $punches = $this->byRang([1 => '08:00', 3 => '15:00']);

        $worked = (new TeletravailHalfDayCalculator())->compute(DayHalf::Matin, $punches, $this->settings);

        self::assertSame(360, $worked->value()); // 08:00 -> 14:00
    }

    #[Test]
    public function a_morning_half_day_without_known_times_falls_back_to_the_afternoon_fixed_slot(): void
    {
        $worked = (new TeletravailHalfDayCalculator())->compute(DayHalf::Matin, [], $this->settings);

        self::assertSame(270, $worked->value()); // 4h30, comme l'après-midi fixe
    }

    #[Test]
    public function a_morning_half_day_with_only_the_arrival_known_falls_back(): void
    {
        $punches = $this->byRang([1 => '08:30']);

        $worked = (new TeletravailHalfDayCalculator())->compute(DayHalf::Matin, $punches, $this->settings);

        self::assertSame(270, $worked->value());
    }

    #[Test]
    public function an_afternoon_half_day_counts_from_the_resume_to_the_end_of_day(): void
    {
        $punches = $this->byRang([2 => '12:30', 4 => '15:30']);

        $worked = (new TeletravailHalfDayCalculator())->compute(DayHalf::ApresMidi, $punches, $this->settings);

        self::assertSame(180, $worked->value()); // 12:30 -> 15:30
    }

    #[Test]
    public function an_afternoon_half_day_is_bounded_by_the_fixed_window(): void
    {
        // Reprise à 11h00 (avant 11h30) et fin à 17h00 (après 16h00) : les deux bornées.
        $punches = $this->byRang([2 => '11:00', 4 => '17:00']);

        $worked = (new TeletravailHalfDayCalculator())->compute(DayHalf::ApresMidi, $punches, $this->settings);

        self::assertSame(270, $worked->value()); // 11:30 -> 16:00
    }

    #[Test]
    public function an_afternoon_half_day_without_known_times_falls_back_to_the_fixed_window(): void
    {
        $worked = (new TeletravailHalfDayCalculator())->compute(DayHalf::ApresMidi, [], $this->settings);

        self::assertSame(270, $worked->value()); // 11h30 -> 16h00
    }

    #[Test]
    public function custom_settings_change_the_fallback_and_the_caps(): void
    {
        $custom = Settings::defaults($this->user);
        $custom->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60,
            fenetreFin: 13 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 17 * 60,
            joursDeRepos: [6, 7],
            quotasAnnuels: [],
        );

        $worked = (new TeletravailHalfDayCalculator())->compute(DayHalf::ApresMidi, [], $custom);

        self::assertSame(360, $worked->value()); // 11h00 -> 17h00
    }

    /**
     * @param array<int, string> $clocksByRang
     *
     * @return array<int, PunchEvent>
     */
    private function byRang(array $clocksByRang): array
    {
        $punches = [];
        foreach ($clocksByRang as $rang => $clock) {
            $punches[$rang] = PunchEvent::provisional($this->user, new \DateTimeImmutable('2026-07-24'), Minutes::fromClock($clock), $rang);
        }

        return $punches;
    }
}
