<?php

declare(strict_types=1);

namespace App\Tests\Entity;

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
    }

    #[Test]
    public function the_weekly_reference_is_five_times_the_contractual_day(): void
    {
        // 35h = 7h00 x 5 (spec §3), derive plutot que duplique.
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
        );

        self::assertSame(20, $settings->pauseMinimale()->value());
        self::assertSame(11 * 60, $settings->fenetreDebut()->value());
        self::assertSame(15 * 60, $settings->fenetreFin()->value());
        self::assertSame(6 * 60 + 30, $settings->journeeReferenceContractuelle()->value());
        self::assertSame(7 * 60, $settings->journeeReferenceEffective()->value());
        self::assertSame(3 * 60, $settings->rttMax()->value());
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
        );
    }

    private function user(): User
    {
        return User::register('guillaume@example.com', 'hashed-password');
    }
}
