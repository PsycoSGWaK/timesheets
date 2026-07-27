<?php

declare(strict_types=1);

namespace App\Domain\Week;

use App\Domain\Day\DayFact;
use App\Domain\Time\Minutes;
use App\Entity\Settings;

/**
 * Agrège les journées d'une même semaine ISO et applique la règle d'acquisition
 * des RTT (spec §4.3), qui se joue sur le total hebdomadaire et non sur la journée :
 *
 *   - ≤ 35 h            : rien ;
 *   - 35 h → 37 h       : le surplus alimente le compteur RTT, plafonné à 2 h ;
 *   - > 37 h            : 2 h en RTT, et tout ce qui dépasse 37 h devient des heures sup.
 *
 * Les seuils hebdomadaires (35h, 37h) se déduisent des journées de référence du
 * paramétrage de l'utilisateur ({@see Settings::weeklyReference()},
 * {@see Settings::weeklyBascule()}) ; le plafond RTT en vient directement.
 */
final class WeeklyCalculator
{
    public function aggregate(Settings $settings, DayFact ...$days): WeekFact
    {
        [$isoYear, $isoWeek] = $this->resolveIsoWeek(...$days);

        $worked = 0;
        foreach ($days as $day) {
            $worked += $day->workedMinutes()->value();
        }

        $aboveReference = max(0, $worked - $settings->weeklyReference()->value());
        $rtt = min($aboveReference, $settings->rttMax()->value());
        $overtime = max(0, $worked - $settings->weeklyBascule()->value());

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
