<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation;

use App\Domain\Time\Minutes;

/**
 * Le rapprochement d'une journée : notre recalcul face au relevé d'ADP, et le verdict.
 *
 * Objet dérivé et immuable, produit par {@see ReconciliationDetector}.
 */
final readonly class DayReconciliation
{
    public function __construct(
        private \DateTimeImmutable $date,
        private Minutes $ourMinutes,
        private ?Minutes $employerMinutes,
        private ?Minutes $delta,
        private ReconciliationStatus $status,
    ) {
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    /** Notre temps de travail recalculé. */
    public function ourMinutes(): Minutes
    {
        return $this->ourMinutes;
    }

    /** Le total relevé chez ADP, ou null en l'absence de relevé. */
    public function employerMinutes(): ?Minutes
    {
        return $this->employerMinutes;
    }

    /** Écart signé (notre total − celui d'ADP), ou null quand la comparaison n'a pas lieu. */
    public function delta(): ?Minutes
    {
        return $this->delta;
    }

    public function status(): ReconciliationStatus
    {
        return $this->status;
    }

    /** Vrai seulement quand l'écart appelle une action : divergence ou journée à 0:00. */
    public function needsAttention(): bool
    {
        return ReconciliationStatus::Divergent === $this->status
            || ReconciliationStatus::EmployerZero === $this->status;
    }
}
