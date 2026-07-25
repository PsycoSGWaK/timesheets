<?php

declare(strict_types=1);

namespace App\Domain\Week;

use App\Domain\Day\DayFact;
use App\Domain\Time\Minutes;

/**
 * Agrège les journées d'une même semaine ISO et applique la règle d'acquisition
 * des RTT (spec §4.3), qui se joue sur le total hebdomadaire et non sur la journée :
 *
 *   - ≤ 35 h            : rien ;
 *   - 35 h → 37 h       : le surplus alimente le compteur RTT, plafonné à 2 h ;
 *   - > 37 h            : 2 h en RTT, et tout ce qui dépasse 37 h devient des heures sup.
 *
 * Les seuils sont ceux du paramétrage 2025 ; ils rejoindront l'entité de paramétrage
 * le moment venu, comme ceux du calcul journalier.
 */
final class WeeklyCalculator
{
    private const SEUIL_REFERENCE = 35 * 60; // 35 h
    private const SEUIL_BASCULE = 37 * 60;   // 37 h
    private const RTT_MAX = 2 * 60;          // 2 h

    public function aggregate(DayFact ...$days): WeekFact
    {
        [$isoYear, $isoWeek] = $this->resolveIsoWeek(...$days);

        $worked = 0;
        foreach ($days as $day) {
            $worked += $day->workedMinutes()->value();
        }

        $aboveReference = max(0, $worked - self::SEUIL_REFERENCE);
        $rtt = min($aboveReference, self::RTT_MAX);
        $overtime = max(0, $worked - self::SEUIL_BASCULE);

        return new WeekFact(
            $isoYear,
            $isoWeek,
            Minutes::of($worked),
            Minutes::of($rtt),
            Minutes::of($overtime),
            \count($days),
        );
    }

    /**
     * Détermine la semaine ISO commune aux journées et refuse tout mélange :
     * l'agrégat porte sur une semaine et une seule. Une semaine vide vaut (0, 0).
     *
     * @return array{int, int}
     */
    private function resolveIsoWeek(DayFact ...$days): array
    {
        if ([] === $days) {
            return [0, 0];
        }

        $isoYear = (int) $days[0]->date()->format('o');
        $isoWeek = (int) $days[0]->date()->format('W');

        foreach ($days as $day) {
            if ((int) $day->date()->format('o') !== $isoYear
                || (int) $day->date()->format('W') !== $isoWeek) {
                throw new \InvalidArgumentException(
                    'Un agrégat hebdomadaire ne peut réunir que des journées d\'une même semaine ISO.',
                );
            }
        }

        return [$isoYear, $isoWeek];
    }
}
