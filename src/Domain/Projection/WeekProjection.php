<?php

declare(strict_types=1);

namespace App\Domain\Projection;

use App\Domain\Time\Minutes;

/**
 * L'état d'avancement d'une semaine en cours : ce qui est fait, ce qu'il reste avant
 * la bascule des heures supplémentaires, et la cible quotidienne pour y arriver.
 *
 * Objet dérivé et immuable, produit par {@see WeekProjectionCalculator}.
 */
final readonly class WeekProjection
{
    public function __construct(
        private Minutes $workedSoFar,
        private Minutes $objective,
        private Minutes $remainingToObjective,
        private int $remainingWorkingDays,
        private Minutes $targetPerRemainingDay,
        private Minutes $overtimeSoFar,
    ) {
    }

    public function workedSoFar(): Minutes
    {
        return $this->workedSoFar;
    }

    /** Objectif hebdomadaire visé (37 h par défaut, seuil de bascule en heures sup). */
    public function objective(): Minutes
    {
        return $this->objective;
    }

    /** Temps restant à accomplir avant d'atteindre l'objectif. */
    public function remainingToObjective(): Minutes
    {
        return $this->remainingToObjective;
    }

    public function remainingWorkingDays(): int
    {
        return $this->remainingWorkingDays;
    }

    /** Temps à faire chaque jour ouvré restant, en répartition égale. */
    public function targetPerRemainingDay(): Minutes
    {
        return $this->targetPerRemainingDay;
    }

    /** Temps déjà accompli au-delà de l'objectif : des heures supplémentaires. */
    public function overtimeSoFar(): Minutes
    {
        return $this->overtimeSoFar;
    }

    public function objectiveReached(): bool
    {
        return 0 === $this->remainingToObjective->value();
    }
}
