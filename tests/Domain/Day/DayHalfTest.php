<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DayHalf;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DayHalfTest extends TestCase
{
    #[Test]
    public function its_backing_values_are_stable_for_persistence(): void
    {
        self::assertSame('matin', DayHalf::Matin->value);
        self::assertSame('apres_midi', DayHalf::ApresMidi->value);
    }

    #[Test]
    public function it_exposes_a_readable_label(): void
    {
        self::assertSame('Matin', DayHalf::Matin->label());
        self::assertSame('Après-midi', DayHalf::ApresMidi->label());
    }
}
