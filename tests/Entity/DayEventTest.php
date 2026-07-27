<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayPortion;
use App\Entity\DayEvent;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DayEventTest extends TestCase
{
    #[Test]
    public function it_declares_a_full_day_event_by_default(): void
    {
        $user = $this->user();
        $date = new \DateTimeImmutable('2026-07-24');

        $event = DayEvent::declare($user, $date, DayEventCode::Teletravail);

        self::assertSame($user, $event->user());
        self::assertSame('2026-07-24', $event->date()->format('Y-m-d'));
        self::assertSame(DayEventCode::Teletravail, $event->code());
        self::assertSame(DayPortion::Full, $event->portion());
    }

    #[Test]
    public function it_accepts_a_half_day_portion(): void
    {
        $event = DayEvent::declare($this->user(), new \DateTimeImmutable('2026-07-24'), DayEventCode::CongePaye, DayPortion::Half);

        self::assertSame(DayPortion::Half, $event->portion());
    }

    #[Test]
    public function it_normalises_the_date_to_midnight(): void
    {
        $event = DayEvent::declare(
            $this->user(),
            new \DateTimeImmutable('2026-07-24 14:00:00'),
            DayEventCode::JourFerie,
        );

        self::assertSame('00:00:00', $event->date()->format('H:i:s'));
    }

    #[Test]
    public function it_has_no_identity_before_persistence(): void
    {
        $event = DayEvent::declare($this->user(), new \DateTimeImmutable('2026-07-24'), DayEventCode::Rtt);

        self::assertNull($event->id());
    }

    private function user(): User
    {
        return User::register('guillaume@example.com', 'hashed-password');
    }
}
