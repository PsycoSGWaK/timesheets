<?php

declare(strict_types=1);

namespace App\Domain\Week;

use App\Domain\Time\Minutes;

/**
 * Le résultat d'agrégation d'une semaine ISO : le total travaillé et sa répartition
 * entre acquisition RTT et heures supplémentaires.
 *
 * C'est la couche que le proto Excel ne modélisait pas (défaut #11) et sans laquelle
 * l'acquisition des RTT ne peut structurellement pas être juste (spec §4.3).
 *
 * Objet dérivé et immuable, produit par {@see WeeklyCalculator}. Non persisté.
 */
final readonly class WeekFact
{
    public function __construct(
        private int $isoYear,
        private int $isoWeek,
        private Minutes $workedMinutes,
        private Minutes $rttAcquired,
        private Minutes $overtimeMinutes,
        private int $dayCount,
    ) {
    }

    /** Année ISO (format « o » : peut différer de l'année civile en début/fin d'année). */
    public function isoYear(): int
    {
        return $this->isoYear;
    }

    public function isoWeek(): int
    {
        return $this->isoWeek;
    }

    /** Temps de travail accompli sur la semaine. */
    public function workedMinutes(): Minutes
    {
        return $this->workedMinutes;
    }

    /** Minutes portées au compteur RTT (plafonnées à 2 h par semaine). */
    public function rttAcquired(): Minutes
    {
        return $this->rttAcquired;
    }

    /** Heures supplémentaires : tout ce qui dépasse 37 h une fois les RTT servis. */
    public function overtimeMinutes(): Minutes
    {
        return $this->overtimeMinutes;
    }

    public function dayCount(): int
    {
        return $this->dayCount;
    }
}
