<?php

declare(strict_types=1);

namespace App\Domain\Projection;

use App\Domain\Time\Minutes;

/**
 * L'estimation de l'heure de sortie d'une journée en cours, et ses composantes.
 *
 * Objet dérivé et immuable, produit par {@see LeaveTimeCalculator}.
 */
final readonly class LeaveEstimate
{
    public function __construct(
        private Minutes $expectedLeave,
        private Minutes $morningWorked,
        private Minutes $breakDuration,
        private Minutes $breakPenalty,
        private Minutes $objective,
    ) {
    }

    /** Heure de sortie estimée, comme instant de la journée. */
    public function expectedLeave(): Minutes
    {
        return $this->expectedLeave;
    }

    public function morningWorked(): Minutes
    {
        return $this->morningWorked;
    }

    public function breakDuration(): Minutes
    {
        return $this->breakDuration;
    }

    /** Minutes ajoutées à la sortie au titre d'une pause trop courte. */
    public function breakPenalty(): Minutes
    {
        return $this->breakPenalty;
    }

    /** Objectif de temps de travail visé (7 h 24 par défaut). */
    public function objective(): Minutes
    {
        return $this->objective;
    }
}
