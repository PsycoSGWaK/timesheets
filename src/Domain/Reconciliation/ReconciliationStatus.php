<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation;

/**
 * L'état de la comparaison entre notre recalcul d'une journée et le total qu'ADP
 * en affiche.
 */
enum ReconciliationStatus: string
{
    /** Notre total et celui d'ADP coïncident. */
    case Aligned = 'aligned';

    /** Les deux totaux diffèrent sur une journée consolidée : écart à investiguer. */
    case Divergent = 'divergent';

    /** ADP affiche 0:00 alors que nous avons compté du temps : une journée perdue dans son décompte. */
    case EmployerZero = 'employer_zero';

    /** Journée non encore consolidée (aujourd'hui ou à venir) : ADP n'a pas stabilisé son total, on reste silencieux. */
    case Pending = 'pending';

    /** Aucun relevé ADP disponible pour la journée : rien à comparer. */
    case NoReading = 'no_reading';

    /** Jour de repos déclaré dans le paramétrage : rien n'est attendu, aucune comparaison n'a lieu. */
    case Repos = 'repos';

    public function label(): string
    {
        return match ($this) {
            self::Aligned => 'Conforme',
            self::Divergent => 'Écart à investiguer',
            self::EmployerZero => 'Journée perdue chez ADP (0:00)',
            self::Pending => 'En attente de consolidation',
            self::NoReading => 'Pas de relevé ADP',
            self::Repos => 'Jour de repos',
        };
    }
}
