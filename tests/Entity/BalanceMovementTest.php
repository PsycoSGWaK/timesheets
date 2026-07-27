<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Domain\Balance\BalanceCounter;
use App\Domain\Time\Minutes;
use App\Entity\BalanceMovement;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BalanceMovementTest extends TestCase
{
    #[Test]
    public function a_credit_carries_a_positive_amount(): void
    {
        $movement = BalanceMovement::credit(
            $this->user(),
            BalanceCounter::Rtt,
            Minutes::of(60),
            new \DateTimeImmutable('2026-07-20'),
            new \DateTimeImmutable('2026-07-27 03:00:00'),
        );

        self::assertSame(60, $movement->amount()->value());
        self::assertTrue($movement->isCredit());
    }

    #[Test]
    public function a_debit_carries_a_negative_amount(): void
    {
        $movement = BalanceMovement::debit(
            $this->user(),
            BalanceCounter::Rtt,
            Minutes::of(420),
            new \DateTimeImmutable('2026-07-24'),
            new \DateTimeImmutable('2026-07-24 09:00:00'),
        );

        self::assertSame(-420, $movement->amount()->value());
        self::assertFalse($movement->isCredit());
    }

    #[Test]
    public function it_carries_an_optional_free_text_motif(): void
    {
        $movement = BalanceMovement::credit(
            $this->user(),
            BalanceCounter::Paiement,
            Minutes::of(120),
            new \DateTimeImmutable('2026-07-20'),
            new \DateTimeImmutable('2026-07-27 03:00:00'),
            'Heures sup de la semaine du 20/07, payées sur demande',
        );

        self::assertSame('Heures sup de la semaine du 20/07, payées sur demande', $movement->motif());
    }

    #[Test]
    public function the_motif_defaults_to_null(): void
    {
        $movement = BalanceMovement::debit(
            $this->user(),
            BalanceCounter::Rtt,
            Minutes::of(420),
            new \DateTimeImmutable('2026-07-24'),
            new \DateTimeImmutable('2026-07-24 09:00:00'),
        );

        self::assertNull($movement->motif());
    }

    #[Test]
    public function it_rejects_a_negative_amount_even_for_a_credit(): void
    {
        // credit()/debit() portent le signe : un montant negatif en entree est un bug appelant.
        $this->expectException(\InvalidArgumentException::class);

        BalanceMovement::credit(
            $this->user(),
            BalanceCounter::Rtt,
            Minutes::of(-60),
            new \DateTimeImmutable('2026-07-20'),
            new \DateTimeImmutable('2026-07-27 03:00:00'),
        );
    }

    #[Test]
    public function it_has_no_identity_before_persistence(): void
    {
        $movement = BalanceMovement::credit(
            $this->user(),
            BalanceCounter::Rtt,
            Minutes::of(60),
            new \DateTimeImmutable('2026-07-20'),
            new \DateTimeImmutable('2026-07-27 03:00:00'),
        );

        self::assertNull($movement->id());
    }

    private function user(): User
    {
        return User::register('guillaume@example.com', 'hashed-password');
    }
}
