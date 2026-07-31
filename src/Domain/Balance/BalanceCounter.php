<?php

declare(strict_types=1);

namespace App\Domain\Balance;

/**
 * Les compteurs documentés avec certitude (spec §2). `Dispo`, `Transfert` et `Boni`
 * existent dans le classeur d'origine mais leur alimentation n'est pas documentée
 * (§8.3, question restée ouverte) : les coder reviendrait à inventer une règle, ce
 * que le projet a déjà écarté une fois pour les majorations d'heures supplémentaires
 * (§4.5). Confirmé hors périmètre par Guillaume le 28/07/2026.
 *
 * `Variable` a existé un temps comme troisième destin d'une heure supplémentaire,
 * retiré le 31/07/2026 : une heure sup est soit payée soit récupérée, jamais autre
 * chose.
 */
enum BalanceCounter: string
{
    /** Se pose en jours, s'acquiert en heures (spec §4.3). */
    case Rtt = 'rtt';

    /** Les deux destins d'une heure supplémentaire. */
    case Recuperation = 'recuperation';
    case Paiement = 'paiement';

    public function label(): string
    {
        return match ($this) {
            self::Rtt => 'RTT',
            self::Recuperation => 'Récupération',
            self::Paiement => 'Paiement',
        };
    }
}
