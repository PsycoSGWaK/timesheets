<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\MinutesExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MinutesExtensionTest extends TestCase
{
    #[Test]
    #[DataProvider('durations')]
    public function it_formats_minutes_as_hours_and_minutes(int $minutes, string $expected): void
    {
        self::assertSame($expected, (new MinutesExtension())->formatDuration($minutes));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function durations(): iterable
    {
        yield 'reference day' => [444, '7h24'];
        yield 'zero' => [0, '0h00'];
        yield 'round hour' => [420, '7h00'];
        yield 'negative delta' => [-44, '-0h44'];
        yield 'large week total' => [2220, '37h00'];
    }
}
