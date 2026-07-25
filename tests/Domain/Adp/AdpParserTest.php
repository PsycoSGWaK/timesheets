<?php

declare(strict_types=1);

namespace App\Tests\Domain\Adp;

use App\Domain\Adp\AdpParser;
use App\Domain\Adp\ParsedDay;
use App\Domain\Time\Minutes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdpParserTest extends TestCase
{
    #[Test]
    public function it_parses_a_standard_telework_day(): void
    {
        $paste = <<<TXT
            Lun
            Mar
            Mer
            Jeu
            Ven
            Sam
            Dim
            23/07

            7:24h
            Télétravail - En attente
            Journée complète
            Afficher
            Pointage
            08:30
            Afficher
            Pointage
            12:12
            Afficher
            Pointage
            13:00
            Afficher
            Pointage
            16:42
            Attendu
            08:30 - 12:12
            13:00 - 16:42
            TXT;

        $week = (new AdpParser())->parse($paste, 2026);

        self::assertSame(1, $week->dayCount());
        $day = $week->days()[0];

        self::assertSame('2026-07-23', $day->date()->format('Y-m-d'));
        self::assertSame(444, $day->adpTotal()?->value());
        self::assertStringContainsString('Télétravail', (string) $day->eventLabel());
        self::assertSame([510, 732, 780, 1002], $this->punchValues($day));
    }

    #[Test]
    public function the_zero_total_is_kept_as_a_real_reading_not_a_null(): void
    {
        // Le cas 0:00 : ADP annonce 0 alors que quatre pointages existent. Ce zéro
        // est une observation réelle à conserver pour la réconciliation, pas une absence.
        $paste = <<<TXT
            23/07
            0:00h
            Journée complète
            Pointage
            08:30
            Pointage
            12:00
            Pointage
            12:30
            Pointage
            16:00
            Attendu
            08:30 - 12:12
            13:00 - 16:42
            TXT;

        $day = (new AdpParser())->parse($paste, 2026)->days()[0];

        self::assertNotNull($day->adpTotal());
        self::assertSame(0, $day->adpTotal()->value());
        self::assertSame([510, 720, 750, 960], $this->punchValues($day));
    }

    #[Test]
    public function a_rest_day_has_no_total_and_no_punch(): void
    {
        $paste = <<<TXT
            26/07
            Attendu
            Repos
            TXT;

        $day = (new AdpParser())->parse($paste, 2026)->days()[0];

        self::assertNull($day->adpTotal());
        self::assertNull($day->eventLabel());
        self::assertSame([], $this->punchValues($day));
    }

    #[Test]
    public function an_odd_number_of_punches_is_carried_as_is(): void
    {
        // Le parseur ne juge pas : il restitue les 3 pointages tels quels.
        // La détection du badgeage manquant appartient au calcul journalier.
        $paste = <<<TXT
            23/07
            5:30h
            Pointage
            08:30
            Pointage
            12:12
            Pointage
            13:00
            Attendu
            08:30 - 12:12
            13:00 - 16:42
            TXT;

        $day = (new AdpParser())->parse($paste, 2026)->days()[0];

        self::assertSame([510, 732, 780], $this->punchValues($day));
    }

    #[Test]
    public function timestamps_after_the_attendu_keyword_are_not_punches(): void
    {
        // Les HH:MM de l'horaire théorique suivent « Attendu » : ce ne sont pas des pointages.
        $paste = <<<TXT
            23/07
            7:24h
            Pointage
            08:30
            Pointage
            16:42
            Attendu
            08:30 - 12:12
            13:00 - 16:42
            TXT;

        $day = (new AdpParser())->parse($paste, 2026)->days()[0];

        self::assertSame([510, 1002], $this->punchValues($day));
    }

    #[Test]
    public function it_splits_several_day_blocks(): void
    {
        $paste = <<<TXT
            20/07
            7:24h
            Pointage
            08:30
            Pointage
            16:42
            Attendu
            08:30 - 16:42
            21/07
            7:24h
            Pointage
            08:00
            Pointage
            16:12
            Attendu
            08:00 - 16:12
            TXT;

        $week = (new AdpParser())->parse($paste, 2026);

        self::assertSame(2, $week->dayCount());
        self::assertSame('2026-07-20', $week->days()[0]->date()->format('Y-m-d'));
        self::assertSame('2026-07-21', $week->days()[1]->date()->format('Y-m-d'));
    }

    #[Test]
    public function it_applies_the_provided_year(): void
    {
        $paste = "23/07\nAttendu\nRepos";

        $day = (new AdpParser())->parse($paste, 2025)->days()[0];

        self::assertSame('2025-07-23', $day->date()->format('Y-m-d'));
    }

    #[Test]
    public function it_strips_non_breaking_spaces_and_interface_noise(): void
    {
        // Ligne réduite à une espace insécable + lignes « Afficher »/« Pointage » : du bruit.
        $paste = "23/07\n\u{00A0}\n7:24h\nAfficher\nPointage\n08:30\nAfficher\nPointage\n16:42\nAttendu\n08:30 - 16:42";

        $day = (new AdpParser())->parse($paste, 2026)->days()[0];

        self::assertSame(444, $day->adpTotal()?->value());
        self::assertSame([510, 1002], $this->punchValues($day));
    }

    #[Test]
    public function it_rejects_an_impossible_date(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new AdpParser())->parse("45/13\nAttendu\nRepos", 2026);
    }

    /**
     * @return list<int>
     */
    private function punchValues(ParsedDay $day): array
    {
        return array_map(static fn (Minutes $m): int => $m->value(), $day->punchTimes());
    }
}
