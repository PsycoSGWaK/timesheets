<?php

declare(strict_types=1);

namespace App\Domain\Work;

use App\Domain\Day\DayFact;
use App\Domain\Reconciliation\DayReconciliation;
use App\Domain\Time\Minutes;
use App\Entity\DayEvent;

/**
 * L'objet domaine d'une journée, reconstruit à la volée pour l'affichage : il réunit
 * notre recalcul ({@see DayFact}), le dernier relevé d'ADP, l'événement du jour
 * éventuel, et le verdict du rapprochement ({@see DayReconciliation}).
 *
 * Non persisté : c'est un assemblage de données déjà stockées ailleurs (les pointages),
 * produit par {@see WorkWeekAssembler}.
 */
final readonly class WorkDay
{
    public function __construct(
        private \DateTimeImmutable $date,
        private DayFact $dayFact,
        private ?Minutes $employerReading,
        private DayReconciliation $reconciliation,
        private ?DayEvent $event = null,
    ) {
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function dayFact(): DayFact
    {
        return $this->dayFact;
    }

    /** Dernier total relevé chez ADP pour ce jour, ou null. */
    public function employerReading(): ?Minutes
    {
        return $this->employerReading;
    }

    public function reconciliation(): DayReconciliation
    {
        return $this->reconciliation;
    }

    public function event(): ?DayEvent
    {
        return $this->event;
    }
}
