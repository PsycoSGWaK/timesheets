<?php

declare(strict_types=1);

namespace App\Tests\Domain\Punch;

use App\Domain\Punch\PunchNature;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PunchNatureTest extends TestCase
{
    #[Test]
    public function it_has_exactly_two_natures(): void
    {
        self::assertCount(2, PunchNature::cases());
    }

    #[Test]
    public function its_backing_values_are_stable_for_persistence(): void
    {
        // Ces chaînes finissent en base : elles ne doivent pas changer par accident.
        self::assertSame('reel', PunchNature::Reel->value);
        self::assertSame('previsionnel', PunchNature::Previsionnel->value);
    }

    #[Test]
    public function only_the_real_nature_is_probative(): void
    {
        // Le réel est un fait ; le prévisionnel n'est qu'une hypothèse de projection.
        self::assertTrue(PunchNature::Reel->isProbative());
        self::assertFalse(PunchNature::Previsionnel->isProbative());
    }

    #[Test]
    public function real_supersedes_provisional_but_never_the_reverse(): void
    {
        // Un pointage réel collé depuis ADP remplace le prévisionnel du même créneau.
        // L'inverse ne doit jamais arriver : une hypothèse n'efface pas un fait.
        self::assertTrue(PunchNature::Reel->supersedes(PunchNature::Previsionnel));

        self::assertFalse(PunchNature::Reel->supersedes(PunchNature::Reel));
        self::assertFalse(PunchNature::Previsionnel->supersedes(PunchNature::Reel));
        self::assertFalse(PunchNature::Previsionnel->supersedes(PunchNature::Previsionnel));
    }

    #[Test]
    public function it_carries_a_human_label(): void
    {
        self::assertSame('Réel', PunchNature::Reel->label());
        self::assertSame('Prévisionnel', PunchNature::Previsionnel->label());
    }
}
