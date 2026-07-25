<?php

declare(strict_types=1);

namespace App\Tests\Domain\Punch;

use App\Domain\Punch\PunchOrigin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PunchOriginTest extends TestCase
{
    #[Test]
    public function it_has_exactly_two_origins(): void
    {
        self::assertCount(2, PunchOrigin::cases());
    }

    #[Test]
    public function its_backing_values_are_stable_for_persistence(): void
    {
        self::assertSame('adp', PunchOrigin::Adp->value);
        self::assertSame('saisie_manuelle', PunchOrigin::SaisieManuelle->value);
    }

    #[Test]
    public function only_the_manual_origin_is_a_human_entry(): void
    {
        // Sert au détecteur d'écart : un pointage saisi à la main sur un créneau
        // qu'ADP laisse vide doit être signalé (« trou comblé à la main »).
        self::assertTrue(PunchOrigin::SaisieManuelle->isManual());
        self::assertFalse(PunchOrigin::Adp->isManual());
    }

    #[Test]
    public function it_carries_a_human_label(): void
    {
        self::assertSame('ADP', PunchOrigin::Adp->label());
        self::assertSame('Saisie manuelle', PunchOrigin::SaisieManuelle->label());
    }
}
