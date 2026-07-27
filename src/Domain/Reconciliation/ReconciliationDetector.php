<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation;

use App\Domain\Time\Minutes;

/**
 * Compare notre recalcul d'une journée au total relevé chez ADP, et en tire un verdict.
 *
 * C'est la raison d'être de l'outil : sans recalcul indépendant, une journée entière
 * peut disparaître du décompte de l'employeur sans que rien ne le signale (source-adp §4).
 *
 * Une garde essentielle : ADP ne consolide ses totaux qu'après minuit. Tant qu'une
 * journée n'est pas passée, son total n'est pas stabilisé — le détecteur reste alors
 * silencieux (statut « en attente ») plutôt que d'alerter chaque soir sans motif (§4bis).
 *
 * Un jour de repos déclaré dans le paramétrage (spec du 28/07/2026) n'attend rien :
 * aucune comparaison n'a de sens, même si un relevé ADP existe ou qu'un pointage
 * réel a été badgé ce jour-là.
 */
final class ReconciliationDetector
{
    public function reconcile(
        \DateTimeImmutable $date,
        Minutes $ourMinutes,
        ?Minutes $employerMinutes,
        \DateTimeImmutable $today,
        bool $isRestDay = false,
    ): DayReconciliation {
        $date = $date->setTime(0, 0, 0, 0);

        if ($isRestDay) {
            return new DayReconciliation($date, $ourMinutes, $employerMinutes, null, ReconciliationStatus::Repos);
        }

        if (null === $employerMinutes) {
            return new DayReconciliation($date, $ourMinutes, null, null, ReconciliationStatus::NoReading);
        }

        $consolidated = $date < $today->setTime(0, 0, 0, 0);
        if (!$consolidated) {
            return new DayReconciliation($date, $ourMinutes, $employerMinutes, null, ReconciliationStatus::Pending);
        }

        $delta = $ourMinutes->minus($employerMinutes);

        $status = match (true) {
            0 === $employerMinutes->value() && $ourMinutes->value() > 0 => ReconciliationStatus::EmployerZero,
            0 === $delta->value() => ReconciliationStatus::Aligned,
            default => ReconciliationStatus::Divergent,
        };

        return new DayReconciliation($date, $ourMinutes, $employerMinutes, $delta, $status);
    }
}
