<?php

declare(strict_types=1);

namespace App\Domain\Punch;

/**
 * D'où vient un pointage : collé depuis ADP, ou saisi à la main ?
 *
 * Dimension distincte de {@see PunchNature}. Les deux se combinent :
 *  - Adp + Réel            : le pointage collé depuis la pointeuse (le cas courant) ;
 *  - SaisieManuelle + Prévisionnel : une hypothèse de projection ;
 *  - SaisieManuelle + Réel : une correction — un badge réel qu'ADP a manqué et que
 *                            l'on comble à la main. Ce créneau reste et se signale
 *                            au détecteur d'écart, pour qu'un réimport ADP ne l'efface
 *                            pas en silence.
 *
 * La combinaison Adp + Prévisionnel n'existe pas : ADP ne fournit jamais d'hypothèse.
 */
enum PunchOrigin: string
{
    case Adp = 'adp';
    case SaisieManuelle = 'saisie_manuelle';

    public function isManual(): bool
    {
        return self::SaisieManuelle === $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::Adp => 'ADP',
            self::SaisieManuelle => 'Saisie manuelle',
        };
    }
}
