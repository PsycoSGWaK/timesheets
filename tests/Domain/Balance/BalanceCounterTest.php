<?php

declare(strict_types=1);

namespace App\Tests\Domain\Balance;

use App\Domain\Balance\BalanceCounter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BalanceCounterTest extends TestCase
{
    #[Test]
    public function it_has_exactly_the_three_documented_counters(): void
    {
        // Spec §2 : Dispo, Transfert et Boni sont hors périmètre, leur alimentation
        // n'étant pas documentée (§8.3) — confirmé par Guillaume le 28/07/2026.
        // Variable a existé un temps comme troisième destin d'une heure sup, retiré
        // le 31/07/2026.
        self::assertCount(3, BalanceCounter::cases());
    }

    #[Test]
    public function its_backing_values_are_stable_for_persistence(): void
    {
        self::assertSame('rtt', BalanceCounter::Rtt->value);
        self::assertSame('recuperation', BalanceCounter::Recuperation->value);
        self::assertSame('paiement', BalanceCounter::Paiement->value);
    }

    #[Test]
    public function every_case_carries_a_human_label(): void
    {
        foreach (BalanceCounter::cases() as $counter) {
            self::assertNotSame('', $counter->label());
        }
    }
}
