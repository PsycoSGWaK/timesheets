<?php

declare(strict_types=1);

namespace App\Domain\Day;

/**
 * La moitié de journée concernée par un événement en demi-journée. Seul le TT
 * (télétravail) en a besoin pour l'instant : le calcul précis d'une demi-journée
 * dépend d'horaires réels différents selon qu'elle est le matin ou l'après-midi
 * ({@see TeletravailHalfDayCalculator}) — les autres codes (CP/CA/RTT/JF) restent
 * une simple moitié de leur référence, sans notion d'horaire.
 */
enum DayHalf: string
{
    case Matin = 'matin';
    case ApresMidi = 'apres_midi';

    public function label(): string
    {
        return match ($this) {
            self::Matin => 'Matin',
            self::ApresMidi => 'Après-midi',
        };
    }
}
