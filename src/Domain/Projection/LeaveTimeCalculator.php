<?php

declare(strict_types=1);

namespace App\Domain\Projection;

use App\Domain\Time\Minutes;

/**
 * Répond à la question quotidienne : « à quelle heure puis-je partir ? » (spec §4.6).
 *
 *   sortie = retour_de_pause + objectif + pénalité_pause − travail_du_matin
 *
 * La pénalité de pause — max(0, 30 min − durée de pause) — est la correction qu'aucun
 * calcul mental ne fait spontanément, et qui peut valoir jusqu'à 30 min par jour.
 * La formule est alignée sur l'horaire théorique affiché par ADP.
 */
final class LeaveTimeCalculator
{
    private const PAUSE_MINIMALE = 30;
    private const OBJECTIF_DEFAUT = 7 * 60 + 24; // 7 h 24

    public function estimate(
        Minutes $morningStart,
        Minutes $lunchDeparture,
        Minutes $lunchReturn,
        ?Minutes $objective = null,
    ): LeaveEstimate {
        $objective ??= Minutes::of(self::OBJECTIF_DEFAUT);

        $morningWorked = $lunchDeparture->minus($morningStart);
        $breakDuration = $lunchReturn->minus($lunchDeparture);
        $breakPenalty = Minutes::of(self::PAUSE_MINIMALE)->minus($breakDuration)->clampToZero();

        $expectedLeave = $lunchReturn
            ->plus($objective)
            ->plus($breakPenalty)
            ->minus($morningWorked);

        return new LeaveEstimate($expectedLeave, $morningWorked, $breakDuration, $breakPenalty, $objective);
    }
}
