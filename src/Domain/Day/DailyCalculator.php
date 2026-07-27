<?php

declare(strict_types=1);

namespace App\Domain\Day;

use App\Domain\Time\Minutes;
use App\Entity\PunchEvent;
use App\Entity\Settings;

/**
 * Recalcule une journée à partir de ses pointages, indépendamment d'ADP.
 *
 * Les pointages s'apparient dans l'ordre du rang (entrée, sortie, entrée, sortie) —
 * l'ordre chronologique de badgeage, jamais un tri à l'horloge : c'est ce qui permet
 * de repérer un intervalle dont la fin précède le début (défaut #4 du proto).
 *
 * Les seuils appliqués (pause minimale, fenêtre autorisée) viennent du paramétrage
 * de l'utilisateur ({@see Settings}), plus des constantes en dur.
 */
final class DailyCalculator
{
    public function calculate(\DateTimeImmutable $date, Settings $settings, PunchEvent ...$punches): DayFact
    {
        usort($punches, static fn (PunchEvent $a, PunchEvent $b): int => $a->rang() <=> $b->rang());

        /** @var array<string, DailyAnomaly> $anomalies indexé par valeur pour dédupliquer */
        $anomalies = [];

        if (0 !== \count($punches) % 2) {
            $anomalies[DailyAnomaly::BadgeageManquant->value] = DailyAnomaly::BadgeageManquant;
        }

        $pairCount = \intdiv(\count($punches), 2);

        $gross = Minutes::of(0);
        for ($i = 0; $i < $pairCount; ++$i) {
            $span = $punches[2 * $i + 1]->time()->minus($punches[2 * $i]->time());
            if ($span->isNegative()) {
                // Intervalle incohérent : il ne contribue pas, pour ne pas fausser le total.
                $anomalies[DailyAnomaly::IntervalleNegatif->value] = DailyAnomaly::IntervalleNegatif;
                continue;
            }
            $gross = $gross->plus($span);
        }

        $breakDuration = null;
        $penalty = Minutes::of(0);

        if ($pairCount >= 2) {
            $breakStart = $punches[1]->time(); // sortie déjeuner (Midi)
            $breakEnd = $punches[2]->time();   // retour de pause (Après-Midi)
            $duration = $breakEnd->minus($breakStart);

            if (!$duration->isNegative()) {
                $breakDuration = $duration;

                if ($breakStart->value() < $settings->fenetreDebut()->value()
                    || $breakEnd->value() > $settings->fenetreFin()->value()) {
                    $anomalies[DailyAnomaly::PauseHorsFenetre->value] = DailyAnomaly::PauseHorsFenetre;
                }

                if ($duration->value() < $settings->pauseMinimale()->value()) {
                    $anomalies[DailyAnomaly::PauseTropCourte->value] = DailyAnomaly::PauseTropCourte;
                    $penalty = $settings->pauseMinimale()->minus($duration)->clampToZero();
                }
            }
        }

        $worked = $gross->minus($penalty)->clampToZero();

        return new DayFact(
            $date->setTime(0, 0, 0, 0),
            $gross,
            $breakDuration,
            $penalty,
            $worked,
            array_values($anomalies),
        );
    }
}
