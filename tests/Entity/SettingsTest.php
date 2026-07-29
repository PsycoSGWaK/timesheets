<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Domain\Day\DayEventCode;
use App\Entity\Settings;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    #[Test]
    public function default_settings_match_todays_hardcoded_values(): void
    {
        $settings = Settings::defaults($this->user());

        self::assertSame(30, $settings->pauseMinimale()->value());
        self::assertSame(11 * 60 + 30, $settings->fenetreDebut()->value());
        self::assertSame(14 * 60, $settings->fenetreFin()->value());
        self::assertSame(7 * 60, $settings->journeeReferenceContractuelle()->value());
        self::assertSame(7 * 60 + 24, $settings->journeeReferenceEffective()->value());
        self::assertSame(2 * 60, $settings->rttMax()->value());
        self::assertSame(16 * 60, $settings->finApresMidiTeletravail()->value());
        self::assertSame([6, 7], $settings->joursDeRepos());
        self::assertSame(0, $settings->quotaAnnuel(DayEventCode::CongePaye)->halfDays());
    }

    #[Test]
    public function the_weekly_reference_is_five_times_the_contractual_day(): void
    {
        // 35h = 7h00 x 5 (spec §3), derive plutot que duplique : 5 jours ouvres car
        // samedi + dimanche sont les jours de repos par defaut.
        $settings = Settings::defaults($this->user());

        self::assertSame(35 * 60, $settings->weeklyReference()->value());
    }

    #[Test]
    public function the_weekly_bascule_is_five_times_the_effective_day(): void
    {
        // 37h = 7h24 x 5 (confirme par l'horaire theorique ADP, source-adp §3.2).
        $settings = Settings::defaults($this->user());

        self::assertSame(37 * 60, $settings->weeklyBascule()->value());
    }

    #[Test]
    public function the_weekly_thresholds_shrink_with_more_rest_days(): void
    {
        // 4 jours ouvres (mer/sam/dim de repos) : 35h -> 28h, la reference suit.
        $settings = Settings::defaults($this->user());
        $settings->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [3, 6, 7],
            quotasAnnuels: [],
        );

        self::assertSame(4, $settings->joursOuvresParSemaine());
        self::assertSame(28 * 60, $settings->weeklyReference()->value());
        self::assertSame(4 * (7 * 60 + 24), $settings->weeklyBascule()->value());
    }

    #[Test]
    public function it_belongs_to_its_user(): void
    {
        $user = $this->user();
        $settings = Settings::defaults($user);

        self::assertSame($user, $settings->user());
    }

    #[Test]
    public function it_has_no_identity_before_persistence(): void
    {
        self::assertNull(Settings::defaults($this->user())->id());
    }

    #[Test]
    public function it_can_be_updated_in_place(): void
    {
        $settings = Settings::defaults($this->user());

        $settings->update(
            pauseMinimale: 20,
            fenetreDebut: 11 * 60,
            fenetreFin: 15 * 60,
            journeeReferenceContractuelle: 6 * 60 + 30,
            journeeReferenceEffective: 7 * 60,
            rttMax: 3 * 60,
            finApresMidiTeletravail: 17 * 60,
            joursDeRepos: [7],
            quotasAnnuels: [],
        );

        self::assertSame(20, $settings->pauseMinimale()->value());
        self::assertSame(11 * 60, $settings->fenetreDebut()->value());
        self::assertSame(15 * 60, $settings->fenetreFin()->value());
        self::assertSame(6 * 60 + 30, $settings->journeeReferenceContractuelle()->value());
        self::assertSame(7 * 60, $settings->journeeReferenceEffective()->value());
        self::assertSame(3 * 60, $settings->rttMax()->value());
        self::assertSame(17 * 60, $settings->finApresMidiTeletravail()->value());
        self::assertSame([7], $settings->joursDeRepos());
    }

    #[Test]
    public function annual_quotas_can_be_set_and_read_back(): void
    {
        $settings = Settings::defaults($this->user());

        $settings->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [6, 7],
            quotasAnnuels: ['CP' => 50, 'TT' => 97],
        );

        self::assertSame(50, $settings->quotaAnnuel(DayEventCode::CongePaye)->halfDays());
        self::assertSame(97, $settings->quotaAnnuel(DayEventCode::Teletravail)->halfDays());
        // Non configuré explicitement : vaut zéro, pas une erreur.
        self::assertSame(0, $settings->quotaAnnuel(DayEventCode::Rtt)->halfDays());
    }

    #[Test]
    public function it_rejects_a_negative_annual_quota(): void
    {
        $settings = Settings::defaults($this->user());

        $this->expectException(\InvalidArgumentException::class);

        $settings->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [6, 7],
            quotasAnnuels: ['CP' => -1],
        );
    }

    #[Test]
    public function it_rejects_a_quota_for_a_code_without_annual_quota(): void
    {
        // CA (congé ancienneté) n'a volontairement pas de quota (spec du 29/07/2026).
        $settings = Settings::defaults($this->user());

        $this->expectException(\InvalidArgumentException::class);

        $settings->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [6, 7],
            quotasAnnuels: ['CA' => 10],
        );
    }

    #[Test]
    public function it_rejects_a_negative_value(): void
    {
        $settings = Settings::defaults($this->user());

        $this->expectException(\InvalidArgumentException::class);

        $settings->update(
            pauseMinimale: -1,
            fenetreDebut: 11 * 60,
            fenetreFin: 15 * 60,
            journeeReferenceContractuelle: 6 * 60,
            journeeReferenceEffective: 7 * 60,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [6, 7],
            quotasAnnuels: [],
        );
    }

    #[Test]
    public function it_rejects_a_break_window_that_ends_before_it_starts(): void
    {
        $settings = Settings::defaults($this->user());

        $this->expectException(\InvalidArgumentException::class);

        $settings->update(
            pauseMinimale: 30,
            fenetreDebut: 14 * 60,
            fenetreFin: 11 * 60 + 30,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [6, 7],
            quotasAnnuels: [],
        );
    }

    #[Test]
    public function it_rejects_a_teletravail_afternoon_end_before_the_break_window_starts(): void
    {
        // La fin de demi-journée TT après-midi doit rester après le début de la
        // fenêtre de pause (11h30 par défaut) : sinon la borne "reprise → fin" du
        // calcul TT (spec du 28/07/2026) n'a plus de sens.
        $settings = Settings::defaults($this->user());

        $this->expectException(\InvalidArgumentException::class);

        $settings->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 11 * 60,
            joursDeRepos: [6, 7],
            quotasAnnuels: [],
        );
    }

    #[Test]
    public function it_rejects_a_weekday_outside_the_iso_range(): void
    {
        $settings = Settings::defaults($this->user());

        $this->expectException(\InvalidArgumentException::class);

        $settings->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [0, 6],
            quotasAnnuels: [],
        );
    }

    #[Test]
    public function it_rejects_every_day_of_the_week_being_a_rest_day(): void
    {
        // Au moins un jour ouvré, sinon la référence hebdomadaire tombe à zéro.
        $settings = Settings::defaults($this->user());

        $this->expectException(\InvalidArgumentException::class);

        $settings->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [1, 2, 3, 4, 5, 6, 7],
            quotasAnnuels: [],
        );
    }

    #[Test]
    public function duplicate_weekdays_are_deduplicated(): void
    {
        $settings = Settings::defaults($this->user());

        $settings->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [6, 6, 7, 7],
            quotasAnnuels: [],
        );

        self::assertSame([6, 7], $settings->joursDeRepos());
    }

    private function user(): User
    {
        return User::register('guillaume@example.com', 'hashed-password');
    }
}
