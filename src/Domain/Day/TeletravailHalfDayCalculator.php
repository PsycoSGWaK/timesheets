<?php

declare(strict_types=1);

namespace App\Domain\Day;

use App\Domain\Time\Minutes;
use App\Entity\PunchEvent;
use App\Entity\Settings;

/**
 * Calcule le travail réel d'une demi-journée de télétravail (règle précise du
 * 28/07/2026, en remplacement de la simple moitié de la journée de référence) :
 *
 * - Matin : de l'arrivée (rang 1) au retour de pause (rang 3), plafonné à la fin
 *   de la fenêtre de pause ({@see Settings::fenetreFin()}, 14h00 par défaut).
 * - Après-midi : de la reprise (rang 2) à la fin de journée (rang 4), bornée entre
 *   le début de la fenêtre de pause ({@see Settings::fenetreDebut()}, 11h30 par
 *   défaut) et {@see Settings::finApresMidiTeletravail()} (16h00 par défaut).
 *
 * Sans les deux horaires nécessaires (jour vierge, ou saisie partielle), on retombe
 * sur la plage fixe de l'après-midi (fenêtreDébut → finApresMidiTeletravail, 4h30
 * par défaut) — même repli pour le matin que pour l'après-midi, choix de Guillaume.
 */
final class TeletravailHalfDayCalculator
{
    /**
     * @param array<int, PunchEvent> $punchesByRang le(s) pointage(s) du jour, indexé par rang
     */
    public function compute(DayHalf $half, array $punchesByRang, Settings $settings): Minutes
    {
        $fallback = $settings->finApresMidiTeletravail()->minus($settings->fenetreDebut());

        return match ($half) {
            DayHalf::Matin => $this->computeMorning($punchesByRang, $settings, $fallback),
            DayHalf::ApresMidi => $this->computeAfternoon($punchesByRang, $settings, $fallback),
        };
    }

    /**
     * @param array<int, PunchEvent> $punchesByRang
     */
    private function computeMorning(array $punchesByRang, Settings $settings, Minutes $fallback): Minutes
    {
        $arrivee = $punchesByRang[1] ?? null;
        $retourPause = $punchesByRang[3] ?? null;

        if (null === $arrivee || null === $retourPause) {
            return $fallback;
        }

        $fin = $retourPause->time()->min($settings->fenetreFin());

        return $fin->minus($arrivee->time())->clampToZero();
    }

    /**
     * @param array<int, PunchEvent> $punchesByRang
     */
    private function computeAfternoon(array $punchesByRang, Settings $settings, Minutes $fallback): Minutes
    {
        $reprise = $punchesByRang[2] ?? null;
        $finJournee = $punchesByRang[4] ?? null;

        if (null === $reprise || null === $finJournee) {
            return $fallback;
        }

        $debut = $reprise->time()->max($settings->fenetreDebut());
        $fin = $finJournee->time()->min($settings->finApresMidiTeletravail());

        return $fin->minus($debut)->clampToZero();
    }
}
