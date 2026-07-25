<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Domain\Time\Minutes;
use App\Entity\EmployerReading;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmployerReadingTest extends TestCase
{
    #[Test]
    public function it_records_the_employer_total_for_a_day(): void
    {
        $reading = EmployerReading::record(
            new \DateTimeImmutable('2026-07-23'),
            Minutes::fromHoursAndMinutes(7, 24),
            new \DateTimeImmutable('2026-07-24 03:12:00'),
        );

        self::assertSame('2026-07-23', $reading->date()->format('Y-m-d'));
        self::assertSame(444, $reading->employerMinutes()->value());
        self::assertSame('2026-07-24 03:12:00', $reading->observedAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function a_zero_total_is_a_valid_reading(): void
    {
        // Le cas 0:00 : une observation réelle à conserver, jamais une absence.
        $reading = EmployerReading::record(
            new \DateTimeImmutable('2026-07-23'),
            Minutes::of(0),
            new \DateTimeImmutable('2026-07-24 03:00:00'),
        );

        self::assertSame(0, $reading->employerMinutes()->value());
    }

    #[Test]
    public function it_rejects_a_negative_total(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EmployerReading::record(
            new \DateTimeImmutable('2026-07-23'),
            Minutes::of(-1),
            new \DateTimeImmutable('2026-07-24 03:00:00'),
        );
    }

    #[Test]
    public function it_normalises_the_day_to_midnight_but_keeps_the_observation_instant(): void
    {
        $reading = EmployerReading::record(
            new \DateTimeImmutable('2026-07-23 09:00:00'),
            Minutes::fromHoursAndMinutes(7, 24),
            new \DateTimeImmutable('2026-07-24 03:12:45'),
        );

        self::assertSame('00:00:00', $reading->date()->format('H:i:s'));
        self::assertSame('03:12:45', $reading->observedAt()->format('H:i:s'));
    }

    #[Test]
    public function it_has_no_identity_before_persistence(): void
    {
        $reading = EmployerReading::record(
            new \DateTimeImmutable('2026-07-23'),
            Minutes::of(0),
            new \DateTimeImmutable('2026-07-24 03:00:00'),
        );

        self::assertNull($reading->id());
    }
}
