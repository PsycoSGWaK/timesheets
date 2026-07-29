<?php

declare(strict_types=1);

namespace App\Week;

use App\Domain\Projection\LeaveTimeCalculator;
use App\Entity\PunchEvent;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\PunchEventRepository;

/**
 * Charge le panneau d'édition d'une journée : ses quatre créneaux (Matin/Midi/
 * Après-midi/Soir, spec §1.1) et la projection « quand partir » dès que les trois
 * premiers sont connus. Utilisé à la fois par le panneau intégré à « Ma semaine »
 * et par {@see \App\Controller\DayController} pour la logique d'enregistrement.
 */
final class DayEditPanelLoader
{
    /** Champ du formulaire => rang du pointage. */
    public const FIELDS = ['matin' => 1, 'midi' => 2, 'apres_midi' => 3, 'soir' => 4];

    public function __construct(
        private readonly PunchEventRepository $punches,
        private readonly LeaveTimeCalculator $leaveTimeCalculator,
    ) {
    }

    public function load(User $user, \DateTimeImmutable $day, Settings $settings): DayEditPanel
    {
        $byRang = $this->punchesByRang($user, $day);

        $slots = [];
        foreach (self::FIELDS as $field => $rang) {
            $punch = $byRang[$rang] ?? null;
            $slots[] = [
                'field' => $field,
                'value' => $punch?->time()->toClock() ?? '',
                'readonly' => $punch?->isProbative() ?? false,
            ];
        }

        $estimate = isset($byRang[1], $byRang[2], $byRang[3])
            ? $this->leaveTimeCalculator->estimate($byRang[1]->time(), $byRang[2]->time(), $byRang[3]->time(), $settings)
            : null;

        return new DayEditPanel($day, $slots, $estimate);
    }

    /**
     * @return array<int, PunchEvent> le pointage de chaque rang occupé, 1 à 4
     */
    public function punchesByRang(User $user, \DateTimeImmutable $day): array
    {
        $byRang = [];
        foreach ($this->punches->findByDates($user, [$day]) as $punch) {
            $byRang[$punch->rang()] = $punch;
        }

        return $byRang;
    }
}
