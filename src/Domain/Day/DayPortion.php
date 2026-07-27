<?php

declare(strict_types=1);

namespace App\Domain\Day;

use App\Domain\Time\Minutes;

/**
 * La portion d'une journée que couvre un événement : jour plein ou demi-journée
 * (spec §2 — les événements en jours se posent en 1 ou 0,5).
 */
enum DayPortion: string
{
    case Full = 'full';
    case Half = 'half';

    /** Applique la portion à une durée de référence (typiquement la journée de 7h24). */
    public function of(Minutes $reference): Minutes
    {
        return match ($this) {
            self::Full => $reference,
            self::Half => Minutes::of(intdiv($reference->value(), 2)),
        };
    }
}
