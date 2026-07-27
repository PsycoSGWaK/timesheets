<?php

declare(strict_types=1);

namespace App\Domain\Projection;

use App\Domain\Time\Minutes;
use App\Entity\Settings;

/**
 * Répond à la question quotidienne : « à quelle heure puis-je partir ? » (spec §4.6).
 *
 *   sortie = retour_de_pause + objectif + pénalité_pause − travail_du_matin
 *
 * La pénalité de pause — max(0, pause minimale − durée de pause) — est la correction
 * qu'aucun calcul mental ne fait spontanément, et qui peut valoir jusqu'à la pause
 * minimale par jour. La formule est alignée sur l'horaire théorique affiché par ADP.
 *
 * L'objectif par défaut vient du paramétrage de l'utilisateur ({@see Settings}) ;
 * il reste ajustable au cas par cas via le dernier paramètre.
 */
final class LeaveTimeCalculator
{
    public function estimate(
        Minutes $morningStart,
        Minutes $lunchDeparture,
        Minutes $lunchReturn,
        Settings $settings,
        ?Minutes $objective = null,
    ): LeaveEstimate {
        $objective ??= $settings->journeeReferenceEffective();

        $morningWorked = $lunchDeparture->minus($morningStart);
        $breakDuration = $lunchReturn->minus($lunchDeparture);
        $breakPenalty = $settings->pauseMinimale()->minus($breakDuration)->clampToZero();

        $expectedLeave = $lunchReturn
            ->plus($objective)
            ->plus($breakPenalty)
            ->minus($morningWorked);

        return new LeaveEstimate($expectedLeave, $morningWorked, $breakDuration, $breakPenalty, $objective);
    }
}
