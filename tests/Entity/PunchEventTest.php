<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Domain\Punch\PunchNature;
use App\Domain\Punch\PunchOrigin;
use App\Domain\Time\Minutes;
use App\Entity\PunchEvent;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PunchEventTest extends TestCase
{
    private const DAY = '2026-07-23';

    #[Test]
    public function a_punch_pasted_from_adp_is_a_real_fact(): void
    {
        $punch = PunchEvent::realFromAdp($this->user(), $this->day(), Minutes::fromClock('08:30'), 1);

        self::assertSame(PunchNature::Reel, $punch->nature());
        self::assertSame(PunchOrigin::Adp, $punch->origin());
        self::assertTrue($punch->isProbative());
    }

    #[Test]
    public function a_provisional_punch_is_a_hand_typed_hypothesis(): void
    {
        $punch = PunchEvent::provisional($this->user(), $this->day(), Minutes::fromClock('16:42'), 4);

        self::assertSame(PunchNature::Previsionnel, $punch->nature());
        self::assertSame(PunchOrigin::SaisieManuelle, $punch->origin());
        self::assertFalse($punch->isProbative());
    }

    #[Test]
    public function a_manual_correction_is_a_real_fact_entered_by_hand(): void
    {
        // Le cas soulevé par l'utilisateur : un badge réel qu'ADP a manqué et que
        // l'on comble à la main. C'est un fait (réel), mais d'origine manuelle —
        // d'où les deux dimensions distinctes.
        $punch = PunchEvent::manualCorrection($this->user(), $this->day(), Minutes::fromClock('13:00'), 3);

        self::assertSame(PunchNature::Reel, $punch->nature());
        self::assertSame(PunchOrigin::SaisieManuelle, $punch->origin());
        self::assertTrue($punch->isProbative());
        self::assertTrue($punch->origin()->isManual());
    }

    #[Test]
    public function it_exposes_its_slot_identity(): void
    {
        $user = $this->user();
        $punch = PunchEvent::realFromAdp($user, $this->day(), Minutes::fromClock('08:30'), 1);

        self::assertSame(self::DAY, $punch->date()->format('Y-m-d'));
        self::assertTrue(Minutes::fromClock('08:30')->equals($punch->time()));
        self::assertSame(1, $punch->rang());
        self::assertSame($user, $punch->user());
    }

    #[Test]
    public function it_normalises_the_date_to_midnight(): void
    {
        // Une date porteuse d'une heure ne doit pas polluer la clé de journée.
        $withTime = new \DateTimeImmutable(self::DAY.' 08:30:00');
        $punch = PunchEvent::realFromAdp($this->user(), $withTime, Minutes::fromClock('08:30'), 1);

        self::assertSame('00:00:00', $punch->date()->format('H:i:s'));
    }

    #[Test]
    public function it_has_no_identity_before_persistence(): void
    {
        $punch = PunchEvent::realFromAdp($this->user(), $this->day(), Minutes::fromClock('08:30'), 1);

        self::assertNull($punch->id());
    }

    #[Test]
    public function it_rejects_a_rank_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PunchEvent::realFromAdp($this->user(), $this->day(), Minutes::fromClock('08:30'), 0);
    }

    #[Test]
    public function it_rejects_a_time_that_is_not_a_time_of_day(): void
    {
        // Un pointage est un instant de la journée : ni négatif, ni au-delà de 23:59.
        $this->expectException(\InvalidArgumentException::class);

        PunchEvent::realFromAdp($this->user(), $this->day(), Minutes::of(1440), 1);
    }

    #[Test]
    public function two_punches_on_the_same_day_and_rank_share_a_slot(): void
    {
        // Un réel collé remplace le prévisionnel du même créneau (date + rang),
        // indépendamment de l'heure exacte. La réconciliation elle-même viendra
        // avec le parseur ; ici on n'expose que la notion de créneau.
        $user = $this->user();
        $provisional = PunchEvent::provisional($user, $this->day(), Minutes::fromClock('16:40'), 4);
        $real = PunchEvent::realFromAdp($user, $this->day(), Minutes::fromClock('16:42'), 4);

        self::assertTrue($real->isSameSlotAs($provisional));
    }

    #[Test]
    public function a_different_rank_is_a_different_slot(): void
    {
        $user = $this->user();
        $morning = PunchEvent::realFromAdp($user, $this->day(), Minutes::fromClock('08:30'), 1);
        $noon = PunchEvent::realFromAdp($user, $this->day(), Minutes::fromClock('12:12'), 2);

        self::assertFalse($morning->isSameSlotAs($noon));
    }

    #[Test]
    public function a_different_day_is_a_different_slot(): void
    {
        $user = $this->user();
        $today = PunchEvent::realFromAdp($user, $this->day(), Minutes::fromClock('08:30'), 1);
        $tomorrow = PunchEvent::realFromAdp(
            $user,
            new \DateTimeImmutable('2026-07-24'),
            Minutes::fromClock('08:30'),
            1,
        );

        self::assertFalse($today->isSameSlotAs($tomorrow));
    }

    #[Test]
    public function a_different_user_is_a_different_slot_even_on_the_same_day_and_rank(): void
    {
        // Le cloisonnement par utilisateur fait partie de l'identité du créneau :
        // les données d'un utilisateur ne doivent jamais recouvrir celles d'un autre.
        $date = $this->day();
        $mine = PunchEvent::realFromAdp($this->user('alice@example.com'), $date, Minutes::fromClock('08:30'), 1);
        $theirs = PunchEvent::realFromAdp($this->user('bob@example.com'), $date, Minutes::fromClock('08:30'), 1);

        self::assertFalse($mine->isSameSlotAs($theirs));
    }

    private function day(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::DAY);
    }

    private function user(string $email = 'guillaume@example.com'): User
    {
        return User::register($email, 'hashed-password');
    }
}
