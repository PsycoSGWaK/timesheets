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
    public function a_half_day_congé_is_valued_at_half_the_reference_day(): void
    {
        $fact = $this->emptyFact();
        $event = DayEvent::declare($this->user(), $this->date(), DayEventCode::CongePaye, DayPortion::Half);

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
