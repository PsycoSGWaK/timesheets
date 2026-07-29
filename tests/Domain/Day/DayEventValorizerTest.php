<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayEventValorizer;
use App\Domain\Day\DayFact;
use App\Domain\Day\DayHalf;
use App\Domain\Day\DayPortion;
use App\Domain\Time\Minutes;
use App\Entity\DayEvent;
use App\Entity\PunchEvent;
use App\Entity\Settings;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DayEventValorizerTest extends TestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        $this->settings = Settings::defaults($this->user());
    }

    #[Test]
    public function a_teletravail_day_without_any_punch_is_valued_at_the_reference_day(): void
    {
        // Cas limite de la spec §6.5 : journée de télétravail sans horodatage,
        // valorisée par l'événement, pas par la présence.
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::Teletravail, DayPortion::Full);

        $valorized = (new DayEventValorizer())->valorize($fact, [], $event, $this->settings);

        self::assertSame(444, $valorized->workedMinutes()->value());
    }

    #[Test]
    public function a_full_day_congé_is_valued_at_the_contractual_reference_day(): void
    {
        // CP/CA/RTT/JF suivent la base contractuelle 7h00 (35h/5j), distincte des
        // 7h24 du TT — confirmé par Guillaume, pas la même référence que le travail réel.
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::CongePaye, DayPortion::Full);

        $valorized = (new DayEventValorizer())->valorize($fact, [], $event, $this->settings);

        self::assertSame(420, $valorized->workedMinutes()->value());
    }

    #[Test]
    public function a_half_day_congé_is_valued_at_half_the_contractual_reference_day(): void
    {
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::CongePaye, DayPortion::Half);

        $valorized = (new DayEventValorizer())->valorize($fact, [], $event, $this->settings);

        self::assertSame(210, $valorized->workedMinutes()->value());
    }

    #[Test]
    public function a_morning_teletravail_half_day_uses_the_real_arrival_and_break_return_times(): void
    {
        // Règle du 28/07/2026 : le matin part de l'arrivée réelle et s'arrête au
        // retour de pause, plafonné à la fenêtre de pause (14h00 par défaut). Ces
        // horaires sont saisis comme des pointages prévisionnels tant qu'aucun
        // pointage réel n'existe ce jour-là (spec §4.6) — le seul indice disponible.
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::Teletravail, DayPortion::Half, DayHalf::Matin);
        $punches = [
            PunchEvent::provisional($this->user(), $this->date(), Minutes::fromClock('08:30'), 1),
            PunchEvent::provisional($this->user(), $this->date(), Minutes::fromClock('12:30'), 3),
        ];

        $valorized = (new DayEventValorizer())->valorize($fact, $punches, $event, $this->settings);

        self::assertSame(240, $valorized->workedMinutes()->value()); // 08:30 -> 12:30
    }

    #[Test]
    public function an_afternoon_teletravail_half_day_uses_the_real_resume_and_end_times(): void
    {
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::Teletravail, DayPortion::Half, DayHalf::ApresMidi);
        $punches = [
            PunchEvent::provisional($this->user(), $this->date(), Minutes::fromClock('12:30'), 2),
            PunchEvent::provisional($this->user(), $this->date(), Minutes::fromClock('15:30'), 4),
        ];

        $valorized = (new DayEventValorizer())->valorize($fact, $punches, $event, $this->settings);

        self::assertSame(180, $valorized->workedMinutes()->value()); // 12:30 -> 15:30
    }

    #[Test]
    public function a_teletravail_half_day_without_known_times_falls_back_to_the_fixed_slot(): void
    {
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::Teletravail, DayPortion::Half, DayHalf::Matin);

        $valorized = (new DayEventValorizer())->valorize($fact, [], $event, $this->settings);

        self::assertSame(270, $valorized->workedMinutes()->value()); // 4h30
    }

    #[Test]
    public function a_day_with_actual_punches_is_left_untouched_even_with_an_event(): void
    {
        // Un événement ne remplace jamais un vrai décompte : il ne comble que le vide.
        $fact = new DayFact(
            $this->date(),
            Minutes::of(444),
            Minutes::of(48),
            Minutes::of(0),
            Minutes::of(444),
            [],
        );
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::Teletravail, DayPortion::Full);
        $punches = [
            PunchEvent::realFromAdp($this->user(), $this->date(), Minutes::fromClock('08:30'), 1),
            PunchEvent::realFromAdp($this->user(), $this->date(), Minutes::fromClock('12:12'), 2),
            PunchEvent::realFromAdp($this->user(), $this->date(), Minutes::fromClock('13:00'), 3),
            PunchEvent::realFromAdp($this->user(), $this->date(), Minutes::fromClock('16:42'), 4),
        ];

        $valorized = (new DayEventValorizer())->valorize($fact, $punches, $event, $this->settings);

        self::assertSame($fact, $valorized);
    }

    #[Test]
    public function a_day_without_any_event_is_left_untouched(): void
    {
        $fact = $this->emptyFact();

        $valorized = (new DayEventValorizer())->valorize($fact, [], null, $this->settings);

        self::assertSame($fact, $valorized);
    }

    #[Test]
    public function valorizing_preserves_the_other_fields_of_the_fact(): void
    {
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::JourFerie, DayPortion::Full);

        $valorized = (new DayEventValorizer())->valorize($fact, [], $event, $this->settings);

        self::assertSame($fact->date(), $valorized->date());
        self::assertTrue($valorized->isClean());
    }

    #[Test]
    public function custom_settings_change_the_valorized_amount(): void
    {
        $custom = Settings::defaults($this->user());
        $custom->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 6 * 60 + 30,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [6, 7],
            quotasAnnuels: [],
        );
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::CongePaye, DayPortion::Full);

        $valorized = (new DayEventValorizer())->valorize($fact, [], $event, $custom);

        self::assertSame(6 * 60 + 30, $valorized->workedMinutes()->value());
    }

    private function emptyFact(): DayFact
    {
        return new DayFact($this->date(), Minutes::of(0), null, Minutes::of(0), Minutes::of(0), []);
    }

    private function date(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-07-24');
    }

    private function user(): User
    {
        return User::register('guillaume@example.com', 'hashed-password');
    }
}
