<?php

declare(strict_types=1);

namespace App\Domain\Adp;

use App\Domain\Time\Minutes;

/**
 * Analyse le texte copié depuis la « Fiche de présence » de MonADP.
 *
 * La pointeuse n'offre aucun export : la seule extraction est une sélection de texte
 * collée dans le presse-papier (source-adp §1). Ce parseur en applique la grammaire
 * (§2) : nettoyage du bruit d'interface, découpage en blocs jour, et extraction du
 * total ADP, des pointages et du libellé d'événement.
 *
 * L'année n'apparaît pas dans le texte : elle est fournie par l'appelant.
 */
final class AdpParser
{
    private const DAY_HEADERS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    private const NOISE = ['Afficher', 'Pointage'];

    private const DATE_LINE = '#^(\d{2})/(\d{2})$#';
    private const TOTAL_LINE = '#^(\d{1,2}):(\d{2})h$#';
    private const PUNCH_LINE = '#^\d{2}:\d{2}$#';
    private const ATTENDU = 'Attendu';

    public function parse(string $clipboard, int $year): ParsedWeek
    {
        $days = [];
        foreach ($this->splitIntoDayBlocks($this->clean($clipboard)) as $block) {
            $days[] = $this->parseBlock($block, $year);
        }

        return new ParsedWeek($year, $days);
    }

    /**
     * Nettoie le texte : espaces insécables, lignes vides, en-têtes de jour et bruit
     * d'interface (« Afficher », « Pointage ») disparaissent (§2, règles de découpage).
     *
     * @return list<string>
     */
    private function clean(string $clipboard): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $clipboard) ?: [] as $raw) {
            $line = trim(str_replace("\u{00A0}", ' ', $raw));

            if ('' === $line
                || \in_array($line, self::NOISE, true)
                || \in_array($line, self::DAY_HEADERS, true)) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Découpe la liste de lignes en blocs jour : un nouveau bloc commence à chaque
     * ligne « JJ/MM ». Ce qui précède le premier bloc (en-tête de période, résumé)
     * est ignoré.
     *
     * @param list<string> $lines
     *
     * @return list<list<string>>
     */
    private function splitIntoDayBlocks(array $lines): array
    {
        $blocks = [];
        $current = null;

        foreach ($lines as $line) {
            if (1 === preg_match(self::DATE_LINE, $line)) {
                if (null !== $current) {
                    $blocks[] = $current;
                }
                $current = [$line];
            } elseif (null !== $current) {
                $current[] = $line;
            }
        }

        if (null !== $current) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * @param list<string> $block
     */
    private function parseBlock(array $block, int $year): ParsedDay
    {
        $date = $this->parseDate($block[0], $year);

        $rest = \array_slice($block, 1);
        $attenduPos = array_search(self::ATTENDU, $rest, true);
        // Seuls les horodatages situés avant « Attendu » sont des pointages ; ceux
        // qui suivent appartiennent à l'horaire théorique (§2, règle de découpage 4).
        $beforeAttendu = false === $attenduPos ? $rest : \array_slice($rest, 0, $attenduPos);

        $total = null;
        $punchTimes = [];
        $eventLines = [];

        foreach ($beforeAttendu as $line) {
            if (1 === preg_match(self::TOTAL_LINE, $line, $matches)) {
                $total = Minutes::fromHoursAndMinutes((int) $matches[1], (int) $matches[2]);
            } elseif (1 === preg_match(self::PUNCH_LINE, $line)) {
                $punchTimes[] = Minutes::fromClock($line);
            } else {
                $eventLines[] = $line;
            }
        }

        return new ParsedDay(
            $date,
            $total,
            [] === $eventLines ? null : implode(' — ', $eventLines),
            $punchTimes,
        );
    }

    private function parseDate(string $line, int $year): \DateTimeImmutable
    {
        if (1 !== preg_match(self::DATE_LINE, $line, $matches)) {
            throw new \InvalidArgumentException(sprintf('Ligne de date illisible : "%s".', $line));
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];

        if (!checkdate($month, $day, $year)) {
            throw new \InvalidArgumentException(
                sprintf('Date impossible : %02d/%02d/%d.', $day, $month, $year),
            );
        }

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
