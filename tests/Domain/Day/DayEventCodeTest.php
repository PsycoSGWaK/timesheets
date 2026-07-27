<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DayEventCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DayEventCodeTest extends TestCase
{
    #[Test]
    public function it_has_the_five_day_based_codes_of_the_specification(): void
    {
        // Spec §2 : les événements exprimés en jours (1 ou 0,5). Les événements en
        // heures (HS, HV, Abs) sont hors périmètre de cette tranche.
        self::assertCount(5, DayEventCode::cases());
    }

    #[Test]
    public function its_backing_values_are_stable_for_persistence(): void
    {
        self::assertSame('CP', DayEventCode::CongePaye->value);
        self::assertSame('CA', DayEventCode::CongeAnciennete->value);
        self::assertSame('RTT', DayEventCode::Rtt->value);
        self::assertSame('JF', DayEventCode::JourFerie->value);
        self::assertSame('TT', DayEventCode::Teletravail->value);
    }

    #[Test]
    public function every_case_carries_a_human_label(): void
    {
        foreach (DayEventCode::cases() as $code) {
            self::assertNotSame('', $code->label());
        }
    }
}
