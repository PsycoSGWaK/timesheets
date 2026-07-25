<?php

declare(strict_types=1);

namespace App\Domain\Day;

/**
 * Ce qu'une journée peut révéler d'anormal une fois recalculée.
 *
 * Une anomalie n'empêche jamais le calcul : la journée est toujours valorisée,
 * l'anomalie sert à signaler qu'une vérification ou une régularisation attend
 * (spec §4.2).
 */
enum DailyAnomaly: string
{
    /** Nombre impair de pointages : un badgeage manque, le dernier reste orphelin. */
    case BadgeageManquant = 'badgeage_manquant';

    /** Pause hors de la fenêtre 11h30–14h00 : défaut de pointage constaté par l'employeur. */
    case PauseHorsFenetre = 'pause_hors_fenetre';

    /** Pause de moins de 30 min : la différence est retranchée (décompte appliqué, on informe). */
    case PauseTropCourte = 'pause_trop_courte';

    /** Un intervalle dont la fin précède le début : poste à cheval sur minuit ou faute de saisie. */
    case IntervalleNegatif = 'intervalle_negatif';

    public function label(): string
    {
        return match ($this) {
            self::BadgeageManquant => 'Badgeage manquant',
            self::PauseHorsFenetre => 'Pause hors de la fenêtre 11h30–14h00',
            self::PauseTropCourte => 'Pause de moins de 30 minutes',
            self::IntervalleNegatif => 'Intervalle négatif (fin avant début)',
        };
    }
}
