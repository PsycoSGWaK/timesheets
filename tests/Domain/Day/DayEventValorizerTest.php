<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayEventValorizer;
use App\Domain\Day\DayFact;
use App\Domain\Day\DayPortion;
use App\Domain\Time\Minutes;
use App\Entity\DayEvent;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DayEventValorizerTest extends TestCase
{
    #[Test]
    public function a_teletravail_day_without_any_punch_is_valued_at_the_reference_day(): void
    {
        // Cas limite de la spec §6.5 : journée de télétravail sans horodatage,
        // valorisée par l'événement, pas par la présence.
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::Teletravail, DayPortion::Full);

        $valorized = (new DayEventValorizer())->valorize($fact, 0, $event);

        self::assertSame(444, $valorized->workedMinutes()->value());
    }

    #[Test]
    public function a_full_day_congé_is_valued_at_the_contractual_reference_day(): void
    {
        // CP/CA/RTT/JF suivent la base contractuelle 7h00 (35h/5j), distincte des
        // 7h24 du TT — confirmé par Guillaume, pas la même référence que le travail réel.
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::CongePaye, DayPortion::Full);

        $valorized = (new DayEventValorizer())->valorize($fact, 0, $event);

        self::assertSame(420, $valorized->workedMinutes()->value());
    }

    #[Test]
    public function a_half_day_congé_is_valued_at_half_the_contractual_reference_day(): void
    {
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::CongePaye, DayPortion::Half);

        $valorized = (new DayEventValorizer())->valorize($fact, 0, $event);

        self::assertSame(210, $valorized->workedMinutes()->value());
    }

    #[Test]
    public function a_half_day_teletravail_currently_halves_its_own_reference_day(): void
    {
        // Comportement provisoire : le TT en demi-journée dépend en réalité d'horaires
        // réels (matin jusqu'au retour de pause, ou 11h30-16h l'après-midi), pas d'une
        // simple moitié. Report explicite à une tranche dédiée (choix de Guillaume) ;
        // en attendant, on garde la moitié de la référence TT (7h24 / 2 = 3h42).
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::Teletravail, DayPortion::Half);

        $valorized = (new DayEventValorizer())->valorize($fact, 0, $event);

        self::assertSame(222, $valorized->workedMinutes()->value());
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

        $valorized = (new DayEventValorizer())->valorize($fact, 4, $event);

        self::assertSame($fact, $valorized);
    }

    #[Test]
    public function a_day_without_any_event_is_left_untouched(): void
    {
        $fact = $this->emptyFact();

        $valorized = (new DayEventValorizer())->valorize($fact, 0, null);

        self::assertSame($fact, $valorized);
    }

    #[Test]
    public function valorizing_preserves_the_other_fields_of_the_fact(): void
    {
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::JourFerie, DayPortion::Full);

        $valorized = (new DayEventValorizer())->valorize($fact, 0, $event);

        self::assertSame($fact->date(), $valorized->date());
        self::assertTrue($valorized->isClean());
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
