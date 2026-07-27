<?php

declare(strict_types=1);

namespace App\Tests\Domain\Time;

use App\Domain\Time\Minutes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MinutesTest extends TestCase
{
    #[Test]
    public function it_exposes_its_whole_minute_value(): void
    {
        self::assertSame(444, Minutes::of(444)->value());
    }

    #[Test]
    public function it_is_built_from_hours_and_minutes(): void
    {
        // 7 h 24 = la journée de référence ADP (08:30-12:12 / 13:00-16:42).
        self::assertSame(444, Minutes::fromHoursAndMinutes(7, 24)->value());
        self::assertSame(0, Minutes::fromHoursAndMinutes(0, 0)->value());
    }

    #[Test]
    #[DataProvider('validClockStrings')]
    public function it_parses_the_adp_clock_format(string $clock, int $expected): void
    {
        self::assertSame($expected, Minutes::fromClock($clock)->value());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validClockStrings(): iterable
    {
        yield 'midnight' => ['00:00', 0];
        yield 'morning punch' => ['08:30', 510];
        yield 'end of day' => ['16:42', 1002];
        yield 'last minute of day' => ['23:59', 1439];
    }

    #[Test]
    #[DataProvider('malformedClockStrings')]
    public function it_rejects_anything_that_is_not_a_strict_HH_MM(string $malformed): void
    {
        // Défaut #3 du proto : les formats approximatifs cassaient le calcul en silence.
        // Ici on échoue bruyamment plutôt que de produire une valeur fausse.
        $this->expectException(\InvalidArgumentException::class);

        Minutes::fromClock($malformed);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedClockStrings(): iterable
    {
        yield 'proto h separator' => ['08h55'];
        yield 'missing leading zero' => ['8:05'];
        yield 'single digit minute' => ['08:5'];
        yield 'hour out of range' => ['24:00'];
        yield 'minute out of range' => ['12:60'];
        yield 'empty' => [''];
        yield 'garbage' => ['midi'];
        yield 'trailing text' => ['08:30h'];
    }

    #[Test]
    public function it_adds_and_subtracts(): void
    {
        $morning = Minutes::fromClock('11:29')->minus(Minutes::fromClock('08:30'));
        self::assertSame(179, $morning->value());

        $sum = Minutes::of(179)->plus(Minutes::of(227));
        self::assertSame(406, $sum->value());
    }

    #[Test]
    public function a_span_crossing_midnight_produces_a_representable_negative(): void
    {
        // Défaut #4 : le proto supposait fin > début et produisait des durées négatives
        // silencieuses. On veut que le négatif existe et soit détectable, pas qu'il plante.
        $span = Minutes::fromClock('01:00')->minus(Minutes::fromClock('23:00'));

        self::assertSame(-1320, $span->value());
        self::assertTrue($span->isNegative());
    }

    #[Test]
    public function a_positive_span_is_not_negative(): void
    {
        self::assertFalse(Minutes::of(0)->isNegative());
        self::assertFalse(Minutes::of(444)->isNegative());
    }

    #[Test]
    public function it_clamps_negative_values_to_zero(): void
    {
        // Pénalité de pause = max(0, 30 − durée). Une pause de 31 min ne pénalise rien.
        $penalty = Minutes::of(30)->minus(Minutes::of(31))->clampToZero();
        self::assertSame(0, $penalty->value());

        // Une pause de 26 min coûte 4 min (cas confirmé par ADP dans source-adp.md §3.3).
        $penalty = Minutes::of(30)->minus(Minutes::of(26))->clampToZero();
        self::assertSame(4, $penalty->value());
    }

    #[Test]
    public function it_keeps_the_smaller_of_two_values(): void
    {
        self::assertSame(420, Minutes::of(420)->min(Minutes::of(444))->value());
        self::assertSame(420, Minutes::of(444)->min(Minutes::of(420))->value());
    }

    #[Test]
    public function it_keeps_the_larger_of_two_values(): void
    {
        self::assertSame(444, Minutes::of(420)->max(Minutes::of(444))->value());
        self::assertSame(444, Minutes::of(444)->max(Minutes::of(420))->value());
    }

    #[Test]
    public function equality_is_by_value(): void
    {
        self::assertTrue(Minutes::of(444)->equals(Minutes::fromHoursAndMinutes(7, 24)));
        self::assertFalse(Minutes::of(444)->equals(Minutes::of(445)));
    }

    #[Test]
    public function it_renders_a_time_of_day_as_zero_padded_HH_MM(): void
    {
        self::assertSame('16:42', Minutes::of(1002)->toClock());
        self::assertSame('08:30', Minutes::fromHoursAndMinutes(8, 30)->toClock());
        self::assertSame('00:00', Minutes::of(0)->toClock());
    }

    #[Test]
    public function it_refuses_to_render_a_negative_or_overflowing_time_of_day(): void
    {
        $this->expectException(\LogicException::class);

        Minutes::of(-1)->toClock();
    }
}
