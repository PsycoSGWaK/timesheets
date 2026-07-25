<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\RawImport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RawImportTest extends TestCase
{
    #[Test]
    public function it_keeps_the_pasted_text_its_year_and_import_instant(): void
    {
        $payload = "23/07\n7:24h\nPointage\n08:30";

        $import = RawImport::capture($payload, 2026, new \DateTimeImmutable('2026-07-24 08:00:00'));

        self::assertSame($payload, $import->rawPayload());
        self::assertSame(2026, $import->year());
        self::assertSame('2026-07-24 08:00:00', $import->importedAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_rejects_an_empty_payload(): void
    {
        // Conserver le texte permet de rejouer le parseur ; un import vide est un bug.
        $this->expectException(\InvalidArgumentException::class);

        RawImport::capture("   \n  ", 2026, new \DateTimeImmutable('2026-07-24 08:00:00'));
    }

    #[Test]
    public function it_has_no_identity_before_persistence(): void
    {
        $import = RawImport::capture('23/07', 2026, new \DateTimeImmutable('2026-07-24 08:00:00'));

        self::assertNull($import->id());
    }
}
