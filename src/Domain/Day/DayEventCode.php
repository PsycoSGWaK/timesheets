<?php

declare(strict_types=1);

namespace App\Domain\Day;

use App\Domain\Time\Minutes;
use App\Entity\Settings;

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

    /**
     * La journée pleine que vaut ce code, selon le paramétrage de l'utilisateur. Le TT
     * est du travail réel : il suit la journée effective (37h/5j). Les autres codes
     * sont des absences, comptées sur la base contractuelle (35h/5j) — deux références
     * distinctes, confirmées par Guillaume, à ne pas confondre.
     */
    public function referenceDay(Settings $settings): Minutes
    {
        return match ($this) {
            self::Teletravail => $settings->journeeReferenceEffective(),
            default => $settings->journeeReferenceContractuelle(),
        };
    }
}
