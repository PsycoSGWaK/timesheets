<?php

declare(strict_types=1);

namespace App\Domain\Work;

use App\Domain\Week\WeekFact;

/**
 * L'objet domaine d'une semaine pour l'affichage : ses sept journées assemblées et
 * l'agrégat hebdomadaire (total, RTT, heures supplémentaires).
 *
 * Non persisté, produit par {@see WorkWeekAssembler}.
 */
final readonly class WorkWeek
{
    /**
     * @param list<WorkDay> $days
     */
    public function __construct(
        private WeekFact $weekFact,
        private WeekFact $estimatedWeekFact,
        private array $days,
    ) {
    }

    public function weekFact(): WeekFact
    {
        return $this->weekFact;
    }

    /**
     * Agrégat basé sur « Nous » (réel, prévisionnel en secours) : une projection
     * affichée à part, qui n'alimente jamais RTT/heures sup/"Travaillé".
     */
    public function estimatedWeekFact(): WeekFact
    {
        return $this->estimatedWeekFact;
    }

    /**
     * @return list<WorkDay>
     */
    public function days(): array
    {
        return $this->days;
    }
}
