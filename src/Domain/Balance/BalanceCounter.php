<?php

declare(strict_types=1);

namespace App\Domain\Balance;

/**
 * Les compteurs documentés avec certitude (spec §2). `Dispo`, `Transfert` et `Boni`
 * existent dans le classeur d'origine mais leur alimentation n'est pas documentée
 * (§8.3, question restée ouverte) : les coder reviendrait à inventer une règle, ce
 * que le projet a déjà écarté une fois pour les majorations d'heures supplémentaires
 * (§4.5). Confirmé hors périmètre par Guillaume le 28/07/2026.
 */
enum BalanceCounter: string
{
    /** Se pose en jours, s'acquiert en heures (spec §4.3). */
    case Rtt = 'rtt';

    /** L'un des trois destins d'une heure supplémentaire. */
    case Recuperation = 'recuperation';
    case Variable = 'variable';
    case Paiement = 'paiement';

    public function label(): string
    {
        return match ($this) {
            self::Rtt => 'RTT',
            self::Recuperation => 'Récupération',
            self::Variable => 'Variable',
            self::Paiement => 'Paiement',
        };
    }
}
