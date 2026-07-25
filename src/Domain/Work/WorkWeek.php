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
        private array $days,
    ) {
    }

    public function weekFact(): WeekFact
    {
        return $this->weekFact;
    }

    /**
     * @return list<WorkDay>
     */
    public function days(): array
    {
        return $this->days;
    }

    /**
     * @return list<WorkDay> uniquement les journées dont le rapprochement appelle une action
     */
    public function daysNeedingAttention(): array
    {
        return array_values(array_filter(
            $this->days,
            static fn (WorkDay $day): bool => $day->reconciliation()->needsAttention(),
        ));
    }
}
