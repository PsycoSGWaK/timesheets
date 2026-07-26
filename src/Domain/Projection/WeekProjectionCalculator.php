<?php

declare(strict_types=1);

namespace App\Domain\Projection;

use App\Domain\Time\Minutes;

/**
 * Répond à la seconde question du mode prévisionnel : « où j'en suis sur la semaine ? »
 * (spec §4.6).
 *
 * À partir du temps déjà accompli et du nombre de jours ouvrés restants, il donne le
 * temps restant avant l'objectif hebdomadaire (37 h, le seuil de bascule en heures
 * supplémentaires) et la cible quotidienne, en répartition égale sur les jours restants.
 */
final class WeekProjectionCalculator
{
    private const OBJECTIF_DEFAUT = 37 * 60; // 37 h, seuil de bascule HS

    public function project(
        Minutes $workedSoFar,
        int $remainingWorkingDays,
        ?Minutes $objective = null,
    ): WeekProjection {
        if ($remainingWorkingDays < 0) {
            throw new \InvalidArgumentException(
                sprintf('Le nombre de jours restants ne peut être négatif, reçu %d.', $remainingWorkingDays),
            );
        }

        $objective ??= Minutes::of(self::OBJECTIF_DEFAUT);

        $remaining = max(0, $objective->value() - $workedSoFar->value());
        $overtime = max(0, $workedSoFar->value() - $objective->value());

        $targetPerDay = $remainingWorkingDays > 0 && $remaining > 0
            ? (int) round($remaining / $remainingWorkingDays)
            : 0;

        return new WeekProjection(
            $workedSoFar,
            $objective,
            Minutes::of($remaining),
            $remainingWorkingDays,
            Minutes::of($targetPerDay),
            Minutes::of($overtime),
        );
    }
}
