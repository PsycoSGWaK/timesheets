<?php

declare(strict_types=1);

namespace App\Domain\Day;

/**
 * Les événements d'une journée exprimés en jours (1 ou 0,5), spec §2.
 *
 * Les événements exprimés en heures (HS, HV, Abs — « à poser ») ne sont pas couverts
 * ici : ils relèvent des compteurs (`BalanceLedger`, hors périmètre pour l'instant).
 */
enum DayEventCode: string
{
    case CongePaye = 'CP';
    case CongeAnciennete = 'CA';
    case Rtt = 'RTT';
    case JourFerie = 'JF';
    case Teletravail = 'TT';

    public function label(): string
    {
        return match ($this) {
            self::CongePaye => 'Congé payé',
            self::CongeAnciennete => 'Congé ancienneté',
            self::Rtt => 'RTT posé',
            self::JourFerie => 'Jour férié',
            self::Teletravail => 'Télétravail',
        };
    }
}
